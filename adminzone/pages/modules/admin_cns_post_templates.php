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
 * @package    cns_post_templates
 */

require_code('crud_module');

/**
 * Module page class.
 */
class Module_admin_cns_post_templates extends Standard_crud_module
{
    public $lang_type = 'POST_TEMPLATE';
    public $select_name = 'TITLE';
    public $table_prefix = 't_';
    public $title_is_multi_lang = false;
    public $archive_entry_point = '_SEARCH:forumview';
    public $archive_label = 'SECTION_FORUMS';
    public $menu_label = 'POST_TEMPLATES';
    public $table = 'f_post_templates';
    public $orderer = 't_title';
    public $donext_entry_content_type = 'post_template';
    public $donext_category_content_type = null;

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
        if (get_forum_type() != 'cns') {
            return null;
        }

        if ($be_deferential || $support_crosslinks) {
            return null;
        }

        return array(
            'browse' => array(do_lang_tempcode('menus:ITEMS_HERE', do_lang_tempcode('POST_TEMPLATES'), make_string_tempcode(escape_html(integer_format($GLOBALS['FORUM_DB']->query_select_value('f_post_templates', 'COUNT(*)', array(), '', true))))), 'menu/adminzone/structure/forum/post_templates'),
        ) + parent::get_entry_points();
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @param  boolean $top_level Whether this is running at the top level, prior to having sub-objects called.
     * @param  ?ID_TEXT $type The screen type to consider for metadata purposes (null: read from environment).
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run($top_level = true, $type = null)
    {
        $type = get_param_string('type', 'browse');

        require_lang('cns');
        require_lang('cns_post_templates');
        require_css('cns_admin');

        set_helper_panel_tutorial('tut_support_desk');

        breadcrumb_set_parents(array(array('_SEARCH:admin_cns_members:browse', do_lang_tempcode('MEMBERS'))));

        if ($type == 'import') {
            breadcrumb_set_parents(array(array('_SEARCH:admin_cns_members:browse', do_lang_tempcode('MEMBERS')), array('_SELF:_SELF:browse', do_lang_tempcode('POST_TEMPLATES'))));
        }

        if ($type == '_import') {
            breadcrumb_set_parents(array(array('_SEARCH:admin_cns_members:browse', do_lang_tempcode('MEMBERS')), array('_SELF:_SELF:browse', do_lang_tempcode('POST_TEMPLATES')), array('_SELF:_SELF:import', do_lang_tempcode('IMPORT_STOCK_RESPONSES_PT'))));
        }

        if ($type == 'import' || $type == '_import') {
            $this->title = get_screen_title('IMPORT_STOCK_RESPONSES_PT');
        }

        return parent::pre_run($top_level);
    }

    /**
     * Standard crud_module run_start.
     *
     * @param  ID_TEXT $type The type of module execution
     * @return Tempcode The output of the run
     */
    public function run_start($type)
    {
        $this->add_one_label = do_lang_tempcode('ADD_POST_TEMPLATE');
        $this->edit_this_label = do_lang_tempcode('EDIT_THIS_POST_TEMPLATE');
        $this->edit_one_label = do_lang_tempcode('EDIT_POST_TEMPLATE');

        if (get_forum_type() != 'cns') {
            warn_exit(do_lang_tempcode('NO_CNS'));
        } else {
            cns_require_all_forum_stuff();
        }
        require_code('cns_general_action');
        require_code('cns_general_action2');

        if ($type == 'browse') {
            return $this->browse();
        }
        if ($type == 'import') {
            return $this->import();
        }
        if ($type == '_import') {
            return $this->_import();
        }
        return new Tempcode();
    }

    /**
     * The do-next manager for before content management.
     *
     * @return Tempcode The UI
     */
    public function browse()
    {
        require_code('templates_donext');
        return do_next_manager(
            get_screen_title('POST_TEMPLATES'),
            comcode_lang_string('DOC_POST_TEMPLATES'),
            array(
                array('menu/_generic_admin/add_one', array('_SELF', array('type' => 'add'), '_SELF'), do_lang('ADD_POST_TEMPLATE')),
                array('menu/_generic_admin/edit_one', array('_SELF', array('type' => 'edit'), '_SELF'), do_lang('EDIT_POST_TEMPLATE')),
                array('menu/_generic_admin/import', array('_SELF', array('type' => 'import'), '_SELF'), do_lang('IMPORT_STOCK_RESPONSES_PT')),
            ),
            do_lang('POST_TEMPLATES')
        );
    }

