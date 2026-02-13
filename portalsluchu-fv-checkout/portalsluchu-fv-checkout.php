<?php
/**
 * Plugin Name: portalsluchu – FV (checkbox + firma + NIP) na checkout
 * Description: Dodaje checkbox "Chcę FV". Po zaznaczeniu pokazuje pola "Firma" i "NIP". Zapisuje do meta zamówienia oraz do billing_vat. Nie polega na billing_company (bo może być usunięte przez builder).
 * Author: portalsluchu.pl
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'woocommerce_checkout_fields', 'portalsluchu_fv_add_fields', 30 );
function portalsluchu_fv_add_fields( $fields ) {
	$fields['billing']['portalsluchu_want_invoice'] = array(
		'type'     => 'checkbox',
		'label'    => 'Chcę FV',
		'required' => false,
		'class'    => array( 'form-row-wide' ),
		'priority' => 65,
	);

	$fields['billing']['portalsluchu_invoice_company'] = array(
		'type'        => 'text',
		'label'       => 'Firma (wymagane do FV)',
		'placeholder' => 'Nazwa firmy',
		'required'    => false, // wymagamy w walidacji tylko gdy checkbox FV zaznaczony
		'class'       => array( 'form-row-wide' ),
		'priority'    => 66,
	);

	$fields['billing']['portalsluchu_billing_nip'] = array(
		'type'        => 'text',
		'label'       => 'NIP (wymagany do FV)',
		'placeholder' => 'np. 1234567890',
		'required'    => false,
		'class'       => array( 'form-row-wide' ),
		'priority'    => 67,
	);

	return $fields;
}

add_action( 'wp_footer', 'portalsluchu_fv_checkout_js', 50 );
function portalsluchu_fv_checkout_js() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) return;
	?>
	<script>
	(function($){
		function toggleFVFields(){
			var checked = $('#portalsluchu_want_invoice').is(':checked');
			var $company = $('#portalsluchu_invoice_company_field');
			var $nip     = $('#portalsluchu_billing_nip_field');

			if ($company.length) $company.toggle(checked);
			if ($nip.length)     $nip.toggle(checked);
		}

		function normalizeLabels(){
			var $nipLabel = $('#portalsluchu_billing_nip_field label');
			if ($nipLabel.length) $nipLabel.text('NIP (wymagany do FV)');

			var $coLabel = $('#portalsluchu_invoice_company_field label');
			if ($coLabel.length) $coLabel.text('Firma (wymagane do FV)');
		}

		$(document).on('change', '#portalsluchu_want_invoice', function(){
			toggleFVFields();
			$('body').trigger('update_checkout');
		});

		$(document).ready(function(){
			toggleFVFields();
			normalizeLabels();
		});
		$(document.body).on('updated_checkout', function(){
			toggleFVFields();
			normalizeLabels();
		});
	})(jQuery);
	</script>
	<?php
}

add_action( 'woocommerce_checkout_process', 'portalsluchu_fv_validate', 30 );
function portalsluchu_fv_validate() {
	$want = ! empty( $_POST['portalsluchu_want_invoice'] );
	if ( ! $want ) return;

	$company = isset( $_POST['portalsluchu_invoice_company'] )
		? trim( (string) wp_unslash( $_POST['portalsluchu_invoice_company'] ) )
		: '';

	if ( $company === '' ) {
		wc_add_notice( 'Podaj nazwę firmy, jeśli chcesz FV.', 'error' );
	}

	$nip = isset( $_POST['portalsluchu_billing_nip'] )
		? preg_replace( '/\s+/', '', (string) wp_unslash( $_POST['portalsluchu_billing_nip'] ) )
		: '';

	$nip_digits = preg_replace( '/\D+/', '', $nip );
	if ( $nip_digits === '' ) {
		wc_add_notice( 'Podaj NIP, jeśli chcesz FV.', 'error' );
	}
}

add_action( 'woocommerce_checkout_create_order', 'portalsluchu_fv_save_meta', 30, 2 );
function portalsluchu_fv_save_meta( $order, $data ) {
	$want    = ! empty( $_POST['portalsluchu_want_invoice'] );
	$company = isset( $_POST['portalsluchu_invoice_company'] ) ? sanitize_text_field( wp_unslash( $_POST['portalsluchu_invoice_company'] ) ) : '';
	$nip     = isset( $_POST['portalsluchu_billing_nip'] ) ? sanitize_text_field( wp_unslash( $_POST['portalsluchu_billing_nip'] ) ) : '';

	$order->update_meta_data( '_portalsluchu_want_invoice', $want ? '1' : '0' );

	if ( $want ) {
		$order->update_meta_data( '_portalsluchu_invoice_company', $company );
		$order->update_meta_data( '_portalsluchu_billing_nip', $nip );

		// zgodnie z Twoim wymaganiem:
		$order->update_meta_data( '_billing_vat', $nip );

		// opcjonalnie kopiujemy firmę do standardowego pola Woo, żeby było spójnie w panelu:
		$order->update_meta_data( '_billing_company', $company );
	}
}