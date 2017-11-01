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
 * @package    search
 */

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__search()
{
    if (!defined('MINIMUM_AUTOCOMPLETE_LENGTH')) {
        define('MINIMUM_AUTOCOMPLETE_LENGTH', intval(get_option('minimum_autocomplete_length')));
        define('MINIMUM_AUTOCOMPLETE_PAST_SEARCH', intval(get_option('minimum_autocomplete_past_search')));
        define('MAXIMUM_AUTOCOMPLETE_SUGGESTIONS', intval(get_option('maximum_autocomplete_suggestions')));
    }
}

/**
 * Base class for catalogue search / custom content fields search.
 *
 * @package        search
 */
abstract class FieldsSearchHook
{
    /**
     * Get a list of extra sort fields.
     *
     * @param string $catalogue_name Catalogue we are searching in in (may be a special custom content fields catalogue)
     * @return array A map between parameter name and string label
     */
    protected function _get_extra_sort_fields($catalogue_name)
    {
        $extra_sort_fields = array();

        if (addon_installed('catalogues')) {
            require_code('fields');

            $rows = $GLOBALS['SITE_DB']->query_select('catalogue_fields', array('id', 'cf_name', 'cf_type', 'cf_default'), array('c_name' => $catalogue_name, 'cf_searchable' => 1, 'cf_visible' => 1), 'ORDER BY cf_order,' . $GLOBALS['SITE_DB']->translate_field_ref('cf_name'));
            foreach ($rows as $i => $row) {
                $ob = get_fields_hook($row['cf_type']);
                $temp = $ob->inputted_to_sql_for_search($row, $i);
                if (is_null($temp)) { // Standard direct 'substring' search
                    $extra_sort_fields['f' . strval($i) . '_actual_value'] = get_translated_text($row['cf_name']);
                }
            }
        }

        return $extra_sort_fields;
    }

    /**
     * Get a list of extra fields to ask for.
     *
     * @param string $catalogue_name Catalogue to search in (may be a special custom content fields catalogue)
     * @return array A list of maps specifying extra fields
     */
    protected function _get_fields($catalogue_name)
    {
        if (!addon_installed('catalogues')) {
            return array();
        }

        $fields = array();
        $rows = $GLOBALS['SITE_DB']->query_select('catalogue_fields', array('*'), array('c_name' => $catalogue_name, 'cf_searchable' => 1, 'cf_visible' => 1), 'ORDER BY cf_order,' . $GLOBALS['FORUM_DB']->translate_field_ref('cf_name'));
        require_code('fields');
        foreach ($rows as $row) {
            $ob = get_fields_hook($row['cf_type']);
            $temp = $ob->get_search_inputter($row);
            if (is_null($temp)) {
                $type = '_TEXT';
                $special = get_param_string('option_' . strval($row['id']), '');
                $extra = '';
                $display = get_translated_text($row['cf_name']);
                $fields[] = array('NAME' => strval($row['id']) . $extra, 'DISPLAY' => $display, 'TYPE' => $type, 'SPECIAL' => $special);
            } else {
                $fields[] = $temp;
            }
        }
        return $fields;
    }

