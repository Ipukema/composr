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
 * @package    search
 */

/**
 * Module page class.
 */
class Module_search
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
        $info['update_require_upgrade'] = 1;
        $info['locked'] = false;
        return $info;
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('searches_saved');
        $GLOBALS['SITE_DB']->drop_table_if_exists('searches_logged');

        delete_privilege('autocomplete_past_search');
        delete_privilege('autocomplete_keyword_comcode_page');
        delete_privilege('autocomplete_title_comcode_page');
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
            $GLOBALS['SITE_DB']->create_table('searches_saved', array(
                'id' => '*AUTO',
                's_title' => 'SHORT_TEXT',
                's_member_id' => 'MEMBER',
                's_time' => 'TIME',
                's_primary' => 'SHORT_TEXT',
                's_auxillary' => 'LONG_TEXT',
            ));

            $GLOBALS['SITE_DB']->create_table('searches_logged', array(
                'id' => '*AUTO',
                's_member_id' => 'MEMBER',
                's_time' => 'TIME',
                's_primary' => 'SHORT_TEXT',
                's_auxillary' => 'LONG_TEXT',
                's_num_results' => 'INTEGER',
            ));

            $GLOBALS['SITE_DB']->create_index('searches_logged', 'past_search', array('s_primary'));

            $GLOBALS['SITE_DB']->create_index('searches_logged', '#past_search_ft', array('s_primary'));

            add_privilege('SEARCH', 'autocomplete_past_search', false);
            add_privilege('SEARCH', 'autocomplete_keyword_comcode_page', false);
            add_privilege('SEARCH', 'autocomplete_title_comcode_page', false);
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
            'browse' => array('SEARCH_TITLE', 'buttons/search'),
        );
    }

    public $title;
    public $ob;
    public $info;

    /**
     * Module pre-run function. Allows us to know meta-data for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('search');
        require_code('database_search');

        if ($type == 'browse' || $type == 'results') {
            inform_non_canonical_parameter('search_under');
            inform_non_canonical_parameter('all_defaults');
            inform_non_canonical_parameter('days');
            inform_non_canonical_parameter('only_titles');
            inform_non_canonical_parameter('conjunctive_operator');
            inform_non_canonical_parameter('boolean_search');
            inform_non_canonical_parameter('only_search_meta');
            inform_non_canonical_parameter('content');
            inform_non_canonical_parameter('author');
            inform_non_canonical_parameter('direction');
            inform_non_canonical_parameter('#^search_.*$#');

            $id = get_param_string('id', '');
            if ($id != '') { // Specific screen, prepare
                require_code('hooks/modules/search/' . filter_naughty_harsh($id), true);
                $ob = object_factory('Hook_search_' . filter_naughty_harsh($id));
                $info = $ob->info();

                if (!is_null($info)) {
                    $this->title = get_screen_title('_SEARCH_TITLE', true, array($info['lang']));
                }

                breadcrumb_set_parents(array(array('_SELF:_SELF', do_lang_tempcode('SEARCH'))));
                breadcrumb_set_self($info['lang']);

                $this->ob = $ob;
                $this->info = $info;
            }
        }

        if ($type == 'browse') {
            $this->title = get_screen_title('SEARCH_TITLE');
        }

        if ($type == 'results') {
            $this->title = get_screen_title('SEARCH_RESULTS');

            attach_to_screen_header('<meta name="robots" content="noindex,nofollow" />'); // XHTMLXHTML
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
        require_css('search');
        require_css('forms');

        $GLOBALS['NO_QUERY_LIMIT'] = true;

        if (function_exists('set_time_limit')) {
            @set_time_limit(15); // We really don't want to let it thrash the DB too long
        }
        send_http_output_ping();

        $type = get_param_string('type', 'browse');
        if (($type == 'browse') || ($type == 'results')) {
            return $this->form();
        }

        return new Tempcode();
    }

    /**
     * The UI to do a search.
     *
     * @return Tempcode The UI
     */
    public function form()
    {
        $id = get_param_string('id', '');

        $_GET['type'] = 'results'; // To make it consistent for the purpose of URL generation (particularly how frames tie together)

        require_code('templates_internalise_screen');

        if ($id != '') { // Specific screen, prepare
            $ob = $this->ob;
            $info = $this->info;

            $under = get_param_string('search_under', '!', true);
            if ((!is_null($info)) && (method_exists($ob, 'get_tree'))) {
                $ob->get_tree($under);
            }
        }

        require_javascript('ajax');
        require_javascript('ajax_people_lists');
        require_javascript('tree_list');

        $content = get_param_string('content', null, true);

        $user_label = do_lang_tempcode('SEARCH_USER');

        $days_label = do_lang_tempcode('SUBMITTED_WITHIN');
        $date_range_label = do_lang_tempcode('DATE_RANGE_LABEL');

        $extra_sort_fields = array();

        $has_template_search = false;

        if ($id != '') { // Specific screen
            $url_map = array('page' => '_SELF', 'type' => 'results', 'id' => $id, 'specific' => 1);
            $catalogue_name = get_param_string('catalogue_name', '');
            if ($catalogue_name != '') {
                $url_map['catalogue_name'] = $catalogue_name;
            }
            $embedded = get_param_integer('embedded', 0);
            if ($embedded == 1) {
                $url_map['embedded'] = 1;
            }
            $url = build_url($url_map, '_SELF', null, false, true);

            require_code('content');
            $content_type = convert_composr_type_codes('search_hook', $id, 'content_type');
            if ($content_type != '') {
                $cma_ob = get_content_object($content_type);
                $cma_info = $cma_ob->info();
                if (isset($info['parent_category_meta_aware_type'])) {
                    $content_type = $info['parent_category_meta_aware_type'];
                }
            }

            require_code('hooks/modules/search/' . filter_naughty_harsh($id), true);
            $ob = object_factory('Hook_search_' . filter_naughty_harsh($id));
            $info = $ob->info();
            if (is_null($info)) {
                warn_exit(do_lang_tempcode('SEARCH_HOOK_NOT_AVAILABLE'));
            }

            if (array_key_exists('user_label', $info)) {
                $user_label = $info['user_label'];
            }
            if (array_key_exists('days_label', $info)) {
                $days_label = $info['days_label'];
            }
            if (array_key_exists('date_range_label', $info)) {
                $date_range_label = $info['date_range_label'];
            }

            $extra_sort_fields = array_key_exists('extra_sort_fields', $info) ? $info['extra_sort_fields'] : array();

            $under = null;
            if (method_exists($ob, 'ajax_tree')) {
                $ajax = true;
                $under = get_param_string('search_under', '', true);
                $ajax_tree = $ob->ajax_tree();
                if (is_object($ajax_tree)) {
                    return $ajax_tree;
                }
                list($ajax_hook, $ajax_options) = $ajax_tree;

                require_code('hooks/systems/ajax_tree/' . $ajax_hook);
                $tree_hook_ob = object_factory('Hook_' . $ajax_hook);
                $simple_content = $tree_hook_ob->simple(null, $ajax_options, preg_replace('#,.*$#', '', $under));

                $nice_label = $under;
                if (!is_null($under)) {
                    $simple_content_evaluated = $simple_content->evaluate();
                    $matches = array();
                    if (preg_match('#<option [^>]*value="' . preg_quote($under, '#') . '(' . ((strpos($under, ',') === false) ? ',' : '') . '[^"]*)?"[^>]*>([^>]* &gt; )?([^>]*)</option>#', $simple_content_evaluated, $matches) != 0) {
                        if (strpos($under, ',') === false) {
                            $under = $under . $matches[1];
                        }
                        $nice_label = trim($matches[3]);
                    }
                }

                require_code('form_templates');
                $tree = do_template('FORM_SCREEN_INPUT_TREE_LIST', array(
                    '_GUID' => '25368e562be3b4b9c6163aa008b47c91',
                    'MULTI_SELECT' => false,
                    'TABINDEX' => strval(get_form_field_tabindex()),
                    'NICE_LABEL' => (is_null($nice_label) || $nice_label == '-1') ? '' : $nice_label,
                    'END_OF_FORM' => true,
                    'REQUIRED' => '',
                    '_REQUIRED' => false,
                    'USE_SERVER_ID' => false,
                    'NAME' => 'search_under',
                    'DEFAULT' => $under,
                    'HOOK' => $ajax_hook,
                    'ROOT_ID' => '',
                    'OPTIONS' => serialize($ajax_options),
                    'DESCRIPTION' => '',
                    'CONTENT_TYPE' => $content_type,
                ));
            } else {
                $ajax = false;
                $tree = form_input_list_entry('!', false, do_lang_tempcode('NA_EM'));
                if (method_exists($ob, 'get_tree')) {
                    $under = get_param_string('search_under', '!', true);
                    $tree->attach($ob->get_tree($under));
                }
            }

            $options = new Tempcode();
            if (array_key_exists('special_on', $info)) {
                foreach ($info['special_on'] as $name => $display) {
                    $options->attach(do_template('SEARCH_FOR_SEARCH_DOMAIN_OPTION', array('_GUID' => 'c1853f42d0a110026453f8b94c9f623c', 'CHECKED' => (is_null($content)) || (get_param_integer('option_' . $id . '_' . $name, 0) == 1), 'NAME' => 'option_' . $id . '_' . $name, 'DISPLAY' => $display)));
                }
            }
            if (array_key_exists('special_off', $info)) {
                foreach ($info['special_off'] as $name => $display) {
                    $options->attach(do_template('SEARCH_FOR_SEARCH_DOMAIN_OPTION', array('_GUID' => '2223ada7636c85e6879feb9a6f6885d2', 'CHECKED' => (get_param_integer('option_' . $id . '_' . $name, 0) == 1), 'NAME' => 'option_' . $id . '_' . $name, 'DISPLAY' => $display)));
                }
            }
            if (method_exists($ob, 'get_fields')) {
                $fields = $ob->get_fields();
                foreach ($fields as $field) {
                    $template = 'SEARCH_FOR_SEARCH_DOMAIN_OPTION' . $field['TYPE'];
                    $fallback = null;
                    $has_range = (substr($field['TYPE'], -strlen('_RANGE')) == '_RANGE');
                    if ($has_range) {
                        $fallback = 'SEARCH_FOR_SEARCH_DOMAIN_OPTION' . substr($field['TYPE'], 0, strlen($field['TYPE']) - strlen('_RANGE'));
                    }
                    $options->attach(do_template($template, array(
                        '_GUID' => 'a223ada7636c85e6879feb9a6f6885d2',
                        'NAME' => 'option_' . $field['NAME'],
                        'DISPLAY' => $field['DISPLAY'],
                        'SPECIAL' => $field['SPECIAL'],
                        'CHECKED' => array_key_exists('checked', $field) ? $field['CHECKED'] : false,
                        'HAS_RANGE' => $has_range,
                    ), null, false, $fallback));
                }

                $has_template_search = true;
            }

            $specialisation = do_template('SEARCH_ADVANCED', array('_GUID' => 'fad0c147b8291ba972f105c65715f1ac', 'AJAX' => $ajax, 'OPTIONS' => $options, 'TREE' => $tree, 'UNDERNEATH' => !is_null($under)));
        } else { // General screen
            $map = array('page' => '_SELF', 'type' => 'results');
            $under = get_param_string('search_under', '-1', true);
            if ($under != '-1') {
                $map['search_under'] = $under;
            }
            $url = build_url($map, '_SELF', null, false, true);

            $search_domains = new Tempcode();
            $_search_domains = array();
            $_hooks = find_all_hooks('modules', 'search');
            foreach (array_keys($_hooks) as $hook) {
                require_code('hooks/modules/search/' . filter_naughty_harsh($hook));
                $ob = object_factory('Hook_search_' . filter_naughty_harsh($hook), true);
                if (is_null($ob)) {
                    continue;
                }
                $info = $ob->info();
                if (is_null($info)) {
                    continue;
                }

                $is_default_or_advanced = (($info['default']) && ($id == '')) || ($hook == $id);

                $checked = (get_param_integer('search_' . $hook, (((is_null($content)) && (get_param_integer('all_defaults', null) !== 0)) || (get_param_integer('all_defaults', 0) == 1)) ? ($is_default_or_advanced ? 1 : 0) : 0) == 1);

                $options_url = ((array_key_exists('special_on', $info)) || (array_key_exists('special_off', $info)) || (array_key_exists('extra_sort_fields', $info)) || (method_exists($ob, 'get_fields')) || (method_exists($ob, 'get_tree')) || (method_exists($ob, 'get_ajax_tree'))) ? build_url(array('page' => '_SELF', 'id' => $hook), '_SELF', null, false, true) : new Tempcode();

                $_search_domains[] = array('_GUID' => '3d3099872184923aec0f49388f52c750', 'ADVANCED_ONLY' => (array_key_exists('advanced_only', $info)) && ($info['advanced_only']), 'CHECKED' => $checked, 'OPTIONS_URL' => $options_url, 'LANG' => $info['lang'], 'NAME' => $hook);
            }
            sort_maps_by($_search_domains, 'LANG');
            foreach ($_search_domains as $sd) {
                $search_domains->attach(do_template('SEARCH_FOR_SEARCH_DOMAIN', $sd));
            }

            $specialisation = do_template('SEARCH_DOMAINS', array('_GUID' => '1fd8718b540ec475988070ee7a444dc1', 'SEARCH_DOMAINS' => $search_domains));
        }

        $author = get_param_string('author', '');
        $author_id = ($author != '') ? $GLOBALS['FORUM_DRIVER']->get_member_from_username($author) : null;
        $sort = get_param_string('sort', 'relevance');
        $direction = get_param_string('direction', 'DESC');
        if (!in_array(strtoupper($direction), array('ASC', 'DESC'))) {
            log_hack_attack_and_exit('ORDERBY_HACK');
        }
        $only_titles = get_param_integer('only_titles', 0) == 1;
        $search_under = get_param_string('search_under', '!', true);
        if ($search_under == '') {
            $search_under = '!';
        }
        $boolean_operator = get_param_string('conjunctive_operator', 'OR');

        $has_fulltext_search = db_has_full_text($GLOBALS['SITE_DB']->connection_read);

        $can_order_by_rating = db_has_subqueries($GLOBALS['SITE_DB']->connection_read);

        $days = mixed();

        $cutoff_from_day = mixed();
        $cutoff_from_month = mixed();
        $cutoff_from_year = mixed();
        $cutoff_to_day = mixed();
        $cutoff_to_month = mixed();
        $cutoff_to_year = mixed();

        if (get_option('search_with_date_range') == '1') {
            $cutoff_from = post_param_date('cutoff_from', true);
            $cutoff_to = post_param_date('cutoff_to', true);
            if (is_null($cutoff_from) && is_null($cutoff_to)) {
                $cutoff = null;
            } else {
                $cutoff = array($cutoff_from, $cutoff_to);

                $cutoff_from_day = is_null($cutoff_from) ? null : intval(date('d', utctime_to_usertime($cutoff_from)));
                $cutoff_from_month = is_null($cutoff_from) ? null : intval(date('m', utctime_to_usertime($cutoff_from)));
                $cutoff_from_year = is_null($cutoff_from) ? null : intval(date('Y', utctime_to_usertime($cutoff_from)));
                $cutoff_to_day = is_null($cutoff_to) ? null : intval(date('d', utctime_to_usertime($cutoff_to)));
                $cutoff_to_month = is_null($cutoff_to) ? null : intval(date('m', utctime_to_usertime($cutoff_to)));
                $cutoff_to_year = is_null($cutoff_to) ? null : intval(date('Y', utctime_to_usertime($cutoff_to)));
            }
        } else {
            $days = get_param_integer('days', null);
            if ($days === null) {
                $_days = get_value('search_days__' . $id);
                if ($_days === null) {
                    $days = ($id == 'cns_members') ? -1 : 60;
                } else {
                    $days = intval($_days);
                    if ($days == 0) {
                        $days = -1;
                    }
                }
            }

            $cutoff = ($days == -1) ? null : (time() - $days * 24 * 60 * 60);
        }

        // Perform search, if we did one
        $out = null;
        $pagination = '';
        $num_results = 0;
        if (!is_null($content)) {
            list($out, $pagination, $num_results) = $this->results($id, $author, $author_id, $cutoff, $sort, $direction, $only_titles, $search_under);

            if (has_zone_access(get_member(), 'adminzone')) {
                $admin_search_url = build_url(array('page' => 'admin', 'type' => 'search', 'content' => $content), 'adminzone');
                attach_message(do_lang_tempcode('ALSO_ADMIN_ZONE_SEARCH', escape_html($admin_search_url->evaluate())), 'inform');
            }
        }

        $tpl = do_template('SEARCH_FORM_SCREEN', array(
            '_GUID' => '8bb208185740183323a6fe6e89d55de5',
            'SEARCH_TERM' => is_null($content) ? '' : $content,
            'HAS_TEMPLATE_SEARCH' => $has_template_search,
            'NUM_RESULTS' => integer_format($num_results),
            'CAN_ORDER_BY_RATING' => $can_order_by_rating,
            'EXTRA_SORT_FIELDS' => $extra_sort_fields,
            'USER_LABEL' => $user_label,
            'BOOLEAN_SEARCH' => $this->_is_boolean_search(),
            'AND' => $boolean_operator == 'AND',
            'ONLY_TITLES' => $only_titles,
            'SORT' => $sort,
            'DIRECTION' => $direction,
            'CONTENT' => $content,
            'RESULTS' => $out,
            'PAGINATION' => $pagination,
            'HAS_FULLTEXT_SEARCH' => $has_fulltext_search,
            'TITLE' => $this->title,
            'AUTHOR' => $author,
            'SPECIALISATION' => $specialisation,
            'URL' => $url,
            'SEARCH_TYPE' => ($id == '') ? null : $id,

            'DAYS_LABEL' => (get_option('search_with_date_range') == '1') ? null : $days_label,
            'DAYS' => is_null($days) ? '' : strval($days),
            'DATE_RANGE_LABEL' => (get_option('search_with_date_range') == '1') ? $date_range_label : null,
            'CUTOFF_FROM_DAY' => is_null($cutoff_from_day) ? '' : strval($cutoff_from_day),
            'CUTOFF_FROM_MONTH' => is_null($cutoff_from_month) ? '' : strval($cutoff_from_month),
            'CUTOFF_FROM_YEAR' => is_null($cutoff_from_year) ? '' : strval($cutoff_from_year),
            'CUTOFF_TO_DAY' => is_null($cutoff_to_day) ? '' : strval($cutoff_to_day),
            'CUTOFF_TO_MONTH' => is_null($cutoff_to_month) ? '' : strval($cutoff_to_month),
            'CUTOFF_TO_YEAR' => is_null($cutoff_to_year) ? '' : strval($cutoff_to_year),
        ));

        require_code('templates_internalise_screen');
        return internalise_own_screen($tpl);
    }

    /**
     * Find whether we are doing a boolean search.
     *
     * @return boolean Whether we are
     */
    public function _is_boolean_search()
    {
        $content = get_param_string('content', '', true);

        $boolean_search = get_param_integer('boolean_search', 0) == 1;
        if (get_option('enable_boolean_search') == '0') {
            $boolean_search = false;
            if ((db_has_full_text($GLOBALS['SITE_DB']->connection_read)) && (method_exists($GLOBALS['SITE_DB']->static_ob, 'db_has_full_text_boolean')) && ($GLOBALS['SITE_DB']->static_ob->db_has_full_text_boolean())) {
                $boolean_search = (preg_match('#["\+\-]#', $content) != 0);
            }
        }
        return $boolean_search;
    }

    /**
     * The actualiser of a search.
     *
     * @param  ID_TEXT $id Codename for what's being searched (blank: mixed search)
     * @param  string $author Author name
     * @param  ?AUTO_LINK $author_id Author ID (null: none given)
     * @param  mixed $cutoff Cutoff date (TIME or a pair representing the range)
     * @param  ID_TEXT $sort Sort key
     * @param  ID_TEXT $direction Sort direction
     * @set    ASC DESC
     * @param  boolean $only_titles Whether to only search titles
     * @param  string $search_under Comma-separated list of categories to search under
     * @return array A triple: The results, results browser, the number of results
     */
    public function results($id, $author, $author_id, $cutoff, $sort, $direction, $only_titles, $search_under)
    {
        cache_module_installed_status();

        // What we're searching for
        $content = get_param_string('content', false, true);

        // Did you mean?
        require_code('spelling');
        $corrected = spell_correct_phrase($content);
        if ($corrected != $content) {
            $search_url = get_self_url(true, false, array('content' => $corrected));
            attach_message(do_lang_tempcode('DID_YOU_MEAN', escape_html($corrected), escape_html($search_url)), 'notice');
        }

        // Search keyword highlighting in any loaded Comcode
        global $SEARCH__CONTENT_BITS;
        $_content_bits = explode(' ', str_replace('"', '', preg_replace('#(^|\s)\+#', '', preg_replace('#(^|\s)\-#', '', $content))));
        $SEARCH__CONTENT_BITS = array();
        require_code('textfiles');
        $too_common_words = explode("\n", read_text_file('too_common_words', '', true));
        foreach ($_content_bits as $content_bit) {
            $content_bit = trim($content_bit);
            if ($content_bit == '') {
                continue;
            }
            if (!in_array(strtolower($content_bit), $too_common_words)) {
                $SEARCH__CONTENT_BITS[] = $content_bit;
            }
        }

        $start = get_param_integer('search_start', 0);
        $default_max = intval(get_option('search_results_per_page'));
        if ((ini_get('memory_limit') != '-1') && (ini_get('memory_limit') != '0')) {
            if (intval(preg_replace('#M$#', '', ini_get('memory_limit'))) < 20) {
                $default_max = 5;
            }
        }
        $max = get_param_integer('search_max', $default_max);  // Also see get_search_rows

        $save_title = get_param_string('save_title', '');
        if ((!is_guest()) && ($save_title != '') && ($start == 0)) {
            static $saved_search = false;
            if (!$saved_search) {
                $GLOBALS['SITE_DB']->query_insert('searches_saved', array(
                    's_title' => $save_title,
                    's_member_id' => get_member(),
                    's_time' => time(),
                    's_primary' => $content,
                    's_auxillary' => serialize(array_merge($_POST, $_GET)),
                ));
                $saved_search = true;
            }
        }

        $boolean_operator = get_param_string('conjunctive_operator', 'OR');
        $boolean_search = $this->_is_boolean_search();
        list($content_where) = build_content_where($content, $boolean_search, $boolean_operator);

        disable_php_memory_limit();

        // Search under all hooks we've asked to search under
        $results = array();
        $_hooks = find_all_hooks('modules', 'search');
        foreach (array_keys($_hooks) as $hook) {
            $test = get_param_integer('search_' . $hook, 0);

            if ((($test == 1) || ((get_param_integer('all_defaults', 0) == 1) && (true)) || ($id == $hook)) && (($id == '') || ($id == $hook))) {
                require_code('hooks/modules/search/' . filter_naughty_harsh($hook));
                $ob = object_factory('Hook_search_' . filter_naughty_harsh($hook), true);
                if (is_null($ob)) {
                    continue;
                }
                $info = $ob->info();
                if (is_null($info)) {
                    continue;
                }
            }

            if ((($test == 1) || ((get_param_integer('all_defaults', 0) == 1) && ($info['default'])) || ($id == $hook)) && (($id == '') || ($id == $hook))) {
                // Category filter
                if (($search_under != '!') && ($search_under != '-1') && (array_key_exists('category', $info))) {
                    $cats = explode(',', $search_under);
                    $where_clause = '(';
                    foreach ($cats as $cat) {
                        if (trim($cat) == '') {
                            continue;
                        }

                        if ($where_clause != '(') {
                            $where_clause .= ' OR ';
                        }
                        if ($info['integer_category']) {
                            $where_clause .= ((strpos($info['category'], '.') !== false) ? '' : 'r.') . $info['category'] . '=' . strval($cat);
                        } else {
                            $where_clause .= db_string_equal_to(((strpos($info['category'], '.') !== false) ? '' : 'r.') . $info['category'], $cat);
                        }
                    }
                    $where_clause .= ')';
                } else {
                    $where_clause = '';
                }

                $only_search_meta = get_param_integer('only_search_meta', 0) == 1;
                $direction = get_param_string('direction', 'ASC');
                if (function_exists('set_time_limit')) {
                    @set_time_limit(5); // Prevent errant search hooks (easily written!) taking down a server. Each call given 5 seconds (calling set_time_limit resets the timer).
                }
                $hook_results = $ob->run($content, $only_search_meta, $direction, $max, $start, $only_titles, $content_where, $author, $author_id, $cutoff, $sort, $max, $boolean_operator, $where_clause, $search_under, $boolean_search ? 1 : 0);
                if (is_null($hook_results)) {
                    continue;
                }
                foreach ($hook_results as $i => $result) {
                    $result['object'] = $ob;
                    $result['type'] = $hook;
                    $hook_results[$i] = $result;
                }

                $results = sort_search_results($hook_results, $results, $direction);
            }
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(15);
        }

        // Now glue our templates together
        $out = build_search_results_interface($results, $start, $max, $direction, $id == '');
        if ($out->is_empty()) {
            if ((is_integer($cutoff)) && ($GLOBALS['TOTAL_SEARCH_RESULTS'] == 0)) {
                $ret_maybe = $this->results($id, $author, $author_id, null, $sort, $direction, $only_titles, $search_under);
                if (!$ret_maybe[0]->is_empty()) {
                    attach_message(do_lang_tempcode('NO_RESULTS_DAYS', escape_html(integer_format(intval((time() - $cutoff) / 24.0 * 60.0 * 60.0)))), 'notice');
                    return $ret_maybe;
                }
            }

            return array(new Tempcode(), new Tempcode(), 0);
        }

        require_code('templates_pagination');
        $pagination = pagination(do_lang_tempcode('RESULTS'), $start, 'search_start', $max, 'search_max', $GLOBALS['TOTAL_SEARCH_RESULTS'], true);

        if ($start == 0) {
            $GLOBALS['SITE_DB']->query_insert('searches_logged', array(
                's_member_id' => get_member(),
                's_time' => time(),
                's_primary' => substr($content, 0, 255),
                's_auxillary' => serialize(array_merge($_POST, $_GET)),
                's_num_results' => count($results),
            ));
        }

        return array($out, $pagination, $GLOBALS['TOTAL_SEARCH_RESULTS']);
    }
}
