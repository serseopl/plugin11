<?php
/**
 * Plugin Name: portalsluchu – Kod pocztowy na stronie "Kasa"
 * Description: portalsluchu-kasa-postcode-helper / Auto-formatowanie kodu pocztowego (01-000) + natychmiastowe wyliczanie strefy dojazdu na stronie zamówienia (/kasa). Wymaga wtyczki "portalsluchu – Strefy dojazdu".
 * Author: portalsluchu.pl
 * Version: 1.0.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wstrzykujemy JS tylko na froncie i tylko na stronie zamówienia.
 */
function portalsluchu_kasa_postcode_helper_footer_script() {
    if ( is_admin() ) {
        return;
    }

    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        return;
    }

    // Pole musi istnieć w HTML (wygenerowane przez Twój box na "Kasa")
    ?>
    <script>
    (function($) {
      $(function() {

        var $postcode = $('#portalsluchu_delivery_postcode');
        var $info     = $('#portalsluchu_delivery_info');

        if (!$postcode.length || !$info.length) {
          return;
        }

        var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
        var lastSentDigits = null;

        /**
         * Formatuje wpis użytkownika do postaci "01-000".
         * - Akceptuje wpis z myślnikiem i bez myślnika.
         * - Zostawia tylko cyfry, max 5.
         */
        function formatPostcode(value) {
          var digits = (value || '').replace(/\D+/g, '').slice(0, 5);

          if (digits.length <= 2) {
            return digits; // "0", "01"
          } else {
            return digits.slice(0, 2) + '-' + digits.slice(2); // "01-0", "01-000"
          }
        }

        /**
         * Pobiera z serwera strefę i cenę na podstawie kodu.
         * Korzysta z akcji AJAX "portalsluchu_dojazd_info"
         * z wtyczki "portalsluchu – Strefy dojazdu".
         */
        function fetchZone() {
          var raw    = $postcode.val() || '';
          var digits = raw.replace(/\D+/g, '');

          if (digits.length !== 5) {
            $info.text('Wprowadź pełny kod pocztowy (5 cyfr).');
            lastSentDigits = null;
            return;
          }

          if (digits === lastSentDigits) {
            return; // Nic się nie zmieniło
          }
          lastSentDigits = digits;

          $.post(
            ajaxUrl,
            {
              action:   'portalsluchu_dojazd_info',
              postcode: digits
            }
          ).done(function(resp) {

            if (!resp || !resp.success || !resp.data) {
              $info.text('Nie udało się pobrać informacji o strefie. Spróbuj ponownie.');
              return;
            }

            var zone       = parseInt(resp.data.zone, 10);
            var priceHtml  = resp.data.price_formatted || '';
            var baseText   = 'Wprowadź kod pocztowy, aby wstępnie oszacować koszt dojazdu. Ostateczna kwota może się zmienić, jeśli na etapie płatności podasz inny adres dostawy.';

            if (zone >= 1 && zone <= 4) {
              // Strefy 1–4 (pochodzą z plików tekstowych)
              $info.html(
                'Należysz do strefy ' + zone +
                ' – przyjazd to ' + priceHtml + '.<br>' +
                baseText
              );
            } else {
              // Wszystko inne traktujemy jako strefę 5 (domyślnie 500 / 550 zł)
              $info.html(
                'Twój kod pocztowy należy do strefy 5 – przyjazd to ' + priceHtml + '.<br>' +
                baseText
              );
            }

          }).fail(function() {
            $info.text('Nie udało się pobrać informacji o strefie. Sprawdź połączenie i spróbuj ponownie.');
          });
        }

        /**
         * Obsługa wpisywania:
         * - formatuje na bieżąco (auto-kreska),
         * - po 5 cyfrach odpala fetchZone().
         */
        $postcode.on('input', function() {
          var before    = $postcode.val();
          var formatted = formatPostcode(before);

          if (before !== formatted) {
            // Zapisujemy pozycję kursora w prosty sposób
            var cursorPos = $postcode[0].selectionStart || formatted.length;
            $postcode.val(formatted);
            try {
              $postcode[0].setSelectionRange(cursorPos, cursorPos);
            } catch(e) {}
          }

          var digits = formatted.replace(/\D+/g, '');
          if (digits.length === 5) {
            fetchZone();
          } else {
            $info.text('');
            lastSentDigits = null;
          }
        });

      });
    })(jQuery);
    </script>
    <?php
}
add_action( 'wp_footer', 'portalsluchu_kasa_postcode_helper_footer_script', 50 );
