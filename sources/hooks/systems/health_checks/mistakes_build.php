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
 * @package    health_check
 */

/**
 * Hook class.
 */
class Hook_health_check_mistakes_build extends Hook_Health_Check
{
    protected $category_label = 'Build mistakes';

    /**
     * Standard hook run function to run this category of health checks.
     *
     * @param  ?array $sections_to_run Which check sections to run (null: all)
     * @param  integer $check_context The current state of the website (a CHECK_CONTEXT__* constant)
     * @param  boolean $manual_checks Mention manual checks
     * @param  boolean $automatic_repair Do automatic repairs where possible
     * @param  ?boolean $use_test_data_for_pass Should test data be for a pass [if test data supported] (null: no test data)
     * @return array A pair: category label, list of results
     */
    public function run($sections_to_run, $check_context, $manual_checks = false, $automatic_repair = false, $use_test_data_for_pass = null)
    {
        $this->process_checks_section('testManualWebStandards', 'Manual checks for web standards', $sections_to_run, $check_context, $manual_checks, $automatic_repair, $use_test_data_for_pass);
        $this->process_checks_section('testGuestAccess', 'Guest access', $sections_to_run, $check_context, $manual_checks, $automatic_repair, $use_test_data_for_pass);
        $this->process_checks_section('testBrokenLinks', 'Broken links', $sections_to_run, $check_context, $manual_checks, $automatic_repair, $use_test_data_for_pass);
        $this->process_checks_section('testIncompleteContent', 'Incomplete content', $sections_to_run, $check_context, $manual_checks, $automatic_repair, $use_test_data_for_pass);
        $this->process_checks_section('testLocalLinking', 'Local linking', $sections_to_run, $check_context, $manual_checks, $automatic_repair, $use_test_data_for_pass);
        $this->process_checks_section('testBrokenWebPostForms', 'Broken web POST forms', $sections_to_run, $check_context, $manual_checks, $automatic_repair, $use_test_data_for_pass);

        return array($this->category_label, $this->results);
    }

    /**
     * Run a section of health checks.
     *
     * @param  integer $check_context The current state of the website (a CHECK_CONTEXT__* constant)
     * @param  boolean $manual_checks Mention manual checks
     * @param  boolean $automatic_repair Do automatic repairs where possible
     * @param  ?boolean $use_test_data_for_pass Should test data be for a pass [if test data supported] (null: no test data)
     */
    public function testManualWebStandards($check_context, $manual_checks = false, $automatic_repair = false, $use_test_data_for_pass = null)
    {
        if ($check_context == CHECK_CONTEXT__INSTALL) {
            return;
        }

        if (!$manual_checks) {
            return;
        }

        // external_health_check (on maintenance sheet)

        $this->stateCheckManual('Check [url="HTML5 validation"]https://validator.w3.org/[/url] (take warnings with a pinch of salt, not every suggestion is appropriate)');
        $this->stateCheckManual('Check [url="CSS validation"]https://jigsaw.w3.org/css-validator/[/url] (take warnings with a pinch of salt, not every suggestion is appropriate)');
        $this->stateCheckManual('Check [url="WCAG validation"]https://achecker.ca/[/url] (take warnings with a pinch of salt, not every suggestion is appropriate)');

        $this->stateCheckManual('Check [url="schema.org/microformats validation"]https://search.google.com/structured-data/testing-tool/u/0/[/url] on any key pages you want to be semantic');
        $this->stateCheckManual('Check [url="OpenGraph metadata"]https://developers.facebook.com/tools/debug/sharing/[/url] on any key pages you expect to be shared');

        $this->stateCheckManual('Do a [url="general check"]https://www.woorank.com/[/url] (take warnings with a pinch of salt, not every suggestion is appropriate)');
        $this->stateCheckManual('Do a [url="general check"]https://website.grader.com/[/url] (take warnings with a pinch of salt, not every suggestion is appropriate)');

        $this->stateCheckManual('Test in Firefox');
        $this->stateCheckManual('Test in Google Chrome');
        $this->stateCheckManual('Test in IE10');
        $this->stateCheckManual('Test in IE11');
        $this->stateCheckManual('Test in Microsoft Edge');
        $this->stateCheckManual('Test in Safari');
        $this->stateCheckManual('Test in Google Chrome (mobile)');
        $this->stateCheckManual('Test in Safari (mobile)');

        $this->stateCheckManual('Check the website would look good if printed');
    }

