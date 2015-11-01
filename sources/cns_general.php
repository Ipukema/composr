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
 * @package    core_cns
 */

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__cns_general()
{
    global $SET_CONTEXT_FORUM;
    $SET_CONTEXT_FORUM = null;
}

/**
 * Get some forum stats.
 *
 * @return array A map of forum stats.
 */
function cns_get_forums_stats()
{
    $out = array();

    if (isset($GLOBALS['CNS_DRIVER'])) {
        $out['num_topics'] = $GLOBALS['CNS_DRIVER']->get_topics();
        $out['num_posts'] = $GLOBALS['CNS_DRIVER']->get_num_forum_posts();
        $out['num_members'] = $GLOBALS['CNS_DRIVER']->get_members();
    } else {
        $out['num_topics'] = 0;
        $out['num_posts'] = 0;
        $out['num_members'] = 0;
    }

    $temp = get_value_newer_than('cns_newest_member_id', time() - 60 * 60 * 1);
    $out['newest_member_id'] = is_null($temp) ? null : intval($temp);
    if (!is_null($out['newest_member_id'])) {
        $out['newest_member_username'] = get_value_newer_than('cns_newest_member_username', time() - 60 * 60 * 1);
    } else {
        $out['newest_member_username'] = null;
    }
    if (is_null($out['newest_member_username'])) {
        $newest_member = $GLOBALS['FORUM_DB']->query('SELECT m_username,id FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_members WHERE m_validated=1 AND id<>' . strval($GLOBALS['FORUM_DRIVER']->get_guest_id()) . ' ORDER BY m_join_time DESC', 1); // Only ordered by m_join_time and not double ordered with ID to make much faster in MySQL
        if (array_key_exists(0, $newest_member)) {
            $out['newest_member_id'] = $newest_member[0]['id'];
            $out['newest_member_username'] = $newest_member[0]['m_username'];
        } else {
            $out['newest_member_id'] = $GLOBALS['FORUM_DRIVER']->get_guest_id();
            $out['newest_member_username'] = do_lang('GUEST');
        }
        if (get_db_type() != 'xml') {
            if (!$GLOBALS['SITE_DB']->table_is_locked('values')) {
                set_value('cns_newest_member_id', strval($out['newest_member_id']));
                set_value('cns_newest_member_username', $out['newest_member_username']);
            }
        }
    }

    return $out;
}

/**
 * Get details on a member profile.
 *
 * @param  MEMBER $member_id The member to get details of.
 * @param  boolean $lite Whether to get a 'lite' version (contains less detail, therefore less costly).
 * @return array A map of details.
 */
function cns_read_in_member_profile($member_id, $lite = true)
{
    $row = $GLOBALS['CNS_DRIVER']->get_member_row($member_id);
    if (is_null($row)) {
        return array();
    }
    $last_visit_time = (($member_id == get_member()) && (array_key_exists('last_visit', $_COOKIE))) ? intval($_COOKIE['last_visit']) : $row['m_last_visit_time'];
    $join_time = $row['m_join_time'];

    $out = array(
        'username' => $row['m_username'],
        'last_visit_time' => $last_visit_time,
        'last_visit_time_string' => get_timezoned_date($last_visit_time),
        'signature' => $row['m_signature'],
        'posts' => $row['m_cache_num_posts'],
        'join_time' => $join_time,
        'join_time_string' => get_timezoned_date($join_time),
    );

    if (addon_installed('points')) {
        require_code('points');
        $num_points = total_points($member_id);
        $out['points'] = $num_points;
    }

    if (!$lite) {
        $out['groups'] = cns_get_members_groups($member_id);

        // Custom fields
        $out['custom_fields'] = cns_get_all_custom_fields_match_member($member_id, ((get_member() != $member_id) && (!has_privilege(get_member(), 'view_any_profile_field'))) ? 1 : null, ((get_member() != $member_id) && (!has_privilege(get_member(), 'view_any_profile_field'))) ? 1 : null);

        // Birthdate
        if ($row['m_reveal_age'] == 1) {
            $out['birthdate'] = $row['m_dob_year'] . '/' . $row['m_dob_month'] . '/' . $row['m_dob_day'];
        }

        // Find title
        $title = get_member_title($member_id);
        if ($title != '') {
            $out['title'] = $title;
        }

        // Find photo
        $photo = $GLOBALS['CNS_DRIVER']->get_member_row_field($member_id, 'm_photo_thumb_url');
        if (($photo != '') && (addon_installed('cns_member_photos'))) {
            if (url_is_local($photo)) {
                $photo = get_complex_base_url($photo) . '/' . $photo;
            }
            $out['photo'] = $photo;
        }

        // Any warnings?
        if ((has_privilege(get_member(), 'see_warnings')) && (addon_installed('cns_warnings'))) {
            $out['warnings'] = cns_get_warnings($member_id);
        }
    }

    // Find avatar
    $avatar = $GLOBALS['CNS_DRIVER']->get_member_avatar_url($member_id);
    if ($avatar != '') {
        $out['avatar'] = $avatar;
    }

    // Primary usergroup
    require_code('cns_members');
    $primary_group = cns_get_member_primary_group($member_id);
    $out['primary_group'] = $primary_group;
    require_code('cns_groups');
    $out['primary_group_name'] = cns_get_group_name($primary_group);

    // Find how many points we need to advance
    if (addon_installed('points')) {
        $promotion_threshold = cns_get_group_property($primary_group, 'promotion_threshold');
        if (!is_null($promotion_threshold)) {
            $num_points_advance = $promotion_threshold - $num_points;
            $out['num_points_advance'] = $num_points_advance;
        }
    }

    return $out;
}

