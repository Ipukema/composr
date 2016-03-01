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
 * @package    news
 */

/**
 * Hook class.
 */
class Hook_sitemap_news_category extends Hook_sitemap_content
{
    protected $content_type = 'news_category';
    protected $screen_type = 'browse';

    // If we have a different content type of entries, under this content type
    protected $entry_content_type = array('news');
    protected $entry_sitetree_hook = array('news');

    /**
     * Get the permission page that nodes matching $page_link in this hook are tied to.
     * The permission page is where privileges may be overridden against.
     *
     * @param  string $page_link The page-link
     * @return ?ID_TEXT The permission page (null: none)
     */
    public function get_privilege_page($page_link)
    {
        return 'cms_news';
    }

    /**
     * Find details of a virtual position in the sitemap. Virtual positions have no structure of their own, but can find child structures to be absorbed down the tree. We do this for modularity reasons.
     *
     * @param  ID_TEXT $page_link The page-link we are finding.
     * @param  ?string $callback Callback function to send discovered page-links to (null: return).
     * @param  ?array $valid_node_types List of node types we will return/recurse-through (null: no limit)
     * @param  ?integer $child_cutoff Maximum number of children before we cut off all children (null: no limit).
     * @param  ?integer $max_recurse_depth How deep to go from the sitemap root (null: no limit).
     * @param  integer $recurse_level Our recursion depth (used to limit recursion, or to calculate importance of page-link, used for instance by Google sitemap [deeper is typically less important]).
     * @param  integer $options A bitmask of SITEMAP_GEN_* options.
     * @param  ID_TEXT $zone The zone we will consider ourselves to be operating in (needed due to transparent redirects feature)
     * @param  integer $meta_gather A bitmask of SITEMAP_GATHER_* constants, of extra data to include.
     * @param  boolean $return_anyway Whether to return the structure even if there was a callback. Do not pass this setting through via recursion due to memory concerns, it is used only to gather information to detect and prevent parent/child duplication of default entry points.
     * @return ?array List of node structures (null: working via callback).
     */
    public function get_virtual_nodes($page_link, $callback = null, $valid_node_types = null, $child_cutoff = null, $max_recurse_depth = null, $recurse_level = 0, $options = 0, $zone = '_SEARCH', $meta_gather = 0, $return_anyway = false)
    {
        $nodes = ($callback === null || $return_anyway) ? array() : mixed();

        if (($valid_node_types !== null) && (!in_array($this->content_type, $valid_node_types))) {
            return $nodes;
        }

        $page = $this->_make_zone_concrete($zone, $page_link);

        if ($child_cutoff !== null) {
            $count = $GLOBALS['SITE_DB']->query_select_value('news_categories', 'COUNT(*)');
            if ($count > $child_cutoff) {
                return $nodes;
            }
        }

        $start = 0;
        do {
            $rows = $GLOBALS['SITE_DB']->query_select('news_categories', array('*'), null, '', SITEMAP_MAX_ROWS_PER_LOOP, $start);
            foreach ($rows as $row) {
                $child_page_link = $zone . ':' . $page . ':' . $this->screen_type . ':select=' . strval($row['id']);
                if (strpos($page_link, ':blog=0') !== false) {
                    $child_page_link .= ':blog=0';
                }
                if (strpos($page_link, ':blog=1') !== false) {
                    $child_page_link .= ':blog=1';
                }
                $node = $this->get_node($child_page_link, $callback, $valid_node_types, $child_cutoff, $max_recurse_depth, $recurse_level, $options, $zone, $meta_gather, $row);
                if (($callback === null || $return_anyway) && ($node !== null)) {
                    $nodes[] = $node;
                }
            }

            $start += SITEMAP_MAX_ROWS_PER_LOOP;
        } while (count($rows) == SITEMAP_MAX_ROWS_PER_LOOP);

        if (is_array($nodes)) {
            sort_maps_by($nodes, 'title');
        }

        return $nodes;
    }

