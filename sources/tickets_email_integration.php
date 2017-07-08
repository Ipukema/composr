<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2017

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/*EXTRA FUNCTIONS: imap\_.+*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    tickets
 */

/**
 * Script to read in an e-mailed ticket/reply.
 */
function incoming_ticket_email_script()
{
    if (!GOOGLE_APPENGINE) {
        return;
    }

    if (!gae_is_admin()) {
        return;
    }

    $_body = file_get_contents('php://input');

    return; // Not currently supported
}

/**
 * Send out an e-mail message for a ticket / ticket reply.
 *
 * @param  ID_TEXT $ticket_id Ticket ID
 * @param  mixed $ticket_url URL to the ticket (URLPATH or Tempcode)
 * @param  string $ticket_type_name The ticket type's label
 * @param  string $subject Ticket subject
 * @param  string $message Ticket message
 * @param  string $to_name Display name of ticket owner
 * @param  EMAIL $to_email E-mail address of ticket owner
 * @param  string $from_displayname Display name of staff poster
 * @param  boolean $new Whether this is a new ticket, just created by the ticket owner
 */
function ticket_outgoing_message($ticket_id, $ticket_url, $ticket_type_name, $subject, $message, $to_name, $to_email, $from_displayname, $new = false)
{
    if (is_object($ticket_url)) {
        $ticket_url = $ticket_url->evaluate();
    }

    if ($to_email == '') {
        return;
    }

    $headers = '';
    $from_email = get_option('ticket_email_from');
    if ($from_email == '') {
        $from_email = get_option('staff_address');
    }
    $website_email = get_option('website_email');
    if ($website_email == '') {
        $website_email = $from_email;
    }
    $headers .= 'From: ' . do_lang('TICKET_SIMPLE_FROM', get_site_name(), $from_displayname) . ' <' . $website_email . '>' . "\r\n";
    $headers .= 'Reply-To: ' . do_lang('TICKET_SIMPLE_FROM', get_site_name(), $from_displayname) . ' <' . $from_email . '>';

    $tightened_subject = str_replace(array("\n", "\r"), array('', ''), $subject);
    $extended_subject = do_lang('TICKET_SIMPLE_SUBJECT_' . ($new ? 'new' : 'reply'), $subject, $ticket_id, array($ticket_type_name, $from_displayname, get_site_name()));

    $extended_message = '';
    $extended_message .= do_lang('TICKET_SIMPLE_MAIL_' . ($new ? 'new' : 'reply'), get_site_name(), $ticket_type_name, array($ticket_url, $from_displayname));
    $extended_message .= $message;

    mail($to_name . ' <' . $to_email . '>', $extended_subject, strip_comcode($extended_message), $headers);
}

/**
 * Send out an e-mail about us not recognising an e-mail address for a ticket.
 *
 * @param  string $subject Subject line of original message
 * @param  string $body Body of original message
 * @param  string $email E-mail address we tried to bind to
 * @param  string $email_bounce_to E-mail address of sender (usually the same as $email, but not if it was a forwarded e-mail)
 */
function ticket_email_cannot_bind($subject, $body, $email, $email_bounce_to)
{
    $headers = '';
    $from_email = get_option('ticket_email_from');
    if ($from_email == '') {
        $from_email = get_option('staff_address');
    }
    $website_email = get_option('website_email');
    if ($website_email == '') {
        $website_email = $from_email;
    }
    $headers .= 'From: ' . get_site_name() . ' <' . $website_email . '>' . "\r\n";
    $headers .= 'Reply-To: ' . get_site_name() . ' <' . $from_email . '>';

    $extended_subject = do_lang('TICKET_CANNOT_BIND_SUBJECT', $subject, $email, get_site_name());
    $extended_message = do_lang('TICKET_CANNOT_BIND_MAIL', strip_comcode($body), $email, array($subject, get_site_name()));

    mail($email_bounce_to, $extended_subject, $extended_message, $headers);
}

/**
 * Scan for new e-mails in the support inbox.
 */
