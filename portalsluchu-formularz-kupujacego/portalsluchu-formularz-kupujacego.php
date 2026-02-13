<?php
/**
 * Plugin Name: portalsluchu – Formularz kupującego
 * Description: Formularz „KUP” na karcie produktu. Zapisuje do sesji WooCommerce pakiet startowy (+100 zł) i gwarancję (5 dni rozruchowej lub 1/2/3 lata wg meta produktu). Dostawa wybierana jest na /kasa.
 * Author: portalsluchu.pl
 * Version: 1.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode: [portalsluchu_kup_form]
 */
function portalsluchu_kup_form_shortcode( $atts ) {
	if ( ! function_exists( 'WC' ) ) {
		return '<p>WooCommerce jest wymagany do działania tego formularza.</p>';
	}

	if ( ! is_user_logged_in() ) {
		return '<p>Aby kupić aparat, zaloguj się lub załóż konto.</p>';
	}

	$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;

	if ( ! $product_id && is_singular( 'product' ) ) {
		$product_id = get_the_ID();
	}

	if ( ! $product_id ) {
		return '<p>Brak wybranego aparatu.</p>';
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return '<p>Wybrany aparat nie istnieje.</p>';
	}

	$base_price   = (float) $product->get_price();
	$pakiet_price = 100.0;

	// Maksymalna gwarancja z meta produktu (1–3 lata)
	$max_warranty = get_post_meta( $product_id, 'portalsluchu_max_warranty_years', true );
	$max_warranty = $max_warranty ? (int) $max_warranty : 1;
	if ( $max_warranty < 1 || $max_warranty > 3 ) {
		$max_warranty = 1;
	}

	// Domyślnie: 5 dni
	$warranty_years = 0;

	if ( $_SERVER['REQUEST_METHOD'] === 'POST'
		&& isset( $_POST['portalsluchu_kup_nonce'] )
		&& wp_verify_nonce( $_POST['portalsluchu_kup_nonce'], 'portalsluchu_kup_form' ) ) {

		$warranty_years = isset( $_POST['warranty_years'] ) ? (int) $_POST['warranty_years'] : 0;

		// Dozwolone: 0 (5 dni) albo 1..max_warranty
		if ( $warranty_years !== 0 && ( $warranty_years < 1 || $warranty_years > $max_warranty ) ) {
			$warranty_years = 0;
		}

		$warranty_price = 0.0;
		if ( $warranty_years === 1 ) {
			$warranty_price = 390.0;
		} elseif ( $warranty_years === 2 ) {
			$warranty_price = 490.0;
		} elseif ( $warranty_years === 3 ) {
			$warranty_price = 790.0;
		}

		$data = array(
			'product_id'      => $product_id,
			'pakiet_startowy' => 1,
			'pakiet_price'    => $pakiet_price,
			'warranty_years'  => $warranty_years,
			'warranty_price'  => $warranty_price,
		);

		if ( WC()->session ) {
			WC()->session->set( 'portalsluchu_kup_data', $data );
		}

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product_id, 1 );

		$redirect_url = function_exists( 'wc_get_checkout_url' )
			? wc_get_checkout_url()
			: wc_get_page_permalink( 'checkout' );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	ob_start();
	?>
	<h2>Formularz zakupu aparatu</h2>
	<p><strong>Aparat:</strong> <?php echo esc_html( $product->get_name() ); ?>, cena aparatu: <?php echo wc_price( $base_price ); ?></p>

	<form method="post" class="portalsluchu-kup-form" id="portalsluchu_kup_form">
		<?php wp_nonce_field( 'portalsluchu_kup_form', 'portalsluchu_kup_nonce' ); ?>

		<h3>Pakiet startowy</h3>
		<p>
			<label>
				<input type="checkbox" checked="checked" disabled="disabled">
				Pakiet startowy (<?php echo wc_price( $pakiet_price ); ?>) – w zestawie otrzymujesz kabelek oraz ładowarkę przetestowaną przez nasz serwis.
			</label>
			<input type="hidden" name="pakiet_startowy" value="1">
		</p>

		<hr>

		<h3>Dostawa</h3>
		<p style="margin-top:0; color:#555;">
			Formę dostawy (dojazd / odbiór w salonie / kurier) wybierzesz na stronie <strong>/kasa</strong>.
		</p>

		<hr>

		<h3>Gwarancja</h3>

		<p>
			<label>
				<input type="radio" name="warranty_years" value="0" <?php checked( $warranty_years, 0 ); ?> />
				5 dni gwarancji rozruchowej (w cenie)
			</label>
			<br>

			<label>
				<input type="radio" name="warranty_years" value="1" <?php checked( $warranty_years, 1 ); ?> />
				1 rok (+390 zł)
			</label>
			<br>

			<?php if ( $max_warranty >= 2 ) : ?>
				<label>
					<input type="radio" name="warranty_years" value="2" <?php checked( $warranty_years, 2 ); ?> />
					2 lata (+490 zł)
				</label>
				<br>
			<?php endif; ?>

			<?php if ( $max_warranty >= 3 ) : ?>
				<label>
					<input type="radio" name="warranty_years" value="3" <?php checked( $warranty_years, 3 ); ?> />
					3 lata (+790 zł)
				</label>
			<?php endif; ?>
		</p>

		<p style="margin-top:20px;">
			<button type="submit" class="button button-primary">Przejdź do opłacenia</button>
		</p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'portalsluchu_kup_form', 'portalsluchu_kup_form_shortcode' );