    /**
     * Find details of a position in the Sitemap.
     *
     * @param  ID_TEXT $page_link The page-link we are finding.
     * @param  ?string $callback Callback function to send discovered page-links to (null: return).
     * @param  ?array $valid_node_types List of node types we will return/recurse-through (null: no limit)
     * @param  ?integer $child_cutoff Maximum number of children before we cut off all children (null: no limit).
     * @param  ?integer $max_recurse_depth How deep to go from the Sitemap root (null: no limit).
     * @param  integer $recurse_level Our recursion depth (used to limit recursion, or to calculate importance of page-link, used for instance by XML Sitemap [deeper is typically less important]).
     * @param  integer $options A bitmask of SITEMAP_GEN_* options.
     * @param  ID_TEXT $zone The zone we will consider ourselves to be operating in (needed due to transparent redirects feature)
     * @param  integer $meta_gather A bitmask of SITEMAP_GATHER_* constants, of extra data to include.
     * @param  ?array $row Database row (null: lookup).
     * @param  boolean $return_anyway Whether to return the structure even if there was a callback. Do not pass this setting through via recursion due to memory concerns, it is used only to gather information to detect and prevent parent/child duplication of default entry points.
     * @return ?array Node structure (null: working via callback / error).
     */
    public function get_node($page_link, $callback = null, $valid_node_types = null, $child_cutoff = null, $max_recurse_depth = null, $recurse_level = 0, $options = 0, $zone = '_SEARCH', $meta_gather = 0, $row = null, $return_anyway = false)
    {
        $_page_link = str_replace(':select=', ':', $page_link);

        $_ = $this->_create_partial_node_structure($_page_link, $callback, $valid_node_types, $child_cutoff, $max_recurse_depth, $recurse_level, $options, $zone, $meta_gather, $row);
        if ($_ === null) {
            return null;
        }
        list($content_id, $row, $partial_struct) = $_;

        $partial_struct['page_link'] = $page_link;

        $matches = array();
        preg_match('#^([^:]*):([^:]*)#', $page_link, $matches);
        $page = $matches[2];

        $this->_make_zone_concrete($zone, $page_link);

        $struct = array(
                      'sitemap_priority' => SITEMAP_IMPORTANCE_HIGH,
                      'sitemap_refreshfreq' => 'daily',

                      'privilege_page' => $this->get_privilege_page($page_link),

                      'edit_url' => build_url(array('page' => 'cms_news', 'type' => '_edit_category', 'id' => $content_id), get_module_zone('cms_news')),
                  ) + $partial_struct;

        if (!$this->_check_node_permissions($struct)) {
            return null;
        }

        if ($callback !== null) {
            call_user_func($callback, $struct);
        }

        // Categories done after node callback, to ensure sensible ordering
        $children = $this->_get_children_nodes($content_id, $page_link, $callback, $valid_node_types, $child_cutoff, $max_recurse_depth, $recurse_level, $options, $zone, $meta_gather, $row);
        if (!is_null($children)) {
            foreach ($children as &$child) {
                if (strpos($page_link, ':blog=0') !== false) {
                    $child['page_link'] .= ':blog=0';
                }
                if (strpos($page_link, ':blog=1') !== false) {
                    $child['page_link'] .= ':blog=1';
                }
            }
            if (($options & SITEMAP_GEN_CONSIDER_SECONDARY_CATEGORIES) != 0) {
                require_code('hooks/systems/sitemap/news');
                $child_hook_ob = object_factory('Hook_sitemap_news');

                $skip_children = false;
                if ($child_cutoff !== null) {
                    $count = $GLOBALS['SITE_DB']->query_select_value('news_category_entries', 'COUNT(*)', array('news_entry_category' => intval($content_id)));
                    if ($count > $child_cutoff) {
                        $skip_children = true;
                    }
                }

                if (!$skip_children) {
                    $child_rows = $GLOBALS['SITE_DB']->query_select('news_category_entries', array('news_entry'), array('news_entry_category' => intval($content_id)));
                    foreach ($child_rows as $child_row) {
                        $child_page_link = $zone . ':' . $page . ':view:' . strval($child_row['news_entry']);
                        $child_node = $child_hook_ob->get_node($child_page_link, $callback, $valid_node_types, $child_cutoff, $max_recurse_depth, $recurse_level + 1, $options, $zone, $meta_gather);
                        if ($child_node !== null) {
                            $children[] = $child_node;
                        }
                    }
                }
            }
            $struct['children'] = $children;
        }

        return ($callback === null || $return_anyway) ? $struct : null;
    }
}
