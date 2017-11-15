<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2017

 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    data_mappr
 */

/**
 * Hook class.
 */
class Hook_config_google_map_key
{
    /**
     * Gets the details relating to the config option.
     *
     * @return ?array The details (null: disabled)
     */
    public function get_details()
    {
        return array(
            'human_name' => 'GOOGLE_MAP_KEY',
            'type' => 'line',
            'category' => 'FEATURE',
            'group' => 'GOOGLE_MAP',
            'explanation' => 'CONFIG_OPTION_google_map_key',
            'shared_hosting_restricted' => '0',
            'list_options' => '',
            'required' => false,
            'public' => true,

            'addon' => 'data_mappr',

            'maintenance_code' => 'google_maps',
        );
    }

    /**
     * Gets the default value for the config option.
     *
     * @return ?string The default value (null: option is disabled)
     */
    public function get_default()
    {
        return '';
    }
}
