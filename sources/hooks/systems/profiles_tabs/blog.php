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
 * @package    news
 */

/**
 * Hook class.
 */
class Hook_profiles_tabs_blog
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
        return has_privilege($member_id_of, 'have_personal_category', 'cms_news');
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
        require_lang('news');

        $title = do_lang_tempcode('BLOG');

        $order = 50;

        if ($leave_to_ajax_if_possible) {
            return array($title, null, $order, 'tabs/member_account/blog');
        }

        // Show recent blog posts
        $recent_blog_posts = new Tempcode();
        $rss_url = new Tempcode();
        $news_cat = $GLOBALS['SITE_DB']->query_select('news_categories', array('*'), array('nc_owner' => $member_id_of), '', 1);
        if ((array_key_exists(0, $news_cat)) && (has_category_access($member_id_viewing, 'news', strval($news_cat[0]['id'])))) {
            $category_id = $news_cat[0]['id'];

            $rss_url = make_string_tempcode('?type=rss2&mode=news&select=' . strval($category_id));

            $recent_blog_posts = do_block('main_news', array('select' => strval($category_id), 'blogs' => '1', 'member_based' => '1', 'zone' => '_SEARCH', 'days' => '0', 'fallback_full' => '10', 'fallback_archive' => '5', 'no_links' => '1', 'pagination' => '1'));
        }

        // Add link
        if ($member_id_of == $member_id_viewing) {
            $add_blog_post_url = build_url(array('page' => 'cms_blogs', 'type' => 'add'), get_module_zone('cms_blogs'));
        } else {
            $add_blog_post_url = new Tempcode();
        }

        // Wrap it all up
        $content = do_template('CNS_MEMBER_PROFILE_BLOG', array('_GUID' => 'f76244bc259c3e7da8c98b28fff85953', 'RSS_URL' => $rss_url, 'ADD_BLOG_POST_URL' => $add_blog_post_url, 'MEMBER_ID' => strval($member_id_of), 'RECENT_BLOG_POSTS' => $recent_blog_posts));

        return array($title, $content, $order, 'tabs/member_account/blog');
    }
}
