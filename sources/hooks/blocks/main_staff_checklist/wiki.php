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
 * @package    wiki
 */

/**
 * Hook class.
 */
class Hook_checklist_wiki
{
    /**
     * Find items to include on the staff checklist.
     *
     * @return array An array of tuples: The task row to show, the number of seconds until it is due (or null if not on a timer), the number of things to sort out (or null if not on a queue), The name of the config option that controls the schedule (or null if no option).
     */
    public function run()
    {
        if (!addon_installed('wiki')) {
            return array();
        }

        require_lang('wiki');

        // Wiki+ moderation
        $status = do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_NA');
        $url = build_url(array('page' => 'wiki'), get_module_zone('wiki'));
        $tpl = do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM', array(
            '_GUID' => 'f32f27f6f9aa77bea277e2f5d4deb6e7',
            'URL' => '',
            'STATUS' => $status,
            'TASK' => do_lang_tempcode('NAG_WIKI', escape_html_tempcode($url)),
            'INFO' => '',
        ));
        return array(array($tpl, null, null, null));
    }
}