    /**
     * The UI to import in bulk from an archive file.
     *
     * @return Tempcode The UI
     */
    public function import()
    {
        $post_url = build_url(array('page' => '_SELF', 'type' => '_import', 'uploading' => 1), '_SELF');

        $fields = new Tempcode();

        $supported = 'tar';
        if ((function_exists('zip_open')) || (get_option('unzip_cmd') != '')) {
            $supported .= ', zip';
        }
        $fields->attach(form_input_upload_multi(do_lang_tempcode('UPLOAD'), do_lang_tempcode('DESCRIPTION_ARCHIVE_TEXT_FILES_PT', escape_html($supported), escape_html('txt')), 'file', true, null, null, true, 'txt,' . $supported));

        if (addon_installed('tickets')) {
            require_code('tickets');
            $ticket_forum_id = get_ticket_forum_id();
        } else {
            $ticket_forum_id = mixed();
        }
        require_code('cns_general_action2');
        $fields->attach(cns_get_forum_multi_code_field(($ticket_forum_id === null) ? '' : ('+' . strval($ticket_forum_id))));

        $text = paragraph(do_lang_tempcode('DESCRIPTION_IMPORT_STOCK_RESPONSES_PT'));

        return do_template('FORM_SCREEN', array(
            '_GUID' => '7089deefe20d3917020610768e0f7f24',
            'TITLE' => $this->title,
            'FIELDS' => $fields,
            'SUBMIT_ICON' => 'menu___generic_admin__import',
            'SUBMIT_NAME' => do_lang_tempcode('IMPORT_STOCK_RESPONSES_PT'),
            'URL' => $post_url,
            'TEXT' => $text,
            'HIDDEN' => '',
        ));
    }

