<?php
/**
 * Plugin Name: portalsluchu – Filtr ofert wg mojego audiogramu
 * Description: portalsluchu-moj-audiogram-filter / Dodaje shortcode [portalsluchu_product_table_filtered id="..."] z checkboxem „Pokaż tylko aparaty zgodne z moim audiogramem” i filtruje wyniki tabeli (WC Product Table Lite) na podstawie meta 'serseo_audiogram' (produkt) oraz 'serseo_user_audiogram' (użytkownik).
 * Author: portalsluchu.pl
 * Version: 1.1.1
 */ 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sprawdza, czy audiogram produktu pasuje do audiogramu użytkownika.
 *
 * Produkt: $product_audio[hz]['od'|'do']
 * Użytkownik: $user_audio[hz]['od'|'do']
 *
 * Warunek: dla każdej częstotliwości wpisanej przez użytkownika
 * zakres użytkownika musi mieścić się w zakresie aparatu:
 *  product_od <= user_od  oraz  product_do >= user_do
 */
function portalsluchu_audiogram_product_matches_user( $product_audio, $user_audio ) {
    if ( ! is_array( $product_audio ) || ! is_array( $user_audio ) ) {
        return false;
    }

    foreach ( $user_audio as $hz => $user_vals ) {
        if ( ! isset( $product_audio[ $hz ] ) ) {
            return false;
        }

        $u_od = isset( $user_vals['od'] ) ? floatval( $user_vals['od'] ) : null;
        $u_do = isset( $user_vals['do'] ) ? floatval( $user_vals['do'] ) : null;

        $p_vals = $product_audio[ $hz ];
        $p_od   = isset( $p_vals['od'] ) ? floatval( $p_vals['od'] ) : null;
        $p_do   = isset( $p_vals['do'] ) ? floatval( $p_vals['do'] ) : null;

        if ( $u_od === null && $u_do === null ) {
            continue;
        }

        if ( $p_od === null || $p_do === null ) {
            return false;
        }

        if ( $u_od !== null && $u_od < $p_od ) {
            return false;
        }
        if ( $u_do !== null && $u_do > $p_do ) {
            return false;
        }
    }

    return true;
}

/**
 * Shortcode wyświetlający checkbox + tabelę produktów.
 *
 * Użycie: [portalsluchu_product_table_filtered id="615"]
 */
function portalsluchu_product_table_filtered_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'id' => '',
        ),
        $atts,
        'portalsluchu_product_table_filtered'
    );

    $table_id = trim( $atts['id'] );

    if ( ! $table_id ) {
        return '<p>Brak ID konfiguracji tabeli produktów.</p>';
    }

    $checked = ( isset( $_GET['audiogram_filter'] ) && $_GET['audiogram_filter'] === '1' );

    ob_start();
    ?>
    <div class="portalsluchu-audiogram-filter-box" style="margin-bottom:15px;">
        <label>
            <input type="checkbox" id="portalsluchu_audiogram_filter_checkbox" <?php checked( $checked ); ?>>
            Pokaż tylko aparaty zgodne z moim audiogramem
        </label>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var cb = document.getElementById('portalsluchu_audiogram_filter_checkbox');
        if (!cb) return;
        cb.addEventListener('change', function() {
            var url = new URL(window.location.href);
            if (cb.checked) {
                url.searchParams.set('audiogram_filter', '1');
            } else {
                url.searchParams.delete('audiogram_filter');
            }
            window.location.href = url.toString();
        });
    });
    </script>
    <?php

    // Sama tabela produktów (shortcode z WC Product Table Lite)
    echo do_shortcode( '[product_table id="' . esc_attr( $table_id ) . '"]' );

    return ob_get_clean();
}
add_shortcode( 'portalsluchu_product_table_filtered', 'portalsluchu_product_table_filtered_shortcode' );

/**
 * Modyfikujemy query WC Product Table Lite, gdy aktywny jest filtr audiogramu.
 *
 * UWAGA: wc-product-table-lite przekazuje do filtra tylko JEDEN parametr ($args),
 * dlatego funkcja przyjmuje tylko $args i add_filter używa accepted_args = 1.
 */
function portalsluchu_filter_wc_product_table_by_audiogram( $args ) {
    if ( ! isset( $_GET['audiogram_filter'] ) || $_GET['audiogram_filter'] !== '1' ) {
        return $args;
    }

    if ( ! is_user_logged_in() ) {
        return $args;
    }

    $user_id    = get_current_user_id();
    $user_audio = get_user_meta( $user_id, 'serseo_user_audiogram', true );

    if ( ! is_array( $user_audio ) || empty( $user_audio ) ) {
        return $args;
    }

    // Aby nie wpaść w pętlę rekurencyjną, na czas pomocniczego zapytania zdejmujemy filtr.
    remove_filter( 'wcpt_query_args', 'portalsluchu_filter_wc_product_table_by_audiogram', 10 );

    $query = new WP_Query( $args );

    // Przywróć filtr
    add_filter( 'wcpt_query_args', 'portalsluchu_filter_wc_product_table_by_audiogram', 10, 1 );

    if ( empty( $query->posts ) ) {
        return $args;
    }

    $allowed_ids = array();

    foreach ( $query->posts as $post ) {
        $product_audio = get_post_meta( $post->ID, 'serseo_audiogram', true );
        if ( ! is_array( $product_audio ) || empty( $product_audio ) ) {
            continue;
        }

        if ( portalsluchu_audiogram_product_matches_user( $product_audio, $user_audio ) ) {
            $allowed_ids[] = $post->ID;
        }
    }

    if ( ! empty( $allowed_ids ) ) {
        $args['post__in']       = $allowed_ids;
        $args['posts_per_page'] = -1;
    } else {
        $args['post__in']       = array( 0 ); // brak wyników
        $args['posts_per_page'] = -1;
    }

    return $args;
}
add_filter( 'wcpt_query_args', 'portalsluchu_filter_wc_product_table_by_audiogram', 10, 1 );
