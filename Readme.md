# WooCommerce Huuto Sync

**Version**: 1.0.0  
**Author**: Your Name  
**Description**: A WooCommerce plugin to sync products between WooCommerce and Huuto.net. The plugin allows store owners to select products for sync, add custom Huuto-specific information, and automatically sync product status changes between WooCommerce and Huuto. The plugin also handles automatic API token authentication.

---

## Features

- Select WooCommerce products to sync with Huuto.
- Add custom Huuto-specific product information, such as category ID, delivery methods, etc.
- Automatically sync product status changes between WooCommerce and Huuto (e.g., from draft to published).
- Automatic API token management: requests and refreshes the token automatically.
- Customize WooCommerce products with additional fields for Huuto integration.
- Sync product information such as title, price, description, and stock quantity.

## Installation

1. Download the plugin files and upload the `woocommerce-huuto-sync` folder to the `/wp-content/plugins/` directory of your WordPress installation.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Once activated, go to **Settings > Huuto Sync** to enter your Huuto.net username and password for API authentication.
4. Save your credentials, and the plugin will automatically request an API token for further API requests.

---

## Usage

### Syncing Products with Huuto

1. **Go to WooCommerce Products**: Navigate to the product page you want to sync.
2. **Enable Sync**: In the product editing page, locate the custom field labeled **Sync with Huuto** and check the box.
3. **Enter Huuto-Specific Information**: Fill in the additional Huuto-specific fields, such as:
   - **Huuto Category ID**: The category ID from Huuto where the product belongs.
   - **Huuto Delivery Methods**: Comma-separated delivery methods (e.g., "pickup, shipment").
4. **Save the Product**: Once the product is saved, it will be synced to Huuto. The plugin automatically handles the API token and sends the product data to Huuto.
5. **Automatic Syncing**: When the product's status changes in WooCommerce (e.g., from draft to published), it will automatically update on Huuto.

### API Token Management

- The plugin will automatically request an `api_token` from Huuto.net when the credentials are saved in the settings.
- The token and its expiration time are stored in the WordPress database and will be refreshed automatically when it expires.

---

## Settings Page

1. **Navigate to Settings**: Go to **Settings > Huuto Sync** in the WordPress admin.
2. **Enter Huuto.net Credentials**: Enter your Huuto.net username and password to allow the plugin to authenticate with the Huuto API.
3. **Save Settings**: Once saved, the plugin will request an API token and store it for future use.

---

## Uninstallation

1. Deactivate the plugin from the **Plugins** menu in WordPress.
2. Click **Delete**. During uninstallation, the plugin will remove all Huuto-related data from the WooCommerce products, including custom fields like `_huuto_sync`, `_huuto_category_id`, `_huuto_delivery_methods`, and `_huuto_item_id`.

---

## File Structure