    /**
     * Get details needed (SQL etc) to perform an advanced field search.
     *
     * @param string $catalogue_name Catalogue we are searching in in (may be a special custom content fields catalogue)
     * @param string $table_alias Table alias for main content table
     * @return ?array A big tuple of details used to search with (null: no fields)
     */
    protected function _get_search_parameterisation_advanced($catalogue_name, $table_alias = 'r')
    {
        if (!addon_installed('catalogues')) {
            return null;
        }

        $where_clause = '';

        $fields = $GLOBALS['SITE_DB']->query_select('catalogue_fields', array('*'), array('c_name' => $catalogue_name, 'cf_searchable' => 1), 'ORDER BY cf_order,' . $GLOBALS['SITE_DB']->translate_field_ref('cf_name'));
        if (count($fields) == 0) {
            return null;
        }

        $table = '';
        $trans_fields = array('!' => '!');
        $nontrans_fields = array();
        $title_field = mixed();
        require_code('fields');
        foreach ($fields as $i => $field) {
            $ob = get_fields_hook($field['cf_type']);
            $temp = $ob->inputted_to_sql_for_search($field, $i);
            if (is_null($temp)) { // Standard direct 'substring' search
                list(, , $row_type) = $ob->get_field_value_row_bits($field);
                switch ($row_type) {
                    case 'long_trans':
                        $trans_fields['f' . strval($i) . '.cv_value'] = 'LONG_TRANS__COMCODE';
                        $table .= ' LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'catalogue_efv_long_trans f' . strval($i) . ' ON (f' . strval($i) . '.ce_id=' . $table_alias . '.id AND f' . strval($i) . '.cf_id=' . strval($field['id']) . ')';
                        if (multi_lang_content()) {
                            $search_field = 't' . strval(count($trans_fields) - 1) . '.text_original';
                        } else {
                            $search_field = 'f' . strval($i) . '.cv_value';
                        }
                        break;
                    case 'short_trans':
                        $trans_fields['f' . strval($i) . '.cv_value'] = 'SHORT_TRANS__COMCODE';
                        $table .= ' LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'catalogue_efv_short_trans f' . strval($i) . ' ON (f' . strval($i) . '.ce_id=' . $table_alias . '.id AND f' . strval($i) . '.cf_id=' . strval($field['id']) . ')';
                        if (multi_lang_content()) {
                            $search_field = 't' . strval(count($trans_fields) - 1) . '.text_original';
                        } else {
                            $search_field = 'f' . strval($i) . '.cv_value';
                        }
                        break;
                    case 'long':
                        $nontrans_fields[] = 'f' . strval($i) . '.cv_value';
                        $table .= ' LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'catalogue_efv_long f' . strval($i) . ' ON (f' . strval($i) . '.ce_id=' . $table_alias . '.id AND f' . strval($i) . '.cf_id=' . strval($field['id']) . ')';
                        if (multi_lang_content()) {
                            $search_field = 't' . strval(count($trans_fields) - 1) . '.text_original';
                        } else {
                            $search_field = 'f' . strval($i) . '.cv_value';
                        }
                        break;
                    case 'short':
                        $nontrans_fields[] = 'f' . strval($i) . '.cv_value';
                        $table .= ' LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'catalogue_efv_short f' . strval($i) . ' ON (f' . strval($i) . '.ce_id=' . $table_alias . '.id AND f' . strval($i) . '.cf_id=' . strval($field['id']) . ')';
                        $search_field = 'f' . strval($i) . '.cv_value';
                        break;
                    case 'float':
                        $table .= ' LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'catalogue_efv_float f' . strval($i) . ' ON (f' . strval($i) . '.ce_id=' . $table_alias . '.id AND f' . strval($i) . '.cf_id=' . strval($field['id']) . ')';
                        $search_field = 'f' . strval($i) . '.cv_value';
                        break;
                    case 'integer':
                        $table .= ' LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'catalogue_efv_integer f' . strval($i) . ' ON (f' . strval($i) . '.ce_id=' . $table_alias . '.id AND f' . strval($i) . '.cf_id=' . strval($field['id']) . ')';
                        $search_field = 'f' . strval($i) . '.cv_value';
                        break;
                }

                $range_search = (option_value_from_field_array($field, 'range_search', 'off') == 'on');
                if ($range_search) {
                    if (method_exists($ob, 'get_search_filter_from_env')) {
                        list($from, $to) = explode(';', $ob->get_search_filter_from_env($field));
                    } else {
                        $from = get_param_string('option_' . strval($field['id']) . '_from', '');
                        $to = get_param_string('option_' . strval($field['id']) . '_to', '');
                    }
                    if ($from != '' || $to != '') {
                        if ($from == '') {
                            $from = $to;
                        }
                        if ($to == '') {
                            $to = $from;
                        }

                        $where_clause .= ' AND ';

                        if (is_numeric($from) && is_numeric($to)) {
                            $where_clause .= $search_field . '>=' . $from . ' AND ' . $search_field . '<=' . $to;
                        } else {
                            $where_clause .= $search_field . '>=\'' . db_escape_string($from) . '\' AND ' . $search_field . '<=\'' . db_escape_string($to) . '\'';
                        }
                    }
                } else {
                    if (method_exists($ob, 'get_search_filter_from_env')) {
                        $param = $ob->get_search_filter_from_env($field);
                    } else {
                        $param = get_param_string('option_' . strval($field['id']), '');
                    }

                    if ($param != '') {
                        $where_clause .= ' AND ';

                        if (substr($param, 0, 1) == '=') {
                            $where_clause .= db_string_equal_to($search_field, substr($param, 1));
                        } elseif ($row_type == 'integer' || $row_type == 'float') {
                            if (is_numeric($param)) {
                                $where_clause .= $search_field . '=' . $param;
                            } else {
                                $where_clause .= db_string_equal_to($search_field, $param);
                            }
                        } else {
                            if ((db_has_full_text($GLOBALS['SITE_DB']->connection_read)) && (method_exists($GLOBALS['SITE_DB']->static_ob, 'db_has_full_text_boolean')) && ($GLOBALS['SITE_DB']->static_ob->db_has_full_text_boolean()) && (!is_under_radar($param))) {
                                $temp = db_full_text_assemble($param, true);
                            } else {
                                list($temp,) = db_like_assemble($param);
                            }
                            $where_clause .= preg_replace('#\?#', $search_field, $temp);
                        }
                    }
                }
            } else {
                $table .= $temp[2];
                $search_field = $temp[3];
                if ($temp[4] != '') {
                    $where_clause .= ' AND ';
                    $where_clause .= $temp[4];
                } else {
                    $trans_fields = array_merge($trans_fields, $temp[0]);
                    $non_trans_fields = array_merge($nontrans_fields, $temp[1]);
                }
            }
            if ($i == 0) {
                $title_field = $search_field;
            }
        }

        $where_clause .= ' AND ';
        if ($catalogue_name[0] == '_') {
            $where_clause .= '(' . db_string_equal_to($table_alias . '.c_name', $catalogue_name) . ' OR ' . $table_alias . '.c_name IS NULL' . ')';
        } else {
            $where_clause .= db_string_equal_to($table_alias . '.c_name', $catalogue_name);
        }

        return array($table, $where_clause, $trans_fields, $nontrans_fields, $title_field);
    }