/**
 * Get a member title.
 *
 * @param  MEMBER $member_id Member ID.
 * @return string Member title.
 */
function get_member_title($member_id)
{
    if (!addon_installed('cns_member_titles')) {
        return '';
    }

    $title = addon_installed('cns_member_titles') ? $GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, 'm_title') : '';
    $primary_group = $GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, 'm_primary_group');
    if ($title == '') {
        $title = get_translated_text(cns_get_group_property($primary_group, 'title'), $GLOBALS['FORUM_DB']);
    }
    return $title;
}

/**
 * Get a usergroup colour based on it's ID number.
 *
 * @param  GROUP $gid ID number.
 * @return string Colour.
 */
function get_group_colour($gid)
{
    $all_colours = array('cns_gcol_1', 'cns_gcol_2', 'cns_gcol_3', 'cns_gcol_4', 'cns_gcol_5', 'cns_gcol_6', 'cns_gcol_7', 'cns_gcol_8', 'cns_gcol_9', 'cns_gcol_10', 'cns_gcol_11', 'cns_gcol_12', 'cns_gcol_13', 'cns_gcol_14', 'cns_gcol_15');
    return $all_colours[$gid % count($all_colours)];
}

/**
 * Find all the birthdays in a certain day.
 *
 * @param  ?TIME $time A timestamps that exists in the certain day (null: now).
 * @return array List of maps describing the members whose birthday it is on the certain day.
 */
function cns_find_birthdays($time = null)
{
    if (is_null($time)) {
        $time = time();
    }

    $upper_limit = intval(get_option('enable_birthdays'));

    list($day, $month, $year) = explode(' ', date('j m Y', utctime_to_usertime($time)));
    $rows = $GLOBALS['FORUM_DB']->query_select('f_members', array('id', 'm_username', 'm_reveal_age', 'm_dob_year'), array('m_dob_day' => intval($day), 'm_dob_month' => intval($month)), 'ORDER BY m_last_visit_time DESC', $upper_limit);
    if (count($rows) == $upper_limit) {
        return array();
    }

    $birthdays = array();
    foreach ($rows as $row) {
        $birthday = array('id' => $row['id'], 'username' => $row['m_username']);
        if ($row['m_reveal_age'] == 1) {
            $birthday['age'] = intval($year) - $row['m_dob_year'];
        }

        $birthdays[] = $birthday;
    }

    return $birthdays;
}

/**
 * Turn a list of maps describing buttons, into a Tempcode button panel.
 *
 * @param  array $buttons List of maps (each map contains: url, img, title).
 * @return Tempcode The button panel.
 */
function cns_button_screen_wrap($buttons)
{
    if (count($buttons) == 0) {
        return new Tempcode();
    }

    $b = new Tempcode();
    foreach ($buttons as $button) {
        $b->attach(do_template('BUTTON_SCREEN', array('_GUID' => 'bdd441c40c5b03134ce6541335fece2c', 'REL' => array_key_exists('rel', $button) ? $button['rel'] : null, 'IMMEDIATE' => $button['immediate'], 'URL' => $button['url'], 'IMG' => $button['img'], 'TITLE' => $button['title'])));
    }
    return $b;
}

/**
 * Set the forum context.
 *
 * @param  AUTO_LINK $forum_id Forum ID.
 */
function cns_set_context_forum($forum_id)
{
    global $SET_CONTEXT_FORUM;
    $SET_CONTEXT_FORUM = $forum_id;
}
