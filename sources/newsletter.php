<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    newsletter
 */

/**
 * Script to read in an e-mailed ticket/reply.
 */
function incoming_bounced_email_script()
{
    if (!GOOGLE_APPENGINE) {
        return;
    }

    if (!gae_is_admin()) {
        return;
    }

    $bounce_email = file_get_contents('php://input');

    $matches = array();
    if (preg_match('#^From: .*([^ ]+@[^ ]+)#m', $bounce_email, $matches) != 0) {
        $email = $matches[1];

        $id = $GLOBALS['SITE_DB']->query_select_value_if_there('newsletter_subscribers', 'id', array('email' => $email));
        if (!is_null($id)) {
            delete_newsletter_subscriber($id);
        }
    }
}

/**
 * Add to the newsletter, in the simplest way.
 * No authorisation support here, checks it works only for non-subscribed or non-confirmed members.
 *
 * @param  EMAIL $email The email address of the subscriber
 * @param  integer $interest_level The interest level
 * @range  1 4
 * @param  ?LANGUAGE_NAME $language The language (null: users)
 * @param  boolean $get_confirm_mail Whether to require a confirmation mail
 * @param  ?AUTO_LINK $newsletter_id The newsletter to join (null: the first)
 * @param  string $forename Subscribers forename
 * @param  string $surname Subscribers surname
 * @return string Newsletter password
 */
function basic_newsletter_join($email, $interest_level = 4, $language = null, $get_confirm_mail = false, $newsletter_id = null, $forename = '', $surname = '')
{
    require_lang('newsletter');

    if (is_null($language)) {
        $language = user_lang();
    }
    if (is_null($newsletter_id)) {
        $newsletter_id = db_get_first_id();
    }

    $code_confirm = $GLOBALS['SITE_DB']->query_select_value_if_there('newsletter_subscribers', 'code_confirm', array('email' => $email));
    if (is_null($code_confirm)) {
        // New, set their details
        require_code('crypt');
        $password = get_rand_password();
        $salt = produce_salt();
        $code_confirm = $get_confirm_mail ? mt_rand(1, 9999999) : 0;
        add_newsletter_subscriber($email, time(), $code_confirm, ratchet_hash($password, $salt, PASSWORD_SALT), $salt, $language, $forename, $surname);
    } else {
        if ($code_confirm > 0) {
            // Was not confirmed, allow confirm mail to go again as if this was new, and update their details
            $id = $GLOBALS['SITE_DB']->query_select_value_if_there('newsletter_subscribers', 'id', array('email' => $email));
            if (!is_null($id)) {
                edit_newsletter_subscriber($id, $email, time(), null, null, null, $language, $forename, $surname);
            }
            $password = do_lang('NEWSLETTER_PASSWORD_ENCRYPTED');
        } else {
            // Already on newsletter and confirmed so don't allow tampering without authorisation, which this method can't do
            return do_lang('NA');
        }
    }

    // Send confirm email
    if ($get_confirm_mail) {
        $_url = build_url(array('page' => 'newsletter', 'type' => 'confirm', 'email' => $email, 'confirm' => $code_confirm), get_module_zone('newsletter'));
        $url = $_url->evaluate();
        $newsletter_url = build_url(array('page' => 'newsletter'), get_module_zone('newsletter'));
        $message = do_lang('NEWSLETTER_SIGNUP_TEXT', comcode_escape($url), comcode_escape($password), array($forename, $surname, $email, get_site_name(), $newsletter_url->evaluate()), $language);
        require_code('mail');
        mail_wrap(do_lang('NEWSLETTER_SIGNUP', null, null, null, $language), $message, array($email), null, '', '', 3, null, false, null, false, false, false, 'MAIL', true);
    }

    // Set subscription
    $GLOBALS['SITE_DB']->query_delete('newsletter_subscribe', array('newsletter_id' => $newsletter_id, 'email' => $email), '', 1);
    $GLOBALS['SITE_DB']->query_insert('newsletter_subscribe', array('newsletter_id' => $newsletter_id, 'the_level' => $interest_level, 'email' => $email), false, true); // race condition

    return $password;
}

