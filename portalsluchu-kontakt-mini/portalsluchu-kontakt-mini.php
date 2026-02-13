<?php
/**
 * Plugin Name: portalsluchu – Mini kontakt + bramka do formularza sprzedającego
 * Description: Pokazuje mini-formularz uzupełnienia imienia i telefonu (dla zalogowanych). Po uzupełnieniu wyświetla shortcode formularza sprzedającego z nowoczesnym stylem przycisków.
 * Author: portalsluchu.pl
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PORTALSLUCHU_KONTAKT_MINI_SHORTCODE', 'portalsluchu_kontakt_i_sprzedaz' );
define( 'PORTALSLUCHU_SELLER_FORM_SHORTCODE', 'portalsluchu_formularz_sprzedajacy' );

function portalsluchu_kontakt_mini_css() {
	static $done = false;
	if ( $done ) return;
	$done = true;

	$css = '
	/* ===== Mini kontakt card ===== */
	.ps-contact-card{
		--ps-border: rgba(15,23,42,.12);
		--ps-text: #0f172a;
		--ps-muted: rgba(15,23,42,.65);
		--ps-warn-bg1: rgba(255, 251, 235, 1);
		--ps-warn-bg2: rgba(255, 255, 255, 1);
		--ps-warn-border: rgba(245, 158, 11, .45);
		--ps-danger-bg: rgba(254, 242, 242, 1);
		--ps-danger-border: rgba(220, 38, 38, .35);
		--ps-success-bg: rgba(236, 253, 245, 1);
		--ps-success-border: rgba(16, 185, 129, .35);
		--ps-radius: 16px;

		margin: 18px 0;
		padding: 16px 18px;
		border-radius: var(--ps-radius);
		border: 1px solid var(--ps-warn-border);
		background: linear-gradient(180deg, var(--ps-warn-bg1) 0%, var(--ps-warn-bg2) 100%);
		box-shadow: 0 10px 24px rgba(0,0,0,.06);
		font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
		color: var(--ps-text);
	}

	.ps-contact-title{
		font-size: 18px;
		font-weight: 800;
		letter-spacing: .2px;
		margin: 0 0 6px 0;
	}

	.ps-contact-desc{
		margin: 0 0 14px 0;
		color: var(--ps-muted);
		font-size: 14px;
		line-height: 1.45;
	}

	.ps-contact-grid{
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 12px;
		align-items: end;
	}

	@media (max-width: 680px){
		.ps-contact-grid{ grid-template-columns: 1fr; }
	}

	.ps-field label{
		display:block;
		font-weight: 700;
		font-size: 13px;
		margin: 0 0 6px 0;
	}

	.ps-field input{
		width: 100%;
		max-width: 520px;
		padding: 11px 12px;
		border-radius: 12px;
		border: 1px solid rgba(15,23,42,.18);
		background: #fff;
		outline: none;
		font-size: 15px;
		transition: box-shadow .15s ease, border-color .15s ease;
	}

	.ps-field input:focus{
		border-color: rgba(37, 99, 235, .45);
		box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
	}

	.ps-actions{
		display:flex;
		gap: 10px;
		align-items:center;
		flex-wrap: wrap;
		margin-top: 14px;
	}

	.ps-btn{
		appearance: none;
		border: 0;
		border-radius: 999px;
		padding: 12px 18px;
		font-weight: 800;
		letter-spacing: .2px;
		cursor: pointer;
		text-decoration: none;
		display:inline-flex;
		align-items:center;
		justify-content:center;
		gap: 8px;
		min-height: 48px;
	}

	.ps-btn-primary{
		background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
		color: #fff;
		box-shadow: 0 12px 22px rgba(37, 99, 235, .22);
		transition: transform .08s ease, filter .15s ease, box-shadow .15s ease;
	}

	.ps-btn-primary:hover{ filter: brightness(1.03); transform: translateY(-1px); box-shadow: 0 16px 28px rgba(37, 99, 235, .28); }
	.ps-btn-primary:active{ transform: translateY(0); filter: brightness(.98); }

	.ps-btn-primary:focus-visible{
		outline: none;
		box-shadow: 0 0 0 4px rgba(37, 99, 235, .20), 0 16px 28px rgba(37, 99, 235, .28);
	}

	.ps-hint{ font-size: 12px; color: rgba(15,23,42,.55); }

	.ps-alert{
		margin: 12px 0 0 0;
		padding: 10px 12px;
		border-radius: 12px;
		border: 1px solid var(--ps-border);
		font-size: 13px;
		line-height: 1.4;
	}

	.ps-alert-danger{ background: var(--ps-danger-bg); border-color: var(--ps-danger-border); color: #991b1b; }
	.ps-alert-success{ background: var(--ps-success-bg); border-color: var(--ps-success-border); color: #065f46; }

	.ps-required{ color: #b91c1c; font-weight: 900; }

	/* ===== Seller form button styles (scoped) ===== */
	.ps-seller-form-wrap input[type="submit"],
	.ps-seller-form-wrap button,
	.ps-seller-form-wrap .button,
	.ps-seller-form-wrap .wpforms-submit,
	.ps-seller-form-wrap .gform_button,
	.ps-seller-form-wrap .wpcf7-submit{
		appearance: none;
		border: 0 !important;
		border-radius: 999px !important;
		padding: 14px 20px !important;
		min-height: 50px;
		font-size: 16px !important;
		font-weight: 800 !important;
		letter-spacing: .2px;
		cursor: pointer;
		text-decoration: none;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;

		color: #fff !important;
		background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%) !important;
		box-shadow: 0 14px 28px rgba(37, 99, 235, .22) !important;

		transition: transform .08s ease, filter .15s ease, box-shadow .15s ease;
	}

	.ps-seller-form-wrap input[type="submit"]:hover,
	.ps-seller-form-wrap button:hover,
	.ps-seller-form-wrap .button:hover,
	.ps-seller-form-wrap .wpforms-submit:hover,
	.ps-seller-form-wrap .gform_button:hover,
	.ps-seller-form-wrap .wpcf7-submit:hover{
		filter: brightness(1.04);
		box-shadow: 0 18px 34px rgba(37, 99, 235, .28) !important;
		transform: translateY(-1px);
	}

	.ps-seller-form-wrap input[type="submit"]:active,
	.ps-seller-form-wrap button:active,
	.ps-seller-form-wrap .button:active{
		transform: translateY(0);
		filter: brightness(.98);
	}

	.ps-seller-form-wrap input[type="submit"]:focus-visible,
	.ps-seller-form-wrap button:focus-visible,
	.ps-seller-form-wrap .button:focus-visible{
		outline: none !important;
		box-shadow: 0 0 0 4px rgba(37, 99, 235, .20), 0 18px 34px rgba(37, 99, 235, .28) !important;
	}

	@media (max-width: 680px){
		.ps-seller-form-wrap input[type="submit"],
		.ps-seller-form-wrap button,
		.ps-seller-form-wrap .button{
			width: 100% !important;
		}
	}
	';

	echo '<style id="ps-contact-mini-css">' . $css . '</style>';
}
add_action( 'wp_head', 'portalsluchu_kontakt_mini_css', 50 );

add_shortcode( PORTALSLUCHU_KONTAKT_MINI_SHORTCODE, 'portalsluchu_kontakt_i_sprzedaz_shortcode' );

function portalsluchu_kontakt_i_sprzedaz_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$user_id = get_current_user_id();

	$first_name = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
	$phone      = trim( (string) get_user_meta( $user_id, 'billing_phone', true ) );

	$errors  = array();
	$success = false;

	if (
		$_SERVER['REQUEST_METHOD'] === 'POST'
		&& isset( $_POST['ps_contact_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ps_contact_nonce'] ) ), 'ps_save_contact_min' )
	) {
		$new_first = isset( $_POST['ps_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ps_first_name'] ) ) : '';
		$new_phone = isset( $_POST['ps_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ps_phone'] ) ) : '';

		$new_first = trim( $new_first );
		$new_phone = trim( $new_phone );

		if ( $new_first === '' ) {
			$errors[] = 'Podaj proszę imię.';
		}

		$digits = preg_replace( '/\D+/', '', $new_phone );
		if ( $digits === '' || strlen( $digits ) < 7 ) {
			$errors[] = 'Podaj proszę poprawny numer telefonu.';
		}

		if ( empty( $errors ) ) {
			update_user_meta( $user_id, 'first_name', $new_first );
			update_user_meta( $user_id, 'billing_phone', $new_phone );
			update_user_meta( $user_id, 'billing_first_name', $new_first );

			$first_name = $new_first;
			$phone      = $new_phone;
			$success    = true;
		}
	}

	if ( $first_name === '' || $phone === '' ) {
		ob_start();
		?>
		<div class="ps-contact-card">
			<div class="ps-contact-title">Uzupełnij dane kontaktowe</div>
			<p class="ps-contact-desc">
				Brakuje nam danych do kontaktu. Podaj proszę <strong>imię</strong> i <strong>numer telefonu</strong>, aby przejść do formularza wystawienia aparatu.
			</p>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="ps-alert ps-alert-danger">
					<?php foreach ( $errors as $e ) : ?>
						<div><?php echo esc_html( $e ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php elseif ( $success ) : ?>
				<div class="ps-alert ps-alert-success">
					Dane zapisane. Możesz teraz wypełnić formularz poniżej.
				</div>
			<?php endif; ?>

			<form method="post" style="margin-top:14px;">
				<?php wp_nonce_field( 'ps_save_contact_min', 'ps_contact_nonce' ); ?>

				<div class="ps-contact-grid">
					<div class="ps-field">
						<label for="ps_first_name">Imię <span class="ps-required">*</span></label>
						<input type="text" id="ps_first_name" name="ps_first_name" value="<?php echo esc_attr( $first_name ); ?>" autocomplete="given-name" required>
					</div>

					<div class="ps-field">
						<label for="ps_phone">Numer telefonu <span class="ps-required">*</span></label>
						<input type="tel" id="ps_phone" name="ps_phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="np. 500 600 700" autocomplete="tel" inputmode="tel" required>
					</div>
				</div>

				<div class="ps-actions">
					<button type="submit" class="ps-btn ps-btn-primary">Zapisz dane</button>
					<div class="ps-hint">Dane zapisujemy w Twoim profilu, aby usprawnić kontakt.</div>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	// Dane są: pokaż formularz sprzedającego + nasze style w wrapperze
	return '<div class="ps-seller-form-wrap">' . do_shortcode( '[' . PORTALSLUCHU_SELLER_FORM_SHORTCODE . ']' ) . '</div>';
}