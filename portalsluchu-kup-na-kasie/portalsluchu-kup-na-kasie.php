<?php
/**
 * Plugin Name: portalsluchu – Zakup na stronie "Kasa"
 * Description: portalsluchu-kup-na-kasie / Wybór sposobu otrzymania aparatu, pakietu startowego i gwarancji bezpośrednio na stronie /kasa. Dolicza opłaty na podstawie adresu (dojazd) lub wybranego salonu. Nie działa przy samej opłacie 10 zł za wystawienie ogłoszenia.
 * Author: portalsluchu.pl
 * Version: 1.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} 

// ID produktu WooCommerce reprezentującego opłatę 10 zł za wystawienie ogłoszenia
if ( ! defined( 'PORTALSLUCHU_LISTING_FEE_PRODUCT_ID' ) ) {
    define( 'PORTALSLUCHU_LISTING_FEE_PRODUCT_ID', 921 );
}

/**
 * Pomocniczo: pobierz ID pierwszego produktu z koszyka.
 */
function portalsluchu_kasa_get_first_product_id_from_cart() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return 0;
    }

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( ! empty( $cart_item['product_id'] ) ) {
            return (int) $cart_item['product_id'];
        }
    }

    return 0;
}

/**
 * Sprawdź, czy w koszyku jest wyłącznie produkt opłaty za wystawienie (10 zł).
 */
function portalsluchu_kasa_cart_has_only_listing_fee() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return false;
    }

    $items = WC()->cart->get_cart();
    if ( empty( $items ) ) {
        return false;
    }

    foreach ( $items as $cart_item ) {
        if ( empty( $cart_item['product_id'] ) ) {
            return false;
        }
        if ( (int) $cart_item['product_id'] !== (int) PORTALSLUCHU_LISTING_FEE_PRODUCT_ID ) {
            return false;
        }
    }

    return true;
}

/**
 * Render sekcji na stronie "Kasa" – pakiet, sposób otrzymania, gwarancja.
 * Wstawiamy ją bezpośrednio w formularz checkout poprzez hook WooCommerce.
 */
