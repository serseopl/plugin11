<?php
/**
 * Plugin Name: portalsluchu – Formularz kupującego
 * Description: portalsluchu-formularz-kupujacego / Formularz „KUP” na karcie produktu. Po wyborze opcji (pakiet startowy, sposób otrzymania, gwarancja) dane są zapisywane w sesji WooCommerce, produkt trafia do koszyka, a na etapie /kasa doliczane są opłaty. Wylicza też strefę dojazdu na podstawie kodu pocztowego.
 * Author: portalsluchu.pl
 * Version: 1.3.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode: [portalsluchu_kup_form]
 * Wyświetla formularz opcji zakupu na karcie produktu.
 */
function portalsluchu_kup_form_shortcode( $atts ) {
    if ( ! function_exists( 'WC' ) ) {
        return '<p>WooCommerce jest wymagany do działania tego formularza.</p>';
    }

    if ( ! is_user_logged_in() ) {
        return '<p>Aby kupić aparat, zaloguj się lub załóż konto.</p>';
    }

    // 1) Spróbuj pobrać ID produktu z parametru ?product_id=...
    $product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;

    // 2) Jeśli nie ma – użyj aktualnego produktu (pojedyncza karta produktu)
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

    $current_user = wp_get_current_user();

    $base_price   = floatval( $product->get_price() );
    $pakiet_price = 100.0;

    // Maksymalna gwarancja z meta produktu (1–3 lata)
    $max_warranty = get_post_meta( $product_id, 'portalsluchu_max_warranty_years', true );
    if ( ! $max_warranty ) {
        $max_warranty = 1;
    } else {
        $max_warranty = intval( $max_warranty );
        if ( $max_warranty < 1 || $max_warranty > 3 ) {
            $max_warranty = 1;
        }
    }

    $error_msg = '';

    // Obsługa wysyłki formularza
    if ( $_SERVER['REQUEST_METHOD'] === 'POST'
         && isset( $_POST['portalsluchu_kup_nonce'] )
         && wp_verify_nonce( $_POST['portalsluchu_kup_nonce'], 'portalsluchu_kup_form' ) ) {

        $delivery_method  = isset( $_POST['delivery_method'] ) ? sanitize_text_field( $_POST['delivery_method'] ) : 'salon';
        $pakiet_startowy  = isset( $_POST['pakiet_startowy'] ) ? 1 : 0;
        $warranty_years   = isset( $_POST['warranty_years'] ) ? intval( $_POST['warranty_years'] ) : 1;

        if ( $warranty_years < 1 || $warranty_years > $max_warranty ) {
            $warranty_years = 1;
        }

        // Wylicz dopłatę za gwarancję
        $warranty_price = 0.0;
if ( $warranty_years === 1 ) {
    $warranty_price = 390.0;
} elseif ( $warranty_years === 2 ) {
    $warranty_price = 490.0;
} elseif ( $warranty_years === 3 ) {
    $warranty_price = 790.0;
}

        // Dostawa
        $delivery_price    = 0.0;
        $delivery_label    = '';
        $delivery_postcode = '';
        $delivery_salon    = '';

        if ( $delivery_method === 'dojazd' ) {
            $delivery_postcode = isset( $_POST['delivery_postcode'] ) ? sanitize_text_field( $_POST['delivery_postcode'] ) : '';

            if ( ! $delivery_postcode ) {
                $error_msg = 'Podaj kod pocztowy do dojazdu.';
            } elseif ( function_exists( 'portalsluchu_dojazd_calculate_for_postcode' ) ) {
                $res            = portalsluchu_dojazd_calculate_for_postcode( $delivery_postcode );
                $delivery_price = isset( $res['price'] ) ? floatval( $res['price'] ) : 0.0;
                $zone           = isset( $res['zone'] ) ? intval( $res['zone'] ) : 0;
                if ( $zone > 0 ) {
                    $delivery_label = 'Dojazd do klienta (strefa ' . $zone . ')';
                } else {
                    $delivery_label = 'Dojazd do klienta';
                }
            } else {
                $delivery_price = 0.0;
                $delivery_label = 'Dojazd do klienta';
            }
        } elseif ( $delivery_method === 'salon' ) {
            $delivery_salon = isset( $_POST['delivery_salon'] ) ? sanitize_text_field( $_POST['delivery_salon'] ) : '';
            if ( ! $delivery_salon ) {
                $error_msg = 'Wybierz salon odbioru.';
            }
            $delivery_price = 0.0;
            $delivery_label = 'Odbiór w salonie';
        } elseif ( $delivery_method === 'kurier' ) {
            // Stała opłata za wysyłkę kurierem, np. 20 zł
            $delivery_price = 20.0;
            $delivery_label = 'Wysyłka kurierem';
        } else {
            $delivery_method = 'salon';
            $delivery_price  = 0.0;
            $delivery_label  = 'Odbiór w salonie';
        }

        if ( ! $error_msg ) {
            // Dane do zapisania w sesji WooCommerce
            $data = array(
                'product_id'        => $product_id,
                'pakiet_startowy'   => $pakiet_startowy,
                'pakiet_price'      => $pakiet_price,
                'delivery_method'   => $delivery_method,
                'delivery_label'    => $delivery_label,
                'delivery_price'    => $delivery_price,
                'delivery_postcode' => $delivery_postcode,
                'delivery_salon'    => $delivery_salon,
                'warranty_years'    => $warranty_years,
                'warranty_price'    => $warranty_price,
            );

            if ( WC()->session ) {
                WC()->session->set( 'portalsluchu_kup_data', $data );
            }

            // Czyścimy koszyk i dodajemy aktualny aparat
            WC()->cart->empty_cart();
            WC()->cart->add_to_cart( $product_id, 1 );

            // Przekierowanie od razu na /kasa
            if ( function_exists( 'wc_get_checkout_url' ) ) {
                $redirect_url = wc_get_checkout_url();
            } else {
                $redirect_url = wc_get_page_permalink( 'checkout' );
            }

            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    // Dane adresowe użytkownika – na potrzeby podpowiedzi do dojazdu
    $billing_postcode = get_user_meta( $current_user->ID, 'billing_postcode', true );

    ob_start();

    if ( $error_msg ) : ?>
        <div class="portalsluchu-error" style="padding:12px 16px; margin-bottom:16px; background:#ffe6e6; border-left:4px solid #cc0000;">
            <?php echo esc_html( $error_msg ); ?>
        </div>
    <?php endif; ?>

    <h2>Formularz zakupu aparatu</h2>

    <p><strong>Aparat:</strong> <?php echo esc_html( $product->get_name() ); ?>,
       cena aparatu: <?php echo wc_price( $base_price ); ?></p>

    <form method="post" class="portalsluchu-kup-form" id="portalsluchu_kup_form">
        <?php wp_nonce_field( 'portalsluchu_kup_form', 'portalsluchu_kup_nonce' ); ?>

        <h3>Pakiet startowy</h3>
        <p>
            <label>
                <input type="checkbox" checked="checked" disabled="disabled">
                Pakiet startowy (<?php echo wc_price( $pakiet_price ); ?>) – w zestawie otrzymujesz kabelek oraz ładowarkę przetestowaną przez nasz serwis.
            </label>
            <input type="hidden" name="pakiet_startowy" value="1">
        </p>

        <hr>

        <h3>Sposób otrzymania aparatu</h3>

        <p>
            <label>
                <input type="radio" name="delivery_method" value="dojazd" id="portalsluchu_delivery_dojazd">
                Dojazd do klienta (cena zależna od kodu pocztowego)
            </label>
        </p>

        <div id="portalsluchu_dojazd_fields" style="display:none; margin-left:15px; margin-bottom:10px;">
            <p>
                <label for="portalsluchu_delivery_postcode">Kod pocztowy:</label><br>
                <input
                    type="text"
                    name="delivery_postcode"
                    id="portalsluchu_delivery_postcode"
                    placeholder="np. 30-001"
                    value="<?php echo esc_attr( $billing_postcode ); ?>"
                    style="margin-top:4px; max-width:150px;"
                >
                <br>
                <small id="portalsluchu_delivery_info" style="display:block; margin-top:4px;"></small>
            </p>
            <p style="font-size:12px; color:#555;">
                Na etapie płatności zweryfikujemy kod z adresem dostawy; jeśli będzie inny, koszt zostanie przeliczony.
            </p>
        </div>

        <p>
            <label>
                <input type="radio" name="delivery_method" value="salon" id="portalsluchu_delivery_salon" checked="checked">
                Odbiór w salonie
            </label>
        </p>

        <div id="portalsluchu_salon_fields" style="margin-left:15px; margin-bottom:10px;">
            <select name="delivery_salon" style="margin-top:4px; max-width:320px;">
                <option value="">– wybierz lokalizację –</option>
                <option value="Warszawa, ul. Marszałkowska 2">Warszawa, ul. Marszałkowska 2</option>
                <option value="Kraków, Jana Pawła 2 lok. 33">Kraków, Jana Pawła 2 lok. 33</option>
                <option value="Kraków, Jana Pawła 44 lok. 33">Kraków, Jana Pawła 44 lok. 33</option>
                <option value="Kraków, Jana Pawła 92 lok. 33">Kraków, Jana Pawła 92 lok. 33</option>
                <option value="Kraków, Jana Pawła 154 lok. 33">Kraków, Jana Pawła 154 lok. 33</option>
                <option value="Kraków, Jana Pawła 254 lok. 33">Kraków, Jana Pawła 254 lok. 33</option>
            </select>
            <p style="font-size:12px; color:#555; margin-top:6px;">
                Wybierz salon, w którym odbierzesz aparat. Przy tej opcji adres dostawy nie będzie wymagany.
            </p>
        </div>

        <p>
            <label>
                <input type="radio" name="delivery_method" value="kurier" id="portalsluchu_delivery_kurier">
                Wysyłka kurierem (stała opłata, np. 20 zł)
            </label>
        </p>

        <hr>

<h3>Gwarancja</h3>

<p style="margin-top:0;">
    W cenie aparatu otrzymujesz <strong>5 dni gwarancji rozruchowej</strong>.
</p>

<p>
    <?php if ( $max_warranty <= 1 ) : ?>
        <strong>Gwarancja 1 rok</strong> (+390 zł).
        <input type="hidden" name="warranty_years" value="1">
    <?php else : ?>
        <label>
            <input type="radio" name="warranty_years" value="1" <?php checked( $warranty_years, 1 ); ?> />
            1 rok (+390 zł)
        </label><br>

        <?php if ( $max_warranty >= 2 ) : ?>
            <label>
                <input type="radio" name="warranty_years" value="2" <?php checked( $warranty_years, 2 ); ?> />
                2 lata (+490 zł)
            </label><br>
        <?php endif; ?>

        <?php if ( $max_warranty >= 3 ) : ?>
            <label>
                <input type="radio" name="warranty_years" value="3" <?php checked( $warranty_years, 3 ); ?> />
                3 lata (+790 zł)
            </label>
        <?php endif; ?>
    <?php endif; ?>
</p>

        <hr>

        <div class="portalsluchu-info-zakup" style="margin-top:20px; font-size:14px; line-height:1.5;">
          <h3>Informacje o zakupie aparatu słuchowego</h3>
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

        <p style="margin-top:20px;">
            <button type="submit" class="button button-primary">Przejdź do opłacenia</button>
        </p>
    </form>

    <script>
    (function() {
      var form   = document.getElementById('portalsluchu_kup_form');
      if (!form) return;

      var methodRadios = form.querySelectorAll('input[name="delivery_method"]');
      var dojazdFields = document.getElementById('portalsluchu_dojazd_fields');
      var salonFields  = document.getElementById('portalsluchu_salon_fields');
      var postcodeInput = document.getElementById('portalsluchu_delivery_postcode');
      var info         = document.getElementById('portalsluchu_delivery_info');

      var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

      function formatPostcode(raw) {
        var digits = raw.replace(/\D/g, '').slice(0, 5);
        if (digits.length <= 2) return digits;
        return digits.slice(0, 2) + '-' + digits.slice(2);
      }

      var lastSent = '';

      function fetchZone(postcode) {
        if (!postcode || postcode.length !== 6) {
          if (info) info.textContent = '';
          return;
        }
        if (postcode === lastSent) return;
        lastSent = postcode;

        if (!info) return;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onreadystatechange = function() {
          if (xhr.readyState === 4) {
            if (xhr.status === 200) {
              try {
                var resp = JSON.parse(xhr.responseText);
                if (resp && resp.success && resp.data) {
                  var zone  = resp.data.zone;
                  var price = resp.data.price_formatted || resp.data.price;
                  info.textContent = 'Kod należy do strefy ' + zone + '. Koszt dojazdu: ' + price + '.';
                } else {
                  info.textContent = 'Nie udało się ustalić strefy. Sprawdź format 00-000 (np. 30-001).';
                }
              } catch (e) {
                info.textContent = 'Nie udało się ustalić strefy. Sprawdź format 00-000 (np. 30-001).';
              }
            } else {
              info.textContent = 'Nie udało się ustalić strefy. Sprawdź format 00-000 (np. 30-001).';
            }
          }
        };
        var body = 'action=portalsluchu_dojazd_info&postcode=' + encodeURIComponent(postcode);
        xhr.send(body);
      }

      function updateDeliveryVisibility() {
        var selected = 'salon';
        for (var i = 0; i < methodRadios.length; i++) {
          if (methodRadios[i].checked) {
            selected = methodRadios[i].value;
            break;
          }
        }

        if (dojazdFields) {
          dojazdFields.style.display = (selected === 'dojazd') ? 'block' : 'none';
        }
        if (salonFields) {
          salonFields.style.display = (selected === 'salon') ? 'block' : 'none';
        }

        // Jeśli wybrano coś innego niż dojazd – czyścimy info o strefie
        if (selected !== 'dojazd' && info) {
          info.textContent = '';
        }
      }

      if (methodRadios && methodRadios.length) {
        for (var i = 0; i < methodRadios.length; i++) {
          methodRadios[i].addEventListener('change', updateDeliveryVisibility);
        }
        updateDeliveryVisibility();
      }

      if (postcodeInput && info) {
        postcodeInput.addEventListener('input', function() {
          var formatted = formatPostcode(postcodeInput.value);
          postcodeInput.value = formatted;
          if (formatted.length === 6) {
            fetchZone(formatted);
          } else {
            info.textContent = '';
          }
        });

        if (postcodeInput.value) {
          var formatted = formatPostcode(postcodeInput.value);
          postcodeInput.value = formatted;
          if (formatted.length === 6) {
            fetchZone(formatted);
          }
        }
      }
    })();
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode( 'portalsluchu_kup_form', 'portalsluchu_kup_form_shortcode' );

/**
 * Doliczanie opłat (pakiet, dojazd, kurier, gwarancja) na podstawie danych zapisanych w sesji.
 */
function portalsluchu_kup_add_fees( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $data = WC()->session->get( 'portalsluchu_kup_data' );
    if ( ! $data || ! is_array( $data ) ) {
        return;
    }

    // Pakiet startowy
    if ( ! empty( $data['pakiet_startowy'] ) && ! empty( $data['pakiet_price'] ) ) {
        $cart->add_fee( 'Pakiet startowy', floatval( $data['pakiet_price'] ) );
    }

    // Dojazd lub kurier – zawsze jeśli jest cena i etykieta
    if ( ! empty( $data['delivery_price'] ) && ! empty( $data['delivery_label'] ) ) {
        $cart->add_fee( $data['delivery_label'], floatval( $data['delivery_price'] ) );
    }

    // Gwarancja
    if ( ! empty( $data['warranty_price'] ) && ! empty( $data['warranty_years'] ) ) {
        $label = 'Gwarancja ' . intval( $data['warranty_years'] ) . ' lata';
        $cart->add_fee( $label, floatval( $data['warranty_price'] ) );
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'portalsluchu_kup_add_fees', 25, 1 );

/**
 * Zapis danych formularza w meta zamówienia.
 */
function portalsluchu_kup_save_order_meta( $order, $data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $kup_data = WC()->session->get( 'portalsluchu_kup_data' );
    if ( ! $kup_data || ! is_array( $kup_data ) ) {
        return;
    }

    $order->update_meta_data( '_portalsluchu_kup_data', $kup_data );

    WC()->session->__unset( 'portalsluchu_kup_data' );
}
add_action( 'woocommerce_checkout_create_order', 'portalsluchu_kup_save_order_meta', 25, 2 );

/**
 * Jeśli wybrano „Odbiór w salonie”, nie wymagaj adresu wysyłki na /kasa
 * i dodaj klasę do <body>, żeby można było ukryć sekcję adresu przez CSS.
 */
function portalsluchu_kup_relax_shipping_required( $fields ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return $fields;
    }

    $data = WC()->session->get( 'portalsluchu_kup_data' );
    if ( ! $data || empty( $data['delivery_method'] ) || $data['delivery_method'] !== 'salon' ) {
        return $fields;
    }

    if ( isset( $fields['shipping'] ) && is_array( $fields['shipping'] ) ) {
        $keys = array( 'first_name', 'last_name', 'country', 'address_1', 'city', 'postcode' );
        foreach ( $keys as $key ) {
            $field_key = 'shipping_' . $key;
            if ( isset( $fields['shipping'][ $field_key ] ) ) {
                $fields['shipping'][ $field_key ]['required'] = false;
            }
        }
    }

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'portalsluchu_kup_relax_shipping_required', 20, 1 );

function portalsluchu_kup_body_class( $classes ) {
    if ( function_exists( 'is_checkout' ) && is_checkout() && function_exists( 'WC' ) && WC()->session ) {
        $data = WC()->session->get( 'portalsluchu_kup_data' );
        if ( $data && ! empty( $data['delivery_method'] ) && $data['delivery_method'] === 'salon' ) {
            $classes[] = 'portalsluchu-odbior-w-salonie';
        }
    }
    return $classes;
}
add_filter( 'body_class', 'portalsluchu_kup_body_class' );

?>
