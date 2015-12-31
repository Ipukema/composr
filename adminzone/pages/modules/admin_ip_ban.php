<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2015

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    securitylogging
 */

/**
 * Module page class.
 */
class Module_admin_ip_ban
{
    /**
     * Find details of the module.
     *
     * @return ?array Map of module info (null: module is disabled).
     */
    public function info()
    {
        $info = array();
        $info['author'] = 'Chris Graham';
        $info['organisation'] = 'ocProducts';
        $info['hacked_by'] = null;
        $info['hack_version'] = null;
        $info['version'] = 5;
        $info['locked'] = true;
        $info['update_require_upgrade'] = 1;
        return $info;
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('banned_ip');
        $GLOBALS['SITE_DB']->drop_table_if_exists('usersubmitban_member');
    }

    /**
     * Install the module.
     *
     * @param  ?integer $upgrade_from What version we're upgrading from (null: new install)
     * @param  ?integer $upgrade_from_hack What hack version we're upgrading from (null: new-install/not-upgrading-from-a-hacked-version)
     */
    public function install($upgrade_from = null, $upgrade_from_hack = null)
    {
        if (is_null($upgrade_from)) {
            $GLOBALS['SITE_DB']->create_table('banned_ip', array(
                'ip' => '*IP',
                'i_descrip' => 'LONG_TEXT',
                'i_ban_until' => '?TIME',
                'i_ban_positive' => 'BINARY',
            ));

            $GLOBALS['SITE_DB']->create_table('usersubmitban_member', array(
                'the_member' => '*MEMBER',
            ));
        }

        if ((!is_null($upgrade_from)) && ($upgrade_from < 5)) {
            $GLOBALS['SITE_DB']->add_table_field('banned_ip', 'i_ban_until', '?TIME');
            $GLOBALS['SITE_DB']->add_table_field('banned_ip', 'i_ban_positive', 'BINARY', 1);
        }
    }

    /**
     * Find entry-points available within this module.
     *
     * @param  boolean $check_perms Whether to check permissions.
     * @param  ?MEMBER $member_id The member to check permissions as (null: current user).
     * @param  boolean $support_crosslinks Whether to allow cross links to other modules (identifiable via a full-page-link rather than a screen-name).
     * @param  boolean $be_deferential Whether to avoid any entry-point (or even return null to disable the page in the Sitemap) if we know another module, or page_group, is going to link to that entry-point. Note that "!" and "browse" entry points are automatically merged with container page nodes (likely called by page-groupings) as appropriate.
     * @return ?array A map of entry points (screen-name=>language-code/string or screen-name=>[language-code/string, icon-theme-image]) (null: disabled).
     */
    public function get_entry_points($check_perms = true, $member_id = null, $support_crosslinks = true, $be_deferential = false)
    {
        return array(
            'browse' => array('IP_BANS', 'menu/adminzone/security/ip_ban'),
        );
    }

    public $title;
    public $test;

