# Tyreorder API Integracija

Integrates the Tyreorder API with WooCommerce, providing seamless tyre stock checking, CSV inventory handling, and product import/update workflows.

## Features

- Per-user API credentials interface in WordPress admin.
- Connects to Tyreorder XML API to check tyre stock.
- Downloads and caches CSV tyre inventory daily via WP Cron.
- Preview CSV rows directly from admin dashboard.
- Import or update WooCommerce products from CSV with live stock and pricing.
- Automatically assigns imported tyres to a "Tyre" product category.
- Batch product wipes (all or out-of-stock) with AJAX progress and stop control to avoid server overload.
- Clean, modular codebase with separation of API, import, CSV, and admin tools.

## Installation

1. Upload the `tyreorder-api` folder to your WordPress `/wp-content/plugins/` directory.
2. Activate the plugin via the WordPress admin.
3. Navigate to **Tyreorder > API Login** and enter your API username and password.
4. Use the **Tyreorder Dashboard** to check stock or preview CSV rows.
5. Use **Product Import** to import products from the cached CSV feed.
6. Use batch wipe controls on the import page to safely clean up your products.

## Usage

- **Dashboard:** Perform stock checks by tyre code and preview CSV data.
- **API Login:** Manage your API credentials securely per user.
- **Product Import:** Import one or all stock products with real-time stock and price info.
- **Wipe Controls:** Remove out-of-stock or all products in safe batches with progress feedback.

## Screenshots

1. Dashboard view with tyre stock check and CSV preview.  
2. API Login form for entering per-user credentials.  
3. Product Import page featuring import buttons and batch wipe controls.  
4. Progress indicator and confirmation dialogs for batch wipes.

## Frequently Asked Questions

### Is WooCommerce required?  
Yes, WooCommerce must be active for product import and stock management features.

### How often is the CSV updated?  
The CSV inventory is fetched and cached daily automatically, with option for manual refresh.

### Can I import products selectively?  
You can run a test import for the first in-stock product or full import via admin buttons.

### How secure is credential storage?  
Credentials are stored per WordPress user and are never publicly exposed.

## Development

### Plugin Structure

/tyreorder-api/
├── tyreorder-api.php           # Plugin loader, loads modules and hooks
├── inc/
│   ├── admin-menu.php          # Admin menu and page callbacks
│   ├── api.php                 # API integration helpers and calls
│   ├── csv.php                 # CSV downloading, caching, preview logic
│   ├── credentials.php         # Secure per-user credential functions
│   ├── import.php              # WooCommerce product import and media handling
│   ├── tools.php               # Admin batch wipe tools and enqueue scripts
│   └── js/
│       └── wipe-batch.js       # JS for batch wipe progress and control


## Contributing

Feel free to fork and open pull requests!

## License

GPLv2 or later — see [license.txt](https://www.gnu.org/licenses/gpl-2.0.html)
