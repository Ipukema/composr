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
 * @package    core_cns
 */

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__cns_members()
{
    global $CUSTOM_FIELD_CACHE;
    $CUSTOM_FIELD_CACHE = array();
    if (function_exists('persistent_cache_get')) {
        $test = persistent_cache_get('CUSTOM_FIELD_CACHE');
        if (is_array($test)) {
            $CUSTOM_FIELD_CACHE = $test;
        }
    }

    global $MEMBER_CACHE_FIELD_MAPPINGS;
    $MEMBER_CACHE_FIELD_MAPPINGS = array();

    global $PRIMARY_GROUP_MEMBERS_CACHE;
    $PRIMARY_GROUP_MEMBERS_CACHE = array();

    global $MAY_WHISPER_CACHE;
    $MAY_WHISPER_CACHE = array();
}

/**
 * Find all the Private Topic filter categories employed by the current member.
 *
 * @param  boolean $only_exists_now Whether to only show ones that already have things in (i.e. not default ones)
 * @return array List of filter categories
 */
function cns_get_filter_cats($only_exists_now = false)
{
    $filter_rows_a = $GLOBALS['FORUM_DB']->query_select('f_topics', array('DISTINCT t_pt_from_category'), array('t_pt_from' => get_member()));
    $filter_rows_b = $GLOBALS['FORUM_DB']->query_select('f_topics', array('DISTINCT t_pt_to_category'), array('t_pt_to' => get_member()));
    $filter_cats = array('' => 1);
    if (!$only_exists_now) {
        $filter_cats[do_lang('TRASH')] = 1;
    }
    if ($GLOBALS['FORUM_DB']->query_select_value('f_special_pt_access', 'COUNT(*)', array('s_member_id' => get_member())) > 0) {
        $filter_cats[do_lang('INVITED_TO_PTS')] = 1;
    }
    foreach ($filter_rows_a as $filter_row) {
        $filter_cats[$filter_row['t_pt_from_category']] = 1;
    }
    foreach ($filter_rows_b as $filter_row) {
        $filter_cats[$filter_row['t_pt_to_category']] = 1;
    }

    return array_keys($filter_cats);
}

/**
 * Find whether a member of a certain username is bound to HTTP authentication (an exceptional situation, only for sites that use it).
 *
 * @param  string $authusername The username.
 * @return ?integer The member ID, if it is (null: not bound).
 */
function cns_authusername_is_bound_via_httpauth($authusername)
{
    $ret = $GLOBALS['FORUM_DB']->query_select_value_if_there('f_members', 'id', array('m_password_compat_scheme' => 'httpauth', 'm_pass_hash_salted' => $authusername));
    if (is_null($ret)) {
        $ret = $GLOBALS['FORUM_DB']->query_value_if_there('SELECT id FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_members WHERE ' . db_string_not_equal_to('m_password_compat_scheme', '') . ' AND ' . db_string_equal_to('m_username', $authusername));
    }
    return $ret;
}

/**
 * Find whether a member is bound to HTTP LDAP (an exceptional situation, only for sites that use it).
 *
 * @param  MEMBER $member_id The member.
 * @return boolean The answer.
 */
function cns_is_ldap_member($member_id)
{
    global $LDAP_CONNECTION;
    if (is_null($LDAP_CONNECTION)) {
        return false;
    }

    $scheme = $GLOBALS['CNS_DRIVER']->get_member_row_field($member_id, 'm_password_compat_scheme');
    return $scheme == 'ldap';
}

/**
 * Find whether a member is bound to HTTP authentication (an exceptional situation, only for sites that use it).
 *
 * @param  MEMBER $member_id The member.
 * @return boolean The answer.
 */
function cns_is_httpauth_member($member_id)
{
    $scheme = $GLOBALS['CNS_DRIVER']->get_member_row_field($member_id, 'm_password_compat_scheme');
    return $scheme == 'httpauth';
}

