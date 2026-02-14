<?php
/** 
 * Plugin Name: portalsluchu – Bezpośrednio na stronę "Kasa"
 * Description: portalsluchu-direct-checkout / Po dodaniu produktu do koszyka przekierowuje użytkownika od razu na stronę zamówienia (/kasa).
 * Author: portalsluchu.pl
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Zawsze po dodaniu do koszyka – przekieruj na stronę zamówienia
function portalsluchu_redirect_to_checkout_after_add_to_cart( $url ) {
    if ( function_exists( 'wc_get_checkout_url' ) ) {
        return wc_get_checkout_url();
    }

    return $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'portalsluchu_redirect_to_checkout_after_add_to_cart' );
