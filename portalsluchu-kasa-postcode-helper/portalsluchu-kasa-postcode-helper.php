<?php
/**
 * Plugin Name: portalsluchu – Kod pocztowy na stronie "Kasa"
 * Description: portalsluchu-kasa-postcode-helper / Auto-formatowanie kodu pocztowego (01-000) + natychmiastowe wyliczanie strefy dojazdu na stronie zamówienia (/kasa). Wymaga wtyczki "portalsluchu – Strefy dojazdu".
 * Author: portalsluchu.pl
 * Version: 1.0.1
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

	?>
	<script>
	(function($) {
	  $(function() {

		// Checkout postcode fields
		var $billingPostcode  = $('#billing_postcode');
		var $shippingPostcode = $('#shipping_postcode');

		// Your info element
		var $info = $('#portalsluchu_delivery_info');

		if (!$info.length) {
		  return;
		}

		var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		var lastSentDigits = null;

		function formatPostcode(value) {
		  var digits = (value || '').replace(/\D+/g, '').slice(0, 5);
		  if (digits.length <= 2) return digits;
		  return digits.slice(0, 2) + '-' + digits.slice(2);
		}

		function normalizeToDigits(value) {
		  return (value || '').replace(/\D+/g, '').slice(0, 5);
		}

		function currentPostcodeDigits() {
		  // If "ship to different address" is enabled, Woo uses shipping fields.
		  // Otherwise, billing fields determine the address.
		  var useShipping = $('#ship-to-different-address-checkbox').is(':checked');

		  var raw = useShipping ? $shippingPostcode.val() : $billingPostcode.val();
		  return normalizeToDigits(raw);
		}

		function applyFormattingToField($field) {
		  if (!$field.length) return;

		  var before    = $field.val();
		  var formatted = formatPostcode(before);

		  if (before !== formatted) {
			var cursorPos = $field[0].selectionStart || formatted.length;
			$field.val(formatted);
			try { $field[0].setSelectionRange(cursorPos, cursorPos); } catch(e) {}
		  }
		}

		function fetchZone() {
		  var digits = currentPostcodeDigits();

		  if (digits.length !== 5) {
			$info.text(''); // czekamy aż będzie pełny kod
			lastSentDigits = null;
			return;
		  }

		  if (digits === lastSentDigits) {
			return;
		  }
		  lastSentDigits = digits;

		  $.post(ajaxUrl, {
			action:   'portalsluchu_dojazd_info',
			postcode: digits
		  }).done(function(resp) {

			if (!resp || !resp.success || !resp.data) {
			  $info.text('Nie udało się ustalić strefy. Sprawdź format 00-000 (np. 30-001) i spróbuj ponownie.');
			  return;
			}

			var zone      = parseInt(resp.data.zone, 10);
			var priceHtml = resp.data.price_formatted || '';

			if (!(zone >= 1 && zone <= 5) || !priceHtml) {
			  $info.text('Nie udało się ustalić strefy. Sprawdź format 00-000 (np. 30-001) i spróbuj ponownie.');
			  return;
			}

			$info.html(
			  'Kod należy do strefy ' + zone + '. Koszt dojazdu: ' + priceHtml + '.<br>' +
			  'Na etapie płatności zweryfikujemy kod z adresem dostawy; jeśli będzie inny, koszt zostanie przeliczony.'
			);

		  }).fail(function() {
			$info.text('Nie udało się ustalić strefy. Sprawdź połączenie i spróbuj ponownie.');
		  });
		}

		// Format + fetch on input
		$(document).on('input', '#billing_postcode, #shipping_postcode', function() {
		  applyFormattingToField($(this));
		  fetchZone();
		  $('body').trigger('update_checkout');
		});

		// When toggling "ship to different address" we must re-evaluate which postcode is active
		$(document).on('change', '#ship-to-different-address-checkbox', function() {
		  fetchZone();
		  $('body').trigger('update_checkout');
		});

		// Initial run
		fetchZone();

	  });
	})(jQuery);
	</script>
	<?php
}
add_action( 'wp_footer', 'portalsluchu_kasa_postcode_helper_footer_script', 50 );