/**
 * Send out the newsletter.
 *
 * @param  LONG_TEXT $message The newsletter message
 * @param  SHORT_TEXT $subject The newsletter subject
 * @param  LANGUAGE_NAME $language The language
 * @param  array $send_details A map describing what newsletters and newsletter levels the newsletter is being sent to
 * @param  BINARY $html_only Whether to only send in HTML format
 * @param  string $from_email Override the email address the mail is sent from (blank: staff address)
 * @param  string $from_name Override the name the mail is sent from (blank: site name)
 * @param  integer $priority The message priority (1=urgent, 3=normal, 5=low)
 * @range  1 5
 * @param  string $csv_data CSV data of extra subscribers (blank: none). This is in the same Composr newsletter CSV format that we export elsewhere.
 * @param  ID_TEXT $mail_template The template used to show the email
 * @return Tempcode UI
 */
function send_newsletter($message, $subject, $language, $send_details, $html_only = 0, $from_email = '', $from_name = '', $priority = 3, $csv_data = '', $mail_template = 'MAIL')
{
    require_lang('newsletter');

    // Put in archive
    $archive_map = array(
        'subject' => $subject,
        'newsletter' => $message,
        'language' => $language,
        'importance_level' => 1,
        'from_email' => $from_email,
        'from_name' => $from_name,
        'priority' => $priority,
        'template' => $mail_template,
        'html_only' => $html_only,
    );
    $message_id = $GLOBALS['SITE_DB']->query_select_value_if_there('newsletter_archive', 'id', $archive_map);
    if (is_null($message_id)) {
        $message_id = $GLOBALS['SITE_DB']->query_insert('newsletter_archive', $archive_map + array('date_and_time' => time()), true);
    }

    // Mark as done
    log_it('NEWSLETTER_SEND', $subject);
    set_value('newsletter_send_time', strval(time()));

    // Schedule the task
    require_code('tasks');
    return call_user_func_array__long_task(do_lang('NEWSLETTER_SEND'), get_screen_title('NEWSLETTER_SEND'), 'send_newsletter', array($message_id, $message, $subject, $language, $send_details, $html_only, $from_email, $from_name, $priority, $csv_data, $mail_template), false, get_param_integer('keep_send_immediately', 0) == 1, false);
}

/**
 * Find a group of people the newsletter will go to.
 *
 * @param  array $send_details A map describing what newsletters and newsletter levels the newsletter is being sent to
 * @param  LANGUAGE_NAME $language The language
 * @param  integer $start Start position in result set (results are returned in parallel for each category of result)
 * @param  integer $max Maximum records to return from each category
 * @param  boolean $get_raw_rows Whether to get raw rows rather than mailer-ready correspondance lists
 * @param  string $csv_data Serialized CSV data to also consider
 * @param  boolean $strict_level Whether to do exact level matching, rather than "at least" matching
 * @return array Returns a tuple of corresponding detail lists, emails,hashes,usernames,forenames,surnames,ids, and a record count for levels (depending on requests: csv, 1, <newsletterID>, g<groupID>) [record counts not returned if $start is not zero, for performance reasons]
 */
