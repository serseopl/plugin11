<?php
/* 
Plugin Name: portalsluchu – automatyczna widoczność ogłoszeń + blokada bez audiogramu
Description: portalsluchu-auto-widocznosc / Po opublikowaniu ogłoszenia ustawia widoczność na „Sklep i wyniki wyszukiwania”, blokuje publikację bez audiogramu i zmienia napis przy przycisku audiogramu dla nowych modeli.
Version: 2.1
Author: portalsluchu
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sprawdza, czy produkt ma uzupełniony audiogram (meta serseo_audiogram).
 */
function portalsluchu_product_has_audiogram( $product_id ) {
    $data = get_post_meta( $product_id, 'serseo_audiogram', true );
    if ( empty( $data ) || ! is_array( $data ) ) {
        return false;
    }

    foreach ( $data as $hz => $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $od = isset( $row['od'] ) ? trim( (string) $row['od'] ) : '';
        $do = isset( $row['do'] ) ? trim( (string) $row['do'] ) : '';
        if ( $od !== '' || $do !== '' ) {
            return true;
        }
    }

    return false;
}

/**
 * Po zapisaniu produktu:
 *  - jeśli status jest "publish" i to ogłoszenie:
 *      • bez audiogramu → cofamy do szkicu + komunikat,
 *      • z audiogramem → ustawiamy widoczność "Sklep i wyniki wyszukiwania".
 *
 * save_post_product z priorytetem 99 – meta audiogramu jest już wtedy zapisana.
 */

function portalsluchu_force_catalog_visible( $product_id ) {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return;
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return;
    }

    // 1) WooCommerce setter
    $product->set_catalog_visibility( 'visible' );
    $product->save();

    // 2) Hard reset terminów product_visibility (pewniejsze)
    if ( taxonomy_exists( 'product_visibility' ) ) {
        $exclude = array( 'exclude-from-catalog', 'exclude-from-search' );

        // pobierz aktualne termy
        $terms = wp_get_object_terms( $product_id, 'product_visibility', array( 'fields' => 'names' ) );
        if ( ! is_wp_error( $terms ) ) {
            // usuń exclude*
            $terms = array_values( array_diff( $terms, $exclude ) );
            // ustaw z powrotem
            wp_set_object_terms( $product_id, $terms, 'product_visibility', false );
        }
    }

    // 3) wyczyść cache
    wc_delete_product_transients( $product_id );
}

function portalsluchu_handle_product_save( $post_id, $post, $update ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }

    if ( $post->post_type !== 'product' ) {
        return;
    }

    // Tylko ogłoszenia (produkty z polem hearing_aid_model)
    $model = get_post_meta( $post_id, 'hearing_aid_model', true );
    if ( empty( $model ) ) {
        return;
    }

    // Interesuje nas tylko sytuacja, gdy produkt jest faktycznie publikowany
    if ( $post->post_status !== 'publish' ) {
        return;
    }

    $has_audiogram = portalsluchu_product_has_audiogram( $post_id );

    if ( ! $has_audiogram ) {
        // Brak audiogramu – cofamy publikację do szkicu

        remove_action( 'save_post_product', 'portalsluchu_handle_product_save', 99 );

        wp_update_post( array(
            'ID'          => $post_id,
            'post_status' => 'draft',
        ) );

        add_filter( 'redirect_post_location', function( $location ) use ( $post_id ) {
            return add_query_arg( array(
                'portalsluchu_audiogram_required' => 1,
                'post' => $post_id,
            ), $location );
        } );

        return;
    }

    // Jest audiogram – ustawiamy widoczność "Sklep i wyniki wyszukiwania"
    if ( function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $post_id );
        if ( $product ) {
            portalsluchu_force_catalog_visible( $post_id );   // w save_post
            portalsluchu_force_catalog_visible( $post->ID );  // w transition_post_status
            $product->save();
        }
    }
}
add_action( 'save_post_product', 'portalsluchu_handle_product_save', 99, 3 );

/**
 * Komunikat w panelu admina, gdy próbowano opublikować bez audiogramu.
 */
function portalsluchu_audiogram_required_admin_notice() {
    if ( empty( $_GET['portalsluchu_audiogram_required'] ) ) {
        return;
    }

    ?>
    <div class="notice notice-error is-dismissible" style="border-left-width:6px; border-left-color:#dc3232; padding:14px 18px;">
        <p style="font-size:15px; font-weight:700; margin-bottom:6px;">
            Proszę uzupełnić audiogram przed opublikowaniem ogłoszenia (nowy model).
        </p>
        <p style="margin:0; font-size:13px;">
            W zakładce „Audiogram” wpisz dane dla tego modelu, zapisz, a następnie ponownie opublikuj produkt.
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', 'portalsluchu_audiogram_required_admin_notice' );

/**
 * Zmiana napisu przy przycisku audiogramu:
 *  - jeśli produkt jest SZKICEM,
 *  - ma ustawiony model (hearing_aid_model),
 *  - i NIE ma jeszcze audiogramu,
 * to podmieniamy tekst przycisku #edit-audiogram na "Zapisz audiogram".
 *
 * Nie dotykamy produktów z już zapisanym audiogramem – tam nadal będzie „Edytuj audiogram”.
 */
function portalsluchu_change_audiogram_button_label() {
    global $post;

    if ( ! $post || $post->post_type !== 'product' ) {
        return;
    }

    // Tylko szkic ogłoszenia
    if ( $post->post_status !== 'draft' ) {
        return;
    }

    $model = get_post_meta( $post->ID, 'hearing_aid_model', true );
    if ( empty( $model ) ) {
        return;
    }

    // Jeżeli audiogram już jest – nic nie zmieniamy
    if ( portalsluchu_product_has_audiogram( $post->ID ) ) {
        return;
    }

    ?>
    <script>
    jQuery(function($){
        var btn = $('#edit-audiogram');
        if (btn.length) {
            btn.text('Zapisz audiogram');
        }
    });
    </script>
    <?php
}
add_action( 'admin_footer-post.php', 'portalsluchu_change_audiogram_button_label' );


add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {

    if ( ! $post || $post->post_type !== 'product' ) {
        return;
    }

    if ( $new_status !== 'publish' ) {
        return;
    }

    // tylko ogłoszenia
    $model = get_post_meta( $post->ID, 'hearing_aid_model', true );
    if ( empty( $model ) ) {
        return;
    }

    if ( function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $post->ID );
        if ( $product ) {
            $product->set_catalog_visibility( 'visible' ); // "Sklep i wyniki wyszukiwania"
            $product->save();
        }
    }

}, 50, 3 );