    /**
     * The actualiser to import in bulk from an archive file.
     *
     * @return Tempcode The UI
     */
    public function _import()
    {
        require_code('files');
        require_lang('dearchive');

        require_code('uploads');
        is_plupload(true);

        set_mass_import_mode();

        $target_forum = read_multi_code('forum_multi_code');

        $post_templates = $GLOBALS['FORUM_DB']->query_select('f_post_templates', array('id'), array('t_forum_multi_code' => $target_forum));
        require_code('cns_general_action2');
        foreach ($post_templates as $post_template) {
            cns_delete_post_template($post_template['id']);
        }

        foreach ($_FILES as $attach_name => $__file) {
            $tmp_name = $__file['tmp_name'];
            $file = $__file['name'];
            switch (get_file_extension($file)) {
                case 'zip':
                    if ((!function_exists('zip_open')) && (get_option('unzip_cmd') == '')) {
                        warn_exit(do_lang_tempcode('ZIP_NOT_ENABLED'));
                    }
                    if (!function_exists('zip_open')) {
                        require_code('m_zip');
                        $mzip = true;
                    } else {
                        $mzip = false;
                    }
                    $myfile = zip_open($tmp_name);
                    if (!is_integer($myfile)) {
                        while (false !== ($entry = zip_read($myfile))) {
                            // Load in file
                            zip_entry_open($myfile, $entry);

                            $filename = zip_entry_name($entry);

                            if ((strtolower(substr($filename, -4)) == '.txt') && (!should_ignore_file($filename))) {
                                $data = '';
                                do {
                                    $more = zip_entry_read($entry);
                                    if ($more !== false) {
                                        $data .= $more;
                                    }
                                } while (($more !== false) && ($more != ''));

                                $this->_import_stock_response($filename, $data, $target_forum);
                            }

                            zip_entry_close($entry);
                        }

                        zip_close($myfile);
                    } else {
                        require_code('failure');
                        warn_exit(zip_error($myfile, $mzip));
                    }
                    break;
                case 'tar':
                    require_code('tar');
                    $myfile = tar_open($tmp_name, 'rb');
                    if ($myfile !== false) {
                        $directory = tar_get_directory($myfile);
                        foreach ($directory as $entry) {
                            $filename = $entry['path'];

                            if ((strtolower(substr($filename, -4)) == '.txt') && (!should_ignore_file($filename))) {
                                // Load in file
                                $_in = tar_get_file($myfile, $entry['path'], false);

                                $this->_import_stock_response($filename, $_in['data'], $target_forum);
                            }
                        }

                        tar_close($myfile);
                    }
                    break;
                default:
                    if (strtolower(substr($file, -4)) == '.txt') {
                        $this->_import_stock_response($file, file_get_contents($tmp_name), $target_forum);
                    } else {
                        attach_message(do_lang_tempcode('BAD_ARCHIVE_FORMAT'), 'warn');
                    }
            }
        }

        log_it('IMPORT_STOCK_RESPONSES_PT');

        return $this->do_next_manager($this->title, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Import a stock response.
     *
     * @param  PATH $path Path of the file (not on disk, just for reference as a title).
     * @param  string $data Data.
     * @param  SHORT_TEXT $target_forum The forum multicode identifying where the multi-moderation is applicable
     */
    public function _import_stock_response($path, $data, $target_forum)
    {
        require_code('cns_general_action');

        $name = do_lang('STOCK_RESPONSE_PT', ucwords(str_replace(array('/', '\\'), array(': ', ': '), preg_replace('#\.txt$#', '', $path))));

        $data = fix_bad_unicode($data);

        cns_make_post_template($name, $data, $target_forum, 0);
    }

    /**
     * Standard crud_module table function.
     *
     * @param  array $url_map Details to go to build_url for link to the next screen.
     * @return array A quartet: The choose table, Whether re-ordering is supported from this screen, Search URL, Archive URL.
     */
    public function create_selection_list_choose_table($url_map)
    {
        require_code('templates_results_table');

        $current_ordering = get_param_string('sort', 't_title ASC', INPUT_FILTER_GET_COMPLEX);
        if (strpos($current_ordering, ' ') === false) {
            warn_exit(do_lang_tempcode('INTERNAL_ERROR'));
        }
        list($sortable, $sort_order) = explode(' ', $current_ordering, 2);
        $sortables = array(
            't_title' => do_lang_tempcode('TITLE'),
        );
        if (((strtoupper($sort_order) != 'ASC') && (strtoupper($sort_order) != 'DESC')) || (!array_key_exists($sortable, $sortables))) {
            log_hack_attack_and_exit('ORDERBY_HACK');
        }

        $header_row = results_field_title(array(
            do_lang_tempcode('TITLE'),
            do_lang_tempcode('ACTIONS'),
        ), $sortables, 'sort', $sortable . ' ' . $sort_order);

        $fields = new Tempcode();

        list($rows, $max_rows) = $this->get_entry_rows(false, $current_ordering);
        foreach ($rows as $row) {
            $edit_url = build_url($url_map + array('id' => $row['id']), '_SELF');

            $fields->attach(results_entry(array($row['t_title'], protect_from_escaping(hyperlink($edit_url, do_lang_tempcode('EDIT'), false, false, do_lang('EDIT') . ' #' . strval($row['id'])))), true));
        }

        $search_url = null;
        $archive_url = null;

        return array(results_table(do_lang($this->menu_label), get_param_integer('start', 0), 'start', either_param_integer('max', 20), 'max', $max_rows, $header_row, $fields, $sortables, $sortable, $sort_order), false, $search_url, $archive_url);
    }

    /**
     * Get Tempcode for a post template adding/editing form.
     *
     * @param  SHORT_TEXT $title The title (name) of the post template
     * @param  LONG_TEXT $text The actual post template text
     * @param  SHORT_TEXT $forum_multi_code Multi-code identifying forums it is applicable to
     * @param  BINARY $use_default_forums Whether to use as the default post for applicable forums
     * @return array A pair: The input fields, Hidden fields
     */
    public function get_form_fields($title = '', $text = '', $forum_multi_code = '', $use_default_forums = 0)
    {
        $fields = new Tempcode();
        $fields->attach(form_input_line(do_lang_tempcode('TITLE'), do_lang_tempcode('DESCRIPTION_TITLE'), 'title', $title, true));
        $fields->attach(form_input_text_comcode(do_lang_tempcode('FORUM_POST'), do_lang_tempcode('DESCRIPTION_POST_TEMPLATE_X'), 'text', $text, true));
        $fields->attach(cns_get_forum_multi_code_field($forum_multi_code));
        $fields->attach(form_input_tick(do_lang_tempcode('DEFAULT'), do_lang_tempcode('USE_AS_DEFAULT_ON_APPLICABLE_FORUMS'), 'use_default_forums', $use_default_forums == 1));

        return array($fields, new Tempcode());
    }

    /**
     * Standard crud_module edit form filler.
     *
     * @param  ID_TEXT $id The entry being edited
     * @return array A pair: The input fields, Hidden fields
     */
    public function fill_in_edit_form($id)
    {
        $m = $GLOBALS['FORUM_DB']->query_select('f_post_templates', array('*'), array('id' => intval($id)), '', 1);
        if (!array_key_exists(0, $m)) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'post_template'));
        }
        $r = $m[0];

        return $this->get_form_fields($r['t_title'], $r['t_text'], $r['t_forum_multi_code'], $r['t_use_default_forums']);
    }

    /**
     * Standard crud_module add actualiser.
     *
     * @return ID_TEXT The entry added
     */
    public function add_actualisation()
    {
        return strval(cns_make_post_template(post_param_string('title'), post_param_string('text'), read_multi_code('forum_multi_code'), post_param_integer('use_default_forums', 0)));
    }

    /**
     * Standard crud_module edit actualiser.
     *
     * @param  ID_TEXT $id The entry being edited
     */
    public function edit_actualisation($id)
    {
        cns_edit_post_template(intval($id), post_param_string('title'), post_param_string('text'), read_multi_code('forum_multi_code'), post_param_integer('use_default_forums', 0));
    }

    /**
     * Standard crud_module delete actualiser.
     *
     * @param  ID_TEXT $id The entry being deleted
     */
    public function delete_actualisation($id)
    {
        cns_delete_post_template(intval($id));
    }
}