function newsletter_who_send_to($send_details, $language, $start, $max, $get_raw_rows = false, $csv_data = '', $strict_level = false)
{
    // Find who to send to
    $level = 0;
    $usernames = array();
    $forenames = array();
    $surnames = array();
    $emails = array();
    $ids = array();
    $hashes = array();
    $total = array();
    $raw_rows = array();

    // Standard newsletter subscribers
    $newsletters = $GLOBALS['SITE_DB']->query_select('newsletters', array('*'));
    foreach ($newsletters as $newsletter) {
        $this_level = array_key_exists(strval($newsletter['id']), $send_details) ? $send_details[strval($newsletter['id'])] : 0;
        if ($this_level != 0) {
            $where_lang = multi_lang() ? (db_string_equal_to('language', $language) . ' AND ') : '';
            $query = ' FROM ' . get_table_prefix() . 'newsletter_subscribe s LEFT JOIN ' . get_table_prefix() . 'newsletter_subscribers n ON n.email=s.email WHERE ' . $where_lang . 'code_confirm=0 AND s.newsletter_id=' . strval($newsletter['id']);
            if ($strict_level) {
                $query .= ' AND the_level=' . strval($this_level);
            } else {
                $query .= ' AND the_level>=' . strval($this_level);
            }
            $query .= ' ORDER BY n.id';

            $sql = 'SELECT n.id,n.email,the_password,n_forename,n_surname' . $query;
            $temp = $GLOBALS['SITE_DB']->query($sql, $max, $start);

            if ($start == 0) {
                $sql = 'SELECT COUNT(*)' . $query;
                $total[strval($newsletter['id'])] = $GLOBALS['SITE_DB']->query_value_if_there($sql);
            }

            foreach ($temp as $_temp) {
                if (!in_array($_temp['email'], $emails)) { // If not already added
                    if (!$get_raw_rows) {
                        $emails[] = $_temp['email'];
                        $forenames[] = $_temp['n_forename'];
                        $surnames[] = $_temp['n_surname'];
                        $username = trim($_temp['n_forename'] . ' ' . $_temp['n_surname']);
                        if ($username == '') {
                            $username = do_lang('NEWSLETTER_SUBSCRIBER', get_site_name());
                        }
                        $usernames[] = $username;
                        $ids[] = 'n' . strval($_temp['id']);
                        require_code('crypt');
                        $hashes[] = ratchet_hash($_temp['the_password'], 'xunsub');
                    } else {
                        $raw_rows[] = $_temp;
                    }
                }
            }
        }
        $level = max($level, $this_level);
    }

    // Conversr imports
    if (get_forum_type() == 'cns') {
        $where_lang = multi_lang() ? ('(' . db_string_equal_to('m_language', $language) . ' OR ' . db_string_equal_to('m_language', '') . ') AND ') : '';

        // Usergroups
        $groups = $GLOBALS['FORUM_DRIVER']->get_usergroup_list();
        foreach ($send_details as $_id => $is_on) {
            if ((is_string($_id)) && (substr($_id, 0, 1) == 'g') && ($is_on == 1)) {
                $id = intval(substr($_id, 1));
                $query = 'SELECT xxxxx  FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_members m LEFT JOIN ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_group_members g ON m.id=g.gm_member_id AND g.gm_validated=1 WHERE ' . db_string_not_equal_to('m_email_address', '') . ' AND ' . $where_lang . 'm_validated=1 AND gm_group_id=' . strval($id);
                if (get_option('allow_email_from_staff_disable') == '1') {
                    $query .= ' AND m_allow_emails=1';
                }
                $query .= ' AND m_is_perm_banned=0';
                $query .= ' UNION SELECT xxxxx FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_members m WHERE ' . db_string_not_equal_to('m_email_address', '') . ' AND ' . $where_lang . 'm_validated=1 AND m_primary_group=' . strval($id);
                if (get_option('allow_email_from_staff_disable') == '1') {
                    $query .= ' AND m_allow_emails=1';
                }
                $query .= ' AND m_is_perm_banned=0';
                $query .= ' ORDER BY id';
                $_rows = $GLOBALS['FORUM_DB']->query(str_replace('xxxxx', 'm.id,m.m_email_address,m.m_username', $query), $max, $start, false, true);
                if ($start == 0) {
                    $total['g' . strval($id)] = $GLOBALS['FORUM_DB']->query_value_if_there('SELECT (' . str_replace(' UNION ', ') + (', str_replace('xxxxx', 'COUNT(*)', $query)) . ')', false, true);
                }

                foreach ($_rows as $row) { // For each member
                    if (!in_array($row['m_email_address'], $emails)) { // If not already added
                        if (!$get_raw_rows) {
                            $emails[] = $row['m_email_address'];
                            $forenames[] = '';
                            $surnames[] = '';
                            $usernames[] = $row['m_username'];
                            $ids[] = 'm' . strval($row['id']);
                            $hashes[] = '';
                        } else {
                            $raw_rows[] = $row;
                        }
                    }
                }
            }
        }

        // *All* Conversr members
        if (array_key_exists('-1', $send_details) ? $send_details['-1'] : 0 == 1) {
            $query = ' FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_members WHERE ' . db_string_not_equal_to('m_email_address', '') . ' AND ' . $where_lang . 'm_validated=1';
            if (get_option('allow_email_from_staff_disable') == '1') {
                $query .= ' AND m_allow_emails=1';
            }
            $query .= ' AND m_is_perm_banned=0';
            $_rows = $GLOBALS['FORUM_DB']->query('SELECT id,m_email_address,m_username' . $query, $max, $start);
            if ($start == 0) {
                $total['-1'] = $GLOBALS['FORUM_DB']->query_value_if_there('SELECT COUNT(*)' . $query);
            }
            foreach ($_rows as $_temp) {
                if (!in_array($_temp['m_email_address'], $emails)) { // If not already added
                    if (!$get_raw_rows) {
                        $emails[] = $_temp['m_email_address'];
                        $forenames[] = '';
                        $surnames[] = '';
                        $usernames[] = $_temp['m_username'];
                        $ids[] = 'm' . strval($_temp['id']);
                        $hashes[] = '';
                    } else {
                        $raw_rows[] = $_temp;
                    }
                }
            }
        }
    }

    // From CSV
    if ($csv_data != '') {
        $_csv_data = unserialize($csv_data);

        $email_index = 0;
        $forename_index = 1;
        $surname_index = 2;
        $username_index = 3;
        $id_index = 4;
        $hash_index = 5;

        if ($start == 0) {
            $total['csv'] = 0;
        }

        $pos = 0;
        foreach ($_csv_data as $i => $csv_line) {
            if (($i <= 1) && (count($csv_line) >= 1) && (isset($csv_line[0])) && (strpos($csv_line[0], '@') === false) && (isset($csv_line[1])) && (strpos($csv_line[1], '@') === false)) {
                foreach ($csv_line as $j => $val) {
                    if (in_array(strtolower($val), array('e-mail', 'email', 'email address', 'e-mail address'))) {
                        $email_index = $j;
                    }
                    if (in_array(strtolower($val), array('forename', 'forenames', 'first name'))) {
                        $forename_index = $j;
                    }
                    if (in_array(strtolower($val), array('surname', 'surnames', 'last name'))) {
                        $surname_index = $j;
                    }
                    if (in_array(strtolower($val), array('username'))) {
                        $username_index = $j;
                    }
                    if (in_array(strtolower($val), array('id', 'identifier'))) {
                        $id_index = $j;
                    }
                    if (in_array(strtolower($val), array('hash', 'password', 'pass', 'code', 'secret'))) {
                        $hash_index = $j;
                    }
                }
                continue;
            }

            if ((count($csv_line) >= 1) && (!is_null($csv_line[$email_index])) && (strpos($csv_line[$email_index], '@') !== false)) {
                if (($pos >= $start) && ($pos - $start < $max)) {
                    if (!$get_raw_rows) {
                        $emails[] = $csv_line[$email_index];
                        $forenames[] = array_key_exists($forename_index, $csv_line) ? $csv_line[$forename_index] : '';
                        $surnames[] = array_key_exists($surname_index, $csv_line) ? $csv_line[$surname_index] : '';
                        $usernames[] = array_key_exists($username_index, $csv_line) ? $csv_line[$username_index] : '';
                        $ids[] = array_key_exists($id_index, $csv_line) ? $csv_line[$id_index] : '';
                        $hashes[] = array_key_exists($hash_index, $csv_line) ? $csv_line[$hash_index] : '';
                    } else {
                        $raw_rows[] = $csv_line;
                    }
                }
                if ($start == 0) {
                    $total['csv']++;
                }

                $pos++;
            }
        }
    }
    return array($emails, $hashes, $usernames, $forenames, $surnames, $ids, $total, $raw_rows);
}

