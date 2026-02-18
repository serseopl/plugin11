<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KONFIGURACJA
 * --------------------------------------------------
 */
$listing_fee_product_id = 1087; // produkt "Opłata za wystawienie ogłoszenia"
$free_code              = 'zazero'; // tajny kod rabatowy – nie pokazujemy go w UI

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
		$submission_mode = isset( $_POST['submission_mode'] ) ? sanitize_text_field( $_POST['submission_mode'] ) : 'code'; // code | pay

		$has_valid_code = ( strtolower( trim( $listing_code ) ) === strtolower( $free_code ) );

		// Server-side required fields
		if ( ! $hearing_aid_model || ! $description || $sale_price <= 0 ) {
			$error_msg = 'Uzupełnij wszystkie wymagane pola: model, opis, cena.';
		}

		// Wymagane: przynajmniej 1 zdjęcie aparatu
		if ( ! $error_msg ) {
			$file_ok = false;
			if ( isset( $_FILES['file_1'] ) && isset( $_FILES['file_1']['error'] ) ) {
				$file_ok = ( (int) $_FILES['file_1']['error'] === UPLOAD_ERR_OK );
			}
			if ( ! $file_ok ) {
				$error_msg = 'Dodaj proszę przynajmniej 1 zdjęcie aparatu (pole „Zdjęcie aparatu *”).';
			}
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
				update_post_meta( $product_id, 'description',       $description );

				// Upload files
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';

				$attachment_ids = array();
				for ( $i = 1; $i <= 7; $i++ ) {
					$key = 'file_' . $i;
					if ( empty( $_FILES[ $key ] ) || empty( $_FILES[ $key ]['name'] ) ) {
						continue;
					}
					$att_id = media_handle_upload( $key, $product_id );
					if ( ! is_wp_error( $att_id ) ) {
						$attachment_ids[] = (int) $att_id;

						// First image as product thumbnail
						if ( $i === 1 ) {
							set_post_thumbnail( $product_id, $att_id );
						}
					}
				}
				if ( ! empty( $attachment_ids ) ) {
					update_post_meta( $product_id, 'portalsluchu_attachments', $attachment_ids );
				}

				// Payment status meta
				if ( $submission_mode === 'code' ) {
					update_post_meta( $product_id, 'listing_payment_status', 'coupon' );
				} else {
					update_post_meta( $product_id, 'listing_payment_status', 'pending_payment' );
				}

				// Emails (minimal, safe)

$admin_email = get_option( 'admin_email' );
$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

$subject_admin = 'Nowe zgłoszenie sprzedaży aparatu – portalsluchu.pl';
$message_admin  = "Pojawiło się nowe zgłoszenie sprzedaży aparatu.\n\n";
$message_admin .= "Model: {$hearing_aid_model}\n";
$message_admin .= "Cena (proponowana): {$sale_price} zł\n\n";
$message_admin .= "Sprzedający:\n";
$message_admin .= "Imię i nazwisko: {$seller_name}\n";
$message_admin .= "Email: {$seller_email}\n";
$message_admin .= "Telefon: {$seller_phone}\n\n";

$status_label_admin = ( $submission_mode === 'code' ) ? 'Opłacone kodem' : 'Oczekuje na opłatę';
$message_admin .= "Status opłaty za wystawienie: {$status_label_admin}\n\n";

$message_admin .= "Link do edycji produktu w panelu:\n";
$message_admin .= admin_url( 'post.php?post=' . $product_id . '&action=edit' ) . "\n";

$subject_user = 'Twoje ogłoszenie na portalsluchu.pl';
$message_user  = "Dziękujemy za wypełnienie formularza sprzedaży aparatu na portalsluchu.pl.\n\n";
$message_user .= "Model aparatu: {$hearing_aid_model}\n";
$message_user .= "Cena (proponowana): {$sale_price} zł\n\n";

// NOWE TEKSTY zależne od trybu:
if ( $submission_mode === 'code' ) {
	$message_user .= "Skorzystałeś z kodu — Twoje zgłoszenie zostało już przesłane do administratora.\n";
	$message_user .= "W razie potrzeby skontaktuj się z administratorem portalu.\n";

	// Admin dostaje maila od razu przy kodzie
	wp_mail( $admin_email, $subject_admin, $message_admin, $headers );

} else {
	$message_user .= "Wybrałeś płatne rozpatrzenie — Twoje zgłoszenie zostało zapisane.\n";
	$message_user .= "Administrator otrzyma Twoje zgłoszenie dopiero po zaksięgowaniu opłaty 10 zł.\n";
	$message_user .= "Jeśli coś się nie zgadza, skontaktuj się z administratorem portalu.\n";

	// DLA pay: NIE wysyłamy maila do admina tutaj.
	// Mail do admina wyślemy dopiero po opłaceniu (hook w pliku pluginu).
}

wp_mail( $seller_email, $subject_user, $message_user, $headers );

				if ( $submission_mode === 'pay' ) {
					if ( function_exists( 'WC' ) && WC()->cart && $listing_fee_product_id ) {
						WC()->cart->empty_cart();
						WC()->cart->add_to_cart( $listing_fee_product_id, 1 );

						if ( WC()->session ) {
							WC()->session->set( 'portalsluchu_listing_id', $product_id );
						}
					}

					$redirect_url = function_exists( 'wc_get_checkout_url' )
						? wc_get_checkout_url()
						: wc_get_page_permalink( 'checkout' );

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
  <div class="ps-alert ps-alert-success" style="margin:32px auto;
      background:rgba(236,253,245,1);
      border: 1px solid #A7F3D0;
      color:#065f46;
      border-radius:14px;
      box-shadow:0 10px 24px rgba(0,0,0,.04);
      font-weight:800;
      font-size:20px;
      padding:24px 26px;
      max-width:400px;
      text-align:center;">
      <?php echo wp_kses_post( $success_msg ); ?>
  </div>
  <script>
  // reset, schowaj formularz po wysłaniu
  document.addEventListener("DOMContentLoaded", function(){
      var form = document.querySelector('.portalsluchu-sell-form');
      if(form){ form.reset(); form.style.display="none"; }
  });
  </script>
<?php endif; ?>

<?php if ( $error_msg ) : ?>
  <div class="portalsluchu-error" style="margin:22px auto; color:#991b1b; padding:20px;
       border-radius:14px; border:1px solid #FCA5A5; background:rgba(254,242,242,1); max-width:400px; text-align:center;">
    <?php echo wp_kses_post( $error_msg ); ?>
  </div>
<?php endif; ?>

<?php if ( ! $success_msg ) : ?>
<div class="ps-sell-layout">
  <aside class="ps-sidecard">
    <div class="ps-card-title">Twoje dane kontaktowe</div>
    <p class="ps-muted" style="margin:0;">
      <strong><?php echo do_shortcode('[wc_user_name]'); ?></strong><br>
      <?php echo do_shortcode('[wc_user_phone]'); ?><br>
      <?php echo do_shortcode('[wc_user_email]'); ?>
    </p>
  </aside>

  <main class="ps-card">
    <div class="ps-card-title">Wystawienie ogłoszenia</div>
    <p class="ps-muted" style="margin:0 0 14px 0;">
      Możesz wprowadzić kod rabatowy, aby przesłać formularz bez opłaty, albo przejść do płatności 10 zł za wystawienie ogłoszenia.
    </p>

    <form method="post" enctype="multipart/form-data" class="portalsluchu-sell-form" novalidate>
      <?php wp_nonce_field( 'portalsluchu_formularz_sprzedajacy', 'portalsluchu_form_nonce' ); ?>

      <div class="ps-grid">
        <div class="ps-field">
          <label for="listing_code">Kod rabatowy</label>
          <input
            type="text"
            name="listing_code"
            id="listing_code"
            value=""
            autocomplete="off"
            data-valid-code="<?php echo esc_attr( strtolower( $free_code ) ); ?>"
          >
          <div class="ps-muted" style="margin-top:6px;">
            <span id="listing_code_status" style="font-weight:900;"></span>
          </div>
        </div>

        <div class="ps-field">
          <label>&nbsp;</label>
          <div class="ps-muted">Jeśli masz kod — wpisz go, a pojawi się przycisk wysyłki bez opłaty.</div>
        </div>
      </div>

      <hr style="margin:14px 0; border:0; border-top:1px solid rgba(15,23,42,.10);">

      <div class="ps-grid">
        <div class="ps-field">
          <label for="hearing_aid_model">Model aparatu <span class="ps-required">*</span></label>
          <input type="text" name="hearing_aid_model" id="hearing_aid_model"
                 value="<?php echo isset( $_POST['hearing_aid_model'] ) ? esc_attr( $_POST['hearing_aid_model'] ) : ''; ?>" required>
        </div>

        <div class="ps-field">
          <label for="purchase_year">Rok zakupu</label>
          <input type="number" name="purchase_year" id="purchase_year"
                 min="2019" max="<?php echo date('Y'); ?>" step="1"
                 value="<?php echo isset( $_POST['purchase_year'] ) ? esc_attr( $_POST['purchase_year'] ) : date('Y'); ?>">
        </div>

        <div class="ps-field">
          <label for="device_condition">Stan aparatu</label>
          <select name="device_condition" id="device_condition">
            <option value="">– wybierz –</option>
            <option value="idealny" <?php selected( isset($_POST['device_condition']) ? $_POST['device_condition'] : '', 'idealny' ); ?>>Idealny</option>
            <option value="bardzo_dobry" <?php selected( isset($_POST['device_condition']) ? $_POST['device_condition'] : '', 'bardzo_dobry' ); ?>>Bardzo dobry</option>
            <option value="dobry" <?php selected( isset($_POST['device_condition']) ? $_POST['device_condition'] : '', 'dobry' ); ?>>Dobry</option>
            <option value="do_poprawy" <?php selected( isset($_POST['device_condition']) ? $_POST['device_condition'] : '', 'do_poprawy' ); ?>>Do poprawy</option>
          </select>
        </div>

        <div class="ps-field">
          <label for="has_damage">Widoczne uszkodzenia</label>
          <input type="text" name="has_damage" id="has_damage"
                 value="<?php echo isset( $_POST['has_damage'] ) ? esc_attr( $_POST['has_damage'] ) : ''; ?>">
        </div>

        <div class="ps-field">
          <label for="sale_price">Cena sprzedaży (zł) <span class="ps-required">*</span></label>
          <input type="number" step="0.01" min="0" name="sale_price" id="sale_price"
                 value="<?php echo isset( $_POST['sale_price'] ) ? esc_attr( $_POST['sale_price'] ) : ''; ?>" required>
        </div>

        <div class="ps-field" style="grid-column: 1 / -1;">
          <label for="description">Opis aparatu <span class="ps-required">*</span></label>
          <textarea name="description" id="description" rows="6" required><?php echo isset( $_POST['description'] ) ? esc_textarea( $_POST['description'] ) : ''; ?></textarea>
        </div>
      </div>

      <div class="ps-card" style="margin-top:14px;">
        <div class="ps-card-title">Zdjęcia i dokumenty</div>
        <p class="ps-muted" style="margin:0 0 10px 0;">
          Dodaj przynajmniej <strong>1 zdjęcie aparatu</strong> (wymagane). Możesz dodać do 7 plików (zdjęcia, dokumenty, instrukcje).
        </p>

        <div id="ps-form-alert" class="ps-alert ps-alert-danger" style="display:none;"></div>

        <div class="ps-files">
          <?php for ( $i = 1; $i <= 7; $i++ ) : ?>
            <div class="ps-file-row">
              <label for="file_<?php echo $i; ?>">
                <?php if ( $i === 1 ) : ?>
                  Zdjęcie aparatu <span class="ps-required">*</span>
                <?php else : ?>
                  Plik <?php echo $i; ?>
                <?php endif; ?>
              </label>

              <div class="ps-file-input">
                <input
                  class="ps-file-native"
                  type="file"
                  name="file_<?php echo $i; ?>"
                  id="file_<?php echo $i; ?>"
                  <?php echo $i === 1 ? 'required accept="image/*"' : ''; ?>
                >
                <button type="button" class="ps-btn ps-btn-ghost ps-file-btn" data-file-for="file_<?php echo $i; ?>">
                  Wybierz plik
                </button>
                <span class="ps-file-name" id="ps_file_name_<?php echo $i; ?>">Nie wybrano pliku</span>
              </div>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <input type="hidden" name="submission_mode" id="submission_mode" value="pay">

      <div class="ps-actions">
        <button type="submit" id="submit_btn" class="ps-btn ps-btn-primary" style="display:none;">
          Wyślij bez opłaty (z kodem)
        </button>

        <button type="submit" class="ps-btn ps-btn-ghost" id="ps-go-to-fee">
          Przejdź do opłaty 10 zł
        </button>

        <span id="loading_text" class="ps-muted" style="display:none; font-weight:800;">Wysyłam...</span>
      </div>
    </form>
  </main>
</div> 
<script>
(function() {
  var form        = document.querySelector('.portalsluchu-sell-form');
  if (!form) return;

  var codeInput   = document.getElementById('listing_code');
  var codeStatus  = document.getElementById('listing_code_status');
  var submitBtn   = document.getElementById('submit_btn');
  var payBtn      = document.getElementById('ps-go-to-fee');
  var modeField   = document.getElementById('submission_mode');
  var loadingText = document.getElementById('loading_text');
  var alertBox    = document.getElementById('ps-form-alert');
  var file1       = document.getElementById('file_1');

  function normalize(s){ return (s || '').toString().trim().toLowerCase(); }

  function showAlert(msg){
    if (!alertBox) return;
    alertBox.textContent = msg;
    alertBox.style.display = 'block';
    alertBox.scrollIntoView({behavior:'smooth', block:'start'});
  }

  function clearAlert(){
    if (!alertBox) return;
    alertBox.textContent = '';
    alertBox.style.display = 'none';
  }

  function file1Ok(){
    return !!(file1 && file1.files && file1.files.length > 0);
  }

  function validateAll(){
    clearAlert();

    if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
      showAlert('Uzupełnij proszę wymagane pola oznaczone gwiazdką (*), aby kontynuować.');
      return false;
    }

    if (!file1Ok()) {
      showAlert('Dodaj proszę przynajmniej 1 zdjęcie aparatu (pole „Zdjęcie aparatu *”).');
      if (file1) file1.focus();
      return false;
    }

    return true;
  }

  function refreshButtons() {
    var validCode = codeInput ? normalize(codeInput.getAttribute('data-valid-code')) : '';
    var entered  = codeInput ? normalize(codeInput.value) : '';
    var ok = (entered && validCode && entered === validCode);

    if (codeStatus) {
      if (ok) {
        codeStatus.textContent = 'Dziękujemy, kod przyjęty.';
        codeStatus.style.color = '#0f766e';
      } else if (entered) {
        codeStatus.textContent = 'Kod nieprawidłowy.';
        codeStatus.style.color = '#b91c1c';
      } else {
        codeStatus.textContent = '';
      }
    }

    if (submitBtn) submitBtn.style.display = ok ? 'inline-flex' : 'none';
    if (payBtn) payBtn.style.display = ok ? 'none' : 'inline-flex';
    if (modeField) modeField.value = ok ? 'code' : 'pay';
  }

  function bindFileRow(fileId){
    var input = document.getElementById(fileId);
    if (!input) return;

    var btn = form.querySelector('.ps-file-btn[data-file-for="' + fileId + '"]');
    var idx = fileId.split('_')[1];
    var name = document.getElementById('ps_file_name_' + idx);

    if (btn) {
      btn.addEventListener('click', function(){
        input.click();
      });
    }

    input.addEventListener('change', function(){
      if (name) {
        name.textContent = (input.files && input.files.length) ? input.files[0].name : 'Nie wybrano pliku';
      }
      clearAlert();
    });
  }

  for (var i=1;i<=7;i++) bindFileRow('file_' + i);

  if (codeInput) codeInput.addEventListener('input', function(){ clearAlert(); refreshButtons(); });

  // Block submit if required not filled. Show loading only when OK.
  form.addEventListener('submit', function(e) {
    if (!validateAll()) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }

    if (loadingText) loadingText.style.display = 'inline';

    // disable only code-submit to avoid double
    if (submitBtn && modeField && modeField.value === 'code') submitBtn.disabled = true;
  });

  refreshButtons();
})();
</script>
<?php endif; ?>