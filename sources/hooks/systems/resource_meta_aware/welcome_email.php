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
 * @package    welcome_emails
 */

/**
 * Hook class.
 */
class Hook_resource_meta_aware_welcome_email
{
    /**
     * Get content type details. Provides information to allow task reporting, randomisation, and add-screen linking, to function.
     *
     * @param  ?ID_TEXT $zone The zone to link through to (null: autodetect).
     * @return ?array Map of award content-type info (null: disabled).
     */
    public function info($zone = null)
    {
        return array(
            'support_custom_fields' => false,

            'content_type_label' => 'cns_welcome_emails:WELCOME_EMAIL',

            'connection' => $GLOBALS['FORUM_DB'],
            'table' => 'f_welcome_emails',
            'id_field' => 'id',
            'id_field_numeric' => true,
            'parent_category_field' => null,
            'parent_category_meta_aware_type' => null,
            'is_category' => false,
            'is_entry' => true,
            'category_field' => null, // For category permissions
            'category_type' => null, // For category permissions
            'parent_spec__table_name' => null,
            'parent_spec__parent_name' => null,
            'parent_spec__field_name' => null,
            'category_is_string' => false,

            'title_field' => 'w_name',
            'title_field_dereference' => false,
            'description_field' => null,
            'thumb_field' => null,
            'thumb_field_is_theme_image' => false,

            'view_page_link_pattern' => null,
            'edit_page_link_pattern' => '_SEARCH:admin_cns_welcome_emails:_edit:_WILD',
            'view_category_page_link_pattern' => null,
            'add_url' => (function_exists('get_member') && has_actual_page_access(get_member(), 'admin_cns_welcome_emails')) ? (get_module_zone('admin_cns_welcome_emails') . ':admin_cns_welcome_emails:add') : null,
            'archive_url' => null,

            'support_url_monikers' => false,

            'views_field' => null,
            'order_field' => null,
            'submitter_field' => null,
            'author_field' => null,
            'add_time_field' => null,
            'edit_time_field' => null,
            'date_field' => null,
            'validated_field' => null,

            'seo_type_code' => null,

            'feedback_type_code' => null,

            'permissions_type_code' => null, // null if has no permissions

            'search_hook' => null,
            'rss_hook' => null,
            'attachment_hook' => null,
            'unvalidated_hook' => null,
            'notification_hook' => null,
            'sitemap_hook' => null,

            'addon_name' => 'welcome_emails',

            'cms_page' => 'admin_cns_welcome_emails',
            'module' => null,

            'commandr_filesystem_hook' => 'welcome_emails',
            'commandr_filesystem__is_folder' => false,

            'support_revisions' => false,

            'support_privacy' => false,

            'support_content_reviews' => false,

            'actionlog_regexp' => '\w+_WELCOME_EMAIL',
        );
    }
}