    /**
     * Get details needed (SQL etc) to perform an advanced field search for custom content fields (builds on _get_search_parameterisation_advanced).
     *
     * @param string $catalogue_name Catalogue we are searching in in (may be a special custom content fields catalogue)
     * @param string $table Table clause to add to
     * @param string $where_clause Where clause to add to
     * @param array $trans_fields Translatable fields to add to
     * @param array $nontrans_fields Non-translatable fields to add to
     */
    protected function _get_search_parameterisation_advanced_for_content_type($catalogue_name, &$table, &$where_clause, &$trans_fields, &$nontrans_fields)
    {
        $advanced = $this->_get_search_parameterisation_advanced($catalogue_name, 'ce');
        if (is_null($advanced)) {
            return;
        }

        $table .= ' LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'catalogue_entry_linkage l ON l.content_id=' . db_cast('r.id', 'CHAR') . ' AND ' . db_string_equal_to('content_type', substr($catalogue_name, 1));
        $table .= ' LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'catalogue_entries ce ON ce.id=l.catalogue_entry_id';

        list($sup_table, $sup_where_clause, $sup_trans_fields, $sup_nontrans_fields) = $advanced;
        $table .= $sup_table;
        $where_clause .= $sup_where_clause;
        $trans_fields = array_merge($trans_fields, $sup_trans_fields);
        $nontrans_fields = array_merge($nontrans_fields, $sup_nontrans_fields);
    }

    /**
     * Insert a date range check into a WHERE clause.
     *
     * @param  mixed $cutoff Cutoff date (TIME or a pair representing the range)
     * @param  string $field The field name of the timestamp field in the database
     * @param  string $where_clause Additional where clause will be written into here
     */
    protected function _handle_date_check($cutoff, $field, &$where_clause)
    {
        if (!is_null($cutoff)) {
            if (is_integer($cutoff)) {
                $where_clause .= ' AND ' . $field . '>' . strval($cutoff);
            } elseif (is_array($cutoff)) {
                if (!is_null($cutoff[0])) {
                    $where_clause .= ' AND ' . $field . '>=' . strval($cutoff[0]);
                }
                if (!is_null($cutoff[1])) {
                    $where_clause .= ' AND ' . $field . '<=' . strval($cutoff[1]);
                }
            }
        }
    }

    /**
     * Do a date range check for a known timestamp.
     *
     * @param  mixed $cutoff Cutoff date (TIME or a pair representing the range)
     * @param  TIME $compare Timestamp to compare to
     * @return boolean Whether the date matches the requirements of $cutoff
     */
    protected function _handle_date_check_runtime($cutoff, $compare)
    {
        if (!is_null($cutoff)) {
            if (is_integer($cutoff)) {
                if ($compare < $cutoff) {
                    return false;
                }
            } elseif (is_array($cutoff)) {
                if (((!is_null($cutoff[0])) && ($compare < $cutoff[0])) || ((!is_null($cutoff[1])) && ($compare > $cutoff[1]))) {
                    return false;
                }
            }
        }
        return true;
    }
}

