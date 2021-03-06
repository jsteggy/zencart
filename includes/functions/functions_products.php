<?php
/**
 * functions_products.php
 * Functions related to products
 * Note: Several product-related lookup functions are located in functions_lookups.php
 *
 * @package functions
 * @copyright Copyright 2019 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: New in v1.5.7 $
 */

/**
 * Query product details
 * @param int $product_id
 * @param int $language_id (optional)
 * @return queryFactoryResult
 */
function zen_get_product_details($product_id, $language_id = null)
{
    global $db;

    if ($language_id === null) $language_id = $_SESSION['languages_id'];

    $sql = "SELECT p.products_status, p.*, pd.*
            FROM " . TABLE_PRODUCTS . " p, " .
                     TABLE_PRODUCTS_DESCRIPTION . " pd
            WHERE    p.products_id = " . (int)$product_id . "
            AND      pd.products_id = p.products_id
            AND      pd.language_id = " . (int)$language_id;
    return $db->Execute($sql);
}

/**
 * @param int $product_id
 * @param null $product_info
 */
function zen_product_set_header_response($product_id, $product_info = null)
{
    global $zco_notifier, $breadcrumb, $robotsNoIndex;

    // make sure we got a dbResponse
    if ($product_info === null || !isset($product_info->EOF)) {
        $product_info = zen_get_product_details($product_id);
    }
    // make sure it's for the current product
    if (!isset($product_info->fields['products_id'], $product_info->fields['products_status']) || $product_info->fields['products_id'] !== $product_id) {
        $product_info = zen_get_product_details($product_id);
    }

    $response_code = 200;

    $should_throw_404 = $product_not_found = $product_info->EOF;
    if ($should_throw_404) {
        $response_code = 404;
    }

    global $product_status;
    $product_status = !$product_info->EOF && $product_info->fields['products_status'] ? (int)$product_info->fields['products_status'] : 0;

    if ($product_status === 0) {
        $response_code = 410;
    }

    if (defined('PRODUCT_THROWS_200_WHEN_DISABLED') && PRODUCT_THROWS_200_WHEN_DISABLED === true) {
        $response_code = 200;
    }

    if ($product_status === -1) {
        $response_code = 410;
    }

    $use_custom_response_code = false;
    /**
     * optionally update the $product_status, $should_throw_404, $response_code vars via the observer
     */
    $zco_notifier->notify('NOTIFY_PRODUCT_INFO_PRODUCT_STATUS_CHECK', $product_info->fields, $product_status, $should_throw_404, $response_code, $use_custom_response_code);

    if ($use_custom_response_code) {
        // skip this function's processing and leave all header handling to the observer.
        // Note: the observer should do all the 404 stuff from below too
        return;
    }

    if ($should_throw_404) {
        // if specified product_id doesn't exist, ensure that metatags and breadcrumbs don't share bad data or inappropriate information
        unset($_GET['products_id']);
        unset($breadcrumb->_trail[sizeof($breadcrumb->_trail)-1]['title']);
        $robotsNoIndex = true;
        header('HTTP/1.1 404 Not Found');
        return;
    }

    if ($response_code === 410) {
        $robotsNoIndex = true;
        header('HTTP/1.1 410 Gone');
        return;
    }

    if ($response_code === 200) return;
}
