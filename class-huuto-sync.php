<?php

class Huuto_Sync {
    private $huuto_api;

    public function __construct() {
        // Initialize Huuto API class
        $this->huuto_api = new Huuto_API();

        // Add custom fields for Huuto in the product editor
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_custom_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_custom_fields' ] );

        // Sync product when saved
        add_action( 'save_post', [ $this, 'sync_product_to_huuto' ] );

        // Sync status changes
        add_action( 'transition_post_status', [ $this, 'sync_status_change' ], 10, 3 );
    }

    // Add custom fields for Huuto

    public function add_custom_fields() {
        global $post;
           // Checkbox for syncing with Huuto
        woocommerce_wp_checkbox( [
            'id' => '_huuto_sync',
            'label' => __( 'Sync with Huuto', 'huuto-sync' ),
            'cbvalue' => 'yes', // Set checkbox value to 'yes' when checked
        ] );

        echo '<div class="options_group">';
        $categories = $this->huuto_api->get_categories();

        if ( !is_wp_error( $categories ) ) {
            echo '<p class="form-field"><label for="_huuto_category">' . __( 'Huuto.net Category', 'huuto-sync' ) . '</label>';
            echo '<select id="_huuto_category" name="_huuto_category">';
            echo '<option value="">' . esc_html__( 'Select a category', 'huuto-sync' ) . '</option>'; // Default option

            foreach ( $categories as $category ) {
                $indentation = str_repeat( '-- ', $category['level'] );
                $selected = selected( get_post_meta( $post->ID, '_huuto_category', true ), $category['id'], false );
                echo '<option value="' . esc_attr( $category['id'] ) . '"' . $selected . '>' . esc_html( $indentation . $category['title'] ) . '</option>';
            }

            echo '</select></p>';
        } else {
            echo '<p>' . __( 'Error fetching categories from Huuto.net.', 'huuto-sync' ) . '</p>';
        }

        // Add manually input fields
        woocommerce_wp_text_input( [
            'id' => '_huuto_delivery_methods',
            'label' => __( 'Huuto Delivery Methods (comma-separated)', 'huuto-sync' ),
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
            'custom_attributes' => ['min' => '0', 'max' => '120'],
        ] );

        woocommerce_wp_text_input( [
            'id' => '_huuto_payment_methods',
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

        echo '</div>';
    }

    // Save custom fields when the product is saved
    public function save_custom_fields( $post_id ) {
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
    }

    // Sync product to Huuto when saved

    public function sync_product_to_huuto( $post_id ) {
        // Check if it's a product post type
        if ( get_post_type( $post_id ) !== 'product' ) {
            return;
        }
    
        $huuto_item_id = get_post_meta( $post_id, '_huuto_item_id', true );
        $product = wc_get_product( $post_id );
    
        // Product data to send to Huuto.net
        $data = [
            'title' => $product->get_name(),
            'description' => $product->get_description(),
            'buyNowPrice' => $product->get_sale_price(),
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
            'saleMethod' => 'buyNow',
            'status' => get_post_meta( $post_id, '_huuto_status', true ),
            'vat' => get_post_meta( $post_id, '_huuto_vat', true ),
            'offersAllowed' => 'yes',
        ];
    
        if ( $huuto_item_id ) {
            // Update existing item on Huuto.net
            $response = $this->huuto_api->update_item( $huuto_item_id, $data );
            if ( is_wp_error( $response ) ) {
                error_log( 'Error updating product on Huuto.net: ' . $response->get_error_message() );
                return;
            }
        } else {
            // Create a new item on Huuto.net
            $response = $this->huuto_api->create_item( $data );
            if ( isset( $response['id'] ) ) {
                update_post_meta( $post_id, '_huuto_item_id', $response['id'] );
                $huuto_item_id = $response['id'];
            } else {
                error_log( 'Error creating product on Huuto.net: ' . print_r( $response, true ) );
                return;
            }
        }
    
        // After successfully saving/updating product info, upload images
        if ( $huuto_item_id ) {
            $this->upload_images_to_huuto( $post_id, $huuto_item_id );
        }
    }
    
    public function upload_images_to_huuto( $post_id, $huuto_item_id ) {
        $product = wc_get_product( $post_id );
        $gallery_image_ids = $product->get_gallery_image_ids();  // Get all image IDs from WooCommerce
    
        foreach ( $gallery_image_ids as $image_id ) {
            $image_url = wp_get_attachment_url( $image_id );
    
            // Send image to Huuto.net
            $this->huuto_api->upload_image( $huuto_item_id, $image_url );
        }
    }
    
}

