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
 * @package    shopping
 */

/**
 * Module page class.
 */
class Module_shopping
{
    /**
     * Find details of the module.
     *
     * @return ?array Map of module info (null: module is disabled).
     */
    public function info()
    {
        $info = array();
        $info['author'] = 'Manuprathap';
        $info['organisation'] = 'ocProducts';
        $info['hacked_by'] = null;
        $info['hack_version'] = null;
        $info['version'] = 8;
        $info['update_require_upgrade'] = true;
        $info['locked'] = false;
        return $info;
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_cart');
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_order_details');
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_order');
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_logging');
        $GLOBALS['SITE_DB']->drop_table_if_exists('ecom_trans_addresses');

        $GLOBALS['SITE_DB']->query_delete('group_category_access', array('module_the_name' => 'shopping'));

        require_code('menus2');
        delete_menu_item_simple('_SEARCH:catalogues:index:products');
    }

    /**
     * Install the module.
     *
     * @param  ?integer $upgrade_from What version we're upgrading from (null: new install)
     * @param  ?integer $upgrade_from_hack What hack version we're upgrading from (null: new-install/not-upgrading-from-a-hacked-version)
     */
    public function install($upgrade_from = null, $upgrade_from_hack = null)
    {
        if ($upgrade_from === null) {
            $GLOBALS['SITE_DB']->create_table('shopping_cart', array(
                'id' => '*AUTO',
                'session_id' => 'ID_TEXT',
                'ordered_by' => 'MEMBER',
                'type_code' => 'ID_TEXT',
                'purchase_id' => 'ID_TEXT',
                'quantity' => 'INTEGER',
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_cart', 'ordered_by', array('ordered_by'));
            $GLOBALS['SITE_DB']->create_index('shopping_cart', 'session_id', array('session_id'));
            $GLOBALS['SITE_DB']->create_index('shopping_cart', 'type_code', array('type_code'));

            // Cart contents turns into order + details...

            $GLOBALS['SITE_DB']->create_table('shopping_order', array(
                'id' => '*AUTO',
                'session_id' => 'ID_TEXT',
                'member_id' => 'MEMBER',
                'add_date' => 'TIME',
                'total_price' => 'REAL',
                'total_tax' => 'REAL',
                'total_shipping_cost' => 'REAL',
                'order_status' => 'ID_TEXT', // ORDER_STATUS_[awaiting_payment|payment_received|onhold|dispatched|cancelled|returned]
                'notes' => 'LONG_TEXT',
                'txn_id' => 'SHORT_TEXT',
                'purchase_through' => 'SHORT_TEXT', // cart|purchase_module
                'tax_opted_out' => 'BINARY',
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_order', 'finddispatchable', array('order_status'));
            $GLOBALS['SITE_DB']->create_index('shopping_order', 'somember_id', array('member_id'));
            $GLOBALS['SITE_DB']->create_index('shopping_order', 'sosession_id', array('session_id'));
            $GLOBALS['SITE_DB']->create_index('shopping_order', 'soadd_date', array('add_date'));

            $GLOBALS['SITE_DB']->create_table('shopping_order_details', array( // individual products in an order
                'id' => '*AUTO',
                'p_order_id' => '?AUTO_LINK',
                'p_type_code' => 'ID_LINK',
                'p_purchase_id' => 'ID_TEXT',
                'p_name' => 'SHORT_TEXT',
                'p_sku' => 'SHORT_TEXT',
                'p_quantity' => 'INTEGER',
                'p_price' => 'REAL',
                'p_tax' => 'REAL',
                'p_included_tax' => 'REAL',
                'p_dispatch_status' => 'SHORT_TEXT'
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_order_details', 'type_code', array('p_type_code'));
            $GLOBALS['SITE_DB']->create_index('shopping_order_details', 'order_id', array('p_order_id'));

            $GLOBALS['SITE_DB']->create_table('shopping_logging', array(
                'id' => '*AUTO',
                'l_member_id' => '*MEMBER',
                'l_session_id' => 'ID_TEXT',
                'l_ip' => 'IP',
                'l_last_action' => 'SHORT_TEXT',
                'l_date_and_time' => 'TIME'
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_logging', 'cart_log', array('l_date_and_time'));
        }

        if (($upgrade_from !== null) && ($upgrade_from < 7)) {
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order', 'session_id', 'ID_TEXT');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_cart', 'session_id', 'ID_TEXT');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_logging', 'l_session_id', 'ID_TEXT');

            $GLOBALS['SITE_DB']->change_primary_key('shopping_cart', array('id'));

            $GLOBALS['SITE_DB']->delete_index_if_exists('shopping_order', 'recent_shopped');
        }

        if (($upgrade_from !== null) && ($upgrade_from < 8)) {
            $GLOBALS['SITE_DB']->drop_table_field('shopping_cart', 'price');
            $GLOBALS['SITE_DB']->drop_table_field('shopping_cart', 'price_pre_tax');
            $GLOBALS['SITE_DB']->drop_table_field('shopping_cart', 'product_name');
            $GLOBALS['SITE_DB']->drop_table_field('shopping_cart', 'product_code');
            $GLOBALS['SITE_DB']->drop_table_field('shopping_cart', 'product_description');
            $GLOBALS['SITE_DB']->drop_table_field('shopping_cart', 'product_type');
            $GLOBALS['SITE_DB']->drop_table_field('shopping_cart', 'product_weight');
            $GLOBALS['SITE_DB']->drop_table_field('shopping_cart', 'is_deleted');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_cart', 'product_id', 'ID_TEXT', 'type_code');

            $GLOBALS['SITE_DB']->alter_table_field('shopping_order', 'tot_price', 'REAL', 'total_price');
            $GLOBALS['SITE_DB']->add_table_field('shopping_order', 'total_tax', 'REAL');
            $GLOBALS['SITE_DB']->add_table_field('shopping_order', 'total_shipping_cost', 'REAL');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order', 'c_member', 'MEMBER', 'member_id');

            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_details', 'p_price', 'REAL', 'p_price');
            $GLOBALS['SITE_DB']->add_table_field('shopping_order_details', 'p_tax', 'REAL');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_details', 'order_id', '?AUTO_LINK', 'p_order_id');
            $GLOBALS['SITE_DB']->delete_table_field('shopping_order_details', 'included_tax');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_details', 'dispatch_status', 'SHORT_TEXT', 'p_dispatch_status');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_details', 'p_id', 'ID_TEXT', 'p_type_code');
            $GLOBALS['SITE_DB']->delete_table_field('shopping_order_details', 'p_type');
            $GLOBALS['SITE_DB']->add_table_field('shopping_order_details', 'p_purchase_id', 'ID_TEXT');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_details', 'p_code', 'SHORT_TEXT', 'p_sku');

            $GLOBALS['SITE_DB']->alter_table_field('shopping_logging', 'e_member_id', '*MEMBER', 'l_member_id');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_logging', 'session_id', 'ID_TEXT', 'l_session_id');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_logging', 'ip', 'IP', 'l_ip');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_logging', 'last_action', 'SHORT_TEXT', 'l_last_action');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_logging', 'date_and_time', 'TIME', 'l_date_and_time');

            $GLOBALS['SITE_DB']->drop_table_if_exists('ecom_trans_addresses');
        }
    }

    /**
     * Find entry-points available within this module.
     *
     * @param  boolean $check_perms Whether to check permissions.
     * @param  ?MEMBER $member_id The member to check permissions as (null: current user).
     * @param  boolean $support_crosslinks Whether to allow cross links to other modules (identifiable via a full-page-link rather than a screen-name).
     * @param  boolean $be_deferential Whether to avoid any entry-point (or even return null to disable the page in the Sitemap) if we know another module, or page_group, is going to link to that entry-point. Note that "!" and "browse" entry points are automatically merged with container page nodes (likely called by page-groupings) as appropriate.
     * @return ?array A map of entry points (screen-name=>language-code/string or screen-name=>[language-code/string, icon-theme-image]) (null: disabled).
     */
    public function get_entry_points($check_perms = true, $member_id = null, $support_crosslinks = true, $be_deferential = false)
    {
        if (get_forum_type() != 'cns') {
            return null;
        }

        $ret = array(
            'browse' => array('SHOPPING', 'menu/rich_content/ecommerce/shopping_cart'),
        );
        if (!$check_perms || !is_guest($member_id)) {
            $ret += array(
                'my_orders' => array('MY_ORDERS', 'menu/rich_content/ecommerce/orders'),
            );
        }
        return $ret;
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('shopping');
        require_lang('catalogues');

        $ecom_catalogue_count = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'COUNT(*)', array('c_ecommerce' => 1));
        $ecom_catalogue = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_ecommerce' => 1));
        $ecom_catalogue_id = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogue_categories', 'MIN(id)', array('c_name' => $ecom_catalogue));

        if ($type == 'browse') {
            if ($ecom_catalogue_count == 1) {
                breadcrumb_set_parents(array(array('_SELF:catalogues:category:=' . $ecom_catalogue_id, do_lang_tempcode('DEFAULT_CATALOGUE_PRODUCTS_TITLE'))));
            } else {
                breadcrumb_set_parents(array(array('_SELF:catalogues:browse:ecommerce=1', do_lang_tempcode('CATALOGUES'))));
            }

            $this->title = get_screen_title('SHOPPING');
        }

        if ($type == 'add_item') {
            $this->title = get_screen_title('SHOPPING');
        }

        if ($type == 'update_cart') {
            $this->title = get_screen_title('SHOPPING');
        }

        if ($type == 'empty_cart') {
            $this->title = get_screen_title('SHOPPING');
        }

        if ($type == 'my_orders') {
            $this->title = get_screen_title('MY_ORDERS');
        }

        if ($type == 'order_details') {
            breadcrumb_set_parents(array(array('_SELF:orders:browse', do_lang_tempcode('MY_ORDERS'))));

            $id = get_param_integer('id');
            $this->title = get_screen_title('_ORDER_DETAILS', true, array(escape_html($id)));
        }

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        @ignore_user_abort(true); // Must keep going till completion

        require_code('shopping');
        require_code('feedback');
        require_code('ecommerce');

        if (get_forum_type() != 'cns') {
            warn_exit(do_lang_tempcode('NO_CNS'));
        }

        // Kill switch
        if ((ecommerce_test_mode()) && (!$GLOBALS['IS_ACTUALLY_ADMIN']) && (!has_privilege(get_member(), 'access_ecommerce_in_test_mode'))) {
            warn_exit(do_lang_tempcode('PURCHASE_DISABLED'));
        }

        $GLOBALS['NO_QUERY_LIMIT'] = true;

        $type = get_param_string('type', 'browse');

        delete_incomplete_orders();

        if ($type == 'browse') {
            return $this->view_shopping_cart();
        }
        if ($type == 'add_item') {
            return $this->add_item();
        }
        if ($type == 'update_cart') {
            return $this->update_cart();
        }
        if ($type == 'empty_cart') {
            return $this->empty_cart();
        }
        if ($type == 'my_orders') {
            return $this->my_orders();
        }
        if ($type == 'order_details') {
            return $this->order_details();
        }

        return new Tempcode();
    }

    /**
     * The UI to show shopping cart.
     *
     * @return Tempcode The UI.
     */
    public function view_shopping_cart()
    {
        require_code('templates_results_table');
        require_code('form_templates');
        require_css('shopping');
        require_css('ecommerce');
        require_javascript('shopping');

        $shopping_cart_rows = find_products_in_cart();
        $max_rows = count($shopping_cart_rows);

        $type_codes = array();

        if ($max_rows > 0) {
            $shopping_cart = new Tempcode();

            $fields_title = results_field_title(array(
                '',
                do_lang_tempcode('PRODUCT'),
                do_lang_tempcode('UNIT_PRICE'),
                do_lang_tempcode('QUANTITY'),
                do_lang_tempcode('PRICE'),
                do_lang_tempcode(get_option('tax_system')),
                do_lang_tempcode('AMOUNT'),
                do_lang_tempcode('REMOVE')
            ), null);

            foreach ($shopping_cart_rows as $item) {
                list($details, , $product_object) = find_product_details($item['p_type_code']);

                if ($details === null) {
                    $GLOBALS['SITE_DB']->query_delete('shopping_cart', array('id' => $item['id']), '', 1);
                    continue;
                }

                $type_codes[] = $item['p_type_code']);

                $this->show_cart_entry($shopping_cart, $details, $item);
            }

            list($total_price, $total_tax, $total_shipping_cost) = derive_cart_amounts($shopping_cart_rows);

            $results_table = results_table(do_lang_tempcode('SHOPPING'), 0, 'cart_start', $max_rows, 'cart_max', $max_rows, $fields_title, $shopping_cart, null, null, null, 'sort', null, null, 'cart');

            $update_cart_url = build_url(array('page' => '_SELF', 'type' => 'update_cart'), '_SELF');
            $empty_cart_url = build_url(array('page' => '_SELF', 'type' => 'empty_cart'), '_SELF');

            $fields = null;
            ecommerce_attach_memo_field_if_needed($fields);

            $next_url = build_url(array('page' => 'purchase', 'type' => 'pay', 'type_code' => 'CART_ORDER'), get_module_zone('purchase'));
        } else {
            $total_price = 0.00;
            $total_tax = 0.00;
            $total_shipping_cost = 0.00;

            $results_table = do_lang_tempcode('CART_EMPTY');

            $update_cart_url = new Tempcode();
            $empty_cart_url = new Tempcode();

            $fields = new Tempcode();
            $next_url = new Tempcode();
        }

        $grand_total = $total_price + $total_tax + $total_shipping_cost;

        $ecom_catalogue_count = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'COUNT(*)', array('c_ecommerce' => 1));
        $ecom_catalogue = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_ecommerce' => 1));
        $ecom_catalogue_id = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogue_categories', 'MIN(id)', array('c_name' => $ecom_catalogue));
        if ($ecom_catalogue_count == 1) {
            $continue_shopping_url = build_url(array('page' => 'catalogues', 'type' => 'category', 'id' => $ecom_catalogue_id), get_module_zone('catalogues'));
        } else {
            $continue_shopping_url = build_url(array('page' => 'catalogues', 'type' => 'browse', 'ecommerce' => 1), get_module_zone('catalogues'));
        }

        log_cart_actions(do_lang('VIEW_CART'));

        $tpl = do_template('ECOM_SHOPPING_CART_SCREEN', array(
            '_GUID' => 'badff09daf52ee1c84b472c44be1bfae',
            'TITLE' => $this->title,
            'RESULTS_TABLE' => $results_table,
            'UPDATE_CART_URL' => $update_cart_url,
            'CONTINUE_SHOPPING_URL' => $continue_shopping_url,
            'MESSAGE' => '',
            'TYPE_CODES' => implode(',', array_unique($type_codes)),
            'EMPTY_CART_URL' => $empty_cart_url,
            'TOTAL_PRICE' => float_format($total_price),
            'TOTAL_TAX' => float_format($total_tax),
            'TOTAL_SHIPPING_COST' => float_format($total_shipping_cost),
            'GRAND_TOTAL' => float_format($grand_total),
            'CURRENCY' => ecommerce_get_currency_symbol(),
            'FIELDS' => $fields,
            'NEXT_URL' => $next_url,
        ));

        require_code('templates_internalise_screen');
        return internalise_own_screen($tpl);
    }

    /**
     * Produce a results table row for a particular shopping cart entry.
     *
     * @param  Tempcode $shopping_cart Tempcode object of shopping cart result table.
     * @param  array $details Product details.
     * @param  array $item Cart row.
     */
    protected function show_cart_entry(&$shopping_cart, $details, $item)
    {
        $tpl_set = 'cart';

        $edit_quantity_link = do_template('ECOM_SHOPPING_ITEM_QUANTITY_FIELD', array(
            'TYPE_CODE' => $item['p_type_code'],
            'QUANTITY' => strval($item['quantity'])
        ));

        $delete_item_link = do_template('ECOM_SHOPPING_ITEM_REMOVE_FIELD', array(
            'TYPE_CODE' => $item['p_type_code'],
        ));

        require_code('images');
        $product_image = do_image_thumb($details['item_image_url'], $details['item_name'], $details['item_name'], false, 50, 50);

        $currency = ecommerce_get_currency_symbol();
        $price_singular = $details['price'];
        $price_multiple = $details['price'] * $item['quantity'];
        $tax = recalculate_tax_due($details, $details['tax'], 0.0, null, $item['quantity']);
        $amount = $price_multiple + $tax;

        $product_det_url = get_product_det_url($item['p_type_code'], false, get_member());
        $product_link = hyperlink($product_det_url, $details['item_name'], false, true, do_lang('INDEX'));

        require_code('templates_results_table');
        $shopping_cart->attach(results_entry(array(
            $product_image,
            $product_link,
            $currency . escape_html(float_format($price_singular)),
            $edit_quantity_link,
            $currency . escape_html(float_format($price_multiple)),
            $currency . escape_html(float_format($tax)),
            $currency . escape_html(float_format($amount)),
            $delete_item_link,
        ), false, $tpl_set));
    }

    /**
     * Add an item to the cart.
     *
     * @return Tempcode The UI.
     */
    public function add_item()
    {
        if (is_guest()) {
            require_code('users_inactive_occasionals');
            set_session_id(get_session_id(), true); // Persist guest sessions longer
        }

        $prefix = either_param_string('prefix', '');
        $type_code = $prefix . either_param_string('type_code');

        $purchase_id = either_param_string('purchase_id', '');

        $quantity = either_param_integer('quantity', 1);

        add_to_cart($type_code, $purchase_id, $quantity);

        log_cart_actions(do_lang('ADD_TO_CART'));

        $cart_view = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');
        return redirect_screen($this->title, $cart_view, do_lang_tempcode('ADDED_TO_CART'));
    }

    /**
     * Update the cart, editing quantities and deleting items.
     *
     * @return Tempcode The UI.
     */
    public function update_cart()
    {
        $product_to_remove = array();

        $products_in_cart = array();

        $type_codes = explode(',', post_param_string('type_codes'));
        foreach ($type_codes as $type_code) {
            $quantity = post_param_integer('quantity_' . $type_code);

            list($details, , $product_object) = find_product_details($type_code);

            $remove = (post_param_integer('remove_' . $type_code, 0) == 1);
            if ($remove) {
                $product_to_remove[] = $type_code;
            } else {
                if (method_exists($product_object, 'get_available_quantity')) {
                    $available_quantity = $product_object->get_available_quantity($type_code, false);
                    if (($available_qty !== null) && ($available_qty <= $quantity)) {
                        $quantity = $available_quantity;

                        attach_message(do_lang_tempcode('PRODUCT_QUANTITY_CHANGED', escape_html($details['item_name'])), 'warn');
                    }
                }

                $products_in_cart[] = array('type_code' => $type_code, 'quantity' => $quantity);
            }
        }

        update_cart($products_in_cart);

        if (count($product_to_remove) > 0) {
            remove_from_cart($product_to_remove);
        }

        log_cart_actions(do_lang('UPDATE_CART'));

        $cart_view = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');
        return redirect_screen($this->title, $cart_view, do_lang_tempcode('CART_UPDATED'));
    }

    /**
     * Empty the shopping cart.
     *
     * @return Tempcode The UI.
     */
    public function empty_cart()
    {
        empty_cart(true);

        log_cart_actions(do_lang('EMPTY_CART'));

        $cart_view = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');
        return redirect_screen($this->title, $cart_view, do_lang_tempcode('CART_EMPTIED'));
    }

    /**
     * Show all my orders
     *
     * @return Tempcode The interface.
     */
    public function my_orders()
    {
        if (is_guest()) {
            access_denied('NOT_AS_GUEST');
        }

        $member_id = get_member();
        if (has_privilege(get_member(), 'assume_any_member')) {
            $member_id = get_param_integer('id', $member_id);
        }

        $orders = array();

        $rows = $GLOBALS['SITE_DB']->query_select('shopping_order o LEFT JOIN ' . get_table_prefix() . 'ecom_transactions t ON t.id=o.txn_id', array('*', 'o.id AS id', 't.id AS t_id'), array('member_id' => $member_id), 'ORDER BY add_date');

        foreach ($rows as $row) {
            $order_details_url = build_url(array('page' => '_SELF', 'type' => 'order_details', 'id' => $row['o_id']), '_SELF');

            if ($row['purchase_through'] == 'cart') {
                $order_title = do_lang('CART_ORDER', strval($row['o_id']));
            } else {
                $order_title = do_lang('PURCHASE_ORDER', strval($row['o_id']));
            }

            $transaction_linker = build_transaction_linker($row['txn_id'], $row['order_status'] == 'ORDER_STATUS_awaiting_payment', $row);

            $orders[] = array(
                'ORDER_TITLE' => $order_title,
                'ID' => strval($row['o_id']),
                'TXN_ID' => $row['txn_id'],
                'TRANSACTION_LINKER' => $transaction_linker,
                'TOTAL_PRICE' => float_format($row['total_price']),
                'TOTAL_TAX' => float_format($row['total_tax']),
                'TOTAL_SHIPPING_COST' => float_format($row['total_shipping_cost']),
                'TIME' => get_timezoned_date($row['add_date'], true, false, true, true),
                'STATUS' => do_lang_tempcode($row['order_status']),
                'NOTE' => '',
                'ORDER_DET_URL' => $order_details_url,
                'DELIVERABLE' => '',
            );
        }

        if (count($orders) == 0) {
            inform_exit(do_lang_tempcode('NO_ENTRIES'));
        }

        return do_template('ECOM_ORDERS_SCREEN', array('_GUID' => '79eb5f17cf4bc2dc4f0cccf438261c73', 'TITLE' => $this->title, 'CURRENCY' => get_option('currency'), 'ORDERS' => $orders));
    }

    /**
     * Show an order details
     *
     * @return Tempcode The interface.
     */
    public function order_details()
    {
        if (is_guest()) {
            access_denied('NOT_AS_GUEST');
        }

        $id = get_param_integer('id');

        if (!has_privilege(get_member(), 'assume_any_member')) {
            $member_id = $GLOBALS['SITE_DB']->query_select_value_if_there('shopping_order', 'member_id', array('id' => $id));
            if ($member_id === null) {
                warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
            }

            if ($member_id != get_member()) {
                access_denied('I_ERROR');
            }
        }

        require_code('ecommerce_logs');
        return build_order_details($this->title, $id, new Tempcode());
    }
}