    /**
     * Run a section of health checks.
     *
     * @param  integer $check_context The current state of the website (a CHECK_CONTEXT__* constant)
     * @param  boolean $manual_checks Mention manual checks
     * @param  boolean $automatic_repair Do automatic repairs where possible
     * @param  ?boolean $use_test_data_for_pass Should test data be for a pass [if test data supported] (null: no test data)
     */
    public function testGuestAccess($check_context, $manual_checks = false, $automatic_repair = false, $use_test_data_for_pass = null)
    {
        if ($check_context == CHECK_CONTEXT__INSTALL) {
            return;
        }

        $page_links = $this->process_urls_into_page_links();

        foreach ($page_links as $page_link) {
            $http_result = $this->get_page_http_content($page_link);

            $this->assertTrue(!in_array($http_result->message, array('401', '403')), '"' . $page_link . '" page is not allowing guest access');
        }
    }

    /**
     * Run a section of health checks.
     *
     * @param  integer $check_context The current state of the website (a CHECK_CONTEXT__* constant)
     * @param  boolean $manual_checks Mention manual checks
     * @param  boolean $automatic_repair Do automatic repairs where possible
     * @param  ?boolean $use_test_data_for_pass Should test data be for a pass [if test data supported] (null: no test data)
     */
    public function testBrokenLinks($check_context, $manual_checks = false, $automatic_repair = false, $use_test_data_for_pass = null)
    {
        if ($check_context == CHECK_CONTEXT__INSTALL) {
            return;
        }

        $page_links = $this->process_urls_into_page_links();

        $urls = array();
        foreach ($page_links as $page_link) {
            $data = $this->get_page_content($page_link);
            if ($data === null) {
                $this->stateCheckSkipped('Could not download page from website');

                continue;
            }

            $urls = array_merge($urls, $this->get_embed_urls_from_data($data));
            $urls = array_merge($urls, $this->get_link_urls_from_data($data));
        }
        $urls = array_unique($urls);

        $_urls = array();
        foreach ($urls as $url) {
            if (substr($url, 0, 2) == '//') {
                $url = 'http:' . $url;
            }

            // Don't check local URLs, we're interested in broken remote links (local validation is too much)
            if (substr($url, 0, strlen(get_base_url(false)) + 1) == get_base_url(false) . '/') {
                continue;
            }
            if (substr($url, 0, strlen(get_base_url(true)) + 1) == get_base_url(true) . '/') {
                continue;
            }
            if (strpos($url, '://') === false) {
                continue;
            }

            $_urls[] = $url;
        }

        foreach ($_urls as $url) {
            // Check
            /*
            $data = http_get_contents($url, array('byte_limit' => 0, 'trigger_error' => false));
            $ok = ($data !== null);
            */
            for ($i = 0; $i < 3; $i++) { // Try a few times in case of some temporary network issue
                $ok = check_url_exists($url, 60 * 60 * 24 * 1);
                if ($ok) {
                    break;
                }
            }
            $this->assertTrue($ok, 'Broken link: [tt]' . $url . '[/tt] (caching is 1 day on these checks)');
        }
    }

    /**
     * Run a section of health checks.
     *
     * @param  integer $check_context The current state of the website (a CHECK_CONTEXT__* constant)
     * @param  boolean $manual_checks Mention manual checks
     * @param  boolean $automatic_repair Do automatic repairs where possible
     * @param  ?boolean $use_test_data_for_pass Should test data be for a pass [if test data supported] (null: no test data)
     */
    public function testIncompleteContent($check_context, $manual_checks = false, $automatic_repair = false, $use_test_data_for_pass = null)
    {
        if ($check_context == CHECK_CONTEXT__INSTALL) {
            return;
        }

        if (!$manual_checks) {
            $this->stateCheckSkipped('Will not check automatically because there could be false positives');
            return;
        }

        $page_links = $this->process_urls_into_page_links();

        foreach ($page_links as $page_link) {
            $data = $this->get_page_content($page_link);

            if ($data === null) {
                $this->stateCheckSkipped('Could not download page from website');
                return;
            }

            $check_for = array('TODO', 'FIXME', 'Lorem Ipsum');
            foreach ($check_for as $c) {
                $this->assertTrue(strpos($data, $c) === false, 'Found a suspicious "' . $c . '" on "' . $page_link . '" page');
            }
        }
    }