function portalsluchu_kasa_render_checkout_section( $checkout = null ) {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        return;
    }
    if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
        return;
    }
    if ( ! function_exists( 'WC' ) ) {
        return;
    }

    // Jeśli w koszyku jest tylko produkt 10 zł (opłata za wystawienie) – NIE pokazujemy tej sekcji.
    if ( portalsluchu_kasa_cart_has_only_listing_fee() ) {
        return;
    }

    // oznacz, że sekcja została wyrenderowana (przydatne dla fallbacku w wp_footer)
    $GLOBALS['portalsluchu_kasa_rendered'] = true;

    $product_id   = portalsluchu_kasa_get_first_product_id_from_cart();
    $pakiet_price = 100.0;

    // Maksymalna długość gwarancji z meta produktu
    $max_warranty = 1;
    if ( $product_id ) {
        $meta_max = get_post_meta( $product_id, 'portalsluchu_max_warranty_years', true );
        if ( $meta_max ) {
            $meta_max = intval( $meta_max );
            if ( $meta_max >= 1 && $meta_max <= 3 ) {
                $max_warranty = $meta_max;
            }
        }
    }

    // Domyślne wartości – jeśli klient wraca do kasy, wczytamy z sesji
    $delivery_method = 'salon';
    $delivery_salon  = '';
    $warranty_years  = 1;

    if ( WC()->session ) {
        $saved = WC()->session->get( 'portalsluchu_checkout_data' );
        if ( is_array( $saved ) ) {
            if ( ! empty( $saved['delivery_method'] ) ) {
                $delivery_method = $saved['delivery_method'];
            }
            if ( ! empty( $saved['delivery_salon'] ) ) {
                $delivery_salon = $saved['delivery_salon'];
            }
            if ( ! empty( $saved['warranty_years'] ) ) {
                $warranty_years = (int) $saved['warranty_years'];
            }
        }
    }

    ?>
    <div class="portalsluchu-kasa-box" style="margin-top:20px; padding:15px; border:1px solid #ddd;">
        <h3>Pakiet startowy</h3>
        <p>
            Pakiet startowy (<?php echo wc_price( $pakiet_price ); ?>) jest doliczany do każdego zamówienia aparatu.
            W zestawie otrzymujesz kabelek oraz ładowarkę przetestowaną przez nasz serwis.
        </p>

        <hr>

        <h3>Jak chcesz otrzymać aparat?</h3>

        <p>
            <label>
                <input type="radio" name="portalsluchu_delivery_method" value="dojazd" <?php checked( $delivery_method, 'dojazd' ); ?> />
                Dojazd do klienta (cena zależna od kodu pocztowego z adresu dostawy)
            </label>
            <br>
            <small>Kod pocztowy z adresu poniżej (sekcja Dane do wysyłki) będzie użyty do wyliczenia strefy i kosztu dojazdu.</small>
        </p>

        <p>
            <label>
                <input type="radio" name="portalsluchu_delivery_method" value="salon" <?php checked( $delivery_method, 'salon' ); ?> />
                Odbiór w salonie
            </label>
        </p>
        <div id="portalsluchu_delivery_salon_box" style="margin-left:15px; margin-bottom:10px; <?php echo ( $delivery_method === 'salon' ) ? '' : 'display:none;'; ?>">
            <p>
                <select name="portalsluchu_delivery_salon" style="max-width:320px;">
                    <option value="">– wybierz lokalizację –</option>
                    <option value="Warszawa, ul. Marszałkowska 2" <?php selected( $delivery_salon, 'Warszawa, ul. Marszałkowska 2' ); ?>>Warszawa, ul. Marszałkowska 2</option>
                    <option value="Kraków, Jana Pawła 2 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 2 lok. 33' ); ?>>Kraków, Jana Pawła 2 lok. 33</option>
                    <option value="Kraków, Jana Pawła 44 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 44 lok. 33' ); ?>>Kraków, Jana Pawła 44 lok. 33</option>
                    <option value="Kraków, Jana Pawła 92 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 92 lok. 33' ); ?>>Kraków, Jana Pawła 92 lok. 33</option>
                    <option value="Kraków, Jana Pawła 154 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 154 lok. 33' ); ?>>Kraków, Jana Pawła 154 lok. 33</option>
                    <option value="Kraków, Jana Pawła 254 lok. 33" <?php selected( $delivery_salon, 'Kraków, Jana Pawła 254 lok. 33' ); ?>>Kraków, Jana Pawła 254 lok. 33</option>
                </select>
            </p>
            <p><small>Przy odbiorze w salonie nie doliczamy opłaty za dojazd.</small></p>
        </div>

        <p>
            <label>
                <input type="radio" name="portalsluchu_delivery_method" value="wysylka" <?php checked( $delivery_method, 'wysylka' ); ?> />
                Wysyłka na adres
            </label>
        </p>
        <div id="portalsluchu_delivery_wysylka_box" style="margin-left:15px; margin-bottom:10px; <?php echo ( $delivery_method === 'wysylka' ) ? '' : 'display:none;'; ?>">
            <p>
                Koszt wysyłki zostanie obliczony zgodnie z wybraną metodą dostawy w WooCommerce.
                <br>
                <small>W przyszłości można tu dodać np. InPost paczkomaty.</small>
            </p>
        </div>

        <hr>

<h3>Gwarancja</h3>

<p style="margin-top:0;">
    W cenie aparatu otrzymujesz <strong>5 dni gwarancji rozruchowej</strong>.
</p>

<p>
    <?php if ( $max_warranty <= 1 ) : ?>
        <strong>Gwarancja 1 rok</strong> (+390 zł).
        <input type="hidden" name="portalsluchu_warranty_years" value="1" />
    <?php else : ?>
        <label>
            <input type="radio" name="portalsluchu_warranty_years" value="1" <?php checked( $warranty_years, 1 ); ?> />
            1 rok (+390 zł)
        </label><br>

        <?php if ( $max_warranty >= 2 ) : ?>
            <label>
                <input type="radio" name="portalsluchu_warranty_years" value="2" <?php checked( $warranty_years, 2 ); ?> />
                2 lata (+490 zł)
            </label><br>
        <?php endif; ?>

        <?php if ( $max_warranty >= 3 ) : ?>
            <label>
                <input type="radio" name="portalsluchu_warranty_years" value="3" <?php checked( $warranty_years, 3 ); ?> />
                3 lata (+790 zł)
            </label>
        <?php endif; ?>
    <?php endif; ?>