/**
 * Find whether a phrase is too small for fulltext search.
 *
 * @param  string $test The phrase
 * @return boolean Whether it is
 */
function is_under_radar($test)
{
    if (get_option('enable_boolean_search') == '0') {
        return false;
    }

    require_code('database_search');

    return ((strlen($test) < get_minimum_search_length()) && ($test != ''));
}

/**
 * Find autocomplete suggestions to complete a partially-typed search request.
 *
 * @param  string $request Search request
 * @param  ID_TEXT $search_type The search type it is for (blank: N/A)
 * @return array List of suggestions
 */
function find_search_suggestions($request, $search_type = '')
{
    $suggestions = array();

    if (strlen($request) < MINIMUM_AUTOCOMPLETE_LENGTH) {
        return $suggestions;
    }

    // NB: We only bind to string starts for our matches, as this is indexable in the DB. Mid-match is too slow due to non-indexed.

    // Based on past searches
    if (has_privilege(get_member(), 'autocomplete_past_search')) {
        $q = 'SELECT s_primary AS search FROM ' . get_table_prefix() . 'searches_logged WHERE ';
        if ((db_has_full_text($GLOBALS['SITE_DB']->connection_read)) && (method_exists($GLOBALS['SITE_DB']->static_ob, 'db_has_full_text_boolean')) && ($GLOBALS['SITE_DB']->static_ob->db_has_full_text_boolean()) && (!is_under_radar($request))) {
            $q .= preg_replace('#\?#', 's_primary', db_full_text_assemble($request, false));
        } else {
            $q .= 's_primary LIKE \'' . db_encode_like($request . '%') . '\'';
        }
        $q .= ' AND s_primary NOT LIKE \'' . db_encode_like('%<%') . '\'';
        $q .= ' AND ' . db_string_not_equal_to('s_primary', '');
        $q .= ' GROUP BY s_primary HAVING COUNT(*)>' . strval(MINIMUM_AUTOCOMPLETE_PAST_SEARCH);
        $q .= ' ORDER BY COUNT(*) DESC';
        $rows = $GLOBALS['SITE_DB']->query($q, MAXIMUM_AUTOCOMPLETE_SUGGESTIONS);
        foreach ($rows as $search) {
            if (count($suggestions) < MAXIMUM_AUTOCOMPLETE_SUGGESTIONS) {
                $suggestions[$search['search']] = true;
            }
        }
    }

    if ($search_type != '') {
        require_code('content');
        $feedback_type = convert_composr_type_codes('search_hook', $search_type, 'feedback_type_code');

        if ($feedback_type != '') {
            $content_type = convert_composr_type_codes('search_hook', $search_type, 'content_type');

            // Based on keywords
            if ((has_privilege(get_member(), 'autocomplete_keyword_' . $content_type)) && (count($suggestions) < MAXIMUM_AUTOCOMPLETE_SUGGESTIONS)) {
                if (multi_lang_content()) {
                    $q = 'SELECT text_original AS search FROM ' . get_table_prefix() . 'seo_meta_keywords m JOIN ' . get_table_prefix() . 'translate t ON t.id=m.meta_keyword';
                    $q .= ' WHERE meta_keyword LIKE \'' . db_encode_like($request . '%') . '\'';
                    $q .= ' AND ' . db_string_equal_to('meta_for_type', $feedback_type);
                    $q .= ' GROUP BY text_original';
                } else {
                    $q = 'SELECT meta_keyword AS search FROM ' . get_table_prefix() . 'seo_meta_keywords';
                    $q .= ' WHERE meta_keyword LIKE \'' . db_encode_like($request . '%') . '\'';
                    $q .= ' AND ' . db_string_equal_to('meta_for_type', $feedback_type);
                    $q .= ' GROUP BY meta_keyword';
                }
                $q .= ' ORDER BY COUNT(*) DESC';
                $rows = $GLOBALS['SITE_DB']->query($q, MAXIMUM_AUTOCOMPLETE_SUGGESTIONS);
                foreach ($rows as $search) {
                    if (count($suggestions) < MAXIMUM_AUTOCOMPLETE_SUGGESTIONS) {
                        $suggestions[$search['search']] = true;
                    }
                }
            }

            // Based on content titles
            if ((has_privilege(get_member(), 'autocomplete_title_' . $content_type)) && (count($suggestions) < MAXIMUM_AUTOCOMPLETE_SUGGESTIONS)) {
                $cma_ob = get_content_object($content_type);
                $cma_info = $cma_ob->info();

                if (strpos($cma_info['title_field'], ':') === false) {
                    if (($cma_info['title_field_dereference']) && (multi_lang_content())) {
                        $q = 'SELECT text_original AS search FROM ' . get_table_prefix() . $cma_info['table'] . 'r';
                        $q = ' JOIN ' . get_table_prefix() . 'translate t ON t.id=r.' . $cma_info['title_field'];
                        if (db_has_full_text($GLOBALS['SITE_DB']->connection_read)) {
                            $q .= ' WHERE ' . preg_replace('#\?#', 'text_original', db_full_text_assemble(str_replace('?', '', $request), false));
                        } else {
                            $q .= ' WHERE text_original LIKE \'' . db_encode_like($request . '%') . '\'';
                        }
                        $q .= ' GROUP BY text_original';
                    } else {
                        $q = 'SELECT ' . $cma_info['title_field'] . ' AS search FROM ' . get_table_prefix() . $cma_info['table'];
                        if (db_has_full_text($GLOBALS['SITE_DB']->connection_read)) {
                            $q .= ' WHERE ' . preg_replace('#\?#', $cma_info['title_field'], db_full_text_assemble(str_replace('?', '', $request), false));
                        } else {
                            $q .= ' WHERE ' . $cma_info['title_field'] . ' LIKE \'' . db_encode_like($request . '%') . '\'';
                        }
                        $q .= ' GROUP BY ' . $cma_info['title_field'];
                    }
                    $q .= ' ORDER BY COUNT(*) DESC';
                    $rows = $GLOBALS['SITE_DB']->query($q, MAXIMUM_AUTOCOMPLETE_SUGGESTIONS);
                    foreach ($rows as $search) {
                        if (count($suggestions) < MAXIMUM_AUTOCOMPLETE_SUGGESTIONS) {
                            $suggestions[$search['search']] = true;
                        }
                    }
                } else {
                    // Cannot do for catalogues. Would need to analyse the catalogue and focus only on a single one.
                    // Recommendation is to write custom content types if you need advanced features like autocomplete.
                }
            }
        }
    }

    return array_keys($suggestions);
}

