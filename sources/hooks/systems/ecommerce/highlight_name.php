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
 * @package    ecommerce
 */

/**
 * Hook class.
 */
class Hook_ecommerce_highlight_name
{
    /**
     * Get the products handled by this eCommerce hook.
     *
     * IMPORTANT NOTE TO PROGRAMMERS: This function may depend only on the database, and not on get_member() or any GET/POST values.
     *  Such dependencies will break IPN, which works via a Guest and no dependable environment variables. It would also break manual transactions from the Admin Zone.
     *
     * @param  boolean $site_lang Whether to make sure the language for item_name is the site default language (crucial for when we read/go to third-party sales systems and use the item_name as a key).
     * @param  ?ID_TEXT $search Product being searched for (null: none).
     * @param  boolean $search_item_names Whether $search refers to the item name rather than the product codename.
     * @return array A map of product name to list of product details.
     */
    public function get_products($site_lang = false, $search = null, $search_item_names = false)
    {
        require_lang('ecommerce');

        $products = array(
            'HIGHLIGHT_NAME' => array(
                'item_name' => do_lang('NAME_HIGHLIGHTING', null, null, null, $site_lang ? get_site_default_lang() : user_lang()),
                'item_description' => do_lang_tempcode('NAME_HIGHLIGHTING_DESCRIPTION'),
                'item_image_url' => find_theme_image('icons/48x48/menu/social/members'),

                'type' => PRODUCT_PURCHASE,
                'type_special_details' => array(),

                'price' => null,
                'currency' => get_option('currency'),
                'price_points' => intval(get_option('highlight_name')),
                'discount_points__num_points' => null,
                'discount_points__price_reduction' => null,

                'needs_shipping_address' => false,
            ),
        );
        return $products;
    }

    /**
     * Check whether the product codename is available for purchase by the member.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  MEMBER $member_id The member we are checking against.
     * @param  integer $req_quantity The number required.
     * @param  boolean $must_be_listed Whether the product must be available for public listing.
     * @return integer The availability code (a ECOMMERCE_PRODUCT_* constant).
     */
    public function is_available($type_code, $member_id, $req_quantity = 1, $must_be_listed = false)
    {
        if ((get_option('is_on_highlight_name_buy') == '0') || (get_forum_type() != 'cns')) {
            return ECOMMERCE_PRODUCT_DISABLED;
        }

        if (get_option('enable_highlight_name') == '0') {
            return ECOMMERCE_PRODUCT_DISABLED;
        }

        if (is_guest($member_id)) {
            return ECOMMERCE_PRODUCT_NO_GUESTS;
        }

        if ($GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, 'm_highlighted_name') == 1) {
            return ECOMMERCE_PRODUCT_ALREADY_HAS;
        }

        return ECOMMERCE_PRODUCT_AVAILABLE;
    }

    /**
     * Handling of a product purchase change state.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @param  array $details Details of the product, with added keys: TXN_ID, PAYMENT_STATUS, ORDER_STATUS.
     */
    public function actualiser($type_code, $purchase_id, $details)
    {
        if ($details['PAYMENT_STATUS'] != 'Completed') {
            return;
        }

        $member_id = intval($purchase_id);

        $GLOBALS['FORUM_DB']->query_update('f_members', array('m_highlighted_name' => 1), array('id' => $member_id), '', 1);

        $GLOBALS['SITE_DB']->query_insert('ecom_sales', array('date_and_time' => time(), 'member_id' => $member_id, 'details' => do_lang('NAME_HIGHLIGHTING', null, null, null, get_site_default_lang()), 'details2' => '', 'transaction_id' => $details['TXN_ID']));
    }

    /**
     * Get the member who made the purchase.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @return ?MEMBER The member ID (null: none).
     */
    public function member_for($type_code, $purchase_id)
    {
        return intval($purchase_id);
    }
}
