<?php
/**
 * Plugin Name: portalsluchu – Formularz sprzedającego
 * Description: Formularz wystawienia aparatu na sprzedaż z opcją opłaty 10 zł (produkt 1087) lub kodem rabatowym.
 * Author: portalsluchu.pl
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

add_shortcode( 'portalsluchu_formularz_sprzedajacy', 'portalsluchu_formularz_sprzedajacy_shortcode' );

function portalsluchu_formularz_sprzedajacy_shortcode( $atts ) {
ob_start();
include __DIR__ . '/form-template.php';
return ob_get_clean();
}
