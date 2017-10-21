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
 * @package    commandr
 */

/**
 * Hook class.
 */
class Hook_commandr_fs_etc
{
    /**
     * Standard Commandr-fs listing function for commandr_fs hooks.
     *
     * @param  array $meta_dir The current meta-directory path
     * @param  string $meta_root_node The root node of the current meta-directory
     * @param  object $commandr_fs A reference to the Commandr filesystem object
     * @return ~array The final directory listing (false: failure)
     */
    public function listing($meta_dir, $meta_root_node, &$commandr_fs)
    {
        require_all_lang();

        require_code('resource_fs');

        if (count($meta_dir) > 0) {
            return false; // Directory doesn't exist
        }
        load_config_options();

        $query = 'SELECT param_a,MAX(date_and_time) AS date_and_time FROM ' . get_table_prefix() . 'actionlogs WHERE ' . db_string_equal_to('the_type', 'CONFIGURATION') . ' GROUP BY param_a';
        $modification_times = collapse_2d_complexity('param_a', 'date_and_time', $GLOBALS['SITE_DB']->query($query));

        $listing = array();
        $hooks = find_all_hooks('systems', 'config');
        foreach (array_keys($hooks) as $option) {
            $value = get_option($option);
            if ($value === null) {
                continue;
            }

            $modification_time = array_key_exists($option, $modification_times) ? $modification_times[$option] : null;

            $listing[] = array(
                $option,
                COMMANDR_FS_FILE,
                strlen($value),
                $modification_time,
            );
        }

        require_code('resource_fs');
        $hooks = find_all_hook_obs('systems', 'commandr_fs_extended_config', 'Hook_commandr_fs_extended_config__');
        foreach ($hooks as $hook => $ob) {
            $modification_time = $ob->get_edit_date();

            $listing[] = array(
                '_' . $hook . 's' . '.' . RESOURCE_FS_DEFAULT_EXTENSION,
                COMMANDR_FS_FILE,
                null/*don't calculate a filesize*/,
                $modification_time,
            );
        }

        return $listing;
    }

    /**
     * Standard Commandr-fs directory creation function for commandr_fs hooks.
     *
     * @param  array $meta_dir The current meta-directory path
     * @param  string $meta_root_node The root node of the current meta-directory
     * @param  string $new_dir_name The new directory name
     * @param  object $commandr_fs A reference to the Commandr filesystem object
     * @return boolean Success?
     */
    public function make_directory($meta_dir, $meta_root_node, $new_dir_name, &$commandr_fs)
    {
        return false;
    }

    /**
     * Standard Commandr-fs directory removal function for commandr_fs hooks.
     *
     * @param  array $meta_dir The current meta-directory path
     * @param  string $meta_root_node The root node of the current meta-directory
     * @param  string $dir_name The directory name
     * @param  object $commandr_fs A reference to the Commandr filesystem object
     * @return boolean Success?
     */
    public function remove_directory($meta_dir, $meta_root_node, $dir_name, &$commandr_fs)
    {
        return false;
    }

    /**
     * Standard Commandr-fs file removal function for commandr_fs hooks.
     *
     * @param  array $meta_dir The current meta-directory path
     * @param  string $meta_root_node The root node of the current meta-directory
     * @param  string $file_name The file name
     * @param  object $commandr_fs A reference to the Commandr filesystem object
     * @return boolean Success?
     */
    public function remove_file($meta_dir, $meta_root_node, $file_name, &$commandr_fs)
    {
        if (count($meta_dir) > 0) {
            return false; // Directory doesn't exist
        }

        return false;
    }

    /**
     * Standard Commandr-fs file reading function for commandr_fs hooks.
     *
     * @param  array $meta_dir The current meta-directory path
     * @param  string $meta_root_node The root node of the current meta-directory
     * @param  string $file_name The file name
     * @param  object $commandr_fs A reference to the Commandr filesystem object
     * @return ~string The file contents (false: failure)
     */
    public function read_file($meta_dir, $meta_root_node, $file_name, &$commandr_fs)
    {
        if (count($meta_dir) > 0) {
            return false; // Directory doesn't exist
        }

        require_code('resource_fs');
        $hooks = find_all_hooks('systems', 'commandr_fs_extended_config');
        $extended_config_filename = preg_replace('#^_(.*)s' . preg_quote('.' . RESOURCE_FS_DEFAULT_EXTENSION, '#') . '$#', '${1}', $file_name);
        if (array_key_exists($extended_config_filename, $hooks)) {
            require_code('hooks/systems/commandr_fs_extended_config/' . filter_naughty_harsh($extended_config_filename));
            $ob = object_factory('Hook_commandr_fs_extended_config__' . filter_naughty_harsh($extended_config_filename));
            return $ob->read_file($meta_dir, $meta_root_node, $file_name, $commandr_fs);
        }

        $option = get_option($file_name, true);
        if ($option === null) {
            return false;
        }
        return $option;
    }

    /**
     * Standard Commandr-fs file writing function for commandr_fs hooks.
     *
     * @param  array $meta_dir The current meta-directory path
     * @param  string $meta_root_node The root node of the current meta-directory
     * @param  string $file_name The file name
     * @param  string $contents The new file contents
     * @param  object $commandr_fs A reference to the Commandr filesystem object
     * @return boolean Success?
     */
    public function write_file($meta_dir, $meta_root_node, $file_name, $contents, &$commandr_fs)
    {
        require_code('config2');

        if (count($meta_dir) > 0) {
            return false; // Directory doesn't exist
        }

        require_code('resource_fs');
        $hooks = find_all_hooks('systems', 'commandr_fs_extended_config');
        $extended_config_filename = preg_replace('#^_(.*)s' . preg_quote('.' . RESOURCE_FS_DEFAULT_EXTENSION, '#') . '$#', '${1}', $file_name);
        if (array_key_exists($extended_config_filename, $hooks)) {
            require_code('hooks/systems/commandr_fs_extended_config/' . filter_naughty_harsh($extended_config_filename));
            $ob = object_factory('Hook_commandr_fs_extended_config__' . filter_naughty_harsh($extended_config_filename));
            return $ob->write_file($meta_dir, $meta_root_node, $file_name, $contents, $commandr_fs);
        }

        $value = get_option($file_name, true);
        if ($value === null) {
            return false; // File doesn't exist
        }

        set_option($file_name, $contents);

        return true;
    }
}