</p>

        <hr>

        <div class="portalsluchu-info-zakup" style="margin-top:10px; font-size:14px; line-height:1.5;">
          <h4>Informacje o zakupie aparatu słuchowego</h4>
          <p>Przy zamówieniu aparatu słuchowego otrzymujesz urządzenie, które zostaje zweryfikowane przez specjalistyczną firmę.
          Jeśli w trakcie weryfikacji okaże się, że aparat nie działa prawidłowo, zostaniesz o tym niezwłocznie poinformowany.</p>

          <p>Kupujesz aparat używany, a my – jako pośrednik – zajmujemy się jego sprawdzeniem i weryfikacją stanu technicznego.</p>

          <p><strong>Proces zakupu:</strong></p>
          <ol>
            <li>Aparat widoczny w ofercie nie jest jeszcze zweryfikowany pod względem jakości.</li>
            <li>Po złożeniu zamówienia dokonujemy jego zakupu od sprzedającego.</li>
            <li>Następnie przeprowadzamy testy i – w razie potrzeby – naprawę urządzenia.</li>
            <li>Po zakończeniu procesu aparat zostaje wysłany do Ciebie.</li>
          </ol>

          <p>Czas realizacji zamówienia może wynosić do 30 dni.</p>
        </div>
    </div>

    <script>
    (function($) {
        $(document).on('change', 'input[name="portalsluchu_delivery_method"], select[name="portalsluchu_delivery_salon"], input[name="billing_postcode"]', function() {
            if (typeof wc_checkout_params !== 'undefined') {
                $('body').trigger('update_checkout');
            }
        });

        function refreshBoxes() {
            var val = $('input[name="portalsluchu_delivery_method"]:checked').val();
            if (!val) val = 'salon';

            $('#portalsluchu_delivery_salon_box').toggle(val === 'salon');
            $('#portalsluchu_delivery_wysylka_box').toggle(val === 'wysylka');
        }

        $(document).on('change', 'input[name="portalsluchu_delivery_method"]', refreshBoxes);
        $(document).ready(refreshBoxes);
    })(jQuery);
    </script>
    <?php
}
add_action( 'woocommerce_review_order_before_payment', 'portalsluchu_kasa_render_checkout_section', 5 );


/**
 * Shortcode: [portalsluchu_kasa_box]
 * Umożliwia ręczne wstawienie sekcji na stronie /kasa (np. w edytorze Breakdance).
 */
function portalsluchu_kasa_box_shortcode() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        // Dla bezpieczeństwa – renderujemy tylko na stronie kasy.
        return '';
    }
    ob_start();
    portalsluchu_kasa_render_checkout_section();
    return ob_get_clean();
}
add_shortcode( 'portalsluchu_kasa_box', 'portalsluchu_kasa_box_shortcode' );


/**
 * Fallback: jeśli z jakiegoś powodu hook WooCommerce nie zadziała,
 * spróbujemy wyrenderować sekcję na samym dole strony (przez wp_footer).
 */
function portalsluchu_kasa_render_checkout_section_footer() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        return;
    }
    if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
        return;
    }
    if ( ! empty( $GLOBALS['portalsluchu_kasa_rendered'] ) ) {
        // sekcja już została wyrenderowana normalnie
        return;
    }

    portalsluchu_kasa_render_checkout_section();
}
add_action( 'wp_footer', 'portalsluchu_kasa_render_checkout_section_footer', 5 );

/**
 * Walidacja wyboru na stronie "Kasa".
 */
