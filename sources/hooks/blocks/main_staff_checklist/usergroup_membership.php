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
 * Hook class.
 */
class Hook_checklist_usergroup_membership
{
    /**
     * Find items to include on the staff checklist.
     *
     * @return array An array of tuples: The task row to show, the number of seconds until it is due (or null if not on a timer), the number of things to sort out (or null if not on a queue), The name of the config option that controls the schedule (or null if no option).
     */
    public function run()
    {
        if (get_forum_type() != 'cns') {
            return array();
        }

        $cnt = $GLOBALS['FORUM_DB']->query_select_value('f_group_members', 'COUNT(*)', array('gm_validated' => 0));

        if ($cnt > 0) {
            $status = do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_0', array('_GUID' => 'o578142633c6f3d37776e82a869deb91'));
        } else {
            $status = do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_1', array('_GUID' => 'p578142633c6f3d37776e82a869deb91'));
        }

        $url = build_url(array('page' => 'groups', 'type' => 'browse'), get_module_zone('groups'));

        require_lang('cns');

        $tpl = do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM', array('_GUID' => 'cccf866e2ea104ac41685a8756e182f8', 'URL' => $url, 'STATUS' => $status, 'TASK' => do_lang_tempcode('USERGROUP_APPLICATIONS'), 'INFO' => do_lang_tempcode('NUM_QUEUE', escape_html(integer_format($cnt)))));
        return array(array($tpl, null, $cnt, null));
    }
}