/**
 * Gets all the system custom fields that match certain parameters.
 *
 * @param  ?array $groups That are applicable only to one of the usergroups in this list (empty: CPFs with no restriction) (null: disregard restriction).
 * @param  ?BINARY $public_view That are publicly viewable (null: don't care).
 * @param  ?BINARY $owner_view That are owner viewable (null: don't care).
 * @param  ?BINARY $owner_set That are owner settable (null: don't care).
 * @param  ?BINARY $required That are required (null: don't care).
 * @param  ?BINARY $show_in_posts That are to be shown in posts (null: don't care).
 * @param  ?BINARY $show_in_post_previews That are to be shown in post previews (null: don't care).
 * @param  BINARY $special_start That start 'cms_'
 * @param  ?boolean $show_on_join_form That are to go on the join form (null: don't care).
 * @return array A list of rows of such fields.
 */
function cns_get_all_custom_fields_match($groups = null, $public_view = null, $owner_view = null, $owner_set = null, $required = null, $show_in_posts = null, $show_in_post_previews = null, $special_start = 0, $show_on_join_form = null)
{
    global $CUSTOM_FIELD_CACHE;
    $x = serialize(array($public_view, $owner_view, $owner_set, $required, $show_in_posts, $show_in_post_previews, $special_start, $show_on_join_form));
    if (isset($CUSTOM_FIELD_CACHE[$x])) { // Composr offers a wide array of features. It's multi dimensional. Composr.. entering the 6th dimension. hyper-hyper-time.
        $result = $CUSTOM_FIELD_CACHE[$x];
    } else {
        // Load up filters
        $hooks = find_all_hooks('systems', 'cns_cpf_filter');
        $to_keep = array();
        foreach (array_keys($hooks) as $hook) {
            require_code('hooks/systems/cns_cpf_filter/' . $hook);
            $_hook = object_factory('Hook_cns_cpf_filter_' . $hook, true);
            if ($_hook === null) {
                continue;
            }
            $to_keep += $_hook->to_enable();
        }

        $where = 'WHERE 1=1 ';
        if ($public_view !== null) {
            $where .= ' AND cf_public_view=' . strval($public_view);
        }
        if ($owner_view !== null) {
            $where .= ' AND cf_owner_view=' . strval($owner_view);
        }
        if ($owner_set !== null) {
            $where .= ' AND cf_owner_set=' . strval($owner_set);
        }
        if ($required !== null) {
            $where .= ' AND cf_required=' . strval($required);
        }
        if ($show_in_posts !== null) {
            $where .= ' AND cf_show_in_posts=' . strval($show_in_posts);
        }
        if ($show_in_post_previews !== null) {
            $where .= ' AND cf_show_in_post_previews=' . strval($show_in_post_previews);
        }
        if ($special_start == 1) {
            $where .= ' AND ' . $GLOBALS['FORUM_DB']->translate_field_ref('cf_name') . ' LIKE \'' . db_encode_like('cms\_%') . '\'';
        }
        if ($show_on_join_form !== null) {
            $where .= ' AND cf_show_on_join_form=' . strval($show_on_join_form);
        }

        global $TABLE_LANG_FIELDS_CACHE;
        $_result = $GLOBALS['FORUM_DB']->query('SELECT f.* FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_custom_fields f ' . $where . ' ORDER BY cf_order,' . $GLOBALS['FORUM_DB']->translate_field_ref('cf_name'), null, null, false, true, isset($TABLE_LANG_FIELDS_CACHE['f_custom_fields']) ? $TABLE_LANG_FIELDS_CACHE['f_custom_fields'] : array());
        $result = array();
        foreach ($_result as $row) {
            $row['trans_name'] = get_translated_text($row['cf_name'], $GLOBALS['FORUM_DB']);

            if ((substr($row['trans_name'], 0, 4) == 'cms_') && ($special_start == 0)) {
                // See if it gets filtered
                if (!isset($to_keep[substr($row['trans_name'], 4)])) {
                    continue;
                }

                require_lang('cns');
                require_lang('cns_special_cpf');
                $test = do_lang('SPECIAL_CPF__' . $row['trans_name'], null, null, null, null, false);
                if ($test !== null) {
                    $row['trans_name'] = $test;
                }
            }
            $result[] = $row;
        }

        $CUSTOM_FIELD_CACHE[$x] = $result;

        if (function_exists('persistent_cache_set')) {
            persistent_cache_set('CUSTOM_FIELD_CACHE', $CUSTOM_FIELD_CACHE);
        }
    }

    $result2 = array();
    foreach ($result as $row) {
        if (($row['cf_only_group'] == '') || ($groups === null) || (count(array_intersect(explode(',', $row['cf_only_group']), $groups)) != 0)) {
            $result2[] = $row;
        }
    }

    return $result2;
}

