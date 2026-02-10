<?php
/**
 * Plugin Name: portalsluchu – Pobierz dane z profilu do adresów (billing/shipping)
 * Description: Dodaje przycisk na stronach edycji adresu WooCommerce (rozliczeniowy i wysyłki), który uzupełnia imię i nazwisko z profilu oraz pomaga szybko przejść do pola telefonu.
 * Version: 1.0.0
 * Author: portalsluchu.pl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wstrzyknięcie UI + JS na stronach edycji adresów w "Moje konto".
 * WooCommerce używa endpointów: edit-address/billing oraz edit-address/shipping.
 */
add_action( 'wp_footer', function() {
    if ( is_admin() ) {
        return;
    }

    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-address' ) ) {
        return;
    }

    $user = wp_get_current_user();
    if ( ! $user || ! $user->ID ) {
        return;
    }

    $first = trim( (string) $user->first_name );
    $last  = trim( (string) $user->last_name );

    // Fallback: jeśli brak first/last, spróbujmy split display_name
    if ( $first === '' && $last === '' ) {
        $display = trim( (string) $user->display_name );
        if ( $display !== '' ) {
            $parts = preg_split( '/\s+/', $display );
            if ( is_array( $parts ) && ! empty( $parts ) ) {
                $first = $parts[0];
                if ( count( $parts ) > 1 ) {
                    $last = implode( ' ', array_slice( $parts, 1 ) );
                }
            }
        }
    }

    // Escapujemy do JS
    $js_first = wp_json_encode( $first );
    $js_last  = wp_json_encode( $last );

    ?>
    <script>
    (function() {
        // Szukamy formularza adresowego WooCommerce na stronie edycji adresu.
        // W praktyce to: form.woocommerce-EditAccountForm lub form.woocommerce-address-fields__field-wrapper (różnie w motywach),
        // więc szukamy po polach input.
        function qs(sel, ctx){ return (ctx||document).querySelector(sel); }
        function qsa(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }

        var firstFromProfile = <?php echo $js_first; ?> || '';
        var lastFromProfile  = <?php echo $js_last; ?> || '';

        // Pola mogą być billing_first_name / shipping_first_name itp.
        var inputs = qsa('input[id="billing_first_name"], input[id="shipping_first_name"], input[name="billing_first_name"], input[name="shipping_first_name"]');
        if (!inputs.length) return; // to nie jest strona edycji adresu

        // Ustal prefix: billing_ albo shipping_
        var prefix = null;
        if (qs('#billing_first_name') || qs('input[name="billing_first_name"]')) prefix = 'billing';
        if (qs('#shipping_first_name') || qs('input[name="shipping_first_name"]')) prefix = 'shipping';
        if (!prefix) return;

        function getField(name) {
            return qs('#' + name) || qs('input[name="' + name + '"]');
        }

        function setIfEmpty(el, value) {
            if (!el) return;
            var current = (el.value || '').trim();
            if (current !== '') return; // nie nadpisujemy
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Zbuduj pasek akcji
        var actions = document.createElement('div');
        actions.className = 'portalsluchu-copy-profile-actions';
        actions.style.cssText = 'margin: 0 0 14px 0; padding: 12px 14px; border: 1px solid #ddd; background: #f8f8f8; border-radius: 6px;';

        var title = document.createElement('div');
        title.style.cssText = 'font-weight: 700; margin-bottom: 8px;';
        title.textContent = 'Ułatwienie: pobierz dane z profilu';
        actions.appendChild(title);

        var btnFill = document.createElement('button');
        btnFill.type = 'button';
        btnFill.className = 'button';
        btnFill.textContent = 'Pobierz imię i nazwisko z profilu';
        btnFill.style.cssText = 'margin-right: 10px;';
        actions.appendChild(btnFill);

        // Przycisk telefonu – tylko jeśli pole istnieje (zwykle billing_phone)
        var phoneField = getField(prefix + '_phone') || getField('billing_phone'); // dodatkowy fallback
        var btnPhone = null;
        if (phoneField) {
            btnPhone = document.createElement('button');
            btnPhone.type = 'button';
            btnPhone.className = 'button button-primary';
            btnPhone.textContent = 'Wpisz telefon';
            actions.appendChild(btnPhone);
        }

        var note = document.createElement('div');
        note.style.cssText = 'margin-top: 10px; font-size: 12px; color: #555; line-height: 1.35;';
        note.textContent =
          'Kliknij, aby uzupełnić imię i nazwisko z profilu. Telefon jest wymagany — upewnij się, że jest wpisany w adresie rozliczeniowym.';
        actions.appendChild(note);

        btnFill.addEventListener('click', function() {
            var firstField = getField(prefix + '_first_name');
            var lastField  = getField(prefix + '_last_name');

            if (firstFromProfile) setIfEmpty(firstField, firstFromProfile);
            if (lastFromProfile)  setIfEmpty(lastField, lastFromProfile);

            // jeśli po kliknięciu nadal pusto – pokaż krótką informację
            var f = firstField ? (firstField.value || '').trim() : '';
            var l = lastField  ? (lastField.value || '').trim() : '';
            if (!f && !l) {
                alert('Nie znaleziono imienia i nazwiska w profilu (Szczegóły konta). Uzupełnij je tam lub wpisz ręcznie.');
            }
        });

        if (btnPhone && phoneField) {
            btnPhone.addEventListener('click', function() {
                try {
                    phoneField.focus();
                    phoneField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } catch(e) {
                    phoneField.focus();
                }
            });
        }

        // Wstawiamy blok nad formularzem adresowym.
        // Spróbujmy znaleźć wrapper formularza po polach first_name.
        var firstFieldForInsert = getField(prefix + '_first_name');
        var form = firstFieldForInsert ? firstFieldForInsert.closest('form') : null;
        if (form) {
            form.insertBefore(actions, form.firstChild);
        }
    })();
    </script>
    <?php
}, 50 );