function ticket_incoming_scan()
{
    if (get_option('ticket_mail_on') !== '1') {
        return;
    }

    if (!function_exists('imap_open')) {
        warn_exit(do_lang_tempcode('IMAP_NEEDED'));
    }

    require_lang('tickets');
    require_code('tickets');
    require_code('tickets2');
    require_code('mail2');

    $server = get_option('ticket_mail_server');
    $port = intval(get_option('ticket_mail_server_port'));
    $type = get_option('ticket_mail_server_type');

    $username = get_option('ticket_mail_username');
    $password = get_option('ticket_mail_password');

    $ref = _imap_server_spec($server, $port, $type);
    $resource = @imap_open($ref . 'INBOX', $username, $password, CL_EXPUNGE);
    if ($resource !== false) {
        $list = imap_search($resource, (get_param_integer('test', 0) == 1 && $GLOBALS['FORUM_DRIVER']->is_super_admin(get_member())) ? '' : 'UNSEEN');
        if ($list === false) {
            $list = array();
        }
        foreach ($list as $l) {
            $header = imap_headerinfo($resource, $l);
            $full_header = imap_fetchheader($resource, $l);

            $subject = $header->subject;

            $attachments = array();
            $attachment_size_total = 0;
            $body = _imap_get_part($resource, $l, 'TEXT/HTML', $attachments, $attachment_size_total);
            if ($body === null) { // Convert from plain text
                $body = _imap_get_part($resource, $l, 'TEXT/PLAIN', $attachments, $attachment_size_total);
                $body = email_comcode_from_text($body);
            } else { // Convert from HTML
                $body = email_comcode_from_html($body);
            }
            _imap_get_part($resource, $l, 'APPLICATION/OCTET-STREAM', $attachments, $attachment_size_total);

            if (strlen($header->reply_toaddress) > 0) {
                $from_email = get_ticket_email_from_header($header->reply_toaddress);
                if (find_ticket_member_from_email($from_email) === null) {
                    $from_email_alt = get_ticket_email_from_header($header->fromaddress);
                    if (find_ticket_member_from_email($from_email_alt) !== null) {
                        $from_email = $from_email_alt;
                    }
                }
            } else {
                $from_email = get_ticket_email_from_header($header->fromaddress);
            }

            if (!is_non_human_email($subject, $body, $full_header, $from_email)) {
                imap_clearflag_full($resource, $l, '\\Seen'); // Clear this, as otherwise it is a real pain to debug (have to keep manually marking unread)

                ticket_incoming_message(
                    $from_email,
                    $subject,
                    $body,
                    $attachments
                );
            }

            imap_setflag_full($resource, $l, '\\Seen');
        }
        imap_close($resource);
    } else {
        $error = imap_last_error();
        imap_errors(); // Works-around weird PHP bug where "Retrying PLAIN authentication after [AUTHENTICATIONFAILED] Authentication failed. (errflg=1) in Unknown on line 0" may get spit out into any stream (even the backup log)

        if (!is_cli()) {
            warn_exit(do_lang_tempcode('IMAP_ERROR', $error), false, true);
        }
    }
}

/**
 * Convert e-mail HTML to Comcode.
 *
 * @param  string $body HTML body
 * @return string Comcode version
 */
