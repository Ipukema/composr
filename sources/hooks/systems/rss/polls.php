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
 * @package    polls
 */

/**
 * Hook class.
 */
class Hook_rss_polls
{
    /**
     * Run function for RSS hooks.
     *
     * @param  string $_filters A list of categories we accept from
     * @param  TIME $cutoff Cutoff time, before which we do not show results from
     * @param  string $prefix Prefix that represents the template set we use
     * @set    RSS_ ATOM_
     * @param  string $date_string The standard format of date to use for the syndication type represented in the prefix
     * @param  integer $max The maximum number of entries to return, ordering by date
     * @return ?array A pair: The main syndication section, and a title (null: error)
     */
    public function run($_filters, $cutoff, $prefix, $date_string, $max)
    {
        if (!addon_installed('polls')) {
            return null;
        }

        if (!has_actual_page_access(get_member(), 'polls')) {
            return null;
        }

        $content = new Tempcode();
        $rows = $GLOBALS['SITE_DB']->query('SELECT * FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'poll WHERE add_time>' . strval($cutoff) . ' AND (votes1+votes2+votes3+votes4+votes5+votes6+votes7+votes8+votes9+votes10<>0 OR is_current=1) ORDER BY add_time DESC', $max);
        foreach ($rows as $row) {
            $id = strval($row['id']);
            $author = $GLOBALS['FORUM_DRIVER']->get_username($row['submitter'], false, USERNAME_DEFAULT_BLANK);

            $news_date = date($date_string, $row['add_time']);
            $edit_date = ($row['edit_date'] === null) ? '' : date($date_string, $row['edit_date']);

            $_news_title = get_translated_tempcode('poll', $row, 'question');
            $news_title = xmlentities($_news_title->evaluate());
            $answers = array();
            for ($i = 1; $i <= 5; $i++) {
                $answers[] = get_translated_tempcode('poll', $row, 'option' . strval($i));
            }
            $_summary = do_template('POLL_RSS_SUMMARY', array('_GUID' => 'db39d44c1fa871122e1ae717e4947244', 'ANSWERS' => $answers));
            $summary = xmlentities($_summary->evaluate());
            $news = '';

            $category = '';
            $category_raw = '';

            $view_url = build_url(array('page' => 'polls', 'type' => 'view', 'id' => $row['id']), get_module_zone('polls'), array(), false, false, true);

            if (($prefix == 'RSS_') && (get_option('is_on_comments') == '1') && ($row['allow_comments'] >= 1)) {
                $if_comments = do_template('RSS_ENTRY_COMMENTS', array('_GUID' => '0a3e8d0b18e619d88f12bc7665fbbbca', 'COMMENT_URL' => $view_url, 'ID' => $id), null, false, null, '.xml', 'xml');
            } else {
                $if_comments = new Tempcode();
            }

            $content->attach(do_template($prefix . 'ENTRY', array('VIEW_URL' => $view_url, 'SUMMARY' => $summary, 'EDIT_DATE' => $edit_date, 'IF_COMMENTS' => $if_comments, 'TITLE' => $news_title, 'CATEGORY_RAW' => $category_raw, 'CATEGORY' => $category, 'AUTHOR' => $author, 'ID' => $id, 'NEWS' => $news, 'DATE' => $news_date), null, false, null, '.xml', 'xml'));
        }

        require_lang('polls');
        return array($content, do_lang('POLLS'));
    }
}
