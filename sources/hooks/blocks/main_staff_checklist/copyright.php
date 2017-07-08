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
 * @package    core_adminzone_dashboard
 */

/**
 * Hook class.
 */
class Hook_checklist_copyright
{
    /**
     * Find items to include on the staff checklist.
     *
     * @return array An array of tuples: The task row to show, the number of seconds until it is due (or null if not on a timer), the number of things to sort out (or null if not on a queue), The name of the config option that controls the schedule (or null if no option).
     */
    public function run()
    {
        $copyright = get_option('copyright');

        $matches = array();
        if ((preg_match('#[^\d]\d\d\d\d-(\d\d(\d\d)?)([^\d]|$)#', $copyright, $matches) == 0) && (preg_match('#[^\d](\d\d(\d\d)?)([^\d]|$)#', $copyright, $matches) == 0)) {
            return array();
        }

        if (strpos($copyright, '$CURRENT_YEAR=') !== false) {
            return array();
        }

        if (((strlen($matches[1]) == 4) && (intval($matches[1]) < intval(date('Y')))) || ((strlen($matches[1]) == 2) && (intval($matches[1]) < intval(substr(date('Y'), 2))))) {
            $status = 0;
        } else {
            $status = 1;
            return array(); // We want to forget about this check entry if it's done for the year
        }
        $_status = ($status == 0) ? do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_0') : do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_1');

        $url = build_url(array('page' => 'admin_config', 'type' => 'category', 'id' => 'SITE'), get_module_zone('admin_config'));

        $tpl = do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM', array(
            '_GUID' => 'c65f89a7af3ce753fc7eada742891400',
            'URL' => '',
            'STATUS' => $_status,
            'TASK' => do_lang_tempcode('NAG_COPYRIGHT_DATE', escape_html_tempcode($url)),
        ));

        return array(array($tpl, ($status == 0) ? -1 : 0, null, null));
    }
}
