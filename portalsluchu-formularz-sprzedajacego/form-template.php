<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KONFIGURACJA
 * --------------------------------------------------
 */
$listing_fee_product_id = 921; // produkt "Opłata za wystawienie ogłoszenia"
$free_code              = 'zglaszamzazero'; // tajny kod rabatowy – nie pokazujemy go w UI

$success_msg = '';
$error_msg   = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset( $_POST['portalsluchu_form_nonce'] )
    && wp_verify_nonce( $_POST['portalsluchu_form_nonce'], 'portalsluchu_formularz_sprzedajacy' )
) {

    if ( ! is_user_logged_in() ) {
        $error_msg = 'Aby wysłać formularz, musisz być zalogowany.';
    } else {

        $current_user = wp_get_current_user();

        $seller_name  = trim( $current_user->first_name . ' ' . $current_user->last_name );
        if ( ! $seller_name ) {
            $seller_name = $current_user->display_name;
        }
        $seller_email = $current_user->user_email;
        $seller_phone = get_user_meta( $current_user->ID, 'billing_phone', true );

        $hearing_aid_model = isset( $_POST['hearing_aid_model'] ) ? sanitize_text_field( $_POST['hearing_aid_model'] ) : '';
        $purchase_year     = isset( $_POST['purchase_year'] ) ? sanitize_text_field( $_POST['purchase_year'] ) : '';
        $device_condition  = isset( $_POST['device_condition'] ) ? sanitize_text_field( $_POST['device_condition'] ) : '';
        $has_damage        = isset( $_POST['has_damage'] ) ? sanitize_text_field( $_POST['has_damage'] ) : '';
        $description       = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
        $sale_price        = isset( $_POST['sale_price'] ) ? floatval( $_POST['sale_price'] ) : 0;

        $listing_code    = isset( $_POST['listing_code'] ) ? sanitize_text_field( $_POST['listing_code'] ) : '';
        $submission_mode = isset( $_POST['submission_mode'] ) ? sanitize_text_field( $_POST['submission_mode'] ) : 'code';

        $has_valid_code = ( strtolower( trim( $listing_code ) ) === strtolower( $free_code ) );

        if ( ! $hearing_aid_model || ! $description || $sale_price <= 0 ) {
            $error_msg = 'Uzupełnij wszystkie wymagane pola: model, opis, cena.';
        }

        if ( ! $error_msg ) {
            if ( $submission_mode === 'code' && ! $has_valid_code ) {
                $error_msg = 'Kod rabatowy jest nieprawidłowy. Popraw go lub wybierz opcję opłaty.';
            } elseif ( $submission_mode === 'pay' && ! $listing_fee_product_id ) {
                $error_msg = 'Brak skonfigurowanego produktu opłaty za wystawienie (skontaktuj się z administratorem).';
            }
        }

        if ( ! $error_msg ) {

            $post_data = array(
                'post_title'   => 'Aparat: ' . $hearing_aid_model,
                'post_content' => $description,
                'post_status'  => 'draft',
                'post_type'    => 'product',
                'post_author'  => $current_user->ID,
            );

            $product_id = wp_insert_post( $post_data );

            if ( is_wp_error( $product_id ) ) {
                $error_msg = 'Wystąpił błąd przy zapisie produktu. Spróbuj ponownie.';
            } else {

                if ( class_exists( 'WC_Product_Simple' ) ) {
                    $product = new WC_Product_Simple( $product_id );
                    $product->set_price( $sale_price );
                    $product->set_regular_price( $sale_price );
                    $product->set_manage_stock( true );
                    $product->set_stock_quantity( 1 );
                    $product->set_catalog_visibility( 'hidden' );
                    $product->save();
                }

                update_post_meta( $product_id, 'seller_name',  $seller_name );
                update_post_meta( $product_id, 'seller_email', $seller_email );
                update_post_meta( $product_id, 'seller_phone', $seller_phone );

                update_post_meta( $product_id, 'hearing_aid_model', $hearing_aid_model );
                update_post_meta( $product_id, 'purchase_year',     intval( $purchase_year ) );
                update_post_meta( $product_id, 'device_condition',  $device_condition );
                update_post_meta( $product_id, 'has_damage',        $has_damage );
                update_post_meta( $product_id, 'sale_price',        $sale_price );

                $payment_status = ( $submission_mode === 'code' ) ? 'coupon' : 'pending_payment';
                update_post_meta( $product_id, 'listing_payment_status', $payment_status );

                $attachment_ids = array();

                for ( $i = 1; $i <= 7; $i++ ) {
                    $field_name = 'file_' . $i;

                    if ( empty( $_FILES[ $field_name ]['name'] ) ) {
                        continue;
                    }

                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    $file_id = media_handle_upload( $field_name, $product_id );

                    if ( ! is_wp_error( $file_id ) ) {
                        $attachment_ids[] = $file_id;
                    }
                }

                if ( ! empty( $attachment_ids ) ) {
                    set_post_thumbnail( $product_id, $attachment_ids[0] );

                    if ( count( $attachment_ids ) > 1 ) {
                        $gallery_ids = array_slice( $attachment_ids, 1 );
                        update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
                    }
                }

                $admin_email = 'admin@serseo.pl';
                $headers     = array( 'Content-Type: text/plain; charset=UTF-8' );

                if ( $payment_status === 'coupon' ) {
                    $status_label_admin = 'Opłacone kodem';
                    $status_msg_user    = "Twoje ogłoszenie zostało przyjęte do weryfikacji przez administratora.\n";
                } else {
                    $status_label_admin = 'Oczekuje na opłatę';
                    $status_msg_user    = "Twoje ogłoszenie zostało zapisane. Czekamy na zaksięgowanie opłaty za wystawienie.\n";
                }

                $subject_admin = 'Nowe zgłoszenie sprzedaży aparatu – portalsluchu.pl';

                $message_admin  = "Pojawiło się nowe zgłoszenie sprzedaży aparatu.\n\n";
                $message_admin .= "Model: {$hearing_aid_model}\n";
                $message_admin .= "Cena (proponowana): {$sale_price} zł\n\n";
                $message_admin .= "Sprzedający:\n";
                $message_admin .= "Imię i nazwisko: {$seller_name}\n";
                $message_admin .= "Email: {$seller_email}\n";
                $message_admin .= "Telefon: {$seller_phone}\n\n";
                $message_admin .= "Status opłaty za wystawienie: {$status_label_admin}\n\n";
                $message_admin .= "Link do edycji produktu w panelu:\n";
                $message_admin .= admin_url( 'post.php?post=' . $product_id . '&action=edit' ) . "\n";

                wp_mail( $admin_email, $subject_admin, $message_admin, $headers );

                $subject_user = 'Twoje ogłoszenie na portalsluchu.pl';

                $message_user  = "Dziękujemy za wypełnienie formularza sprzedaży aparatu na portalsluchu.pl.\n\n";
                $message_user .= "Model aparatu: {$hearing_aid_model}\n";
                $message_user .= "Cena (proponowana): {$sale_price} zł\n\n";
                $message_user .= $status_msg_user . "\n";
                $message_user .= "Jeśli coś się nie zgadza, skontaktuj się z administratorem portalu.\n";

                wp_mail( $seller_email, $subject_user, $message_user, $headers );

                if ( $submission_mode === 'pay' ) {
                    if ( function_exists( 'WC' ) && WC()->cart && $listing_fee_product_id ) {
                        WC()->cart->empty_cart();
                        WC()->cart->add_to_cart( $listing_fee_product_id, 1 );

                        if ( WC()->session ) {
                            WC()->session->set( 'portalsluchu_listing_id', $product_id );
                        }
                    }

                    if ( function_exists( 'wc_get_checkout_url' ) ) {
                        $redirect_url = wc_get_checkout_url();
                    } else {
                        $redirect_url = wc_get_page_permalink( 'checkout' );
                    }

                    wp_safe_redirect( $redirect_url );
                    exit;
                } else {
                    $success_msg = 'Dziękujemy, formularz został wysłany do administratora.';
                }
            }
        }
    }
}
?>

