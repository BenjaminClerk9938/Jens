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
        echo '<div class="options_group">';

        // Get categories from Huuto API
        $categories = $this->huuto_api->get_categories();
        print_r( json_encode( $categories[ 'categories' ] ) );
        // Get categories from Huuto API ( replace with your actual API call )
        // Display categories dropdown
        if ( !is_wp_error( $categories ) && isset( $categories[ 'categories' ] ) ) {
            echo '<p class="form-field"><label for="_huuto_category">' . __( 'Huuto.net Category', 'huuto-sync' ) . '</label>';
            echo '<select id="_huuto_category" name="_huuto_category">';
            echo '<option value="">' . esc_html__( 'Select a category', 'huuto-sync' ) . '</option>';
            // Default option

            // Loop through categories and subcategories
            foreach ( $categories[ 'categories' ] as $category ) {
                // Display main category
                $selected = selected( get_post_meta( $post->ID, '_huuto_category', true ), $category[ 'id' ], false );
                echo '<option value="' . esc_attr( $category[ 'id' ] ) . '"' . $selected . '>' . esc_html( $category[ 'title' ] ) . '</option>';

                // Display subcategories, if available
                if ( isset( $category[ 'subcategories' ] ) ) {
                    foreach ( $category[ 'subcategories' ] as $subcategory ) {
                        $selected = selected( get_post_meta( $post->ID, '_huuto_category', true ), $subcategory[ 'id' ], false );
                        echo '<option value="' . esc_attr( $subcategory[ 'id' ] ) . '"' . $selected . '>-- ' . esc_html( $subcategory[ 'title' ] ) . '</option>';
                    }
                }
            }

            echo '</select></p>';
        } else {
            // Handle error if categories couldn't be fetched
            echo '<p>' . __( 'Error fetching categories from Huuto.net.', 'huuto-sync' ) . '</p>';
        }

        // Checkbox for syncing with Huuto
        woocommerce_wp_checkbox( [
            'id' => '_huuto_sync',
            'label' => __( 'Sync with Huuto', 'huuto-sync' ),
            'cbvalue' => 'yes', // Set checkbox value to 'yes' when checked
        ] );

        // Text input for Huuto Delivery Methods
        woocommerce_wp_text_input( [
            'id' => '_huuto_delivery_methods',
            'label' => __( 'Huuto Delivery Methods ( comma-separated )', 'huuto-sync' ),
            'description' => __( 'Enter delivery methods separated by commas.', 'huuto-sync' ),
        ] );

        echo '</div>';
    }

    // Save custom fields when product is saved

    public function save_custom_fields( $post_id ) {
        // Check if the checkbox is set before saving
        update_post_meta( $post_id, '_huuto_sync', isset( $_POST[ '_huuto_sync' ] ) ? 'yes' : 'no' );
        update_post_meta( $post_id, '_huuto_category', sanitize_text_field( $_POST[ '_huuto_category' ] ) );
        update_post_meta( $post_id, '_huuto_delivery_methods', sanitize_text_field( $_POST[ '_huuto_delivery_methods' ] ) );
    }

    // Sync product to Huuto when saved

    public function sync_product_to_huuto( $post_id ) {
        // Check if it's a product post type and if it should be synced
            if ( get_post_type( $post_id ) !== 'product' || get_post_meta( $post_id, '_huuto_sync', true ) !== 'yes' ) {
                return;
            }

            // Get product data
            $product = wc_get_product( $post_id );
            $huuto_item_id = get_post_meta( $post_id, '_huuto_item_id', true );

            $data = [
                'title' => $product->get_name(),
                'description' => $product->get_description(),
                'buyNowPrice' => $product->get_regular_price(),
                'categoryId' => get_post_meta( $post_id, '_huuto_category', true ),
                'deliveryMethods' => explode( ',', get_post_meta( $post_id, '_huuto_delivery_methods', true ) ),
                'quantity' => $product->get_stock_quantity(),
                'status' => 'preview', // You might need to adjust the initial status
            ];

            // Create or update the listing on Huuto
            if ( $huuto_item_id ) {
                // Update existing listing
                $response = $this->huuto_api->update_item( $huuto_item_id, $data );

                // Handle the API response ( check for errors, etc. )
                if ( is_wp_error( $response ) ) {
                    // Handle error, maybe log it or display a notice
                    error_log( 'Error updating product on Huuto: ' . $response->get_error_message() );
                    // Consider adding user-facing feedback here as well
                } else {
                    // Success! Maybe log a success message or update a flag
                }
            } else {
                // Create new listing
                $response = $this->huuto_api->create_item( $data );

                // If creation is successful, save the Huuto item ID
                if ( isset( $response[ 'id' ] ) && !empty( $response[ 'id' ] ) ) {

                    update_post_meta( $post_id, '_huuto_item_id', $response[ 'id' ] );
                } else {
                    // Handle unexpected response format
                    error_log( 'Failed to sync product with Huuto: Unexpected response format: ' . print_r( $response, true ) );

                    // Consider adding user-facing feedback here as well
                }
            }

        }

        // Sync product status change

        public function sync_status_change( $new_status, $old_status, $post ) {
            // Ensure we're working with a product post type and if it should be synced
        if ( $post->post_type !== 'product' || get_post_meta( $post->ID, '_huuto_sync', true ) !== 'yes' ) {
            return;
        }

        // Get the Huuto item ID
        $huuto_item_id = get_post_meta( $post->ID, '_huuto_item_id', true );

        // If the product has a corresponding Huuto listing
        if ( $huuto_item_id ) {
            // Determine the status to set on Huuto based on WooCommerce status
            $status = ( $new_status === 'publish' ) ? 'published' : 'draft';

            // Add a small delay (e.g., 1-2 seconds) to allow for initial sync
            sleep(2); 

            // Update the item's status on Huuto using the API
            $response = $this->huuto_api->update_item_status( $huuto_item_id, $status );

            // Check for errors
            if ( is_wp_error( $response ) ) {
                error_log( 'Failed to update Huuto status: ' . $response->get_error_message() );
                // Consider adding user-facing feedback here as well
            }
        }
    }
}

