<?php
/**
 * Plugin Name: Chương Trình Khuyến Mãi Tuỳ Chỉnh
 * Description: Plugin tạo chương trình khuyến mãi theo phần trăm (%) và thời gian. Tích hợp menu chọn sản phẩm thả xuống thông minh, tự động loại trừ các sản phẩm đang giảm giá.
 * Version: 1.2
 * Author: Developer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 0. Tải thư viện Select2 cho giao diện thả xuống tìm kiếm mượt mà
 */
add_action( 'admin_enqueue_scripts', 'custom_promo_enqueue_scripts' );
function custom_promo_enqueue_scripts( $hook ) {
    // Chỉ tải script trên đúng trang cài đặt của plugin
    if ( $hook !== 'toplevel_page_custom_promo_settings' ) {
        return;
    }
    wp_enqueue_style( 'select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13' );
    wp_enqueue_script( 'select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array( 'jquery' ), '4.0.13', true );
}

/**
 * 1. Khởi tạo Menu trong Dashboard
 */
add_action( 'admin_menu', 'custom_promo_add_admin_menu' );
function custom_promo_add_admin_menu() {
    add_menu_page(
        'Cấu Hình Khuyến Mãi',
        'Khuyến Mãi',
        'manage_options',
        'custom_promo_settings',
        'custom_promo_settings_page',
        'dashicons-tickets-alt',
        50
    );
}

/**
 * 2. Giao diện trang Cấu hình (Dashboard)
 */
function custom_promo_settings_page() {
    // Đảm bảo WooCommerce đang hoạt động
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="notice notice-error"><p>Vui lòng cài đặt và kích hoạt WooCommerce để sử dụng plugin này.</p></div>';
        return;
    }

    $message = '';
    // Xử lý lưu dữ liệu
    if ( isset( $_POST['submit_promo_settings'] ) ) {
        update_option( 'promo_discount_percent', sanitize_text_field( $_POST['promo_discount_percent'] ) );
        update_option( 'promo_start_date', sanitize_text_field( $_POST['promo_start_date'] ) );
        update_option( 'promo_end_date', sanitize_text_field( $_POST['promo_end_date'] ) );
        
        // Xử lý mảng ID sản phẩm được chọn từ Dropdown
        $selected_products = isset( $_POST['promo_product_ids'] ) ? array_map( 'intval', $_POST['promo_product_ids'] ) : array();
        update_option( 'promo_product_ids', $selected_products );
        
        $message = '<div class="custom-promo-notice"><strong>✓ Thành công:</strong> Đã lưu cấu hình khuyến mãi!</div>';
    }

    $percent      = get_option( 'promo_discount_percent', '' );
    $start_date   = get_option( 'promo_start_date', '' );
    $end_date     = get_option( 'promo_end_date', '' );
    $saved_ids    = get_option( 'promo_product_ids', array() );

    // Tương thích ngược nếu bản cũ lưu dưới dạng chuỗi string
    if ( ! is_array( $saved_ids ) ) {
        $saved_ids = empty( $saved_ids ) ? array() : explode( ',', $saved_ids );
    }
    ?>
    
    <style>
        .custom-promo-wrap { max-width: 700px; margin: 30px 20px 0 0; font-family: sans-serif; }
        .custom-promo-card { background: #ffffff; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); padding: 35px; border: 1px solid #e2e4e7; }
        .custom-promo-header { display: flex; align-items: center; border-bottom: 2px solid #f0f0f1; padding-bottom: 15px; margin-bottom: 25px; margin-top: 0; }
        .custom-promo-header h2 { margin: 0; font-size: 22px; color: #1d2327; font-weight: 600; }
        .custom-promo-header .dashicons { font-size: 28px; width: 28px; height: 28px; margin-right: 10px; color: #2271b1; }
        .custom-promo-group { margin-bottom: 25px; }
        .custom-promo-group label { display: block; font-weight: 600; margin-bottom: 10px; color: #2c3338; font-size: 14px; }
        .custom-promo-group input[type="number"], .custom-promo-group input[type="datetime-local"] { width: 100%; padding: 12px 15px; border: 1px solid #c3c4c7; border-radius: 6px; background-color: #f6f7f7; font-size: 14px; transition: all 0.3s; }
        .custom-promo-group input:focus { border-color: #2271b1; background-color: #ffffff; box-shadow: 0 0 0 1px #2271b1; outline: none; }
        .custom-promo-desc { color: #646970; font-size: 13px; margin-top: 6px; display: block; font-style: italic; }
        .custom-promo-btn { background: #2271b1; color: #fff; border: none; padding: 12px 30px; font-size: 15px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .custom-promo-btn:hover { background: #135e96; }
        .custom-promo-notice { background: #edfaef; color: #0f5132; border-left: 4px solid #198754; padding: 12px 20px; border-radius: 4px; margin-bottom: 25px; }
        
        /* Chỉnh lại style cho thư viện Select2 khớp với giao diện */
        .select2-container--default .select2-selection--multiple { border: 1px solid #c3c4c7; border-radius: 6px; background-color: #f6f7f7; padding: 6px; min-height: 44px; }
        .select2-container--default.select2-container--focus .select2-selection--multiple { border-color: #2271b1; background-color: #ffffff; }
        .select2-container--default .select2-selection--multiple .select2-selection__choice { background-color: #2271b1; color: #fff; border: none; border-radius: 4px; padding: 4px 8px; margin-top: 4px; }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color: #fff; margin-right: 8px; border-right: 1px solid rgba(255,255,255,0.3); padding-right: 8px; }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover { background: transparent; color: #ffcccc; }
    </style>

    <div class="custom-promo-wrap">
        <div class="custom-promo-card">
            <div class="custom-promo-header">
                <span class="dashicons dashicons-tickets-alt"></span>
                <h2>Thiết Lập Chương Trình Khuyến Mãi</h2>
            </div>
            
            <?php echo $message; ?>

            <form method="post" action="">
                
                <div class="custom-promo-group">
                    <label for="promo_product_ids">Chọn sản phẩm áp dụng khuyến mãi</label>
                    <select name="promo_product_ids[]" id="promo_product_ids" multiple="multiple" style="width: 100%;">
                        <?php
                        // Lấy toàn bộ sản phẩm đang được xuất bản
                        $args = array(
                            'status' => 'publish',
                            'limit'  => -1,
                        );
                        $products = wc_get_products( $args );

                        foreach ( $products as $product ) {
                            $pid         = $product->get_id();
                            $name        = $product->get_name();
                            $is_selected = in_array( $pid, $saved_ids );
                            $is_on_sale  = $product->is_on_sale();
                            
                            // Nếu SP đang được Sale mặc định bên ngoài và KHÔNG nằm trong danh sách đang chọn của thẻ này -> Khóa không cho chọn
                            $disabled  = ( $is_on_sale && !$is_selected ) ? 'disabled="disabled"' : '';
                            $sale_text = ( $is_on_sale && !$is_selected ) ? ' (Đã có khuyến mãi)' : '';
                            
                            echo '<option value="' . esc_attr( $pid ) . '" ' . selected( $is_selected, true, false ) . ' ' . $disabled . '>';
                            echo esc_html( $name . $sale_text );
                            echo '</option>';
                        }
                        ?>
                    </select>
                    <span class="custom-promo-desc">Tìm và chọn các sản phẩm (VD: Guitar Classic, Digital Piano, v.v.). Các sản phẩm đang có mức giá Sale độc lập bên ngoài sẽ bị làm mờ để tránh giảm giá chồng chéo. Nếu để trống, hệ thống sẽ bỏ qua.</span>
                </div>

                <div class="custom-promo-group">
                    <label for="promo_discount_percent">Phần trăm giảm giá (%)</label>
                    <input type="number" name="promo_discount_percent" id="promo_discount_percent" value="<?php echo esc_attr( $percent ); ?>" min="0" max="100" placeholder="Ví dụ: 15">
                </div>

                <div class="custom-promo-group">
                    <label for="promo_start_date">Thời gian bắt đầu</label>
                    <input type="datetime-local" name="promo_start_date" id="promo_start_date" value="<?php echo esc_attr( $start_date ); ?>">
                </div>

                <div class="custom-promo-group">
                    <label for="promo_end_date">Thời gian kết thúc</label>
                    <input type="datetime-local" name="promo_end_date" id="promo_end_date" value="<?php echo esc_attr( $end_date ); ?>">
                </div>

                <button type="submit" name="submit_promo_settings" class="custom-promo-btn">
                    Lưu Cấu Hình
                </button>
            </form>
        </div>
    </div>
    
    <!-- Kích hoạt hiệu ứng Dropdown tìm kiếm (Select2) -->
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#promo_product_ids').select2({
                placeholder: "Click để tìm kiếm và chọn sản phẩm...",
                allowClear: true,
                language: {
                    noResults: function() { return "Không tìm thấy sản phẩm nào"; }
                }
            });
        });
    </script>
    <?php
}

/**
 * 3. Logic xử lý tính toán và áp dụng giảm giá vào WooCommerce
 */
add_action( 'woocommerce_cart_calculate_fees', 'apply_custom_promo_discount', 10, 1 );
function apply_custom_promo_discount( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $percent      = get_option( 'promo_discount_percent' );
    $start_date   = get_option( 'promo_start_date' );
    $end_date     = get_option( 'promo_end_date' );
    $target_ids   = get_option( 'promo_product_ids', array() ); // Đã là mảng từ Database

    if ( empty( $percent ) || empty( $start_date ) || empty( $end_date ) || empty( $target_ids ) ) return;

    $current_time = current_time( 'timestamp' );
    $start_time   = strtotime( $start_date );
    $end_time     = strtotime( $end_date );

    if ( $current_time >= $start_time && $current_time <= $end_time ) {
        
        // Xử lý fallback cho biến cũ dạng chuỗi
        if ( ! is_array( $target_ids ) ) {
            $target_ids = array_map( 'intval', explode( ',', $target_ids ) );
        }

        $discount_subtotal = 0;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $current_product_id = $cart_item['product_id'];

            if ( in_array( $current_product_id, $target_ids ) ) {
                $discount_subtotal += $cart_item['line_total'];
            }
        }

        if ( $discount_subtotal > 0 ) {
            $discount_amount = ( $discount_subtotal * $percent ) / 100;
            $fee_title = sprintf( 'Khuyến mãi giảm %s%%', $percent );
            $cart->add_fee( $fee_title, -$discount_amount, true, '' );
        }
    }
}
/**
 * 4. Tạo Shortcode hiển thị danh sách sản phẩm khuyến mãi ra frontend
 */
add_shortcode( 'danh_sach_khuyen_mai', 'custom_promo_frontend_shortcode' );
function custom_promo_frontend_shortcode( $atts ) {
    // Lấy dữ liệu cấu hình từ Database
    $percent      = get_option( 'promo_discount_percent' );
    $start_date   = get_option( 'promo_start_date' );
    $end_date     = get_option( 'promo_end_date' );
    $target_ids   = get_option( 'promo_product_ids', array() );

    // Kiểm tra nếu chưa cấu hình
    if ( empty( $percent ) || empty( $start_date ) || empty( $end_date ) || empty( $target_ids ) ) {
        return '<div class="woocommerce-info">Hiện tại chưa có chương trình khuyến mãi nào được thiết lập.</div>';
    }

    $current_time = current_time( 'timestamp' );
    $start_time   = strtotime( $start_date );
    $end_time     = strtotime( $end_date );

    // Nếu không nằm trong khoảng thời gian diễn ra sự kiện
    if ( $current_time < $start_time || $current_time > $end_time ) {
        return '<div class="woocommerce-info">Chương trình khuyến mãi đã kết thúc hoặc chưa bắt đầu.</div>';
    }

    // Xử lý fallback cho mảng ID
    if ( ! is_array( $target_ids ) ) {
        $target_ids = array_map( 'intval', explode( ',', $target_ids ) );
    }
    
    // Chuyển mảng ID thành chuỗi cách nhau dấu phẩy để đưa vào shortcode WooCommerce
    $ids_string = implode( ',', $target_ids );

    // Bắt đầu bộ nhớ đệm (Output buffering) để render HTML
    ob_start();
    ?>
    <style>
        .promo-page-header {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
            color: #d32f2f;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .promo-page-header h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .promo-countdown {
            font-size: 14px;
            color: #555;
            margin-top: 10px;
            display: block;
        }
    </style>

    <div class="custom-promo-frontend-wrapper">
        <div class="promo-page-header">
            <h3>🔥 BÙNG NỔ ƯU ĐÃI: GIẢM TRỰC TIẾP <?php echo esc_html( $percent ); ?>% MỌI ĐƠN HÀNG!</h3>
            <span class="promo-countdown">
                Áp dụng đến hết ngày <?php echo date( 'd/m/Y H:i', $end_time ); ?>
            </span>
        </div>
        
        <?php
        // Tận dụng chính Shortcode mặc định của WooCommerce để hiển thị lưới sản phẩm
        // Thiết lập hiển thị 4 cột, bạn có thể đổi columns="4" thành "3" tuỳ thiết kế
        echo do_shortcode( '[products ids="' . esc_attr( $ids_string ) . '" columns="4"]' );
        ?>
    </div>
    
    <?php
    // Trả về nội dung HTML đã xử lý
    return ob_get_clean();
}