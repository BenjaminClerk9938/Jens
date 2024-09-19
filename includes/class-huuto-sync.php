<?php

class Huuto_Sync {
    private $huuto_api;

    public function __construct() {
        // Initialize Huuto API class
        $this->huuto_api = new Huuto_API();

        // Add custom fields for Huuto in the product editor
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_custom_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_custom_fields']);

        // Sync product when saved
        add_action('save_post', [$this, 'sync_product_to_huuto']);

        // Sync status changes
        add_action('transition_post_status', [$this, 'sync_status_change'], 10, 3);
    }

    // Add custom fields for Huuto
    public function add_custom_fields() {
        echo '<div class="options_group">';

        // Checkbox for syncing with Huuto
        woocommerce_wp_checkbox([
            'id' => '_huuto_sync',
            'label' => __('Sync with Huuto', 'huuto-sync'),
        ]);

        // Text input for Huuto Category ID
        woocommerce_wp_text_input([
            'id' => '_huuto_category_id',
            'label' => __('Huuto Category ID', 'huuto-sync'),
        ]);

        // Text input for Huuto Delivery Methods
        woocommerce_wp_text_input([
            'id' => '_huuto_delivery_methods',
            'label' => __('Huuto Delivery Methods', 'huuto-sync'),
        ]);

        echo '</div>';
    }

    // Save custom fields when product is saved
    public function save_custom_fields($post_id) {
        update_post_meta($post_id, '_huuto_sync', isset($_POST['_huuto_sync']) ? 'yes' : 'no');
        update_post_meta($post_id, '_huuto_category_id', sanitize_text_field($_POST['_huuto_category_id']));
        update_post_meta($post_id, '_huuto_delivery_methods', sanitize_text_field($_POST['_huuto_delivery_methods']));
    }

    // Sync product to Huuto when saved
    public function sync_product_to_huuto($post_id) {
        // Check if product is marked for syncing with Huuto
        $sync = get_post_meta($post_id, '_huuto_sync', true);
        if ($sync === 'yes') {
            // Get product data
            $product = wc_get_product($post_id);
            $data = [
                'title' => $product->get_name(),
                'description' => $product->get_description(),
                'buyNowPrice' => $product->get_regular_price(),
                'categoryId' => get_post_meta($post_id, '_huuto_category_id', true),
                'deliveryMethods' => explode(',', get_post_meta($post_id, '_huuto_delivery_methods', true)),
                'quantity' => $product->get_stock_quantity(),
                'status' => 'preview',
            ];

            // Send data to Huuto using the Huuto_API class
            $response = $this->huuto_api->create_item($data);

            // Check for success and save the Huuto item ID
            if (!is_wp_error($response) && isset($response['id'])) {
                update_post_meta($post_id, '_huuto_item_id', $response['id']);
            } else {
                // Log error if API request failed
                error_log('Failed to sync product with Huuto: ' . print_r($response, true));
            }
        }
    }

    // Sync product status change
    public function sync_status_change($new_status, $old_status, $post) {
        // Ensure we're working with a product post type
        if ($post->post_type === 'product') {
            // Get the Huuto item ID
            $huuto_item_id = get_post_meta($post->ID, '_huuto_item_id', true);
            if ($huuto_item_id) {
                // Determine the status to set on Huuto based on WooCommerce status
                $status = ($new_status === 'publish') ? 'published' : 'draft';

                // Update the item's status on Huuto using the API
                $response = $this->huuto_api->update_item_status($huuto_item_id, $status);

                // Check for errors
                if (is_wp_error($response)) {
                    error_log('Failed to update Huuto status: ' . $response->get_error_message());
                }
            }
        }
    }
}