/**
 * Gets all a member's custom fields that match certain parameters.
 *
 * @param  MEMBER $member_id The member.
 * @param  ?BINARY $public_view That are publicly viewable (null: don't care).
 * @param  ?BINARY $owner_view That are owner viewable (null: don't care).
 * @param  ?BINARY $owner_set That are owner settable (null: don't care).
 * @param  ?BINARY $encrypted That are encrypted (null: don't care).
 * @param  ?BINARY $required That are required (null: don't care).
 * @param  ?BINARY $show_in_posts That are to be shown in posts (null: don't care).
 * @param  ?BINARY $show_in_post_previews That are to be shown in post previews (null: don't care).
 * @param  BINARY $special_start That start 'cms_'
 * @param  ?boolean $show_on_join_form That are to go on the join form (null: don't care).
 * @return array A mapping of field title to a map of details: 'RAW' as the raw field value, 'RENDERED' as the rendered field value, 'FIELD_ID' to the field ID, 'EDITABILITY' defining if fractional editing can work on this
 */
function cns_get_all_custom_fields_match_member($member_id, $public_view = null, $owner_view = null, $owner_set = null, $encrypted = null, $required = null, $show_in_posts = null, $show_in_post_previews = null, $special_start = 0, $show_on_join_form = null)
{
    $fields_to_show = cns_get_all_custom_fields_match($GLOBALS['FORUM_DRIVER']->get_members_groups($member_id), $public_view, $owner_view, $owner_set, $required, $show_in_posts, $show_in_post_previews, $special_start, $show_on_join_form);
    $custom_fields = array();
    $member_mappings = cns_get_custom_field_mappings($member_id);
    $member_value = mixed(); // Initialise type to mixed
    $all_cpf_permissions = ((get_member() == $member_id) || $GLOBALS['FORUM_DRIVER']->is_super_admin(get_member())) ?/*no restricts if you are the member or a super-admin*/array() : list_to_map('field_id', $GLOBALS['FORUM_DB']->query_select('f_member_cpf_perms', array('*'), array('member_id' => $member_id)));

    require_code('fields');

    $editable_with_comcode = array('long_text' => 1, 'long_trans' => 1, 'short_trans' => 1);
    $editable_without_comcode = array('list' => 1, 'short_text' => 1, 'codename' => 1, 'url' => 1, 'integer' => 1, 'float' => 1, 'email' => 1);

    foreach ($fields_to_show as $i => $field_to_show) {
        $key = 'field_' . strval($field_to_show['id']);
        if (!array_key_exists($key, $member_mappings))
        {
            continue;
        }
        $member_value = $member_mappings[$key];
        if (!is_string($member_value)) {
            if (is_float($member_value)) {
                $member_value = float_to_raw_string($member_value, 30);
            } elseif (!is_null($member_value)) {
                $member_value = strval($member_value);
            }
        }

        // Decrypt the value if appropriate
        if ((isset($field_to_show['cf_encrypted'])) && ($field_to_show['cf_encrypted'] == 1) && ($member_value != '') && ($member_value != $field_to_show['cf_default']) && (!is_null($member_value))) {
            require_code('encryption');
            if ((is_encryption_enabled()) && (post_param_string('decrypt', null) !== null)) {
                $member_value = decrypt_data($member_value, post_param_string('decrypt'));
            }
        }

        $ob = get_fields_hook($field_to_show['cf_type']);
        list(, , $storage_type) = $ob->get_field_value_row_bits($field_to_show);

        if ($storage_type == 'short_trans' || $storage_type == 'long_trans') {
            if (($member_value === null) || ((multi_lang_content()) && ($member_value == '0'))) {
                $member_value_raw = '';
                $member_value = ''; // This is meant to be '' for blank, not new Tempcode()
            } else {
                $member_value_raw = get_translated_text($member_mappings['field_' . strval($field_to_show['id'])], $GLOBALS['FORUM_DB']);
                $member_mappings_copy = db_map_restrict($member_mappings, array('mf_member_id', 'field_' . strval($field_to_show['id'])));
                $member_value = get_translated_tempcode('f_member_custom_fields', $member_mappings_copy, 'field_' . strval($field_to_show['id']), $GLOBALS['FORUM_DB']);
                if ((is_object($member_value)) && ($member_value->is_empty())) {
                    $member_value = '';
                }
            }
        } else {
            if ($member_value === null) {
                $member_value = '';
            }
            $member_value_raw = $member_value;
        }

        // Get custom permissions for the current CPF
        $cpf_permissions = isset($all_cpf_permissions[$field_to_show['id']]) ? $all_cpf_permissions[$field_to_show['id']] : array();

        $display_cpf = true;

        // If there are custom permissions set and we are not showing to all
        if ((isset($cpf_permissions[0])) && ($public_view !== null)) {
            $display_cpf = false;

            // Negative ones
            if ($cpf_permissions[0]['guest_view'] == 1) {
                $display_cpf = true;
            }
            if (!is_guest()) {
                if ($cpf_permissions[0]['member_view'] == 1) {
                    $display_cpf = true;
                }
            }

            if (!$display_cpf) { // Guard this, as the code will take some time to run
                if ($cpf_permissions[0]['friend_view'] == 1) {
                    if (addon_installed('chat')) {
                        if ($GLOBALS['SITE_DB']->query_select_value_if_there('chat_friends', 'member_liked', array('member_likes' => $member_id, 'member_liked' => get_member())) === null) {
                            $display_cpf = true;
                        }
                    }
                }

                if (!is_guest()) {
                    if ($cpf_permissions[0]['group_view'] == 'all') {
                        $display_cpf = true;
                    } else {
                        if (strlen($cpf_permissions[0]['group_view']) > 0) {
                            require_code('selectcode');

                            $groups = $GLOBALS['FORUM_DRIVER']->get_usergroup_list(false, false, false, null, $member_id);

                            $groups_to_search = array();
                            foreach (array_keys($groups) as $group_id) {
                                $groups_to_search[$group_id] = null;
                            }
                            $matched_groups = selectcode_to_idlist_using_memory($cpf_permissions[0]['group_view'], $groups_to_search);

                            if (count($matched_groups) > 0) {
                                $display_cpf = true;
                            }
                        }
                    }
                }
            }
        }

        if ($display_cpf) {
            $rendered_value = $ob->render_field_value($field_to_show, $member_value, $i, null, 'f_members', $member_id, 'mf_member_id', null, 'field_' . strval($field_to_show['id']), $member_id);

            $editability = mixed(); // If stays as null, not editable
            if (isset($editable_with_comcode[$field_to_show['cf_type']])) {
                $editability = true; // Editable: Supports Comcode
            } elseif (isset($editable_without_comcode[$field_to_show['cf_type']])) {
                $editability = false; // Editable: Does not support Comcode
            }

            $edit_type = 'line';
            if ($field_to_show['cf_type']  == 'list') {
                $edit_type = $field_to_show['cf_default'];
            } elseif (($field_to_show['cf_type'] == 'long_text') || ($field_to_show['cf_type'] == 'long_trans')) {
                $edit_type = 'textarea';
            }

            $custom_fields[$field_to_show['trans_name']] = array(
                'RAW' => $member_value_raw,
                'RENDERED' => $rendered_value,
                'FIELD_ID' => strval($field_to_show['id']),
                'EDITABILITY' => $editability,
                'TYPE' => $field_to_show['cf_type'],
                'EDIT_TYPE' => $edit_type,
            );
        }
    }

    return $custom_fields;
}

