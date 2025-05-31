=== Tyreorder API Integracija ===
Contributors: stormas
Tags: woocommerce, api, tire, tyre, stock, csv, import, inventory
Requires at least: 5.0
Tested up to: 6.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 7.2

Integrates Tyreorder API with WooCommerce. Manages per-user API credentials, fetches CSV inventory, imports stock products, and supports batch product wipes.

== Description ==

Tyreorder API Integracija connects your WooCommerce store with the Tyreorder supplier API.

Key functionalities:
- Store API credentials per WordPress user for security and flexibility.
- Fetch tyre stock data securely using the Tyreorder XML API.
- Download and cache updated CSV tyre inventory daily.
- Preview CSV rows for specific tyre codes in admin dashboard.
- Import or update WooCommerce products from cached CSV with live stock data.
- Assign imported tyres to a "Tyre" product category automatically.
- Batch product wipes with AJAX-powered UI to remove out-of-stock or all products safely, avoiding server overload.
- Admin UI split into intuitive pages: Dashboard, API Login, Product Import, and tools.

This plugin allows seamless synchronization between your WooCommerce store and Tyreorder’s inventory, streamlining stock management and product updates.

== Installation ==

1. Upload the `tyreorder-api` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the **Tyreorder > API Login** page to enter your API username and password.
4. Use the **Tyreorder Dashboard** to check stock and preview CSV rows.
5. Use the **Product Import** page to import or update products from the CSV inventory.
6. Use the batch wipe buttons on the Product Import page to clean your product catalog as needed.

== Screenshots ==

1. Tyreorder API Dashboard with stock check and CSV preview.
2. API login form for per-user credentials.
3. Product Import page with import buttons and batch wipe controls.
4. Confirmation dialogs for batch wipes with progress display.

== Frequently Asked Questions ==

= Is this plugin compatible with WooCommerce? =  
Yes, WooCommerce must be active for product import and stock management features.

= How often is the CSV inventory updated? =  
The CSV feed is fetched daily via WP Cron and cached locally. You can also manually redownload the CSV in the admin.

= Can I import only selected products? =  
Currently, you can import the first in-stock product for testing or the entire stock via the import page buttons.

= Are my Tyreorder API credentials secure? =  
Yes, credentials are stored per WordPress user and never exposed publicly.

== Changelog ==

= 1.0.0 =  
Initial public release with core API integration, CSV handling, product import, and batch wipe tools.

== Upgrade Notice ==

Upgrade carefully from prior versions; make sure to backup your database and products before running product wipes or imports.

== Plugin Directory Structure ==

    /tyreorder-api/
    ├── tyreorder-api.php           # Main loader
    ├── inc/
    │   ├── admin-menu.php          # Admin menu and pages
    │   ├── api.php                 # Tyreorder API calls + helpers
    │   ├── csv.php                 # CSV fetch, cache & preview
    │   ├── credentials.php         # Per-user credential storage
    │   ├── import.php              # Products import and media attach
    │   ├── tools.php               # Batch wipe admin tools + JS enqueue
    │   └── js/
    │       └── wipe-batch.js       # Batch wipe JS with progress & stop

---

Feel free to customize screenshots, FAQ answers, or add further sections as your plugin evolves.

If you want, I can also help you generate a minimal `readme.md` or detailed developer docs next!
