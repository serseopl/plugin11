<?php
/*
Plugin Name: portalsluchu – Formularz sprzedającego
Description: portalsluchu-formularz-sprzedajacego / Formularz do zgłaszania sprzedaży aparatu słuchowego, z obsługą kodu rabatowego oraz opłaty 10 zł przez WooCommerce.
Author: portalsluchu.pl
Version: 1.1.0
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode: [portalsluchu_formularz_sprzedajacy]
 */
function portalsluchu_formularz_sprzedajacego_shortcode() {
    ob_start();
    include __DIR__ . '/form-template.php';
    return ob_get_clean();
}
add_shortcode( 'portalsluchu_formularz_sprzedajacy', 'portalsluchu_formularz_sprzedajacego_shortcode' );

/**
 * Po utworzeniu zamówienia zapisujemy ID ogłoszenia (jeżeli istnieje w sesji)
 * do meta zamówienia.
 */
function portalsluchu_attach_listing_to_order( $order, $data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $listing_id = WC()->session->get( 'portalsluchu_listing_id' );
    if ( $listing_id ) {
        $order->update_meta_data( '_portalsluchu_listing_id', intval( $listing_id ) );
        WC()->session->__unset( 'portalsluchu_listing_id' );
    }
}
add_action( 'woocommerce_checkout_create_order', 'portalsluchu_attach_listing_to_order', 10, 2 );

/**
 * Meta box z informacją o statusie opłaty przy produkcie.
 */
function portalsluchu_add_payment_status_metabox() {
    add_meta_box(
        'portalsluchu_payment_status',
        'Status opłaty za wystawienie',
        'portalsluchu_payment_status_metabox_cb',
        'product',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'portalsluchu_add_payment_status_metabox' );

function portalsluchu_payment_status_metabox_cb( $post ) {
    wp_nonce_field( 'portalsluchu_save_payment_status', 'portalsluchu_payment_status_nonce' );

    $status = get_post_meta( $post->ID, 'listing_payment_status', true );
    if ( ! $status ) {
        $status = 'pending_payment';
    }

    $options = array(
        'pending_payment' => 'Oczekuje na opłatę',
        'paid'            => 'Opłacone (płatność online / przelew)',
        'coupon'          => 'Opłacone kodem',
    );
    ?>
    <p><strong>Aktualny status:</strong></p>
    <p>
        <select name="portalsluchu_payment_status" id="portalsluchu_payment_status">
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p style="font-size:12px; color:#555;">
        • Jeśli formularz przyszedł z kodem – będzie ustawione „Opłacone kodem”.<br>
        • Po zaksięgowaniu przelewu możesz zmienić na „Opłacone (płatność online / przelew)”.
    </p>
    <?php
}

function portalsluchu_save_payment_status_metabox( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! isset( $_POST['portalsluchu_payment_status_nonce'] ) ||
         ! wp_verify_nonce( $_POST['portalsluchu_payment_status_nonce'], 'portalsluchu_save_payment_status' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['portalsluchu_payment_status'] ) ) {
        $status = sanitize_text_field( $_POST['portalsluchu_payment_status'] );
        update_post_meta( $post_id, 'listing_payment_status', $status );
    }
}
add_action( 'save_post_product', 'portalsluchu_save_payment_status_metabox' );

/**
 * Kolumna "Opłata" w liście produktów WooCommerce.
 */
function portalsluchu_add_payment_status_column( $columns ) {
    $new_columns = array();

    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;

        if ( in_array( $key, array( 'price', 'stock' ), true ) ) {
            if ( ! isset( $new_columns['portalsluchu_payment'] ) ) {
                $new_columns['portalsluchu_payment'] = 'Opłata';
            }
        }
    }

    if ( ! isset( $new_columns['portalsluchu_payment'] ) ) {
        $new_columns['portalsluchu_payment'] = 'Opłata';
    }

    return $new_columns;
}
add_filter( 'manage_edit-product_columns', 'portalsluchu_add_payment_status_column', 20 );

function portalsluchu_render_payment_status_column( $column, $post_id ) {
    if ( 'portalsluchu_payment' !== $column ) {
        return;
    }

    $status = get_post_meta( $post_id, 'listing_payment_status', true );

    if ( 'coupon' === $status || 'paid' === $status ) {
        echo '<span style="color:#008000;font-weight:600;">Opłacone</span>';
    } else {
        echo '<span style="color:#cc0000;font-weight:600;">Do opłaty</span>';
    }
}
add_action( 'manage_product_posts_custom_column', 'portalsluchu_render_payment_status_column', 10, 2 );

/**
 * Shortcody z danymi zalogowanego użytkownika.
 */
function portalsluchu_wc_user_name_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '';
    }

    $user = wp_get_current_user();
    $name = trim( $user->first_name . ' ' . $user->last_name );
    if ( ! $name ) {
        $name = $user->display_name;
    }

    return esc_html( $name );
}
add_shortcode( 'wc_user_name', 'portalsluchu_wc_user_name_shortcode' );

function portalsluchu_wc_user_phone_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '';
    }

    $user  = wp_get_current_user();
    $phone = get_user_meta( $user->ID, 'billing_phone', true );

    return esc_html( $phone );
}
add_shortcode( 'wc_user_phone', 'portalsluchu_wc_user_phone_shortcode' );

function portalsluchu_wc_user_email_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '';
    }

    $user  = wp_get_current_user();
    $email = $user->user_email;

    return esc_html( $email );
}
add_shortcode( 'wc_user_email', 'portalsluchu_wc_user_email_shortcode' );
// --- Portalsluchu: handler opłaty za wystawienie (variant B) ---
// Wklej ten blok na końcu pliku głównego pluginu.

add_action( 'template_redirect', 'ps_handle_listing_fee' );
function ps_handle_listing_fee() {
    if ( is_admin() ) {
        return;
    }

    // Sprawdzamy, czy to POST z naszego formularza
    if ( ! isset( $_POST['portalsluchu_submit'] ) ) {
        return;
    }

    // Walidacja nonce
    if ( empty( $_POST['portalsluchu_nonce'] ) || ! wp_verify_nonce( $_POST['portalsluchu_nonce'], 'portalsluchu_submit_action' ) ) {
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    // Jeśli wpisano kod darmowego wystawienia, pomijamy dodawanie opłaty
    $free_code = 'wystawzazero';
    $entered_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
    if ( $entered_code && strcasecmp( $entered_code, $free_code ) === 0 ) {
        // darmowe zgłoszenie — nic nie dodajemy
        return;
    }
 
    // ID produktu reprezentującego opłatę za wystawienie (USTAW TUTAJ ID z admina)
    $fee_product_id = 1087; // <-- ZMIEŃ NA SWOJE ID jeśli inny

    if ( ! class_exists( 'WooCommerce' ) ) {
        error_log( '[Portalsluchu] WooCommerce nie jest aktywny - nie można dodać opłaty do koszyka.' );
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    if ( null === WC()->cart ) {
        wc_load_cart();
    }

    // Opcjonalnie: wyczyść koszyk (usuń tę linię jeśli chcesz dodać opłatę obok innych produktów)
    WC()->cart->empty_cart( true );

    // Dodaj produkt opłaty do koszyka
    $added = WC()->cart->add_to_cart( intval( $fee_product_id ) );

    if ( $added ) {
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    } else {
        wc_add_notice( __( 'Wystąpił problem z dodaniem opłaty do koszyka. Spróbuj ponownie.', 'portalsluchu' ), 'error' );
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }
}