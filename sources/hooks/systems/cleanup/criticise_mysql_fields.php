<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_cleanup_tools
 */

/**
 * Hook class.
 */
class Hook_cleanup_criticise_mysql_fields
{
    /**
     * Find details about this cleanup hook.
     *
     * @return ?array Map of cleanup hook info (null: hook is disabled).
     */
    public function info()
    {
        if (substr(get_db_type(), 0, 5) != 'mysql') {
            return null;
        }

        $info = array();
        $info['title'] = do_lang_tempcode('CORRECT_MYSQL_SCHEMA_ISSUES');
        $info['description'] = do_lang_tempcode('DESCRIPTION_CORRECT_MYSQL_SCHEMA_ISSUES');
        $info['type'] = 'optimise';

        return $info;
    }

    /**
     * Run the cleanup hook action.
     *
     * @return Tempcode Results
     */
    public function run()
    {
        require_code('database_repair');
        $repair_ob = new DatabaseRepair();
        list($phase, $sql) = $repair_ob->search_for_database_issues();

        if ($sql != '') {
            return do_lang_tempcode('MYSQL_QUERY_CHANGES_MAKE_' . strval($phase), escape_html($sql));
        }

        return do_lang_tempcode('NO_MYSQL_QUERY_CHANGES_MAKE');
    }
}
