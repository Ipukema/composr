<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

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
class form_to_email_test_set extends cms_test_case
{
    public function testFormToEmail()
    {
        $GLOBALS['SITE_DB']->query_delete('logged_mail_messages');

        $bak = get_option('mail_queue_debug');
        set_option('mail_queue_debug', '1');
        $url = find_script('form_to_email');
        http_download_file($url, null, true, false, 'Composr', array('foo' => 'bar')); // TODO: Change in v11
        set_option('mail_queue_debug', $bak);

        $rows = $GLOBALS['SITE_DB']->query_select('logged_mail_messages', array('*'));
        foreach ($rows as $row) {
            $this->assertTrue(strpos($row['m_message'], 'bar') !== false);
        }

        $this->assertTrue(count($rows) == 1);
    }
}