/**
 * Generate a search block.
 *
 * @param  array $map Search block parameters
 * @return array Search block template parameters
 */
function do_search_block($map)
{
    require_lang('search');
    require_css('search');
    require_javascript('ajax_people_lists');

    $zone = array_key_exists('zone', $map) ? $map['zone'] : get_module_zone('search');

    $title = array_key_exists('title', $map) ? $map['title'] : null;
    if ($title === null) {
        $title = do_lang('SEARCH');
    }

    $sort = array_key_exists('sort', $map) ? $map['sort'] : 'relevance';
    $author = array_key_exists('author', $map) ? $map['author'] : '';
    $days = array_key_exists('days', $map) ? intval($map['days']) : -1;
    $direction = array_key_exists('direction', $map) ? $map['direction'] : 'DESC';
    $only_titles = (array_key_exists('only_titles', $map) ? $map['only_titles'] : '') == '1';
    $only_search_meta = (array_key_exists('only_search_meta', $map) ? $map['only_search_meta'] : '0') == '1';
    $boolean_search = (array_key_exists('boolean_search', $map) ? $map['boolean_search'] : '0') == '1';
    $conjunctive_operator = array_key_exists('conjunctive_operator', $map) ? $map['conjunctive_operator'] : 'AND';
    $_extra = array_key_exists('extra', $map) ? $map['extra'] : '';

    $map2 = array('page' => 'search', 'type' => 'results');
    if (array_key_exists('search_under', $map)) {
        $map2['search_under'] = $map['search_under'];
    }
    $url = build_url($map2, $zone, null, false, true);

    $extra = array();
    foreach (explode(',', $_extra) as $_bits) {
        $bits = explode('=', $_bits, 2);
        if (count($bits) == 2) {
            $extra[$bits[0]] = $bits[1];
        }
    }

    $input_fields = array('content' => do_lang('SEARCH_TITLE'));
    if (array_key_exists('input_fields', $map)) {
        $input_fields = array();
        foreach (explode(',', $map['input_fields']) as $_bits) {
            $bits = explode('=', $_bits, 2);
            if (count($bits) == 2) {
                $input_fields[$bits[0]] = $bits[1];
            }
        }
    }

    $search_types = array();

    $limit_to = array('all_defaults');
    $extrax = array();
    if ((array_key_exists('limit_to', $map)) && ($map['limit_to'] != 'all_defaults')) {
        $limit_to = array();
        $map['limit_to'] = str_replace('|', ',', $map['limit_to']); // "|" looks cleaner in templates
        foreach (explode(',', $map['limit_to']) as $key) {
            $limit_to[] = 'search_' . $key;
            if (strpos($map['limit_to'], ',') !== false) {
                $extrax['search_' . $key] = '1';
                $search_types[] = $key;
            }
        }
        $hooks = find_all_hooks('modules', 'search');
        foreach (array_keys($hooks) as $key) {
            if (!array_key_exists('search_' . $key, $extrax)) {
                $extrax['search_' . $key] = '0';
            }
        }
        if (strpos($map['limit_to'], ',') === false) {
            $extra['id'] = $map['limit_to'];
        }
    }

    $url_map = $map;
    unset($url_map['input_fields']);
    unset($url_map['extra']);
    unset($url_map['zone']);
    unset($url_map['title']);
    unset($url_map['limit_to']);
    unset($url_map['block']);
    $full_link = build_url(array('page' => 'search', 'type' => 'browse') + $url_map + $extra + $extrax, $zone);

    if ((!array_key_exists('content', $input_fields)) && (count($input_fields) != 1)) {
        $extra['content'] = '';
    }

    $options = array();
    if ((count($limit_to) == 1) && ($limit_to[0] != 'all_defaults')) { // If we are doing a specific hook
        $id = preg_replace('#^search\_#', '', $limit_to[0]);

        require_code('hooks/modules/search/' . filter_naughty_harsh($id, true));
        $object = object_factory('Hook_search_' . filter_naughty_harsh($id, true));
        $info = $object->info();
        if (!is_null($info)) {
            if (array_key_exists('special_on', $info)) {
                foreach ($info['special_on'] as $name => $display) {
                    $_name = 'option_' . $id . '_' . $name;
                    $options[$_name] = array('SEARCH_FOR_SEARCH_DOMAIN_OPTION', array('CHECKED' => (get_param_string('content', null) === null) || (get_param_integer($_name, 0) == 1), 'DISPLAY' => $display));
                }
            }
            if (array_key_exists('special_off', $info)) {
                foreach ($info['special_off'] as $name => $display) {
                    $_name = 'option_' . $id . '_' . $name;
                    $options[$_name] = array('SEARCH_FOR_SEARCH_DOMAIN_OPTION', array('CHECKED' => (get_param_integer($_name, 0) == 1), 'DISPLAY' => $display));
                }
            }
            if (method_exists($object, 'get_fields')) {
                $fields = $object->get_fields();
                foreach ($fields as $field) {
                    $_name = 'option_' . $field['NAME'];
                    $options[$_name] = array('SEARCH_FOR_SEARCH_DOMAIN_OPTION' . $field['TYPE'], array('DISPLAY' => $field['DISPLAY'], 'SPECIAL' => $field['SPECIAL'], 'CHECKED' => array_key_exists('checked', $field) ? $field['CHECKED'] : false));
                }
            }
        }
    }

    $_input_fields = array();
    foreach ($input_fields as $key => $val) {
        $input = new Tempcode();
        if (isset($options['option_' . $key])) { // If there is an input option for this particular $key
            $tpl_params = $options['option_' . $key][1];
            $tpl_params['NAME'] = 'option_' . $key;
            if ($val != '') {
                $tpl_params['DISPLAY'] = $val;
            }
            $input = do_template($options['option_' . $key][0], $tpl_params);
        }
        $_input_fields[$key] = array(
            'LABEL' => $val,
            'INPUT' => $input,
        );
    }

    return array(
        'TITLE' => $title,
        'INPUT_FIELDS' => $_input_fields,
        'EXTRA' => $extra,
        'SORT' => $sort,
        'AUTHOR' => $author,
        'DAYS' => strval($days),
        'DIRECTION' => $direction,
        'ONLY_TITLES' => $only_titles ? '1' : '0',
        'ONLY_SEARCH_META' => $only_search_meta ? '1' : '0',
        'BOOLEAN_SEARCH' => $boolean_search ? '1' : '0',
        'CONJUNCTIVE_OPERATOR' => $conjunctive_operator,
        'LIMIT_TO' => $limit_to,
        'URL' => $url,
        'FULL_SEARCH_URL' => $full_link,
        'SEARCH_TYPE' => (count($search_types) != 1) ? null : $search_types[0],
    );
}