function email_comcode_from_html($body)
{
    $body = unixify_line_format($body);

    // We only want inside the body
    $body = preg_replace('#.*<body[^<>]*>#is', '', $body);
    $body = preg_replace('#</body>.*#is', '', $body);

    // Cleanup some junk
    $body = str_replace(array('<<', '>>'), array('&lt;<', '>&gt;'), $body);
    $body = str_replace(array(' class="Apple-interchange-newline"', ' style="margin-top: 0px; margin-right: 0px; margin-bottom: 0px; margin-left: 0px;"', ' apple-width="yes" apple-height="yes"', '<br clear="all">', ' class="gmail_extra"', ' class="gmail_quote"', ' style="word-wrap:break-word"', ' style="word-wrap: break-word; -webkit-nbsp-mode: space; -webkit-line-break: after-white-space; "'), array('', '', '', '<br />', '', '', '', ''), $body);
    $body = preg_replace('# style="text-indent:0px.*"#U', '', $body); // Apple Mail long list of styles

    // Convert quotes
    $body = preg_replace('#<div[^<>]*>On (.*) wrote:</div><br[^<>]*><blockquote[^<>]*>#i', '[quote="${1}"]', $body); // Apple Mail
    $body = preg_replace('#(<div[^<>]*>)On (.*) wrote:<br[^<>]*><blockquote[^<>]*>#i', '${1}[quote="${2}"]', $body); // gmail
    $body = preg_replace('#(\[quote="[^"]*) &lt;<.*>&gt;#U', '${1}', $body); // Remove e-mail address (Apple Mail)
    $body = preg_replace('#(\[quote="[^"]*) <span[^<>]*>&lt;<.*>&gt;</span>#U', '${1}', $body); // Remove e-mail address (gmail)
    $body = preg_replace('#<blockquote[^<>]*>#i', '[quote]', $body);
    $body = preg_replace('#</blockquote>#i', '[/quote]', $body);

    $body = preg_replace('<img [^<>]*src="cid:[^"]*"[^<>]*>', '', $body); // We will get this as an attachment instead

    // Strip signature
    do {
        $pos = strpos($body, '<div apple-content-edited="true">');
        if ($pos !== false) {
            $stack = 1;
            $len = strlen($body);
            for ($pos_b = $pos + 1; $pos_b < $len; $pos_b++) {
                if ($body[$pos_b] == '<') {
                    if (substr($body, $pos_b, 4) == '<div') {
                        $stack++;
                    } else {
                        if (substr($body, $pos_b, 5) == '</div') {
                            $stack--;
                            if ($stack == 0) {
                                $body = substr($body, 0, $pos) . substr($body, $pos_b);
                                break;
                            }
                        }
                    }
                }
            }
        }
    } while ($pos !== false);

    $body = cms_trim($body, true);

    require_code('comcode_from_html');
    $body = semihtml_to_comcode($body, true);

    // Trim too much white-space
    $body = preg_replace('#\[quote\](\s|<br />)+#s', '[quote]', $body);
    $body = preg_replace('#(\s|<br />)+\[/quote\]#s', '[/quote]', $body);
    $body = str_replace("\n\n\n", "\n\n", $body);

    // Tidy up the body
    foreach (array('TICKET_SIMPLE_MAIL_new_regexp', 'TICKET_SIMPLE_MAIL_reply_regexp') as $s) {
        $body = preg_replace('#' . str_replace("\n", "(\n|<br[^<>]*>)", do_lang($s)) . '#', '', $body);
    }
    $body = trim($body, "- \n\r");

    return $body;
}

/**
 * Convert e-mail text to Comcode.
 *
 * @param  string $body Text body
 * @return string Comcode version
 */
function email_comcode_from_text($body)
{
    $body = unixify_line_format($body);

    $body = preg_replace_callback('#(\n> .*)+#', '_convert_text_quote_to_comcode', $body);

    // Tidy up the body
    foreach (array('TICKET_SIMPLE_MAIL_new_regexp', 'TICKET_SIMPLE_MAIL_reply_regexp') as $s) {
        $body = preg_replace('#' . do_lang($s) . '#', '', $body);
    }
    $body = trim($body, "- \n\r");

    return $body;
}

/**
 * See if we need to skip over an e-mail message, due to it not being from a human.
 *
 * @param  string $subject Subject line
 * @param  string $body Message body
 * @param  string $full_header Message headers
 * @param  EMAIL $from_email From address
 * @return boolean Whether it should not be processed
 */
function is_non_human_email($subject, $body, $full_header, $from_email)
{
    if ($from_email == get_option('ticket_email_from') || $from_email == get_option('staff_address') || $from_email == get_option('website_email')) {
        return true;
    }

    $full_header = "\r\n" . strtolower($full_header);
    if (strpos($full_header, "\r\nfrom: <>") !== false) {
        return true;
    }
    if (strpos($full_header, "\r\nauto-submitted: ") !== false && strpos($full_header, "\r\nauto-submitted: no") === false) {
        return true;
    }

    $junk = false;
    $junk_strings = array(
        'Delivery Status Notification',
        'Delivery Notification',
        'Returned mail',
        'Undeliverable message',
        'Mail delivery failed',
        'Failure Notice',
        'Delivery Failure',
        'Nondeliverable',
        'Undeliverable',
    );
    foreach ($junk_strings as $j) {
        if ((stripos($subject, $j) !== false) || (stripos($body, $j) !== false)) {
            $junk = true;
        }
    }
    return $junk;
}

