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
 * @package    debrand
 */

/**
 * Module page class.
 */
class Module_admin_debrand
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
        $info['version'] = 2;
        $info['locked'] = false;
        return $info;
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
            'browse' => array('SUPER_DEBRAND', 'menu/adminzone/style/debrand'),
        );
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        require_code('form_templates'); // Needs to run high so that the anti-click-hacking header is sent

        appengine_live_guard();

        $type = get_param_string('type', 'browse');

        require_lang('debrand');

        set_helper_panel_text(comcode_lang_string('DOC_SUPERDEBRAND'));

        $this->title = get_screen_title('SUPER_DEBRAND');

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        if ($GLOBALS['CURRENT_SHARE_USER'] !== null) {
            warn_exit(do_lang_tempcode('SHARED_INSTALL_PROHIBIT'));
        }

        require_lang('config');

        $type = get_param_string('type', 'browse');
        if ($type == 'browse') {
            return $this->browse();
        }
        if ($type == 'actual') {
            return $this->actual();
        }

        return new Tempcode();
    }

    /**
     * The UI for managing super debranding.
     *
     * @return Tempcode The UI
     */
    public function browse()
    {
        $rebrand_name = get_value('rebrand_name');
        if ($rebrand_name === null) {
            $rebrand_name = 'Composr';
        }
        $rebrand_base_url = get_brand_base_url();
        $company_name = get_value('company_name');
        if ($company_name === null) {
            $company_name = 'ocProducts';
        }
        $keyboard_map = file_exists(get_file_base() . '/pages/comcode/' . get_site_default_lang() . '/keymap.txt') ? cms_file_get_contents_safe(get_file_base() . '/pages/comcode/' . get_site_default_lang() . '/keymap.txt') : cms_file_get_contents_safe(get_file_base() . '/pages/comcode/' . fallback_lang() . '/keymap.txt');
        if (file_exists(get_file_base() . '/pages/comcode_custom/' . get_site_default_lang() . '/keymap.txt')) {
            $keyboard_map = cms_file_get_contents_safe(get_file_base() . '/pages/comcode_custom/' . get_site_default_lang() . '/keymap.txt');
        }
        if (file_exists(get_file_base() . '/adminzone/pages/comcode_custom/' . get_site_default_lang() . '/website.txt')) {
            $adminguide = cms_file_get_contents_safe(get_file_base() . '/adminzone/pages/comcode_custom/' . get_site_default_lang() . '/website.txt');
        } else {
            $adminguide = do_lang('ADMINGUIDE_DEFAULT_TRAINING');
        }
        if (file_exists(get_file_base() . '/adminzone/pages/comcode_custom/' . get_site_default_lang() . '/' . DEFAULT_ZONE_PAGE_NAME . '.txt')) {
            $dashboard = cms_file_get_contents_safe(get_file_base() . '/adminzone/pages/comcode_custom/' . get_site_default_lang() . '/' . DEFAULT_ZONE_PAGE_NAME . '.txt');
        } elseif (file_exists(get_file_base() . '/adminzone/pages/comcode/' . get_site_default_lang() . '/' . DEFAULT_ZONE_PAGE_NAME . '.txt')) {
            $dashboard = file_exists(get_file_base() . '/adminzone/pages/comcode/' . get_site_default_lang() . '/' . DEFAULT_ZONE_PAGE_NAME . '.txt') ? cms_file_get_contents_safe(get_file_base() . '/adminzone/pages/comcode/' . get_site_default_lang() . '/' . DEFAULT_ZONE_PAGE_NAME . '.txt') : cms_file_get_contents_safe(get_file_base() . '/adminzone/pages/comcode/' . fallback_lang() . '/' . DEFAULT_ZONE_PAGE_NAME . '.txt');
        } else {
            $dashboard = do_lang('REBRAND_DASHBOARD');
        }

        $fields = new Tempcode();
        $fields->attach(form_input_line(do_lang_tempcode('REBRAND_NAME'), do_lang_tempcode('DESCRIPTION_REBRAND_NAME'), 'rebrand_name', $rebrand_name, true));
        $fields->attach(form_input_line(do_lang_tempcode('REBRAND_BASE_URL'), do_lang_tempcode('DESCRIPTION_BRAND_BASE_URL', escape_html('docs' . strval(cms_version()))), 'rebrand_base_url', $rebrand_base_url, true));
        $fields->attach(form_input_line(do_lang_tempcode('COMPANY_NAME'), '', 'company_name', $company_name, true));
        $fields->attach(form_input_text_comcode(do_lang_tempcode('ADMINGUIDE'), do_lang_tempcode('DESCRIPTION_ADMINGUIDE'), 'adminguide', $adminguide, true));
        $fields->attach(form_input_text_comcode(do_lang_tempcode('ADMIN_DASHBOARD'), do_lang_tempcode('DESCRIPTION_ADMIN_DASHBOARD'), 'dashboard', $dashboard, true));
        $fields->attach(form_input_text_comcode(do_lang_tempcode('KEYBOARD_MAP'), '', 'keyboard_map', $keyboard_map, true));
        $fields->attach(form_input_tick(do_lang_tempcode('DELETE_UN_PC'), do_lang_tempcode('DESCRIPTION_DELETE_UN_PC'), 'churchy', false));
        $fields->attach(form_input_tick(do_lang_tempcode('SHOW_DOCS'), do_lang_tempcode('DESCRIPTION_SHOW_DOCS'), 'show_docs', get_option('show_docs') == '1'));
        require_code('images');
        $fields->attach(form_input_upload(do_lang_tempcode('FAVICON'), do_lang_tempcode('DESCRIPTION_FAVICON'), 'favicon', false, find_theme_image('favicon'), null, true, get_allowed_image_file_types()));
        $fields->attach(form_input_upload(do_lang_tempcode('WEBCLIPICON'), do_lang_tempcode('DESCRIPTION_WEBCLIPICON'), 'webclipicon', false, find_theme_image('webclipicon'), null, true, get_allowed_image_file_types()));
        if (addon_installed('cns_avatars')) {
            $fields->attach(form_input_upload(do_lang_tempcode('SYSTEM_AVATAR'), do_lang_tempcode('DESCRIPTION_SYSTEM_AVATAR'), 'system_avatar', false, find_theme_image('cns_default_avatars/system'), null, true, get_allowed_image_file_types()));
        }

        $post_url = build_url(array('page' => '_SELF', 'type' => 'actual'), '_SELF');
        $submit_name = do_lang_tempcode('PROCEED');

        return do_template('FORM_SCREEN', array(
            '_GUID' => 'fd47f191ac51f7754eb17e3233f53bcc',
            'HIDDEN' => '',
            'TITLE' => $this->title,
            'URL' => $post_url,
            'FIELDS' => $fields,
            'TEXT' => do_lang_tempcode('WARNING_SUPER_DEBRAND_MAJOR_CHANGES'),
            'SUBMIT_ICON' => 'buttons__proceed',
            'SUBMIT_NAME' => $submit_name,
        ));
    }

    /**
     * The actualiser for super debranding.
     *
     * @return Tempcode The UI
     */
    public function actual()
    {
        require_code('config2');
        require_code('database_action');
        require_code('files');

        if ($GLOBALS['CURRENT_SHARE_USER'] === null) { // Only if not a shared install
            require_code('abstract_file_manager');
            force_have_afm_details();
        }

        set_value('rebrand_name', post_param_string('rebrand_name'));
        set_value('rebrand_base_url', post_param_string('rebrand_base_url', false, INPUT_FILTER_URL_GENERAL));
        set_value('company_name', post_param_string('company_name'));
        set_option('show_docs', post_param_string('show_docs', '0'));

        $keyboard_map_path = get_file_base() . '/pages/comcode_custom/' . get_site_default_lang() . '/keymap.txt';
        $km = post_param_string('keyboard_map');
        cms_file_put_contents_safe($keyboard_map_path, $km, FILE_WRITE_FIX_PERMISSIONS | FILE_WRITE_SYNC_FILE);

        $adminguide_path = get_file_base() . '/adminzone/pages/comcode_custom/' . get_site_default_lang() . '/website.txt';
        $adminguide = post_param_string('adminguide');
        $adminguide = str_replace('__company__', post_param_string('company_name'), $adminguide);
        cms_file_put_contents_safe($adminguide_path, $adminguide, FILE_WRITE_FIX_PERMISSIONS | FILE_WRITE_SYNC_FILE);

        $start_path = get_file_base() . '/adminzone/pages/comcode_custom/' . get_site_default_lang() . '/' . DEFAULT_ZONE_PAGE_NAME . '.txt';
        if (!file_exists($start_path)) {
            $start = post_param_string('start_page');
            cms_file_put_contents_safe($start_path, $start, FILE_WRITE_FIX_PERMISSIONS | FILE_WRITE_SYNC_FILE);
        }

        if ($GLOBALS['CURRENT_SHARE_USER'] === null) { // Only if not a shared install
            $critical_errors = cms_file_get_contents_safe(get_file_base() . '/sources/critical_errors.php');
            $critical_errors = str_replace('Composr', addslashes(post_param_string('rebrand_name')), $critical_errors);
            $critical_errors = str_replace('http://compo.sr', addslashes(post_param_string('rebrand_base_url', false, INPUT_FILTER_URL_GENERAL)), $critical_errors);
            $critical_errors = str_replace('ocProducts', 'ocProducts/' . addslashes(post_param_string('company_name')), $critical_errors);
            $critical_errors_path = 'sources_custom/critical_errors.php';

            afm_make_file($critical_errors_path, $critical_errors, false);
        }

        $save_global_tpl_path = get_file_base() . '/themes/' . $GLOBALS['FORUM_DRIVER']->get_theme('') . '/templates_custom/GLOBAL_HTML_WRAP.tpl';
        $global_tpl_path = $save_global_tpl_path;
        if (!file_exists($global_tpl_path)) {
            $global_tpl_path = get_file_base() . '/themes/default/templates/GLOBAL_HTML_WRAP.tpl';
        }
        $global_tpl = cms_file_get_contents_safe($global_tpl_path);
        $global_tpl = str_replace('Copyright ocProducts Limited', '', $global_tpl);
        cms_file_put_contents_safe($save_global_tpl_path, $global_tpl, FILE_WRITE_FIX_PERMISSIONS | FILE_WRITE_SYNC_FILE);

        if (post_param_integer('churchy', 0) == 1) {
            if (is_object($GLOBALS['FORUM_DB'])) {
                $GLOBALS['FORUM_DB']->query_delete('f_emoticons', array('e_code' => ':devil:'), '', 1);
            } else {
                $GLOBALS['SITE_DB']->query_delete('f_emoticons', array('e_code' => ':devil:'), '', 1);
            }
        }

        // Make sure some stuff is disabled for non-admin staff
        $staff_groups = $GLOBALS['FORUM_DRIVER']->get_moderator_groups();
        $disallowed_pages = array('admin_setupwizard', 'admin_addons', 'admin_backup', 'admin_errorlog', 'admin_import', 'admin_commandr', 'admin_phpinfo', 'admin_debrand');
        foreach (array_keys($staff_groups) as $id) {
            foreach ($disallowed_pages as $page) {
                $GLOBALS['SITE_DB']->query_delete('group_page_access', array('page_name' => $page, 'zone_name' => 'adminzone', 'group_id' => $id), '', 1); // in case already exists
                $GLOBALS['SITE_DB']->query_insert('group_page_access', array('page_name' => $page, 'zone_name' => 'adminzone', 'group_id' => $id));
            }
        }

        // Clean up the theme images
        //  background-image
        $theme = $GLOBALS['FORUM_DRIVER']->get_theme('');
        find_theme_image('background_image');
        //  logo/*
        if (addon_installed('zone_logos')) {
            $main_logo_url = find_theme_image('logo/-logo', false, true);

            $test = find_theme_image('logo/adminzone-logo', true);
            if ($test != '') {
                $GLOBALS['SITE_DB']->query_update('theme_images', array('path' => $main_logo_url), array('id' => 'logo/adminzone-logo', 'theme' => $theme), '', 1);
            }

            $test = find_theme_image('logo/cms-logo', true);
            if ($test != '') {
                $GLOBALS['SITE_DB']->query_update('theme_images', array('path' => $main_logo_url), array('id' => 'logo/cms-logo', 'theme' => $theme), '', 1);
            }
        }

        // Various other icons
        require_code('uploads');
        $path = get_url('', 'favicon', 'themes/default/images_custom');
        if ($path[0] != '') {
            $GLOBALS['SITE_DB']->query_update('theme_images', array('path' => $path[0]), array('id' => 'favicon'));
        }
        $path = get_url('', 'webclipicon', 'themes/default/images_custom');
        if ($path[0] != '') {
            $GLOBALS['SITE_DB']->query_update('theme_images', array('path' => $path[0]), array('id' => 'webclipicon'));
        }
        if (addon_installed('cns_avatars')) {
            $path = get_url('', 'system_avatar', 'themes/default/images_custom');
            if ($path[0] != '') {
                $GLOBALS['SITE_DB']->query_update('theme_images', array('path' => $path[0]), array('id' => 'cns_default_avatars/system'));
            }
        }

        // Decache
        require_code('caches3');
        erase_cached_templates(false, null, TEMPLATE_DECACHE_WITH_CONFIG);
        erase_cached_templates(false, array('GLOBAL_HTML_WRAP'));

        // Redirect them back to editing screen
        $url = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');
        return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
    }
}
