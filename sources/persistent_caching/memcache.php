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
 * @package    core
 */

/*EXTRA FUNCTIONS: Memcache*/

/**
 * Cache driver class.
 */
class Persistent_caching_memcache extends Memcache
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->connect('localhost', 11211);
    }

    public $objects_list = null;

    /**
     * Instruction to load up the objects list.
     *
     * @return array The list of objects
     */
    public function load_objects_list()
    {
        if (is_null($this->objects_list)) {
            $this->objects_list = $this->get(get_file_base() . 'PERSISTENT_CACHE_OBJECTS');
            if ($this->objects_list === false) {
                $this->objects_list = array();
            }
        }
        return $this->objects_list;
    }

    /**
     * Get data from the persistent cache.
     *
     * @param  string $key Key
     * @param  ?TIME $min_cache_date Minimum timestamp that entries from the cache may hold (null: don't care)
     * @return ?mixed The data (null: not found / null entry)
     */
    public function get($key, $min_cache_date = null)
    {
        $_data = parent::get($key);
        if ($_data === false) {
            return null;
        }
        $data = unserialize($_data);
        if ((!is_null($min_cache_date)) && ($data[0] < $min_cache_date)) {
            return null;
        }
        return $data[1];
    }

    /**
     * Put data into the persistent cache.
     *
     * @param  string $key Key
     * @param  mixed $data The data
     * @param  integer $flags Various flags (parameter not used)
     * @param  ?integer $expire_secs The expiration time in seconds (null: no expiry)
     */
    public function set($key, $data, $flags = 0, $expire_secs = null)
    {
        // Update list of persistent-objects
        $objects_list = $this->load_objects_list();
        if (!array_key_exists($key, $objects_list)) {
            $objects_list[$key] = true;
            $this->set(get_file_base() . 'PERSISTENT_CACHE_OBJECTS', $objects_list, 0, 0);
        }

        parent::set($key, array(time(), $data), $flags, $expire_secs);
    }

    /**
     * Delete data from the persistent cache.
     *
     * @param  string $key Key
     */
    public function delete($key)
    {
        // Update list of persistent-objects
        $objects_list = $this->load_objects_list();
        unset($objects_list[$key]);
        //$this->set(get_file_base() . 'PERSISTENT_CACHE_OBJECTS', $objects_list, 0, 0); Wasteful

        parent::delete($key);
    }

    /**
     * Remove all data from the persistent cache.
     */
    public function flush()
    {
        // Update list of persistent-objects
        $objects_list = array();
        $this->set(get_file_base() . 'PERSISTENT_CACHE_OBJECTS', $objects_list, 0, 0);

        parent::flush();
    }
}
