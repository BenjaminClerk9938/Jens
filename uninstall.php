<?php
/**
 * Uninstall WooCommerce Huuto Sync Plugin
 * This file removes all plugin-related data from the database when the plugin is uninstalled.
 */

// Exit if accessed directly or if uninstall is not triggered from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete custom fields from all products
$products = get_posts([
    'post_type' => 'product',
    'numberposts' => -1,
    'post_status' => 'any',
]);

foreach ($products as $product) {
    // Delete Huuto-related meta data
    delete_post_meta($product->ID, '_huuto_sync');
    delete_post_meta($product->ID, '_huuto_category_id');
    delete_post_meta($product->ID, '_huuto_delivery_methods');
    delete_post_meta($product->ID, '_huuto_item_id');
}

// You can also delete any custom options or settings added by the plugin if necessary.
// Example: delete_option('huuto_plugin_setting');
