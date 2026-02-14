<?php
/**
 * Plugin Name: portalsluchu – Dostawa na stronie "Kasa"
 * Description: Forma dostawy na /kasa: dojazd / salon / kurier (20 zł). Dolicza opłaty za dojazd i kuriera. Dla kurier+dojazd wymusza Woo shipping address.
 * Author: portalsluchu.pl
 * Version: 1.4.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PORTALSLUCHU_LISTING_FEE_PRODUCT_ID' ) ) {
	define( 'PORTALSLUCHU_LISTING_FEE_PRODUCT_ID', 1087 );
}
if ( ! defined( 'PORTALSLUCHU_KURIER_FLAT_FEE' ) ) {
	define( 'PORTALSLUCHU_KURIER_FLAT_FEE', 20.0 );
}

function portalsluchu_kasa_is_real_checkout() {
	return function_exists( 'is_checkout' )
		&& is_checkout()
		&& ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() );
}

function portalsluchu_kasa_cart_has_only_listing_fee() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}
	$items = WC()->cart->get_cart();
	if ( empty( $items ) ) {
		return false;
	}
	foreach ( $items as $cart_item ) {
		if ( empty( $cart_item['product_id'] ) ) {
			return false;
		}
		if ( (int) $cart_item['product_id'] !== (int) PORTALSLUCHU_LISTING_FEE_PRODUCT_ID ) {
			return false;
		}
	}
	return true;
}

function portalsluchu_kasa_get_checkout_post_data_array() {
	$post_data = array();

	if ( isset( $_POST['post_data'] ) ) {
		parse_str( (string) wp_unslash( $_POST['post_data'] ), $post_data );
		return is_array( $post_data ) ? $post_data : array();
	}

	foreach ( $_POST as $k => $v ) {
		$post_data[ $k ] = is_string( $v ) ? wp_unslash( $v ) : $v;
	}
	return $post_data;
}

function portalsluchu_kasa_get_selected_delivery_method_from_request() {
	$pd = portalsluchu_kasa_get_checkout_post_data_array();

	if ( ! empty( $pd['portalsluchu_delivery_method'] ) ) {
		return sanitize_text_field( (string) $pd['portalsluchu_delivery_method'] );
	}

	if ( function_exists( 'WC' ) && WC()->session ) {
		$stored = WC()->session->get( 'portalsluchu_checkout_data' );
		if ( is_array( $stored ) && ! empty( $stored['delivery_method'] ) ) {
			return (string) $stored['delivery_method'];
		}
	}

	return 'salon';
}

function portalsluchu_kasa_selected_method_requires_shipping() {
	if ( ! portalsluchu_kasa_is_real_checkout() ) {
		return false;
	}
	if ( portalsluchu_kasa_cart_has_only_listing_fee() ) {
		return false;
	}
	$method = portalsluchu_kasa_get_selected_delivery_method_from_request();
	return in_array( $method, array( 'kurier', 'dojazd' ), true );
}

function portalsluchu_kasa_get_active_postcode_from_post_data( array $pd ) {
	$shipping_postcode = ! empty( $pd['shipping_postcode'] ) ? sanitize_text_field( (string) $pd['shipping_postcode'] ) : '';
	$billing_postcode  = ! empty( $pd['billing_postcode'] )  ? sanitize_text_field( (string) $pd['billing_postcode'] )  : '';
	$use_shipping      = ! empty( $pd['ship_to_different_address'] );

	return ( $use_shipping && $shipping_postcode ) ? $shipping_postcode : $billing_postcode;
}

/**
 * Filtry mogą być wołane z różną liczbą argumentów (u Ciebie 1), więc przyjmujemy 1.
 */
add_filter( 'woocommerce_cart_needs_shipping', 'portalsluchu_kasa_force_needs_shipping', 20 );
function portalsluchu_kasa_force_needs_shipping( $needs_shipping ) {
	return portalsluchu_kasa_selected_method_requires_shipping() ? true : $needs_shipping;
}

add_filter( 'woocommerce_cart_needs_shipping_address', 'portalsluchu_kasa_force_needs_shipping_address', 20 );
function portalsluchu_kasa_force_needs_shipping_address( $needs_address ) {
	return portalsluchu_kasa_selected_method_requires_shipping() ? true : $needs_address;
}

add_filter( 'woocommerce_product_needs_shipping', 'portalsluchu_kasa_force_product_needs_shipping', 20, 2 );
function portalsluchu_kasa_force_product_needs_shipping( $needs_shipping, $product ) {
	return portalsluchu_kasa_selected_method_requires_shipping() ? true : $needs_shipping;
}

/**
 * Sekcja "Forma dostawy" – nie pokazujemy jej dla fee-only (1087).
 */