    /**
     * Module pre-run function. Allows us to know meta-data for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('submitban');

        set_helper_panel_tutorial('tut_censor');

        if ($type == 'browse') {
            $lookup_url = build_url(array('page' => 'admin_lookup'), get_module_zone('admin_lookup'));
            set_helper_panel_text(comcode_to_tempcode(do_lang('IP_BANNING_WILDCARDS', $lookup_url->evaluate())));
        }

        if ($type == 'browse') {
            $this->title = get_screen_title('IP_BANS');
        }

        if ($type == 'actual') {
            $this->title = get_screen_title('IP_BANS');
        }

        if ($type == 'syndicate_ip_ban') {
            $this->title = get_screen_title('SYNDICATE_TO_STOPFORUMSPAM');
        }

        if ($type == 'multi_ban') {
            $this->title = get_screen_title('BAN_MEMBER');
        }

        if ($type == 'toggle_ip_ban') {
            $ip = get_param_string('id');

            $test = ip_banned($ip, true);

            if (!$test) {
                $this->title = get_screen_title('IP_BANNED');
            } else {
                $this->title = get_screen_title('IP_UNBANNED');
            }

            $this->test = $test;
        }

        if ($type == 'toggle_member_ban') {
            $id = get_param_integer('id');

            $test = $GLOBALS['FORUM_DRIVER']->is_banned($id);

            if (!$test) {
                $this->title = get_screen_title('MEMBER_BANNED');
            } else {
                $this->title = get_screen_title('MEMBER_UNBANNED');
            }

            $this->test = $test;
        }

        if ($type == 'toggle_submitter_ban') {
            $id = get_param_integer('id');
            $test = $GLOBALS['SITE_DB']->query_select_value_if_there('usersubmitban_member', 'the_member', array('the_member' => $id));

            if (is_null($test)) {
                $this->title = get_screen_title('SUBMITTER_BANNED');
            } else {
                $this->title = get_screen_title('SUBMITTER_UNBANNED');
            }

            $this->test = $test;
        }

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        require_code('submit');

        // What are we doing?
        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            return $this->gui();
        }
        if ($type == 'actual') {
            return $this->actual();
        }
        if ($type == 'syndicate_ip_ban') {
            return $this->syndicate_ip_ban();
        }
        if ($type == 'toggle_ip_ban') {
            return $this->toggle_ip_ban();
        }
        if ($type == 'toggle_submitter_ban') {
            return $this->toggle_submitter_ban();
        }
        if ($type == 'toggle_member_ban') {
            return $this->toggle_member_ban();
        }
        if ($type == 'multi_ban') {
            return $this->multi_ban();
        }

        return new Tempcode();
    }

    /**
     * The UI for managing banned IPs.
     *
     * @return Tempcode The UI
     */
    public function gui()
    {
        $bans = '';
        $locked_bans = '';
        $rows = $GLOBALS['SITE_DB']->query('SELECT ip,i_descrip,i_ban_until FROM ' . get_table_prefix() . 'banned_ip WHERE i_ban_positive=1 AND (i_ban_until IS NULL' . ' OR i_ban_until>' . strval(time()) . ')');
        foreach ($rows as $row) {
            if (is_null($row['i_ban_until'])) {
                $bans .= $row['ip'] . ' ' . str_replace("\n", ' ', $row['i_descrip']) . "\n";
            } else {
                $locked_bans .= do_lang('SPAM_AUTO_BAN_TIMEOUT', $row['ip'], str_replace("\n", ' ', $row['i_descrip']), get_timezoned_date($row['i_ban_until'])) . "\n";
            }
        }

        $unbannable = '';
        $rows = $GLOBALS['SITE_DB']->query_select('unbannable_ip', array('ip', 'note'));
        foreach ($rows as $row) {
            $unbannable .= $row['ip'] . ' ' . $row['note'] . "\n";
        }

        $post_url = build_url(array('page' => '_SELF', 'type' => 'actual'), '_SELF');

        require_code('form_templates');

        list($warning_details, $ping_url) = handle_conflict_resolution();

        return do_template('IP_BAN_SCREEN', array(
            '_GUID' => '963d24852ba87e9aa84e588862bcfecb',
            'PING_URL' => $ping_url,
            'WARNING_DETAILS' => $warning_details,
            'TITLE' => $this->title,
            'BANS' => $bans,
            'LOCKED_BANS' => $locked_bans,
            'UNBANNABLE' => $unbannable,
            'URL' => $post_url,
        ));
    }

