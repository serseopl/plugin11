<?php
/**
 * Plugin Name: portalsluchu – Formularz kupującego
 * Description: Formularz „KUP” na karcie produktu. Zapisuje do sesji WooCommerce: prowizję (99 zł), opcjonalne przystosowanie (100 zł), opcjonalny pakiet startowy (99 zł) i gwarancję (5 dni lub 1/2/3 lata wg meta produktu). Dostawa wybierana jest na /kasa. Styl przycisku zgodny z systemem PS (Ghost). Zmienione detale na płynne animacje JS.
 * Author: portalsluchu.pl
 * Version: 1.5.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function portalsluchu_kup_form_shortcode( $atts ) {
    if ( ! function_exists( 'WC' ) ) {
        return '<p>WooCommerce jest wymagany do działania tego formularza.</p>';
    }

    if ( ! is_user_logged_in() ) {
        $account_url = home_url( '/moje-konto/' );

        return '
        <div style="
            margin: 18px 0;
            padding: 14px 18px;
            border-radius: 14px;
            border: 1px solid rgba(220, 38, 38, .35);
            background: linear-gradient(180deg, rgba(254, 242, 242, 1) 0%, rgba(255, 255, 255, 1) 100%);
            box-shadow: 0 10px 24px rgba(0,0,0,.08);
            color: #991b1b;
            font-size: 19px;
            font-weight: 700;
            line-height: 1.4;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, \"Apple Color Emoji\", \"Segoe UI Emoji\";
            letter-spacing: .2px;
        ">
            Aby kupić aparat,
            <a href="' . esc_url( $account_url ) . '" style="color:#7f1d1d; text-decoration: underline; font-weight: 800;">
                zaloguj się
            </a>
            lub
            <a href="' . esc_url( $account_url ) . '" style="color:#7f1d1d; text-decoration: underline; font-weight: 800;">
                załóż konto
            </a>.
            <div style="margin-top:6px; font-size: 14px; font-weight: 600; color: rgba(153,27,27,.78);">
                To zajmie chwilę i pozwoli dokończyć zakup.
            </div>
        </div>';
    }

    $product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;

    if ( ! $product_id && is_singular( 'product' ) ) {
        $product_id = get_the_ID();
    }

    if ( ! $product_id ) {
        return '<p>Brak wybranego aparatu.</p>';
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return '<p>Wybrany aparat nie istnieje.</p>';
    }

    $base_price = (float) $product->get_price();

    // Stałe opłaty
    $prowizja_price       = 99.0;  // zawsze
    $przystosowanie_price = 100.0; // opcjonalnie
    $pakiet_price         = 99.0;  // opcjonalnie (Pakiet startowy)

    // Maksymalna gwarancja z meta produktu (1–3 lata)
    $max_warranty = get_post_meta( $product_id, 'portalsluchu_max_warranty_years', true );
    $max_warranty = $max_warranty ? (int) $max_warranty : 1;
    if ( $max_warranty < 1 || $max_warranty > 3 ) {
        $max_warranty = 1;
    }

    // Domyślne wartości
    $warranty_years = 0; // 5 dni rozruchowej
    $przystosowanie = 0;
    $pakiet         = 0;

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset( $_POST['portalsluchu_kup_nonce'] )
        && wp_verify_nonce( $_POST['portalsluchu_kup_nonce'], 'portalsluchu_kup_form' )
    ) {
        $warranty_years = isset( $_POST['warranty_years'] ) ? (int) $_POST['warranty_years'] : 0;

        // Dozwolone: 0 (5 dni) albo 1..max_warranty
        if ( $warranty_years !== 0 && ( $warranty_years < 1 || $warranty_years > $max_warranty ) ) {
            $warranty_years = 0;
        }

        $warranty_price = 0.0;
        if ( $warranty_years === 1 ) {
            $warranty_price = 390.0;
        } elseif ( $warranty_years === 2 ) {
            $warranty_price = 490.0;
        } elseif ( $warranty_years === 3 ) {
            $warranty_price = 790.0;
        }

        $przystosowanie = ! empty( $_POST['portalsluchu_przystosowanie'] ) ? 1 : 0;
        $pakiet         = ! empty( $_POST['portalsluchu_pakiet'] ) ? 1 : 0;

        // Zapis do sesji – to będzie użyte na /kasa do naliczenia fee
        $data = array(
            'product_id'             => $product_id,

            // Prowizja (zawsze)
            'prowizja_enable'        => 1,
            'prowizja_price'         => $prowizja_price,

            // Przystosowanie (opcjonalnie)
            'przystosowanie_enable'  => $przystosowanie,
            'przystosowanie_price'   => $przystosowanie ? $przystosowanie_price : 0.0,

            // Pakiet startowy (opcjonalnie)
            'pakiet_enable'          => $pakiet,
            'pakiet_price'           => $pakiet ? $pakiet_price : 0.0,

            // Gwarancja
            'warranty_years'         => $warranty_years,
            'warranty_price'         => $warranty_price,
        );

        if ( WC()->session ) {
            WC()->session->set( 'portalsluchu_kup_data', $data );
        }

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart( $product_id, 1 );

        $redirect_url = function_exists( 'wc_get_checkout_url' )
            ? wc_get_checkout_url()
            : wc_get_page_permalink( 'checkout' );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    ob_start();
    ?>
    <!-- Style przycisku i sekcji rozwijanych -->
    <style>
        :root {
            --ps-brand: #0ea5a8;
            --ps-brand-dark: #0f766e;
        }
        /* Styl bazowy przycisku */
        .ps-btn {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 14px 22px;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            font-weight: 900;
            letter-spacing: .2px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 52px;
            text-decoration: none;
            font-size: 16px;
            transition: transform .08s ease, filter .15s ease, box-shadow .15s ease;
        }
        .ps-btn-ghost {
            background: rgba(14,165,168,.10);
            color: rgba(15,23,42,.92);
            border: 1px solid rgba(14,165,168,.35);
        }
        .ps-btn-ghost:hover {
            background: rgba(14,165,168,.18);
            transform: translateY(-1px);
        }
        .ps-btn-ghost:focus-visible {
            outline: none;
            box-shadow: 0 0 0 4px rgba(14,165,168,.20);
        }
        .ps-btn-primary {
            background: linear-gradient(180deg, var(--ps-brand) 0%, var(--ps-brand-dark) 100%);
            color: #fff;
            box-shadow: 0 12px 22px rgba(14,165,168,.22);
        }
        .ps-btn-primary:hover {
            filter: brightness(1.03);
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(14,165,168,.28);
        }

        /* Style dla płynnie rozwijanych sekcji (zastępują details) */
        .ps-toggle-trigger {
            cursor: pointer;
            font-size: 0.9em;
            color: #0ea5a8; /* Kolor marki */
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            margin-top: 6px;
            margin-left: 22px; /* Wcięcie pasujące do checkboxa */
            user-select: none;
            text-decoration: none;
        }
        .ps-toggle-trigger:hover {
            text-decoration: underline;
        }
        .ps-toggle-trigger::before {
            content: '▶';
            font-size: 0.7em;
            margin-right: 6px;
            display: inline-block;
            transition: transform 0.4s ease; /* Płynny obrót strzałki */
        }
        .ps-toggle-trigger.open::before {
            transform: rotate(90deg);
        }
        .ps-toggle-content {
            display: none; /* Domyślnie ukryte */
            margin-left: 22px;
            margin-top: 8px;
            font-size: 0.9em;
            color: #555;
            line-height: 1.6;
            padding-bottom: 5px;
        }
        /* Specjalne wcięcie dla gwarancji, która nie ma checkboxa powyżej */
        .ps-toggle-trigger.ps-warranty-trigger {
            margin-left: 0;
            margin-top: 10px;
        }
        .ps-toggle-content.ps-warranty-content {
            margin-left: 0;
        }
    </style>

    <p><strong>Cena aparatu: </strong>&nbsp;<?php echo wc_price( $base_price ); ?></p>

    <form method="post" class="portalsluchu-kup-form" id="portalsluchu_kup_form">
        <?php wp_nonce_field( 'portalsluchu_kup_form', 'portalsluchu_kup_nonce' ); ?>

        <h3>Opłaty</h3>

        <!-- Prowizja -->
        <p>
            <label>
                <input type="checkbox" checked="checked" disabled="disabled">
                Prowizja za sprawdzenie oraz zakup aparatu słuchowego przez nasz portal – <?php echo wc_price( $prowizja_price ); ?>
            </label>
            <input type="hidden" name="portalsluchu_prowizja" value="1">
        </p>

        <!-- Przystosowanie (Dopasowanie) -->
        <div style="margin-top:12px;">
            <label>
                <input type="checkbox" name="portalsluchu_przystosowanie" value="1" <?php checked( $przystosowanie, 1 ); ?>>
                <strong>Tak, chcę, aby aparat został dopasowany do mojego ubytku słuchu</strong> - <?php echo wc_price( $przystosowanie_price ); ?>
            </label>

            <!-- Custom toggle (zamiast details) -->
            <div class="ps-toggle-trigger">Szczegóły</div>
            <div class="ps-toggle-content">
                <p style="margin:0 0 5px;">
                    Dopasowanie na podstawie badania słuchu oraz oględzin uszu.<br />
                    Zakup aparatu słuchowego za pośrednictwem naszego portalu nie oznacza automatycznego dopasowania urządzenia do indywidualnego ubytku słuchu.<br />
                    Jeżeli chcesz, aby zakupiony aparat został przygotowany na podstawie Twojego badania słuchu, oględzin uszu oraz innych istotnych parametrów, koniecznie wybierz tę opcję podczas składania zamówienia.
                    <br />
                    Jeśli nie wybierzesz tej opcji, otrzymasz aparat z ustawieniami fabrycznymi, niedopasowany do indywidualnych potrzeb słuchowych.
                </p>
            </div>
        </div>
        
        <!-- Pakiet startowy (Nowy checkbox) -->
        <div style="margin-top:12px;">
            <label>
                <input type="checkbox" name="portalsluchu_pakiet" value="1" <?php checked( $pakiet, 1 ); ?>>
                <strong>Tak, wybieram Pakiet Startowy</strong> – <?php echo wc_price( $pakiet_price ); ?>
            </label>

            <!-- Custom toggle (zamiast details) -->
            <div class="ps-toggle-trigger">Szczegóły pakietu</div>
            <div class="ps-toggle-content">
                <div style="margin:0; font-size:0.95em;">
                    <strong>W ramach pakietu startowego otrzymujesz:</strong>
                    <ul style="list-style:disc; margin-left:18px; margin-top:4px; margin-bottom:0;">
                        <li>baterie (jeśli są konieczne do działania aparatu),</li>
                        <li>paczkę filtrów (6 lub 8 sztuk – w zależności od modelu aparatu),</li>
                        <li>tabletkę do osuszania aparatu wraz z pudełkiem,</li>
                        <li>chusteczkę do czyszczenia aparatu,</li>
                        <li>szczoteczkę do czyszczenia aparatu,</li>
                        <li>pudełko do przechowywania aparatu,</li>
                        <li>spray do dezynfekcji aparatu.</li>
                    </ul>
                </div>
            </div>
        </div>

        <hr>
        <h3>Gwarancja</h3>
        <div class="ps-warranty-section">
            <label>
                <input type="radio" name="warranty_years" value="0" <?php checked( $warranty_years, 0 ); ?> />
                5 dni gwarancji rozruchowej (w cenie)
            </label>
            <br>

            <label>
                <input type="radio" name="warranty_years" value="1" <?php checked( $warranty_years, 1 ); ?> />
                1 rok (+390 zł)
            </label>
            <br>

            <?php if ( $max_warranty >= 2 ) : ?>
                <label>
                    <input type="radio" name="warranty_years" value="2" <?php checked( $warranty_years, 2 ); ?> />
                    2 lata (+490 zł)
                </label>
                <br>
            <?php endif; ?>

            <?php if ( $max_warranty >= 3 ) : ?>
                <label>
                    <input type="radio" name="warranty_years" value="3" <?php checked( $warranty_years, 3 ); ?> />
                    3 lata (+790 zł)
                </label>
            <?php endif; ?>

            <!-- Custom toggle dla gwarancji -->
            <div class="ps-toggle-trigger ps-warranty-trigger">Szczegóły gwarancji</div>
            <div class="ps-toggle-content ps-warranty-content">
                Przedłużona gwarancja obejmuje wsparcie serwisowe, naprawy oraz obsługę usterek zgodnie z zakresem gwarancyjnym.
            </div>
        </div>

        <p style="margin-top:20px;">
            <button type="submit" class="ps-btn ps-btn-ghost" style="width:100%;">Przejdź do opłacenia</button>
        </p>
    </form>

    <!-- Skrypt do płynnego otwierania (600ms) -->
    <script>
    (function($) {
        $(document).on('click', '.ps-toggle-trigger', function(e) {
            e.preventDefault();
            var $trigger = $(this);
            var $content = $trigger.next('.ps-toggle-content');
            
            // Toggle klasy dla obrotu strzałki
            $trigger.toggleClass('open');
            
            // Płynne otwieranie/zamykanie (600ms = wolno)
            $content.stop().slideToggle(600);
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'portalsluchu_kup_form', 'portalsluchu_kup_form_shortcode' );

/**
 * Doliczanie opłat (prowizja + przystosowanie + pakiet + gwarancja płatna) na podstawie sesji.
 * 5 dni gwarancji nie dodaje fee.
 */
function portalsluchu_kup_add_fees( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

    // GUARD: jeśli koszyk zawiera WYŁĄCZNIE opłatę za wystawienie (1087),
    // to nie doliczamy prowizji / gwarancji / przystosowania / pakietu.
    if ( function_exists( 'WC' ) && WC()->cart ) {
        $items = WC()->cart->get_cart();
        if ( ! empty( $items ) ) {
            $fee_only = true;
            foreach ( $items as $it ) {
                if ( empty( $it['product_id'] ) || (int) $it['product_id'] !== 1087 ) {
                    $fee_only = false;
                    break;
                }
            }
            if ( $fee_only ) {
                return;
            }
        }
    }

    $data = WC()->session->get( 'portalsluchu_kup_data' );
    if ( ! $data || ! is_array( $data ) ) return;

    // Prowizja (zawsze)
    if ( ! empty( $data['prowizja_enable'] ) && ! empty( $data['prowizja_price'] ) ) {
        $cart->add_fee( 'Prowizja', (float) $data['prowizja_price'] );
    }

    // Przystosowanie (opcjonalne)
    if ( ! empty( $data['przystosowanie_enable'] ) && ! empty( $data['przystosowanie_price'] ) ) {
        $cart->add_fee( 'Przystosowanie aparatu słuchowego do Twojego ubytku słuchu', (float) $data['przystosowanie_price'] );
    }

    // Pakiet startowy (opcjonalny)
    if ( ! empty( $data['pakiet_enable'] ) && ! empty( $data['pakiet_price'] ) ) {
        $cart->add_fee( 'Pakiet startowy', (float) $data['pakiet_price'] );
    }

    // Gwarancja (tylko płatne warianty)
    $years = isset( $data['warranty_years'] ) ? (int) $data['warranty_years'] : 0;
    $price = isset( $data['warranty_price'] ) ? (float) $data['warranty_price'] : 0.0;

    if ( $years > 0 && $price > 0 ) {
        $cart->add_fee( 'Gwarancja ' . $years . ' lata', $price );
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'portalsluchu_kup_add_fees', 25, 1 );

/**
 * Zapis danych formularza w meta zamówienia.
 */
function portalsluchu_kup_save_order_meta( $order, $data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) return;

    $kup_data = WC()->session->get( 'portalsluchu_kup_data' );
    if ( ! $kup_data || ! is_array( $kup_data ) ) return;

    $order->update_meta_data( '_portalsluchu_kup_data', $kup_data );
    WC()->session->__unset( 'portalsluchu_kup_data' );
}
add_action( 'woocommerce_checkout_create_order', 'portalsluchu_kup_save_order_meta', 25, 2 );

/**
 * Informacja o gwarancji w mailach i szczegółach zamówienia.
 */
function portalsluchu_kup_get_warranty_label_from_order( $order ) {
    $kup_data = $order->get_meta( '_portalsluchu_kup_data', true );
    if ( ! is_array( $kup_data ) ) return '';

    $years = isset( $kup_data['warranty_years'] ) ? (int) $kup_data['warranty_years'] : 0;

    if ( $years === 0 ) return '5 dni gwarancji rozruchowej';
    if ( $years === 1 ) return 'Gwarancja 1 rok';
    if ( $years === 2 ) return 'Gwarancja 2 lata';
    if ( $years === 3 ) return 'Gwarancja 3 lata';

    return '';
}

add_action( 'woocommerce_email_order_meta', function( $order, $sent_to_admin, $plain_text, $email ) {
    if ( ! $order instanceof WC_Order ) return;

    $label = portalsluchu_kup_get_warranty_label_from_order( $order );
    if ( ! $label ) return;

    if ( $plain_text ) {
        echo "\nGwarancja: " . $label . "\n";
    } else {
        echo '<p><strong>Gwarancja:</strong> ' . esc_html( $label ) . '</p>';
    }
}, 25, 4 );

add_action( 'woocommerce_order_details_after_order_table', function( $order ) {
    if ( ! $order instanceof WC_Order ) return;

    $label = portalsluchu_kup_get_warranty_label_from_order( $order );
    if ( ! $label ) return;

    echo '<p><strong>Gwarancja:</strong> ' . esc_html( $label ) . '</p>';
}, 25 );

add_action( 'admin_post_ps_listing_fee', 'ps_listing_fee_post_handler' );
add_action( 'admin_post_nopriv_ps_listing_fee', 'ps_listing_fee_post_handler' );

function ps_listing_fee_post_handler() {
    if ( ! function_exists( 'WC' ) ) {
        wp_safe_redirect( home_url() );
        exit;
    }

    // Nonce
    if ( empty( $_POST['ps_listing_fee_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ps_listing_fee_nonce'] ) ), 'ps_listing_fee' ) ) {
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    $fee_product_id = isset( $_POST['fee_product_id'] ) ? (int) $_POST['fee_product_id'] : 0;
    if ( $fee_product_id <= 0 ) {
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    if ( null === WC()->cart ) {
        wc_load_cart();
    }

    WC()->cart->empty_cart( true );
    $added = WC()->cart->add_to_cart( $fee_product_id, 1 );

    if ( $added ) {
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    wp_safe_redirect( wc_get_cart_url() );
    exit;
}

/// --- newsletter ---
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    // Często występuje jako billing -> wc_billing_marketing
    if ( isset( $fields['billing']['wc_billing_marketing'] ) ) {
        unset( $fields['billing']['wc_billing_marketing'] );
    }

    // Czasem w innych instalacjach/by wtyczkach pole ma inną nazwę.
    // Usuń wszystkie pola, które w label mają "exclusive emails" (case-insensitive).
    foreach ( $fields as $section_key => $section_fields ) {
        if ( ! is_array( $section_fields ) ) continue;
        foreach ( $section_fields as $key => $def ) {
            $label = isset( $def['label'] ) ? (string) $def['label'] : '';
            if ( $label && stripos( $label, 'exclusive emails' ) !== false ) {
                unset( $fields[ $section_key ][ $key ] );
            }
        }
    }

    return $fields;
}, 999 );