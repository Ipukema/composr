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
 * @package    core_feedback_features
 */

/**
 * Set an overridden comment topic forum for a feedback scenario. Moves topics if required.
 *
 * @param  ID_TEXT $feedback_code The feedback code to override the comment topic forum for
 * @param  ?ID_TEXT $category_id The category ID to override the comment topic forum for (null: none)
 * @param  ID_TEXT $forum_id The new comment topic forum
 */
function set_comment_forum_for($feedback_code, $category_id, $forum_id)
{
    require_code('feedback');

    $old_forum_id = find_overridden_comment_forum($feedback_code, $category_id);
    $_old_forum_id = $GLOBALS['FORUM_DRIVER']->forum_id_from_name($old_forum_id);
    $_forum_id = $GLOBALS['FORUM_DRIVER']->forum_id_from_name($forum_id);
    if ($_forum_id === null) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'forum'));
    }

    $default_comment_topic_forum = $GLOBALS['FORUM_DRIVER']->forum_id_from_name(get_option('comments_forum_name'));
    if ($category_id !== null) {
        if ($default_comment_topic_forum == $_forum_id) {
            delete_value('comment_forum__' . $feedback_code . '__' . $category_id);
        } else {
            set_value('comment_forum__' . $feedback_code . '__' . $category_id, strval($_forum_id));
        }
    } else {
        if ($default_comment_topic_forum == $_forum_id) {
            delete_value('comment_forum__' . $feedback_code);
        } else {
            set_value('comment_forum__' . $feedback_code, strval($_forum_id));
        }
    }

    // Move stuff
    if (get_forum_type() == 'cns') {
        require_code('content');
        $cma_hook = convert_composr_type_codes('feedback_type_code', $feedback_code, 'content_type');
        require_code('hooks/systems/content_meta_aware/' . $cma_hook);
        $cma_ob = object_factory('Hook_content_meta_aware_' . $cma_hook);
        $info = $cma_ob->info();
        $category_is_string = (isset($info['category_is_string']) && $info['category_is_string']);
        $topics = array();
        $start = 0;
        do {
            $rows = $GLOBALS['SITE_DB']->query_select($info['table'], array($info['id_field']), array($info['parent_category_field'] => $category_is_string ? $category_id : intval($category_id)), '', 100, $start);
            foreach ($rows as $row) {
                $id = $row[$info['id_field']];
                $feedback_id = $feedback_code . '_' . (is_string($id) ? $id : strval($id));
                $topic_id = $GLOBALS['FORUM_DRIVER']->find_topic_id_for_topic_identifier($old_forum_id, $feedback_id, do_lang('COMMENT'));
                if ($topic_id !== null) {
                    $topics[] = $topic_id;
                }
            }
            $start += 100;
        } while (count($rows) > 0);

        if (count($topics) > 0) {
            require_code('cns_topics_action2');
            cns_move_topics($_old_forum_id, $_forum_id, $topics, false);
        }
    }
}

/**
 * Output the trackback script and handle trackbacks.
 */
function trackback_script()
{
    if (get_option('is_on_trackbacks') == '0') {
        return;
    }

    require_lang('trackbacks');

    header('Content-type: text/xml');

    $page = get_page_name();
    $id = get_param_integer('id');
    $mode = either_param_string('__mode', 'none');

    $allow_trackbacks = true;

    $hooks = find_all_hooks('systems', 'trackback');
    foreach (array_keys($hooks) as $hook) {
        if ($hook == $page) {
            require_code('hooks/systems/trackback/' . filter_naughty_harsh($hook));
            $object = object_factory('Hook_trackback_' . filter_naughty_harsh($hook), true);
            if ($object === null) {
                continue;
            }
            $allow_trackbacks = $object->run($id);
            break;
        }
    }

    if ($mode == 'rss') {
        // List all the trackbacks to the specified page
        $xml = get_trackbacks($page, strval($id), $allow_trackbacks, 'xml');
    } else {
        $time = get_param_integer('time');
        if ($time > time() - 60 * 5) {
            exit(); // Trackback link intentionally goes stale after 5 minutes, so it can't be statically stored and spam hammered
        }

        // Add a trackback for the specified page
        $output = actualise_post_trackback($allow_trackbacks, $page, strval($id));

        if ($output) {
            $xml = do_template('TRACKBACK_XML_NO_ERROR', array(), null, false, null, '.xml', 'xml');
        } else {
            $xml = do_template('TRACKBACK_XML_ERROR', array('_GUID' => 'ac5e34aeabf92712607e62e062407861', 'TRACKBACK_ERROR' => do_lang_tempcode('TRACKBACK_ERROR')), null, false, null, '.xml', 'xml');
        }
    }

    $echo = do_template('TRACKBACK_XML_WRAPPER', array('_GUID' => 'cd8d057328569803a6cca9f8d37a0ac8', 'XML' => $xml), null, false, null, '.xml', 'xml');
    $echo->evaluate_echo();
}

