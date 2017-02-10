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
 * @package    setupwizard
 */

/**
 * Hook class.
 */
class Hook_sw_core
{
    /**
     * Run function for features in the setup wizard.
     *
     * @return array Current settings.
     */
    public function get_current_settings()
    {
        $settings = array();

        $settings['show_content_tagging'] = (get_option('show_content_tagging') == '1') ? '1' : '0';
        $settings['show_content_tagging_inline'] = (get_option('show_content_tagging_inline') == '1') ? '1' : '0';
        $settings['show_screen_actions'] = (get_option('show_screen_actions') == '1') ? '1' : '0';
        $settings['collapse_user_zones'] = (get_option('collapse_user_zones') == '1') ? '1' : '0';

        $guest_groups = $GLOBALS['FORUM_DRIVER']->get_members_groups($GLOBALS['FORUM_DRIVER']->get_guest_id());
        $test = $GLOBALS['SITE_DB']->query_select_value_if_there('group_zone_access', 'zone_name', array('zone_name' => 'site', 'group_id' => $guest_groups[0]));
        $settings['guest_zone_access'] = is_null($test) ? '0' : '1';

        return $settings;
    }

    /**
     * Run function for features in the setup wizard.
     *
     * @param  array $field_defaults Default values for the fields, from the install-profile.
     * @return Tempcode An input field.
     */
    public function get_fields($field_defaults)
    {
        $fields = new Tempcode();

        $field_defaults += $this->get_current_settings(); // $field_defaults will take precedence, due to how "+" operator works in PHP

        $fields->attach(form_input_tick(do_lang_tempcode('SHOW_CONTENT_TAGGING'), do_lang_tempcode('CONFIG_OPTION_show_content_tagging'), 'show_content_tagging', $field_defaults['show_content_tagging'] == '1'));
        $fields->attach(form_input_tick(do_lang_tempcode('SHOW_CONTENT_TAGGING_INLINE'), do_lang_tempcode('CONFIG_OPTION_show_content_tagging_inline'), 'show_content_tagging_inline', $field_defaults['show_content_tagging_inline'] == '1'));
        $fields->attach(form_input_tick(do_lang_tempcode('SHOW_SCREEN_ACTIONS'), do_lang_tempcode('CONFIG_OPTION_show_screen_actions'), 'show_screen_actions', $field_defaults['show_screen_actions'] == '1'));

        $fields->attach(do_template('FORM_SCREEN_FIELD_SPACER', array('_GUID' => '1f8970c551c886532158e16596f9c9b8', 'TITLE' => do_lang_tempcode('menus:STRUCTURE'), 'HELP' => do_lang_tempcode('SETUPWIZARD_5x_DESCRIBE'))));

        $fields->attach(form_input_tick(do_lang_tempcode('COLLAPSE_USER_ZONES'), do_lang_tempcode('CONFIG_OPTION_collapse_user_zones'), 'collapse_user_zones', $field_defaults['collapse_user_zones'] == '1'));
        $fields->attach(form_input_tick(do_lang_tempcode('GUEST_ZONE_ACCESS'), do_lang_tempcode('DESCRIPTION_GUEST_ZONE_ACCESS'), 'guest_zone_access', $field_defaults['guest_zone_access'] == '1'));

        return $fields;
    }

    /**
     * Run function for setting features from the setup wizard.
     */
    public function set_fields()
    {
        set_option('show_content_tagging', post_param_string('show_content_tagging', '0'));
        set_option('show_content_tagging_inline', post_param_string('show_content_tagging_inline', '0'));
        set_option('show_screen_actions', post_param_string('show_screen_actions', '0'));

        // Zone structure
        $collapse_zones = post_param_integer('collapse_user_zones', 0) == 1;
        set_option('collapse_user_zones', $collapse_zones ? '1' : '0');
        $guest_groups = $GLOBALS['FORUM_DRIVER']->get_members_groups($GLOBALS['FORUM_DRIVER']->get_guest_id());
        if (post_param_integer('guest_zone_access', 0) == 1) {
            $test = $GLOBALS['SITE_DB']->query_select_value_if_there('group_zone_access', 'zone_name', array('zone_name' => 'site', 'group_id' => $guest_groups[0]));
            if (is_null($test)) {
                $GLOBALS['SITE_DB']->query_insert('group_zone_access', array('zone_name' => 'site', 'group_id' => $guest_groups[0]));
            }
        } else {
            $GLOBALS['SITE_DB']->query_delete('group_zone_access', array('zone_name' => 'site', 'group_id' => $guest_groups[0]), '', 1);
        }
    }
}
