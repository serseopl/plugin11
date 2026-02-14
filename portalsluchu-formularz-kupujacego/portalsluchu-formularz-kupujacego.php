<?php
/**
 * Plugin Name: portalsluchu – Formularz kupującego
 * Description: Formularz „KUP” na karcie produktu. Zapisuje do sesji WooCommerce: prowizję (99 zł), opcjonalne przystosowanie (100 zł) i gwarancję (5 dni lub 1/2/3 lata wg meta produktu). Dostawa wybierana jest na /kasa.
 * Author: portalsluchu.pl
 * Version: 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function portalsluchu_kup_form_shortcode( $atts ) {
	if ( ! function_exists( 'WC' ) ) {
		return '<p>WooCommerce jest wymagany do działania tego formularza.</p>';
	}

/*
if ( ! is_user_logged_in() ) {
	return '<div style="padding:12px 16px; border:2px solid #cc0000; background:#ffecec; color:#cc0000; font-size:20px; font-weight:700; line-height:1.4; margin:16px 0;">Aby kupić aparat, zaloguj się lub załóż konto.</div>';
}
	*/

	if ( ! is_user_logged_in() ) {
	$account_url = home_url( '/moje-konto/' );

	return '
	<div style="
		margin: 18px 0;
		padding: 14px 18px;
		border-radius: 14px;
		border: 1px solid rgba(220, 38, 38, .35);
		background: linear-gradient(180deg, rgba(254, 242, 242, 1) 0%, rgba(255, 255, 255, 1) 100%);
		box-shadow: 0 10px 24px rgba(0,0,0,.08);
		color: #991b1b;
		font-size: 19px;
		font-weight: 700;
		line-height: 1.4;
		font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, \"Apple Color Emoji\", \"Segoe UI Emoji\";
		letter-spacing: .2px;
	">
		Aby kupić aparat,
		<a href="' . esc_url( $account_url ) . '" style="color:#7f1d1d; text-decoration: underline; font-weight: 800;">
			zaloguj się
		</a>
		lub
		<a href="' . esc_url( $account_url ) . '" style="color:#7f1d1d; text-decoration: underline; font-weight: 800;">
			załóż konto
		</a>.
		<div style="margin-top:6px; font-size: 14px; font-weight: 600; color: rgba(153,27,27,.78);">
			To zajmie chwilę i pozwoli dokończyć zakup.
		</div>
	</div>';
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

	$base_price = (float) $product->get_price();

	// Stałe opłaty
	$prowizja_price      = 99.0;  // zawsze
	$przystosowanie_price = 100.0; // opcjonalnie

	// Maksymalna gwarancja z meta produktu (1–3 lata)
	$max_warranty = get_post_meta( $product_id, 'portalsluchu_max_warranty_years', true );
	$max_warranty = $max_warranty ? (int) $max_warranty : 1;
	if ( $max_warranty < 1 || $max_warranty > 3 ) {
		$max_warranty = 1;
	}

	// Domyślnie: 5 dni rozruchowej
	$warranty_years = 0;

	// Domyślnie: przystosowanie niezaznaczone
	$przystosowanie = 0;

	if (
		$_SERVER['REQUEST_METHOD'] === 'POST'
		&& isset( $_POST['portalsluchu_kup_nonce'] )
		&& wp_verify_nonce( $_POST['portalsluchu_kup_nonce'], 'portalsluchu_kup_form' )
	) {
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

		$przystosowanie = ! empty( $_POST['portalsluchu_przystosowanie'] ) ? 1 : 0;

		// Zapis do sesji – to będzie użyte na /kasa do naliczenia fee
		$data = array(
			'product_id'             => $product_id,

			// Prowizja (zawsze)
			'prowizja_enable'        => 1,
			'prowizja_price'         => $prowizja_price,

			// Przystosowanie (opcjonalnie)
			'przystosowanie_enable'  => $przystosowanie,
			'przystosowanie_price'   => $przystosowanie ? $przystosowanie_price : 0.0,

			// Gwarancja
			'warranty_years'         => $warranty_years,
			'warranty_price'         => $warranty_price,
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
	<p><strong>Aparat:</strong> <?php echo esc_html( $product->get_name() ); ?>, <br />cena aparatu: <?php echo wc_price( $base_price ); ?></p>

	<form method="post" class="portalsluchu-kup-form" id="portalsluchu_kup_form">
		<?php wp_nonce_field( 'portalsluchu_kup_form', 'portalsluchu_kup_nonce' ); ?>

		<h3>Opłaty</h3>

		<p>
			<label>
				<input type="checkbox" checked="checked" disabled="disabled">
				Prowizja za sprawdzenie oraz zakup aparatu słuchowego przez nasz portal – <?php echo wc_price( $prowizja_price ); ?>
			</label>
			<input type="hidden" name="portalsluchu_prowizja" value="1">
		</p>

		<p style="margin-top:12px;">
			<label>
				<input type="checkbox" name="portalsluchu_przystosowanie" value="1" <?php checked( $przystosowanie, 1 ); ?>>
				Przystosowanie aparatu słuchowego do Twojego ubytku słuchu – <?php echo wc_price( $przystosowanie_price ); ?>
			</label>

			<details style="margin-top:6px; margin-left:22px;">
				<summary>Szczegóły</summary>
				<p style="margin:6px 0 0;">
					Zakup aparatu słuchowego przez nasz portal nie oznacza, że aparat jest dopasowany do Twojego ubytku słuchu.
				</p>
			</details>
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
 * Doliczanie opłat (prowizja + przystosowanie + gwarancja płatna) na podstawie sesji.
 * 5 dni gwarancji nie dodaje fee.
 */
function portalsluchu_kup_add_fees( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

	$data = WC()->session->get( 'portalsluchu_kup_data' );
	if ( ! $data || ! is_array( $data ) ) return;

	// Prowizja (zawsze)
	if ( ! empty( $data['prowizja_enable'] ) && ! empty( $data['prowizja_price'] ) ) {
		$cart->add_fee( 'Prowizja', (float) $data['prowizja_price'] );
	}

	// Przystosowanie (opcjonalne)
	if ( ! empty( $data['przystosowanie_enable'] ) && ! empty( $data['przystosowanie_price'] ) ) {
		$cart->add_fee( 'Przystosowanie aparatu słuchowego do Twojego ubytku słuchu', (float) $data['przystosowanie_price'] );
	}

	// Gwarancja (tylko płatne warianty)
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
 * Informacja o gwarancji w mailach i szczegółach zamówienia.
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
add_action( 'admin_post_ps_listing_fee', 'ps_listing_fee_post_handler' );
add_action( 'admin_post_nopriv_ps_listing_fee', 'ps_listing_fee_post_handler' );

function ps_listing_fee_post_handler() {
	if ( ! function_exists( 'WC' ) ) {
		wp_safe_redirect( home_url() );
		exit;
	}

	// Nonce
	if ( empty( $_POST['ps_listing_fee_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ps_listing_fee_nonce'] ) ), 'ps_listing_fee' ) ) {
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	$fee_product_id = isset( $_POST['fee_product_id'] ) ? (int) $_POST['fee_product_id'] : 0;
	if ( $fee_product_id <= 0 ) {
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	if ( null === WC()->cart ) {
		wc_load_cart();
	}

	WC()->cart->empty_cart( true );
	$added = WC()->cart->add_to_cart( $fee_product_id, 1 );

	if ( $added ) {
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	wp_safe_redirect( wc_get_cart_url() );
	exit;
}