/**
 * Process a quote block in plain-text e-mail, into a Comcode quote tag. preg callback.
 *
 * @param  array $matches preg Matches
 * @return string The result
 *
 * @ignore
 */
function _convert_text_quote_to_comcode($matches)
{
    return '[quote]' . trim(preg_replace('#\n> (.*)#', "\n" . '${1}', $matches[0])) . '[/quote]';
}

/**
 * Get the mime type for a part of the IMAP structure.
 *
 * @param  object $structure Structure
 * @return string Mime type
 *
 * @ignore
 */
function _imap_get_mime_type($structure)
{
    $primary_mime_type = array('TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER');
    if ($structure->subtype) {
        return $primary_mime_type[intval($structure->type)] . '/' . strtoupper($structure->subtype);
    }
    return 'TEXT/PLAIN';
}

/**
 * Find a message part of an e-mail that matches a mime-type.
 * Taken from http://php.net/manual/en/function.imap-fetchbody.php.
 *
 * @param  resource $stream IMAP connection object
 * @param  integer $msg_number Message number
 * @param  string $mime_type Mime type (in upper case)
 * @param  array $attachments Map of attachments (name to file data); only populated if $mime_type is APPLICATION/OCTET-STREAM
 * @param  integer $attachment_size_total Total size of attachments in bytes
 * @param  ?object $structure IMAP message structure (null: look up)
 * @param  string $part_number Message part number (blank: root)
 * @return ?string The message part (null: could not find one)
 * @ignore
 */
function _imap_get_part($stream, $msg_number, $mime_type, &$attachments, &$attachment_size_total, $structure = null, $part_number = '')
{
    if ($structure === null) {
        $structure = imap_fetchstructure($stream, $msg_number);
    }

    $part_mime_type = _imap_get_mime_type($structure);

    if ($mime_type == 'APPLICATION/OCTET-STREAM') {
        $disposition = $structure->ifdisposition ? strtoupper($structure->disposition) : '';
        if (($disposition == 'ATTACHMENT') || (($structure->type != 1) && ($structure->type != 2) && (isset($structure->bytes)) && ($part_mime_type != 'TEXT/PLAIN') && ($part_mime_type != 'TEXT/HTML'))) {
            $filename = $structure->parameters[0]->value;

            if ($attachment_size_total + $structure->bytes < 1024 * 1024 * 20/*20MB is quite enough, thankyou*/) {
                $filedata = imap_fetchbody($stream, $msg_number, $part_number);
                if ($structure->encoding == 3) {
                    $filedata = imap_base64($filedata);
                } elseif ($structure->encoding == 4) {
                    $filedata = imap_qprint($filedata);
                }

                $attachments[$filename] = $filedata;

                $attachment_size_total += $structure->bytes;
            } else {
                $new_filename = 'errors-' . $filename . '.txt';
                $attachments[] = array($new_filename => '20MB filesize limit exceeded');
            }
        }
    } else {
        if ($part_mime_type == $mime_type) {
            require_code('character_sets');

            if ($part_number == '') {
                $part_number = '1';
            }
            $filedata = imap_fetchbody($stream, $msg_number, $part_number);
            if ($structure->encoding == 3) {
                $filedata = imap_base64($filedata);
                $filedata = convert_to_internal_encoding($filedata, 'ISO-8859-1');
            } elseif ($structure->encoding == 4) {
                $filedata = imap_qprint($filedata);
                $filedata = convert_to_internal_encoding($filedata, 'ISO-8859-1');
            }
            if ($structure->ifparameters == 1) {
                $parameters = array();
                foreach ($structure->parameters as $param) {
                    $parameters[strtolower($param->attribute)] = $param->value;
                }
                if (isset($parameters['charset'])) {
                    $filedata = convert_to_internal_encoding($filedata, $parameters['charset']);
                    $filedata = fix_bad_unicode($filedata, true);
                }
            }
            return $filedata;
        }
    }

    if ($structure->type == 1) { // Multi-part
        foreach ($structure->parts as $index => $sub_structure) {
            if ($part_number != '') {
                $prefix = $part_number . '.';
            } else {
                $prefix = '';
            }
            $data = _imap_get_part($stream, $msg_number, $mime_type, $attachments, $attachment_size_total, $sub_structure, $prefix . strval($index + 1));
            if ($data !== null) {
                return $data;
            }
        }
    }

    return null;
}

