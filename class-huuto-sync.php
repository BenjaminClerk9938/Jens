<?php

class Huuto_Sync {
    private $huuto_api;

    public function __construct() {
        // Initialize Huuto API class
        $this->huuto_api = new Huuto_API();
        add_action( 'wp_ajax_sync_product_to_huuto', [ $this, 'ajax_sync_product_to_huuto' ] );

        // Add custom fields for Huuto in the product editor
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_custom_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_custom_fields' ] );

        // Sync product when saved
        add_action( 'save_post', [ $this, 'sync_product_to_huuto' ] );

        // Sync status changes
        add_action( 'transition_post_status', [ $this, 'sync_product_to_huuto' ], 10, 3 );
    }

    // Add custom fields for Huuto

    public function add_custom_fields() {
        global $post;
        // Checkbox for syncing with Huuto

        echo '<div class="options_group">';
        woocommerce_wp_checkbox( [
            'id' => '_huuto_sync',
            'label' => __( 'Sync with Huuto', 'huuto-sync' ),
            'cbvalue' => 'yes', // Set checkbox value to 'yes' when checked
        ] );
        $categories = $this->huuto_api->get_categories();

        if ( !is_wp_error( $categories ) ) {
            echo '<p class="form-field"><label for="_huuto_category">' . __( 'Huuto.net Category', 'huuto-sync' ) . '</label>';
            echo '<select id="_huuto_category" name="_huuto_category">';
            echo '<option value="">' . esc_html__( 'Select a category', 'huuto-sync' ) . '</option>';
            // Default option

            foreach ( $categories as $category ) {
                $indentation = str_repeat( '-- ', $category[ 'level' ] );
                $selected = selected( get_post_meta( $post->ID, '_huuto_category', true ), $category[ 'id' ], false );
                echo '<option value="' . esc_attr( $category[ 'id' ] ) . '"' . $selected . '>' . esc_html( $indentation . $category[ 'title' ] ) . '</option>';
            }

            echo '</select></p>';
        } else {
            echo '<p>' . __( 'Error fetching categories from Huuto.net.', 'huuto-sync' ) . '</p>';
        }

        // Add manually input fields
        woocommerce_wp_text_input( [
            'id' => '_huuto_delivery_methods',
            'label' => __( 'Huuto Delivery Methods (comma-separated)', 'huuto-sync' ),
            'placeholder' => 'e.g., pickup, shipment',
            'description' => __( 'Enter delivery methods separated by commas.', 'huuto-sync' ),
        ] );

        woocommerce_wp_text_input( [
            'id' => '_huuto_delivery_terms',
            'label' => __( 'Huuto Delivery Terms', 'huuto-sync' ),
        ] );

        woocommerce_wp_text_input( [
            'id' => '_huuto_open_days',
            'label' => __( 'Open Days', 'huuto-sync' ),
            'type' => 'number',
            'custom_attributes' => [ 'min' => '0', 'max' => '120' ],
        ] );

        woocommerce_wp_text_input( [
            'id' => '_huuto_payment_methods',
            'placeholder' => 'e.g., wire-transfer, cash, mobile-pay',
            'label' => __( 'Payment Methods (comma-separated)', 'huuto-sync' ),
        ] );

        woocommerce_wp_text_input( [
            'id' => '_huuto_payment_terms',
            'label' => __( 'Payment Terms', 'huuto-sync' ),
        ] );

        woocommerce_wp_text_input( [
            'id' => '_huuto_vat',
            'label' => __( 'VAT', 'huuto-sync' ),
        ] );

        woocommerce_wp_text_input( [
            'id' => '_huuto_original_id',
            'label' => __( 'Original ID (optional)', 'huuto-sync' ),
        ] );

        // Add dropdown for marginal tax
        woocommerce_wp_select( [
            'id' => '_huuto_marginal_tax',
            'label' => __( 'Marginal Tax', 'huuto-sync' ),
            'options' => [ '0' => __( 'No', 'huuto-sync' ), '1' => __( 'Yes', 'huuto-sync' ) ],
            'default' => '0',
        ] );

        // Add dropdown for status
        woocommerce_wp_select( [
            'id' => '_huuto_status',
            'label' => __( 'Status', 'huuto-sync' ),
            'options' => [
                'draft' => __( 'Draft', 'huuto-sync' ),
                'preview' => __( 'Preview', 'huuto-sync' ),
                'published' => __( 'Published', 'huuto-sync' ),
                'closed' => __( 'Closed', 'huuto-sync' ),
                'disabled' => __( 'Disabled', 'huuto-sync' ),
                'waiting' => __( 'Waiting', 'huuto-sync' ),
            ],
        ] );
        echo '<p><button id="huuto-sync-button" data-post-id="' . esc_attr( $post->ID ) . '" class="button button-primary">' . __( 'Sync to Huuto.net', 'huuto-sync' ) . '</button></p>';

        // Add a status message area to display success or error messages
        echo '<p id="huuto-sync-status"></p>';

        $huuto_product_id = get_post_meta( $post->ID, '_huuto_item_id', true );

        // Display the Huuto.net Product ID as read-only
        woocommerce_wp_text_input( [
            'id' => '_huuto_item_id',
            'label' => __( 'Huuto.net Product ID', 'huuto-sync' ),
            'value' => $huuto_product_id ? $huuto_product_id : __( 'Not Synced', 'huuto-sync' ),
            'custom_attributes' => [ 'readonly' => 'readonly' ],  // Make the field read-only
            'description' => __( 'This is the product ID from Huuto.net.', 'huuto-sync' ),
        ] );
        echo '</div>';
    }

