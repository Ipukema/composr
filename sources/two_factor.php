<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2017

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_cns
 */

/**
 * Approve an IP address, indirectly by passing through a confirmation code.
 */
function approve_ip_script()
{
    require_code('site');
    attach_to_screen_header('<meta name="robots" content="noindex" />'); // XHTMLXHTML

    $keep = keep_symbol(array('1'));

    $code = either_param_string('code', '');
    if ($code == '') {
        $title = get_screen_title('CONFIRM');
        require_code('form_templates');
        $fields = new Tempcode();
        $fields->attach(form_input_codename(do_lang_tempcode('CODE'), '', 'code', '', true));
        $submit_name = do_lang_tempcode('PROCEED');
        $url = find_script('approve_ip') . $keep;
        $middle = do_template('FORM_SCREEN', array(
            '_GUID' => 'd92ce4ec82dc709f920a4ce6760778de',
            'TITLE' => $title,
            'SKIP_WEBSTANDARDS' => true,
            'HIDDEN' => '',
            'URL' => $url,
            'FIELDS' => $fields,
            'TEXT' => do_lang_tempcode('MISSING_CONFIRM_CODE'),
            'SUBMIT_ICON' => 'buttons__proceed',
            'SUBMIT_NAME' => $submit_name,
        ));
        $echo = globalise($middle, null, '', true, true);
        $echo->evaluate_echo();
        exit();
    }

    // If we're still here, we're ok to go
    require_lang('cns');
    $test = $GLOBALS['FORUM_DB']->query_select_value_if_there('f_member_known_login_ips', 'i_val_code', array('i_val_code' => $code));
    if ($test === null) {
        warn_exit(do_lang_tempcode('ALREADY_APPROVED_IP'));
    }
    $GLOBALS['FORUM_DB']->query_update('f_member_known_login_ips', array('i_val_code' => ''), array('i_val_code' => $code), '', 1);
    if ((get_option('maintenance_script_htaccess') == '1') && (maintenance_script_htaccess_option_available())) {
        adjust_htaccess();
    }

    $title = get_screen_title('CONFIRM');
    $middle = redirect_screen($title, get_base_url() . $keep, do_lang_tempcode('SUCCESS'));
    $echo = globalise($middle, null, '', true, true);
    $echo->evaluate_echo();
    exit();
}

/**
 * See if the maintenance_script_htaccess option is available (if the environment is compatible).
 *
 * @return boolean Whether it is
 */
function maintenance_script_htaccess_option_available()
{
    if (get_forum_type() != 'cns') {
        return false;
    }

    $server_software = cms_srv('SERVER_SOFTWARE');
    if ((stripos($server_software, 'Apache') === false) && (stripos($server_software, 'LiteSpeed') === false)) {
        return false;
    }

    if (!cms_is_writable(get_file_base() . '/.htaccess')) {
        return false;
    }

    return true;
}

/**
 * Adjust the .htaccess file with an approved IP address.
 */
function adjust_htaccess()
{
    $path = get_file_base() . '/.htaccess';

    $contents = cms_file_get_contents_safe($path);

    $lines = array(
        '<FilesMatch ^((rootkit_detection|upgrader|uninstall|data/upgrader2|config_editor|code_editor)\.php)$>',
        'Order deny,allow',
        'Deny from all',
    );
    $ips = $GLOBALS['FORUM_DB']->query_select('f_member_known_login_ips', array('i_ip'), array('i_val_code' => ''));
    foreach ($ips as $ip) {
        $lines[] = 'Allow from ' . $ip['i_ip'];
    }
    $lines = array_merge($lines, array(
        '</FilesMatch>',
    ));

    $final_line = $lines[count($lines) - 1];

    $start_pos = strpos($contents, $lines[0]);
    if ($start_pos === false) {
        $contents .= "\n" . implode("\n", $lines) . "\n";
    } else {
        $end_pos = strpos($contents, $final_line, $start_pos);
        if ($end_pos === false) {
            fatal_exit(do_lang_tempcode('INTERNAL_ERROR')); // Should never happen, things would crash if so! But we can't proceed
        }

        $contents = substr($contents, 0, $start_pos) . implode("\n", $lines) . substr($contents, $end_pos + strlen($final_line));
    }

    $myfile = @fopen($path, GOOGLE_APPENGINE ? 'wb' : 'ab');
    flock($myfile, LOCK_EX);
    ftruncate($myfile, 0);
    fwrite($myfile, $contents);
    flock($myfile, LOCK_UN);
    fclose($myfile);

    fix_permissions($path);
    sync_file($path);
}