function portalsluchu_kasa_render_checkout_section( $checkout = null ) {
	if ( ! portalsluchu_kasa_is_real_checkout() ) {
		return;
	}
	if ( ! function_exists( 'WC' ) ) {
		return;
	}
	if ( portalsluchu_kasa_cart_has_only_listing_fee() ) {
		return;
	}

	$GLOBALS['portalsluchu_kasa_rendered'] = true;

	$delivery_method = 'salon';
	$delivery_salon  = '';

	if ( WC()->session ) {
		$saved = WC()->session->get( 'portalsluchu_checkout_data' );
		if ( is_array( $saved ) ) {
			if ( ! empty( $saved['delivery_method'] ) ) {
				$delivery_method = $saved['delivery_method'];
			}
			if ( ! empty( $saved['delivery_salon'] ) ) {
				$delivery_salon = $saved['delivery_salon'];
			}
		}
	}
	?>
	<div class="portalsluchu-kasa-box" style="margin-top:20px; padding:15px; border:1px solid #ddd;">
		<h3>Forma dostawy</h3>

		<p><label>
			<input type="radio" name="portalsluchu_delivery_method" value="dojazd" <?php checked( $delivery_method, 'dojazd' ); ?> />
			Dojazd do klienta (cena zależna od kodu pocztowego)
		</label></p>

		<p><label>
			<input type="radio" name="portalsluchu_delivery_method" value="salon" <?php checked( $delivery_method, 'salon' ); ?> />
			Odbiór w salonie
		</label></p>

		<div id="portalsluchu_delivery_salon_box" style="margin-left:15px; margin-bottom:10px; <?php echo ( $delivery_method === 'salon' ) ? '' : 'display:none;'; ?>">
			<p>
				<select name="portalsluchu_delivery_salon" style="max-width:320px;">
					<option value="">– wybierz lokalizację –</option>
					<option value="Warszawa, ul. Marszałkowska 2" <?php selected( $delivery_salon, 'Warszawa, ul. Marszałkowska 2' ); ?>>Warszawa, ul. Marszałkowska 2</option>
					<option value="Kraków, Jana Pawła 2 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 2 lok. 33' ); ?>>Kraków, Jana Pawła 2 lok. 33</option>
					<option value="Kraków, Jana Pawła 44 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 44 lok. 33' ); ?>>Kraków, Jana Pawła 44 lok. 33</option>
					<option value="Kraków, Jana Pawła 92 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 92 lok. 33' ); ?>>Kraków, Jana Pawła 92 lok. 33</option>
					<option value="Kraków, Jana Pawła 154 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 154 lok. 33' ); ?>>Kraków, Jana Pawła 154 lok. 33</option>
					<option value="Kraków, Jana Pawła 254 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 254 lok. 33' ); ?>>Kraków, Jana Pawła 254 lok. 33</option>
				</select>
			</p>
		</div>

		<p><label>
			<input type="radio" name="portalsluchu_delivery_method" value="kurier" <?php checked( $delivery_method, 'kurier' ); ?> />
			Kurier (stała opłata 20 zł)
		</label></p>
	</div>

	<script>
	(function($) {
		function refreshBoxes() {
			var val = $('input[name="portalsluchu_delivery_method"]:checked').val();
			if (!val) val = 'salon';
			$('#portalsluchu_delivery_salon_box').toggle(val === 'salon');
		}

		$(document).on('change', 'input[name="portalsluchu_delivery_method"], select[name="portalsluchu_delivery_salon"]', function() {
			refreshBoxes();
			$('body').trigger('update_checkout');
		});

		$(document).on('change input', '#billing_postcode, #shipping_postcode, input[name="ship_to_different_address"], #ship-to-different-address-checkbox', function() {
			$('body').trigger('update_checkout');
		});

		$(document).ready(refreshBoxes);
	})(jQuery);
	</script>
	<?php
}
add_action( 'woocommerce_review_order_before_payment', 'portalsluchu_kasa_render_checkout_section', 5 );

function portalsluchu_kasa_store_session_data() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	if ( ! portalsluchu_kasa_is_real_checkout() ) {
		return;
	}
	if ( portalsluchu_kasa_cart_has_only_listing_fee() ) {
		return;
	}

	$pd = portalsluchu_kasa_get_checkout_post_data_array();
	if ( empty( $pd['portalsluchu_delivery_method'] ) ) {
		return;
	}

	$delivery_method = sanitize_text_field( (string) $pd['portalsluchu_delivery_method'] );
	$delivery_salon  = ! empty( $pd['portalsluchu_delivery_salon'] )
		? sanitize_text_field( (string) $pd['portalsluchu_delivery_salon'] )
		: '';

	WC()->session->set( 'portalsluchu_checkout_data', array(
		'delivery_method' => $delivery_method,
		'delivery_salon'  => $delivery_salon,
	) );
}
add_action( 'woocommerce_checkout_update_order_review', 'portalsluchu_kasa_store_session_data' );

