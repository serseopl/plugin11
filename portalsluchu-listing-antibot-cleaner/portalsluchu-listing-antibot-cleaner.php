<?php
/**
 * Plugin Name: portalsluchu – Listing antibot cleaner (pending_payment TTL)
 * Description: portalsluchu-listing-antibot-cleaner / Ukrywa nieopłacone ogłoszenia (pending_payment) w panelu admina i usuwa je automatycznie po 6 minutach, aby nie dało się zaspamić bazy.
 * Version: 1.0.0
 * Author: portalsluchu.pl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Portalsluchu_Listing_Antibot_Cleaner {

    // Po ilu sekundach kasujemy nieopłacone szkice (6 minut = 360 s)
    const TTL_SECONDS = 360;

    // Klucz meta statusu opłaty (już używany w Twoim kodzie)
    const META_STATUS = 'listing_payment_status';

    // Jaki status oznacza "czeka na płatność"
    const STATUS_PENDING = 'pending_payment';

    // Klucz meta czasu utworzenia pending
    const META_PENDING_AT = 'listing_pending_created_at';

    // Nazwa eventu cron
    const CRON_HOOK = 'portalsluchu_cleanup_pending_listings';

    public static function init() : void {
        // Harmonogram co minutę (WP-Cron) – potrzebne dla TTL 6 min
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

        register_activation_hook( __FILE__, array( __CLASS__, 'on_activate' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'on_deactivate' ) );

        add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );

        // Awaryjnie: sprzątaj też "przy okazji" (żeby działało nawet bez realnego cron-a)
        add_action( 'init', array( __CLASS__, 'maybe_cleanup_light' ) );

        // Ukryj pending_payment w liście produktów w adminie
        add_action( 'pre_get_posts', array( __CLASS__, 'hide_pending_in_admin_products_list' ) );
    }

    public static function add_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['every_minute'] ) ) {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display'  => 'Every Minute',
            );
        }
        return $schedules;
    }

    public static function on_activate() : void {
        // Zaplanuj event co minutę, jeśli nie istnieje
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 60, 'every_minute', self::CRON_HOOK );
        }
    }

    public static function on_deactivate() : void {
        // Usuń zaplanowany event
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Lekki cleanup max raz na 60s (bez ciężkiego obciążenia).
     */
    public static function maybe_cleanup_light() : void {
        // nie rób tego w admin-ajax i cron, żeby nie dublować
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return;
        }

        $last = (int) get_option( 'portalsluchu_pending_cleanup_last_run', 0 );
        if ( time() - $last < 60 ) {
            return;
        }

        update_option( 'portalsluchu_pending_cleanup_last_run', time(), false );
        self::cleanup();
    }

    /**
     * Sprzątanie: usuń drafty "pending_payment" starsze niż TTL.
     */
    public static function cleanup() : void {
        $cutoff = time() - self::TTL_SECONDS;

        // Szukamy produktów (ogłoszeń) w szkicach, które mają pending_payment i są stare.
        $args = array(
            'post_type'      => 'product',
            'post_status'    => array( 'draft' ),
            'posts_per_page' => 200, // na raz; przy spamie i tak będzie co minutę
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => self::META_STATUS,
                    'value' => self::STATUS_PENDING,
                ),
                array(
                    'key'     => self::META_PENDING_AT,
                    'value'   => $cutoff,
                    'type'    => 'NUMERIC',
                    'compare' => '<=',
                ),
            ),
        );

        $ids = get_posts( $args );
        if ( empty( $ids ) ) {
            return;
        }

        foreach ( $ids as $post_id ) {
            // hard delete – bez kosza, żeby nie puchło
            wp_delete_post( (int) $post_id, true );
        }
    }

    /**
     * Ukrywanie pending_payment w wp-admin -> Produkty
     */
    public static function hide_pending_in_admin_products_list( $query ) : void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        global $pagenow;

        // Tylko ekran listy produktów
        if ( $pagenow !== 'edit.php' ) {
            return;
        }
        if ( $query->get( 'post_type' ) !== 'product' ) {
            return;
        }

        // Pozwól sobie podejrzeć pending, jeśli dodasz ?show_pending=1
        if ( isset($_GET['show_pending']) && $_GET['show_pending'] === '1' ) {
            return;
        }

        $meta_query = (array) $query->get( 'meta_query' );

        // Wyklucz pending_payment
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => self::META_STATUS,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => self::META_STATUS,
                'value'   => self::STATUS_PENDING,
                'compare' => '!=',
            ),
        );

        $query->set( 'meta_query', $meta_query );
    }
}

Portalsluchu_Listing_Antibot_Cleaner::init();