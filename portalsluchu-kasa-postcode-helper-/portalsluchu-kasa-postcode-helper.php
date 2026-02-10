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

        // Try to find postcode field (shipping first, then billing as fallback)
        var $postcode = $('#shipping_postcode');
        if (!$postcode.length) {
          $postcode = $('#billing_postcode');
        }
        
        // Info container - create if it doesn't exist
        var $info = $('#portalsluchu_delivery_info');
        if (!$info.length && $postcode.length) {
          // Create info container after the postcode field
          $postcode.after('<div id="portalsluchu_delivery_info" style="margin-top:8px; padding:8px; border-left:3px solid #0073aa; background:#f0f0f0; font-size:13px;"></div>');
          $info = $('#portalsluchu_delivery_info');
        }

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
            $info.html('<span style="color:#666;">Wprowadź pełny kod pocztowy (format 00-000), aby wyliczyć strefę i koszt dojazdu.</span>');
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
              $info.html('<span style="color:#dc3545;">Nie udało się ustalić strefy. Sprawdź kod pocztowy (format 00-000) i spróbuj ponownie.</span>');
              return;
            }

            var zone       = parseInt(resp.data.zone, 10);
            var priceHtml  = resp.data.price_formatted || '';

            $info.html(
              '<strong style="color:#28a745;">Strefa ' + zone + ', koszt dojazdu: ' + priceHtml + '</strong><br>' +
              '<small style="color:#666;">Ostateczna strefa i koszt będą zweryfikowane na podstawie kodu pocztowego z adresu dostawy podanego poniżej.</small>'
            );

          }).fail(function() {
            $info.html('<span style="color:#dc3545;">Błąd połączenia. Sprawdź kod pocztowy (format 00-000) i spróbuj ponownie.</span>');
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
            $info.html('<span style="color:#666;">Wprowadź pełny kod pocztowy (format 00-000), aby wyliczyć strefę i koszt dojazdu.</span>');
            lastSentDigits = null;
          }
        });

        // Check on load if there's already a postcode
        if ($postcode.val()) {
          var formatted = formatPostcode($postcode.val());
          $postcode.val(formatted);
          var digits = formatted.replace(/\D+/g, '');
          if (digits.length === 5) {
            fetchZone();
          }
        }

        // Also check when WooCommerce updates the checkout
        $(document.body).on('updated_checkout', function() {
          if ($postcode.val()) {
            var formatted = formatPostcode($postcode.val());
            $postcode.val(formatted);
            var digits = formatted.replace(/\D+/g, '');
            if (digits.length === 5) {
              fetchZone();
            }
          }
        });

      });
    })(jQuery);
    </script>
    <?php
}
add_action( 'wp_footer', 'portalsluchu_kasa_postcode_helper_footer_script', 50 );
