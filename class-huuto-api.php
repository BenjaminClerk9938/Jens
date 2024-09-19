<?php

class Huuto_API {
    private $api_url = 'https://api.huuto.net/1.1/';
    private $username;
    private $password;

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

        // Request new token
        $response = wp_remote_post( $this->api_url . 'authentication', [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => json_encode( [
                'username' => $this->username,
                'password' => $this->password
            ] )
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Save token and expiration time
        if ( isset( $body[ 'authentication' ][ 'token' ][ 'id' ] ) ) {
            $token = $body[ 'authentication' ][ 'token' ][ 'id' ];
            $expires = strtotime( $body[ 'authentication' ][ 'token' ][ 'expires' ] );

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

        $response = wp_remote_request( $this->api_url . $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
            // Return the WP_Error object directly
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
            // Check for success status codes
            $error_message = wp_remote_retrieve_body( $response );
            return new WP_Error( 'huuto_api_error', 'Huuto API request failed. Error: ' . $error_message, array( 'status_code' => $status_code ) );
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    // Example method: create item

    public function create_item( $data ) {
        return $this->send_request( 'items/', 'POST', $data );
    }

    // Example method: update item status

    public function update_item_status( $item_id, $status ) {
        return $this->send_request( "items/{$item_id}", 'PUT', [ 'status' => $status ] );
    }

    // Method to get categories from Huuto

    public function get_categories() {
        // Add a debug log to see if categories are fetched
        if ( is_wp_error( $response ) ) {
            error_log( 'Failed to fetch categories: ' . $response->get_error_message() );
        } else {
            error_log( 'Categories fetched: ' . print_r( $response, true ) );
        }
        return $this->send_request( 'categories/', 'GET' );
    }
}
