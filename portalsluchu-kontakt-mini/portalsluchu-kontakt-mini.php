<?php
/**
 * Plugin Name: portalsluchu – Mini kontakt + bramka do formularza sprzedającego
 * Description: Pokazuje mini-formularz uzupełnienia imienia i telefonu (dla zalogowanych). Po uzupełnieniu wyświetla shortcode formularza sprzedającego z nowoczesnym, responsywnym stylem.
 * Author: portalsluchu.pl
 * Version: 1.3.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PORTALSLUCHU_KONTAKT_MINI_SHORTCODE', 'portalsluchu_kontakt_i_sprzedaz' );
define( 'PORTALSLUCHU_SELLER_FORM_SHORTCODE', 'portalsluchu_formularz_sprzedajacy' );

function portalsluchu_kontakt_mini_css() {
	static $done = false;
	if ( $done ) return;
	$done = true;

	$css = '
	:root{
		--ps-brand: #0ea5a8;
		--ps-brand-dark: #0f766e;
		--ps-text: #0f172a;
		--ps-muted: rgba(15,23,42,.62);
		--ps-border: rgba(15,23,42,.12);
		--ps-radius: 16px;
	}

	.ps-contact-card{
		margin: 18px 0;
		padding: 16px 18px;
		border-radius: var(--ps-radius);
		border: 1px solid rgba(14,165,168,.30);
		background: linear-gradient(180deg, rgba(240,253,250,1) 0%, rgba(255,255,255,1) 100%);
		box-shadow: 0 10px 24px rgba(0,0,0,.06);
		font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
		color: var(--ps-text);
	}
	.ps-contact-title{ font-size: 18px; font-weight: 900; letter-spacing: .2px; margin: 0 0 6px 0; }
	.ps-contact-desc{ margin: 0 0 14px 0; color: var(--ps-muted); font-size: 14px; line-height: 1.45; }

	.ps-contact-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; align-items:end; }
	@media (max-width: 680px){ .ps-contact-grid{ grid-template-columns:1fr; } }

	.ps-field label{ display:block; font-weight:800; font-size:13px; margin:0 0 6px 0; }
	.ps-field input{
		width:100%;
		padding:12px 12px;
		border-radius:12px;
		border:1px solid rgba(15,23,42,.18);
		background:#fff;
		outline:none;
		font-size:15px;
		transition: box-shadow .15s ease, border-color .15s ease;
	}
	.ps-field input:focus{
		border-color: rgba(14,165,168,.55);
		box-shadow: 0 0 0 4px rgba(14,165,168,.14);
	}

	.ps-actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:14px; }

	.ps-btn{
		appearance:none;
		border:0;
		border-radius:999px;
		padding:12px 18px;
		font-weight:900;
		letter-spacing:.2px;
		cursor:pointer;
		display:inline-flex;
		align-items:center;
		justify-content:center;
		min-height:48px;
		text-decoration:none;
	}
	.ps-btn-primary{
		background: linear-gradient(180deg, var(--ps-brand) 0%, var(--ps-brand-dark) 100%);
		color:#fff;
		box-shadow: 0 12px 22px rgba(14,165,168,.22);
		transition: transform .08s ease, filter .15s ease, box-shadow .15s ease;
	}
	.ps-btn-primary:hover{ filter:brightness(1.03); transform:translateY(-1px); box-shadow:0 16px 28px rgba(14,165,168,.28); }
	.ps-btn-primary:focus-visible{ outline:none; box-shadow:0 0 0 4px rgba(14,165,168,.20), 0 16px 28px rgba(14,165,168,.28); }

	.ps-btn-ghost{
		background: rgba(14,165,168,.10);
		color: rgba(15,23,42,.92);
		border: 1px solid rgba(14,165,168,.35);
	}

	.ps-hint{ font-size:12px; color: rgba(15,23,42,.55); }

	.ps-alert{ margin: 12px 0 0 0; padding:10px 12px; border-radius:12px; border:1px solid var(--ps-border); font-size:13px; line-height:1.4; }
	.ps-alert-danger{ background: rgba(254,242,242,1); border-color: rgba(220,38,38,.35); color:#991b1b; }
	.ps-alert-success{ background: rgba(236,253,245,1); border-color: rgba(16,185,129,.35); color:#065f46; }
	.ps-required{ color:#b91c1c; font-weight:900; }

	/* ===== Seller form skin (scoped) ===== */
	.ps-seller-form-wrap{
		font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
		color: var(--ps-text);
	}

	.ps-seller-form-wrap .ps-sell-layout{
		max-width: 980px;
		margin: 0 auto;
		display: grid;
		grid-template-columns: 280px 1fr;
		gap: 18px;
		align-items: start;
	}
	@media (max-width: 900px){
		.ps-seller-form-wrap .ps-sell-layout{ grid-template-columns: 1fr; }
	}

	.ps-seller-form-wrap .ps-sidecard,
	.ps-seller-form-wrap .ps-card{
		border: 1px solid var(--ps-border);
		border-radius: var(--ps-radius);
		background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
		box-shadow: 0 10px 24px rgba(0,0,0,.04);
		padding: 14px 16px;
	}

	.ps-seller-form-wrap .ps-card-title{
		font-weight: 950;
		font-size: 16px;
		letter-spacing: .2px;
		margin: 0 0 10px 0;
	}

	.ps-seller-form-wrap .ps-muted{
		color: var(--ps-muted);
		font-size: 13px;
		line-height: 1.45;
		margin: 0;
	}

	.ps-seller-form-wrap .ps-grid{
		display:grid;
		grid-template-columns: 1fr 1fr;
		gap: 12px;
	}
	@media (max-width: 720px){
		.ps-seller-form-wrap .ps-grid{ grid-template-columns: 1fr; }
	}

	.ps-seller-form-wrap .ps-field{ margin: 0 0 12px 0; }
	.ps-seller-form-wrap .ps-field label{
		display:block;
		font-weight: 850;
		font-size: 13px;
		margin: 0 0 6px 0;
		color: rgba(15,23,42,.88);
	}

	.ps-seller-form-wrap .ps-field input[type="text"],
	.ps-seller-form-wrap .ps-field input[type="number"],
	.ps-seller-form-wrap .ps-field input[type="email"],
	.ps-seller-form-wrap .ps-field input[type="tel"],
	.ps-seller-form-wrap .ps-field select,
	.ps-seller-form-wrap .ps-field textarea{
		width: 100%;
		box-sizing: border-box;
		padding: 12px 12px;
		border-radius: 12px;
		border: 1px solid rgba(15,23,42,.18);
		background: #fff;
		font-size: 15px;
		outline: none;
		transition: box-shadow .15s ease, border-color .15s ease;
	}
	.ps-seller-form-wrap .ps-field textarea{ min-height: 140px; resize: vertical; }

	.ps-seller-form-wrap .ps-field input:focus,
	.ps-seller-form-wrap .ps-field select:focus,
	.ps-seller-form-wrap .ps-field textarea:focus{
		border-color: rgba(14,165,168,.55);
		box-shadow: 0 0 0 4px rgba(14,165,168,.14);
	}

	.ps-seller-form-wrap .ps-files .ps-file-row{
		display:grid;
		grid-template-columns: 180px 1fr;
		gap: 10px;
		align-items:center;
		padding: 10px 0;
		border-bottom: 1px dashed rgba(15,23,42,.12);
	}
	.ps-seller-form-wrap .ps-files .ps-file-row:last-child{ border-bottom:0; }
	@media (max-width: 720px){
		.ps-seller-form-wrap .ps-files .ps-file-row{ grid-template-columns: 1fr; }
	}

	.ps-seller-form-wrap .ps-file-input{
		display:flex;
		align-items:center;
		gap: 10px;
		flex-wrap: wrap;
	}
	.ps-seller-form-wrap .ps-file-native{
		position:absolute;
		left:-9999px;
		width:1px;
		height:1px;
		overflow:hidden;
	}
	.ps-seller-form-wrap .ps-file-name{
		font-size: 13px;
		color: rgba(15,23,42,.62);
		font-weight: 700;
		word-break: break-word;
	}

	.ps-seller-form-wrap .ps-actions{
		display:flex;
		gap: 12px;
		flex-wrap: wrap;
		align-items: center;
		margin-top: 14px;
	}

	.ps-seller-form-wrap .ps-btn{
		appearance:none;
		border:0;
		border-radius: 999px;
		padding: 14px 22px;
		min-height: 52px;
		font-size: 16px;
		font-weight: 950;
		letter-spacing: .25px;
		cursor: pointer;
		display:inline-flex;
		align-items:center;
		justify-content:center;
		gap: 10px;
		text-decoration:none;
	}

	.ps-seller-form-wrap .ps-btn-primary{
		color:#fff;
		background: linear-gradient(180deg, var(--ps-brand) 0%, var(--ps-brand-dark) 100%);
		box-shadow: 0 16px 30px rgba(14,165,168,.22);
		transition: transform .08s ease, filter .15s ease, box-shadow .15s ease;
	}
	.ps-seller-form-wrap .ps-btn-primary:hover{
		filter: brightness(1.04);
		box-shadow: 0 20px 38px rgba(14,165,168,.28);
		transform: translateY(-1px);
	}
	.ps-seller-form-wrap .ps-btn:focus-visible{
		outline:none;
		box-shadow: 0 0 0 4px rgba(14,165,168,.20), 0 16px 30px rgba(14,165,168,.22);
	}
	@media (max-width: 720px){
		.ps-seller-form-wrap .ps-btn{ width: 100%; }
	}

	.ps-seller-form-wrap .portalsluchu-success,
	.ps-seller-form-wrap .portalsluchu-error{
		border-radius: 14px;
		border: 1px solid var(--ps-border);
		box-shadow: 0 10px 24px rgba(0,0,0,.04);
		padding: 12px 16px;
		margin-bottom: 16px;
	}
	';

	echo '<style id="ps-contact-mini-css">' . $css . '</style>';
}
add_action( 'wp_head', 'portalsluchu_kontakt_mini_css', 50 );

