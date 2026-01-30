<?php
/*
Plugin Name: portalsluchu – dojazd strefy (kody pocztowe)
Description: Dodaje opłatę za dojazd na podstawie kodu pocztowego klienta (4 strefy z plików TXT + 5. strefa domyślna).
Author: portalsluchu.pl
Version: 1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Normalizuje kod pocztowy do formatu 00-000 (jeśli to możliwe).
 */
function portalsluchu_normalize_postcode( $code ) {
    $code = trim( (string) $code );
    if ( $code === '' ) {
        return '';
    }

    // Usuń spacje
    $code = str_replace( ' ', '', $code );

    // Jeżeli w formacie 00-000
    if ( preg_match( '/^[0-9]{2}-[0-9]{3}$/', $code ) ) {
        return $code;
    }

    // Jeżeli 5 cyfr, zamień na 00-000
    if ( preg_match( '/^[0-9]{5}$/', $code ) ) {
        return substr( $code, 0, 2 ) . '-' . substr( $code, 2 );
    }

    // Inne formaty zostawiamy jak są (ale raczej ich nie będzie)
    return $code;
}

/**
 * Wczytuje kody z danego pliku strefy i zwraca tablicę znormalizowanych kodów.
 */
function portalsluchu_load_zone_codes( $filename ) {
    $codes = array();

    if ( ! file_exists( $filename ) ) {
        return $codes;
    }

    $lines = file( $filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    if ( ! is_array( $lines ) ) {
        return $codes;
    }

    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( $line === '' ) {
            continue;
        }
        if ( strpos( $line, '#' ) === 0 ) {
            // komentarz
            continue;
        }

        $norm = portalsluchu_normalize_postcode( $line );
        if ( $norm !== '' ) {
            $codes[ $norm ] = true;
        }
    }

    return $codes;
}

/**
 * Wyznacza strefę i cenę dojazdu na podstawie kodu pocztowego.
 *
 * Zwraca tablicę:
 *   array(
 *     'zone'  => 1–5,
 *     'price' => float (0, 150, 300, 500, 550)
 *   )
 */
function portalsluchu_get_dojazd_for_postcode( $postcode ) {
    $postcode = portalsluchu_normalize_postcode( $postcode );

    $prices = array(
        1 => 0.0,
        2 => 150.0,
        3 => 300.0,
        4 => 500.0,
        5 => 550.0, // jeśli nie znaleziono w żadnej strefie
    );

    $plugin_dir = plugin_dir_path( __FILE__ );
    $kody_dir   = trailingslashit( $plugin_dir . 'kody' );

    $zones_files = array(
        1 => $kody_dir . 'strefa1.txt',
        2 => $kody_dir . 'strefa2.txt',
        3 => $kody_dir . 'strefa3.txt',
        4 => $kody_dir . 'strefa4.txt',
    );

    $zone = 5; // domyślnie strefa 5

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

    return array(
        'zone'  => $zone,
        'price' => $price,
    );
}

/**
 * Dodaje pole "Dojazd" na stronie zamówienia (checkout).
 */
function portalsluchu_dojazd_checkout_field( $checkout ) {
    echo '<div id="portalsluchu_dojazd_field" style="margin-top:20px; padding:15px; border:1px solid #ddd;">';

    woocommerce_form_field( 'portalsluchu_dojazd_enable', array(
        'type'  => 'checkbox',
        'class' => array( 'form-row-wide' ),
        'label' => __( 'Chcę dowóz aparatu pod wskazany adres (koszt według kodu pocztowego).', 'portalsluchu' ),
    ), $checkout->get_value( 'portalsluchu_dojazd_enable' ) );

    echo '<p style="margin:5px 0 0; font-size:12px;">';
    echo 'Cena dojazdu zostanie wyliczona na podstawie kodu pocztowego (4 strefy + 5. strefa domyślna).';
    echo '</p>';

    echo '</div>';
}
add_action( 'woocommerce_after_order_notes', 'portalsluchu_dojazd_checkout_field' );

/**
 * Dodajemy opłatę za dojazd do koszyka, jeśli zaznaczono checkbox.
 */