    /**
     * Run a section of health checks.
     *
     * @param  integer $check_context The current state of the website (a CHECK_CONTEXT__* constant)
     * @param  boolean $manual_checks Mention manual checks
     * @param  boolean $automatic_repair Do automatic repairs where possible
     * @param  ?boolean $use_test_data_for_pass Should test data be for a pass [if test data supported] (null: no test data)
     */
    public function testLocalLinking($check_context, $manual_checks = false, $automatic_repair = false, $use_test_data_for_pass = null)
    {
        if ($check_context == CHECK_CONTEXT__INSTALL) {
            return;
        }

        if ($this->is_localhost_domain()) {
            return;
        }

        if (!$manual_checks) {
            $this->stateCheckSkipped('Will not check automatically because we do not know intent, a live site could be pointing to an Intranet');
            return;
        }

        $page_links = $this->process_urls_into_page_links();

        foreach ($page_links as $page_link) {
            $data = $this->get_page_content($page_link);

            if ($data === null) {
                $this->stateCheckSkipped('Could not download page from website');
                return;
            }

            $c = '#https?://(localhost|127\.|192\.168\.|10\.)#';
            $this->assertTrue(preg_match($c, $data) == 0, 'Found links to a local URL on "' . $page_link . '" page');
        }
    }

    /**
     * Run a section of health checks.
     *
     * @param  integer $check_context The current state of the website (a CHECK_CONTEXT__* constant)
     * @param  boolean $manual_checks Mention manual checks
     * @param  boolean $automatic_repair Do automatic repairs where possible
     * @param  ?boolean $use_test_data_for_pass Should test data be for a pass [if test data supported] (null: no test data)
     */
    public function testBrokenWebPostForms($check_context, $manual_checks = false, $automatic_repair = false, $use_test_data_for_pass = null)
    {
        if ($check_context == CHECK_CONTEXT__INSTALL) {
            return;
        }

        $zones = find_all_zones(false, false, true);
        foreach ($zones as $zone) {
            $pages = array();
            $lang = user_lang();
            if ($lang != get_site_default_lang()) {
                $pages += find_all_pages($zone, 'comcode_custom/' . get_site_default_lang(), 'txt', false, null, FIND_ALL_PAGES__ALL);
                $pages += find_all_pages($zone, 'comcode/' . get_site_default_lang(), 'txt', false, null, FIND_ALL_PAGES__ALL);
            }
            $pages += find_all_pages($zone, 'comcode_custom/' . $lang, 'txt', false, null, FIND_ALL_PAGES__ALL);
            $pages += find_all_pages($zone, 'comcode/' . $lang, 'txt', false, null, FIND_ALL_PAGES__ALL);

            foreach ($pages as $page => $page_dir) {
                $_path = (($zone == '') ? '' : ($zone . '/')) . 'pages/' . $page_dir . '/' . $page . '.txt';
                $file_path = get_custom_file_base() . '/'. $_path;
                if (!is_file($file_path)) {
                    $file_path = get_file_base() . '/'. $_path;
                }

                if (is_file($file_path)) {
                    $_c = cms_file_get_contents_safe($file_path);

                    if (stripos($_c, '<form') !== false) {
                        $c = static_evaluate_tempcode(comcode_to_tempcode($_c, null, true));

                        $matches = array();
                        $num_matches = preg_match_all('#<form[^<>]*method="POST">#i', $c, $matches);
                        for ($i = 0; $i < $num_matches; $i++) {
                            $match = $matches[0][$i];

                            $matches_action = array();
                            $has_action = (preg_match('#action=["\']([^"\']*)["\']#i', $match, $matches_action) != 0);
                            $this->assert_true($has_action, 'Has a form action defined for web POST form');

                            if ($has_action) {
                                $url = html_entity_decode($matches_action[1], ENT_QUOTES);
                                $is_absolute_url = (strpos($url, '://') !== false);
                                $this->assert_true($is_absolute_url, 'Form action is absolute (i.e. robust)');

                                if ($is_absolute_url) {
                                    $result = cms_http_request($url, null, false);
                                    $this->assert_true($result->message == '400', 'Gets 400 response, indicating only issue is missing POST parameter(s), ' . $url);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
