<?php
/*
Plugin Name: portalsluchu – dojazd strefy (kody pocztowe)
Description: Silnik stref dojazdu (kody z TXT) + AJAX. Bez własnych pól/checkboxów na checkout i bez własnego naliczania fee.
Author: portalsluchu.pl
Version: 1.1.1
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function portalsluchu_normalize_postcode( $code ) {
	$code = trim( (string) $code );
	if ( $code === '' ) return '';
	$code = str_replace( ' ', '', $code );

	if ( preg_match( '/^[0-9]{2}-[0-9]{3}$/', $code ) ) return $code;
	if ( preg_match( '/^[0-9]{5}$/', $code ) ) return substr( $code, 0, 2 ) . '-' . substr( $code, 2 );
	return $code;
}

function portalsluchu_load_zone_codes( $filename ) {
	$codes = array();
	if ( ! file_exists( $filename ) ) return $codes;

	$lines = file( $filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	if ( ! is_array( $lines ) ) return $codes;

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line === '' ) continue;
		if ( strpos( $line, '#' ) === 0 ) continue;

		$norm = portalsluchu_normalize_postcode( $line );
		if ( $norm !== '' ) $codes[ $norm ] = true;
	}
	return $codes;
}

function portalsluchu_get_dojazd_for_postcode( $postcode ) {
	$postcode = portalsluchu_normalize_postcode( $postcode );

	$prices = array(
		1 => 100.0,
		2 => 200.0,
		3 => 300.0,
		4 => 400.0,
		5 => 450.0,
	);

	$plugin_dir = plugin_dir_path( __FILE__ );
	$kody_dir   = trailingslashit( $plugin_dir . 'kody' );

	$zones_files = array(
		1 => $kody_dir . 'strefa1.txt',
		2 => $kody_dir . 'strefa2.txt',
		3 => $kody_dir . 'strefa3.txt',
		4 => $kody_dir . 'strefa4.txt',
	);

	$zone = 5;

	if ( $postcode !== '' ) {
		foreach ( $zones_files as $zone_num => $file ) {
			$codes = portalsluchu_load_zone_codes( $file );
			if ( isset( $codes[ $postcode ] ) ) {
				$zone = $zone_num;
				break;
			}
		}
	}

	$price = isset( $prices[ $zone ] ) ? $prices[ $zone ] : 0.0;

	return array('zone' => $zone, 'price' => $price);
}

if ( ! function_exists( 'portalsluchu_dojazd_calculate_for_postcode' ) ) {
	function portalsluchu_dojazd_calculate_for_postcode( $postcode ) {
		return portalsluchu_get_dojazd_for_postcode( $postcode );
	}
}

function portalsluchu_ajax_dojazd_info() {
	$postcode = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';
	$info     = portalsluchu_get_dojazd_for_postcode( $postcode );

	$zone  = isset( $info['zone'] ) ? (int) $info['zone'] : 5;
	$price = isset( $info['price'] ) ? (float) $info['price'] : 0.0;

	wp_send_json_success( array(
		'zone'            => $zone,
		'price'           => $price,
		'price_formatted' => function_exists( 'wc_price' ) ? wc_price( $price ) : (string) $price,
	) );
}
add_action( 'wp_ajax_portalsluchu_dojazd_info', 'portalsluchu_ajax_dojazd_info' );
add_action( 'wp_ajax_nopriv_portalsluchu_dojazd_info', 'portalsluchu_ajax_dojazd_info' );