    // Save custom fields when the product is saved

    public function save_custom_fields( $post_id ) {
        update_post_meta( $post_id, '_huuto_sync', isset( $_POST[ '_huuto_sync' ] ) ? 'yes' : 'no' );
        update_post_meta( $post_id, '_huuto_category', sanitize_text_field( $_POST[ '_huuto_category' ] ) );
        update_post_meta( $post_id, '_huuto_delivery_methods', sanitize_text_field( $_POST[ '_huuto_delivery_methods' ] ) );
        update_post_meta( $post_id, '_huuto_delivery_terms', sanitize_text_field( $_POST[ '_huuto_delivery_terms' ] ) );
        update_post_meta( $post_id, '_huuto_open_days', intval( $_POST[ '_huuto_open_days' ] ) );
        update_post_meta( $post_id, '_huuto_payment_methods', sanitize_text_field( $_POST[ '_huuto_payment_methods' ] ) );
        update_post_meta( $post_id, '_huuto_payment_terms', sanitize_text_field( $_POST[ '_huuto_payment_terms' ] ) );
        update_post_meta( $post_id, '_huuto_vat', sanitize_text_field( $_POST[ '_huuto_vat' ] ) );
        update_post_meta( $post_id, '_huuto_original_id', sanitize_text_field( $_POST[ '_huuto_original_id' ] ) );
        update_post_meta( $post_id, '_huuto_marginal_tax', sanitize_text_field( $_POST[ '_huuto_marginal_tax' ] ) );
        update_post_meta( $post_id, '_huuto_status', sanitize_text_field( $_POST[ '_huuto_status' ] ) );
        //print_r( 'save_custom_fields is called' );
        //print_r( $post_id );
        //print_r( $_POST );
    }

    // Handle AJAX request for syncing products

    public function ajax_sync_product_to_huuto() {
        check_ajax_referer( 'huuto_ajax_nonce', 'security' );
        // Validate the nonce for security

        $post_id = intval( $_POST[ 'post_id' ] );

        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid product ID.' );
        }

        // Sync the product to Huuto.net ( reuse existing sync logic )
        $response = $this->sync_product_to_huuto( $post_id );