add_shortcode( PORTALSLUCHU_KONTAKT_MINI_SHORTCODE, 'portalsluchu_kontakt_i_sprzedaz_shortcode' );

function portalsluchu_kontakt_i_sprzedaz_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) return '';

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

		if ( $new_first === '' ) $errors[] = 'Podaj proszę imię.';

		$digits = preg_replace( '/\D+/', '', $new_phone );
		if ( $digits === '' || strlen( $digits ) < 7 ) $errors[] = 'Podaj proszę poprawny numer telefonu.';

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
		ob_start(); ?>
		<div class="ps-contact-card">
			<div class="ps-contact-title">Uzupełnij dane kontaktowe</div>
			<p class="ps-contact-desc">
				Brakuje nam danych do kontaktu. Podaj proszę <strong>imię</strong> i <strong>numer telefonu</strong>, aby przejść do formularza wystawienia aparatu.
			</p>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="ps-alert ps-alert-danger">
					<?php foreach ( $errors as $e ) : ?><div><?php echo esc_html( $e ); ?></div><?php endforeach; ?>
				</div>
			<?php elseif ( $success ) : ?>
				<div class="ps-alert ps-alert-success">Dane zapisane. Możesz teraz wypełnić formularz poniżej.</div>
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

	return '<div class="ps-seller-form-wrap">' . do_shortcode( '[' . PORTALSLUCHU_SELLER_FORM_SHORTCODE . ']' ) . '</div>';
}