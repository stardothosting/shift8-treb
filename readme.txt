=== Shift8 Real Estate Listings for TRREB ===
Contributors: shift8
Tags: real estate, listings, proptx, trreb, mlstr
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically synchronize Toronto Regional Real Estate Board (TRREB), listings via PropTx RESO Web API and create WordPress posts for property listings. TRREB was formerly called TREB (Toronto Real Estate Board).

== Description ==

Shift8 TREB Real Estate Listings is a comprehensive WordPress plugin that automates the process of importing and managing real estate listings from the Toronto Real Estate Board (TREB) via the PropTx RESO Web API. This plugin eliminates manual listing management by automatically fetching property data and creating properly formatted WordPress posts.

**[Read our detailed blog post about this plugin](https://shift8web.ca/how-to-import-trreb-proptx-real-estate-listings-into-your-wordpress-site/)** for technical insights, implementation details, and the story behind migrating from RETS to RESO Web API.

= Key Features =

* **Automated Synchronization** - Scheduled sync using WordPress cron with configurable frequency
* **PropTx RESO Web API Integration** - Secure Bearer token authentication with comprehensive error handling
* **Unlimited Image Import** - Imports ALL available photos per listing with cross-hosting batch processing
* **Universal Template System** - Compatible with all page builders (Visual Composer, Elementor, Gutenberg, Bricks)
* **Google Maps Integration** - Interactive maps with free OpenStreetMap geocoding and conditional display
* **WalkScore Integration** - Walkability scoring for properties
* **Member-Based Categorization** - Automatic categorization based on agent membership
* **Sold Listing Management** - Automatically updates existing listings to sold status with title prefix and tags
* **WP-CLI Support** - Full command-line interface for server management
* **Comprehensive Logging** - Detailed logging system with admin interface

= Perfect For =

* Real estate agencies using TREB/PropTx RESO Web API
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
4. Configure your PropTx RESO Web API bearer token and sync preferences
5. Set up your listing template with the provided placeholders
6. Run your first sync manually or wait for the scheduled sync

== External Services ==

This plugin connects to several external services to provide real estate listing functionality:

**PropTx RESO Web API (Toronto Real Estate Board)**
- **Purpose**: Retrieves real estate listing data from the Toronto Real Estate Board (TREB) MLS system
- **Data Sent**: API authentication token, search parameters, listing filters
- **When**: During scheduled syncs and manual data imports
- **Service Provider**: PropTx RESO Web API (query.ampre.ca)
- **Terms of Service**: https://www.ampre.ca/terms-of-service
- **Privacy Policy**: https://www.ampre.ca/privacy-policy

**OpenStreetMap Nominatim API**
- **Purpose**: Geocodes property addresses to obtain latitude/longitude coordinates for mapping
- **Data Sent**: Property addresses (street, city, province, postal code)
- **When**: When processing new listings or updating existing ones
- **Service Provider**: OpenStreetMap Foundation (nominatim.openstreetmap.org)
- **Usage Policy**: https://operations.osmfoundation.org/policies/nominatim/
- **Privacy Policy**: https://wiki.osmfoundation.org/wiki/Privacy_Policy

**Google Maps API (Optional)**
- **Purpose**: Displays interactive maps for property locations
- **Data Sent**: Property coordinates, API key
- **When**: When viewing individual listing pages (if Google Maps API key is configured)
- **Service Provider**: Google LLC
- **Terms of Service**: https://developers.google.com/maps/terms
- **Privacy Policy**: https://policies.google.com/privacy

**WalkScore API (Optional)**
- **Purpose**: Displays walkability scores and neighborhood information
- **Data Sent**: Property address, WalkScore ID
- **When**: When viewing individual listing pages (if WalkScore credentials are configured)
- **Service Provider**: WalkScore.com
- **Terms of Service**: https://www.walkscore.com/terms-of-use/
- **Privacy Policy**: https://www.walkscore.com/privacy/

All external service connections are made server-to-server and do not directly collect visitor data. Property addresses and coordinates are only sent to mapping services when explicitly configured by the site administrator.

== Frequently Asked Questions ==

= Do I need a PropTx RESO Web API account? =

Yes, you need a valid PropTx RESO Web API bearer token to access TREB listing data. Contact PropTx for API access credentials.

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

= 1.7.0 =
* **Transaction Type Differentiation**: Post titles now prefixed with "For Sale:", "For Lease:", or "For Sale or Lease:" to distinguish dual listings
* **Transaction Type Filtering**: Optional API filter to sync only sale listings, lease listings, or both (new setting: transaction_type_filter)
* **Weekly Cleanup Job**: Automated removal of terminated/cancelled listings with intelligent API querying (runs weekly for optimal performance)
* **Enhanced API Filtering**: Replaced ContractStatus filter with StandardStatus (Active, Pending, Closed) for better accuracy
* **Performance Optimization**: Weekly cleanup uses 1 API query vs 200 individual calls (200x reduction in API usage)
* **Improved Filtering Logic**: Added ContractStatus ne 'Unavailable' exclusion to prevent importing unavailable listings
* **Comprehensive Test Coverage**: Added 13 new tests, all 129 tests passing with 428 assertions
* **Code Coverage Improvement**: Sync Service coverage increased from 55.56% to 83.95%
* Prevents importing terminated, cancelled, expired, or withdrawn listings at the API level
* Automatically removes terminated listings from website within 7 days
* Helps agents differentiate between same-address sale vs lease listings

= 1.6.6 =
* **Documentation Enhancement**: Added prominent blog post link with technical implementation details
* **Tag Optimization**: Updated WordPress.org tags for better plugin discoverability (trreb, mlstr)
* **Improved Credits**: Added clickable link to Shift8 Web website in credits section

= 1.6.5 =
* **Branding Update**: Replaced all mentions of "AMPRE" with "PropTx RESO Web API" in documentation
* **Documentation Enhancement**: Updated all readme files to reflect the official API provider naming
* Improved consistency across user-facing documentation

= 1.6.4 =
* **Conditional Publishing**: Posts now remain as drafts if images fail to download, preventing imageless listings from appearing on site
* **Automatic Image Retry**: Failed images are automatically retried on subsequent syncs with intelligent status management
* **Auto-Publish Functionality**: Draft posts automatically publish when images become available on retry
* **WP-CLI Retry Command**: New `wp shift8-treb retry-images` command for manual retry control with progress tracking
* **Enhanced Image Handling**: Stores external image references with attempt tracking for comprehensive failure management
* **Improved User Experience**: Clear draft queue for manual review with automatic resolution of temporary image failures
* **Comprehensive Test Coverage**: Added 6 new tests covering conditional publishing, retry logic, and auto-publish scenarios
* Fixed WordPress.org plugin directory compliance issues
* Updated contributors list to 'shift8'
* Replaced inline CSS/JS with proper wp_enqueue functions for better performance
* Added comprehensive external services documentation
* Fixed transient prefixes to use proper plugin prefix (shift8_treb_)
* Created dedicated frontend CSS file for listing display
* Improved WalkScore integration to comply with WordPress standards
* Enhanced code organization and maintainability

= 1.6.3 =
* Fixed WordPress.org plugin directory compliance issues
* Updated contributors list to 'shift8'
* Replaced inline CSS/JS with proper wp_enqueue functions
* Added comprehensive external services documentation
* Fixed transient prefixes to use proper plugin prefix
* Created dedicated frontend CSS file
* Improved WalkScore integration

= 1.6.2 =
* Critical Bug Fixes: Comprehensive resolution of three major production issues
* Duplicate Image Prevention: Enhanced detection handles WordPress -1.jpg suffixes with automatic cleanup
* Geocoding Accuracy: Intelligent address cleaning preserves street name components (Upper/Lower/North/South/East/West)
* Duplicate Post Prevention: Multi-layered detection with race condition protection and proactive cleanup
* Comprehensive Test Coverage: 107 assertions covering diverse Toronto area addresses including condos, apartments, complex street names
* Enhanced Reliability: Zero-tolerance testing approach with 97 tests passing (437 total assertions)
* Performance Optimizations: Rate-limiting compliance, batch processing, enhanced error handling and debugging
* Improved duplicate detection with fallback methods (meta → tags → title search)
* Automatic attachment cleanup and orphaned image correction
* Proactive duplicate post cleanup with attachment migration

= 1.6.1 =
* Post Excerpt Template: Added customizable template system for post excerpts with full placeholder support
* HTML Support: Both listing template and excerpt template now preserve HTML formatting (using wp_kses_post sanitization)
* Enhanced Admin Interface: Excerpt template field positioned logically below listing template with shared placeholder documentation
* Improved Template Processing: Consistent placeholder replacement system across both content and excerpt templates
* Better User Experience: Templates now support rich formatting while maintaining WordPress security standards

= 1.6.0 =
* Sold Listing Management: Intelligent handling of sold/closed listings with automatic status updates
* Automatically detects sold listings using ContractStatus and StandardStatus fields from PropTx RESO Web API
* Updates existing listings to sold status with "(SOLD)" title prefix for clear identification
* Adds "Sold" tag to sold listings for easy filtering and categorization
* Skips importing new sold listings (only updates existing ones to sold status)
* Comprehensive logging of sold listing status changes for audit trail
* Enhanced test coverage with 5 new tests for sold listing functionality
* API filter inclusion for sold/closed listings to ensure status detection

= 1.5.0 =
* OpenStreetMap Rate Limiting Compliance: Strict 1-request-per-second enforcement to prevent API abuse
* Enhanced Address Geocoding: Multiple fallback strategies with intelligent unit number removal for TREB addresses
* Comprehensive Test Coverage: Expanded from 72 to 87 tests (292 assertions) with complete geocoding and API filtering validation
* Security Improvements: Enhanced input sanitization, output escaping, and robust error handling
* Enhanced Documentation: Updated development patterns and best practices for external API integration
* Prevents IP blocking and ensures sustainable OpenStreetMap usage

= 1.4.0 =
* Added --members-only CLI flag for API-level member filtering
* Dramatic performance improvement: 3 listings vs 100+ with member filtering
* Enhanced CLI experience with clear progress indicators and emoji icons
* Comprehensive help documentation with practical examples
* Flexible settings architecture with temporary CLI overrides
* Settings dependency validation for CLI flags
* Expanded development patterns in .cursorrules

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
* PropTx RESO Web API integration
* WordPress post creation
* Administrative interface
* WP-CLI command support
* Comprehensive logging system
* Automated synchronization

== Upgrade Notice ==

= 1.6.2 =
Critical update: Resolves three major production issues - duplicate images, geocoding failures, and duplicate posts. Enhanced reliability with comprehensive test coverage and zero-tolerance testing approach. Includes intelligent address cleaning and multi-layered duplicate detection.

= 1.6.1 =
New feature: Customizable post excerpt templates with full HTML support and placeholder system. Enhanced template processing for better formatting control.

= 1.6.0 =
New feature: Sold listing management automatically updates existing listings to sold status with clear identification. Enhanced API integration now includes sold/closed listings for proper status tracking.

= 1.5.0 =
Critical update: Implements OpenStreetMap rate limiting compliance to prevent API abuse and IP blocking. Enhanced geocoding accuracy with multiple fallback strategies. Comprehensive test coverage expansion ensures reliability.

= 1.4.0 =
Performance enhancement: New --members-only CLI flag provides dramatic speed improvement for member-specific operations (3 vs 100+ listings). Enhanced CLI experience with better documentation and validation.

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
* Valid PropTx RESO Web API bearer token

== Support ==

For support, documentation, and updates, visit the plugin's GitHub repository or contact Shift8 Web.

== Privacy Policy ==

This plugin connects to the PropTx RESO Web API to retrieve real estate listing data. No personal data is transmitted to external services beyond what is necessary for API authentication and data retrieval. All API credentials are encrypted and stored securely in your WordPress database.

== Credits ==

Developed by [Shift8 Web](https://shift8web.ca) for integration with the PropTx RESO Web API and Toronto Real Estate Board listing management.