/**
 * Sub in newsletter variables.
 *
 * @param  string $message The original newsletter message
 * @param  SHORT_TEXT $subject The newsletter subject
 * @param  SHORT_TEXT $forename Subscribers forename (blank: unknown)
 * @param  SHORT_TEXT $surname Subscribers surname (blank: unknown)
 * @param  SHORT_TEXT $name Subscribers name (or username)
 * @param  EMAIL $email_address Subscribers email address
 * @param  ID_TEXT $sendid Specially encoded ID of subscriber (begins either 'n' for newsletter subscriber, or 'm' for member - then has normal subscriber/member ID following)
 * @param  SHORT_TEXT $hash Double encoded password hash of subscriber (blank: can not unsubscribe by URL)
 * @return string The new newsletter message
 */
function newsletter_variable_substitution($message, $subject, $forename, $surname, $name, $email_address, $sendid, $hash)
{
    if ($hash == '') {
        $unsub_url = build_url(array('page' => 'members', 'type' => 'view'), get_module_zone('members'), null, false, false, true, 'tab__edit');
    } else {
        $unsub_url = build_url(array('page' => 'newsletter', 'type' => 'unsub', 'id' => substr($sendid, 1), 'hash' => $hash), get_module_zone('newsletter'), null, false, false, true);
    }

    $member_id = mixed();
    if (substr($sendid, 0, 1) == 'm') {
        $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_username($name);
        $name = $GLOBALS['FORUM_DRIVER']->get_displayname($name);
    }

    $vars = array(
        'title' => $subject,
        'forename' => $forename,
        'surname' => $surname,
        'name' => $name,
        'member_id' => is_null($member_id) ? '' : strval($member_id),
        'email_address' => $email_address,
        'sendid' => $sendid,
        'unsub_url' => $unsub_url,
    );

    foreach ($vars as $var => $sub) {
        $message = str_replace('{' . $var . '}', is_object($sub) ? $sub->evaluate() : $sub, $message);
        $message = str_replace('{' . $var . '*}', escape_html(is_object($sub) ? $sub->evaluate() : $sub), $message);
    }

    return $message;
}

