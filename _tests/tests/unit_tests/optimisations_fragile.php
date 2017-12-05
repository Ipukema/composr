<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2017

 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    testing_platform
 */

/**
 * Composr test case class (unit testing).
 */
class optimisations_fragile_test_set extends cms_test_case
{
    public function testSymbols2Optimisation()
    {
        $GLOBALS['SITE_DB']->query_insert('group_zone_access', array('zone_name' => 'forum', 'group_id' => db_get_first_id()), false, true);
        $GLOBALS['FORUM_DB']->query_insert('group_category_access', array(
            'module_the_name' => 'forums',
            'category_name' => db_get_first_id(),
            'group_id' => db_get_first_id(),
        ), false, true);

        require_code('site');
        $_GET['id'] = strval(db_get_first_id());
        $out = load_module_page('forum/pages/modules/forumview.php', 'forumview');
        require_lang('cns');
        $this->assertTrue(strpos($out->evaluate(), do_lang('ROOT_FORUM')) !== false);
        $this->assertTrue(!function_exists('ecv2_MAKE_URL_ABSOLUTE'));

        require_code('failure');
        set_throw_errors(true);

        $modules = find_all_pages('site', 'modules');
        foreach (array_keys($modules) as $module) {
            try {
                $out = load_module_page('site/pages/modules/' . $module . '.php', $module);
            }
            catch (Exception $e) {
            }
            $this->assertTrue(!function_exists('ecv2_MAKE_URL_ABSOLUTE'));
        }
    }
}
