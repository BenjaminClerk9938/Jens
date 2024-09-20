<?php
/*
Plugin Name: WooCommerce Huuto.net Sync
Description: Sync your WooCommerce products with Huuto.net
Version: 1.0
Author: YanHong
*/

// Include required classes
require_once plugin_dir_path( __FILE__ ) . 'class-huuto-sync.php';
require_once plugin_dir_path( __FILE__ ) . 'class-huuto-api.php';

class WooCommerce_Huuto_Sync {
    public function __construct() {
        // Initialize Huuto Sync functionality
        new Huuto_Sync();

        // Add settings page
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    // Add a settings page for Huuto API credentials

    public function add_settings_page() {
        add_options_page(
            'Huuto Sync Settings',
            'Huuto Sync',
            'manage_options',
            'huuto-sync-settings',
            [ $this, 'create_settings_page' ]
        );
    }

    // Register settings for Huuto API

    public function register_settings() {
        register_setting( 'huuto_sync_settings', 'huuto_sync_settings' );
        add_settings_section( 'huuto_sync_settings_section', 'Huuto.net API Settings', null, 'huuto-sync-settings' );

        add_settings_field(
            'huuto_sync_username',
            'Huuto.net Username',
            [ $this, 'settings_field_callback' ],
            'huuto-sync-settings',
            'huuto_sync_settings_section',
            [ 'label_for' => 'huuto_sync_username' ]
        );

        add_settings_field(
            'huuto_sync_password',
            'Huuto.net Password',
            [ $this, 'settings_field_callback' ],
            'huuto-sync-settings',
            'huuto_sync_settings_section',
            [ 'label_for' => 'huuto_sync_password', 'type' => 'password' ]
        );
    }

    // Settings field callback

    public function settings_field_callback( $args ) {
        $options = get_option( 'huuto_sync_settings' );
        $value = isset( $options[ $args[ 'label_for' ] ] ) ? esc_attr( $options[ $args[ 'label_for' ] ] ) : '';
        $type = isset( $args[ 'type' ] ) ? $args[ 'type' ] : 'text';

        echo "<input type='{$type}' id='{$args['label_for']}' name='huuto_sync_settings[{$args['label_for']}]' value='{$value}' />";
    }

    // Create settings page content

    public function create_settings_page() {
        ?>
        <div class = 'wrap'>
        <h1>Huuto Sync Settings</h1>
        <form method = 'post' action = 'options.php'>
        <?php
        settings_fields( 'huuto_sync_settings' );
        do_settings_sections( 'huuto-sync-settings' );
        submit_button();
        ?>
        </form>
        </div>
        <?php
    }
}

// Initialize the plugin
new WooCommerce_Huuto_Sync();