/**
 * Process an e-mail found, sent to the support ticket system.
 *
 * @param  EMAIL $from_email From e-mail
 * @param  string $subject E-mail subject
 * @param  string $body E-mail body
 * @param  array $attachments Map of attachments (name to file data); only populated if $mime_type is APPLICATION/OCTET-STREAM
 */
function ticket_incoming_message($from_email, $subject, $body, $attachments)
{
    require_lang('tickets');
    require_code('tickets');
    require_code('tickets2');

    $from_email_orig = $from_email;

    // Try to bind to an existing ticket
    $existing_ticket_id = null;
    $matches = array();
    if (preg_match('#' . do_lang('TICKET_SIMPLE_SUBJECT_regexp') . '#', $subject, $matches) != 0) {
        if (strpos($matches[2], '_') !== false) {
            $existing_ticket_id = $matches[2];

            // Validate
            $topic_id = $GLOBALS['FORUM_DRIVER']->find_topic_id_for_topic_identifier(get_option('ticket_forum_name'), $existing_ticket_id, do_lang('SUPPORT_TICKET'));
            if ($topic_id === null) {
                $existing_ticket_id = null; // Invalid
            }
        }
    }

    // Remove any tags from the subject line
    $num_matches = preg_match_all('# \[([^\[\]]+)\]#', $subject, $matches);
    $tags = array();
    for ($i = 0; $i < $num_matches; $i++) {
        $tags[] = $matches[1][$i];
        $subject = str_replace($matches[0][$i], '', $subject);
    }

    // De-forward
    $forwarded = false;
    foreach (array('fwd: ', 'fw: ') as $prefix) {
        if (substr(strtolower($subject), 0, strlen($prefix)) == $prefix) {
            $subject = substr($subject, strlen($prefix));
            $forwarded = true;
            $body = preg_replace('#^(\[semihtml\])?(<br />\n)*-------- Original Message --------(\n|<br />)+#', '${1}', $body);
            $body = preg_replace('#^(\[semihtml\])?(<br />\n)*Begin forwarded message:(\n|<br />)*#', '${1}', $body);
            $body = preg_replace('#^(\[semihtml\])?(<br />\n)*<div>Begin forwarded message:</div>(\n|<br />)*#', '${1}', $body);
            $body = preg_replace('#^(\[semihtml\])?(<br />\n)*<div>(<br />\n)*<div>Begin forwarded message:</div>(\n|<br />)*#', '${1}<div>', $body);
        }
    }
    if ($forwarded) {
        if (find_ticket_member_from_email($from_email) === null) {
            if (preg_match('#From:(.*)#s', $body, $matches) != 0) {
                $from_email_alt = get_ticket_email_from_header($matches[1]);
                if (find_ticket_member_from_email($from_email_alt) !== null) {
                    $from_email = $from_email_alt;
                }
            }
        }
    }

    // Try to bind to a from member
    $member_id = null;
    foreach ($tags as $tag) {
        $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_username($tag);
        if ($member_id !== null) {
            break;
        }
    }
    if ($member_id === null) {
        $member_id = $GLOBALS['SITE_DB']->query_select_value_if_there('ticket_known_emailers', 'member_id', array(
            'email_address' => $from_email,
        ));
        if ($member_id === null) {
            $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_email_address($from_email);
            if ($member_id === null) {
                if ($existing_ticket_id === null) {
                    // E-mail back, saying user not found
                    ticket_email_cannot_bind($subject, $body, $from_email, $from_email_orig);
                    return;
                } else {
                    $_temp = explode('_', $existing_ticket_id, 2);
                    $member_id = intval($_temp[0]);
                }
            }
        }
    }

    // Remember the e-mail address to member ID mapping
    $GLOBALS['SITE_DB']->query_delete('ticket_known_emailers', array(
        'email_address' => $from_email,
    ));
    $GLOBALS['SITE_DB']->query_insert('ticket_known_emailers', array(
        'email_address' => $from_email,
        'member_id' => $member_id,
    ));

    // Check there can be no forgery vulnerability
    if (has_privilege($member_id, 'comcode_dangerous')) {
        $member_id = $GLOBALS['FORUM_DRIVER']->get_guest_id(); // Sorry, we can't let e-mail posting with staff permissions
    }

    // Add in attachments
    require_code('urls2');
    foreach ($attachments as $filename => $filedata) {
        require_code('files');
        $new_filename = preg_replace('#\..*#', '', $filename) . '.dat';
        list($new_path, $new_url, $new_filename) = find_unique_path('uploads/attachments', $new_filename);
        cms_file_put_contents_safe($new_path, $filedata, FILE_WRITE_FIX_PERMISSIONS | FILE_WRITE_SYNC_FILE);

        $attachment_id = $GLOBALS['SITE_DB']->query_insert('attachments', array(
            'a_member_id' => $member_id,
            'a_file_size' => strlen($filedata),
            'a_url' => 'uploads/attachments/' . rawurlencode($new_filename),
            'a_thumb_url' => '',
            'a_original_filename' => $filename,
            'a_num_downloads' => 0,
            'a_last_downloaded_time' => time(),
            'a_description' => '',
            'a_add_time' => time(),
        ), true);

        $body .= "\n\n" . '[attachment framed="1" thumb="1"]' . strval($attachment_id) . '[/attachment]';
    }

    // Mark that this was e-mailed in
    $body .= "\n\n" . do_lang('TICKET_EMAILED_IN');

    push_lax_comcode(true);

    // Post
    if ($existing_ticket_id === null) {
        $new_ticket_id = strval($member_id) . '_' . uniqid('', false);

        // Pick up ticket type, a other/general ticket type if it exists
        $ticket_type_id = null;
        $tags[] = do_lang('OTHER');
        $tags[] = do_lang('GENERAL');
        foreach ($tags as $tag) {
            $ticket_type_id = $GLOBALS['SITE_DB']->query_select_value_if_there('ticket_types', 'id', array($GLOBALS['SITE_DB']->translate_field_ref('ticket_type_name') => $tag));
            if ($ticket_type_id !== null) {
                break;
            }
        }
        if ($ticket_type_id === null) {
            $ticket_type_id = $GLOBALS['SITE_DB']->query_select_value('ticket_types', 'MIN(id)');
        }

        // Create the ticket...

        $ticket_url = ticket_add_post($new_ticket_id, $ticket_type_id, $subject, $body, false, $member_id);

        // Send email (to staff)
        send_ticket_email($new_ticket_id, $subject, $body, $ticket_url, $from_email, $ticket_type_id, $member_id, true);
    } else {
        // Reply to the ticket...

        $ticket_type_id = $GLOBALS['SITE_DB']->query_select_value_if_there('tickets', 'ticket_type', array(
            'ticket_id' => $existing_ticket_id,
        ));

        $ticket_url = ticket_add_post($existing_ticket_id, $ticket_type_id, $subject, $body, false, $member_id);

        $details = get_ticket_meta_details($existing_ticket_id);
        if (empty($details)) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'ticket'), false, true);
        }
        list($__title) = $details;

        // Send email (to staff & to confirm receipt to $member_id)
        send_ticket_email($existing_ticket_id, $__title, $body, $ticket_url, $from_email, null, $member_id, true);
    }

    pop_lax_comcode();
}

/**
 * Try and get an e-mail address from an embedded part of an e-mail header.
 *
 * @param  string $from_email E-mail header
 * @return string E-mail address (hopefully)
 */
function get_ticket_email_from_header($from_email)
{
    $matches = array();
    if (preg_match('#([\w\.\-\+]+@[\w\.\-]+)#', $from_email, $matches) != 0) {
        $from_email = $matches[1];
    }
    return $from_email;
}

/**
 * Find the ticket member for an e-mail address.
 *
 * @param  string $from_email E-mail address
 * @return ?MEMBER Member ID (null: none)
 */
function find_ticket_member_from_email($from_email)
{
    $member_id = $GLOBALS['SITE_DB']->query_select_value_if_there('ticket_known_emailers', 'member_id', array(
        'email_address' => $from_email,
    ));
    if ($member_id === null) {
        $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_email_address($from_email);
    }
    return $member_id;
}
