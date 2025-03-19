<?php
/**
 * Plugin Name: Multiple Company Invoice System (Invoice + PackingSlip + CreditNote)
 * Description: WooCommerce çoklu firma ve fatura yönetimi.
 * Version:     1.3.0
 * Author:      Erme Digital - @Baris
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1) Dompdf Dahil
 * composer require dompdf/dompdf
 */
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * 2) Sabitler ve Kurulum
 */
define( 'MMCI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMCI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, 'mmci_activate_plugin' );
function mmci_activate_plugin() {
    mmci_register_company_cpt();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'mmci_deactivate_plugin' );
function mmci_deactivate_plugin() {
    flush_rewrite_rules();
}

/**
 * 3) Firmalar için CPT
 */
add_action( 'init', 'mmci_register_company_cpt' );
function mmci_register_company_cpt() {
    $args = array(
        'labels' => array(
            'name'          => __( 'Firmalar', 'mmci' ),
            'singular_name' => __( 'Firma', 'mmci' ),
        ),
        'public'      => false,
        'show_ui'     => false,
        'supports'    => array( 'title' ),
    );
    register_post_type( 'mmci_company', $args );
}

/**
 * 4) Admin Menü: Çoklu Fatura
 */
add_action( 'admin_menu', 'mmci_add_admin_menu' );
function mmci_add_admin_menu() {
    add_menu_page(
        __( 'Çoklu Fatura', 'mmci' ),
        __( 'Çoklu Fatura', 'mmci' ),
        'manage_options',
        'mmci-dashboard',
        'mmci_render_dashboard_page',
        'dashicons-media-spreadsheet',
        56
    );
    add_submenu_page(
        'mmci-dashboard',
        __( 'Firmalar', 'mmci' ),
        __( 'Firmalar', 'mmci' ),
        'manage_options',
        'mmci-companies',
        'mmci_render_companies_page'
    );
    add_submenu_page(
        null,
        __( 'Firma Düzenle', 'mmci' ),
        __( 'Firma Düzenle', 'mmci' ),
        'manage_options',
        'mmci-company-edit',
        'mmci_render_company_edit_page'
    );
}

function mmci_render_dashboard_page() {
    echo '<div class="wrap"><h1>' . esc_html__( 'Çoklu Fatura Sistemi', 'mmci' ) . '</h1></div>';
}

// Firma listeleme
function mmci_render_companies_page() {
    $args = array(
        'post_type'      => 'mmci_company',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    );
    $companies = get_posts( $args );
    ?>
    <div class="wrap">
        <h1><?php _e( 'Firmalar', 'mmci' ); ?></h1>
        <a href="<?php echo admin_url( 'admin.php?page=mmci-company-edit' ); ?>" class="button button-primary">
            <?php _e( 'Yeni Firma Ekle', 'mmci' ); ?>
        </a>
        <table class="widefat fixed striped" style="margin-top:20px;">
            <thead>
                <tr>
                    <th><?php _e( 'Firma Adı', 'mmci' ); ?></th>
                    <th><?php _e( 'İşlemler', 'mmci' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $companies ) : ?>
                <?php foreach ( $companies as $company ) : ?>
                    <tr>
                        <td><?php echo esc_html( $company->post_title ); ?></td>
                        <td>
                            <a href="<?php echo admin_url( 'admin.php?page=mmci-company-edit&company_id=' . $company->ID ); ?>">
                                <?php _e( 'Düzenle', 'mmci' ); ?>
                            </a>
                            |
                            <a href="<?php echo wp_nonce_url(
                                admin_url( 'admin-post.php?action=mmci_delete_company&company_id=' . $company->ID ),
                                'mmci_delete_company_nonce'
                            ); ?>"
                               style="color:red;"
                               onclick="return confirm('<?php _e( 'Silmek istediğinize emin misiniz?', 'mmci' ); ?>');">
                                <?php _e( 'Sil', 'mmci' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="2"><?php _e( 'Henüz firma eklenmedi.', 'mmci' ); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * 5) Firma Ekle/Düzenle
 */
function mmci_render_company_edit_page() {
    $company_id   = isset( $_GET['company_id'] ) ? absint( $_GET['company_id'] ) : 0;
    $company_post = null;
    if ( $company_id ) {
        $company_post = get_post( $company_id );
        if ( ! $company_post || $company_post->post_type !== 'mmci_company' ) {
            wp_die( __( 'Firma bulunamadı.', 'mmci' ) );
        }
    }

    // Mevcut meta
    $company_title        = $company_post ? $company_post->post_title : '';
    $company_address      = $company_post ? get_post_meta( $company_id, '_mmci_company_address', true ) : '';
    $company_phone        = $company_post ? get_post_meta( $company_id, '_mmci_company_phone', true ) : '';
    $company_email        = $company_post ? get_post_meta( $company_id, '_mmci_company_email', true ) : '';
    $company_kvk          = $company_post ? get_post_meta( $company_id, '_mmci_company_kvk', true ) : '';
    $company_btw          = $company_post ? get_post_meta( $company_id, '_mmci_company_btw', true ) : '';
    $company_iban         = $company_post ? get_post_meta( $company_id, '_mmci_company_iban', true ) : '';
    $company_taxno        = $company_post ? get_post_meta( $company_id, '_mmci_company_taxno', true ) : '';
    $company_logo         = $company_post ? get_post_meta( $company_id, '_mmci_company_logo', true ) : '';
    // Eski 'Fatura Numarası' alanı (opsiyonel)
    $company_invoice_no   = $company_post ? get_post_meta( $company_id, '_mmci_company_invoice_no', true ) : '';
    // Yeni eklenenler (prefix ve başlangıç numarası)
    $company_invoice_prefix = $company_post ? get_post_meta( $company_id, '_mmci_company_invoice_prefix', true ) : '';
    $company_invoice_start  = $company_post ? get_post_meta( $company_id, '_mmci_company_invoice_start', true ) : '';

    ?>
    <div class="wrap">
        <h1><?php echo $company_id ? __( 'Firma Düzenle', 'mmci' ) : __( 'Yeni Firma Ekle', 'mmci' ); ?></h1>

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <?php wp_nonce_field( 'mmci_save_company_nonce', 'mmci_save_company_nonce_field' ); ?>
            <input type="hidden" name="action" value="mmci_save_company" />
            <input type="hidden" name="company_id" value="<?php echo esc_attr( $company_id ); ?>" />

            <table class="form-table">
                <tr>
                    <th><label for="mmci_company_name"><?php _e( 'Firma Adı', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_name" name="mmci_company_name" class="regular-text"
                               value="<?php echo esc_attr( $company_title ); ?>" required />
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_address"><?php _e( 'Adres', 'mmci' ); ?></label></th>
                    <td>
                        <textarea id="mmci_company_address" name="mmci_company_address" class="large-text" rows="3"><?php 
                            echo esc_textarea( $company_address ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_phone"><?php _e( 'Telefon', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_phone" name="mmci_company_phone" class="regular-text"
                               value="<?php echo esc_attr( $company_phone ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_email"><?php _e( 'E-posta', 'mmci' ); ?></label></th>
                    <td>
                        <input type="email" id="mmci_company_email" name="mmci_company_email" class="regular-text"
                               value="<?php echo esc_attr( $company_email ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_kvk"><?php _e( 'KVK Numarası', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_kvk" name="mmci_company_kvk" class="regular-text"
                               value="<?php echo esc_attr( $company_kvk ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_btw"><?php _e( 'BTW Numarası', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_btw" name="mmci_company_btw" class="regular-text"
                               value="<?php echo esc_attr( $company_btw ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_iban"><?php _e( 'IBAN', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_iban" name="mmci_company_iban" class="regular-text"
                               value="<?php echo esc_attr( $company_iban ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_taxno"><?php _e( 'Vergi / KVK Numarası (ek)', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_taxno" name="mmci_company_taxno" class="regular-text"
                               value="<?php echo esc_attr( $company_taxno ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_logo"><?php _e( 'Logo URL', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_logo" name="mmci_company_logo" class="regular-text"
                               value="<?php echo esc_attr( $company_logo ); ?>" />
                    </td>
                </tr>
                <!-- Opsiyonel eski fatura numarası alanı -->
                <tr>
                    <th><label for="mmci_company_invoice_no"><?php _e( 'Fatura Numarası (Kullanılmıyorsa boş bırakın)', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_invoice_no" name="mmci_company_invoice_no" class="regular-text"
                               value="<?php echo esc_attr( $company_invoice_no ); ?>" />
                    </td>
                </tr>
                <!-- Yeni alanlar: Prefix ve Başlangıç Numarası -->
                <tr>
                    <th><label for="mmci_company_invoice_prefix"><?php _e( 'Fatura Numarası Prefixi', 'mmci' ); ?></label></th>
                    <td>
                        <input type="text" id="mmci_company_invoice_prefix" name="mmci_company_invoice_prefix" class="regular-text"
                               value="<?php echo esc_attr( $company_invoice_prefix ); ?>" />
                        <p class="description"><?php _e( 'Örnek: INV, FAT, gibi kısaltma.', 'mmci' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mmci_company_invoice_start"><?php _e( 'Fatura Başlangıç Numarası', 'mmci' ); ?></label></th>
                    <td>
                        <input type="number" id="mmci_company_invoice_start" name="mmci_company_invoice_start" class="regular-text"
                               value="<?php echo esc_attr( $company_invoice_start ); ?>" />
                        <p class="description"><?php _e( 'Örnek: 1000 girdiyseniz ilk fatura INV1000, sonra INV1001 vb.', 'mmci' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Firma Kayıt / Sil
 */
add_action( 'admin_post_mmci_save_company', 'mmci_save_company_handler' );
function mmci_save_company_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Yetkiniz yok.', 'mmci' ) );
    }
    check_admin_referer( 'mmci_save_company_nonce', 'mmci_save_company_nonce_field' );

    $company_id = absint( $_POST['company_id'] ?? 0 );
    $name       = sanitize_text_field( $_POST['mmci_company_name'] ?? '' );
    $address    = wp_kses_post( $_POST['mmci_company_address'] ?? '' );
    $phone      = sanitize_text_field( $_POST['mmci_company_phone'] ?? '' );
    $email      = sanitize_email( $_POST['mmci_company_email'] ?? '' );
    $kvk        = sanitize_text_field( $_POST['mmci_company_kvk'] ?? '' );
    $btw        = sanitize_text_field( $_POST['mmci_company_btw'] ?? '' );
    $iban       = sanitize_text_field( $_POST['mmci_company_iban'] ?? '' );
    $taxno      = sanitize_text_field( $_POST['mmci_company_taxno'] ?? '' );
    $logo       = esc_url_raw( $_POST['mmci_company_logo'] ?? '' );
    // Eski fatura no
    $old_invoice_no = sanitize_text_field( $_POST['mmci_company_invoice_no'] ?? '' );
    // Yeni alanlar
    $prefix     = sanitize_text_field( $_POST['mmci_company_invoice_prefix'] ?? '' );
    $start      = absint( $_POST['mmci_company_invoice_start'] ?? 1 );

    if ( $company_id ) {
        wp_update_post( array(
            'ID'         => $company_id,
            'post_type'  => 'mmci_company',
            'post_title' => $name,
        ) );
    } else {
        $company_id = wp_insert_post( array(
            'post_type'   => 'mmci_company',
            'post_title'  => $name,
            'post_status' => 'publish',
        ) );
    }

    update_post_meta( $company_id, '_mmci_company_address', $address );
    update_post_meta( $company_id, '_mmci_company_phone', $phone );
    update_post_meta( $company_id, '_mmci_company_email', $email );
    update_post_meta( $company_id, '_mmci_company_kvk', $kvk );
    update_post_meta( $company_id, '_mmci_company_btw', $btw );
    update_post_meta( $company_id, '_mmci_company_iban', $iban );
    update_post_meta( $company_id, '_mmci_company_taxno', $taxno );
    update_post_meta( $company_id, '_mmci_company_logo', $logo );
    update_post_meta( $company_id, '_mmci_company_invoice_no', $old_invoice_no );
    update_post_meta( $company_id, '_mmci_company_invoice_prefix', $prefix );
    update_post_meta( $company_id, '_mmci_company_invoice_start', $start );

    wp_redirect( admin_url( 'admin.php?page=mmci-companies' ) );
    exit;
}

add_action( 'admin_post_mmci_delete_company', 'mmci_delete_company_handler' );
function mmci_delete_company_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Yetkiniz yok.', 'mmci' ) );
    }
    $company_id = absint( $_GET['company_id'] ?? 0 );
    check_admin_referer( 'mmci_delete_company_nonce' );

    if ( $company_id ) {
        wp_delete_post( $company_id, true );
    }
    wp_redirect( admin_url( 'admin.php?page=mmci-companies' ) );
    exit;
}

/**
 * 6) WooCommerce Sipariş Ekranına "Fatura Kesilecek Firma" Dropdown
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'mmci_add_firma_dropdown_to_order' );
function mmci_add_firma_dropdown_to_order( $order ) {
    $args = array(
        'post_type'      => 'mmci_company',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    );
    $companies = get_posts( $args );
    $selected_company_id = get_post_meta( $order->get_id(), '_mmci_company_id', true );
    ?>
    <div class="form-field form-field-wide">
        <label for="mmci_company_select">
            <?php _e( 'Fatura Kesilecek Firma:', 'mmci' ); ?>
        </label>
        <select id="mmci_company_select" name="mmci_company_select">
            <option value=""><?php _e( 'Seçilmedi', 'mmci' ); ?></option>
            <?php foreach ( $companies as $company ) : ?>
                <option value="<?php echo esc_attr( $company->ID ); ?>"
                    <?php selected( $company->ID, $selected_company_id ); ?>>
                    <?php echo esc_html( $company->post_title ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

add_action( 'woocommerce_process_shop_order_meta', 'mmci_save_firma_dropdown_to_order' );
function mmci_save_firma_dropdown_to_order( $order_id ) {
    if ( isset( $_POST['mmci_company_select'] ) ) {
        update_post_meta( $order_id, '_mmci_company_id', sanitize_text_field( $_POST['mmci_company_select'] ) );
    }
}

/**
 * 7) PDF Butonları - Sipariş Ekranı
 */
add_action( 'add_meta_boxes_shop_order', 'mmci_add_invoice_button_metabox' );
function mmci_add_invoice_button_metabox() {
    add_meta_box(
        'mmci_invoice_box',
        __( 'Fatura & Belgeler (PDF)', 'mmci' ),
        'mmci_invoice_metabox_content',
        'shop_order',
        'side',
        'default'
    );
}
function mmci_invoice_metabox_content( $post ) {
    $order_id = $post->ID;
    $invoice_url = add_query_arg( array(
        'mmci_action' => 'invoice',
        'order_id'    => $order_id,
    ), admin_url( 'admin.php' ) );
    $packing_url = add_query_arg( array(
        'mmci_action' => 'packing_slip',
        'order_id'    => $order_id,
    ), admin_url( 'admin.php' ) );
    $credit_url = add_query_arg( array(
        'mmci_action' => 'credit_note',
        'order_id'    => $order_id,
    ), admin_url( 'admin.php' ) );

    echo '<p><a href="' . esc_url( $invoice_url ) . '" class="button" target="_blank">'
         . __( 'Faturayı Görüntüle (PDF)', 'mmci' ) . '</a></p>';
    echo '<p><a href="' . esc_url( $packing_url ) . '" class="button" target="_blank">'
         . __( 'Pakbon (PDF)', 'mmci' ) . '</a></p>';
    echo '<p><a href="' . esc_url( $credit_url ) . '" class="button" target="_blank">'
         . __( 'İade Faturası (PDF)', 'mmci' ) . '</a></p>';
}

add_action( 'woocommerce_admin_order_data_after_order_details', 'mmci_add_invoice_buttons_in_order_details' );
function mmci_add_invoice_buttons_in_order_details( $order ) {
    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        return;
    }
    $order_id = $order->get_id();
    $invoice_url = add_query_arg( array(
        'mmci_action' => 'invoice',
        'order_id'    => $order_id,
    ), admin_url( 'admin.php' ) );
    $packing_url = add_query_arg( array(
        'mmci_action' => 'packing_slip',
        'order_id'    => $order_id,
    ), admin_url( 'admin.php' ) );
    $credit_url = add_query_arg( array(
        'mmci_action' => 'credit_note',
        'order_id'    => $order_id,
    ), admin_url( 'admin.php' ) );

    echo '<div style="margin:20px 0;">';
    echo '<h3>' . __( 'Fatura & Belgeler (PDF)', 'mmci' ) . '</h3>';
    echo '<a href="' . esc_url( $invoice_url ) . '" class="button" target="_blank">'
         . __( 'Fatura', 'mmci' ) . '</a> ';
    echo '<a href="' . esc_url( $packing_url ) . '" class="button" target="_blank">'
         . __( 'Pakbon', 'mmci' ) . '</a> ';
    echo '<a href="' . esc_url( $credit_url ) . '" class="button" target="_blank">'
         . __( 'İade Faturası', 'mmci' ) . '</a>';
    echo '</div>';
}

/**
 * 8) Yardımcı: Firma Bilgisini Getir
 */
function mmci_get_company_info( $order_id ) {
    $company_id = get_post_meta( $order_id, '_mmci_company_id', true );
    $ret = array(
        'title'      => '',
        'address'    => '',
        'phone'      => '',
        'email'      => '',
        'kvk'        => '',
        'btw'        => '',
        'iban'       => '',
        'taxno'      => '',
        'logo'       => '',
        'invoice_no' => '', // eski
        'prefix'     => '',
        'start'      => '',
    );
    if ( $company_id ) {
        $cp = get_post( $company_id );
        if ( $cp ) {
            $ret['title']      = $cp->post_title;
            $ret['address']    = get_post_meta( $cp->ID, '_mmci_company_address', true );
            $ret['phone']      = get_post_meta( $cp->ID, '_mmci_company_phone', true );
            $ret['email']      = get_post_meta( $cp->ID, '_mmci_company_email', true );
            $ret['kvk']        = get_post_meta( $cp->ID, '_mmci_company_kvk', true );
            $ret['btw']        = get_post_meta( $cp->ID, '_mmci_company_btw', true );
            $ret['iban']       = get_post_meta( $cp->ID, '_mmci_company_iban', true );
            $ret['taxno']      = get_post_meta( $cp->ID, '_mmci_company_taxno', true );
            $ret['logo']       = get_post_meta( $cp->ID, '_mmci_company_logo', true );
            $ret['invoice_no'] = get_post_meta( $cp->ID, '_mmci_company_invoice_no', true );
            $ret['prefix']     = get_post_meta( $cp->ID, '_mmci_company_invoice_prefix', true );
            $ret['start']      = get_post_meta( $cp->ID, '_mmci_company_invoice_start', true );
        }
    }
    return $ret;
}

/**
 * 9) PDF: INVOICE, PACKING SLIP, CREDIT NOTE ekranda görüntüleme
 */
add_action( 'admin_init', 'mmci_handle_document_view' );
function mmci_handle_document_view() {
    if ( isset( $_GET['mmci_action'] ) && isset( $_GET['order_id'] ) ) {
        $action   = sanitize_text_field( $_GET['mmci_action'] );
        $order_id = absint( $_GET['order_id'] );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( __( 'Yetkiniz yok.', 'mmci' ) );
        }

        if ( $action === 'invoice' ) {
            mmci_render_invoice_pdf( $order_id );
        } elseif ( $action === 'packing_slip' ) {
            mmci_render_packing_slip_pdf( $order_id );
        } elseif ( $action === 'credit_note' ) {
            mmci_render_credit_note_pdf( $order_id );
        }
    }
}

/* ------------------------------------------------------------------
 * 9-A) Fatura HTML'sini ortak oluşturma fonksiyonu
 * ----------------------------------------------------------------*/
function mmci_build_invoice_html( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return '<p>' . __('Sipariş bulunamadı.', 'mmci') . '</p>';
    }
    $company = mmci_get_company_info( $order_id );

    // Fatura numarası yoksa oluştur
    $existing_invoice_no = get_post_meta( $order_id, '_mmci_invoice_number', true );
    if ( empty( $existing_invoice_no ) ) {
        $prefix = $company['prefix'] ?: 'INV';
        $start  = (int) ($company['start'] ?: 1);
        $new_invoice_no = $prefix . str_pad( $start, 5, '0', STR_PAD_LEFT );
        update_post_meta( $order_id, '_mmci_invoice_number', $new_invoice_no );
        // Firma başlangıç numarasını artır
        $company_id = get_post_meta( $order_id, '_mmci_company_id', true );
        update_post_meta( $company_id, '_mmci_company_invoice_start', $start + 1 );
        $existing_invoice_no = $new_invoice_no;
    }

    // Sipariş verileri
    $billing_name = $order->get_formatted_billing_full_name();
    $addr1        = $order->get_billing_address_1();
    $addr2        = $order->get_billing_address_2();
    $city         = $order->get_billing_city();
    $postcode     = $order->get_billing_postcode();
    $country      = $order->get_billing_country();
    $email        = $order->get_billing_email();
    $phone        = $order->get_billing_phone();
    
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
    @page { margin:20px; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 12px;
        margin: 0;
        color: #333;
    }
    .invoice-container {
        min-height:950px;
        display:flex;
        flex-direction:column;
        justify-content:space-between;
        padding:20px;
    }
    .header-table { display:table; width:100%; }
    .header-row { display:table-row; }
    .header-cell { display:table-cell; vertical-align:top; padding-right:10px; }
    .header-cell-right { text-align:right; }
    .invoice-title { font-size:16px; font-weight:bold; margin-top:10px; }
    .customer-info { margin-top:10px; line-height:1.4; }
    .company-title { font-weight:bold; margin-bottom:5px; }
    .company-details { margin-top:5px; line-height:1.4; }
    .invoice-details { margin-top:10px; line-height:1.4; }
    .products-table {
        width:100%; border-collapse:collapse; margin-top:20px;
    }
    .products-table th {
        background:#f8f8f8; text-align:left; font-weight:bold;
        padding:8px; border-bottom:1px solid #ddd;
    }
    .products-table td {
        padding:8px; border-bottom:1px solid #eee;
    }
    .totals-section {
        margin-top:20px; width:100%; text-align:right;
    }
    .totals-table {
        display:inline-table; border-collapse:collapse;
    }
    .totals-table tr td {
        padding:4px 8px;
    }
    .totals-table tr td:first-child { text-align:left; }
    .totals-table tr td:last-child {
        text-align:right; font-weight:bold;
    }
    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        border-top:1px solid #ccc;
        font-size:10px;
        color:#666;
        text-align:center;
        padding:8px 0;
        background:#fff;
    }
    </style>
</head>
<body>
<div class="invoice-container">
    <div class="content-area">
        <!-- HEADER -->
        <div class="header-table">
            <div class="header-row">
                <!-- Sol -->
                <div class="header-cell" style="width:50%;">
                    <?php if ( $company['logo'] ) : ?>
                        <img src="<?php echo esc_url( $company['logo'] ); ?>" alt="Logo" style="max-height:60px;">
                    <?php endif; ?>
                    <div class="invoice-title">INVOICE</div>
                    <div class="customer-info">
                        <?php echo esc_html( $billing_name ); ?><br>
                        <?php echo esc_html( $addr1 . ' ' . $addr2 ); ?><br>
                        <?php echo esc_html( $postcode . ' ' . $city ); ?><br>
                        <?php echo esc_html( $country ); ?><br>
                        <?php echo esc_html( $email ); ?><br>
                        <?php echo esc_html( $phone ); ?>
                    </div>
                </div>
                <!-- Sağ -->
                <div class="header-cell header-cell-right" style="width:50%;">
                    <div class="company-title">
                        <?php echo esc_html( $company['title'] ); ?>
                    </div>
                    <div class="company-details">
                        <?php echo nl2br( esc_html( $company['address'] ) ); ?><br>
                        <?php if ( $company['email'] ) : echo esc_html( $company['email'] ) . '<br>'; endif; ?>
                        <?php if ( $company['phone'] ) : echo esc_html( $company['phone'] ) . '<br>'; endif; ?>
                        <?php if ( $company['kvk'] ) : ?>
                            <?php _e( 'KVK:', 'mmci' ); echo ' ' . esc_html( $company['kvk'] ) . '<br>'; ?>
                        <?php endif; ?>
                    </div>
                    <div class="invoice-details">
                        <strong><?php _e('Invoice Number:', 'mmci'); ?></strong>
                        <?php echo esc_html( $existing_invoice_no ); ?><br>
                        <strong><?php _e('Invoice Date:', 'mmci'); ?></strong>
                        <?php echo date_i18n( 'F j, Y', current_time( 'timestamp' ) ); ?><br>
                        <strong><?php _e('Order Number:', 'mmci'); ?></strong>
                        <?php echo $order->get_order_number(); ?><br>
                        <strong><?php _e('Order Date:', 'mmci'); ?></strong>
                        <?php echo wc_format_datetime( $order->get_date_created() ); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ÜRÜNLER TABLOSU -->
        <table class="products-table">
            <thead>
                <tr>
                    <th><?php _e( 'Product', 'mmci' ); ?></th>
                    <th><?php _e( 'Quantity', 'mmci' ); ?></th>
                    <th><?php _e( 'Price', 'mmci' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $order->get_items() as $item ) :
                $product_name = $item->get_name();
                $quantity     = $item->get_quantity();
                $total        = wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) );
                ?>
                <tr>
                    <td><?php echo esc_html( $product_name ); ?></td>
                    <td><?php echo esc_html( $quantity ); ?></td>
                    <td><?php echo $total; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <table class="totals-table">
                <tbody>
                    <tr>
                        <td><?php _e( 'Subtotal', 'mmci' ); ?></td>
                        <td><?php echo $order->get_subtotal_to_display(); ?></td>
                    </tr>
                    <?php if ( $order->get_shipping_total() > 0 ) : ?>
                    <tr>
                        <td><?php _e( 'Shipping', 'mmci' ); ?></td>
                        <td><?php echo wc_price( $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $order->get_total_tax() ) : ?>
                    <tr>
                        <td><?php _e( 'Tax', 'mmci' ); ?></td>
                        <td><?php echo wc_price( $order->get_total_tax(), array( 'currency' => $order->get_currency() ) ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><?php _e( 'Total', 'mmci' ); ?></td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <p>
            <strong><?php echo esc_html( $company['title'] ); ?></strong> |
            <?php echo nl2br( esc_html( $company['address'] ) ); ?> |
            <?php if ( $company['email'] ) : echo esc_html( $company['email'] ) . ' | '; endif; ?>
            <?php if ( $company['phone'] ) : echo esc_html( $company['phone'] ) . ' | '; endif; ?>
            <?php if ( $company['btw'] ) : _e('BTW:', 'mmci'); echo ' ' . esc_html( $company['btw'] ) . ' | '; endif; ?>
            <?php if ( $company['kvk'] ) : _e('KVK:', 'mmci'); echo ' ' . esc_html( $company['kvk'] ) . ' | '; endif; ?>
            <?php if ( $company['iban'] ) : _e('IBAN:', 'mmci'); echo ' ' . esc_html( $company['iban'] ); endif; ?>
        </p>
    </div>
</div>
</body>
</html>
    <?php
    return ob_get_clean();
}

/**
 * 9-B) Fatura oluşturma (ekrana yazdır)
 */
function mmci_render_invoice_pdf( $order_id ) {
    $html = mmci_build_invoice_html( $order_id );

    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $order = wc_get_order( $order_id );
    $filename = 'Invoice-' . ( $order ? $order->get_order_number() : $order_id ) . '.pdf';
    $dompdf->stream($filename, array('Attachment' => false));
    exit;
}

/**
 * 9-C) Pakbon (ekrana yazdır; minimal)
 */
function mmci_render_packing_slip_pdf( $order_id ) {
    // Bu pakbonu isterseniz de "ayrı" bir build fonksiyonuna taşıyabilirsiniz
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_die( __( 'Sipariş bulunamadı.', 'mmci' ) );
    }
    $company = mmci_get_company_info( $order_id );

    ob_start();
    ?>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin:20px; }
        body { font-family:DejaVu Sans, sans-serif; font-size:12px; margin:0; color:#333; }
        .invoice-container {
            min-height:950px; display:flex; flex-direction:column;
            justify-content:space-between; padding:20px;
        }
        .header-table { display:table; width:100%; }
        .header-row { display:table-row; }
        .header-cell { display:table-cell; vertical-align:top; padding-right:10px; }
        .header-cell-right { text-align:right; }
        .invoice-title { font-size:16px; font-weight:bold; margin-top:10px; }
        .customer-info { margin-top:10px; line-height:1.4; }
        .company-title { font-weight:bold; margin-bottom:5px; }
        .company-details { margin-top:5px; line-height:1.4; }
        .invoice-details { margin-top:10px; line-height:1.4; }
        .products-table {
            width:100%; border-collapse:collapse; margin-top:20px;
        }
        .products-table th {
            background:#f8f8f8; text-align:left; font-weight:bold;
            padding:8px; border-bottom:1px solid #ddd;
        }
        .products-table td {
            padding:8px; border-bottom:1px solid #eee;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top:1px solid #ccc;
            font-size:10px;
            color:#666;
            text-align:center;
            padding:8px 0;
            background:#fff;
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <div class="content-area">
        <!-- HEADER -->
        <div class="header-table">
            <div class="header-row">
                <!-- Sol -->
                <div class="header-cell" style="width:50%;">
                    <?php if ( $company['logo'] ) : ?>
                        <img src="<?php echo esc_url( $company['logo'] ); ?>" alt="Logo" style="max-height:60px;">
                    <?php endif; ?>
                    <div class="invoice-title">PACKING SLIP</div>
                    <div class="customer-info">
                        <?php 
                        $shipping_name = $order->get_formatted_shipping_full_name();
                        $saddr1 = $order->get_shipping_address_1();
                        $saddr2 = $order->get_shipping_address_2();
                        $scity  = $order->get_shipping_city();
                        $spostcode = $order->get_shipping_postcode();
                        $scountry = $order->get_shipping_country();
                        ?>
                        <?php echo esc_html( $shipping_name ); ?><br>
                        <?php echo esc_html( $saddr1 . ' ' . $saddr2 ); ?><br>
                        <?php echo esc_html( $spostcode . ' ' . $scity ); ?><br>
                        <?php echo esc_html( $scountry ); ?>
                    </div>
                </div>
                <!-- Sağ -->
                <div class="header-cell header-cell-right" style="width:50%;">
                    <div class="company-title">
                        <?php echo esc_html( $company['title'] ); ?>
                    </div>
                    <div class="company-details">
                        <?php echo nl2br( esc_html( $company['address'] ) ); ?><br>
                        <?php if ( $company['email'] ) : echo esc_html( $company['email'] ) . '<br>'; endif; ?>
                        <?php if ( $company['phone'] ) : echo esc_html( $company['phone'] ) . '<br>'; endif; ?>
                    </div>
                    <div class="invoice-details">
                        <strong><?php _e('Order Number:', 'mmci'); ?></strong>
                        <?php echo $order->get_order_number(); ?><br>
                        <strong><?php _e('Order Date:', 'mmci'); ?></strong>
                        <?php echo wc_format_datetime( $order->get_date_created() ); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRODUCTS TABLE -->
        <table class="products-table">
            <thead>
                <tr>
                    <th><?php _e( 'Product', 'mmci' ); ?></th>
                    <th><?php _e( 'Quantity', 'mmci' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $order->get_items() as $item ) :
                $product_name = $item->get_name();
                $quantity     = $item->get_quantity();
                ?>
                <tr>
                    <td><?php echo esc_html( $product_name ); ?></td>
                    <td><?php echo esc_html( $quantity ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>
            <strong><?php echo esc_html( $company['title'] ); ?></strong> |
            <?php echo nl2br( esc_html( $company['address'] ) ); ?> |
            <?php if ( $company['email'] ) : echo esc_html( $company['email'] ) . ' | '; endif; ?>
            <?php if ( $company['phone'] ) : echo esc_html( $company['phone'] ) . ' | '; endif; ?>
            <?php if ( $company['btw'] ) : _e('BTW:', 'mmci'); echo ' ' . esc_html( $company['btw'] ) . ' | '; endif; ?>
            <?php if ( $company['kvk'] ) : _e('KVK:', 'mmci'); echo ' ' . esc_html( $company['kvk'] ) . ' | '; endif; ?>
            <?php if ( $company['iban'] ) : _e('IBAN:', 'mmci'); echo ' ' . esc_html( $company['iban'] ); endif; ?>
        </p>
    </div>
</div>
</body>
</html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $filename = 'PackingSlip-' . $order->get_order_number() . '.pdf';
    $dompdf->stream($filename, array('Attachment' => false));
    exit;
}

/* ------------------------------------------------------------------
 * 9-D) Kredi notu (iade faturası) HTML'sini oluşturan fonksiyon
 * ----------------------------------------------------------------*/
function mmci_build_credit_note_html( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return '<p>' . __('Sipariş bulunamadı.', 'mmci') . '</p>';
    }
    $company = mmci_get_company_info( $order_id );
    $order_invoice_no = get_post_meta( $order_id, '_mmci_invoice_number', true );

    // REFUND edilmiş kalemler:
    $refunds = $order->get_refunds();
    $refunded_items = array();
    $total_refunded = 0;
    if ( $refunds ) {
        foreach ( $refunds as $refund ) {
            $total_refunded += abs( $refund->get_amount() );
            foreach ( $refund->get_items() as $r_item ) {
                $pr_name  = $r_item->get_name();
                $pr_qty   = abs( $r_item->get_quantity() );
                $pr_total = abs( $r_item->get_total() );
                $refunded_items[] = array(
                    'name' => $pr_name,
                    'qty'  => $pr_qty,
                    'amt'  => $pr_total,
                );
            }
        }
    }

    // Faturalama adresi
    $billing_name = $order->get_formatted_billing_full_name();
    $addr1        = $order->get_billing_address_1();
    $addr2        = $order->get_billing_address_2();
    $city         = $order->get_billing_city();
    $postcode     = $order->get_billing_postcode();
    $country      = $order->get_billing_country();
    $email        = $order->get_billing_email();
    $phone        = $order->get_billing_phone();

    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
    @page { margin:20px; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 12px;
        margin: 0;
        color: #333;
    }
    .invoice-container {
        min-height:950px; display:flex; flex-direction:column;
        justify-content:space-between; padding:20px;
    }
    .header-table { display:table; width:100%; }
    .header-row { display:table-row; }
    .header-cell { display:table-cell; vertical-align:top; padding-right:10px; }
    .header-cell-right { text-align:right; }
    .invoice-title { font-size:16px; font-weight:bold; margin-top:10px; }
    .customer-info { margin-top:10px; line-height:1.4; }
    .company-title { font-weight:bold; margin-bottom:5px; }
    .company-details { margin-top:5px; line-height:1.4; }
    .invoice-details { margin-top:10px; line-height:1.4; }
    .products-table {
        width:100%; border-collapse:collapse; margin-top:20px;
    }
    .products-table th {
        background:#f8f8f8; text-align:left; font-weight:bold;
        padding:8px; border-bottom:1px solid #ddd;
    }
    .products-table td {
        padding:8px; border-bottom:1px solid #eee;
    }
    .totals-section {
        margin-top:20px; width:100%; text-align:right;
    }
    .totals-table {
        display:inline-table; border-collapse:collapse;
    }
    .totals-table tr td {
        padding:4px 8px;
    }
    .totals-table tr td:first-child { text-align:left; }
    .totals-table tr td:last-child {
        text-align:right; font-weight:bold;
    }
    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        border-top:1px solid #ccc;
        font-size:10px;
        color:#666;
        text-align:center;
        padding:8px 0;
        background:#fff;
    }
    </style>
</head>
<body>
<div class="invoice-container">
    <div class="content-area">
        <!-- HEADER -->
        <div class="header-table">
            <div class="header-row">
                <!-- Sol -->
                <div class="header-cell" style="width:50%;">
                    <?php if ( $company['logo'] ) : ?>
                        <img src="<?php echo esc_url( $company['logo'] ); ?>" alt="Logo" style="max-height:60px;">
                    <?php endif; ?>
                    <div class="invoice-title">CREDIT NOTE</div>
                    <div class="customer-info">
                        <?php echo esc_html( $billing_name ); ?><br>
                        <?php echo esc_html( $addr1 . ' ' . $addr2 ); ?><br>
                        <?php echo esc_html( $postcode . ' ' . $city ); ?><br>
                        <?php echo esc_html( $country ); ?><br>
                        <?php echo esc_html( $email ); ?><br>
                        <?php echo esc_html( $phone ); ?>
                    </div>
                </div>
                <!-- Sağ -->
                <div class="header-cell header-cell-right" style="width:50%;">
                    <div class="company-title">
                        <?php echo esc_html( $company['title'] ); ?>
                    </div>
                    <div class="company-details">
                        <?php echo nl2br( esc_html( $company['address'] ) ); ?><br>
                        <?php if ( $company['email'] ) : echo esc_html( $company['email'] ) . '<br>'; endif; ?>
                        <?php if ( $company['phone'] ) : echo esc_html( $company['phone'] ) . '<br>'; endif; ?>
                    </div>
                    <div class="invoice-details">
                        <strong><?php _e('Credit Note Date:', 'mmci'); ?></strong>
                        <?php echo date_i18n( 'F j, Y', current_time( 'timestamp' ) ); ?><br>
                        <strong><?php _e('Order Number:', 'mmci'); ?></strong>
                        <?php echo $order->get_order_number(); ?><br>
                        <strong><?php _e('Order Date:', 'mmci'); ?></strong>
                        <?php echo wc_format_datetime( $order->get_date_created() ); ?><br>
                        <?php if ( $order_invoice_no ) : ?>
                            <strong><?php _e('Reference Invoice:', 'mmci'); ?></strong>
                            <?php echo esc_html( $order_invoice_no ); ?><br>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refund Edilmiş Kalemler Tablosu -->
        <table class="products-table">
            <thead>
                <tr>
                    <th><?php _e( 'Product', 'mmci' ); ?></th>
                    <th><?php _e( 'Quantity', 'mmci' ); ?></th>
                    <th><?php _e( 'Refunded', 'mmci' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $refunds ) ) : ?>
                <tr><td colspan="3"><?php _e( 'No refunds were found.', 'mmci' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $refunded_items as $ri ) : ?>
                <tr>
                    <td><?php echo esc_html( $ri['name'] ); ?></td>
                    <td><?php echo esc_html( $ri['qty'] ); ?></td>
                    <td><?php echo wc_price( $ri['amt'], array( 'currency' => $order->get_currency() ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ( ! empty( $refunds ) ) : ?>
        <div class="totals-section">
            <table class="totals-table">
                <tbody>
                    <tr>
                        <td><?php _e( 'Total Refund', 'mmci' ); ?></td>
                        <td><?php echo wc_price( $total_refunded, array( 'currency' => $order->get_currency() ) ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>
            <strong><?php echo esc_html( $company['title'] ); ?></strong> |
            <?php echo nl2br( esc_html( $company['address'] ) ); ?> |
            <?php if ( $company['email'] ) : echo esc_html( $company['email'] ) . ' | '; endif; ?>
            <?php if ( $company['phone'] ) : echo esc_html( $company['phone'] ) . ' | '; endif; ?>
            <?php if ( $company['btw'] ) : _e('BTW:', 'mmci'); echo ' ' . esc_html( $company['btw'] ) . ' | '; endif; ?>
            <?php if ( $company['kvk'] ) : _e('KVK:', 'mmci'); echo ' ' . esc_html( $company['kvk'] ) . ' | '; endif; ?>
            <?php if ( $company['iban'] ) : _e('IBAN:', 'mmci'); echo ' ' . esc_html( $company['iban'] ); endif; ?>
        </p>
    </div>
</div>
</body>
</html>
    <?php
    return ob_get_clean();
}

/**
 * Kredi notu PDF'ini ekranda göstermek
 */
function mmci_render_credit_note_pdf( $order_id ) {
    $html = mmci_build_credit_note_html( $order_id );
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $order = wc_get_order( $order_id );
    $filename = 'CreditNote-' . ( $order ? $order->get_order_number() : $order_id ) . '.pdf';
    $dompdf->stream($filename, array('Attachment' => false));
    exit;
}

/* ------------------------------------------------------------------
 * 10) E-postaya PDF eklemek için "string" döndüren fonksiyonlar
 * ----------------------------------------------------------------*/
function mmci_get_invoice_pdf_as_string( $order_id ) {
    $html = mmci_build_invoice_html( $order_id );

    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output(); // PDF datası (string)
}

function mmci_get_credit_note_pdf_as_string( $order_id ) {
    $html = mmci_build_credit_note_html( $order_id );

    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

/* ------------------------------------------------------------------
 * 11) WooCommerce e-posta gönderilerinde PDF ekleme
 * (İsterseniz bu eklemeyi devre dışı bırakabilirsiniz)
 * ----------------------------------------------------------------*/
add_filter( 'woocommerce_email_attachments', 'mmci_attach_invoice_to_email', 10, 3 );
function mmci_attach_invoice_to_email( $attachments, $email_id, $order ) {
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return $attachments;
    }
    // Hangi maillere eklenecek?
    if ( in_array( $email_id, array( 'customer_processing_order', 'customer_completed_order' ), true ) ) {
        $order_id = $order->get_id();
        $pdf_data = mmci_get_invoice_pdf_as_string( $order_id );
        if ( $pdf_data ) {
            $upload_dir = wp_upload_dir();
            $temp_path  = $upload_dir['basedir'] . '/Invoice-' . $order->get_order_number() . '.pdf';
            file_put_contents( $temp_path, $pdf_data );
            $attachments[] = $temp_path;
        }
    }
    return $attachments;
}

// İade faturası (kredi notu) maili (customer_refunded_order)
add_filter( 'woocommerce_email_attachments', 'mmci_attach_credit_note_to_email', 10, 3 );
function mmci_attach_credit_note_to_email( $attachments, $email_id, $order ) {
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return $attachments;
    }
    if ( 'customer_refunded_order' === $email_id ) {
        $order_id = $order->get_id();
        $pdf_data = mmci_get_credit_note_pdf_as_string( $order_id );
        if ( $pdf_data ) {
            $upload_dir = wp_upload_dir();
            $temp_path  = $upload_dir['basedir'] . '/CreditNote-' . $order->get_order_number() . '.pdf';
            file_put_contents( $temp_path, $pdf_data );
            $attachments[] = $temp_path;
        }
    }
    return $attachments;
}

/* ------------------------------------------------------------------
 * 12) İsterseniz sipariş "processing" olduğunda AYRI e-posta atmak
 *     için şu fonksiyonları kullanabilirsiniz (opsiyonel).
 * ------------------------------------------------------------------

// add_action( 'woocommerce_order_status_processing', 'mmci_send_invoice_mail_on_processing', 10, 2 );
function mmci_send_invoice_mail_on_processing( $order_id, $order ) {
    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order ) return;

    $pdf_data = mmci_get_invoice_pdf_as_string( $order_id );
    if ( ! $pdf_data ) return;

    $upload_dir = wp_upload_dir();
    $temp_path  = $upload_dir['basedir'] . '/Invoice-' . $order->get_order_number() . '.pdf';
    file_put_contents( $temp_path, $pdf_data );

    $to       = $order->get_billing_email();
    $subject  = sprintf( __( 'Sipariş #%s - Faturanız', 'mmci' ), $order->get_order_number() );
    $message  = __( 'Merhaba,<br>Sipariş faturanızı ekte bulabilirsiniz.', 'mmci' );
    $headers  = array( 'Content-Type: text/html; charset=UTF-8' );

    wp_mail( $to, $subject, $message, $headers, array( $temp_path ) );
    
    // unlink($temp_path); // dilersek geçici dosyayı silebiliriz
}

*/