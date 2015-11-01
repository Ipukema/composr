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
 * @package    commandr
 */

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__commandr_fs()
{
    define('COMMANDRFS_FILE', 0);
    define('COMMANDRFS_DIR', 1);
}

/**
 * Virtual filesystems.
 *
 * @package    commandr
 */
class Commandr_fs
{
    public $commandr_fs;
    public $pwd;
    public $current_meta = null;
    public $current_meta_pwd = null;

    /**
     * Constructor function. Setup a virtual filesystem, but do nothing with it.
     */
    public function commandr_fs()
    {
        // Initialise a new virtual filesystem; setup the vfs array, and fetch the pwd from a cookie

        /*
        The pwd is stored in a flat array, each value holds the key for each level in the $this->commandr_fs array that is in the pwd:
            $this->pwd=array('blah2','foo3','bar');

        The virtual filesystem is a nested directory structure, where terminals mapping to strings represent Commandr-fs hooks
            $this->commandr_fs=array(
                    'blah'=>array(),
        *** 'blah2'=>array(
                            'foo'=>array(),
                            'foo2'=>array(),
        ***    'foo3'=>array(
        ***       'bar'=>'members', // 'members' hook is tied into 'bar', rather than an explicit array
                                        'bar2'=>array(),
                            ),
                            'foo4'=>array(),
                    ),
                    'blah3'=>array(),
            );
        */

        // Build up the filesystem structure
        $commandrfs_hooks = find_all_hooks('systems', 'commandr_fs');
        $this->commandr_fs = array();
        $cma_hooks = find_all_hooks('systems', 'content_meta_aware') + find_all_hooks('systems', 'resource_meta_aware');
        require_code('content');
        $var = array();
        foreach (array_keys($cma_hooks) as $hook) { // Find 'var' hooks, for content
            $cma_ob = get_content_object($hook);
            $cma_info = $cma_ob->info();
            $commandr_fs_hook = $cma_info['commandr_filesystem_hook'];
            if (!is_null($commandr_fs_hook)) {
                unset($commandrfs_hooks[$commandr_fs_hook]); // It's under 'var', don't put elsewhere
                $var[$commandr_fs_hook] = $commandr_fs_hook;
            }
        }
        foreach (array_keys($commandrfs_hooks) as $hook) { // Other filesystems go directly under the root (not 'root', which is different)
            $this->commandr_fs[$hook] = $hook;
        }
        $this->commandr_fs['var'] = $var;

        $this->pwd = $this->_start_pwd();
        $this->current_meta = null;
        $this->current_meta_pwd = null;
    }

    /**
     * Fetch the current directory from a cookie, or the default.
     *
     * @return array Current directory
     */
    protected function _start_pwd()
    {
        // Fetch the pwd from a cookie, or generate a new one
        if (array_key_exists('commandr_dir', $_COOKIE)) {
            if (get_magic_quotes_gpc()) {
                $_COOKIE['commandr_dir'] = stripslashes($_COOKIE['commandr_dir']);
            }
            return $this->_pwd_to_array(base64_decode($_COOKIE['commandr_dir']));
        } else {
            $default_dir = array();
            require_code('users_active_actions');
            cms_setcookie('commandr_dir', base64_encode($this->pwd_to_string($default_dir)));
            return $default_dir;
        }
    }

    /**
     * Return the contents of the given directory in $this->commandr_fs (i.e. ls without the fancy bits).
     *
     * @param  ?array $dir Directory (null: current directory is used)
     * @param  boolean $full_paths Whether to use full paths
     * @return ~array Directory contents (false: failure)
     */
    protected function _get_current_dir_contents($dir = null, $full_paths = false)
    {
        if (is_null($dir)) {
            $dir = $this->pwd;
        }

        if (strpos(implode('/', $dir), '*') !== false) { // Handle wildcards
            $end_bit = array_pop($dir); // Remove last element
            $dir_remaining = implode('/', $dir);
            if ($dir_remaining == '') {
                $dir_remaining = '/';
            }

            $ret = array();
            if (strpos($dir_remaining, '*') !== false) { // Showing everything underneath any outcome of the wildcards of directories paths
                $before = $this->_get_current_dir_contents($dir, true);
                foreach ($before as $entry) {
                    $_ret = $this->_get_current_dir_contents(array_merge(explode('/', $entry[0]), array($end_bit)), $full_paths);
                    if ($_ret !== false) {
                        $ret = array_merge($ret, $_ret);
                    }
                }
            } else { // Filtering everything under a directory by a wildcard
                $before = $this->_get_current_dir_contents($dir, $full_paths);

                foreach ($before as $entry) {
                    if (simulated_wildcard_match($entry[0], $end_bit, true)) {
                        $entry[0] = preg_replace('#^.*/#', '', $entry[0]);
                        $ret[] = $entry;
                    }
                }
            }
            return $ret;
        }

        $meta_dir = array();
        $meta_root_node = '';
        $meta_root_node_type = '';
        $current_dir = $this->_discern_meta_dir($meta_dir, $meta_root_node, $meta_root_node_type, $dir);

        if (!is_null($meta_root_node)) {
            // We're underneath a meta root node (a directory which is generated dynamically)
            require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($meta_root_node_type));
            $object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($meta_root_node_type));
            $current_dir = $object->listing($meta_dir, $meta_root_node, $this);

