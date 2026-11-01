# WP Dorna

[![WordPress](https://img.shields.io/badge/WordPress-5.3%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-3.0%2B-purple.svg)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/License-GPL2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.1.2-orange.svg)](https://github.com/alifallahrn/wp-dorna/releases)

A powerful WordPress plugin that seamlessly integrates WooCommerce with the Dorna platform, enabling automated product synchronization and invoice management.

## 🚀 Features

- **Automatic product sync**: price, discount and stock are pulled from Dorna every minute. A failed request never loses changes: the sync resumes from the last *successful* run.
- **Connect with one button**: sign in to Dorna, pick the business, confirm; the token is issued for this site only (a copy of the site cannot use it) and the connection shows on your Dorna dashboard with alerts.
- **Scheduled full sync**: run the full sync every night or week at the hour you pick.
- **First-run checklist**: connect, first full sync, import, nightly schedule.
- **Full sync with progress**: one slow, paced pass over all Dorna products (one request every few seconds, pausable and resumable) to make sure the store matches Dorna.
- **Invoices**: orders are sent to Dorna when they become *processing* or *completed*; failures are retried with growing pauses and shown with their reason.
- **Bulk import**: bring Dorna products that the store lacks in as drafts.
- **Per-product update**: an "update from Dorna" button on the product screen.
- **Designed admin**: a Persian, RTL admin area in the look of the Dorna panel — overview, full sync, import, orders, logs and settings.
- **Safe by default**: capability + nonce on every action, token masked and removable from the database (`WP_DORNA_API_KEY` in wp-config.php), logs without secrets.
- **Auto-updates**: built-in update checker for GitHub releases.

## 📋 Requirements

- WordPress 5.3 or higher
- WooCommerce (required; not version-tested below a current release)
- PHP 7.4 or higher
- Active Dorna platform account with API access
- SSL certificate (recommended for secure API communication)

## 🔧 Installation

### Method 1: Manual Installation

1. Download the latest release from the [releases page](https://github.com/alifallahrn/wp-dorna/releases)
2. Extract the ZIP file
3. Upload the `wp-dorna` folder to your `/wp-content/plugins/` directory
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Configure the plugin settings with your Dorna API credentials

### Method 2: WordPress Admin

1. Go to your WordPress admin panel
2. Navigate to **Plugins > Add New**
3. Upload the plugin ZIP file
4. Click **Install Now** and then **Activate**

## ⚙️ Configuration

### Initial Setup

1. After activation, open the **درنا** menu in your WordPress admin and go to **تنظیمات**
2. Press **اتصال به درنا**, sign in, choose the business and confirm (or paste a token / define `WP_DORNA_API_KEY` in `wp-config.php` under the advanced section)
3. Follow the **راه‌اندازی** checklist: first full sync, optional import, nightly sync

## 📖 Usage

### Automatic Product Synchronization

Once configured, the plugin automatically:
- Syncs price, discount and stock from Dorna to WooCommerce every minute
- Sends orders to Dorna as invoices and retries the ones that failed

## 🤝 Contributing

We welcome contributions! Please feel free to submit issues and pull requests.

## 📄 License

This plugin is licensed under the GPL2 License. See the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Ali Fallah**
- Website: [https://dornaapp.ir](https://dornaapp.ir)
- GitHub: [@alifallahrn](https://github.com/alifallahrn)

## 🆘 Support

- **Documentation**: This README and inline code comments
- **Issues**: [GitHub Issues](https://github.com/alifallahrn/wp-dorna/issues)
- **Dorna Platform**: [https://dornaapp.ir](https://dornaapp.ir)

## 🔗 Links

- [Plugin Homepage](https://github.com/alifallahrn/wp-dorna)
- [Dorna Platform](https://my.dornaapp.ir)
- [WordPress.org](https://wordpress.org/)
- [WooCommerce](https://woocommerce.com/)

---

**Made with ❤️ for the WordPress and WooCommerce community**