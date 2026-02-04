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
 * Produkt: $product_audio[hz]['od'|'do']  (zakres dB dla danej częstotliwości)
 * Użytkownik: $user_audio[hz] = poziom (dB) (jedna wartość, bierzemy bardziej restrykcyjne ucho)
 *
 * Warunek: dla każdej częstotliwości wpisanej przez użytkownika:
 *  product_od <= user_db <= product_do
 */
function portalsluchu_audiogram_product_matches_user( $product_audio, $user_audio ) {
    if ( ! is_array( $product_audio ) || ! is_array( $user_audio ) ) {
        return false;
    }
foreach ( $user_audio as $hz => $u_db ) {
    $hz = intval( $hz );
        if ( $u_db === null || $u_db === '' ) {
            continue;
        }

        if ( ! isset( $product_audio[ $hz ] ) ) {
            return false;
        }

        $p_vals = $product_audio[ $hz ];
        $p_od   = isset( $p_vals['od'] ) ? floatval( $p_vals['od'] ) : null;
        $p_do   = isset( $p_vals['do'] ) ? floatval( $p_vals['do'] ) : null;

        if ( $p_od === null || $p_do === null ) {
            return false;
        }

        $u_db = floatval( $u_db );

        if ( $u_db < $p_od || $u_db > $p_do ) {
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



// Jeśli user zaznaczył filtr, ale nie ma uzupełnionego audiogramu – pokaż komunikat + link
$show_missing_audiogram_notice = false;

if ( $checked ) {
    if ( ! is_user_logged_in() ) {
        $show_missing_audiogram_notice = true;
    } else {
        $user_id    = get_current_user_id();
        $user_prawe = get_user_meta( $user_id, 'serseo_user_audiogram_prawe_db', true );
$user_lewe  = get_user_meta( $user_id, 'serseo_user_audiogram_lewe_db', true );

$user_audio = array();

if ( is_array( $user_prawe ) ) {
    foreach ( $user_prawe as $hz => $db ) {
        if ( $db === '' || $db === null ) {
            continue;
        }
        $user_audio[ intval( $hz ) ] = floatval( $db );
    }
}

if ( is_array( $user_lewe ) ) {
    foreach ( $user_lewe as $hz => $db ) {
        if ( $db === '' || $db === null ) {
            continue;
        }
        $hz_i  = intval( $hz );
        $db_f  = floatval( $db );

        // jeśli prawe już było wpisane, bierzemy większą wartość (bardziej restrykcyjna)
        if ( isset( $user_audio[ $hz_i ] ) ) {
            $user_audio[ $hz_i ] = max( $user_audio[ $hz_i ], $db_f );
        } else {
            $user_audio[ $hz_i ] = $db_f;
        }
    }
}


        if ( ! is_array( $user_audio ) || empty( $user_audio ) ) {
            $show_missing_audiogram_notice = true;
        }
    }
}



    ob_start();
if ( $show_missing_audiogram_notice ) : ?>
<div class="woocommerce-info" style="margin-bottom:12px;">
    <div style="text-align:center; color:#b32d2e; font-weight:700; margin-bottom:6px;">
        UWAGA
    </div>
    Wykryliśmy, że nie masz jeszcze uzupełnionego audiogramu. Aby filtrować oferty według zgodności,
    uzupełnij swój audiogram:<br />
   <div style="text-align:center;">
  <a style="font-size:24px; text-decoration:none;" href="moje-konto/moj-audiogram/">>>> Wypełnij audiogram <<<</a>.
</div>
</div>
<?php endif; ?>

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
    $user_prawe = get_user_meta( $user_id, 'serseo_user_audiogram_prawe_db', true );
$user_lewe  = get_user_meta( $user_id, 'serseo_user_audiogram_lewe_db', true );

$user_audio = array();

if ( is_array( $user_prawe ) ) {
    foreach ( $user_prawe as $hz => $db ) {
        if ( $db === '' || $db === null ) {
            continue;
        }
        $user_audio[ intval( $hz ) ] = floatval( $db );
    }
}

if ( is_array( $user_lewe ) ) {
    foreach ( $user_lewe as $hz => $db ) {
        if ( $db === '' || $db === null ) {
            continue;
        }
        $hz_i  = intval( $hz );
        $db_f  = floatval( $db );

        // jeśli prawe już było wpisane, bierzemy większą wartość (bardziej restrykcyjna)
        if ( isset( $user_audio[ $hz_i ] ) ) {
            $user_audio[ $hz_i ] = max( $user_audio[ $hz_i ], $db_f );
        } else {
            $user_audio[ $hz_i ] = $db_f;
        }
    }
}
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
