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
 * @package    import
 */

/**
 * Module page class.
 */
class Module_admin_import
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
        $info['version'] = 7;
        $info['locked'] = false;
        $info['update_require_upgrade'] = 1;
        return $info;
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('import_id_remap');
        $GLOBALS['SITE_DB']->drop_table_if_exists('import_session');
        $GLOBALS['SITE_DB']->drop_table_if_exists('import_parts_done');
    }

    /**
     * Install the module.
     *
     * @param  ?integer $upgrade_from What version we're upgrading from (null: new install)
     * @param  ?integer $upgrade_from_hack What hack version we're upgrading from (null: new-install/not-upgrading-from-a-hacked-version)
     */
    public function install($upgrade_from = null, $upgrade_from_hack = null)
    {
        if ((!is_null($upgrade_from)) && ($upgrade_from < 7)) {
            $GLOBALS['SITE_DB']->alter_table_field('import_id_remap', 'id_session', 'ID_TEXT');
            $GLOBALS['SITE_DB']->alter_table_field('import_session', 'imp_session', 'ID_TEXT');
            $GLOBALS['SITE_DB']->add_table_field('import_parts_done', 'id', '*AUTO');
            $GLOBALS['SITE_DB']->alter_table_field('import_parts_done', 'imp_session', 'ID_TEXT');
        }

        if ((!is_null($upgrade_from)) && ($upgrade_from < 6)) {
            $GLOBALS['SITE_DB']->add_table_field('import_session', 'imp_db_host', 'ID_TEXT');
        }

        if ((!is_null($upgrade_from)) && ($upgrade_from < 5)) {
            $GLOBALS['SITE_DB']->alter_table_field('import_id_remap', 'id_old', 'ID_TEXT');
        }

        if ((is_null($upgrade_from)) || ($upgrade_from < 4)) {
            $GLOBALS['SITE_DB']->create_table('import_parts_done', array(
                'id' => '*AUTO',
                'imp_id' => 'SHORT_TEXT',
                'imp_session' => 'ID_TEXT',
            ));

            $GLOBALS['SITE_DB']->create_table('import_session', array(
                'imp_session' => '*ID_TEXT',
                'imp_old_base_dir' => 'SHORT_TEXT',
                'imp_db_name' => 'ID_TEXT',
                'imp_db_user' => 'ID_TEXT',
                'imp_hook' => 'ID_TEXT',
                'imp_db_table_prefix' => 'ID_TEXT',
                'imp_db_host' => 'ID_TEXT',
                'imp_refresh_time' => 'INTEGER',
            ));

            $usergroups = $GLOBALS['FORUM_DRIVER']->get_usergroup_list(false, true);
            foreach (array_keys($usergroups) as $id) {
                $GLOBALS['SITE_DB']->query_insert('group_page_access', array('page_name' => 'admin_import', 'zone_name' => 'adminzone', 'group_id' => $id)); // Import very dangerous
            }

            $GLOBALS['SITE_DB']->create_table('import_id_remap', array(
                'id_old' => '*ID_TEXT',
                'id_new' => 'AUTO_LINK',
                'id_type' => '*ID_TEXT',
                'id_session' => '*ID_TEXT'
            ));
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
            'browse' => array('IMPORT', 'menu/_generic_admin/import'),
        );
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know meta-data for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('import');

        set_helper_panel_tutorial('tut_importer');

        if ($type == 'browse') {
            breadcrumb_set_self(do_lang_tempcode('IMPORT'));
        }

        if ($type == 'session') {
            breadcrumb_set_parents(array(array('_SELF:_SELF:browse', do_lang_tempcode('IMPORT'))));
            breadcrumb_set_self(do_lang_tempcode('IMPORT_SESSION'));
        }

        if ($type == 'session2') {
            breadcrumb_set_parents(array(array('_SELF:_SELF:browse', do_lang_tempcode('IMPORT')), array('_SELF:_SELF:session', do_lang_tempcode('IMPORT_SESSION'))));
        }

        if ($type == 'hook') {
            $importer = filter_naughty(get_param_string('importer'));
            breadcrumb_set_parents(array(array('_SELF:_SELF:browse', do_lang_tempcode('IMPORT')), array('_SELF:_SELF:session:importer=' . $importer, do_lang_tempcode('IMPORT_SESSION'))));
        }

        if ($type == 'import') {
            breadcrumb_set_parents(array(array('_SELF:_SELF:browse', do_lang_tempcode('IMPORT')), array('_SELF:_SELF:session', do_lang_tempcode('IMPORT_SESSION')), array('_SELF:_SELF:hook:importer=session2:session=' . get_param_string('session'), do_lang_tempcode('IMPORT'))));
            breadcrumb_set_self(do_lang_tempcode('ACTIONS'));
        }

        $this->title = get_screen_title('IMPORT');

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        if (!is_null($GLOBALS['CURRENT_SHARE_USER'])) {
            warn_exit(do_lang_tempcode('SHARED_INSTALL_PROHIBIT'));
        }

        disable_php_memory_limit();

        require_code('import');
        load_import_deps();

        $GLOBALS['LAX_COMCODE'] = true;

        set_mass_import_mode();

        // Decide what we're doing
        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            return $this->choose_importer();
        }
        if ($type == 'session') {
            return $this->choose_session();
        }
        if ($type == 'session2') {
            return $this->choose_session2();
        }
        if ($type == 'hook') {
            return $this->choose_actions();
        }
        if ($type == 'import') {
            return $this->do_import();
        }

        return new Tempcode();
    }

    /**
     * The UI to choose an importer.
     *
     * @return Tempcode The UI
     */
    public function choose_importer()
    {
        $hooks = new Tempcode();
        $_hooks = find_all_hooks('modules', 'admin_import');
        $__hooks = array();
        require_code('form_templates');
        foreach (array_keys($_hooks) as $hook) {
            require_code('hooks/modules/admin_import/' . filter_naughty_harsh($hook));
            if (class_exists('Hook_' . filter_naughty_harsh($hook))) {
                $object = object_factory('Hook_' . filter_naughty_harsh($hook), true);
                if (is_null($object)) {
                    continue;
                }
                $info = $object->info();
                $__hooks[$hook] = $info['product'];
            }
        }
        uasort($__hooks, 'strnatcmp');
        foreach ($__hooks as $hook => $hook_title) {
            $hooks->attach(form_input_list_entry($hook, false, $hook_title));
        }
        if ($hooks->is_empty()) {
            warn_exit(do_lang_tempcode('NO_CATEGORIES'));
        }
        $fields = form_input_huge_list(do_lang_tempcode('IMPORTER'), do_lang_tempcode('DESCRIPTION_IMPORTER'), 'importer', $hooks, null, true);

        $post_url = build_url(array('page' => '_SELF', 'type' => 'session'), '_SELF');

        return do_template('FORM_SCREEN', array('_GUID' => '02416e5e9d6cb64248adeb9d2e6f2402', 'GET' => true, 'HIDDEN' => '', 'SKIP_WEBSTANDARDS' => true, 'SUBMIT_ICON' => 'buttons__proceed', 'SUBMIT_NAME' => do_lang_tempcode('PROCEED'), 'TITLE' => $this->title, 'FIELDS' => $fields, 'URL' => $post_url, 'TEXT' => ''));
    }

    /**
     * The UI to choose an import session.
     *
     * @return Tempcode The UI
     */
    public function choose_session()
    {
        // Code to detect redirect hooks for import
        $importer = filter_naughty(get_param_string('importer'));
        require_code('hooks/modules/admin_import/' . filter_naughty_harsh($importer));
        $object = object_factory('Hook_' . filter_naughty_harsh($importer));
        $info = $object->info();

        if (array_key_exists('hook_type', $info)) {
            $redirect_url = build_url(array('page' => $info['import_module'], 'type' => $info['import_method_name']), get_module_zone($info['import_module']));
            return redirect_screen($this->title, $redirect_url, do_lang_tempcode('REDIRECTED_TO_CACHE_MODULES'));
        }

        $sessions = new Tempcode();
        $_sessions = $GLOBALS['SITE_DB']->query_select('import_session', array('*'));
        require_code('form_templates');
        foreach ($_sessions as $session) {
            if ($session['imp_session'] == get_session_id()) {
                $text = do_lang_tempcode('IMPORT_SESSION_CURRENT', escape_html($session['imp_db_name']));
            } else {
                $text = do_lang_tempcode('IMPORT_SESSION_EXISTING_REMAP', escape_html($session['imp_db_name']));
            }
            $sessions->attach(form_input_list_entry($session['imp_session'], false, $text));
        }
        $text = do_lang_tempcode((count($_sessions) == 0) ? 'IMPORT_SESSION_NEW' : 'IMPORT_SESSION_NEW_DELETE');
        $sessions->attach(form_input_list_entry(strval(-1), false, $text));
        if ($importer == 'cms_merge') {
            $text = do_lang_tempcode('IMPORT_SESSION_NEW_DELETE_CNS_SATELLITE');
            $sessions->attach(form_input_list_entry(strval(-2), false, $text));
        }
        $fields = form_input_list(do_lang_tempcode('IMPORT_SESSION'), do_lang_tempcode('DESCRIPTION_IMPORT_SESSION'), 'session', $sessions, null, true);

        $post_url = build_url(array('page' => '_SELF', 'type' => 'session2', 'importer' => get_param_string('importer')), '_SELF');

        return do_template('FORM_SCREEN', array(
            '_GUID' => 'f474980f7263f2def2ff75e7ee40be33',
            'SKIP_WEBSTANDARDS' => true,
            'HIDDEN' => form_input_hidden('importer', get_param_string('importer')),
            'SUBMIT_ICON' => 'buttons__proceed',
            'SUBMIT_NAME' => do_lang_tempcode('CHOOSE'),
            'TITLE' => $this->title,
            'FIELDS' => $fields,
            'URL' => $post_url,
            'TEXT' => '',
        ));
    }

    /**
     * The UI to choose session details.
     *
     * @return Tempcode The UI
     */
    public function choose_session2()
    {
        /* These cases:
          1) We are continuing (therefore do nothing)
          2) We are resuming a prior session, after our session changed (therefore remap old session-data to current session)
          3) We are starting afresh (therefore delete all previous import sessions)
          4) As per '3', except Conversr imports are maintained as we're now importing a satellite site
        */
        $session = either_param_string('session', get_session_id());
        if (($session == '-1') || ($session == '-2')) {
            // Delete all others
            $GLOBALS['SITE_DB']->query_delete('import_session');
            if ($session == '-1') {
                $GLOBALS['SITE_DB']->query_delete('import_parts_done');
                $GLOBALS['SITE_DB']->query_delete('import_id_remap');
            } else {
                $GLOBALS['SITE_DB']->query('DELETE FROM ' . get_table_prefix() . 'import_parts_done WHERE imp_id NOT LIKE \'' . db_encode_like('cns_%') . '\'');
                $GLOBALS['SITE_DB']->query('DELETE FROM ' . get_table_prefix() . 'import_id_remap WHERE (id_type NOT LIKE \'' . db_encode_like('cns_%') . '\'' . ') AND ' . db_string_not_equal_to('id_type', 'category') . ' AND ' . db_string_not_equal_to('id_type', 'forum') . ' AND ' . db_string_not_equal_to('id_type', 'topic') . ' AND ' . db_string_not_equal_to('id_type', 'post') . ' AND ' . db_string_not_equal_to('id_type', 'f_poll') . ' AND ' . db_string_not_equal_to('id_type', 'group') . ' AND ' . db_string_not_equal_to('id_type', 'member'));
            }

            $session = get_session_id();
        }
        if ($session != get_session_id()) {
            // Remap given to current
            $GLOBALS['SITE_DB']->query_delete('import_session', array('imp_session' => get_session_id()), '', 1);
            $GLOBALS['SITE_DB']->query_delete('import_parts_done', array('imp_session' => get_session_id()));
            $GLOBALS['SITE_DB']->query_delete('import_id_remap', array('id_session' => get_session_id()));
            $GLOBALS['SITE_DB']->query_update('import_session', array('imp_session' => get_session_id()), array('imp_session' => $session), '', 1);
            $GLOBALS['SITE_DB']->query_update('import_parts_done', array('imp_session' => get_session_id()), array('imp_session' => $session));
            $GLOBALS['SITE_DB']->query_update('import_id_remap', array('id_session' => get_session_id()), array('id_session' => $session));
        }

        // Get details from the session row
        $importer = filter_naughty(get_param_string('importer'));
        require_code('hooks/modules/admin_import/' . filter_naughty_harsh($importer));
        $object = object_factory('Hook_' . filter_naughty_harsh($importer));
        $info = $object->info();

        $session_row = $GLOBALS['SITE_DB']->query_select('import_session', array('*'), array('imp_session' => get_session_id()), '', 1);
        if (array_key_exists(0, $session_row)) {
            $old_base_dir = $session_row[0]['imp_old_base_dir'];
            $db_name = $session_row[0]['imp_db_name'];
            $db_user = $session_row[0]['imp_db_user'];
            $db_table_prefix = $session_row[0]['imp_db_table_prefix'];
            $refresh_time = $session_row[0]['imp_refresh_time'];
        } else {
            $old_base_dir = get_file_base() . '/old';
            $db_name = get_db_site();
            $db_user = get_db_site_user();
            $db_table_prefix = array_key_exists('prefix', $info) ? $info['prefix'] : $GLOBALS['SITE_DB']->get_table_prefix();
            $refresh_time = 15;
        }

        // Build the form
        $fields = new Tempcode();
        require_code('form_templates');
        if (!method_exists($object, 'probe_db_access')) {
            $fields->attach(form_input_line(do_lang_tempcode('DATABASE_NAME'), do_lang_tempcode('_FROM_IMPORTING_SYSTEM'), 'db_name', $db_name, true));
            $fields->attach(form_input_line(do_lang_tempcode('DATABASE_USERNAME'), do_lang_tempcode('_FROM_IMPORTING_SYSTEM'), 'db_user', $db_user, true));
            $fields->attach(form_input_password(do_lang_tempcode('DATABASE_PASSWORD'), do_lang_tempcode('_FROM_IMPORTING_SYSTEM'), 'db_password', false)); // Not required as there may be a blank password
            $fields->attach(form_input_line(do_lang_tempcode('TABLE_PREFIX'), do_lang_tempcode('_FROM_IMPORTING_SYSTEM'), 'db_table_prefix', $db_table_prefix, true));
        }
        $fields->attach(form_input_line(do_lang_tempcode('FILE_BASE'), do_lang_tempcode('FROM_IMPORTING_SYSTEM'), 'old_base_dir', $old_base_dir, true));
        if (intval(ini_get('safe_mode')) == 0) {
            $fields->attach(form_input_integer(do_lang_tempcode('REFRESH_TIME'), do_lang_tempcode('DESCRIPTION_REFRESH_TIME'), 'refresh_time', $refresh_time, true));
        }
        if (method_exists($object, 'get_extra_fields')) {
            $fields->attach($object->get_extra_fields());
        }

        $url = build_url(array('page' => '_SELF', 'type' => 'hook', 'session' => $session, 'importer' => $importer), '_SELF');
        $message = array_key_exists('message', $info) ? $info['message'] : '';

        return do_template('FORM_SCREEN', array('_GUID' => '15f2c855acf0d365a2e6329bec692dc8', 'TEXT' => $message, 'TITLE' => $this->title, 'FIELDS' => $fields, 'URL' => $url, 'HIDDEN' => '', 'SUBMIT_ICON' => 'buttons__proceed', 'SUBMIT_NAME' => do_lang_tempcode('PROCEED')));
    }

    /**
     * The UI to choose what to import.
     *
     * @param  mixed $extra Output to show from last action (blank: none)
     * @return Tempcode The UI
     */
    public function choose_actions($extra = '')
    {
        $session = either_param_string('session', get_session_id());
        $importer = filter_naughty(get_param_string('importer'));

        require_code('hooks/modules/admin_import/' . filter_naughty_harsh($importer));
        $object = object_factory('Hook_' . filter_naughty_harsh($importer));

        // Test import source is good
        $db_host = get_db_site_host();
        if (method_exists($object, 'probe_db_access')) {
            $probe = $object->probe_db_access(either_param_string('old_base_dir'));
            list($db_name, $db_user, $db_password, $db_table_prefix) = $probe;
            if (array_key_exists(4, $probe)) {
                $db_host = $probe[4];
            }
        } else {
            $db_name = either_param_string('db_name');
            $db_user = either_param_string('db_user');
            $db_password = either_param_string('db_password');
            $db_table_prefix = either_param_string('db_table_prefix');
            $db_host = either_param_string('db_host', $db_host);
        }
        if (($db_name == get_db_site()) && ($importer == 'cms_merge') && ($db_table_prefix == $GLOBALS['SITE_DB']->get_table_prefix())) {
            warn_exit(do_lang_tempcode('IMPORT_SELF_NO'));
        }
        $import_source = is_null($db_name) ? null : new DatabaseConnector($db_name, $db_host, $db_user, $db_password, $db_table_prefix);
        unset($import_source);

        // Save data
        $old_base_dir = either_param_string('old_base_dir');
        $refresh_time = either_param_integer('refresh_time', 15); // Shouldn't default, but reported on some systems to do so
        $GLOBALS['SITE_DB']->query_delete('import_session', array('imp_session' => get_session_id()), '', 1);
        $GLOBALS['SITE_DB']->query_insert('import_session', array(
            'imp_hook' => $importer,
            'imp_old_base_dir' => $old_base_dir,
            'imp_db_name' => is_null($db_name) ? '' : $db_name,
            'imp_db_user' => is_null($db_user) ? '' : $db_user,
            'imp_db_table_prefix' => is_null($db_table_prefix) ? '' : $db_table_prefix,
            'imp_db_host' => is_null($db_host) ? '' : $db_host,
            'imp_refresh_time' => $refresh_time,
            'imp_session' => get_session_id()
        ));

        $lang_array = array();
        $hooks = find_all_hooks('modules', 'admin_import_types');
        foreach (array_keys($hooks) as $hook) {
            require_code('hooks/modules/admin_import_types/' . filter_naughty_harsh($hook));
            $_hook = object_factory('Hook_admin_import_types_' . filter_naughty_harsh($hook));
            $lang_array += $_hook->run();
        }

        $info = $object->info();

        $session_row = $GLOBALS['SITE_DB']->query_select('import_session', array('*'), array('imp_session' => get_session_id()), '', 1);
        if (array_key_exists(0, $session_row)) {
            $old_base_dir = $session_row[0]['imp_old_base_dir'];
            $db_name = $session_row[0]['imp_db_name'];
            $db_user = $session_row[0]['imp_db_user'];
            $db_table_prefix = $session_row[0]['imp_db_table_prefix'];
            $db_host = $session_row[0]['imp_db_host'];
            $refresh_time = $session_row[0]['imp_refresh_time'];
        } else {
            $old_base_dir = get_file_base() . '/old';
            $db_name = get_db_site();
            $db_user = get_db_site_user();
            $db_table_prefix = array_key_exists('prefix', $info) ? $info['prefix'] : $GLOBALS['SITE_DB']->get_table_prefix();
            $db_host = get_db_site_host();
            $refresh_time = 15;
        }

        $_import_list = $info['import'];
        $_import_list_2 = array();
        foreach ($_import_list as $import) {
            if (is_null($import)) {
                continue;
            }
            if (!array_key_exists($import, $lang_array)) {
                continue;
            }
            if (is_null($lang_array[$import])) {
                continue;
            }

            $text = do_lang_tempcode((strtolower($lang_array[$import]) != $lang_array[$import]) ? $lang_array[$import] : strtoupper($lang_array[$import]));
            $_import_list_2[$import] = $text;
        }
        if ((array_key_exists('cns_members', $_import_list_2)) && (get_forum_type() == $importer) && ($db_name == get_db_forums()) && ($db_table_prefix == $GLOBALS['FORUM_DB']->get_table_prefix())) {
            $_import_list_2['cns_switch'] = do_lang_tempcode('SWITCH_TO_CNS');
        }
        $import_list = new Tempcode();
        //asort($_import_list_2); Let's preserve order here
        $just = get_param_string('just', null);
        $first = true;
        $skip_hidden = array();
        $parts_done = collapse_2d_complexity('imp_id', 'imp_session', $GLOBALS['SITE_DB']->query_select('import_parts_done', array('imp_id', 'imp_session'), array('imp_session' => get_session_id())));
        foreach ($_import_list_2 as $import => $text) {
            if (array_key_exists($import, $parts_done)) {
                $import_list->attach(do_template('IMPORT_ACTION_LINE', array(
                    '_GUID' => '887770aad4269b74fdf11d09f4ab4fa3',
                    'CHECKED' => false,
                    'DISABLED' => true,
                    'NAME' => 'import_' . $import,
                    'TEXT' => $text,
                    'ADVANCED_URL' => $info['supports_advanced_import'] ? build_url(array('page' => '_SELF', 'type' => 'advanced_hook', 'session' => $session, 'content_type' => $import, 'importer' => $importer), '_SELF') : new Tempcode(),
                )));
            } else {
                $checked = (is_null($just)) && ($first);
                $import_list->attach(do_template('IMPORT_ACTION_LINE', array(
                    '_GUID' => 'f2215115f920200a0a1ba6bc776ad945',
                    'CHECKED' => $checked,
                    'NAME' => 'import_' . $import,
                    'TEXT' => $text,
                    'ADVANCED_URL' => $info['supports_advanced_import'] ? build_url(array('page' => '_SELF', 'type' => 'advanced_hook', 'session' => $session, 'content_type' => $import, 'importer' => $importer), '_SELF') : new Tempcode()
                )));
            }
            if ($just == $import) {
                $first = true;
                $just = null;
            } else {
                $first = false;
            }

            $skip_hidden[] = 'import_' . $import;
        }

        $message = array_key_exists('message', $info) ? $info['message'] : '';

        if (count($parts_done) == count($_import_list_2)) {
            inform_exit(do_lang_tempcode(($message === '') ? '_IMPORT_ALL_FINISHED' : 'IMPORT_ALL_FINISHED', $message));
        }

        $url = build_url(array('page' => '_SELF', 'type' => 'import', 'session' => $session, 'importer' => $importer), '_SELF');

        $hidden = new Tempcode();
        $hidden->attach(build_keep_post_fields($skip_hidden));
        $hidden->attach(build_keep_form_fields('', true));

        return do_template('IMPORT_ACTION_SCREEN', array('_GUID' => 'a3a69637e541923ad76e9e7e6ec7e1af', 'EXTRA' => $extra, 'MESSAGE' => $message, 'TITLE' => $this->title, 'FIELDS' => '', 'HIDDEN' => $hidden, 'IMPORTER' => $importer, 'IMPORT_LIST' => $import_list, 'URL' => $url));
    }

    /**
     * The actualiser to do an import.
     *
     * @return Tempcode The UI
     */
    public function do_import()
    {
        $refresh_url = get_self_url(true, false, array('type' => 'import'), true);
        $refresh_time = either_param_integer('refresh_time', 15); // Shouldn't default, but reported on some systems to do so
        if (function_exists('set_time_limit')) {
            @set_time_limit($refresh_time);
            safe_ini_set('display_errors', '0'); // So that the timeout message does not show, which made the user not think the refresh was going to happen automatically, and could thus result in double-requests
        }
        send_http_output_ping();
        header('Content-type: text/html; charset=' . get_charset());
        safe_ini_set('log_errors', '0');
        global $I_REFRESH_URL;
        $I_REFRESH_URL = $refresh_url;

        $importer = get_param_string('importer');
        require_code('hooks/modules/admin_import/' . filter_naughty_harsh($importer));
        $object = object_factory('Hook_' . filter_naughty_harsh($importer));

        // Get data
        $old_base_dir = either_param_string('old_base_dir');
        if ((method_exists($object, 'verify_base_path')) && (!$object->verify_base_path($old_base_dir))) {
            warn_exit(do_lang_tempcode('BAD_IMPORT_PATH', escape_html($old_base_dir)));
        }
        $db_host = get_db_site_host();
        if (method_exists($object, 'probe_db_access')) {
            $probe = $object->probe_db_access(either_param_string('old_base_dir'));
            list($db_name, $db_user, $db_password, $db_table_prefix) = $probe;
            if (array_key_exists(4, $probe)) {
                $db_host = $probe[4];
            }
        } else {
            $db_name = either_param_string('db_name');
            $db_user = either_param_string('db_user');
            $db_password = either_param_string('db_password');
            $db_table_prefix = either_param_string('db_table_prefix');
            $db_host = either_param_string('db_host', $db_host);
        }
        if (($db_name == get_db_site()) && ($importer == 'cms_merge') && ($db_table_prefix == $GLOBALS['SITE_DB']->get_table_prefix())) {
            warn_exit(do_lang_tempcode('IMPORT_SELF_NO'));
        }

        $import_source = is_null($db_name) ? null : new DatabaseConnector($db_name, $db_host, $db_user, $db_password, $db_table_prefix);

        // Some preliminary tests
        $happy = get_param_integer('happy', 0);
        if ((method_exists($object, 'pre_import_tests')) && ($happy == 0)) {
            $ui = $object->pre_import_tests($import_source, $db_table_prefix, $old_base_dir);
            if (!is_null($ui)) {
                return $ui;
            }
        }

        // Save data
        $GLOBALS['SITE_DB']->query_delete('import_session', array('imp_session' => get_session_id()), '', 1);
        $GLOBALS['SITE_DB']->query_insert('import_session', array(
            'imp_hook' => $importer,
            'imp_old_base_dir' => $old_base_dir,
            'imp_db_name' => is_null($db_name) ? '' : $db_name,
            'imp_db_user' => is_null($db_user) ? '' : $db_user,
            'imp_db_table_prefix' => is_null($db_table_prefix) ? '' : $db_table_prefix,
            'imp_db_host' => is_null($db_host) ? '' : $db_host,
            'imp_refresh_time' => $refresh_time,
            'imp_session' => get_session_id()
        ));

        $info = $object->info();
        $_import_list = $info['import'];
        $out = new Tempcode();
        $parts_done = collapse_2d_complexity('imp_id', 'imp_session', $GLOBALS['SITE_DB']->query_select('import_parts_done', array('imp_id', 'imp_session'), array('imp_session' => get_session_id())));
        $import_last = '-1';
        if (get_forum_type() != 'cns') {
            require_code('forum/cns');
            $GLOBALS['CNS_DRIVER'] = new Forum_driver_cns();
            $GLOBALS['CNS_DRIVER']->connection = $GLOBALS['SITE_DB'];
            $GLOBALS['CNS_DRIVER']->MEMBER_ROWS_CACHED = array();
        }
        $_import_list[] = 'cns_switch';
        $all_skipped = true;

        $lang_array = array();
        $hooks = find_all_hooks('modules', 'admin_import_types');
        foreach (array_keys($hooks) as $hook) {
            require_code('hooks/modules/admin_import_types/' . filter_naughty_harsh($hook));
            $_hook = object_factory('Hook_admin_import_types_' . filter_naughty_harsh($hook));
            $lang_array += $_hook->run();
        }

        foreach ($_import_list as $import) {
            $import_this = either_param_integer('import_' . $import, 0);
            if ($import_this == 1) {
                $dependency = null;
                if ((array_key_exists('dependencies', $info)) && (array_key_exists($import, $info['dependencies']))) {
                    foreach ($info['dependencies'][$import] as $_dependency) {
                        if ((!array_key_exists($_dependency, $parts_done)) && (isset($lang_array[$_dependency]))) {
                            $dependency = $_dependency;
                        }
                    }
                }
                if (is_null($dependency)) {
                    if ($import == 'cns_switch') {
                        $out->attach($this->cns_switch());
                    } else {
                        $function_name = 'import_' . $import;
                        cns_over_local();
                        $func_output = call_user_func_array(array($object, $function_name), array($import_source, $db_table_prefix, $old_base_dir));
                        if (!is_null($func_output)) {
                            $out->attach($func_output);
                        }
                        cns_over_msn();
                    }
                    $parts_done[$import] = get_session_id();

                    $import_last = $import;
                    $all_skipped = false;

                    $GLOBALS['SITE_DB']->query_delete('import_parts_done', array('imp_id' => $import, 'imp_session' => get_session_id()), '', 1);
                    $GLOBALS['SITE_DB']->query_insert('import_parts_done', array('imp_id' => $import, 'imp_session' => get_session_id()));
                } else {
                    $out->attach(do_template('IMPORT_MESSAGE', array('_GUID' => 'b2a853f5fb93beada51a3eb8fbd1575f', 'MESSAGE' => do_lang_tempcode('IMPORT_OF_SKIPPED', escape_html($import), escape_html($dependency)))));
                }
            }
        }
        if (!$all_skipped) {
            $lang_code = 'SUCCESS';
            if (count($GLOBALS['ATTACHED_MESSAGES_RAW']) != 0) {
                $lang_code = 'SOME_ERRORS_OCCURRED';
            }
            $out->attach(do_template('IMPORT_MESSAGE', array('_GUID' => '4c4860d021814ffd1df6e21e712c7b44', 'MESSAGE' => do_lang_tempcode($lang_code))));
        }

        log_it('IMPORT');
        post_import_cleanup();

        $back_url = build_url(array('page' => '_SELF', 'type' => 'hook', 'importer' => get_param_string('importer'), 'just' => $import_last), '_SELF');
        $_GET['just'] = $import_last;
        return $this->choose_actions($out);
    }

    /**
     * Special import-esque function to aid switching to Conversr after importing forum previously served by a forum driver.
     *
     * @return Tempcode Information about progress
     */
    public function cns_switch()
    {
        $out = new Tempcode();

        $todos = array('MEMBER' => array('member', db_get_first_id(), null), 'GROUP' => array('group', null, 'group_id'));
        foreach ($todos as $db_abstraction => $definition) {
            list($import_code, $default_id, $field_name_also) = $definition;

            $count = 0;

            $extra = is_null($field_name_also) ? '' : (' OR ' . db_string_equal_to('m_name', $field_name_also));
            $fields = $GLOBALS['SITE_DB']->query('SELECT m_table,m_name FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'db_meta WHERE (NOT (m_table LIKE \'' . db_encode_like('f_%') . '\')) AND (' . db_string_equal_to('m_type', $db_abstraction) . ' OR ' . db_string_equal_to('m_type', '*' . $db_abstraction) . ' OR ' . db_string_equal_to('m_type', '?' . $db_abstraction) . $extra . ')');
            foreach ($fields as $field) {
                if ($field['m_table'] == 'stats') {
                    continue; // Lots of data and it's not important
                }

                //echo '(working) ' . $field['m_table'] . '/' . $field['m_name'] . '<br />';

                $values = $GLOBALS['SITE_DB']->query_select($field['m_table'], array('*'));
                foreach ($values as $value) {
                    $current = $value[$field['m_name']];
                    $remapped = import_id_remap_get($import_code, $current, true);
                    if (is_null($remapped)) {
                        $remapped = $default_id;
                    }

                    if (!is_null($remapped)) {
                        $value2 = $value;
                        $value2[$field['m_name']] = -$remapped;
                        $c = $GLOBALS['SITE_DB']->query_update($field['m_table'], $value2, $value, '', null, null, true, true);
                        if (is_null($c)) { // Something went wrong apparently- but we still need to clean up
                            $GLOBALS['SITE_DB']->query_delete($field['m_table'], $value);
                        } else {
                            $count += $c;
                        }
                    } else {
                        $GLOBALS['SITE_DB']->query_delete($field['m_table'], $value);
                    }
                }
                $GLOBALS['SITE_DB']->query('UPDATE ' . $GLOBALS['SITE_DB']->get_table_prefix() . $field['m_table'] . ' SET ' . $field['m_name'] . '=-' . $field['m_name'] . ' WHERE ' . $field['m_name'] . '<0');
            }

            $out->attach(paragraph(do_lang_tempcode('CNS_CONVERTED_' . $db_abstraction, ($count == 0) ? '?' : strval($count))));
        }

        // _config.php
        global $FILE_BASE;
        $config_file = '_config.php';
        $config_file_handle = @fopen($FILE_BASE . '/' . $config_file, GOOGLE_APPENGINE ? 'wb' : 'wt') or intelligent_write_error($FILE_BASE . '/' . $config_file);
        fwrite($config_file_handle, "<" . "?php\n");
        global $SITE_INFO;
        $SITE_INFO['forum_type'] = 'cns';
        $SITE_INFO['cns_table_prefix'] = $SITE_INFO['table_prefix'];
        $SITE_INFO['db_forums'] = $SITE_INFO['db_site'];
        $SITE_INFO['db_forums_host'] = array_key_exists('db_site_host', $SITE_INFO) ? $SITE_INFO['db_site_host'] : 'localhost';
        $SITE_INFO['db_forums_user'] = $SITE_INFO['db_site_user'];
        $SITE_INFO['db_forums_password'] = $SITE_INFO['db_site_password'];
        $SITE_INFO['board_prefix'] = get_base_url();
        foreach ($SITE_INFO as $key => $val) {
            $_val = str_replace('\\', '\\\\', $val);
            fwrite($config_file_handle, '$SITE_INFO[\'' . $key . '\']=\'' . $_val . "';\n");
        }
        fwrite($config_file_handle, "?" . ">\n");
        fclose($config_file_handle);
        fix_permissions($FILE_BASE . '/' . $config_file);
        sync_file($FILE_BASE . '/' . $config_file);
        $out->attach(paragraph(do_lang_tempcode('CNS_CONVERTED_INFO')));

        // Add zone formally
        $map = array(
            'zone_name' => 'forum',
            'zone_default_page' => 'forumview',
            'zone_theme' => '-1',
            'zone_require_session' => 0,
        );
        $map += insert_lang('zone_title', do_lang('SECTION_FORUMS'), 1);
        $map += insert_lang('zone_header_text', do_lang('FORUM'), 1);
        $GLOBALS['SITE_DB']->query_insert('zones', $map);

        return $out;
    }
}
