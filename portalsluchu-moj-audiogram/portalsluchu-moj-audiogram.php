<?php
/**
 * Plugin Name: portalsluchu – Mój audiogram (formularz + konto klienta)
 * Description: portalsluchu-moj-audiogram / Formularz „Mój audiogram” dla zalogowanego użytkownika + opcja płatnego przepisania z obrazka (100 zł doliczane przy zamówieniu), osobna zakładka w „Moje konto” oraz edycja audiogramu w profilu użytkownika w panelu.
 * Author: portalsluchu.pl
 * Version: 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** 
 * Cena usługi przepisania audiogramu z obrazka.
 */
function portalsluchu_moj_audiogram_service_price() {
    return 100.0;
}

/**
 * Lista częstotliwości używana wszędzie (frontend + admin).
 */
function portalsluchu_moj_audiogram_get_frequencies() {
    return array( 125, 250, 500, 750, 1000, 1500, 2000, 3000, 4000, 6000, 8000 );
}

/**
 * Rejestracja endpointu "moj-audiogram" dla konta WooCommerce.
 */
function portalsluchu_moj_audiogram_add_endpoint() {
    add_rewrite_endpoint( 'moj-audiogram', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'portalsluchu_moj_audiogram_add_endpoint' );

/**
 * Flush rewrite przy aktywacji wtyczki.
 */
function portalsluchu_moj_audiogram_activate() {
    portalsluchu_moj_audiogram_add_endpoint();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'portalsluchu_moj_audiogram_activate' );

/**
 * Dodanie pozycji "Mój audiogram" do menu "Moje konto".
 */
function portalsluchu_moj_audiogram_account_menu_items( $items ) {
    // Dodajemy nową pozycję przed "Wyloguj", jeśli istnieje
    $new_items = array();
    $inserted  = false;

    foreach ( $items as $key => $label ) {
        if ( 'customer-logout' === $key && ! $inserted ) {
            $new_items['moj-audiogram'] = __( 'Mój audiogram', 'portalsluchu' );
            $inserted                   = true;
        }

        $new_items[ $key ] = $label;
    }

    if ( ! $inserted ) {
        $new_items['moj-audiogram'] = __( 'Mój audiogram', 'portalsluchu' );
    }

    return $new_items;
}
add_filter( 'woocommerce_account_menu_items', 'portalsluchu_moj_audiogram_account_menu_items' );

/**
 * Treść zakładki "Mój audiogram" w koncie WooCommerce.
 */
function portalsluchu_moj_audiogram_endpoint_content() {
    echo portalsluchu_moj_audiogram_render_form();
}
add_action( 'woocommerce_account_moj-audiogram_endpoint', 'portalsluchu_moj_audiogram_endpoint_content' );

/**
 * Shortcode: [portalsluchu_moj_audiogram]
 * – w razie potrzeby można też użyć formularza na osobnej stronie.
 */
function portalsluchu_moj_audiogram_shortcode() {
    return portalsluchu_moj_audiogram_render_form();
}
add_shortcode( 'portalsluchu_moj_audiogram', 'portalsluchu_moj_audiogram_shortcode' );

/**
 * Główna funkcja renderująca formularz + obsługująca zapis (frontend – Moje konto).
 */
function portalsluchu_moj_audiogram_render_form() {
    if ( ! is_user_logged_in() ) {
        return '<p>Musisz być zalogowany, aby skorzystać z tej funkcji.</p>';
    }

    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID;

    $success_msg = '';
    $error_msg   = '';

    $frequencies = portalsluchu_moj_audiogram_get_frequencies();

// Odczyt aktualnych danych audiogramu użytkownika
$audiogram_prawe = get_user_meta( $user_id, 'serseo_user_audiogram_prawe', true );
if ( ! is_array( $audiogram_prawe ) ) {
    $audiogram_prawe = array();
}

$audiogram_lewe = get_user_meta( $user_id, 'serseo_user_audiogram_lewe', true );
if ( ! is_array( $audiogram_lewe ) ) {
    $audiogram_lewe = array();
}

    // Flaga usługi oraz załączony obrazek
    $service_requested = get_user_meta( $user_id, 'serseo_user_audiogram_service_requested', true );
    $image_id          = get_user_meta( $user_id, 'serseo_user_audiogram_image_id', true );
    $image_url         = $image_id ? wp_get_attachment_url( $image_id ) : '';

    // Obsługa wysyłki formularza
    if ( $_SERVER['REQUEST_METHOD'] === 'POST'
         && isset( $_POST['portalsluchu_moj_audiogram_nonce'] )
         && wp_verify_nonce( $_POST['portalsluchu_moj_audiogram_nonce'], 'portalsluchu_moj_audiogram_save' ) ) {

// Zapis wartości z formularza
$new_audiogram_prawe = array();
$new_audiogram_lewe  = array();

foreach ( $frequencies as $hz ) {
    $hz_key = 'hz_' . $hz;

    $prawe_od = isset( $_POST[ $hz_key . '_prawe_od' ] ) ? trim( sanitize_text_field( $_POST[ $hz_key . '_prawe_od' ] ) ) : '';
    $prawe_do = isset( $_POST[ $hz_key . '_prawe_do' ] ) ? trim( sanitize_text_field( $_POST[ $hz_key . '_prawe_do' ] ) ) : '';

    $lewe_od  = isset( $_POST[ $hz_key . '_lewe_od' ] ) ? trim( sanitize_text_field( $_POST[ $hz_key . '_lewe_od' ] ) ) : '';
    $lewe_do  = isset( $_POST[ $hz_key . '_lewe_do' ] ) ? trim( sanitize_text_field( $_POST[ $hz_key . '_lewe_do' ] ) ) : '';

    if ( $prawe_od !== '' || $prawe_do !== '' ) {
        $new_audiogram_prawe[ $hz ] = array(
            'od' => $prawe_od,
            'do' => $prawe_do,
        );
    }

    if ( $lewe_od !== '' || $lewe_do !== '' ) {
        $new_audiogram_lewe[ $hz ] = array(
            'od' => $lewe_od,
            'do' => $lewe_do,
        );
    }
}

update_user_meta( $user_id, 'serseo_user_audiogram_prawe', $new_audiogram_prawe );
update_user_meta( $user_id, 'serseo_user_audiogram_lewe',  $new_audiogram_lewe );

$audiogram_prawe = $new_audiogram_prawe;
$audiogram_lewe  = $new_audiogram_lewe;

        // Obsługa checkboxa usługi
        $service_flag = isset( $_POST['portalsluchu_audiogram_service'] ) ? '1' : '0';
        update_user_meta( $user_id, 'serseo_user_audiogram_service_requested', $service_flag );
        $service_requested = $service_flag;

        // Obsługa uploadu obrazka (jeśli jest)
        if ( ! empty( $_FILES['portalsluchu_audiogram_image']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $file_id = media_handle_upload( 'portalsluchu_audiogram_image', 0 );

            if ( is_wp_error( $file_id ) ) {
                $error_msg = 'Nie udało się zapisać załączonego pliku audiogramu. Spróbuj ponownie lub skontaktuj się z administratorem.';
            } else {
                update_user_meta( $user_id, 'serseo_user_audiogram_image_id', $file_id );
                $image_id  = $file_id;
                $image_url = wp_get_attachment_url( $file_id );
            }
        }

        // Jeśli zaznaczono usługę i jest obrazek – wyślij maila do admina
        if ( ! $error_msg && $service_flag === '1' && $image_id ) {
            $admin_email = 'admin@serseo.pl';
            $subject     = 'Nowa prośba o przepisanie audiogramu z obrazka – portalsluchu.pl';

            $edit_user_link = admin_url( 'user-edit.php?user_id=' . $user_id );

            $message  = "Nowa prośba o przepisanie audiogramu z obrazka.\n\n";
            $message .= "Użytkownik:\n";
            $message .= "ID: " . $user_id . "\n";
            $message .= "Imię i nazwisko: " . $current_user->display_name . "\n";
            $message .= "Email: " . $current_user->user_email . "\n\n";
            $message .= "Link do edycji profilu użytkownika w panelu:\n" . $edit_user_link . "\n\n";

            if ( $image_url ) {
                $message .= "Załączony audiogram (obrazek):\n" . $image_url . "\n\n";
            }

            $message .= "Przy najbliższym zamówieniu tego użytkownika zostanie doliczona opłata ";
            $message .= portalsluchu_moj_audiogram_service_price() . " zł za ręczne przepisanie audiogramu.\n";

            $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

            wp_mail( $admin_email, $subject, $message, $headers );
        }

        if ( ! $error_msg ) {
            $success_msg = 'Dane Twojego audiogramu zostały zapisane.';
            if ( $service_flag === '1' ) {
                $success_msg .= ' Przy najbliższym zamówieniu zostanie doliczona opłata za przepisanie audiogramu z obrazka.';
            }
        }
    }

    ob_start();

    if ( $success_msg ) : ?>
      <div class="portalsluchu-success" style="padding:12px 16px; margin-bottom:16px; background:#e6ffed; border-left:4px solid #00a32a;">
        <?php echo esc_html( $success_msg ); ?>
      </div>
    <?php endif; ?>

    <?php if ( $error_msg ) : ?>
      <div class="portalsluchu-error" style="padding:12px 16px; margin-bottom:16px; background:#ffe6e6; border-left:4px solid #cc0000;">
        <?php echo esc_html( $error_msg ); ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="portalsluchu-moj-audiogram-form">
      <?php wp_nonce_field( 'portalsluchu_moj_audiogram_save', 'portalsluchu_moj_audiogram_nonce' ); ?>

      <h3>Mój audiogram</h3>
      <p>Wpisz zakresy słyszenia (w dB) dla poszczególnych częstotliwości. Jeśli nie znasz dokładnych wartości, możesz pominąć wybrane pola.</p>

     <!-- PRAWE UCHO -->
<h4 style="margin-top:0; padding:10px 12px; background:#ffe6e6; border-left:6px solid #cc0000;">
  PRAWE UCHO
</h4>

<table class="widefat striped" style="max-width:600px; border:1px solid #ffb3b3;">
  <thead>
    <tr>
      <th>Częstotliwość (Hz)</th>
      <th>Od (dB)</th>
      <th>Do (dB)</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ( $frequencies as $hz ) :
      $row    = isset( $audiogram_prawe[ $hz ] ) ? $audiogram_prawe[ $hz ] : array();
      $val_od = isset( $row['od'] ) ? $row['od'] : '';
      $val_do = isset( $row['do'] ) ? $row['do'] : '';
    ?>
      <tr>
        <td><?php echo esc_html( $hz ); ?></td>
        <td>
          <input
            type="number"
            step="1"
            name="<?php echo esc_attr( 'hz_' . $hz . '_prawe_od' ); ?>"
            value="<?php echo esc_attr( $val_od ); ?>"
            style="width:100%;"
          >
        </td>
        <td>
          <input
            type="number"
            step="1"
            name="<?php echo esc_attr( 'hz_' . $hz . '_prawe_do' ); ?>"
            value="<?php echo esc_attr( $val_do ); ?>"
            style="width:100%;"
          >
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<hr style="margin:20px 0;">

<!-- LEWE UCHO -->
<h4 style="margin-top:0; padding:10px 12px; background:#e6f0ff; border-left:6px solid #0066cc;">
  LEWE UCHO
</h4>

<table class="widefat striped" style="max-width:600px; border:1px solid #b3d1ff;">
  <thead>
    <tr>
      <th>Częstotliwość (Hz)</th>
      <th>Od (dB)</th>
      <th>Do (dB)</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ( $frequencies as $hz ) :
      $row    = isset( $audiogram_lewe[ $hz ] ) ? $audiogram_lewe[ $hz ] : array();
      $val_od = isset( $row['od'] ) ? $row['od'] : '';
      $val_do = isset( $row['do'] ) ? $row['do'] : '';
    ?>
      <tr>
        <td><?php echo esc_html( $hz ); ?></td>
        <td>
          <input
            type="number"
            step="1"
            name="<?php echo esc_attr( 'hz_' . $hz . '_lewe_od' ); ?>"
            value="<?php echo esc_attr( $val_od ); ?>"
            style="width:100%;"
          >
        </td>
        <td>
          <input
            type="number"
            step="1"
            name="<?php echo esc_attr( 'hz_' . $hz . '_lewe_do' ); ?>"
            value="<?php echo esc_attr( $val_do ); ?>"
            style="width:100%;"
          >
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

      <hr style="margin:20px 0;">

      <h3>Nie chcesz samodzielnie wpisywać wartości?</h3>
      <p>
        Możesz załączyć czytelne zdjęcie / skan swojego audiogramu.<br>
        Jeśli zaznaczysz poniższą opcję, przy Twoim najbliższym zamówieniu w sklepie portalsluchu.pl
        zostanie <strong>automatycznie doliczona opłata
        <?php
        if ( function_exists( 'wc_price') ) {
            echo wc_price( portalsluchu_moj_audiogram_service_price() );
        } else {
            echo '100 zł';
        }
        ?></strong>
        za ręczne przepisanie danych przez specjalistę.
      </p>

      <p>
        <label for="portalsluchu_audiogram_image"><strong>Załącz zdjęcie audiogramu (opcjonalnie):</strong></label><br>
        <input type="file" name="portalsluchu_audiogram_image" id="portalsluchu_audiogram_image" accept="image/*">
      </p>

      <?php if ( $image_url ) : ?>
        <p>Aktualnie zapisany plik audiogramu:</p>
        <p><a href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer">Zobacz załączony audiogram</a></p>
      <?php endif; ?>

      <p>
        <label>
          <input type="checkbox" name="portalsluchu_audiogram_service" value="1" <?php checked( $service_requested, '1' ); ?>>
          Chcę, aby specjalista przepisał dane z załączonego obrazka –
          <strong>
          <?php
          if ( function_exists( 'wc_price') ) {
              echo wc_price( portalsluchu_moj_audiogram_service_price() );
          } else {
              echo '100 zł';
          }
          ?>
          </strong>
          zostanie doliczony przy zamówieniu.
        </label>
      </p>

      <p>
        <button type="submit" class="button button-primary">Zapisz mój audiogram</button>
      </p>
    </form>

    <?php
    return ob_get_clean();
}

/**
 * Dodaje opłatę 100 zł za usługę przepisania audiogramu, jeśli użytkownik ją zaznaczył
 * i ma załączony obrazek.
 */
function portalsluchu_moj_audiogram_add_fee( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id = get_current_user_id();

    $service_requested = get_user_meta( $user_id, 'serseo_user_audiogram_service_requested', true );
    $image_id          = get_user_meta( $user_id, 'serseo_user_audiogram_image_id', true );

    if ( $service_requested !== '1' || ! $image_id ) {
        return;
    }

    $price = portalsluchu_moj_audiogram_service_price();

    if ( $price <= 0 ) {
        return;
    }

    $label = __( 'Przepisanie audiogramu z obrazka', 'portalsluchu' );

    $cart->add_fee( $label, $price );

    if ( function_exists( 'WC' ) && WC()->session ) {
        WC()->session->set( 'portalsluchu_audiogram_service_fee_applied', '1' );
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'portalsluchu_moj_audiogram_add_fee', 25, 1 );

/**
 * Zapisuje informację o usłudze do meta zamówienia
 * i po naliczeniu opłaty automatycznie wyłącza flagę u użytkownika,
 * żeby nie naliczać ponownie w kolejnych zamówieniach.
 */
function portalsluchu_moj_audiogram_save_order_meta( $order, $data ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $flag = WC()->session->get( 'portalsluchu_audiogram_service_fee_applied' );
    if ( $flag !== '1' ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id  = get_current_user_id();
    $image_id = get_user_meta( $user_id, 'serseo_user_audiogram_image_id', true );

    $order->update_meta_data( '_portalsluchu_audiogram_service', '1' );
    $order->update_meta_data( '_portalsluchu_audiogram_service_price', portalsluchu_moj_audiogram_service_price() );
    if ( $image_id ) {
        $order->update_meta_data( '_portalsluchu_audiogram_image_id', intval( $image_id ) );
    }

    // Po naliczeniu opłaty – wyłącz usługę, aby nie powtarzać w kolejnych zamówieniach.
    update_user_meta( $user_id, 'serseo_user_audiogram_service_requested', '0' );

    WC()->session->__unset( 'portalsluchu_audiogram_service_fee_applied' );
}
add_action( 'woocommerce_checkout_create_order', 'portalsluchu_moj_audiogram_save_order_meta', 25, 2 );

/* ============================================================
 *  CZĘŚĆ ADMIN – edycja audiogramu w profilu użytkownika
 * ============================================================ */

/**
 * Wyświetlenie sekcji "Mój audiogram" w profilu użytkownika (wp-admin → Użytkownicy → Edytuj).
 */
function portalsluchu_moj_audiogram_show_profile_fields( $user ) {
    if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $frequencies = portalsluchu_moj_audiogram_get_frequencies();

$audiogram_prawe = get_user_meta( $user->ID, 'serseo_user_audiogram_prawe', true );
if ( ! is_array( $audiogram_prawe ) ) {
    $audiogram_prawe = array();
}

$audiogram_lewe = get_user_meta( $user->ID, 'serseo_user_audiogram_lewe', true );
if ( ! is_array( $audiogram_lewe ) ) {
    $audiogram_lewe = array();
}

    $service_requested = get_user_meta( $user->ID, 'serseo_user_audiogram_service_requested', true );
    $image_id          = get_user_meta( $user->ID, 'serseo_user_audiogram_image_id', true );
    $image_url         = $image_id ? wp_get_attachment_url( $image_id ) : '';
    ?>
    <h2>Mój audiogram (portalsluchu)</h2>
    <p>Te dane są wykorzystywane przy filtrze „Pokaż tylko aparaty zgodne z moim audiogramem”.</p>

    <table class="form-table" role="presentation">
        <tr>
            <th><label>Audiogram – wartości w dB</label></th>
            <td>
<!-- PRAWE UCHO (ADMIN) -->
<h4 style="margin-top:0; padding:10px 12px; background:#ffe6e6; border-left:6px solid #cc0000;">
  PRAWE UCHO
</h4>

<table class="widefat striped" style="max-width:600px; border:1px solid #ffb3b3;">
  <thead>
    <tr>
      <th>Częstotliwość (Hz)</th>
      <th>Od (dB)</th>
      <th>Do (dB)</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ( $frequencies as $hz ) :
      $row    = isset( $audiogram_prawe[ $hz ] ) ? $audiogram_prawe[ $hz ] : array();
      $val_od = isset( $row['od'] ) ? $row['od'] : '';
      $val_do = isset( $row['do'] ) ? $row['do'] : '';
    ?>
      <tr>
        <td><?php echo esc_html( $hz ); ?></td>
        <td>
          <input
            type="number"
            step="1"
            name="<?php echo esc_attr( 'portalsluchu_admin_hz_' . $hz . '_prawe_od' ); ?>"
            value="<?php echo esc_attr( $val_od ); ?>"
            style="width:100%;"
          >
        </td>
        <td>
          <input
            type="number"
            step="1"
            name="<?php echo esc_attr( 'portalsluchu_admin_hz_' . $hz . '_prawe_do' ); ?>"
            value="<?php echo esc_attr( $val_do ); ?>"
            style="width:100%;"
          >
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<hr style="margin:20px 0;">

<!-- LEWE UCHO (ADMIN) -->
<h4 style="margin-top:0; padding:10px 12px; background:#e6f0ff; border-left:6px solid #0066cc;">
  LEWE UCHO
</h4>

<table class="widefat striped" style="max-width:600px; border:1px solid #b3d1ff;">
  <thead>
    <tr>
      <th>Częstotliwość (Hz)</th>
      <th>Od (dB)</th>
      <th>Do (dB)</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ( $frequencies as $hz ) :
      $row    = isset( $audiogram_lewe[ $hz ] ) ? $audiogram_lewe[ $hz ] : array();
      $val_od = isset( $row['od'] ) ? $row['od'] : '';
      $val_do = isset( $row['do'] ) ? $row['do'] : '';
    ?>
      <tr>
        <td><?php echo esc_html( $hz ); ?></td>
        <td>
          <input
            type="number"
            step="1"
            name="<?php echo esc_attr( 'portalsluchu_admin_hz_' . $hz . '_lewe_od' ); ?>"
            value="<?php echo esc_attr( $val_od ); ?>"
            style="width:100%;"
          >
        </td>
        <td>
          <input
            type="number"
            step="1"
            name="<?php echo esc_attr( 'portalsluchu_admin_hz_' . $hz . '_lewe_do' ); ?>"
            value="<?php echo esc_attr( $val_do ); ?>"
            style="width:100%;"
          >
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
                <p class="description">
                    Zmiana wartości tutaj zapisze się jako „Mój audiogram” użytkownika i będzie widoczna również w panelu klienta.
                </p>
            </td>
        </tr>

        <tr>
            <th><label>Usługa przepisania z obrazka</label></th>
            <td>
                <label>
                    <input type="checkbox" name="portalsluchu_admin_audiogram_service" value="1" <?php checked( $service_requested, '1' ); ?>>
                    Zaznaczono prośbę o przepisanie audiogramu (naliczana opłata <?php echo esc_html( portalsluchu_moj_audiogram_service_price() ); ?> zł przy zamówieniu).
                </label>
                <?php if ( $image_url ) : ?>
                    <p class="description">
                        Załączony audiogram: <a href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $image_url ); ?></a>
                    </p>
                <?php else : ?>
                    <p class="description">Brak zapisanego pliku audiogramu dla tego użytkownika.</p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'portalsluchu_moj_audiogram_show_profile_fields' );
add_action( 'edit_user_profile', 'portalsluchu_moj_audiogram_show_profile_fields' );

/**
 * Zapis danych audiogramu z profilu użytkownika (admin).
 */
function portalsluchu_moj_audiogram_save_profile_fields( $user_id ) {
    if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
        return;
    }

if (
    ! isset( $_POST['portalsluchu_admin_hz_125_prawe_od'] ) &&
    ! isset( $_POST['portalsluchu_admin_hz_125_prawe_do'] ) &&
    ! isset( $_POST['portalsluchu_admin_hz_125_lewe_od'] ) &&
    ! isset( $_POST['portalsluchu_admin_hz_125_lewe_do'] )
) {
    // Zakładamy, że jeśli nie ma nawet pierwszej częstotliwości w POST, to formularz nie był użyty.
    return;
}
$frequencies         = portalsluchu_moj_audiogram_get_frequencies();
$new_audiogram_prawe  = array();
$new_audiogram_lewe   = array();

foreach ( $frequencies as $hz ) {
    $prawe_od_key = 'portalsluchu_admin_hz_' . $hz . '_prawe_od';
    $prawe_do_key = 'portalsluchu_admin_hz_' . $hz . '_prawe_do';

    $lewe_od_key  = 'portalsluchu_admin_hz_' . $hz . '_lewe_od';
    $lewe_do_key  = 'portalsluchu_admin_hz_' . $hz . '_lewe_do';

    $prawe_od = isset( $_POST[ $prawe_od_key ] ) ? trim( sanitize_text_field( $_POST[ $prawe_od_key ] ) ) : '';
    $prawe_do = isset( $_POST[ $prawe_do_key ] ) ? trim( sanitize_text_field( $_POST[ $prawe_do_key ] ) ) : '';

    $lewe_od  = isset( $_POST[ $lewe_od_key ] ) ? trim( sanitize_text_field( $_POST[ $lewe_od_key ] ) ) : '';
    $lewe_do  = isset( $_POST[ $lewe_do_key ] ) ? trim( sanitize_text_field( $_POST[ $lewe_do_key ] ) ) : '';

    if ( $prawe_od !== '' || $prawe_do !== '' ) {
        $new_audiogram_prawe[ $hz ] = array(
            'od' => $prawe_od,
            'do' => $prawe_do,
        );
    }

    if ( $lewe_od !== '' || $lewe_do !== '' ) {
        $new_audiogram_lewe[ $hz ] = array(
            'od' => $lewe_od,
            'do' => $lewe_do,
        );
    }
}

update_user_meta( $user_id, 'serseo_user_audiogram_prawe', $new_audiogram_prawe );
update_user_meta( $user_id, 'serseo_user_audiogram_lewe',  $new_audiogram_lewe );


    $service_flag = isset( $_POST['portalsluchu_admin_audiogram_service'] ) ? '1' : '0';
    update_user_meta( $user_id, 'serseo_user_audiogram_service_requested', $service_flag );
}
add_action( 'personal_options_update', 'portalsluchu_moj_audiogram_save_profile_fields' );
add_action( 'edit_user_profile_update', 'portalsluchu_moj_audiogram_save_profile_fields' );


/**
 * Komunikat na kokpicie "Moje konto" – przypomnienie o audiogramie.
 */
function portalsluchu_myaccount_audiogram_notice() {
    // Tylko dla zalogowanych
    if ( ! is_user_logged_in() ) {
        return;
    }

    // URL do zakładki "Mój audiogram"
    $myaccount_url  = wc_get_page_permalink( 'myaccount' );
    $audiogram_url  = wc_get_endpoint_url( 'moj-audiogram', '', $myaccount_url );
    ?>
    <div class="woocommerce-info portalsluchu-audiogram-notice">
        Aby móc wybierać aparat spośród <strong>dopasowanych do Twojego słuchu</strong>,
        uzupełnij swój wynik w zakładce
        <a href="<?php echo esc_url( $audiogram_url ); ?>">Mój audiogram</a>.
    </div>
    <?php
}
add_action( 'woocommerce_account_dashboard', 'portalsluchu_myaccount_audiogram_notice', 15 );

/**
 * Walidacja formularza "Mój audiogram":
 * - nie pozwala zaznaczyć opcji 100 zł bez załączonego pliku,
 * - nie pozwala zapisać pustego audiogramu bez zaznaczonej opcji 100 zł.
 */
function portalsluchu_audiogram_frontend_validation() {
    // Działamy tylko na stronach "Moje konto"
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }
    ?>
    <script>
    (function() {
        // Szukamy zawartości "Moje konto"
        var accountContent = document.querySelector('.woocommerce-MyAccount-content');
        if (!accountContent) return;

        // Szukamy formularza, w którym występuje tekst "Zapisz mój audiogram"
        var forms = accountContent.querySelectorAll('form');
        var form  = null;

        Array.prototype.forEach.call(forms, function(f) {
            if (f.textContent && f.textContent.indexOf('Zapisz mój audiogram') !== -1) {
                form = f;
            }
        });

        if (!form) return;

        // W środku tego formularza szukamy pól
        var fileInput    = form.querySelector('input[type="file"]');
        var checkbox     = form.querySelector('input[type="checkbox"]');
        var numberInputs = form.querySelectorAll('input[type="number"]');

        if (!checkbox) return;

        // 1) Nie pozwalaj zaznaczyć opcji 100 zł bez pliku
        checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    alert('Aby skorzystać z tej opcji, najpierw załącz czytelne zdjęcie audiogramu.');
                    checkbox.checked = false;
                }
            }
        });

        // 2) Walidacja przy zapisie formularza
        form.addEventListener('submit', function (e) {
            var hasValues = false;

            Array.prototype.forEach.call(numberInputs, function (input) {
                if (input.value.trim() !== '') {
                    hasValues = true;
                }
            });

            var hasFile      = fileInput && fileInput.files && fileInput.files.length > 0;
            var wantsService = checkbox.checked;

            // Jeśli zaznacza usługę 100 zł, ale nie ma pliku – blokujemy
            if (wantsService && !hasFile) {
                e.preventDefault();
                alert('Załącz proszę zdjęcie/skan audiogramu, aby specjalista mógł przepisać dane.');
                return false;
            }

            // Jeśli audiogram pusty i brak zaznaczonej usługi 100 zł – blokujemy
            if (!hasValues && !wantsService) {
                e.preventDefault();
                alert('Proszę o uzupełnienie audiogramu lub zaznaczenie opcji przepisania danych za 100 zł.');
                return false;
            }

            // Dozwolone:
            // - jest jakakolwiek wartość w polach (niezależnie od checkboxa),
            // - albo checkbox zaznaczony + jest załączony plik.
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'portalsluchu_audiogram_frontend_validation' );
