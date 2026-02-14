<?php
/**
 * Plugin Name: portalsluchu – Listing fee checkout: no shipping (1087)
 * Description: Ukrywa wysyłkę / metody dostawy na checkout, gdy koszyk zawiera WYŁĄCZNIE produkt opłaty za wystawienie (ID 1087).
 * Version: 1.1.0
 * Author: portalsluchu.pl
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class Portalsluchu_Listing_Fee_No_Shipping {

	const FEE_PRODUCT_ID = 1087;

	public static function init() : void {
		// 1) Woo: koszyk nie wymaga wysyłki
		add_filter( 'woocommerce_cart_needs_shipping', array( __CLASS__, 'cart_needs_shipping' ), 100, 2 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( __CLASS__, 'cart_needs_shipping_address' ), 100, 1 );

		// 2) Woo: produkty nie wymagają wysyłki (tylko w fee-only)
		add_filter( 'woocommerce_product_needs_shipping', array( __CLASS__, 'product_needs_shipping' ), 100, 2 );

		// 3) Woo: zbij wymagania pól shipping na checkout (żeby nie blokowały płatności)
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 100, 1 );

		// 4) Nie pokazuj checkboxa “inny adres wysyłki”
		add_filter( 'woocommerce_ship_to_different_address_checked', array( __CLASS__, 'ship_to_different_address_checked' ), 100, 1 );
	}

	private static function cart_has_only_fee() : bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) return false;

		$items = WC()->cart->get_cart();
		if ( empty( $items ) ) return false;

		foreach ( $items as $item ) {
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			if ( $product_id !== (int) self::FEE_PRODUCT_ID ) {
				return false;
			}
		}
		return true;
	}

	public static function cart_needs_shipping( $needs_shipping, $cart ) {
		if ( self::cart_has_only_fee() ) return false;
		return $needs_shipping;
	}

	public static function cart_needs_shipping_address( $needs_address ) {
		if ( self::cart_has_only_fee() ) return false;
		return $needs_address;
	}

	public static function product_needs_shipping( $needs_shipping, $product ) {
		if ( self::cart_has_only_fee() ) return false;
		return $needs_shipping;
	}

	public static function checkout_fields( $fields ) {
		if ( ! self::cart_has_only_fee() ) {
			return $fields;
		}

		// Usuń required z pól shipping, żeby checkout nie blokował płatności.
		if ( isset( $fields['shipping'] ) && is_array( $fields['shipping'] ) ) {
			foreach ( $fields['shipping'] as $key => $def ) {
				if ( isset( $fields['shipping'][ $key ]['required'] ) ) {
					$fields['shipping'][ $key ]['required'] = false;
				}
			}
		}

		// Czasem motywy/buildery podpinają shipping do billing – też zdejmij required z “adresowych” billing,
		// ale tylko jeśli chcesz maksymalnie uprościć fee-only checkout:
		$maybe_address_billing = array(
			'billing_address_1',
			'billing_city',
			'billing_postcode',
			'billing_country',
		);

		if ( isset( $fields['billing'] ) && is_array( $fields['billing'] ) ) {
			foreach ( $maybe_address_billing as $k ) {
				if ( isset( $fields['billing'][ $k ]['required'] ) ) {
					$fields['billing'][ $k ]['required'] = false;
				}
			}
		}

		return $fields;
	}

	public static function ship_to_different_address_checked( $checked ) {
		if ( self::cart_has_only_fee() ) return false;
		return $checked;
	}
}

Portalsluchu_Listing_Fee_No_Shipping::init();