/**
 * Get the ID for a CPF if we only know the title. Warning: Only use this with custom code, never core code! It assumes a single language and that fields aren't renamed.
 *
 * @param  SHORT_TEXT $title The title.
 * @return ?AUTO_LINK The ID (null: could not find).
 */
function find_cpf_field_id($title)
{
    static $cache = array();
    if (array_key_exists($title, $cache)) {
        return $cache[$title];
    }
    $fields_to_show = cns_get_all_custom_fields_match(null);
    foreach ($fields_to_show as $field_to_show) {
        if ($field_to_show['trans_name'] == $title) {
            $cache[$title] = $field_to_show['id'];
            return $field_to_show['id'];
        }
    }
    $cache[$title] = null;
    return null;
}

/**
 * Get the ID for a CPF if we only know the title. Warning: Only use this with custom code, never core code! It assumes a single language and that fields aren't renamed.
 *
 * @param  SHORT_TEXT $title The title.
 * @return ?AUTO_LINK The ID (null: could not find).
 */
function find_cms_cpf_field_id($title)
{
    static $cache = array();
    if (array_key_exists($title, $cache)) {
        return $cache[$title];
    }
    $fields_to_show = cns_get_all_custom_fields_match(null, null, null, null, null, null, null, 1);
    foreach ($fields_to_show as $field_to_show) {
        if ($field_to_show['trans_name'] == $title) {
            $cache[$title] = $field_to_show['id'];
            return $field_to_show['id'];
        }
    }
    $cache[$title] = null;
    return null;
}