<?php if ( $success_msg ) : ?>
  <div class="portalsluchu-success" style="padding:12px 16px; margin-bottom:16px; background:#e6ffed; border-left:4px solid #00a32a;">
    <?php echo wp_kses_post( $success_msg ); ?>
  </div>
<?php endif; ?>

<?php if ( $error_msg ) : ?>
  <div class="portalsluchu-error" style="padding:12px 16px; margin-bottom:16px; background:#ffe6e6; border-left:4px solid #cc0000;">
    <?php echo wp_kses_post( $error_msg ); ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="portalsluchu-sell-form">
  <?php wp_nonce_field( 'portalsluchu_formularz_sprzedajacy', 'portalsluchu_form_nonce' ); ?>

  <div class="portalsluchu-listing-box" style="border:1px solid #ddd; padding:15px; margin-bottom:20px;">
    <p><strong>Wystawienie ogłoszenia</strong></p>
    <p>
      Możesz wprowadzić kod rabatowy, aby przesłać formularz bez opłaty,
      albo przejść do płatności 10 zł za wystawienie ogłoszenia.
    </p>
    <p>
      <label for="listing_code">Kod rabatowy:</label><br>
      <input
        type="text"
        name="listing_code"
        id="listing_code"
        value=""
        autocomplete="off"
        data-valid-code="<?php echo esc_attr( strtolower( $free_code ) ); ?>"
      >
      <span id="listing_code_status" style="margin-left:8px; font-weight:bold;"></span>
    </p>
  </div>

  <p>
    <label for="hearing_aid_model">Model aparatu *:</label><br>
    <input type="text" name="hearing_aid_model" id="hearing_aid_model" value="<?php echo isset( $_POST['hearing_aid_model'] ) ? esc_attr( $_POST['hearing_aid_model'] ) : ''; ?>" required>
  </p>

  <p>
    <label for="purchase_year">Rok zakupu:</label><br>
    <input
      type="number"
      name="purchase_year"
      id="purchase_year"
      min="2019"
      max="<?php echo date( 'Y' ); ?>"
      step="1"
      value="<?php echo isset( $_POST['purchase_year'] ) ? esc_attr( $_POST['purchase_year'] ) : date( 'Y' ); ?>"
    >
  </p>

  <p>
    <label for="device_condition">Stan aparatu:</label><br>
    <?php
      $cond_value = isset( $_POST['device_condition'] ) ? $_POST['device_condition'] : '';
    ?>
    <select name="device_condition" id="device_condition">
      <option value="">– wybierz –</option>
      <option value="bardzo dobry" <?php selected( $cond_value, 'bardzo dobry' ); ?>>Bardzo dobry</option>
      <option value="dobry" <?php selected( $cond_value, 'dobry' ); ?>>Dobry</option>
      <option value="uszkodzony" <?php selected( $cond_value, 'uszkodzony' ); ?>>Uszkodzony</option>
    </select>
  </p>

  <p>
    <label for="has_damage">Czy aparat jest uszkodzony?</label><br>
    <input type="text" name="has_damage" id="has_damage" value="<?php echo isset( $_POST['has_damage'] ) ? esc_attr( $_POST['has_damage'] ) : ''; ?>">
  </p>

  <p>
    <label for="sale_price">Cena sprzedaży (zł) *:</label><br>
    <input type="number" step="0.01" min="0" name="sale_price" id="sale_price" value="<?php echo isset( $_POST['sale_price'] ) ? esc_attr( $_POST['sale_price'] ) : ''; ?>" required>
  </p>

  <p>
    <label for="description">Opis aparatu *:</label><br>
    <textarea name="description" id="description" rows="6" required><?php echo isset( $_POST['description'] ) ? esc_textarea( $_POST['description'] ) : ''; ?></textarea>
  </p>

  <fieldset style="border:1px solid #ddd; padding:10px; margin-bottom:15px;">
    <legend>Zdjęcia i dokumenty (opcjonalnie)</legend>
    <p>Możesz dodać do 7 plików (zdjęcia aparatu, dokumenty, instrukcje).</p>
    <?php for ( $i = 1; $i <= 7; $i++ ) : ?>
      <p>
        <label for="file_<?php echo $i; ?>">Plik <?php echo $i; ?>:</label><br>
        <input type="file" name="file_<?php echo $i; ?>" id="file_<?php echo $i; ?>">
      </p>
    <?php endfor; ?>
  </fieldset>

  <input type="hidden" name="submission_mode" id="submission_mode" value="code">

  <p>
    <button type="submit" id="submit_btn" style="display:none; margin-right:10px;">Wyślij bez opłaty (z kodem)</button>
    <button type="button" class="button button-primary" id="ps-go-to-fee" onclick="location.href='<?php echo esc_url( add_query_arg( 'add-to-cart', 1087, wc_get_checkout_url() ) ); ?>'">Przejdź do opłaty 10 zł</button>
    <span id="loading_text" style="display:none; margin-left:10px;">Wysyłam...</span>
  </p>
