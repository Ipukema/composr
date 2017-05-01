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
class Module_admin_shopping
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
            $ret['_SEARCH:admin_shopping:show_orders:filter=undispatched'] = array('SHOW_UNDISPATCHED_ORDERS', 'menu/adminzone/audit/ecommerce/undispatched_orders');
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

        require_code('ecommerce');
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

        if ($type == 'order_details' || $action == 'order_act' || $action == '_add_note' || $action == 'export_orders' || $action == '_export_orders') {
            breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce_logs:browse', do_lang_tempcode('ECOMMERCE')), array('_SELF:_SELF:browse', do_lang_tempcode('ORDERS')), array('_SELF:_SELF:show_orders', do_lang_tempcode('SHOW_ORDERS'))));
        }

        if ($action == 'order_act') {
            if ($action != 'add_note') {
                breadcrumb_set_self(do_lang_tempcode('DONE'));
            }
        }

        if ($action == '_add_note' || $action == '_export_orders') {
            breadcrumb_set_self(do_lang_tempcode('DONE'));
        }

        if ($type == 'order_details') {
            $this->title = get_screen_title('ORDER_DETAILS');
        }

        if ($type == 'export_orders' || $action == '_export_orders') {
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
        require_javascript('shopping');
        require_css('shopping');
        require_code('users_active_actions');
        require_code('shopping');

        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            return $this->browse();
        }
        if ($type == 'show_orders') {
            return $this->show_orders();
        }
        if ($type == 'order_details') {
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
        if ($type == 'export_orders') {
            return $this->export_orders();
        }
        if ($type == '_export_orders') {
            $this->_export_orders();
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
        $filter = get_param_string('filter', null);
        $search = get_param_string('search', '', true);

        $where = '1=1';

        if ($filter == 'undispatched') {
            $where .= ' AND ' . db_string_equal_to('order_status', 'ORDER_STATUS_payment_received');
        }

        if (($search !== null) && ($search != '')) {
            $GLOBALS['NO_DB_SCOPE_CHECK'] = true;

            $where .= ' AND (';
            if (is_numeric($filter)) {
                $where .= 'id=' . strval(intval($filter));
            }
            $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_username($search);
            if ($member_id !== null) {
                if (is_numeric($filter)) {
                    $where .= ' OR ';
                }
                $where .= 'member_id=' . strval($member_id);
            }
            $where .= ')';
        }

        $start = get_param_integer('start', 0);
        $max = get_param_integer('max', 10);

        require_code('templates_results_table');

        $sortables = array(
            'add_date' => do_lang_tempcode('ORDERED_DATE'),
            'member_id' => do_lang_tempcode('ORDERED_BY'),
            'total_price' => do_lang_tempcode('PRICE'),
            'order_status' => do_lang_tempcode('STATUS'),
        );

        $query_sort = explode(' ', get_param_string('sort', 'add_date ASC'), 2);
        if (count($query_sort) == 1) {
            $query_sort[] = 'ASC';
        }
        list($sortable, $sort_order) = $query_sort;
        if (((strtoupper($sort_order) != 'ASC') && (strtoupper($sort_order) != 'DESC')) || (!array_key_exists($sortable, $sortables))) {
            log_hack_attack_and_exit('ORDERBY_HACK');
        }

        $fields_title = results_field_title(array(
            do_lang_tempcode('ECOM_ORDER'),
            do_lang_tempcode('PRICE'),
            do_lang_tempcode(get_option('tax_system')),
            do_lang_tempcode('SHIPPING_COST'),
            do_lang_tempcode('ORDERED_DATE'),
            do_lang_tempcode('ORDERED_BY'),
            do_lang_tempcode('TRANSACTION'),
            do_lang_tempcode('STATUS'),
            do_lang_tempcode('ACTIONS')
        ), $sortables, 'sort', $sortable . ' ' . $sort_order);

        global $NO_DB_SCOPE_CHECK;
        $NO_DB_SCOPE_CHECK = true;

        $sql = 'SELECT * FROM ' . get_table_prefix() . 'shopping_orders WHERE ' . $where . ' ORDER BY ' . db_string_equal_to('order_status', 'ORDER_STATUS_cancelled')/*cancelled always last*/ . ',' . $sortable . ' ' . $sort_order;
        $rows = $GLOBALS['SITE_DB']->query($sql, $max, $start, false, true);
        $order_entries = new Tempcode();
        foreach ($rows as $row) {
            if ($row['purchase_through'] == 'cart') {
                $order_title = do_lang('CART_ORDER', strval($row['id']));
            } else {
                $order_title = do_lang('PURCHASE_ORDER', strval($row['id']));
            }

            $order_details_url = build_url(array('page' => '_SELF', 'type' => 'order_details', 'id' => $row['id']), '_SELF');
            $order_date = hyperlink($order_details_url, get_timezoned_date($row['add_date'], true, false, true, true), false, true);

            $submitted_by = $GLOBALS['FORUM_DRIVER']->get_username($row['member_id']);
            if (($submitted_by === null) || (is_guest($row['member_id']))) {
                $member_link = do_lang('UNKNOWN');
            } else {
                $member_url = build_url(array('page' => 'members', 'type' => 'view', 'id' => $row['member_id']), get_module_zone('members'));
                $member_link = hyperlink($member_url, $submitted_by, false, true, do_lang('CUSTOMER'));
            }

            $transaction_linker = build_transaction_linker($row['txn_id'], $row['order_status'] == 'ORDER_STATUS_awaiting_payment');

            $order_status = do_lang_tempcode($row['order_status']);

            $order_actualise_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'id' => $row['id']), '_SELF');
            $actions = do_template('ECOM_ADMIN_ORDER_ACTIONS', array('_GUID' => '19ad8393aa5dba3f2f768818f22d8837', 'ORDER_TITLE' => $order_title, 'ORDER_ACTUALISE_URL' => $order_actualise_url, 'ORDER_STATUS' => $order_status));

            $order_entries->attach(results_entry(array(
                escape_html($order_title),
                ecommerce_get_currency_symbol() . escape_html(float_format($row['total_price'])),
                ecommerce_get_currency_symbol() . escape_html(float_format($row['total_tax'])),
                ecommerce_get_currency_symbol() . escape_html(float_format($row['total_shipping_cost'])),
                $order_date,
                $member_link,
                $transaction_linker,
                $order_status,
                $actions
            ), false));
        }
        if ($order_entries->is_empty()) {
            inform_exit(do_lang_tempcode('NO_ENTRIES'));
        }

        require_code('templates_pagination');
        $max_rows = $GLOBALS['SITE_DB']->query_value_if_there('SELECT COUNT(*) FROM ' . get_table_prefix() . 'shopping_orders WHERE ' . $where);
        $pagination = pagination(do_lang_tempcode('ORDERS'), $start, 'start', $max, 'max', $max_rows, true);

        $results_table = results_table(do_lang_tempcode('ORDERS'), 0, 'start', $max_rows, 'max', $max_rows, $fields_title, $order_entries, $sortables, $sortable, $sort_order, 'sort');

        $hidden = build_keep_form_fields('_SELF', true, array('filter'));

        $search_url = get_self_url(true);

        $tpl = do_template('ECOM_ADMIN_ORDERS_SCREEN', array(
            '_GUID' => '08afb0204c061644ec9c562b4eba24f4',
            'TITLE' => $this->title,
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

        $text = do_lang_tempcode('ORDER_DETAILS_TEXT');

        require_code('ecommerce_logs');
        $tpl = build_order_details($this->title, $id, $text, true);

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

        $GLOBALS['SITE_DB']->query_update('shopping_orders', array('order_status' => 'ORDER_STATUS_dispatched'), array('id' => $id), '', 1);
        $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('p_dispatch_status' => 'ORDER_STATUS_dispatched'), array('p_order_id' => $id)); // There may be more than one items to update status

        $add_note_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'action' => 'add_note', 'last_act' => 'dispatched', 'id' => $id), get_module_zone('admin_shopping'));

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

        $note = $GLOBALS['SITE_DB']->query_select_value('shopping_orders', 'notes', array('id' => $id));

        if ($last_action !== null) {
            $note .= do_lang('ADD_NOTE_APPEND_TEXT', get_timezoned_date(time(), true, false, true, true), do_lang('ORDER_STATUS_' . $last_action));
        }

        $fields->attach(form_input_text(do_lang_tempcode('NOTE'), do_lang_tempcode('NOTE_DESCRIPTION'), 'note', $note, true));

        $fields->attach(form_input_hidden('order_id', strval($id)));

        if ($last_action == 'dispatched') {
            // Display dispatch mail preview
            $res = $GLOBALS['SITE_DB']->query_select('shopping_orders', array('*'), array('id' => $id), '', 1);
            $order_details = $res[0];

            $member_name = $GLOBALS['FORUM_DRIVER']->get_username($order_details['member_id']);

            $message = do_lang('ORDER_DISPATCHED_MAIL_MESSAGE', comcode_escape(get_site_name()), comcode_escape($member_name), array(strval($id)), get_lang($order_details['member_id']));

            $fields->attach(form_input_text(do_lang_tempcode('DISPATCH_MAIL_PREVIEW'), do_lang_tempcode('DISPATCH_MAIL_PREVIEW_DESCRIPTION'), 'dispatch_mail_content', $message, true));

            $submit_name = do_lang_tempcode('SEND_DISPATCH_NOTIFICATION');
        } else {
            $submit_name = do_lang_tempcode('ADD_NOTE');
        }

        return do_template('FORM_SCREEN', array(
            '_GUID' => 'a5bd2fd3e7f326fd7559e78015d70715',
            'TITLE' => $this->title,
            'TEXT' => do_lang_tempcode('NOTE_DESCRIPTION'),
            'HIDDEN' => '',
            'FIELDS' => $fields,
            'URL' => $update_url,
            'SUBMIT_ICON' => 'buttons__proceed',
            'SUBMIT_NAME' => $submit_name,
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

        $GLOBALS['SITE_DB']->query_update('shopping_orders', array('notes' => $notes), array('id' => $id), '', 1);

        $this->send_dispatch_notification($id);

        if ($redirect === null) { // If a redirect url is not passed, redirect to the order list
            $_redirect = build_url(array('page' => '_SELF', 'type' => 'show_orders'), get_module_zone('admin_shopping'));
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

        if ($message === null) {
            return;
        }

        $res = $GLOBALS['SITE_DB']->query_select('shopping_orders', array('*'), array('id' => $order_id), '', 1);
        $order_details = $res[0];

        if (is_guest($order_details['member_id'])) {
            attach_message(do_lang_tempcode('NO_NOTE_GUEST'), 'warn');
        } else {
            require_code('notifications');
            dispatch_notification('order_dispatched', null, do_lang('ORDER_DISPATCHED_MAIL_SUBJECT', get_site_name(), strval($order_id), null, get_lang($order_details['member_id'])), $message, array($order_details['member_id']), A_FROM_SYSTEM_PRIVILEGED);
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

        $GLOBALS['SITE_DB']->query_update('shopping_orders', array('order_status' => 'ORDER_STATUS_cancelled'), array('id' => $id), '', 1);
        $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('p_dispatch_status' => 'ORDER_STATUS_cancelled'), array('p_order_id' => $id), '', 1);

        $add_note_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'action' => 'add_note', 'last_act' => 'cancelled', 'id' => $id), get_module_zone('admin_shopping'));

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

        $GLOBALS['SITE_DB']->query_update('shopping_orders', array('order_status' => 'ORDER_STATUS_returned'), array('id' => $id), '', 1);
        $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('p_dispatch_status' => 'ORDER_STATUS_returned'), array('p_order_id' => $id), '', 1);

        $add_note_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'action' => 'add_note', 'last_act' => 'returned', 'id' => $id), get_module_zone('admin_shopping'));

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

        $GLOBALS['SITE_DB']->query_update('shopping_orders', array('order_status' => 'ORDER_STATUS_onhold'), array('id' => $id), '', 1);
        $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('p_dispatch_status' => 'ORDER_STATUS_onhold'), array('p_order_id' => $id), '', 1);

        $add_note_url = build_url(array('page' => '_SELF', 'type' => 'order_act', 'action' => 'add_note', 'last_act' => 'onhold', 'id' => $id), get_module_zone('admin_shopping'));

        return redirect_screen($this->title, $add_note_url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Method to display export order list filters.
     *
     * @return Tempcode The interface.
     */
    public function export_orders()
    {
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
            'URL' => build_url(array('page' => '_SELF', 'type' => '_export_orders'), '_SELF'),
            'HIDDEN' => '',
            'FIELDS' => $fields,
        ));
    }

    /**
     * Actualiser to build CSV from the selected filters.
     *
     * @return Tempcode The result of execution.
     */
    public function _export_orders()
    {
        $start_date = post_param_date('start_date', true);
        $end_date = post_param_date('end_date', true);
        $order_status = post_param_string('order_status');

        require_code('tasks');
        return call_user_func_array__long_task(do_lang('EXPORT_ORDER_LIST'), $this->title, 'export_shopping_orders', array($start_date, $end_date, $order_status));
    }
}