function portalsluchu_kasa_checkout_validation() {
    if ( ! function_exists( 'WC' ) ) {
        return;
    }

    // Dla koszyka zawierającego tylko produkt 10 zł – nie walidujemy naszych pól
    if ( portalsluchu_kasa_cart_has_only_listing_fee() ) {
        return;
    }

    $delivery_method = isset( $_POST['portalsluchu_delivery_method'] )
        ? sanitize_text_field( wp_unslash( $_POST['portalsluchu_delivery_method'] ) )
        : 'salon';

    if ( $delivery_method === 'dojazd' ) {
        $billing_postcode = isset( $_POST['billing_postcode'] )
            ? sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) )
            : '';

        if ( ! $billing_postcode ) {
            wc_add_notice( 'Podaj kod pocztowy w adresie, abyśmy mogli wyliczyć koszt dojazdu.', 'error' );
        }
    } elseif ( $delivery_method === 'salon' ) {
        $delivery_salon = isset( $_POST['portalsluchu_delivery_salon'] )
            ? sanitize_text_field( wp_unslash( $_POST['portalsluchu_delivery_salon'] ) )
            : '';

        if ( ! $delivery_salon ) {
            wc_add_notice( 'Wybierz salon, w którym odbierzesz aparat.', 'error' );
        }
    }

    // Gwarancja – upewnijmy się, że jest w dozwolonym zakresie
    $warranty_years = isset( $_POST['portalsluchu_warranty_years'] )
        ? (int) $_POST['portalsluchu_warranty_years']
        : 1;

    $product_id  = portalsluchu_kasa_get_first_product_id_from_cart();
    $max_allowed = 1;
    if ( $product_id ) {
        $meta_max = get_post_meta( $product_id, 'portalsluchu_max_warranty_years', true );
        if ( $meta_max ) {
            $meta_max = (int) $meta_max;
            if ( $meta_max >= 1 && $meta_max <= 3 ) {
                $max_allowed = $meta_max;
            }
        }
    }
    if ( $warranty_years < 1 || $warranty_years > $max_allowed ) {
        wc_add_notice( 'Wybrano nieprawidłowy wariant gwarancji.', 'error' );
    }
}
add_action( 'woocommerce_checkout_process', 'portalsluchu_kasa_checkout_validation' );

/**
 * Zapisywanie wyboru klienta w sesji – potrzebne do doliczania opłat.
 */
function portalsluchu_kasa_store_session_data() {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    // Dla koszyka zawierającego tylko produkt 10 zł – pomijamy.
    if ( portalsluchu_kasa_cart_has_only_listing_fee() ) {
        return;
    }

    if ( ! isset( $_POST['portalsluchu_delivery_method'] ) ) {
        return;
    }

    $delivery_method = sanitize_text_field( wp_unslash( $_POST['portalsluchu_delivery_method'] ) );
    $delivery_salon  = isset( $_POST['portalsluchu_delivery_salon'] )
        ? sanitize_text_field( wp_unslash( $_POST['portalsluchu_delivery_salon'] ) )
        : '';

    $warranty_years = isset( $_POST['portalsluchu_warranty_years'] )
        ? (int) $_POST['portalsluchu_warranty_years']
        : 1;

    $data = array(
        'delivery_method' => $delivery_method,
        'delivery_salon'  => $delivery_salon,
        'warranty_years'  => $warranty_years,
    );

    WC()->session->set( 'portalsluchu_checkout_data', $data );
}
add_action( 'woocommerce_checkout_update_order_review', 'portalsluchu_kasa_store_session_data' );

/**
 * Doliczanie opłat (pakiet, dojazd, gwarancja) do koszyka na podstawie danych z sesji i adresu.
 */
