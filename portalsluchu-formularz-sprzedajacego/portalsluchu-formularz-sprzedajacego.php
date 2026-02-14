<?php
/**
 * Plugin Name: portalsluchu – Formularz sprzedającego
 * Description: Formularz sprzedaży aparatów słuchowych z opcją opłaty 10 zł lub kodu rabatowego
 * Author: portalsluchu.pl
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

add_shortcode( 'portalsluchu_formularz_sprzedajacy', 'portalsluchu_formularz_sprzedajacy_shortcode' );

function portalsluchu_formularz_sprzedajacy_shortcode() {
ob_start();
include plugin_dir_path( __FILE__ ) . 'form-template.php';
return ob_get_clean();
}