/**
 * Generate a newsletter preview in full HTML and full text.
 *
 * @param  string $message The message
 * @param  string $subject The subject
 * @param  boolean $html_only HTML only
 * @param  string $forename Forename
 * @param  string $surname Surname
 * @param  string $name Name
 * @param  string $address Address
 * @param  string $sendid Send ID
 * @param  string $hash Password hash
 * @return array A triple: HTML version, Text version, Whether the e-mail has to be fully HTML
 */
function newsletter_preview($message, $subject, $html_only, $forename, $surname, $name, $address, $sendid, $hash)
{
    require_code('tempcode_compiler');

    // HTML message
    $message = newsletter_variable_substitution($message, $subject, $forename, $surname, $name, $address, $sendid, $hash);
    if (stripos($message, '<html') !== false) {
        $html_version = template_to_tempcode($message);

        $in_html = true;
    } else {
        require_code('media_renderer');
        push_media_mode(peek_media_mode() | MEDIA_LOWFI);
        $comcode_version = comcode_to_tempcode(static_evaluate_tempcode(template_to_tempcode($message)), get_member(), true);
        pop_media_mode();

        $html_version = do_template(
            'MAIL',
            array(
                '_GUID' => 'b081cf9104748b090f63b6898027985e',
                'TITLE' => $subject,
                'CSS' => css_tempcode(true, true, $comcode_version->evaluate()),
                'LANG' => get_site_default_lang(),
                'LOGOURL' => get_logo_url(''),
                'CONTENT' => $comcode_version
            ),
            null,
            false,
            null,
            '.tpl',
            'templates',
            $GLOBALS['FORUM_DRIVER']->get_theme('')
        );

        $in_html = $html_only;
    }

    // Text message
    $text_version = $html_only ? '' : comcode_to_clean_text(static_evaluate_tempcode(template_to_tempcode($message)));

    return array($html_version, $text_version, $in_html);
}

/**
 * Work out newsletter block list.
 *
 * @return array List of blocked email addresses (actually a map)
 */