function portalsluchu_kasa_add_fees( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    // Dla koszyka zawierającego tylko produkt 10 zł – nie dodajemy żadnych dodatkowych opłat.
    if ( portalsluchu_kasa_cart_has_only_listing_fee() ) {
        return;
    }

    $data = WC()->session->get( 'portalsluchu_checkout_data' );
    if ( ! is_array( $data ) ) {
        $data = array();
    }

    $delivery_method = isset( $data['delivery_method'] ) ? $data['delivery_method'] : 'salon';
    $warranty_years  = isset( $data['warranty_years'] ) ? (int) $data['warranty_years'] : 1;

    $pakiet_price   = 100.0;
    $warranty_price = 0.0;

    // Pakiet startowy – zawsze (dla zakupów aparatu)
    $cart->add_fee( 'Pakiet startowy', $pakiet_price );

    // Gwarancja – na podstawie ustawień produktu
    $product_id  = portalsluchu_kasa_get_first_product_id_from_cart();
    $max_allowed = 1;
    if ( $product_id ) {
        $meta_max = get_post_meta( $product_id, 'portalsluchu_max_warranty_years', true );
        if ( $meta_max ) {
            $meta_max = (int) $meta_max;
            if ( $meta_max >= 1 && $meta_max <= 3 ) {
                $max_allowed = $meta_max;
            }
        }
    }

    if ( $warranty_years < 1 || $warranty_years > $max_allowed ) {
        $warranty_years = 1;
    }

    if ( $warranty_years === 1 ) {
    $warranty_price = 390.0;
} elseif ( $warranty_years === 2 ) {
    $warranty_price = 490.0;
} elseif ( $warranty_years === 3 ) {
    $warranty_price = 790.0;
}

    if ( $warranty_price > 0 ) {
        $cart->add_fee( 'Gwarancja ' . $warranty_years . ' lata', $warranty_price );
    }

    // Dojazd – liczymy na podstawie billing_postcode, tylko jeśli wybrano "dojazd"
    if ( $delivery_method === 'dojazd' ) {
        $billing_postcode = '';
        if ( isset( $_POST['billing_postcode'] ) ) {
            $billing_postcode = sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) );
        } elseif ( WC()->customer ) {
            $billing_postcode = WC()->customer->get_billing_postcode();
        }

        if ( $billing_postcode && function_exists( 'portalsluchu_dojazd_calculate_for_postcode' ) ) {
            $res   = portalsluchu_dojazd_calculate_for_postcode( $billing_postcode );
            $zone  = isset( $res['zone'] )  ? (int) $res['zone'] : 5;
            $price = isset( $res['price'] ) ? (float) $res['price'] : 0.0;

            if ( $price > 0 ) {
                $label = 'Dojazd do klienta (strefa ' . $zone . ')';
                $cart->add_fee( $label, $price );
            }
        }
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'portalsluchu_kasa_add_fees', 25 );

/**
 * Zapis wyboru klienta do meta zamówienia.
 */
function portalsluchu_kasa_save_order_meta( $order, $data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $stored = WC()->session->get( 'portalsluchu_checkout_data' );
    if ( ! is_array( $stored ) ) {
        $stored = array();
    }

    $order->update_meta_data( '_portalsluchu_checkout_data', $stored );

    WC()->session->__unset( 'portalsluchu_checkout_data' );
}
add_action( 'woocommerce_checkout_create_order', 'portalsluchu_kasa_save_order_meta', 25, 2 );

/**
 * Przy odbiorze w salonie adres rozliczeniowy nie jest wymagany.
 * Oczekujemy, że w formularzu na /kasa pole ma nazwę
 * portalsluchu_delivery_method i wartość 'salon' dla odbioru w punkcie.
 */
add_filter( 'woocommerce_checkout_fields', 'portalsluchu_relax_billing_for_salon_pickup' );
function portalsluchu_relax_billing_for_salon_pickup( $fields ) {

    $method = '';

    // 1) Wartość z POST (gdy klient właśnie wysyła formularz)
    if ( isset( $_POST['portalsluchu_delivery_method'] ) ) {
        $method = sanitize_text_field( wp_unslash( $_POST['portalsluchu_delivery_method'] ) );
    }
    // 2) Albo z sesji WooCommerce (gdy wraca na kasę itp.)
    elseif ( function_exists( 'WC' ) && WC()->session ) {
        $stored = WC()->session->get( 'portalsluchu_delivery_method' );
        if ( $stored ) {
            $method = $stored;
        }
    }

    if ( 'salon' !== $method ) {
        return $fields;
    }

    // Pola adresowe, które przy odbiorze w salonie mogą być puste
    $keys = array(
        'billing_company',
        'billing_address_1',
        'billing_address_2',
        'billing_postcode',
        'billing_city',
        'billing_state',
        'billing_country',
    );

    foreach ( $keys as $key ) {
        if ( isset( $fields['billing'][ $key ] ) ) {
            $fields['billing'][ $key ]['required'] = false;
        }
    }

    return $fields;
}

/**
 * Zapisujemy wybraną metodę dostawy do sesji – żeby powyższy filtr
 * działał także po odświeżeniu / powrocie na stronę kasy.
 */
add_action( 'woocommerce_checkout_update_order_meta', 'portalsluchu_store_delivery_method_in_session', 5, 2 );
function portalsluchu_store_delivery_method_in_session( $order_id, $data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    if ( isset( $_POST['portalsluchu_delivery_method'] ) ) {
        $method = sanitize_text_field( wp_unslash( $_POST['portalsluchu_delivery_method'] ) );
        WC()->session->set( 'portalsluchu_delivery_method', $method );
    }
}
