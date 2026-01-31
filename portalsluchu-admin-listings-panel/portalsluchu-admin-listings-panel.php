<?php
/**
 * Plugin Name: portalsluchu – Admin panel ogłoszeń (kolumny + ukrywanie nieopłaconych)
 * Description: portalsluchu-admin-listings-panel.php / Na liście Produkty dodaje kolumny Model / Audiogram / Telefon sprzedającego oraz domyślnie ukrywa ogłoszenia z listing_payment_status=pending_payment.
 * Version: 1.0.0
 * Author: portalsluchu.pl
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class Portalsluchu_Admin_Listings_Panel {

    const META_MODEL  = 'hearing_aid_model';
    const META_AUDIO  = 'serseo_audiogram';
    const META_PHONE  = 'seller_phone';
    const META_PAY    = 'listing_payment_status';
    const STATUS_HIDE = 'pending_payment';

    public static function init() : void {
        add_action( 'pre_get_posts', array( __CLASS__, 'hide_pending_listings_in_admin' ) );
        add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_columns' ), 25 );
        add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_columns' ), 10, 2 );
        add_filter( 'manage_edit-product_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'handle_sorting' ) );
    }

    /**
     * Ukryj nieopłacone ogłoszenia (pending_payment) na liście Produkty.
     * Uwaga: działa tylko dla ogłoszeń (czyli takich, co mają hearing_aid_model).
     */
    public static function hide_pending_listings_in_admin( $query ) : void {
        if ( ! is_admin() || ! $query->is_main_query() ) return;

        global $pagenow;
        if ( $pagenow !== 'edit.php' ) return;
        if ( $query->get( 'post_type' ) !== 'product' ) return;

        // Opcja awaryjna: pokaż wszystko jeśli dopiszesz ?show_pending=1
        if ( isset($_GET['show_pending']) && $_GET['show_pending'] === '1' ) {
            return;
        }

        $meta_query = (array) $query->get( 'meta_query' );

        // Ograniczamy tylko do ogłoszeń (mają model)
        $meta_query[] = array(
            'key'     => self::META_MODEL,
            'compare' => 'EXISTS',
        );

        // Wykluczamy pending_payment
        $meta_query[] = array(
            'key'     => self::META_PAY,
            'value'   => self::STATUS_HIDE,
            'compare' => '!=',
        );

        $query->set( 'meta_query', $meta_query );
    }

    public static function add_columns( $columns ) {
        $new = array();

        foreach ( $columns as $key => $label ) {
            $new[$key] = $label;

            // po "name" dodajemy nasze kolumny
            if ( $key === 'name' ) {
                $new['ps_model']    = 'Model';
                $new['ps_audiogram'] = 'Audiogram';
                $new['ps_phone']    = 'Telefon';
            }
        }

        // fallback, jeśli "name" nie było (raczej zawsze jest)
        if ( ! isset( $new['ps_model'] ) ) {
            $new['ps_model']     = 'Model';
            $new['ps_audiogram'] = 'Audiogram';
            $new['ps_phone']     = 'Telefon';
        }

        return $new;
    }

    public static function render_columns( $column, $post_id ) : void {
        if ( $column === 'ps_model' ) {
            $model = get_post_meta( $post_id, self::META_MODEL, true );
            echo $model ? esc_html( $model ) : '—';
            return;
        }

        if ( $column === 'ps_phone' ) {
            $phone = get_post_meta( $post_id, self::META_PHONE, true );
            if ( $phone ) {
                // klikany tel:
                $tel = preg_replace('/[^0-9+]/', '', (string) $phone);
                echo '<a href="tel:' . esc_attr($tel) . '">' . esc_html( $phone ) . '</a>';
            } else {
                echo '—';
            }
            return;
        }

        if ( $column === 'ps_audiogram' ) {
            $audio = get_post_meta( $post_id, self::META_AUDIO, true );

            $has = ( is_array($audio) && ! empty($audio) );
            if ( $has ) {
                echo '<span style="color:#008000;font-weight:700;">✔</span>';
            } else {
                echo '<span style="color:#cc0000;font-weight:700;">✖</span>';
            }
            return;
        }
    }

    public static function sortable_columns( $columns ) {
        $columns['ps_model'] = 'ps_model';
        return $columns;
    }

    public static function handle_sorting( $query ) : void {
        if ( ! is_admin() || ! $query->is_main_query() ) return;

        global $pagenow;
        if ( $pagenow !== 'edit.php' ) return;
        if ( $query->get( 'post_type' ) !== 'product' ) return;

        if ( $query->get( 'orderby' ) === 'ps_model' ) {
            $query->set( 'meta_key', self::META_MODEL );
            $query->set( 'orderby', 'meta_value' );
        }
    }
}

Portalsluchu_Admin_Listings_Panel::init();