        if ( is_wp_error( $response ) || $response == null ) {
            wp_send_json_error( $response->get_error_message() );
        }
        print_r( $response );
        // Send success response to AJAX call ( if successful )
        wp_send_json_success( [
            'message' => 'Product synced successfully with Huuto.net.',
            'huuto_response' => $response, // Include the actual response
        ] );
    }

    // Sync product to Huuto when saved

    public function sync_product_to_huuto( $post_id ) {
        // Check if it's a product post type
        if ( get_post_type( $post_id ) !== 'product' ) {

            //print_r("product id don't exist");
            return;
        }   
         // Check if the Huuto sync checkbox is checked
          $sync_with_huuto = get_post_meta( $post_id, '_huuto_sync', true );
         if ( $sync_with_huuto !== 'yes' ) {
             return;  // Don't sync if the checkbox is not checked
         }
         //print_r("sync_product_to_huuto" );
        $huuto_item_id = get_post_meta( $post_id, '_huuto_item_id', true );
        $product = wc_get_product( $post_id );
        // Product data to send to Huuto.net
        print_r( $product );
        $buy_now_price = $product->get_price();

        // Fallback to regular price if needed
        if ( ! $buy_now_price ) {
            $buy_now_price = $product->get_regular_price();
        }
        // Get product data from WooCommerce ( adjust fields as needed )
        $data = [
            'title' => $product->get_name(),
            'description' => $product->get_description(),
            'buyNowPrice' => $buy_now_price,
            'categoryId' => get_post_meta( $post_id, '_huuto_category', true ),
            'condition' => 'new',
            'deliveryMethods' => explode( ',', get_post_meta( $post_id, '_huuto_delivery_methods', true ) ),
            'deliveryTerms' => get_post_meta( $post_id, '_huuto_delivery_terms', true ),
            'identificationRequired' => 0,
            'isLocationAbroad' => 1,
            'marginalTax' => get_post_meta( $post_id, '_huuto_marginal_tax', true ),
            'openDays' => get_post_meta( $post_id, '_huuto_open_days', true ),
            'originalId' => get_post_meta( $post_id, '_huuto_original_id', true ),
            'paymentMethods' => explode( ',', get_post_meta( $post_id, '_huuto_payment_methods', true ) ),
            'paymentTerms' => get_post_meta( $post_id, '_huuto_payment_terms', true ),
            'quantity' => $product->get_stock_quantity(),
            'republish' => 1,
            'saleMethod' => 'buy-now',
            'status' => get_post_meta( $post_id, '_huuto_status', true ),
            'vat' => get_post_meta( $post_id, '_huuto_vat', true ),
            'offersAllowed' => 1,
        ];
        // Check if the product already exists on Huuto.net ( based on the item ID )
        if ( $huuto_item_id ) {
            $this->upload_images_to_huuto( $post_id, $huuto_item_id );

            print_r( $huuto_item_id );
            // Update existing item on Huuto.net
            $response = $this->huuto_api->update_item( $huuto_item_id, $data );
            if ( is_wp_error( $response ) ) {
                error_log( 'Error updating product on Huuto.net: ' . $response->get_error_message() );
                return;
            }
            return $response;
        } else {
            // Create a new item on Huuto.net
            $response = $this->huuto_api->create_item( $data );
            print_r( 'create item is called' );
            print_r( $response );

            if ( isset( $response[ 'id' ] ) ) {
                update_post_meta( $post_id, '_huuto_item_id', $response[ 'id' ] );
                $huuto_item_id = $response[ 'id' ];
            } else {
                return error_log( 'Error creating product on Huuto.net: ' .print_r( $response, true ) );
            }
            return $response;
        }

    }

    public function upload_images_to_huuto( $post_id, $huuto_item_id ) {
        $product = wc_get_product( $post_id );
        $gallery_image_ids = $product->get_gallery_image_ids();
        // Get all image IDs from WooCommerce

        foreach ( $gallery_image_ids as $image_id ) {
            $image_url = wp_get_attachment_url( $image_id );

            // Send image to Huuto.net
            $this->huuto_api->upload_image( $huuto_item_id, $image_url );
        }
    }

}

