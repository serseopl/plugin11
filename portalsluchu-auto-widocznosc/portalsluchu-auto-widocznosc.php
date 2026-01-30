<?php
/*
Plugin Name: portalsluchu – automatyczna widoczność ogłoszeń + blokada bez audiogramu
Description: Po opublikowaniu ogłoszenia ustawia widoczność na „Sklep i wyniki wyszukiwania”, blokuje publikację bez audiogramu i zmienia napis przy przycisku audiogramu dla nowych modeli.
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
function portalsluchu_handle_product_save( $post_id, $post, $update ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }

    if ( $post->post_type !== 'product' ) {
        return;
    }

    // Pomijamy produkt–opłatę
    if ( (int) $post_id === 921 ) {
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
            $product->set_catalog_visibility( 'visible' );
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

/**
 * Podmiana tekstu zachęty na przycisk wystawiania aparatu dla zalogowanych.
 */
add_filter( 'the_content', function( $content ) {
    // Sprawdzamy, czy użytkownik jest zalogowany
    if ( is_user_logged_in() ) {
        
        // Szukamy tekstu, który jest obecnie w Breakdance
        $stary_tekst = 'Formularz wystawienia aparatu będzie dostępny wyłącznie po zalogowaniu. <br>Zachęcamy do utworzenia konta lub zalogowania się.';
        
        // Tworzymy pomarańczowy przycisk (CTA) dla zalogowanego użytkownika
        $nowy_link = '<br><a href="/wystaw-aparat-sluchowy-na-sprzedaz/" class="button" style="background-color: #ff8c00; color: #fff; padding: 12px 24px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-block; margin: 15px 0; border: none; cursor: pointer;">
                        Wystaw aparat słuchowy na sprzedaż →
                      </a>';

        $content = str_replace( $stary_tekst, $nowy_link, $content );
    }
    return $content;
}, 100 );
