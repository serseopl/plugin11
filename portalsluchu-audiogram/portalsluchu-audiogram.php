<?php
/**
 * Plugin Name: portalsluchu – Audiogram produktu
 * Description: portalsluchu-audiogram / Metabox audiogramu i ustawień modelu aparatu (w tym maksymalna długość gwarancji) dla produktów WooCommerce. Dane audiogramu są przechowywane w meta 'serseo_audiogram' i mogą być współdzielone między produktami o tym samym modelu (meta 'hearing_aid_model').
 * Author: portalsluchu.pl
 * Version: 1.1.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** 
 * Lista częstotliwości (Hz) używana w audiogramie produktów.
 */
function portalsluchu_product_audiogram_frequencies() {
    return array( 125, 250, 500, 750, 1000, 1500, 2000, 3000, 4000, 5000, 6000, 8000 );
}

/**
 * Rejestracja metaboxu.
 */
function portalsluchu_product_audiogram_add_metabox() {
    add_meta_box(
        'portalsluchu_product_audiogram',
        __( 'Audiogram modelu aparatu', 'portalsluchu' ),
        'portalsluchu_product_audiogram_metabox_cb',
        'product',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'portalsluchu_product_audiogram_add_metabox' );

/**
 * Callback metaboxu – wyświetlenie pól.
 */
function portalsluchu_product_audiogram_metabox_cb( $post ) {
    wp_nonce_field( 'portalsluchu_product_audiogram_save', 'portalsluchu_product_audiogram_nonce' );

    $frequencies = portalsluchu_product_audiogram_frequencies();

    $audiogram = get_post_meta( $post->ID, 'serseo_audiogram', true );
    if ( ! is_array( $audiogram ) ) {
        $audiogram = array();
    }

    // Informacja o modelu aparatu
    $model = get_post_meta( $post->ID, 'hearing_aid_model', true );

    // Maksymalna długość gwarancji
    $max_warranty = get_post_meta( $post->ID, 'portalsluchu_max_warranty_years', true );
    if ( ! $max_warranty ) {
        $max_warranty = 1;
    }
    ?>
    <p>
        <strong><?php esc_html_e( 'Model aparatu:', 'portalsluchu' ); ?></strong>
        <?php echo $model ? esc_html( $model ) : '<em>(nie ustawiono – uzupełnij w danych sprzedającego)</em>'; ?>
    </p>

    <table class="widefat striped" style="max-width:1000px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Hz', 'portalsluchu' ); ?></th>
                <?php foreach ( $frequencies as $hz ) : ?>
                    <th><?php echo esc_html( $hz ); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong><?php esc_html_e( 'Od (dB)', 'portalsluchu' ); ?></strong></td>
                <?php foreach ( $frequencies as $hz ) :
                    $row = isset( $audiogram[ $hz ] ) ? $audiogram[ $hz ] : array();
                    $od  = isset( $row['od'] ) ? $row['od'] : '';
                ?>
                    <td>
                        <input type="number"
                               name="<?php echo esc_attr( 'portalsluchu_audiogram_' . $hz . '_od' ); ?>"
                               value="<?php echo esc_attr( $od ); ?>"
                               step="1"
                               style="width:80px;" />
                    </td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td><strong><?php esc_html_e( 'Do (dB)', 'portalsluchu' ); ?></strong></td>
                <?php foreach ( $frequencies as $hz ) :
                    $row = isset( $audiogram[ $hz ] ) ? $audiogram[ $hz ] : array();
                    $do  = isset( $row['do'] ) ? $row['do'] : '';
                ?>
                    <td>
                        <input type="number"
                               name="<?php echo esc_attr( 'portalsluchu_audiogram_' . $hz . '_do' ); ?>"
                               value="<?php echo esc_attr( $do ); ?>"
                               step="1"
                               style="width:80px;" />
                    </td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>

    <p class="description">
        Zmień dowolną wartość dB i kliknij „Zaktualizuj”, aby zapisać.
        Jeśli model już istnieje w innych produktach, edycja wpłynie na wszystkie produkty z tym samym modelem.
    </p>

    <hr style="margin:20px 0;">

    <p>
        <label for="portalsluchu_max_warranty_years"><strong>Maksymalna długość gwarancji dla tego modelu:</strong></label><br>
        <select name="portalsluchu_max_warranty_years" id="portalsluchu_max_warranty_years">
            <option value="1" <?php selected( $max_warranty, 1 ); ?>>1 rok</option>
            <option value="2" <?php selected( $max_warranty, 2 ); ?>>do 2 lat</option>
            <option value="3" <?php selected( $max_warranty, 3 ); ?>>do 3 lat</option>
        </select>
    </p>
<p class="description">
    Na etapie zakupu klient będzie mógł wybrać 1, 2 lub 3 lata gwarancji (w zależności od wartości ustawionej tutaj).
    Dopłaty: 1 rok +390 zł, 2 lata +490 zł, 3 lata +790 zł. W cenie aparatu jest 5 dni gwarancji rozruchowej.
</p>
    <?php
}

/**
 * Zapis danych z metaboxu.
 */
function portalsluchu_product_audiogram_save( $post_id, $post ) {
    // Tylko produkt i zwykłe zapisy
    if ( $post->post_type !== 'product' ) {
        return;
    }

    if ( ! isset( $_POST['portalsluchu_product_audiogram_nonce'] )
         || ! wp_verify_nonce( $_POST['portalsluchu_product_audiogram_nonce'], 'portalsluchu_product_audiogram_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $frequencies   = portalsluchu_product_audiogram_frequencies();
    $new_audiogram = array();

    foreach ( $frequencies as $hz ) {
        $od_key = 'portalsluchu_audiogram_' . $hz . '_od';
        $do_key = 'portalsluchu_audiogram_' . $hz . '_do';

        $od = isset( $_POST[ $od_key ] ) ? trim( sanitize_text_field( $_POST[ $od_key ] ) ) : '';
        $do = isset( $_POST[ $do_key ] ) ? trim( sanitize_text_field( $_POST[ $do_key ] ) ) : '';

        if ( $od !== '' || $do !== '' ) {
            $new_audiogram[ $hz ] = array(
                'od' => $od,
                'do' => $do,
            );
        }
    }

    if ( ! empty( $new_audiogram ) ) {
        update_post_meta( $post_id, 'serseo_audiogram', $new_audiogram );

        // Jeśli ten produkt ma model, zaktualizuj wszystkie produkty z tym samym modelem
        $model = get_post_meta( $post_id, 'hearing_aid_model', true );
        if ( $model ) {
            $args = array(
                'post_type'      => 'product',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => 'hearing_aid_model',
                        'value' => $model,
                    ),
                ),
            );
            $products = get_posts( $args );
            if ( $products ) {
                foreach ( $products as $pid ) {
                    if ( intval( $pid ) === intval( $post_id ) ) {
                        continue;
                    }
                    update_post_meta( $pid, 'serseo_audiogram', $new_audiogram );
                }
            }
        }
    } else {
        // brak danych – usuń meta
        delete_post_meta( $post_id, 'serseo_audiogram' );
    }

    // Zapis maksymalnej gwarancji
    if ( isset( $_POST['portalsluchu_max_warranty_years'] ) ) {
        $max_w = intval( $_POST['portalsluchu_max_warranty_years'] );
        if ( $max_w < 1 || $max_w > 3 ) {
            $max_w = 1;
        }
        update_post_meta( $post_id, 'portalsluchu_max_warranty_years', $max_w );
    }
}
add_action( 'save_post', 'portalsluchu_product_audiogram_save', 10, 2 );

/**
 * Przy pierwszym otwarciu produktu bez audiogramu spróbuj skopiować dane
 * z innego produktu o tym samym modelu.
 */
function portalsluchu_product_audiogram_maybe_autofill( $post ) {
    if ( $post->post_type !== 'product' ) {
        return;
    }

    $existing = get_post_meta( $post->ID, 'serseo_audiogram', true );
    if ( ! empty( $existing ) && is_array( $existing ) ) {
        return; // już jest
    }

    $model = get_post_meta( $post->ID, 'hearing_aid_model', true );
    if ( ! $model ) {
        return;
    }

    $args = array(
        'post_type'      => 'product',
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'posts_per_page' => 1,
        'post__not_in'   => array( $post->ID ),
        'meta_query'     => array(
            array(
                'key'   => 'hearing_aid_model',
                'value' => $model,
            ),
            array(
                'key'     => 'serseo_audiogram',
                'compare' => 'EXISTS',
            ),
        ),
    );

    $products = get_posts( $args );
    if ( $products ) {
        $src_id    = $products[0]->ID;
        $src_audio = get_post_meta( $src_id, 'serseo_audiogram', true );
        if ( ! empty( $src_audio ) && is_array( $src_audio ) ) {
            update_post_meta( $post->ID, 'serseo_audiogram', $src_audio );
        }
    }
}
add_action( 'load-post.php', function() {
    if ( ! isset( $_GET['post'] ) ) {
        return;
    }
    $post_id = intval( $_GET['post'] );
    $post    = get_post( $post_id );
    if ( $post ) {
        portalsluchu_product_audiogram_maybe_autofill( $post );
    }
});
