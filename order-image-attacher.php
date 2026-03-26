<?php
/** 
 * Plugin Name: Order Image Attacher
 * Description: Makes a widget on the order edit page where you can add images that can be downloaded anytime.
 * Version: 1.2.0
 * Author: Josel Canlas
 * Author URI: https://joselcanlas.com/
 * Developer: Josel Canlas
 * Developer URI: https://joselcanlas.com/
 * Text Domain: order-image-attacher
 * 
 * WC requires at least: 9.8.5
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Order_Image_Attachments {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_order_images_meta_box'), 10, 2);
        add_action('add_meta_boxes_woocommerce_page_wc-orders', array($this, 'add_order_images_meta_box_hpos'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_add_order_meta', array($this, 'save_images'));
    }

    public function enqueue_admin_scripts($hook) {
        // Ensure we're editing an order
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ((($hook !== 'post.php' && $hook !== 'post-new.php') && $screen->id !== 'woocommerce_page_wc-orders') || !$screen || $screen->post_type !== 'shop_order' || !isset($_GET['action']) && $_GET['action'] !== 'edit') return;

        wp_enqueue_script('order-image-attacher-js', plugin_dir_url(__FILE__).'assets/js/order-image-attacher.js', [], '1.0.0');
        wp_localize_script('order-image-attacher-js', 'orderImagesAttachmentsVars', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('order_image_attachments_nonce')
        ));
        wp_enqueue_style('order-image-attacher-css', plugin_dir_url(__FILE__).'assets/css/order-image-attacher.css', [], '1.0.0');
    }

    public function add_order_images_meta_box($post_type, $post) {
        if ($post_type !== 'shop_order' || !$post) return;
        
        $order = wc_get_order($post->ID);

        if (!$order) return;
        add_meta_box(
            'order_images_meta_box',
            'Order Images',
            array($this, 'render_meta_box'),
            'shop_order',
            'normal',
            'core',
            ['order' => $order,]
        );
    }
    public function add_order_images_meta_box_hpos() {
        $order_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : 0;
        
        $order = wc_get_order($order_id);

        if (!$order) return;

        add_meta_box(
            'order_images_meta_box',
            'Order Images',
            array($this, 'render_meta_box'),
            'woocommerce_page_wc-orders',
            'normal',
            'core',
            ['order' => $order,]
        );
    }

    public function render_meta_box($post, $meta_box) {
        $order = $meta_box['args']['order'] ?? null;

        if (!$order) return;

        $files = explode(', ', (string) $order->get_meta('_oia_images'));

        ob_start();
        ?>
        <div class="oia-order-imgs" id="OIAOrderImages">
            <input name="upload_order_images" id="orderImgUpload" type="file" multiple>
            <button type="button" id="oiaDownloadAll" class="oia-download-all">Download all images</button>
            <?php foreach ($files as $file) { 
                if (!$file) continue;
                ?>
                <a class="oia-image-download" href="<?php echo esc_attr($file) ?>" download>
                    <img class="oia-order-image" src="<?php echo esc_attr($file) ?>">
                </a>
            <?php } ?>
        </div>
        <?php
        echo ob_get_clean();
    }

    public function save_images() {
        check_ajax_referer( 'order_image_attachments_nonce', 'nonce' );

        if ( empty( $_POST['order_id'] ) || empty( $_FILES['order_images'] ) ) {
            wp_send_json_error( 'Missing data' );
        }

        $order_id = absint( $_POST['order_id'] );

        if ( ! wc_get_order( $order_id ) ) {
            wp_send_json_error( 'Invalid order' );
        }
        if (!current_user_can('edit_shop_order', $order_id)) {
            wp_send_json_error('Permission denied.');
        }

        $order = wc_get_order($order_id);

        $upload_dir = wp_upload_dir();

        $order_dir = trailingslashit( $upload_dir['basedir'] ) .
            'order-image-attacher/' . $order_id;

        if ( ! file_exists( $order_dir ) ) {
            wp_mkdir_p( $order_dir );
        }

        $uploaded = [];

        foreach ( $_FILES['order_images']['name'] as $i => $name ) {

            if ( $_FILES['order_images']['error'][ $i ] !== UPLOAD_ERR_OK ) {
                continue;
            }

            $file = [
                'name'     => $_FILES['order_images']['name'][ $i ],
                'type'     => $_FILES['order_images']['type'][ $i ],
                'tmp_name' => $_FILES['order_images']['tmp_name'][ $i ],
                'error'    => $_FILES['order_images']['error'][ $i ],
                'size'     => $_FILES['order_images']['size'][ $i ],
            ];

            // Let WP do validation + temp upload
            $result = wp_handle_upload( $file, [
                'test_form' => false,
            ] );

            if ( empty( $result['file'] ) ) {
                continue;
            }

            $filename = wp_basename( $result['file'] );
            $target   = trailingslashit( $order_dir ) . $filename;

            rename( $result['file'], $target );

            $uploaded[] = trailingslashit(
                $upload_dir['baseurl']
            ) . 'order-image-attacher/' . $order_id . '/' . $filename;
        }
        $existing = (string) $order->get_meta('_oia_images');

        if ($existing !== '') {
            $existing_parts = array_filter(array_map('trim', explode(',', $existing)));
            $uploaded = array_unique(array_merge($existing_parts, $uploaded));
        }

        $order->add_order_note('Image(s) added.');
        $order->update_meta_data('_oia_images', implode(', ', $uploaded));
        $order->save();

        wp_send_json_success( $uploaded );
    }
}

// Init plugin
new Order_Image_Attachments();