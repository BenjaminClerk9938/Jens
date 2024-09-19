# WooCommerce Huuto.net Sync

## Description

The WooCommerce Huuto.net Sync plugin allows you to synchronize your WooCommerce products with the Huuto.net online marketplace. This plugin enables easy listing of WooCommerce products on Huuto.net and keeps the product data in sync between your WooCommerce store and Huuto.net.

## Features

- Sync WooCommerce products to Huuto.net.
- Select Huuto.net categories directly from the WooCommerce product editor.
- Manage delivery methods for products listed on Huuto.net.
- Automatically sync product updates and status changes (published/draft) between WooCommerce and Huuto.net.

## Installation

1. **Upload the plugin files to the `/wp-content/plugins/woocommerce-huuto-sync` directory**, or install the plugin through the WordPress plugins screen directly.

2. **Activate the plugin** through the 'Plugins' screen in WordPress.

3. Go to **Settings > Huuto Sync** to configure the plugin with your **Huuto.net API credentials**.

4. In the WooCommerce product editor, you will see additional fields for syncing products with Huuto.net, selecting a category, and entering delivery methods.

## Usage

1. **Configure API Credentials**:

   - Go to **Settings > Huuto Sync** in your WordPress admin panel.
   - Enter your Huuto.net username and password.

2. **Sync WooCommerce Products**:

   - Go to any WooCommerce product in your store.
   - In the product editor, check the **Sync with Huuto.net** checkbox.
   - Select a **Huuto.net Category** and enter **Delivery Methods**.
   - Save the product, and it will be synced with Huuto.net.

3. **Automatic Sync**:
   - Any changes to the product (such as price, stock, or status) will automatically be updated on Huuto.net.

## Requirements

- WooCommerce
- WordPress 5.0 or higher
- PHP 7.0 or higher

## Frequently Asked Questions (FAQ)

### How do I get my Huuto.net API credentials?

To obtain your API credentials, you will need to create an account on Huuto.net and contact their support to request API access.

### What happens if a product sync fails?

If the sync to Huuto.net fails, an error will be logged in the WordPress error log. Make sure to check for any API errors or invalid product data.

## Changelog

### Version 1.0

- Initial release of the plugin.
- Basic synchronization functionality between WooCommerce and Huuto.net.

## License

This plugin is open-source and distributed under the MIT License.