/**
 * Doliczanie opłat (pakiet + gwarancja płatna) na podstawie sesji.
 * 0 (5 dni) nie dodaje fee.
 */
function portalsluchu_kup_add_fees( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

	$data = WC()->session->get( 'portalsluchu_kup_data' );
	if ( ! $data || ! is_array( $data ) ) return;

	if ( ! empty( $data['pakiet_startowy'] ) && ! empty( $data['pakiet_price'] ) ) {
		$cart->add_fee( 'Pakiet startowy', (float) $data['pakiet_price'] );
	}

	$years = isset( $data['warranty_years'] ) ? (int) $data['warranty_years'] : 0;
	$price = isset( $data['warranty_price'] ) ? (float) $data['warranty_price'] : 0.0;

	if ( $years > 0 && $price > 0 ) {
		$cart->add_fee( 'Gwarancja ' . $years . ' lata', $price );
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'portalsluchu_kup_add_fees', 25, 1 );

/**
 * Zapis danych formularza w meta zamówienia.
 */
function portalsluchu_kup_save_order_meta( $order, $data ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

	$kup_data = WC()->session->get( 'portalsluchu_kup_data' );
	if ( ! $kup_data || ! is_array( $kup_data ) ) return;

	$order->update_meta_data( '_portalsluchu_kup_data', $kup_data );
	WC()->session->__unset( 'portalsluchu_kup_data' );
}
add_action( 'woocommerce_checkout_create_order', 'portalsluchu_kup_save_order_meta', 25, 2 );

/**
 * Wyświetlanie gwarancji w mailach i w szczegółach zamówienia (bez fee).
 */
function portalsluchu_kup_get_warranty_label_from_order( $order ) {
	$kup_data = $order->get_meta( '_portalsluchu_kup_data', true );
	if ( ! is_array( $kup_data ) ) return '';

	$years = isset( $kup_data['warranty_years'] ) ? (int) $kup_data['warranty_years'] : 0;

	if ( $years === 0 ) return '5 dni gwarancji rozruchowej';
	if ( $years === 1 ) return 'Gwarancja 1 rok';
	if ( $years === 2 ) return 'Gwarancja 2 lata';
	if ( $years === 3 ) return 'Gwarancja 3 lata';

	return '';
}

add_action( 'woocommerce_email_order_meta', function( $order, $sent_to_admin, $plain_text, $email ) {
	if ( ! $order instanceof WC_Order ) return;

	$label = portalsluchu_kup_get_warranty_label_from_order( $order );
	if ( ! $label ) return;

	if ( $plain_text ) {
		echo "\nGwarancja: " . $label . "\n";
	} else {
		echo '<p><strong>Gwarancja:</strong> ' . esc_html( $label ) . '</p>';
	}
}, 25, 4 );

add_action( 'woocommerce_order_details_after_order_table', function( $order ) {
	if ( ! $order instanceof WC_Order ) return;

	$label = portalsluchu_kup_get_warranty_label_from_order( $order );
	if ( ! $label ) return;

	echo '<p><strong>Gwarancja:</strong> ' . esc_html( $label ) . '</p>';
}, 25 );