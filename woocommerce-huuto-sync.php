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

add_action( 'manage_product_posts_custom_column', 'populate_huuto_product_id_column', 10, 2 );

function populate_huuto_product_id_column( $column, $post_id ) {
    if ( 'huuto_product_id' === $column ) {
        $huuto_product_id = get_post_meta( $post_id, '_huuto_item_id', true );
        if ( $huuto_product_id ) {
            echo esc_html( $huuto_product_id );
        } else {
            echo '<span style="color:gray;">' . __( 'Not Synced', 'huuto-sync' ) . '</span>';
        }
    }
}

/**
* Make the Huuto.net Product ID column sortable.
*/
add_filter( 'manage_edit-product_sortable_columns', 'make_huuto_product_id_column_sortable' );

function make_huuto_product_id_column_sortable( $sortable_columns ) {
    $sortable_columns[ 'huuto_product_id' ] = 'huuto_product_id';
    return $sortable_columns;
}

/**
* Handle sorting by Huuto.net Product ID in the WooCommerce products table.
*/
add_action( 'pre_get_posts', 'sort_by_huuto_product_id' );

function sort_by_huuto_product_id( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( 'huuto_product_id' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', '_huuto_item_id' );
        $query->set( 'orderby', 'meta_value' );
    }
}

class WooCommerce_Huuto_Sync {
    public function __construct() {
        // Initialize Huuto Sync functionality
        new Huuto_Sync();

        // Add settings page
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_ajax_scripts' ] );
    }

    public function enqueue_ajax_scripts() {
        if ( get_current_screen()->post_type === 'product' ) {
            // Enqueue the custom AJAX script
            wp_enqueue_script( 'huuto-ajax', plugin_dir_url( __FILE__ ) . 'assets/js/ajax-huuto.js', [ 'jquery' ], '1.0', true );

            // Pass data to the JavaScript file
            wp_localize_script( 'huuto-ajax', 'huuto_ajax_obj', [
                'nonce' => wp_create_nonce( 'huuto_ajax_nonce' )  // Create a security nonce
            ] );
        }
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