function portalsluchu_dojazd_add_fee( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    // Odczyt danych z checkout (również przy AJAX)
    $enable  = false;
    $postcode = '';

    if ( isset( $_POST['post_data'] ) ) {
        // Ajax z checkoutu
        parse_str( $_POST['post_data'], $post_data );

        if ( ! empty( $post_data['portalsluchu_dojazd_enable'] ) ) {
            $enable = true;
        }

        if ( ! empty( $post_data['shipping_postcode'] ) ) {
            $postcode = $post_data['shipping_postcode'];
        } elseif ( ! empty( $post_data['billing_postcode'] ) ) {
            $postcode = $post_data['billing_postcode'];
        }

    } else {
        // Zwykły POST
        if ( ! empty( $_POST['portalsluchu_dojazd_enable'] ) ) {
            $enable = true;
        }

        if ( ! empty( $_POST['shipping_postcode'] ) ) {
            $postcode = $_POST['shipping_postcode'];
        } elseif ( ! empty( $_POST['billing_postcode'] ) ) {
            $postcode = $_POST['billing_postcode'];
        }
    }

    if ( ! $enable ) {
        return;
    }

    $info = portalsluchu_get_dojazd_for_postcode( $postcode );

    $zone  = $info['zone'];
    $price = $info['price'];

    if ( $price < 0 ) {
        $price = 0;
    }

    $label = sprintf( __( 'Dojazd – strefa %d', 'portalsluchu' ), $zone );

    $cart->add_fee( $label, $price );

    // Zachowaj do późniejszego użycia (meta zamówienia)
    if ( function_exists( 'WC' ) && WC()->session ) {
        WC()->session->set( 'portalsluchu_dojazd_zone', $zone );
        WC()->session->set( 'portalsluchu_dojazd_price', $price );
        WC()->session->set( 'portalsluchu_dojazd_postcode', $postcode );
        WC()->session->set( 'portalsluchu_dojazd_enable', $enable ? '1' : '0' );
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'portalsluchu_dojazd_add_fee', 20, 1 );

/**
 * Zapisujemy informacje o dojeździe do meta zamówienia.
 */
function portalsluchu_dojazd_save_order_meta( $order_id, $data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $zone     = WC()->session->get( 'portalsluchu_dojazd_zone' );
    $price    = WC()->session->get( 'portalsluchu_dojazd_price' );
    $postcode = WC()->session->get( 'portalsluchu_dojazd_postcode' );
    $enable   = WC()->session->get( 'portalsluchu_dojazd_enable' );

    if ( $enable === '1' ) {
        if ( $zone ) {
            update_post_meta( $order_id, '_portalsluchu_dojazd_zone', intval( $zone ) );
        }
        if ( $price !== null ) {
            update_post_meta( $order_id, '_portalsluchu_dojazd_price', floatval( $price ) );
        }
        if ( $postcode ) {
            update_post_meta( $order_id, '_portalsluchu_dojazd_postcode', sanitize_text_field( $postcode ) );
        }
    }
}
add_action( 'woocommerce_checkout_create_order', 'portalsluchu_dojazd_save_order_meta', 20, 2 );

/**
 * Metabox w edycji zamówienia – pokazuje strefę i cenę dojazdu.
 */
function portalsluchu_dojazd_add_order_metabox() {
    add_meta_box(
        'portalsluchu_dojazd_metabox',
        __( 'Dojazd – strefa', 'portalsluchu' ),
        'portalsluchu_dojazd_metabox_cb',
        'shop_order',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'portalsluchu_dojazd_add_order_metabox' );

function portalsluchu_dojazd_metabox_cb( $post ) {
    $order_id = $post->ID;

    $zone     = get_post_meta( $order_id, '_portalsluchu_dojazd_zone', true );
    $price    = get_post_meta( $order_id, '_portalsluchu_dojazd_price', true );
    $postcode = get_post_meta( $order_id, '_portalsluchu_dojazd_postcode', true );

    if ( ! $zone && ! $price && ! $postcode ) {
        echo '<p>Brak danych o dojeździe.</p>';
        return;
    }

    echo '<p><strong>Kod pocztowy:</strong><br>' . esc_html( $postcode ) . '</p>';
    echo '<p><strong>Strefa:</strong><br>' . ( $zone ? intval( $zone ) : '-' ) . '</p>';
    echo '<p><strong>Cena dojazdu:</strong><br>' . wc_price( $price ) . '</p>';
}
