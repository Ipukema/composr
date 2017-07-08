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
 * @package    content_reviews
 */

/**
 * Module page class.
 */
class Module_admin_content_reviews
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
        $info['version'] = 1;
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
            '!' => array('_CONTENT_NEEDING_REVIEWING', 'menu/adminzone/audit/content_reviews'),
        );
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('content_reviews');

        delete_privilege('set_content_review_settings');
    }

    /**
     * Install the module.
     *
     * @param  ?integer $upgrade_from What version we're upgrading from (null: new install)
     * @param  ?integer $upgrade_from_hack What hack version we're upgrading from (null: new-install/not-upgrading-from-a-hacked-version)
     */
    public function install($upgrade_from = null, $upgrade_from_hack = null)
    {
        add_privilege('SUBMISSION', 'set_content_review_settings', false);

        $GLOBALS['SITE_DB']->create_table('content_reviews', array(
            'content_type' => '*ID_TEXT',
            'content_id' => '*ID_TEXT',
            'review_freq' => '?INTEGER',
            'next_review_time' => 'TIME',
            'auto_action' => 'ID_TEXT', // leave|unvalidate|delete
            'review_notification_happened' => 'BINARY',
            'display_review_status' => 'BINARY',
            'last_reviewed_time' => 'TIME',
        ));
        $GLOBALS['SITE_DB']->create_index('content_reviews', 'next_review_time', array('next_review_time', 'review_notification_happened'));
        $GLOBALS['SITE_DB']->create_index('content_reviews', 'needs_review', array('next_review_time', 'content_type'));
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('content_reviews');

        set_helper_panel_text(comcode_lang_string('DOC_CONTENT_REVIEWS'));

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        $_title = get_screen_title('_CONTENT_NEEDING_REVIEWING');

        require_code('content');

        $out = new Tempcode();
        require_code('form_templates');

        $_hooks = find_all_hooks('systems', 'content_meta_aware');
        foreach (array_keys($_hooks) as $content_type) {
            require_code('content');
            $object = get_content_object($content_type);
            if ($object === null) {
                continue;
            }
            $info = $object->info();
            if ($info === null) {
                continue;
            }

            if ($info['edit_page_link_pattern'] === null) {
                continue;
            }

            $content = new Tempcode();
            $content_ids = collapse_2d_complexity('content_id', 'next_review_time', $GLOBALS['SITE_DB']->query('SELECT content_id,next_review_time FROM ' . get_table_prefix() . 'content_reviews WHERE ' . db_string_equal_to('content_type', $content_type) . ' AND next_review_time<=' . strval(time()) . ' ORDER BY next_review_time', intval(get_option('general_safety_listing_limit'))));
            $_content_ids = array();
            foreach ($content_ids as $content_id => $next_review_time) {
                list($title,) = content_get_details($content_type, $content_id);
                if ($title !== null) {
                    $title = ($content_type == 'comcode_page') ? $content_id : strip_comcode($title);
                    $title .= ' (' . get_timezoned_date($next_review_time) . ')';
                    $_content_ids[$content_id] = $title;
                } else {
                    $GLOBALS['SITE_DB']->query_delete('content_reviews', array('content_type' => $content_type, 'content_id' => $content_id), '', 1); // The actual content was deleted, I guess
                    continue;
                }
            }
            foreach ($_content_ids as $content_id => $title) {
                $content->attach(form_input_list_entry($content_id, false, $title));
            }
            if (count($content_ids) == intval(get_option('general_safety_listing_limit'))) {
                attach_message(do_lang_tempcode('TOO_MANY_TO_CHOOSE_FROM'), 'warn');
            }

            if (!$content->is_empty()) {
                list($zone, $attributes,) = page_link_decode($info['edit_page_link_pattern']);
                $edit_identifier = 'id';
                foreach ($attributes as $key => $val) {
                    if ($val == '_WILD') {
                        $edit_identifier = $key;
                        unset($attributes[$key]);
                        break;
                    }
                }
                $post_url = build_url($attributes + array('redirect' => protect_url_parameter(SELF_REDIRECT)), $zone);
                $fields = form_input_huge_list(do_lang_tempcode('CONTENT'), '', $edit_identifier, $content, null, true);

                // Could debate whether to include "'TARGET' => '_blank',". However it does redirect back, so it's a nice linear process like this. If it was new window it could be more efficient, but also would confuse people with a lot of new windows opening and not closing.
                $content = do_template('FORM', array(
                    '_GUID' => '288c2534a75e5af5bc7155594dfef68f',
                    'SKIP_REQUIRED' => true,
                    'GET' => true,
                    'HIDDEN' => '',
                    'SUBMIT_ICON' => 'buttons__proceed',
                    'SUBMIT_NAME' => do_lang_tempcode('EDIT'),
                    'FIELDS' => $fields,
                    'URL' => $post_url,
                    'TEXT' => '',
                ));

                $out->attach(do_template('UNVALIDATED_SECTION', array('_GUID' => '406d4c0a8abd36b9c88645df84692c7d', 'TITLE' => do_lang_tempcode($info['content_type_label']), 'CONTENT' => $content)));
            }
        }

        return do_template('UNVALIDATED_SCREEN', array('_GUID' => 'c8574404597d25e3c027766c74d1a008', 'TITLE' => $_title, 'SECTIONS' => $out));
    }
}
