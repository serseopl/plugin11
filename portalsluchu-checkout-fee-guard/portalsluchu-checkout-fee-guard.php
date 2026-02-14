<?php
/**
 * Plugin Name: portalsluchu – Checkout Fee Guard
 * Description: Ukrywa metody wysyłki i wymogi adresu wysyłki dla koszyka zawierającego wyłącznie produkt opłaty za wystawienie (ID 1087).
 * Author: portalsluchu.pl
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! defined( 'PORTALSLUCHU_FEE_GUARD_PRODUCT_ID' ) ) {
define( 'PORTALSLUCHU_FEE_GUARD_PRODUCT_ID', 1087 );
}

/**
 * Check if cart contains only the listing fee product (ID 1087)
 * Returns true only if ALL cart items are the fee product (1087).
 * Returns false for empty carts, carts with mixed products, or invalid cart items.
 */
function portalsluchu_fee_guard_cart_has_only_fee() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}
	
	$items = WC()->cart->get_cart();
	if ( empty( $items ) ) {
		return false;
	}
	
	foreach ( $items as $cart_item ) {
		// Skip invalid cart items (safety check)
		if ( empty( $cart_item['product_id'] ) ) {
			return false;
		}
		// If any item is NOT the fee product, return false
		if ( (int) $cart_item['product_id'] !== (int) PORTALSLUCHU_FEE_GUARD_PRODUCT_ID ) {
			return false;
		}
	}
	
	// All items are the fee product
	return true;
}

/**
 * Hide shipping methods when cart has only fee product
 */
add_filter( 'woocommerce_cart_needs_shipping', 'portalsluchu_fee_guard_cart_needs_shipping', 100 );
function portalsluchu_fee_guard_cart_needs_shipping( $needs_shipping ) {
if ( portalsluchu_fee_guard_cart_has_only_fee() ) {
return false;
}
return $needs_shipping;
}

/**
 * Hide shipping address requirement when cart has only fee product
 */
add_filter( 'woocommerce_cart_needs_shipping_address', 'portalsluchu_fee_guard_cart_needs_shipping_address', 100 );
function portalsluchu_fee_guard_cart_needs_shipping_address( $needs_address ) {
if ( portalsluchu_fee_guard_cart_has_only_fee() ) {
return false;
}
return $needs_address;
}

/**
 * Don't require shipping for fee product
 */
add_filter( 'woocommerce_product_needs_shipping', 'portalsluchu_fee_guard_product_needs_shipping', 100, 2 );
function portalsluchu_fee_guard_product_needs_shipping( $needs_shipping, $product ) {
if ( ! $product ) {
return $needs_shipping;
}

$product_id = $product->get_id();
if ( (int) $product_id === (int) PORTALSLUCHU_FEE_GUARD_PRODUCT_ID ) {
return false;
}

return $needs_shipping;
}

/**
 * Hide shipping section on checkout when only fee product in cart
 */
add_filter( 'woocommerce_cart_ready_to_calc_shipping', 'portalsluchu_fee_guard_ready_to_calc_shipping', 100 );
function portalsluchu_fee_guard_ready_to_calc_shipping( $show_shipping ) {
if ( portalsluchu_fee_guard_cart_has_only_fee() ) {
return false;
}
return $show_shipping;
}
