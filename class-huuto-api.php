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
        $url = 'https://api.huuto.net/1.1/categories';
        $response = wp_remote_get( $url );

        // Handle API response errors
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $categories = json_decode( $body, true );

        // Handle JSON decoding errors
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', 'Error decoding JSON response from Huuto.net.' );
        }

        // Loop through main categories and fetch subcategories if available
        foreach ( $categories[ 'categories' ] as &$category ) {
            if ( isset( $category[ 'links' ][ 'subcategories' ] ) ) {
                $subcategories_url = $category[ 'links' ][ 'subcategories' ];
                $sub_response = wp_remote_get( $subcategories_url );

                if ( !is_wp_error( $sub_response ) ) {
                    $sub_body = wp_remote_retrieve_body( $sub_response );
                    $subcategories = json_decode( $sub_body, true );

                    if ( isset( $subcategories[ 'categories' ] ) ) {
                        $category[ 'subcategories' ] = $subcategories[ 'categories' ];
                        // Attach subcategories to the category
                    }
                }
            }
        }

        return $categories;
    }
}