function newsletter_block_list()
{
    $blocked = array();
    $block_path = get_custom_file_base() . '/uploads/website_specific/newsletter_blocked.csv';
    if (is_file($block_path)) {
        safe_ini_set('auto_detect_line_endings', '1');
        $myfile = fopen($block_path, 'rt');
        while (($row = fgetcsv($myfile, 1024)) !== false) {
            if ($row[0] != '') {
                $blocked[$row[0]] = true;
            }
        }
        fclose($myfile);
    }
    return $blocked;
}

/**
 * Make a newsletter.
 *
 * @param  SHORT_TEXT $title The title
 * @param  LONG_TEXT $description The description
 * @return AUTO_LINK The ID
 */
function add_newsletter($title, $description)
{
    require_code('global4');
    prevent_double_submit('ADD_NEWSLETTER', null, $title);

    $map = array();
    $map += insert_lang('title', $title, 2);
    $map += insert_lang('description', $description, 2);
    $id = $GLOBALS['SITE_DB']->query_insert('newsletters', $map, true);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('newsletter', strval($id), null, null, true);
    }

    log_it('ADD_NEWSLETTER', strval($id), $title);

    decache('main_newsletter_signup');

    return $id;
}

/**
 * Edit a newsletter.
 *
 * @param  AUTO_LINK $id The ID
 * @param  SHORT_TEXT $title The title
 * @param  LONG_TEXT $description The description
 */
function edit_newsletter($id, $title, $description)
{
    $_title = $GLOBALS['SITE_DB']->query_select_value('newsletters', 'title', array('id' => $id));
    $_description = $GLOBALS['SITE_DB']->query_select_value('newsletters', 'description', array('id' => $id));
    $map = array();
    $map += lang_remap('title', $_title, $title);
    $map += lang_remap('description', $_description, $description);
    $GLOBALS['SITE_DB']->query_update('newsletters', $map, array('id' => $id), '', 1);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('newsletter', strval($id));
    }

    log_it('EDIT_NEWSLETTER', strval($id), $_title);

    decache('main_newsletter_signup');
}

/**
 * Delete a newsletter.
 *
 * @param  AUTO_LINK $id The ID
 */
function delete_newsletter($id)
{
    $_title = $GLOBALS['SITE_DB']->query_select_value('newsletters', 'title', array('id' => $id));
    $_description = $GLOBALS['SITE_DB']->query_select_value('newsletters', 'description', array('id' => $id));

    $GLOBALS['SITE_DB']->query_delete('newsletters', array('id' => $id), '', 1);
    $GLOBALS['SITE_DB']->query_delete('newsletter_subscribe', array('newsletter_id' => $id));
    delete_lang($_title);
    delete_lang($_description);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resource_fs_moniker('newsletter', strval($id));
    }

    log_it('DELETE_NEWSLETTER', strval($id), get_translated_text($_title));

    decache('main_newsletter_signup');
}

/**
 * Make a periodic newsletter.
 *
 * @param  LONG_TEXT $subject Subject
 * @param  LONG_TEXT $message Message
 * @param  LANGUAGE_NAME $lang Language to send for
 * @param  LONG_TEXT $send_details The data sent in each newsletter
 * @param  BINARY $html_only Whether to send in HTML only
 * @param  SHORT_TEXT $from_email From address
 * @param  SHORT_TEXT $from_name From name
 * @param  SHORT_INTEGER $priority Priority
 * @param  LONG_TEXT $csv_data CSV data of who to send to
 * @param  SHORT_TEXT $frequency Send frequency
 * @set weekly biweekly monthly
 * @param  SHORT_INTEGER $day Weekday to send on
 * @param  BINARY $in_full Embed full articles
 * @param  ID_TEXT $template Mail template to use, e.g. MAIL
 * @param  ?TIME $last_sent When was last sent (null: now)
 * @return AUTO_LINK The ID
 */