</form>

<script>
(function() {
  var form        = document.querySelector('.portalsluchu-sell-form');
  if (!form) return;

  var codeInput   = document.getElementById('listing_code');
  var codeStatus  = document.getElementById('listing_code_status');
  var submitBtn   = document.getElementById('submit_btn');
  var payBtn      = document.getElementById('pay_btn');
  var modeField   = document.getElementById('submission_mode');
  var loadingText = document.getElementById('loading_text');

  var validCode   = '';
  if (codeInput) {
    validCode = (codeInput.getAttribute('data-valid-code') || '').toLowerCase();
  }

  if (codeInput) {
    codeInput.addEventListener('input', function() {
      var value = codeInput.value.trim().toLowerCase();

      if (!value) {
        codeStatus.textContent = '';
        if (submitBtn) {
          submitBtn.style.display = 'none';
        }
        if (payBtn) {
          payBtn.disabled = false;
        }
        return;
      }

      if (value === validCode) {
        codeStatus.textContent = 'Dziękujemy, kod przyjęty.';
        codeStatus.style.color = 'green';
        if (submitBtn) {
          submitBtn.style.display = 'inline-block';
        }
        if (payBtn) {
          payBtn.disabled = false;
        }
      } else {
        codeStatus.textContent = 'Kod nie pasuje.';
        codeStatus.style.color = 'red';
        if (submitBtn) {
          submitBtn.style.display = 'none';
        }
        if (payBtn) {
          payBtn.disabled = false;
        }
      }
    });
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', function() {
      if (modeField) {
        modeField.value = 'code';
      }
    });
  }

  if (payBtn) {
    payBtn.addEventListener('click', function() {
      if (modeField) {
        modeField.value = 'pay';
      }
    });
  }

  form.addEventListener('submit', function() {
    if (submitBtn) submitBtn.disabled = true;
    if (payBtn)   payBtn.disabled   = true;
    if (!loadingText) return;

    var percent = 0;
    loadingText.style.display = 'inline';

    var interval = setInterval(function() {
      percent += 2;
      loadingText.textContent = 'Wysyłam... ' + percent + '%';
      if (percent >= 100) {
        clearInterval(interval);
      }
    }, 200);
  });
})();
</script>