/**
 * Returns a list of all field values for user. Doesn't take translation into account. Doesn't take anything permissive into account.
 *
 * @param  MEMBER $member_id The member.
 * @return array The mapping, field_<id> to value.
 */
function cns_get_custom_field_mappings($member_id)
{
    require_code('fields');

    global $MEMBER_CACHE_FIELD_MAPPINGS;
    if (!isset($MEMBER_CACHE_FIELD_MAPPINGS[$member_id])) {
        $row = array('mf_member_id' => $member_id);

        $query = $GLOBALS['FORUM_DB']->query_select('f_member_custom_fields', array('*'), $row, '', 1);
        if (!isset($query[0])) { // Repair
            $value = mixed();
            $row = array();

            $all_fields_regardless = $GLOBALS['FORUM_DB']->query_select('f_custom_fields', array('id', 'cf_type', 'cf_required', 'cf_default'));
            foreach ($all_fields_regardless as $field) {
                $ob = get_fields_hook($field['cf_type']);
                list(, $value, $storage_type) = $ob->get_field_value_row_bits($field, $field['cf_required'] == 1, '', $GLOBALS['FORUM_DB']);

                $row['field_' . strval($field['id'])] = $value;
                if (is_string($value)) { // Should not normally be needed, but the grabbing from cf_default further up is not converted yet
                    switch ($storage_type) {
                        case 'short_trans':
                        case 'long_trans':
                            if ($value !== null) {
                                $row = insert_lang_comcode('field_' . strval($field['id']), $value, 3, $GLOBALS['FORUM_DB']) + $row;
                            } else {
                                $row['field_' . strval($field['id'])] = null;
                            }
                            break;
                        case 'integer':
                            $row['field_' . strval($field['id'])] = intval($value);
                            break;
                        case 'float':
                            $row['field_' . strval($field['id'])] = floatval($value);
                            break;
                    }
                }
            }
            $GLOBALS['FORUM_DB']->query_insert('f_member_custom_fields', array('mf_member_id' => $member_id) + $row);
            $query = array($row);
        }
        $MEMBER_CACHE_FIELD_MAPPINGS[$member_id] = $query[0];
    }
    return $MEMBER_CACHE_FIELD_MAPPINGS[$member_id];
}

/**
 * Returns a mapping between field number and field value. Doesn't take translation into account. Doesn't take anything permissive into account.
 *
 * @param  MEMBER $member_id The member.
 * @return array The mapping.
 */
function cns_get_custom_fields_member($member_id)
{
    $row = cns_get_custom_field_mappings($member_id);
    $result = array();
    foreach ($row as $column => $val) {
        if (preg_match('#^field\_\d+$#', $column) != 0) {
            $result[intval(substr($column, 6))] = $val;
        }
    }
    return $result;
}

/**
 * Get the primary of a member (supports consulting of LDAP).
 *
 * @param  MEMBER $member_id The member.
 * @return GROUP The primary.
 */
function cns_get_member_primary_group($member_id)
{
    global $PRIMARY_GROUP_MEMBERS_CACHE;
    if (isset($PRIMARY_GROUP_MEMBERS_CACHE[$member_id])) {
        return $PRIMARY_GROUP_MEMBERS_CACHE[$member_id];
    }

    if (cns_is_ldap_member($member_id)) {
        cns_ldap_get_member_primary_group($member_id);
    } else {
        $PRIMARY_GROUP_MEMBERS_CACHE[$member_id] = $GLOBALS['CNS_DRIVER']->get_member_row_field($member_id, 'm_primary_group');
    }

    return $PRIMARY_GROUP_MEMBERS_CACHE[$member_id];
}