function add_periodic_newsletter($subject, $message, $lang, $send_details, $html_only, $from_email, $from_name, $priority, $csv_data, $frequency, $day, $in_full = 0, $template = 'MAIL', $last_sent = null)
{
    require_code('global4');
    prevent_double_submit('ADD_PERIODIC_NEWSLETTER', null, $subject);

    if (is_null($last_sent)) {
        $last_sent = time();
    }

    $id = $GLOBALS['SITE_DB']->query_insert('newsletter_periodic', array(
        'np_subject' => $subject,
        'np_message' => $message,
        'np_lang' => $lang,
        'np_send_details' => $send_details,
        'np_html_only' => $html_only,
        'np_from_email' => $from_email,
        'np_from_name' => $from_name,
        'np_priority' => $priority,
        'np_csv_data' => $csv_data,
        'np_frequency' => $frequency,
        'np_day' => $day,
        'np_in_full' => $in_full,
        'np_template' => $template,
        'np_last_sent' => $last_sent,
    ), true);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('periodic_newsletter', strval($id), null, null, true);
    }

    log_it('ADD_PERIODIC_NEWSLETTER', strval($id), $subject);

    return $id;
}

/**
 * Edit a periodic newsletter.
 *
 * @param  AUTO_LINK $id The ID
 * @param  LONG_TEXT $subject Subject
 * @param  LONG_TEXT $message Message
 * @param  LANGUAGE_NAME $lang Language to send for
 * @param  LONG_TEXT $send_details The data sent in each newsletter
 * @param  BINARY $html_only Whether to send in HTML only
 * @param  SHORT_TEXT $from_email From address
 * @param  SHORT_TEXT $from_name From name
 * @param  SHORT_INTEGER $priority Priority
 * @param  LONG_TEXT $csv_data CSV data of who to send to
 * @param  SHORT_TEXT $frequency Send frequency
 * @set weekly biweekly monthly
 * @param  SHORT_INTEGER $day Weekday to send on
 * @param  BINARY $in_full Embed full articles
 * @param  ID_TEXT $template Mail template to use, e.g. MAIL
 * @param  ?TIME $last_sent When was last sent (null: don't change)
 */
function edit_periodic_newsletter($id, $subject, $message, $lang, $send_details, $html_only, $from_email, $from_name, $priority, $csv_data, $frequency, $day, $in_full, $template, $last_sent = null)
{
    $map = array(
        'np_subject' => $subject,
        'np_message' => $message,
        'np_lang' => $lang,
        'np_send_details' => $send_details,
        'np_html_only' => $html_only,
        'np_from_email' => $from_email,
        'np_from_name' => $from_name,
        'np_priority' => $priority,
        'np_csv_data' => $csv_data,
        'np_frequency' => $frequency,
        'np_day' => $day,
        'np_in_full' => $in_full,
        'np_template' => $template,
    );
    if (!is_null($last_sent)) {
        $map['np_last_sent'] = $last_sent;
    }
    $GLOBALS['SITE_DB']->query_update('newsletter_periodic', $map, array('id' => $id));

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('periodic_newsletter', strval($id));
    }

    log_it('EDIT_PERIODIC_NEWSLETTER', strval($id), $subject);
}

/**
 * Delete a periodic newsletter.
 *
 * @param  AUTO_LINK $id The ID
 */
function delete_periodic_newsletter($id)
{
    $subject = $GLOBALS['SITE_DB']->query_select_value('newsletter_periodic', 'np_subject', array('id' => $id));

    $GLOBALS['SITE_DB']->query_delete('newsletter_periodic', array('id' => $id));

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resource_fs_moniker('periodic_newsletter', strval($id));
    }

    log_it('DELETE_PERIODIC_NEWSLETTER', strval($id), $subject);
}

/**
 * Add a newsletter subscriber to the system (not to any particular newsletters though).
 *
 * @param  EMAIL $email The email address of the subscriber
 * @param  TIME $join_time The join time
 * @param  integer $code_confirm Confirm code
 * @param  ID_TEXT $password Newsletter password (hashed)
 * @param  ID_TEXT $salt Newsletter salt
 * @param  LANGUAGE_NAME $language The language
 * @param  string $forename Subscribers forename
 * @param  string $surname Subscribers surname
 * @return AUTO_LINK Subscriber ID
 */