/**
 * Doliczanie opłat kurier/dojazd – nie dotyczy fee-only.
 */
function portalsluchu_kasa_add_fees( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	if ( ! portalsluchu_kasa_is_real_checkout() ) {
		return;
	}
	if ( portalsluchu_kasa_cart_has_only_listing_fee() ) {
		return;
	}

	$data = WC()->session->get( 'portalsluchu_checkout_data' );
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$delivery_method = isset( $data['delivery_method'] ) ? (string) $data['delivery_method'] : 'salon';

	if ( $delivery_method === 'kurier' ) {
		$cart->add_fee( 'Kurier', (float) PORTALSLUCHU_KURIER_FLAT_FEE );
		return;
	}

	if ( $delivery_method !== 'dojazd' ) {
		return;
	}

	$pd = portalsluchu_kasa_get_checkout_post_data_array();
	$postcode_raw = portalsluchu_kasa_get_active_postcode_from_post_data( $pd );

	$digits = preg_replace( '/\D+/', '', (string) $postcode_raw );
	if ( strlen( $digits ) !== 5 ) {
		return;
	}

	$postcode = substr( $digits, 0, 2 ) . '-' . substr( $digits, 2, 3 );

	if ( function_exists( 'portalsluchu_dojazd_calculate_for_postcode' ) ) {
		$res   = portalsluchu_dojazd_calculate_for_postcode( $postcode );
		$zone  = isset( $res['zone'] ) ? (int) $res['zone'] : 5;
		$price = isset( $res['price'] ) ? (float) $res['price'] : 0.0;

		if ( $price > 0 ) {
			$cart->add_fee( 'Dojazd do klienta (strefa ' . $zone . ')', $price );
		}
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'portalsluchu_kasa_add_fees', 25 );

/**
 * Checkout: kolejność pól adresu + pole "Numer domu" + FV na końcu.
 * Dodatkowo: ukrycie kraju (zostaje PL w tle) i zmiana label miasta.
 */
add_filter( 'woocommerce_checkout_fields', function( $fields ) {

	// --- BILLING: label miasta ---
	if ( isset( $fields['billing']['billing_city'] ) ) {
		$fields['billing']['billing_city']['label'] = 'Miasto (wysyłka tylko na terenie Polski) *';
		$fields['billing']['billing_city']['priority'] = 40;
	}

	if ( isset( $fields['billing']['billing_postcode'] ) ) {
		$fields['billing']['billing_postcode']['priority'] = 45;
	}

	if ( isset( $fields['billing']['billing_address_1'] ) ) {
		$fields['billing']['billing_address_1']['priority'] = 50;
	}

	// Numer domu po ulicy
	if ( ! isset( $fields['billing']['billing_house_number'] ) ) {
		$fields['billing']['billing_house_number'] = array(
			'type'         => 'text',
			'label'        => 'Numer domu',
			'required'     => true,
			'class'        => array( 'form-row-first' ),
			'priority'     => 55,
			'autocomplete' => 'address-line2',
		);
	} else {
		$fields['billing']['billing_house_number']['priority'] = 55;
	}

	// Ciąg dalszy adresu po numerze domu
	if ( isset( $fields['billing']['billing_address_2'] ) ) {
		$fields['billing']['billing_address_2']['priority'] = 60;
	}

	// FV na koniec
	if ( isset( $fields['billing']['portalsluchu_want_invoice'] ) ) {
		$fields['billing']['portalsluchu_want_invoice']['priority'] = 200;
	}

	// Ukryj kraj (zostaw wartość PL; nie usuwamy pola żeby nic nie popsuć)
	if ( isset( $fields['billing']['billing_country'] ) ) {
		$fields['billing']['billing_country']['required'] = false;
		$fields['billing']['billing_country']['class'][] = 'ps-hide-country';
		$fields['billing']['billing_country']['priority'] = 999;
	}

	// --- SHIPPING: analogicznie jeśli używasz ---
	if ( isset( $fields['shipping']['shipping_city'] ) ) {
		$fields['shipping']['shipping_city']['label'] = 'Miasto (wysyłka tylko na terenie Polski) *';
		$fields['shipping']['shipping_city']['priority'] = 40;
	}
	if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
		$fields['shipping']['shipping_postcode']['priority'] = 45;
	}
	if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
		$fields['shipping']['shipping_address_1']['priority'] = 50;
	}
	if ( isset( $fields['shipping'] ) ) {
		if ( ! isset( $fields['shipping']['shipping_house_number'] ) ) {
			$fields['shipping']['shipping_house_number'] = array(
				'type'         => 'text',
				'label'        => 'Numer domu',
				'required'     => true,
				'class'        => array( 'form-row-first' ),
				'priority'     => 55,
				'autocomplete' => 'address-line2',
			);
		} else {
			$fields['shipping']['shipping_house_number']['priority'] = 55;
		}
	}
	if ( isset( $fields['shipping']['shipping_address_2'] ) ) {
		$fields['shipping']['shipping_address_2']['priority'] = 60;
	}
	if ( isset( $fields['shipping']['shipping_country'] ) ) {
		$fields['shipping']['shipping_country']['required'] = false;
		$fields['shipping']['shipping_country']['class'][] = 'ps-hide-country';
		$fields['shipping']['shipping_country']['priority'] = 999;
	}

	return $fields;
}, 50 );