            if ($full_paths) {
                foreach ($current_dir as $i => $d) {
                    $current_dir[$i][0] = implode('/', $dir) . '/' . $d[0];
                }
            }
        }
        return $current_dir;
    }

    /**
     * Convert a string-form path to an array.
     *
     * @param  string $pwd Path
     * @return array Array-form path
     */
    public function _pwd_to_array($pwd)
    {
        // Convert a string-form pwd to an array-form pwd, and sanitise it
        if ($pwd == '') {
            return array();
        }
        $absolute = ($pwd[0] == '/');
        $_pwd = explode('/', $pwd);
        if ($absolute) {
            $target_directory = array();
        } else {
            $target_directory = $this->pwd;
        }
        return $this->_merge_pwds($target_directory, $_pwd);
    }

    /**
     * Merge an absolute array-form path with a non-absolute array-form path, with support for "."/".." resolution.
     *
     * @param  array $pwd1 Absolute path
     * @param  array $pwd2 Non-absolute path
     * @return array Merged path
     */
    protected function _merge_pwds($pwd1, $pwd2)
    {
        // Merge two array-form pwds, assuming the former is absolute and the latter isn't
        $target_directory = $pwd1;
        foreach ($pwd2 as $section) {
            if (($section != '.') && ($section != '..') && ($section != '') && (!is_null($section))) {
                $target_directory[] = $section;
            } elseif ($section == '..') {
                array_pop($target_directory);
            }
        }
        return $target_directory;
    }

    /**
     * Convert an array-form path to a string.
     *
     * @param  ?array $pwd Path (null: use $this->pwd)
     * @return string String-form path
     */
    public function pwd_to_string($pwd = null)
    {
        if (is_null($pwd)) {
            $pwd = $this->pwd;
        }
        $output = '';
        foreach ($pwd as $section) {
            $output .= '/' . $section;
        }
        if ($this->_is_dir($pwd)) {
            $output .= '/';
        }
        return $output;
    }

    /**
     * Return filename from a path.
     *
     * @param  string $filename Path
     * @return string Filename
     */
    protected function _get_filename($filename)
    {
        // Make sure no directories are included with the filename
        $parts = explode('/', $filename);
        return $parts[count($parts) - 1];
    }

    /**
     * Is it a directory?
     *
     * @param  ?array $dir Path to check (null: current dir is used)
     * @return boolean Directory?
     */
    public function _is_dir($dir = null)
    {
        if (is_null($dir)) {
            $dir = $this->pwd;
        }

        if (count($dir) == 0) {
            return true;
        }
        $filename = array_pop($dir);

        $contents = $this->_get_current_dir_contents($dir); // Look at contents of parent directory
        if ($contents === false) {
            return false;
        }

        foreach ($contents as $entry) {
            if ($entry[0] == $filename) {
                return $entry[1] == COMMANDRFS_DIR;
            }
        }

        return false;
    }

    /**
     * Is it a file?
     *
     * @param  array $dir Path (with filename) to use
     * @return boolean Directory?
     */
    public function _is_file($dir)
    {
        $filename = array_pop($dir);

        $contents = $this->_get_current_dir_contents($dir); // Look at contents of parent directory
        if ($contents === false) {
            return false;
        }

        foreach ($contents as $entry) {
            if ($entry[0] == $filename) {
                return $entry[1] == COMMANDRFS_FILE;
            }
        }

        return false;
    }

    /**
     * Get details of the current meta directory.
     *
     * @param  array $meta_dir Meta directory result: returned by reference
     * @param  string $meta_root_node Meta root node result: returned by reference
     * @param  string $meta_root_node_type Meta root node type result: returned by reference
     * @param  ?array $target_dir Directory (null: current directory is used)
     * @return ~array Current directory contents (false: error)
     */
    protected function _discern_meta_dir(&$meta_dir, &$meta_root_node, &$meta_root_node_type, $target_dir = null)
    {
        // Get the details of the current meta dir (re: object creation) and where the pwd is in relation to it
        $inspected_dir = $this->_convert_meta_dir_to_detailed_dir($this->commandr_fs); // Start at the root
        if (is_null($target_dir)) {
            $target_dir = $this->pwd;
        }
        $meta_dir = $target_dir;
        $meta_root_node = null;
        $meta_root_node_type = null;

        foreach ($target_dir as $section_no => $section) { // For each component in our path
            unset($meta_dir[$section_no]); // Okay so we're still not under the meta-dir, so actually this $section_no is not a part of the meta-dir

            if (!array_key_exists($section, $inspected_dir)) {
                return false; // Cannot find the directory
            }

            if (is_array($inspected_dir[$section][4])) { // Hard-coded known directory, so we can scan it
                $inspected_dir = $this->_convert_meta_dir_to_detailed_dir($inspected_dir[$section][4]); // We will continue on through more possible hard-coded directories, or to find a deeper meta-dir
            } else { // Known directory, and we've not got to a meta-dir yet -- must therefore be the meta-dir
                $meta_root_node = $section;
                $meta_root_node_type = $inspected_dir[$section][4];
                $inspected_dir = array();
                break; // We've found the meta-dir we're under, so we can stop going through now
            }
        }

        $meta_dir = array_values($meta_dir); // Everything left over needs re-indexing

        return $inspected_dir;
    }

    /**
     * Fill out a hardcoded meta-dir to use our more detailed internal format.
     *
     * @param  array $_inspected_dir Simple list of directories under here
     * @return array Full detailed directory contents
     */
    protected function _convert_meta_dir_to_detailed_dir($_inspected_dir)
    {
        $inspected_dir = array();
        foreach ($_inspected_dir as $dir_name => $contents) {
            $inspected_dir[$dir_name/*only here for hard-coded dirs*/] = array(
                $dir_name,
                COMMANDRFS_DIR,
                null,
                null,
                $contents, // This is only here for hard-coded dirs; it will either be a string (i.e. hook name) or an array (more hard-coded depth to go)
            );
        }
        return $inspected_dir;
    }

    /**
     * Convert a directory contents structure into a template parameter structure.
     *
     * @param  array $entries Structure
     * @return array Template parameter structure
     */
    public function prepare_dir_contents_for_listing($entries)
    {
        $out = array();
        require_code('files');
        foreach ($entries as $entry) {
            $out[] = array(
                'FILENAME' => $entry[0],
                'FILESIZE' => is_null($entry[2]) ? '' : clean_file_size($entry[2]),
                '_FILESIZE' => is_null($entry[2]) ? '' : strval($entry[2]),
                'MTIME' => is_null($entry[3]) ? '' : date('Y-m-d H:i', $entry[3]),
                '_MTIME' => is_null($entry[3]) ? '' : strval($entry[3]),
            );
        }
        return $out;
    }

    /**
     * Return the current working directory of the virtual filesystem. Equivalent to Unix "pwd".
     *
     * @param  boolean $array_form Return the pwd in array form?
     * @return mixed The current working directory (array or string)
     */
    public function print_working_directory($array_form = false)
    {
        // Return the current working directory
        if ($array_form) {
            return $this->pwd;
        } else {
            return $this->pwd_to_string();
        }
    }

    /**
     * Return a directory and file listing of the current working directory. Equivalent to Unix "ls".
     *
     * @param  ?array $dir An alternate directory in which to perform the action (null: current directory is used)
     * @return array Directories and files in the current working directory
     */
    public function listing($dir = null)
    {
        // Return an array list of all the directories and files in the pwd
        $current_dir_contents = $this->_get_current_dir_contents($dir);
        if ($current_dir_contents === false) {
            return array(array(), array());
        }

        $directories = array();
        $files = array();

        foreach ($current_dir_contents as $entry) {
            if ($entry[1] == COMMANDRFS_DIR) {
                // Directory
                $directories[$entry[0]] = $entry;
            } elseif ($entry[1] == COMMANDRFS_FILE) {
                // File
                $files[$entry[0]] = $entry;
            }
        }

        // Sort them nicely and neatly ;-)
        asort($directories);
        asort($files);

        return array($directories, $files);
    }

    /**
     * Return a listing of all the files/directories found matching the specified pattern. Equivalent to Unix "find".
     *
     * @param  string $pattern The search pattern (PRCE regexp or plain)
     * @param  boolean $regexp Is the search pattern a regexp?
     * @param  boolean $recursive Should the search be recursive?
     * @param  boolean $files Should files be included in the results?
     * @param  boolean $directories Should directories be included in the results?
     * @param  ?array $dir Directory (null: current directory is used)
     * @return array The search results
     */
    public function search($pattern, $regexp = false, $recursive = false, $files = true, $directories = false, $dir = null)
    {
        // Search!
        $current_dir_contents = $this->listing($dir);
        $dir_string = $this->pwd_to_string($dir);
        $output_directories = array();
        $output_files = array();

        if ($regexp) {
            if (($pattern == '') || (($pattern[0] != '#') && ($pattern[0] != '/'))) {
                $pattern = '#' . $pattern . '#';
            }
        }

        foreach ($current_dir_contents[0/*directories*/] as $directory) {
            if ($directories) {
                if (($regexp) && (preg_match($pattern, $directory[0]))) {
                    $output_directories[] = $dir_string . $directory[0] . '/';
                } elseif ((!$regexp) && ($pattern == $directory[0])) {
                    $output_directories[] = $dir_string . $directory[0] . '/';
                }
            }
            if ($recursive) {
                $temp_dir = $dir;
                $temp_dir[] = $directory[0];
                $temp = $this->search($pattern, $regexp, $recursive, $files, $directories, $temp_dir);
                $output_directories = array_merge($output_directories, $temp[0]);
                $output_files = array_merge($output_files, $temp[1]);
            }
        }

        if ($files) {
            foreach ($current_dir_contents[1/*files*/] as $file) {
                if (($regexp) && (preg_match($pattern, $file[0]))) {
                    $output_files[] = $dir_string . $file[0];
                } elseif ((!$regexp) && ($pattern == $file[0])) {
                    $output_files[] = $dir_string . $file[0];
                }
            }
        }

        // Sort them nicely and neatly ;-)
        asort($output_directories);
        asort($output_files);

        return array($output_directories, $output_files);
    }

    /**
     * Change the current working directory. Equivalent to Unix "cd".
     *
     * @param  array $target_directory The target directory path
     * @return boolean Success?
     */
    public function change_directory($target_directory)
    {
        // Change the current directory
        if ($this->_is_dir($target_directory)) {
            $this->pwd = $target_directory;
            require_code('users_active_actions');
            cms_setcookie('commandr_dir', base64_encode($this->pwd_to_string($target_directory)));

            return true;
        } else {
            return false;
        }
    }

    /**
     * Create a directory under the current working directory. Equivalent to Unix "mkdir".
     *
     * @param  array $directory The new directory's path and name
     * @return boolean Success?
     */
    public function make_directory($directory)
    {
        $directory_name = array_pop($directory);
        $meta_dir = array();
        $meta_root_node = '';
        $meta_root_node_type = '';
        $this->_discern_meta_dir($meta_dir, $meta_root_node, $meta_root_node_type, $directory);

        if (!is_null($meta_root_node)) {
            // We're underneath a meta root node (a directory which is generated dynamically)
            require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($meta_root_node_type));
            $object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($meta_root_node_type));
            return $object->make_directory($meta_dir, $meta_root_node, $directory_name, $this);
        } else {
            return false;
        }
    }

    /**
     * Remove a directory under the current working directory. Equivalent to Unix "rmdir".
     *
     * @param  array $directory The directory-to-remove's path and name
     * @return boolean Success?
     */
    public function remove_directory($directory)
    {
        $directory_name = $directory[count($directory) - 1];
        $meta_dir = array();
        $meta_root_node = '';
        $meta_root_node_type = '';
        $this->_discern_meta_dir($meta_dir, $meta_root_node, $meta_root_node_type, $directory);

        if (!is_null($meta_root_node)) {
            // We're underneath a meta root node (a directory which is generated dynamically)
            require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($meta_root_node_type));
            $object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($meta_root_node_type));
            $listing = $object->listing($meta_dir, $meta_root_node, $directory, $this);

            // Remove contents
            foreach ($listing as $value) {
                switch ($value[1]) {
                    case COMMANDRFS_FILE:
                        $object->remove_file($directory, $meta_root_node, $value[0], $this);
                        break;
                    case COMMANDRFS_DIR:
                        $this->remove_directory(array_merge($directory, array($value[0]))); // Recurse
                        break;
                }
            }

            array_pop($meta_dir);

            // Remove directory itself
            return $object->remove_directory($meta_dir, $meta_root_node, $directory_name, $this);
        } else {
            return false;
        }
    }

    /**
     * Copy a directory. Equivalent to Unix "cp".
     *
     * @param  array $to_copy The directory to copy
     * @param  array $destination The destination path
     * @return boolean Success?
     */
    public function copy_directory($to_copy, $destination)
    {
        $directory_contents = $this->_get_current_dir_contents($to_copy);
        $success = true;

        $dir_name = $to_copy[count($to_copy) - 1];
        $_destination = $destination;
        $_destination[] = $dir_name;

        if (!$this->make_directory($_destination)) {
            return false;
        }

        foreach ($directory_contents as $entry) {
            $_to_copy_path = $to_copy;
            $_destination = $destination;
            $_destination[] = $dir_name;

            if ($entry[1] == COMMANDRFS_DIR) {
                $_to_copy_path[] = $entry[0];
                $success = ($success) ? $this->copy_directory($_to_copy_path, $_destination) : false;
            } elseif ($entry[1] == COMMANDRFS_FILE) {
                $_to_copy_path[] = $entry[0];
                $success = ($success) ? $this->copy_file($_to_copy_path, $_destination) : false;
            }
        }

        return $success;
    }

    /**
     * Move a directory. Equivalent to Unix "mv".
     *
     * @param  array $to_move The directory to move
     * @param  array $destination The destination path
     * @return boolean Success?
     */
    public function move_directory($to_move, $destination)
    {
        $to_move_meta_dir = array();
        $to_move_meta_root_node = '';
        $to_move_meta_root_node_type = '';
        $this->_discern_meta_dir($to_move_meta_dir, $to_move_meta_root_node, $to_move_meta_root_node_type, $to_move);
        require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($to_move_meta_root_node_type));
        $to_move_object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($to_move_meta_root_node_type));

        $destination_meta_dir = array();
        $destination_meta_root_node = '';
        $destination_meta_root_node_type = '';
        $this->_discern_meta_dir($destination_meta_dir, $destination_meta_root_node, $destination_meta_root_node_type, $destination);
        require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($destination_meta_root_node_type));
        $destination_object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($destination_meta_root_node_type));

        if ($destination_meta_root_node == $to_move_meta_root_node_type) {
            if (method_exists($to_move_object, 'folder_save')) { // Resource-fs wants a better renaming technique
                $new_label = array_pop($destination_meta_dir);
                return $to_move_object->folder_save(array_pop($to_move_meta_dir), implode('/', $destination_meta_dir), array('label' => $new_label));
            }
        }

        $success = $this->copy_directory($to_move, $destination);
        if ($success) {
            return $this->remove_directory($to_move);
        } else {
            return false;
        }
    }

    /**
     * Copy a file. Equivalent to Unix "cp".
     *
     * @param  array $to_copy The file to copy
     * @param  array $destination The destination path
     * @return boolean Success?
     */
    public function copy_file($to_copy, $destination)
    {
        $contents = $this->read_file($to_copy);
        $destination[] = $to_copy[count($to_copy) - 1];
        return $this->write_file($destination, $contents) !== false;
    }

    /**
     * Move a file. Equivalent to Unix "mv".
     *
     * @param  array $to_move The file to move
     * @param  array $destination The destination path
     * @return boolean Success?
     */
    public function move_file($to_move, $destination)
    {
        $to_move_meta_dir = array();
        $to_move_meta_root_node = '';
        $to_move_meta_root_node_type = '';
        $this->_discern_meta_dir($to_move_meta_dir, $to_move_meta_root_node, $to_move_meta_root_node_type, $to_move);
        require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($to_move_meta_root_node_type));
        $to_move_object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($to_move_meta_root_node_type));

        $destination_meta_dir = array();
        $destination_meta_root_node = '';
        $destination_meta_root_node_type = '';
        $this->_discern_meta_dir($destination_meta_dir, $destination_meta_root_node, $destination_meta_root_node_type, $destination);
        require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($destination_meta_root_node_type));
        $destination_object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($destination_meta_root_node_type));

        if ($destination_meta_root_node == $to_move_meta_root_node_type) {
            if (method_exists($to_move_object, 'file_save')) { // Resource-fs wants a better renaming technique
                $new_label = basename(array_pop($destination_meta_dir), '.' . RESOURCEFS_DEFAULT_EXTENSION);
                return $to_move_object->file_save(array_pop($to_move_meta_dir), implode('/', $destination_meta_dir), array('label' => $new_label));
            }
        }

        $success = $this->copy_file($to_move, $destination);
        if ($success) {
            return $this->remove_file($to_move);
        }
        return false;
    }

    /**
     * Remove a file. Equivalent to Unix "rm".
     *
     * @param  array $to_remove The file to remove
     * @return boolean Success?
     */
    public function remove_file($to_remove)
    {
        $filename = array_pop($to_remove);
        $meta_dir = array();
        $meta_root_node = '';
        $meta_root_node_type = '';
        $this->_discern_meta_dir($meta_dir, $meta_root_node, $meta_root_node_type, $to_remove);

        if (!is_null($meta_root_node)) {
            // We're underneath a meta root node (a directory which is generated dynamically)
            require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($meta_root_node_type));
            $object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($meta_root_node_type));
            return $object->remove_file($meta_dir, $meta_root_node, $filename, $this);
        } else {
            return false;
        }
    }

    /**
     * Read a file and return the contents.
     *
     * @param  array $to_read The file to read
     * @return ~string The file contents (false: failure)
     */
    public function read_file($to_read)
    {
        $filename = array_pop($to_read);
        $meta_dir = array();
        $meta_root_node = '';
        $meta_root_node_type = '';
        $this->_discern_meta_dir($meta_dir, $meta_root_node, $meta_root_node_type, $to_read);

        if (!is_null($meta_root_node)) {
            // We're underneath a meta root node (a directory which is generated dynamically)
            require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($meta_root_node_type));
            $object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($meta_root_node_type));
            return $object->read_file($meta_dir, $meta_root_node, $filename, $this);
        } else {
            return false;
        }
    }

    /**
     * Write to a file; create the file if it doesn't exist.
     *
     * @param  array $to_write The file to write
     * @param  string $contents The contents to write
     * @return boolean Success?
     */
    public function write_file($to_write, $contents)
    {
        $filename = array_pop($to_write);
        $meta_dir = array();
        $meta_root_node = '';
        $meta_root_node_type = '';
        $this->_discern_meta_dir($meta_dir, $meta_root_node, $meta_root_node_type, $to_write);

        if (!is_null($meta_root_node)) {
            // We're underneath a meta root node (a directory which is generated dynamically)
            require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($meta_root_node_type));
            $object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($meta_root_node_type));
            return $object->write_file($meta_dir, $meta_root_node, $filename, $contents, $this) !== false;
        } else {
            return false;
        }
    }

    /**
     * Append to a file.
     *
     * @param  array $to_append The file to which to append
     * @param  string $contents The contents to append
     * @return boolean Success?
     */
    public function append_file($to_append, $contents)
    {
        $filename = array_pop($to_append);
        $meta_dir = array();
        $meta_root_node = '';
        $meta_root_node_type = '';
        $this->_discern_meta_dir($meta_dir, $meta_root_node, $meta_root_node_type, $to_append);

        if (!is_null($meta_root_node)) {
            // We're underneath a meta root node (a directory which is generated dynamically)
            require_code('hooks/systems/commandr_fs/' . filter_naughty_harsh($meta_root_node_type));
            $object = object_factory('Hook_commandr_fs_' . filter_naughty_harsh($meta_root_node_type));
            $old_contents = $object->read_file($meta_dir, $meta_root_node, $filename, $this);
            return $object->write_file($meta_dir, $meta_root_node, $filename, $old_contents . $contents, $this);
        } else {
            return false;
        }
    }
}
