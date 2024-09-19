<?php
/**
* Plugin Name: WooCommerce Huuto Sync
* Description: Sync WooCommerce products with Huuto.
* Version: 1.0.0
* Author: Yan Hong
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'HUUTO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Include necessary files
include_once HUUTO_PLUGIN_DIR . 'includes/class-huuto-api.php';
include_once HUUTO_PLUGIN_DIR . 'includes/class-huuto-sync.php';

// Register activation hook
register_activation_hook( __FILE__, 'huuto_plugin_activate' );

function huuto_plugin_activate() {
    // Any code needed during plugin activation
}

// Register deactivation hook
register_deactivation_hook( __FILE__, 'huuto_plugin_deactivate' );

function huuto_plugin_deactivate() {
    // Any code needed during plugin deactivation
}

// Initialize the plugin
add_action( 'plugins_loaded', 'huuto_plugin_init' );

function huuto_plugin_init() {
    // Initialize sync functionality
    $huuto_sync = new Huuto_Sync();
}

// Add admin styles
add_action( 'admin_enqueue_scripts', 'huuto_admin_styles' );

function huuto_admin_styles() {
    wp_enqueue_style( 'huuto-admin-css', plugins_url( 'assets/admin.css', __FILE__ ) );
}
// Register the settings page
add_action( 'admin_menu', 'huuto_sync_add_admin_menu' );
add_action( 'admin_init', 'huuto_sync_settings_init' );

// Create settings menu item

function huuto_sync_add_admin_menu() {
    add_options_page(
        'WooCommerce Huuto Sync',    // Page title
        'Huuto Sync',                // Menu title
        'manage_options',            // Capability
        'woocommerce-huuto-sync',    // Menu slug
        'huuto_sync_options_page'    // Function to display the page
    );
}

// Register settings fields

function huuto_sync_settings_init() {
    register_setting( 'huuto_sync_options', 'huuto_sync_settings' );

    add_settings_section(
        'huuto_sync_section',
        __( 'Huuto.net API Settings', 'huuto-sync' ),
        null,
        'huuto_sync_options'
    );

    add_settings_field(
        'huuto_sync_username',
        __( 'Huuto.net Username', 'huuto-sync' ),
        'huuto_sync_username_render',
        'huuto_sync_options',
        'huuto_sync_section'
    );

    add_settings_field(
        'huuto_sync_password',
        __( 'Huuto.net Password', 'huuto-sync' ),
        'huuto_sync_password_render',
        'huuto_sync_options',
        'huuto_sync_section'
    );
}

// Render input fields

function huuto_sync_username_render() {
    $options = get_option( 'huuto_sync_settings' );
    ?>
    <input type = 'text' name = 'huuto_sync_settings[huuto_sync_username]' value = '<?php echo $options['huuto_sync_username']; ?>'>
    <?php
}

function huuto_sync_password_render() {
    $options = get_option( 'huuto_sync_settings' );
    ?>
    <input type = 'password' name = 'huuto_sync_settings[huuto_sync_password]' value = '<?php echo $options['huuto_sync_password']; ?>'>
    <?php
}

// Display settings page

function huuto_sync_options_page() {
    ?>
    <form action = 'options.php' method = 'post'>
    <h2>WooCommerce Huuto Sync</h2>
    <?php
    settings_fields( 'huuto_sync_options' );
    do_settings_sections( 'huuto_sync_options' );
    submit_button();
    ?>
    </form>
    <?php
}
