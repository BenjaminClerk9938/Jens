<?php

class Huuto_API {
    private $api_url = 'https://api.huuto.net/1.1/';
    private $category_endpoint = 'https://api.huuto.net/1.1/categories';
    private $username;
    private $password;
    private $response;

    public function __construct() {
        // Retrieve settings from the database
        $settings = get_option( 'huuto_sync_settings' );
        $this->username = $settings[ 'huuto_sync_username' ];
        $this->password = $settings[ 'huuto_sync_password' ];
    }

    // Get API token, request if expired or non-existent

    private function get_api_token() {

        $token = get_option( 'huuto_api_token' );
        $token_expires = get_option( 'huuto_api_token_expires' );

        // Check if token exists and is still valid
        if ( $token && $token_expires > current_time( 'timestamp' ) ) {
            return $token;
        }
        $ch = curl_init();
        // Request new token
        curl_setopt( $ch, CURLOPT_URL, $this->api_url . 'authentication' );
        curl_setopt( $ch, CURLOPT_POST, true );
        // Use POST method

        // Important: Set Content-Type header for form-data
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            'Content-Type: multipart/form-data'
        ] );

        // Create an array to hold the form data
        $post_data = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        // Set the POST fields using the array
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data );

        // Tell cURL to return the response as a string
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

        // Execute the request
        $this->response = curl_exec( $ch );
        // Check for cURL errors
        if ( $this->response === false ) {
            $error = curl_error( $ch );
            curl_close( $ch );
            return new WP_Error( 'huuto_api_error', 'cURL error: ' . $error );
        }

        // Close cURL handle
        curl_close( $ch );

        $body = json_decode( $this->response, true );
        // Use $this->response directly

        // Save token and expiration time
        if ( isset( $body[ 'authentication' ][ 'token' ][ 'id' ] ) ) {
            $token = $body[ 'authentication' ][ 'token' ][ 'id' ];
            $expires = strtotime( $body[ 'authentication' ][ 'token' ][ 'expires' ] );
            print_r( $token );

            // Save token and expiration time in WordPress options ( for future use )
            update_option( 'huuto_api_token', $token );
            update_option( 'huuto_api_token_expires', $expires );

            return $token;
        }

        return false;
    }

    // Send API request with valid token

    private function send_request( $endpoint, $method = 'GET', $body = null ) {
        $token = $this->get_api_token();

        if ( !$token ) {
            return new WP_Error( 'api_error', 'Failed to get Huuto API token' );
        }

        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-HuutoApiToken' => $token,
            ],
        ];

        if ( $body ) {
            $args[ 'body' ] = json_encode( $body );
        }

        $this->response = wp_remote_request( $endpoint, $args );

        $status_code = wp_remote_retrieve_response_code( $this->response );
        if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
            // Check for success status codes
            $error_message = wp_remote_retrieve_body( $this->response );
            return new WP_Error( 'huuto_api_error', 'Huuto API request failed. Error: ' . $error_message, array( 'status_code' => $status_code ) );
        }

        return json_decode( wp_remote_retrieve_body( $this->response ), true );
    }

    // Example method: create item

    public function create_item( $data ) {
        return $this->send_request( 'https://api.huuto.net/1.1/items/', 'POST', $data );
    }

    // Example method: update item status

    public function update_item_status( $item_id, $status ) {
        return $this->send_request( "https://api.huuto.net/1.1/items/{$item_id}", 'PUT', [ 'status' => $status ] );
    }

    // Method to get categories from Huuto

    public function get_categories() {
        $cached_categories = get_transient( 'huuto_cached_categories' );
        if ( $cached_categories !== false ) {
            return $cached_categories;
        }

        $response = wp_remote_get( $this->api_url . 'categories' );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $categories = json_decode( wp_remote_retrieve_body( $response ), true );

        $flattened_categories = [];
        foreach ( $categories[ 'categories' ] as $category ) {
            $flattened_categories[] = [
                'id' => $category[ 'id' ],
                'title' => $category[ 'title' ],
                'level' => 0,
            ];

            if ( isset( $category[ 'links' ][ 'subcategories' ] ) ) {
                $subcategories_response = wp_remote_get( $category[ 'links' ][ 'subcategories' ] );
                if ( !is_wp_error( $subcategories_response ) ) {
                    $subcategories = json_decode( wp_remote_retrieve_body( $subcategories_response ), true );
                    foreach ( $subcategories[ 'categories' ] as $subcategory ) {
                        $flattened_categories[] = [
                            'id' => $subcategory[ 'id' ],
                            'title' => $subcategory[ 'title' ],
                            'level' => 1,
                        ];

                        if ( isset( $subcategory[ 'links' ][ 'subcategories' ] ) ) {
                            $sub_subcategories_response = wp_remote_get( $subcategory[ 'links' ][ 'subcategories' ] );
                            if ( !is_wp_error( $sub_subcategories_response ) ) {
                                $sub_subcategories = json_decode( wp_remote_retrieve_body( $sub_subcategories_response ), true );
                                foreach ( $sub_subcategories[ 'categories' ] as $sub_subcategory ) {
                                    $flattened_categories[] = [
                                        'id' => $sub_subcategory[ 'id' ],
                                        'title' => $sub_subcategory[ 'title' ],
                                        'level' => 2,
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        set_transient( 'huuto_cached_categories', $flattened_categories, 24 * HOUR_IN_SECONDS );
        return $flattened_categories;
    }

    public function upload_image( $huuto_item_id, $image_url ) {
        $token = $this->get_api_token();  // Get the API token
    
        if ( !$token ) {
            return new WP_Error( 'api_error', 'Failed to get Huuto API token' );
        }
    
        // Get the image content from the URL
        $file_data = file_get_contents( $image_url );
    
        // Prepare boundary for multipart/form-data
        $boundary = wp_generate_password( 24, false );
    
        // Prepare the multipart/form-data body for image upload
        $body = "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="image"; filename="' . basename( $image_url ) . "\"\r\n";
        $body .= "Content-Type: image/jpeg\r\n\r\n";  // Assuming JPEG, adjust for PNG or others
        $body .= $file_data . "\r\n";
        $body .= "--{$boundary}--\r\n";
    
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'X-HuutoApiToken' => $token,  // Use the API token here
            ],
            'body' => $body,
        ];
    
        // Make the request to upload the image for a specific Huuto item
        $response = wp_remote_post( $this->api_url . 'items/' . $huuto_item_id . '/images', $args );
    
        // Handle response
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'image_upload_error', 'Error uploading image to Huuto.net' );
        }
    
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( ! in_array( $status_code, [200, 201], true ) ) {
            // Handle failure if the status code is not 200 or 201
            $error_message = wp_remote_retrieve_body( $response );
            return new WP_Error( 'huuto_image_upload_error', 'Image upload failed: ' . $error_message, [ 'status_code' => $status_code ] );
        }
    
        // Return success response
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
    

}