    /**
     * The actualiser for managing banned IPs.
     *
     * @return Tempcode The UI
     */
    public function actual()
    {
        require_code('failure');

        $rows = $GLOBALS['SITE_DB']->query('SELECT ip,i_descrip FROM ' . get_table_prefix() . 'banned_ip WHERE i_ban_until IS NULL'/*.' OR i_ban_until>'.strval(time())*/, null, null, false, true);
        $old_bans = collapse_1d_complexity('ip', $rows);
        $bans = post_param_string('bans');
        $_bans = explode("\n", $bans);
        foreach ($old_bans as $ban) {
            if (preg_match('#^' . preg_quote($ban, '#') . '(\s|$)#m', $bans) == 0) {
                remove_ip_ban($ban);
            }
        }
        $matches = array();
        foreach ($_bans as $ban) {
            if (trim($ban) == '') {
                continue;
            }
            preg_match('#^([^\s]+)(.*)$#', $ban, $matches);
            $ip = $matches[1];
            if (preg_match('#^[a-f0-9\.\*:]+$#U', $ip) == 0) {
                attach_message(do_lang_tempcode('IP_ADDRESS_NOT_VALID', $ip), 'warn');
            } else {
                if (!in_array($ip, $old_bans)) {
                    if ($ip == get_ip_address()) {
                        attach_message(do_lang_tempcode('WONT_BAN_SELF', $ip), 'warn');
                    } elseif ($ip == cms_srv('SERVER_ADDR')) {
                        attach_message(do_lang_tempcode('WONT_BAN_SERVER', $ip), 'warn');
                    } else {
                        ban_ip($ip, isset($matches[2]) ? trim($matches[2]) : '');
                        $old_bans[] = $ip;
                    }
                }
            }
        }

        $rows = $GLOBALS['SITE_DB']->query_select('unbannable_ip', array('ip'));
        $unbannable_already = collapse_1d_complexity('ip', $rows);
        $unbannable = post_param_string('unbannable');
        foreach ($unbannable_already as $ip) {
            if (preg_match('#^' . preg_quote($ip, '#') . '(\s|$)#m', $unbannable) == 0) {
                $GLOBALS['SITE_DB']->query_delete('unbannable_ip', array('ip' => $ip), '', 1);
                log_it('MADE_IP_BANNABLE', $ip);
            }
        }
        $_unbannable = explode("\n", $unbannable);
        foreach ($_unbannable as $str)
        {
            if (trim($str) == '') {
                continue;
            }
            preg_match('#^([^\s]+)(.*)$#', $str, $matches);
            $ip = $matches[1];
            if (preg_match('#^[a-f0-9\.]+$#U', $ip) == 0)
            {
                attach_message(do_lang_tempcode('IP_ADDRESS_NOT_VALID_MAKE_UNBANNABLE', $str), 'warn');
            } else
            {
                if (!in_array($ip, $unbannable_already))
                {
                    $GLOBALS['SITE_DB']->query_insert('unbannable_ip', array(
                        'ip' => $ip,
                        'note' => isset($matches[2]) ? $matches[2] : '',
                    ));
                    log_it('MADE_IP_UNBANNABLE', $matches[1]);
                    $unbannable_already[] = $ip;
                }
            }
        }

        // Show it worked / Refresh
        $refresh_url = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');
        return redirect_screen($this->title, $refresh_url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * The actualiser to toggle a member ban. Only works with Conversr.
     *
     * @return Tempcode The UI
     */
    public function toggle_member_ban()
    {
        $id = get_param_integer('id');
        $test = $this->test;

        if (!$test) {
            if ($id == get_member()) {
                warn_exit(do_lang_tempcode('AVOIDING_BANNING_SELF'));
            }

            if (post_param_integer('confirm', 0) == 0) {
                $preview = do_lang_tempcode('BAN_MEMBER_DESCRIPTION', escape_html($GLOBALS['FORUM_DRIVER']->get_username($id)));
                $url = get_self_url(false, false);
                return do_template('CONFIRM_SCREEN', array('_GUID' => '4f8c5443497e60e9d636cd45283f2d59', 'TITLE' => $this->title, 'PREVIEW' => $preview, 'FIELDS' => form_input_hidden('confirm', '1'), 'URL' => $url));
            }

            require_code('cns_members_action');
            require_code('cns_members_action2');
            cns_ban_member($id);
        } else {
            if (post_param_integer('confirm', 0) == 0) {
                $preview = do_lang_tempcode('UNBAN_MEMBER_DESCRIPTION', escape_html($GLOBALS['FORUM_DRIVER']->get_username($id)));
                $url = get_self_url(false, false);
                return do_template('CONFIRM_SCREEN', array('_GUID' => '6a21b101d5c0621572d0f80606258963', 'TITLE' => $this->title, 'PREVIEW' => $preview, 'FIELDS' => form_input_hidden('confirm', '1'), 'URL' => $url));
            }

            require_code('cns_members_action');
            require_code('cns_members_action2');
            cns_unban_member($id);
        }

        persistent_cache_delete('IP_BANS');

        // Show it worked / Refresh
        $_url = get_param_string('redirect', null);
        if (!is_null($_url)) {
            $url = make_string_tempcode($_url);
            return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
        }
        return inform_screen($this->title, do_lang_tempcode('SUCCESS'));
    }

    /**
     * The actualiser to toggle a submitter ban.
     *
     * @return Tempcode The UI
     */
    public function toggle_submitter_ban()
    {
        $id = get_param_integer('id');
        $test = $this->test;

        if (is_null($test)) {
            $this->title = get_screen_title('SUBMITTER_BANNED');

            if ($id == get_member()) {
                warn_exit(do_lang_tempcode('AVOIDING_BANNING_SELF'));
            }

            if (post_param_integer('confirm', 0) == 0) {
                $preview = do_lang_tempcode('BAN_SUBMITTER_DESCRIPTION', escape_html($GLOBALS['FORUM_DRIVER']->get_username($id)));
                $url = get_self_url(false, false);
                return do_template('CONFIRM_SCREEN', array('_GUID' => 'c1b82528e4f86be64484097adb60fdf2', 'TITLE' => $this->title, 'PREVIEW' => $preview, 'FIELDS' => form_input_hidden('confirm', '1'), 'URL' => $url));
            }

            $GLOBALS['SITE_DB']->query_insert('usersubmitban_member', array('the_member' => $id));
            log_it('SUBMITTER_BANNED', strval($id));
        } else {
            $this->title = get_screen_title('SUBMITTER_UNBANNED');

            if (post_param_integer('confirm', 0) == 0) {
                $preview = do_lang_tempcode('UNBAN_SUBMITTER_DESCRIPTION', escape_html($GLOBALS['FORUM_DRIVER']->get_username($id)));
                $url = get_self_url(false, false);
                return do_template('CONFIRM_SCREEN', array('_GUID' => '3abb432a4d9ef0a812307f8681f3e3fe', 'TITLE' => $this->title, 'PREVIEW' => $preview, 'FIELDS' => form_input_hidden('confirm', '1'), 'URL' => $url));
            }

            $GLOBALS['SITE_DB']->query_delete('usersubmitban_member', array('the_member' => $id), '', 1);
            log_it('SUBMITTER_UNBANNED', strval($id));
        }

        persistent_cache_delete('IP_BANS');

        // Show it worked / Refresh
        $_url = get_param_string('redirect', null);
        if (!is_null($_url)) {
            $url = make_string_tempcode($_url);
            return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
        }
        return inform_screen($this->title, do_lang_tempcode('SUCCESS'));
    }

    /**
     * The actualiser to syndicate an IP ban.
     *
     * @return Tempcode The UI
     */
    public function syndicate_ip_ban()
    {
        $ip = either_param_string('ip');
        $member_id = either_param_integer('member_id');

        if (post_param_integer('confirm', 0) == 0) {
            $preview = do_lang_tempcode('DESCRIPTION_SYNDICATE_TO_STOPFORUMSPAM');
            $url = get_self_url(false, false, null, true);
            return do_template('CONFIRM_SCREEN', array('_GUID' => '5dcc3d19a71be9e948d7d3668325ef90', 'TITLE' => $this->title, 'PREVIEW' => $preview, 'FIELDS' => form_input_hidden('confirm', '1'), 'URL' => $url));
        }

        require_code('failure');
        syndicate_spammer_report($ip, is_guest($member_id) ? '' : $GLOBALS['FORUM_DRIVER']->get_username($member_id), $GLOBALS['FORUM_DRIVER']->get_member_email_address($member_id), get_param_string('reason'), true);
        log_it('SYNDICATED_IP_BAN', $ip);

        // Show it worked / Refresh
        $_url = get_param_string('redirect', null);
        if (!is_null($_url)) {
            $url = make_string_tempcode($_url);
            return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
        }
        return inform_screen($this->title, do_lang_tempcode('SUCCESS'));
    }

    /**
     * The actualiser to toggle an IP ban.
     *
     * @return Tempcode The UI
     */
    public function toggle_ip_ban()
    {
        $ip = get_param_string('id');
        $test = $this->test;

        if (!$test) {
            if ($ip == get_ip_address()) {
                warn_exit(do_lang_tempcode('AVOIDING_BANNING_SELF'));
            }

            if (post_param_integer('confirm', 0) == 0) {
                $preview = do_lang_tempcode('BAN_IP_DESCRIPTION', escape_html($ip));
                $url = get_self_url(false, false);
                return do_template('CONFIRM_SCREEN', array('_GUID' => 'f6c2c7cacdb014fcca278865fbd663fe', 'TITLE' => $this->title, 'PREVIEW' => $preview, 'FIELDS' => form_input_hidden('confirm', '1'), 'URL' => $url));
            }

            require_code('failure');
            add_ip_ban($ip);
            log_it('IP_BANNED', $ip);
        } else {
            if (post_param_integer('confirm', 0) == 0) {
                $preview = do_lang_tempcode('UNBAN_IP_DESCRIPTION', escape_html($ip));
                $url = get_self_url(false, false);
                return do_template('CONFIRM_SCREEN', array('_GUID' => '19f4bee88709ba8e2534eec083abbafb', 'TITLE' => $this->title, 'PREVIEW' => $preview, 'FIELDS' => form_input_hidden('confirm', '1'), 'URL' => $url));
            }

            require_code('failure');
            remove_ip_ban($ip);
            log_it('IP_UNBANNED', $ip);
        }

        persistent_cache_delete('IP_BANS');

        // Show it worked / Refresh
        $_url = get_param_string('redirect', null);
        if (!is_null($_url)) {
            $url = make_string_tempcode($_url);
            return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
        }
        return inform_screen($this->title, do_lang_tempcode('SUCCESS'));
    }

    /**
     * The actualiser to toggle a combined IP/member ban.
     *
     * @return Tempcode The UI
     */
    public function multi_ban()
    {
        $id = either_param_string('id', null);
        $_ip = explode(':', strrev($id), 2);
        $ip = strrev($_ip[0]);
        $member = array_key_exists(1, $_ip) ? strrev($_ip[1]) : null;

        if (post_param_integer('confirm', 0) == 0) {
            $preview = do_lang_tempcode('BAN_MEMBER_DOUBLE_DESCRIPTION', is_null($member) ? do_lang_tempcode('NA_EM') : make_string_tempcode(strval($member)), make_string_tempcode(escape_html($ip)));
            $url = get_self_url(false, false);
            return do_template('CONFIRM_SCREEN', array('_GUID' => '3840c52b23d9034cb6f9dd529b236c97', 'TITLE' => $this->title, 'PREVIEW' => $preview, 'FIELDS' => form_input_hidden('confirm', '1'), 'URL' => $url));
        }

        if (!is_null($member)) {
            cns_ban_member(intval($member));
        }
        require_code('failure');
        add_ip_ban($ip);

        return inform_screen($this->title, do_lang_tempcode('SUCCESS'));
    }
}