/**
 * Wymuś PL w tle (na wypadek gdy pole country będzie ukryte).
 */
add_action( 'woocommerce_checkout_process', function() {
	if ( isset( $_POST['billing_country'] ) ) {
		$_POST['billing_country'] = 'PL';
	}
	if ( isset( $_POST['shipping_country'] ) ) {
		$_POST['shipping_country'] = 'PL';
	}
}, 5 );

/**
 * Zapisz "Numer domu" w meta zamówienia i dopisz do address_1.
 */
add_action( 'woocommerce_checkout_create_order', function( $order, $data ) {

	$billing_house = isset( $_POST['billing_house_number'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_house_number'] ) ) : '';
	if ( $billing_house !== '' ) {
		$order->update_meta_data( '_billing_house_number', $billing_house );
		$addr1 = (string) $order->get_billing_address_1();
		$order->set_billing_address_1( trim( $addr1 . ' ' . $billing_house ) );
	}

	$shipping_house = isset( $_POST['shipping_house_number'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_house_number'] ) ) : '';
	if ( $shipping_house !== '' ) {
		$order->update_meta_data( '_shipping_house_number', $shipping_house );
		$addr1 = (string) $order->get_shipping_address_1();
		$order->set_shipping_address_1( trim( $addr1 . ' ' . $shipping_house ) );
	}

}, 30, 2 );

/**
 * Deadline fix:
 * - po każdym updated_checkout przestawiamy kolejność pól w DOM (bo AJAX ją nadpisuje),
 * - ukrywamy kraj,
 * - dbamy żeby pola firmy/NIP pojawiały się POD checkboxem FV.
 *
 * UWAGA: pola NIP/firma mają różne ID zależnie od wtyczki – obsługujemy najczęstsze.
 */
add_action( 'wp_footer', function() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
	?>
	<style>
		/* Ukrycie kraju/regionu (zostaje PL w tle) */
		.ps-hide-country,
		#billing_country_field,
		#shipping_country_field{
			display:none !important;
		}
	</style>
	<script>
	(function($){
		function firstExistingField(selectorList){
			for (var i=0;i<selectorList.length;i++){
				var $el = $(selectorList[i]);
				if ($el.length) return $el;
			}
			return $();
		}

		function reorderBillingFields(){
			var $wrap = $('.woocommerce-billing-fields__field-wrapper').first();
			if (!$wrap.length) return;

			// Pola fakturowe – różne wtyczki używają różnych ID
			var $company = firstExistingField(['#billing_company_field', '#portalsluchu_company_field']);
			var $nip     = firstExistingField(['#billing_nip_field', '#billing_vat_field', '#billing_tax_number_field', '#portalsluchu_nip_field']);

			var ordered = [
				$('#billing_city_field'),
				$('#billing_postcode_field'),
				$('#billing_address_1_field'),
				$('#billing_house_number_field'),
				$('#billing_address_2_field'),
				$('#portalsluchu_want_invoice_field')
			];

			ordered.forEach(function($el){
				if ($el && $el.length) $wrap.append($el);
			});

			// Firma + NIP zawsze pod FV (nawet jeśli pokazują się dopiero po kliknięciu)
			if ($company.length) $wrap.append($company);
			if ($nip.length) $wrap.append($nip);
		}

		// initial + after every ajax refresh
		$(document).on('updated_checkout', function(){
			reorderBillingFields();
		});
		$(document).ready(function(){
			reorderBillingFields();
		});

		// dodatkowo: po kliknięciu FV często wtyczka pokazuje pola bez update_checkout
		$(document).on('change', '#portalsluchu_want_invoice', function(){
			setTimeout(reorderBillingFields, 0);
			setTimeout(reorderBillingFields, 150);
		});

	})(jQuery);
	</script>
	<?php
}, 999 );