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
class Module_admin_orders
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
        $info['version'] = 2;
        $info['locked'] = false;
        return $info;
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
        if ($be_deferential || $support_crosslinks) {
            return null;
        }

        $ret = array(
            'browse' => array('ORDERS', 'menu/adminzone/audit/ecommerce/orders'),
            'show_orders' => array('SHOW_ORDERS', 'menu/adminzone/audit/ecommerce/orders'),
        );

        if ($support_crosslinks) {
            $ret['_SEARCH:admin_orders:show_orders:filter=undispatched'] = array('SHOW_UNDISPATCHED_ORDERS', 'menu/adminzone/audit/ecommerce/undispatched_orders');
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

        require_lang('ecommerce');
        require_lang('shopping');

        if ($type == 'browse') {
            breadcrumb_set_self(do_lang_tempcode('ORDERS'));
            breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce_logs:browse', do_lang_tempcode('ECOMMERCE'))));
        }

        if ($type == 'show_orders') {
            breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce_logs:browse', do_lang_tempcode('ECOMMERCE')), array('_SELF:_SELF:browse', do_lang_tempcode('ORDERS'))));

            $filter = get_param_string('filter', null);
            if ($filter == 'undispatched') {
                $this->title = get_screen_title('SHOW_UNDISPATCHED_ORDERS');
            } else {
                $this->title = get_screen_title('SHOW_ORDERS');
            }
        }

        $action = either_param_string('action', '');

        if ($type == 'order_det' || $action == 'order_act' || $action == '_add_note' || $action == 'order_export' || $action == '_order_export') {
            breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce_logs:browse', do_lang_tempcode('ECOMMERCE')), array('_SELF:_SELF:browse', do_lang_tempcode('ORDERS')), array('_SELF:_SELF:show_orders', do_lang_tempcode('ORDERS'))));
        }

        if ($action == 'order_act') {
            if ($action != 'add_note') {
                breadcrumb_set_self(do_lang_tempcode('DONE'));
            }
        }

        if ($action == '_add_note' || $action == '_order_export') {
            breadcrumb_set_self(do_lang_tempcode('DONE'));
        }

        if ($type == 'order_det') {
            $this->title = get_screen_title('MY_ORDER_DETAILS');
        }

        if ($type == 'order_export') {
            $this->title = get_screen_title('EXPORT_ORDER_LIST');
        }

        if ($type == 'order_act') {
            $action = either_param_string('action');

            if ($action == 'add_note') {
                $id = get_param_integer('id');
                $this->title = get_screen_title('ADD_NOTE_TITLE', true, array(escape_html(strval($id))));
            }

            if ($action == 'dispatch') {
                $this->title = get_screen_title('ORDER_STATUS_dispatched');
            }

            if ($action == 'del_order') {
                $this->title = get_screen_title('ORDER_STATUS_cancelled');
            }

            if ($action == 'return') {
                $this->title = get_screen_title('ORDER_STATUS_returned');
            }

            if ($action == 'hold') {
                $this->title = get_screen_title('ORDER_STATUS_onhold');
            }
        }

        if ($type == '_add_note') {
            $id = post_param_integer('order_id');
            $this->title = get_screen_title('ADD_NOTE_TITLE', true, array(escape_html($id)));
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
        require_code('ecommerce');
        require_javascript('shopping');
        require_css('shopping');
        require_code('users_active_actions');

        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            return $this->browse();
        }
        if ($type == 'show_orders') {
            return $this->show_orders();
        }
        if ($type == 'order_det') {
            return $this->order_details();
        }
        if ($type == 'order_act') {
            $action = either_param_string('action');

            if ($action == 'add_note') {
                return $this->add_note();
            }
            if ($action == 'dispatch') {
                return $this->dispatch();
            }
            if ($action == 'del_order') {
                return $this->delete_order();
            }
            if ($action == 'return') {
                return $this->return_order();
            }
            if ($action == 'hold') {
                return $this->hold_order();
            }
        }
        if ($type == '_add_note') {
            return $this->_add_note();
        }
        if ($type == 'order_export') {
            return $this->order_export();
        }
        if ($type == '_order_export') {
            $this->_order_export();
        }

        return new Tempcode();
    }

    /**
     * The do-next manager for order module.
     *
     * @return Tempcode The UI
     */
    public function browse()
    {
        require_code('templates_donext');
        return do_next_manager(get_screen_title('ORDERS'), comcode_lang_string('DOC_ECOMMERCE'),
            array(
                array('menu/adminzone/audit/ecommerce/orders', array('_SELF', array('type' => 'show_orders'), '_SELF'), do_lang('SHOW_ORDERS')),
                array('menu/adminzone/audit/ecommerce/undispatched_orders', array('_SELF', array('type' => 'show_orders', 'filter' => 'undispatched'), '_SELF'), do_lang('SHOW_UNDISPATCHED_ORDERS')),
            ),
            do_lang('ORDERS')
        );
    }

    /**
     * UI to show all orders.
     *
     * @return Tempcode The interface.
     */
    public function show_orders()
    {
        require_code('shopping');

        $filter = get_param_string('filter', null);
        $search = get_param_string('search', '', true);

        $cond = 'WHERE 1=1';

        if ($filter == 'undispatched') {
            $cond .= ' AND ' . db_string_equal_to('t1.order_status', 'ORDER_STATUS_payment_received');
        }

        $extra_join = '';
        if ((!is_null($search)) && ($search != '')) {
            $GLOBALS['NO_DB_SCOPE_CHECK'] = true;

            $cond .= ' AND (t1.id LIKE \'' . db_encode_like(str_replace('#', '', $search) . '%') . '\' OR t2.m_username LIKE \'' . db_encode_like(str_replace('#', '', $search) . '%') . '\')';
            $extra_join = ' JOIN ' . get_table_prefix() . 'f_members t2 ON t2.id=t1.c_member';
        }

        $start = get_param_integer('start', 0);
        $max = get_param_integer('max', 10);

        require_code('templates_results_table');

        $sortables = array(
            't1.id' => do_lang_tempcode('ECOM_ORDER'),
            't1.add_date' => do_lang_tempcode('ORDERED_DATE'),
            't1.c_member' => do_lang_tempcode('ORDERED_BY'),
            't1.tot_price' => do_lang_tempcode('ORDER_PRICE_AMT'),
            't3.included_tax' => do_lang_tempcode('TAX_PAID'),
            't1.order_status' => do_lang_tempcode('STATUS'),
            't1.transaction_id' => do_lang_tempcode('TRANSACTION_ID'),
        );

        $query_sort = explode(' ', get_param_string('sort', 't1.add_date ASC'), 2);
        if (count($query_sort) == 1) {
            $query_sort[] = 'ASC';
        }
        list($sortable, $sort_order) = $query_sort;
        if (((strtoupper($sort_order) != 'ASC') && (strtoupper($sort_order) != 'DESC')) || (!array_key_exists($sortable, $sortables))) {
            log_hack_attack_and_exit('ORDERBY_HACK');
        }

        $fields_title = results_field_title(
            array(
                do_lang_tempcode('ECOM_ORDER'),
                do_lang_tempcode('THE_PRICE'),
                do_lang_tempcode('TAX_PAID'),
                do_lang_tempcode('ORDERED_DATE'),
                do_lang_tempcode('ORDERED_BY'),
                do_lang_tempcode('TRANSACTION_ID'),
                do_lang_tempcode('STATUS'),
                do_lang_tempcode('ACTIONS')
            ), $sortables, 'sort', $sortable . ' ' . $sort_order
        );

        global $NO_DB_SCOPE_CHECK;
        $NO_DB_SCOPE_CHECK = true;

        $rows = $GLOBALS['SITE_DB']->query('SELECT t1.*,(t3.p_quantity*t3.included_tax) as tax FROM ' . get_table_prefix() . 'shopping_order t1' . $extra_join . ' LEFT JOIN ' . get_table_prefix() . 'shopping_order_details t3 ON t1.id=t3.order_id ' . $cond . ' GROUP BY t1.id ORDER BY ' . db_string_equal_to('t1.order_status', 'ORDER_STATUS_cancelled') . ',' . $sortable . ' ' . $sort_order, $max, $start, false, true);
        $order_entries = new Tempcode();
        foreach ($rows as $row) {
            if ($row['purchase_through'] == 'cart') {
                $view_url = build_url(array('page' => '_SELF', 'type' => 'order_det', 'id' => $row['id']), '_SELF');

                $order_title = do_lang('CART_ORDER', strval($row['id']));
            } else {
                $res = $GLOBALS['SITE_DB']->query_select('shopping_order_details', array('p_id', 'p_name'), array('order_id' => $row['id']));

                if (!array_key_exists(0, $res)) {
                    continue; // DB corruption
                }
                $product_det = $res[0];

                $view_url = build_url(array('page' => 'catalogues', 'type' => 'entry', 'id' => $product_det['p_id']), get_module_zone('catalogues'));

                $order_title = do_lang('PURCHASE_ORDER', strval($row['id']));
            }

            $order_status = do_lang($row['order_status']);

            $order_actualise_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'id' => $row['id']), '_SELF');

            $actions = do_template('ECOM_ADMIN_ORDER_ACTIONS', array('_GUID' => '19ad8393aa5dba3f2f768818f22d8837', 'ORDER_TITLE' => $order_title, 'ORDER_ACTUALISE_URL' => $order_actualise_url, 'ORDER_STATUS' => $order_status));

            $submitted_by = $GLOBALS['FORUM_DRIVER']->get_username($row['c_member']);
            $member_url = build_url(array('page' => 'members', 'type' => 'view', 'id' => $row['c_member']), get_module_zone('members'));
            $member = hyperlink($member_url, $submitted_by, false, true, do_lang('CUSTOMER'));

            $order_date = hyperlink($view_url, get_timezoned_date($row['add_date'], true, false, true, true), false, true);

            if (($row['transaction_id'] != '') && ($row['order_status'] != 'ORDER_STATUS_awaiting_payment')) {
                $transaction_details_url = build_url(array('page' => 'admin_ecommerce_logs', 'type' => 'logs', 'type_code' => $order_title, 'id' => $row['id']), get_module_zone('admin_ecommerce_logs'));
                $transaction_id = hyperlink($transaction_details_url, $row['transaction_id'], false, true);
            } else {
                $transaction_id = do_lang_tempcode('INCOMPLETED_TRANSACTION');
            }

            $order_entries->attach(results_entry(
                    array(
                        escape_html($order_title),
                        ecommerce_get_currency_symbol() . escape_html(float_format($row['tot_price'], 2)),
                        escape_html(is_null($row['tax']) ? '' : float_format($row['tax'], 2)),
                        $order_date,
                        $member,
                        $transaction_id,
                        $order_status,
                        $actions
                    ), false, null)
            );
        }
        if ($order_entries->is_empty()) {
            inform_exit(do_lang_tempcode('NO_ENTRIES'));
        }

        require_code('templates_pagination');
        $max_rows = $GLOBALS['SITE_DB']->query_value_if_there('SELECT COUNT(*) FROM ' . get_table_prefix() . 'shopping_order t1' . $extra_join . ' LEFT JOIN ' . get_table_prefix() . 'shopping_order_details t3 ON t1.id=t3.order_id ' . $cond);
        $pagination = pagination(do_lang_tempcode('ORDERS'), $start, 'start', $max, 'max', $max_rows, true);

        $widths = mixed();//array('110', '70', '80', '200', '120', '180', '180', '200');
        $results_table = results_table(do_lang_tempcode('ORDERS'), 0, 'start', $max_rows, 'max', $max_rows, $fields_title, $order_entries, $sortables, $sortable, $sort_order, 'sort', null, $widths);

        $hidden = build_keep_form_fields('_SELF', true, array('filter'));

        $search_url = get_self_url(true);

        $tpl = do_template('ECOM_ADMIN_ORDERS_SCREEN', array(
            '_GUID' => '08afb0204c061644ec9c562b4eba24f4',
            'TITLE' => $this->title,
            'CURRENCY' => get_option('currency'),
            'RESULTS_TABLE' => $results_table,
            'PAGINATION' => $pagination,
            'SEARCH_URL' => $search_url,
            'SEARCH_VAL' => $search,
            'HIDDEN' => $hidden,
        ));

        require_code('templates_internalise_screen');
        return internalise_own_screen($tpl);
    }

    /**
     * UI to show details of an order.
     *
     * @return Tempcode The interface.
     */
    public function order_details()
    {
        $id = get_param_integer('id');

        $order_title = do_lang('CART_ORDER', $id);

        $start = get_param_integer('start', 0);
        $max = get_param_integer('max', 10);

        $sortables = array();
        $query_sort = explode(' ', get_param_string('sort', 'p_name ASC'), 2);
        if (count($query_sort) == 1) {
            $query_sort[] = 'ASC';
        }
        list($sortable, $sort_order) = $query_sort;

        require_code('templates_results_table');

        $fields_title = results_field_title(
            array(
                do_lang_tempcode('SKU'),
                do_lang_tempcode('PRODUCT_NAME'),
                do_lang_tempcode('THE_PRICE'),
                do_lang_tempcode('QUANTITY'),
                do_lang_tempcode('STATUS'),
            ), $sortables, 'sort', $sortable . ' ' . $sort_order
        );

        $max_rows = $GLOBALS['SITE_DB']->query_select_value_if_there('shopping_order_details', 'COUNT(*)', array('order_id' => $id));

        // Show products in the order
        $rows = $GLOBALS['SITE_DB']->query_select('shopping_order_details', array('*'), array('order_id' => $id), 'ORDER BY ' . $sortable . ' ' . $sort_order, $max, $start);
        $product_entries = new Tempcode();
        foreach ($rows as $row) {
            $product_info_url = build_url(array('page' => 'catalogues', 'type' => 'entry', 'id' => $row['p_id']), get_module_zone('catalogues'));

            $product_name = $row['p_name'];

            $product = hyperlink($product_info_url, $product_name, false, true, do_lang('VIEW'));

            $product_entries->attach(results_entry(
                    array(
                        escape_html(strval($row['p_id'])),
                        $product,
                        ecommerce_get_currency_symbol() . escape_html(float_format($row['p_price'], 2)),
                        escape_html(strval($row['p_quantity'])),
                        do_lang($row['dispatch_status'])
                    ), false, null)
            );
        }
        $results_table = results_table(do_lang_tempcode('PRODUCTS'), 0, 'start', $max_rows, 'max', $max_rows, $fields_title, $product_entries, $sortables, $sortable, $sort_order, 'sort', null, null);

        // Pagination
        require_code('templates_pagination');
        $pagination = pagination(do_lang_tempcode('ORDERS'), $start, 'start', $max, 'max', $max_rows, true);

        $text = do_lang_tempcode('ORDER_DETAILS_TEXT');

        // Collecting order details
        $rows = $GLOBALS['SITE_DB']->query_select('shopping_order', array('*'), array('id' => $id), '', 1);
        if (!array_key_exists(0, $rows)) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
        }
        $data = $rows[0];

        // Order actions
        $ordered_by_member_id = $data['c_member'];
        $ordered_by_username = $GLOBALS['FORUM_DRIVER']->get_username($data['c_member']);
        $self_url = get_self_url(true, true);
        $order_actualise_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'id' => $id, 'redirect' => $self_url), '_SELF');
        $order_actions = do_template('ECOM_ADMIN_ORDER_ACTIONS', array(
            '_GUID' => '6a24f6fb7c23f60b049ebce0f9765736',
            'ORDER_TITLE' => $order_title,
            'ORDER_ACTUALISE_URL' => $order_actualise_url,
            'ORDER_STATUS' => do_lang($data['order_status']),
        ));

        // Shipping address display
        $row = $GLOBALS['SITE_DB']->query_select('shopping_order_addresses', array('*'), array('order_id' => $id), '', 1);
        if (array_key_exists(0, $row)) {
            $address = $row[0];
            $shipping_address = do_template('ECOM_SHIPPING_ADDRESS', array(
                '_GUID' => '332bc2e28a75cff64e6856bbeda6102e',
                'FIRST_NAME' => $address['first_name'],
                'LAST_NAME' => $address['last_name'],
                'ADDRESS_NAME' => $address['address_name'],
                'ADDRESS_STREET' => $address['address_street'],
                'ADDRESS_CITY' => $address['address_city'],
                'ADDRESS_STATE' => $address['address_state'],
                'ADDRESS_ZIP' => $address['address_zip'],
                'ADDRESS_COUNTRY' => $address['address_country'],
                'RECEIVER_EMAIL' => $address['receiver_email'],
                'CONTACT_PHONE' => $address['contact_phone'],
            ));
        } else {
            $shipping_address = new Tempcode();
        }

        // Show screen...

        $tpl = do_template('ECOM_ADMIN_ORDERS_DETAILS_SCREEN', array(
            '_GUID' => '3ae59a343288eb6aa67e3627b5ea7eda',
            'TITLE' => $this->title,
            'TEXT' => $text,
            'RESULTS_TABLE' => $results_table,
            'PAGINATION' => $pagination,
            'ORDER_NUMBER' => strval($id),
            'ADD_DATE' => get_timezoned_date($data['add_date'], true, false, true, true),
            'CURRENCY' => get_option('currency'),
            'TOTAL_PRICE' => float_format($data['tot_price'], 2),
            'ORDERED_BY_MEMBER_ID' => strval($ordered_by_member_id),
            'ORDERED_BY_USERNAME' => $ordered_by_username,
            'ORDER_STATUS' => do_lang($data['order_status']),
            'NOTES' => $data['notes'],
            'ORDER_ACTIONS' => $order_actions,
            'SHIPPING_ADDRESS' => $shipping_address,
        ));

        require_code('templates_internalise_screen');
        return internalise_own_screen($tpl);
    }

    /**
     * Method to dispatch an order.
     *
     * @return Tempcode The interface.
     */
    public function dispatch()
    {
        $id = get_param_integer('id');

        $GLOBALS['SITE_DB']->query_update('shopping_order', array('order_status' => 'ORDER_STATUS_dispatched'), array('id' => $id), '', 1);
        $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('dispatch_status' => 'ORDER_STATUS_dispatched'), array('order_id' => $id)); // There may be more than one items to update status

        require_code('shopping');
        update_stock($id);

        $add_note_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'action' => 'add_note', 'last_act' => 'dispatched', 'id' => $id), get_module_zone('admin_orders'));

        return redirect_screen($this->title, $add_note_url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * UI to add note to an order.
     *
     * @return Tempcode The interface.
     */
    public function add_note()
    {
        $id = get_param_integer('id');

        require_code('form_templates');

        $redirect_url = get_param_string('redirect', null);
        $last_action = get_param_string('last_act', null);

        $update_url = build_url(array('page' => '_SELF', 'type' => '_add_note', 'redirect' => $redirect_url), '_SELF');

        $fields = new Tempcode();

        $note = $GLOBALS['SITE_DB']->query_select_value('shopping_order', 'notes', array('id' => $id));

        if (!is_null($last_action)) {
            $note .= do_lang('ADD_NOTE_APPEND_TEXT', get_timezoned_date(time(), true, false, true, true), do_lang('ORDER_STATUS_' . $last_action));
        }

        $fields->attach(form_input_text(do_lang_tempcode('NOTE'), do_lang_tempcode('NOTE_DESCRIPTION'), 'note', $note, true));

        $fields->attach(form_input_hidden('order_id', strval($id)));

        if ($last_action == 'dispatched') {
            // Display dispatch mail preview
            $res = $GLOBALS['SITE_DB']->query_select('shopping_order', array('*'), array('id' => $id), '', 1);
            $order_det = $res[0];

            $member_name = $GLOBALS['FORUM_DRIVER']->get_username($order_det['c_member']);

            $message = do_lang('ORDER_DISPATCHED_MAIL_MESSAGE', comcode_escape(get_site_name()), comcode_escape($member_name), array(strval($id)), get_lang($order_det['c_member']));

            $fields->attach(form_input_text(do_lang_tempcode('DISPATCH_MAIL_PREVIEW'), do_lang_tempcode('DISPATCH_MAIL_PREVIEW_DESCRIPTION'), 'dispatch_mail_content', $message, true));
        }

        return do_template('FORM_SCREEN', array(
            '_GUID' => 'a5bd2fd3e7f326fd7559e78015d70715',
            'TITLE' => $this->title,
            'TEXT' => do_lang_tempcode('NOTE_DESCRIPTION'),
            'HIDDEN' => '',
            'FIELDS' => $fields,
            'URL' => $update_url,
            'SUBMIT_ICON' => 'buttons__proceed',
            'SUBMIT_NAME' => do_lang_tempcode('ADD_NOTE'),
            'SUPPORT_AUTOSAVE' => true,
        ));
    }

    /**
     * Actualiser to add a note to an order.
     *
     * @return Tempcode The interface.
     */
    public function _add_note()
    {
        $id = post_param_integer('order_id');

        $notes = post_param_string('note');
        $redirect = get_param_string('redirect', null);

        $GLOBALS['SITE_DB']->query_update('shopping_order', array('notes' => $notes), array('id' => $id), '', 1);

        $this->send_dispatch_notification($id);

        if (is_null($redirect)) { // If a redirect url is not passed, redirect to the order list
            $_redirect = build_url(array('page' => '_SELF', 'type' => 'show_orders'), get_module_zone('admin_orders'));
            $redirect = $_redirect->evaluate();
        }

        return redirect_screen($this->title, $redirect, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Method to dispatch a notification for an order.
     *
     * @param  AUTO_LINK $order_id Order ID
     */
    public function send_dispatch_notification($order_id)
    {
        $message = post_param_string('dispatch_mail_content', null);

        if (is_null($message)) {
            return;
        }

        $res = $GLOBALS['SITE_DB']->query_select('shopping_order', array('*'), array('id' => $order_id), '', 1);
        $order_det = $res[0];

        if (is_guest($order_det['c_member'])) {
            attach_message(do_lang_tempcode('NO_NOTE_GUEST'), 'warn');
        } else {
            require_code('notifications');
            dispatch_notification('order_dispatched', null, do_lang('ORDER_DISPATCHED_MAIL_SUBJECT', get_site_name(), strval($order_id), null, get_lang($order_det['c_member'])), $message, array($order_det['c_member']), A_FROM_SYSTEM_PRIVILEGED);
        }
    }

    /**
     * Method to delete order.
     *
     * @return Tempcode The interface.
     */
    public function delete_order()
    {
        $id = get_param_integer('id');

        $GLOBALS['SITE_DB']->query_update('shopping_order', array('order_status' => 'ORDER_STATUS_cancelled'), array('id' => $id), '', 1);
        $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('dispatch_status' => 'ORDER_STATUS_cancelled'), array('order_id' => $id), '', 1);

        $add_note_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'action' => 'add_note', 'last_act' => 'cancelled', 'id' => $id), get_module_zone('admin_orders'));

        return redirect_screen($this->title, $add_note_url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Method to return order items.
     *
     * @return Tempcode The interface.
     */
    public function return_order()
    {
        $id = get_param_integer('id');

        $GLOBALS['SITE_DB']->query_update('shopping_order', array('order_status' => 'ORDER_STATUS_returned'), array('id' => $id), '', 1);
        $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('dispatch_status' => 'ORDER_STATUS_returned'), array('order_id' => $id), '', 1);

        $add_note_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'action' => 'add_note', 'last_act' => 'returned', 'id' => $id), get_module_zone('admin_orders'));

        return redirect_screen($this->title, $add_note_url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Method to hold an order.
     *
     * @return Tempcode The interface.
     */
    public function hold_order()
    {
        $id = get_param_integer('id');

        $GLOBALS['SITE_DB']->query_update('shopping_order', array('order_status' => 'ORDER_STATUS_onhold'), array('id' => $id), '', 1);
        $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('dispatch_status' => 'ORDER_STATUS_onhold'), array('order_id' => $id), '', 1);

        $add_note_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'action' => 'add_note', 'last_act' => 'onhold', 'id' => $id), get_module_zone('admin_orders'));

        return redirect_screen($this->title, $add_note_url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Method to display export order list filters.
     *
     * @return Tempcode The interface.
     */
    public function order_export()
    {
        require_code('shopping');

        require_code('form_templates');

        $fields = new Tempcode();

        $order_status_list = get_order_status_list();

        $fields->attach(form_input_list(do_lang_tempcode('ORDER_STATUS'), do_lang_tempcode('ORDER_STATUS_FILTER_DESCRIPTION'), 'order_status', $order_status_list, null, false, false));

        // Dates...

        $start_year = intval(date('Y')) - 1;
        $start_month = intval(date('m'));
        $start_day = intval(date('d'));
        $start_hour = intval(date('H'));
        $start_minute = intval(date('i'));

        $end_year = $start_year + 1;
        $end_month = $start_month;
        $end_day = $start_day;
        $end_hour = $start_hour;
        $end_minute = $start_minute;

        $fields->attach(form_input_date(do_lang_tempcode('ST_START_PERIOD'), do_lang_tempcode('ST_START_PERIOD_DESCRIPTION'), 'start_date', true, false, true, array($start_minute, $start_hour, $start_month, $start_day, $start_year)));
        $fields->attach(form_input_date(do_lang_tempcode('ST_END_PERIOD'), do_lang_tempcode('ST_END_PERIOD_DESCRIPTION'), 'end_date', true, false, true, array($end_minute, $end_hour, $end_month, $end_day, $end_year)));

        return do_template('FORM_SCREEN', array(
            '_GUID' => 'e2e5097798c963f4977ba22b50ddf2f3',
            'SKIP_WEBSTANDARDS' => true,
            'TITLE' => $this->title,
            'SUBMIT_ICON' => 'menu___generic_admin__export',
            'SUBMIT_NAME' => do_lang_tempcode('EXPORT_ORDER_LIST'),
            'TEXT' => paragraph(do_lang_tempcode('EXPORT_ORDER_LIST_TEXT')),
            'URL' => build_url(array('page' => '_SELF', 'type' => '_order_export'), '_SELF'),
            'HIDDEN' => '',
            'FIELDS' => $fields,
        ));
    }

    /**
     * Actualiser to build CSV from the selected filters.
     *
     * @param  boolean $inline Whether to avoid exit (useful for unit test).
     */
    public function _order_export($inline = false)
    {
        require_code('shopping');

        $start_date = post_param_date('start_date', true);
        $end_date = post_param_date('end_date', true);
        $order_status = post_param_string('order_status');

        $filename = 'Orders_' . $order_status . '__' . get_timezoned_date($start_date, false, false, false, true) . '-' . get_timezoned_date($end_date, false, false, false, true) . '.csv';

        $orders = array();
        $data = array();

        $cond = 't1.add_date BETWEEN ' . strval($start_date) . ' AND ' . strval($end_date);
        if ($order_status != 'all') {
            $cond .= ' AND ' . db_string_equal_to('t1.order_status', $order_status);
        }

        $qry = 'SELECT t1.*,(t2.included_tax*t2.p_quantity) AS tax_amt,t3.*
            FROM ' . get_table_prefix() . 'shopping_order t1
            LEFT JOIN ' . get_table_prefix() . 'shopping_order_details t2 ON t1.id=t2.order_id
            LEFT JOIN ' . get_table_prefix() . 'shopping_order_addresses t3 ON t1.id=t3.order_id
            WHERE ' . $cond;
        $row = $GLOBALS['SITE_DB']->query($qry);
        remove_duplicate_rows($row);

        foreach ($row as $order) {
            $orders[do_lang('ORDER_NUMBER')] = strval($order['id']);
            $orders[do_lang('ORDERED_DATE')] = get_timezoned_date($order['add_date'], true, false, true, true);
            $orders[do_lang('ORDER_PRICE')] = $order['tot_price'];
            $orders[do_lang('ORDER_STATUS')] = do_lang($order['order_status']);
            $orders[do_lang('ORDER_TAX_OPT_OUT')] = ($order['tax_opted_out']) ? do_lang('YES') : do_lang('NO');
            $orders[do_lang('TOTAL_TAX_PAID')] = is_null($order['tax_amt']) ? float_format(0.0, 2) : float_format($order['tax_amt'], 2);
            $orders[do_lang('ORDERED_PRODUCTS')] = get_ordered_product_list_string($order['id']);
            $orders[do_lang('ORDERED_BY')] = $GLOBALS['FORUM_DRIVER']->get_username($order['c_member']);

            // Put address together
            $address = array();
            if ($order['first_name'] . $order['last_name'] != '') {
                $address[] = trim($order['first_name'] . ' ' . $order['last_name']);
            }
            if ($order['address_name'] != '') {
                $address[] = $order['address_name'];
            }
            if ($order['address_city'] != '') {
                $address[] = $order['address_city'];
            }
            if ($order['address_state'] != '') {
                $address[] = $order['address_state'];
            }
            if ($order['address_zip'] != '') {
                $address[] = $order['address_zip'];
            }
            if ($order['address_country'] != '') {
                $address[] = $order['address_country'];
            }
            if ($order['contact_phone'] != '') {
                $address[] = do_lang('PHONE_NUMBER') . ': ' . $order['contact_phone'];
            }
            $full_address = implode("\n", $address);
            $orders[do_lang('FULL_ADDRESS')] = $full_address;

            $data[] = $orders;
        }

        require_code('files2');
        make_csv($data, $filename, !$inline, !$inline);
    }
}