=== Shift8 TREB Real Estate Listings ===
Contributors: shift8web
Tags: real estate, listings, treb, ampre, mls
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically synchronize Toronto Real Estate Board (TREB) listings via AMPRE API and create WordPress posts for property listings.

== Description ==

Shift8 TREB Real Estate Listings is a comprehensive WordPress plugin that automates the process of importing and managing real estate listings from the Toronto Real Estate Board (TREB) via the AMPRE API. This plugin eliminates manual listing management by automatically fetching property data and creating properly formatted WordPress posts.

= Key Features =

* **Automated Synchronization** - Scheduled sync using WordPress cron with configurable frequency
* **AMPRE API Integration** - Secure Bearer token authentication with comprehensive error handling
* **Unlimited Image Import** - Imports ALL available photos per listing with cross-hosting batch processing
* **Universal Template System** - Compatible with all page builders (Visual Composer, Elementor, Gutenberg, Bricks)
* **Google Maps Integration** - Interactive maps with free OpenStreetMap geocoding and conditional display
* **WalkScore Integration** - Walkability scoring for properties
* **Member-Based Categorization** - Automatic categorization based on agent membership
* **WP-CLI Support** - Full command-line interface for server management
* **Comprehensive Logging** - Detailed logging system with admin interface

= Perfect For =

* Real estate agencies using TREB/AMPRE API
* Property management companies
* Real estate agents and brokers
* WordPress developers building real estate sites
* Anyone needing automated MLS listing synchronization

= Advanced Features =

* **Incremental Synchronization** - Uses ModificationTimestamp for efficient API usage
* **Batch Image Processing** - Memory-aware processing with adaptive timeouts
* **Direct MLS Import** - Import specific listings via WP-CLI
* **API Diagnostics** - Raw API response analysis for troubleshooting
* **Sync Mode Management** - Control over incremental vs age-based synchronization
* **Security Focused** - All input sanitized, output escaped, encrypted credential storage

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/shift8-treb/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to Shift8 > TREB Listings in the admin menu
4. Configure your AMPRE API bearer token and sync preferences
5. Set up your listing template with the provided placeholders
6. Run your first sync manually or wait for the scheduled sync

== Frequently Asked Questions ==

= Do I need an AMPRE API account? =

Yes, you need a valid AMPRE API bearer token to access TREB listing data. Contact AMPRE for API access credentials.

= How many images can be imported per listing? =

The plugin imports ALL available images per listing with no artificial limits. It uses intelligent batch processing to handle large image sets efficiently.

= Is this compatible with page builders? =

Yes! The plugin uses universal placeholders that work with Visual Composer, Elementor, Gutenberg, Bricks, and any other page builder.

= Can I import specific listings? =

Yes, use the WP-CLI command: `wp shift8-treb import W12345678,C12345679` to import specific MLS numbers.

= How do I troubleshoot sync issues? =

1. Enable debug mode in plugin settings
2. Run a manual sync to generate logs
3. Check the log viewer for error messages
4. Use the API connection tester
5. Use `wp shift8-treb analyze` for raw API diagnostics

= Does this work with multisite? =

The plugin is designed for single-site installations. Multisite compatibility is not currently supported.

== Screenshots ==

1. Main settings page with API configuration and sync controls
2. Template placeholder documentation and customization options
3. Log viewer showing detailed sync progress and debugging information
4. Quick stats widget displaying sync status and listing counts

== Changelog ==

= 1.3.0 =
* Replaced Google Maps geocoding with free OpenStreetMap Nominatim API
* No API key required for geocoding (eliminates REQUEST_DENIED errors)
* Unique coordinates for each listing instead of default Toronto fallback
* Intelligent address cleaning for better geocoding accuracy
* 7-day caching for improved performance
* Google Maps API key now only needed for map display, not geocoding
* Enhanced testing for OpenStreetMap integration
* Updated documentation to clarify geocoding vs map display requirements

= 1.2.0 =
* Added Google Maps integration with conditional display
* New direct MLS import via WP-CLI
* API diagnostics for troubleshooting without full import
* Sync mode management (incremental vs age-based)
* Fixed "Clear Logs" multiple confirmation dialogs
* Enhanced test coverage (67 tests, 157 assertions)
* Improved documentation and best practices

= 1.1.0 =
* Enhanced media integration with batch processing
* Universal template system for all page builders
* Advanced image management with retry logic
* Google Maps geocoding with intelligent caching
* WalkScore integration (simplified)
* Dynamic category assignment based on agent membership
* Performance optimizations and comprehensive testing

= 1.0.0 =
* Initial release
* AMPRE API integration
* WordPress post creation
* Administrative interface
* WP-CLI command support
* Comprehensive logging system
* Automated synchronization

== Upgrade Notice ==

= 1.3.0 =
Important update: Replaced Google Maps geocoding with free OpenStreetMap for reliable, unique coordinates per listing. No API key required for geocoding.

= 1.2.0 =
Major update with Google Maps integration, direct MLS import, and enhanced diagnostics. All tests pass with zero tolerance policy.

= 1.1.0 =
Significant improvements to image processing, template system, and performance. Recommended upgrade for all users.

== Technical Requirements ==

* WordPress 5.0 or higher
* PHP 7.4 or higher
* cURL extension for API communication
* Write permissions for wp-content/uploads directory
* Valid AMPRE API bearer token

== Support ==

For support, documentation, and updates, visit the plugin's GitHub repository or contact Shift8 Web.

== Privacy Policy ==

This plugin connects to the AMPRE API to retrieve real estate listing data. No personal data is transmitted to external services beyond what is necessary for API authentication and data retrieval. All API credentials are encrypted and stored securely in your WordPress database.

== Credits ==

Developed by Shift8 Web for integration with the AMPRE API and Toronto Real Estate Board listing management.