/**
 * Get the Tempcode for the manipulation of the feedback fields for some content, if they are enabled in the Admin Zone.
 *
 * @param  string $content_type The content type
 * @param  boolean $allow_rating Whether rating is currently/by-default allowed for this resource
 * @param  boolean $allow_comments Whether comments are currently/by-default allowed for this resource
 * @param  ?boolean $allow_trackbacks Whether trackbacks are currently/by-default allowed for this resource (null: this resource does not support trackbacks regardless)
 * @param  boolean $send_trackbacks Whether we're allowed to send trackbacks for this resource
 * @param  LONG_TEXT $notes The current/by-default notes for this content
 * @param  ?boolean $allow_reviews Whether reviews are currently/by-default allowed for this resource (null: no reviews allowed here)
 * @param  boolean $default_off Whether the default values for the allow options is actually off (this determines how the tray auto-hides itself)
 * @param  boolean $has_notes If there's to be a notes field
 * @param  boolean $show_header Whether to show a header
 * @param  string $field_name_prefix Field name prefix
 * @return Tempcode The feedback editing fields
 */
function feedback_fields($content_type, $allow_rating, $allow_comments, $allow_trackbacks, $send_trackbacks, $notes, $allow_reviews = null, $default_off = false, $has_notes = true, $show_header = true, $field_name_prefix = '')
{
    if (get_option('enable_feedback') == '0') {
        return new Tempcode();
    }

    require_code('feedback');
    require_code('form_templates');

    $fields = new Tempcode();

    if (($send_trackbacks) && (get_option('is_on_trackbacks') == '1')) {
        require_lang('trackbacks');
        $fields->attach(form_input_line(do_lang_tempcode('SEND_TRACKBACKS'), do_lang_tempcode('DESCRIPTION_SEND_TRACKBACKS'), $field_name_prefix . 'send_trackbacks', get_param_string('trackback', '', INPUT_FILTER_GET_COMPLEX), false));
    }

    if (get_option('is_on_rating') == '1') {
        $fields->attach(form_input_tick(do_lang_tempcode('ALLOW_RATING'), do_lang_tempcode('DESCRIPTION_ALLOW_RATING', $content_type), $field_name_prefix . 'allow_rating', $allow_rating));
    }

    if (get_option('is_on_comments') == '1') {
        if ($allow_reviews !== null) {
            $choices = new Tempcode();
            $choices->attach(form_input_list_entry('0', !$allow_comments && !$allow_reviews, do_lang('NO')));
            $choices->attach(form_input_list_entry('1', $allow_comments && !$allow_reviews, do_lang('ALLOW_COMMENTS_ONLY')));
            $choices->attach(form_input_list_entry('2', $allow_reviews, do_lang('ALLOW_REVIEWS')));
            $fields->attach(form_input_list(do_lang_tempcode('ALLOW_COMMENTS'), do_lang_tempcode('DESCRIPTION_ALLOW_COMMENTS', $content_type), $field_name_prefix . 'allow_comments', $choices, null, false, false));
        } else {
            $fields->attach(form_input_tick(do_lang_tempcode('ALLOW_COMMENTS'), do_lang_tempcode('DESCRIPTION_ALLOW_COMMENTS', $content_type), $field_name_prefix . 'allow_comments', $allow_comments));
        }
    }

    if ((get_option('is_on_trackbacks') == '1') && ($allow_trackbacks !== null)) {
        require_lang('trackbacks');
        $fields->attach(form_input_tick(do_lang_tempcode('ALLOW_TRACKBACKS'), do_lang_tempcode('DESCRIPTION_ALLOW_TRACKBACKS', $content_type), $field_name_prefix . 'allow_trackbacks', $allow_trackbacks));
    }

    if ((get_option('enable_staff_notes') == '1') && ($has_notes)) {
        $fields->attach(form_input_text(do_lang_tempcode('NOTES'), do_lang_tempcode('DESCRIPTION_NOTES'), $field_name_prefix . 'notes', $notes, false));
    }

    if ($show_header) {
        if (!$fields->is_empty()) {
            if ($default_off) {
                $section_hidden = $notes == '' && !$allow_comments && (($allow_trackbacks === null) || !$allow_trackbacks) && !$allow_rating;
            } else {
                $section_hidden = $notes == '' && $allow_comments && (($allow_trackbacks === null) || $allow_trackbacks || (get_option('is_on_trackbacks') == '0')) && $allow_rating;
            }
            $_fields = do_template('FORM_SCREEN_FIELD_SPACER', array(
                '_GUID' => '95864784029fd6d46a8b2ebbca9d81eb',
                'SECTION_HIDDEN' => $section_hidden,
                'TITLE' => do_lang_tempcode((get_option('enable_staff_notes') == '1') ? 'FEEDBACK_AND_NOTES' : '_FEEDBACK'),
            ));
            $_fields->attach($fields);
            $fields = $_fields;
        }
    }

    return $fields;
}

/**
 * Send a trackback to somebody else's website.
 *
 * @param  string $_urls A comma-separated list of URLs to which we should trackback
 * @param  string $title The article title
 * @param  string $excerpt The excerpt to send
 * @return boolean Success?
 */
function send_trackbacks($_urls, $title, $excerpt)
{
    if ($_urls == '') {
        return true;
    }

    $urls = explode(',', $_urls);

    foreach ($urls as $url) {
        $url = trim($url);
        http_get_contents($url, array('trigger_error' => false, 'post_params' => array('url' => get_custom_base_url(), 'title' => $title, 'blog_name' => get_site_name(), 'excerpt' => $excerpt)));
    }

    return true;
}
