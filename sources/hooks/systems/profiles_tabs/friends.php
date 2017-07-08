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
 * @package    chat
 */

/**
 * Hook class.
 */
class Hook_profiles_tabs_friends
{
    /**
     * Find whether this hook is active.
     *
     * @param  MEMBER $member_id_of The ID of the member who is being viewed
     * @param  MEMBER $member_id_viewing The ID of the member who is doing the viewing
     * @return boolean Whether this hook is active
     */
    public function is_active($member_id_of, $member_id_viewing)
    {
        return addon_installed('chat');
    }

    /**
     * Render function for profile tab hooks.
     *
     * @param  MEMBER $member_id_of The ID of the member who is being viewed
     * @param  MEMBER $member_id_viewing The ID of the member who is doing the viewing
     * @param  boolean $leave_to_ajax_if_possible Whether to leave the tab contents null, if tis hook supports it, so that AJAX can load it later
     * @return array A tuple: The tab title, the tab contents, the suggested tab order, the icon
     */
    public function render_tab($member_id_of, $member_id_viewing, $leave_to_ajax_if_possible = false)
    {
        require_lang('chat');
        require_lang('cns');
        require_javascript('checking');

        $title = do_lang_tempcode('FRIENDS');

        $order = 70;

        if ($leave_to_ajax_if_possible) {
            return array($title, null, $order, 'tabs/member_account/friends');
        }

        $add_friend_url = new Tempcode();
        $remove_friend_url = new Tempcode();
        require_code('chat');
        if (($member_id_of != $member_id_viewing) && (!is_guest())) {
            if (!member_befriended($member_id_of)) {
                $add_friend_url = build_url(array('page' => 'chat', 'type' => 'friend_add', 'member_id' => $member_id_of, 'redirect' => protect_url_parameter($GLOBALS['FORUM_DRIVER']->member_profile_url($member_id_of, true))), get_module_zone('chat'));
            } else {
                $remove_friend_url = build_url(array('page' => 'chat', 'type' => 'friend_remove', 'member_id' => $member_id_of, 'redirect' => protect_url_parameter($GLOBALS['FORUM_DRIVER']->member_profile_url($member_id_of, true))), get_module_zone('chat'));
            }
        }

        $content = do_template('CNS_MEMBER_PROFILE_FRIENDS', array('_GUID' => 'b24a8607c6e2d3d6ddc29c8e22b972e8', 'MEMBER_ID' => strval($member_id_of), 'ADD_FRIEND_URL' => $add_friend_url, 'REMOVE_FRIEND_URL' => $remove_friend_url));

        return array($title, $content, $order, 'tabs/member_account/friends');
    }
}