function add_newsletter_subscriber($email, $join_time, $code_confirm, $password, $salt, $language, $forename, $surname)
{
    $GLOBALS['SITE_DB']->query_delete('newsletter_subscribers', array(
        'email' => $email,
    ));
    $id = $GLOBALS['SITE_DB']->query_insert('newsletter_subscribers', array(
        'email' => $email,
        'join_time' => $join_time,
        'code_confirm' => $code_confirm,
        'the_password' => $password,
        'pass_salt' => $salt,
        'language' => $language,
        'n_forename' => $forename,
        'n_surname' => $surname,
    ), true, true/*race condition*/);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('newsletter_subscriber', strval($id), null, null, true);
    }

    return $id;
}

/**
 * Add a newsletter subscriber to the system (not to any particular newsletters though).
 *
 * @param  AUTO_LINK $id Subscriber ID
 * @param  ?EMAIL $email The email address of the subscriber (null: don't change)
 * @param  ?TIME $join_time The join time (null: don't change)
 * @param  ?integer $code_confirm Confirm code (null: don't change)
 * @param  ?ID_TEXT $password Newsletter password (hashed) (null: don't change)
 * @param  ?ID_TEXT $salt Newsletter salt (null: don't change)
 * @param  ?LANGUAGE_NAME $language The language (null: don't change)
 * @param  ?string $forename Subscribers forename (null: don't change)
 * @param  ?string $surname Subscribers surname (null: don't change)
 */
function edit_newsletter_subscriber($id, $email = null, $join_time = null, $code_confirm = null, $password = null, $salt = null, $language = null, $forename = null, $surname = null)
{
    $map = array();
    if (!is_null($email)) {
        $map['email'] = $email;
    }
    if (!is_null($join_time)) {
        $map['join_time'] = $join_time;
    }
    if (!is_null($code_confirm)) {
        $map['code_confirm'] = $code_confirm;
    }
    if (!is_null($password)) {
        $map['the_password'] = $password;
    }
    if (!is_null($salt)) {
        $map['pass_salt'] = $salt;
    }
    if (!is_null($language)) {
        $map['language'] = $language;
    }
    if (!is_null($forename)) {
        $map['n_forename'] = $forename;
    }
    if (!is_null($surname)) {
        $map['n_surname'] = $surname;
    }

    $GLOBALS['SITE_DB']->query_update('newsletter_subscribers', $map, array('id' => $id), '', 1);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('newsletter_subscriber', strval($id));
    }
}

/**
 * Add a newsletter subscriber to the system (not to any particular newsletters though).
 *
 * @param  AUTO_LINK $id Subscriber ID
 */
function delete_newsletter_subscriber($id)
{
    $GLOBALS['SITE_DB']->query_delete('newsletter_subscribers', array('id' => $id), '', 1);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resource_fs_moniker('newsletter_subscriber', strval($id));
    }
}

/**
 * Remove bounced addresses from the newsletter / turn off staff e-mails on member accounts.
 *
 * @param  array $bounces List of e-mail addresses
 */
function remove_email_bounces($bounces)
{
    if (count($bounces) == 0) {
        return;
    }

    $delete_sql = '';
    $delete_sql_members = '';

    foreach ($bounces as $email_address) {
        if ($delete_sql != '') {
            $delete_sql .= ' OR ';
            $delete_sql_members .= ' OR ';
        }
        $delete_sql .= db_string_equal_to('email', $email_address);
        $delete_sql_members .= db_string_equal_to('m_email_address', $email_address);
    }

    $query = 'DELETE FROM ' . get_table_prefix() . 'newsletter_subscribers WHERE ' . $delete_sql;
    $GLOBALS['SITE_DB']->query($query);

    $query = 'DELETE FROM ' . get_table_prefix() . 'newsletter_subscribe WHERE ' . $delete_sql;
    $GLOBALS['SITE_DB']->query($query);

    if (get_forum_type() == 'cns') {
        $query = 'UPDATE ' . get_table_prefix() . 'f_members SET m_allow_emails_from_staff=0 WHERE ' . $delete_sql_members;
        $GLOBALS['FORUM_DB']->query($query);
    }
}
