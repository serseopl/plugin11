<?php
/**
 * Plugin Name: portalsluchu – Checkout fee guard (anti-duplicate add-to-cart)
 * Description: portalsluchu-checkout-fee-guard / Zapobiega wielokrotnemu dodawaniu produktu opłaty (np. 1087) przy odświeżeniu checkout z parametrem ?add-to-cart=ID. Dodatkowo wymusza ilość 1 szt. w koszyku.
 * Version: 1.0.0
 * Author: portalsluchu.pl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Portalsluchu_Checkout_Fee_Guard {

    // USTAW TU ID PRODUKTU OPŁATY
    const FEE_PRODUCT_ID = 1087;

    public static function init() : void {
        // 1) Usuń parametr add-to-cart z URL na checkout (żeby F5 nie dublowało)
        add_action( 'wp_loaded', array( __CLASS__, 'redirect_clean_checkout_url' ), 20 );

        // 2) Max 1 sztuka opłaty w koszyku
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'enforce_single_quantity' ), 10, 1 );
    }

    public static function redirect_clean_checkout_url() : void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        if ( empty( $_GET['add-to-cart'] ) ) {
            return;
        }

        if ( (int) $_GET['add-to-cart'] !== self::FEE_PRODUCT_ID ) {
            return;
        }

        // Jeżeli WooCommerce dodało produkt do koszyka (albo próbuje), czyścimy URL
        // Przekierowanie na "czystą" kasę blokuje wielokrotne dodanie na F5.
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    public static function enforce_single_quantity( $cart ) : void {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        if ( ! $cart || ! is_object( $cart ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['product_id'] ) ) {
                continue;
            }

            if ( (int) $cart_item['product_id'] === self::FEE_PRODUCT_ID ) {
                if ( ! empty( $cart_item['quantity'] ) && (int) $cart_item['quantity'] > 1 ) {
                    $cart->set_quantity( $cart_item_key, 1 );
                }
            }
        }
    }
}

Portalsluchu_Checkout_Fee_Guard::init();


add_filter( 'woocommerce_available_payment_gateways', function( $gateways ) {

    if ( is_admin() ) {
        return $gateways;
    }

    if ( ! function_exists('WC') || ! WC()->cart ) {
        return $gateways;
    }

    $fee_product_id = 1087;
    $has_fee = false;

    foreach ( WC()->cart->get_cart() as $item ) {
        if ( isset($item['product_id']) && (int) $item['product_id'] === $fee_product_id ) {
            $has_fee = true;
            break;
        }
    }

    // Jeśli to jest opłata za wystawienie – usuń "za pobraniem"
    if ( $has_fee ) {
        // standardowy ID bramki COD w WooCommerce to 'cod'
        if ( isset( $gateways['cod'] ) ) {
            unset( $gateways['cod'] );
        }
    }

    return $gateways;
}, 20 );