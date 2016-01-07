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
 * Hook class.
 */
class Hook_admin_import_types_core
{
    /**
     * Get a map of valid import types.
     *
     * @return array A map from codename to the language string that names them to the user.
     */
    public function run()
    {
        $ret = array(
            'attachments' => 'ATTACHMENTS',
            'attachment_references' => 'ATTACHMENT_REFERENCES',
            'feedback' => '_FEEDBACK',
            'permissions' => 'PERMISSIONS',
            'themes' => 'THEMES',
            'useronline_tracking' => 'USER_ONLINE_TRACKING',
            'zones' => 'ZONES',
            'cns_emoticons' => 'EMOTICONS',
            'cns_members' => 'MEMBERS',
            'cns_member_files' => 'MEMBER_FILES',
            'cns_groups' => 'USERGROUPS',
            'cns_privileges' => 'PRIVILEGES',
            'config' => 'CONFIGURATION',
            'logs' => 'LOGS',
            'pages' => 'COMCODE_PAGES',
            'rss' => 'RSS',
            'blocks' => '_BLOCKS',
            'menu_items' => 'MENU_ITEMS',
            'staff_links' => 'STAFF_LINKS',
            'staff_checklist_cus_tasks' => 'CHECK_LIST',
            'staff_website_monitoring' => 'SITE_WATCHLIST',
            'searches_saved' => 'SAVED_SEARCHES',
            'notifications' => 'NOTIFICATIONS',
        );
        if (addon_installed('cns_cpfs')) {
            $ret['cns_custom_profile_fields'] = 'CUSTOM_PROFILE_FIELDS';
        }
        if (addon_installed('cns_warnings')) {
            $ret['cns_warnings'] = 'WARNINGS';
        }
        if (addon_installed('custom_comcode')) {
            $ret['custom_comcode'] = 'CUSTOM_COMCODE';
        }
        if (addon_installed('authors')) {
            $ret['authors'] = 'AUTHORS';
        }
        if (addon_installed('welcome_emails')) {
            $ret['cns_welcome_emails'] = 'WELCOME_EMAILS';
        }
        if (addon_installed('securitylogging')) {
            $ret['ip_bans'] = 'BANNED_ADDRESSES';
        }
        if (addon_installed('redirects_editor')) {
            $ret['redirects'] = 'REDIRECTS';
        }
        if (addon_installed('match_key_permissions')) {
            $ret['match_key_messages'] = 'PAGE_MATCH_KEY_ACCESS';
        }
        return $ret;
    }
}
