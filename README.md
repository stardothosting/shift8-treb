# Shift8 Real Estate Listings for TREB

A WordPress plugin that synchronizes real estate listings from the Toronto Real Estate Board (TREB) via the AMPRE API and automatically creates WordPress posts for property listings.

## Overview

This plugin replaces manual listing management by automatically fetching property data from the AMPRE API and creating properly formatted WordPress posts. It integrates seamlessly with the existing Shift8 plugin ecosystem and provides comprehensive administrative controls for managing real estate listing synchronization.

## Features

### Automated Synchronization
- Scheduled synchronization using WordPress cron system
- Configurable sync frequency (hourly to monthly intervals)
- Manual sync capability with real-time progress tracking
- Duplicate detection and prevention using MLS numbers

### AMPRE API Integration
- Secure Bearer token authentication with intelligent encryption handling
- Comprehensive API connection testing
- Incremental synchronization using ModificationTimestamp for efficiency
- Configurable listing age filters (days) for targeted data retrieval
- Member-based categorization supporting multiple agent IDs
- Exclusion filters for unwanted agent listings
- Rate limiting and comprehensive error handling

### Content Management
- Creates standard WordPress posts (not custom post types)
- Universal template system compatible with all page builders (Visual Composer, Elementor, Gutenberg, Bricks)
- **Unlimited image import** - imports ALL available photos per listing (no artificial limits)
- **Cross-hosting compatible batch processing** with adaptive sizing and timeouts
- Intelligent image processing with retry logic and external URL fallbacks
- Featured image assignment with intelligent priority (first image or preferred photo)
- Proper post_parent linking for all media attachments
- Dynamic categorization (Listings/OtherListings) based on agent membership
- **Sold listing management** - automatically updates existing listings to sold status with title prefix and tags
- SEO-friendly post structure with proper excerpts and metadata

### Administrative Interface
- Integrated settings page under Shift8 main menu
- Real-time API connection testing
- Comprehensive logging system with log viewer
- Manual sync controls with progress feedback
- Settings validation and error reporting

### Command Line Interface
- Full WP-CLI integration for server management
- Dry-run capability for testing configurations
- Verbose output options for debugging
- Batch processing with progress indicators
- **Direct MLS import** for specific listings
- **Raw API diagnostics** for troubleshooting
- **Sync mode management** (incremental vs age-based)

## Installation

1. Upload the plugin files to `/wp-content/plugins/shift8-treb/`
2. Activate the plugin through the WordPress admin interface
3. Navigate to Shift8 > TREB Listings in the admin menu
4. Configure your AMPRE API bearer token and sync preferences

## Configuration

### Required Settings
- **Bearer Token**: Your AMPRE API authentication token (automatically encrypted)
- **Sync Frequency**: How often to check for new listings
- **Max Listings Per Query**: API request batch size (1-1000)

### Agent Configuration
- **Member ID**: Comma-separated list of agent IDs for "Listings" category
- **Member IDs to Exclude**: Comma-separated list of agent IDs to skip entirely
- **Listing Age (days)**: Maximum age of listings to sync (1-365 days)

### Optional Integrations
- **Google Maps API Key**: For displaying interactive maps (geocoding now uses free OpenStreetMap)
- **WalkScore API Key**: For walkability scoring integration
- **WalkScore ID**: Widget ID for WalkScore integration
- **Listing Template**: Customizable post content template with universal placeholders
- **Debug Mode**: Enable detailed logging for troubleshooting

### Template Placeholders

#### Property Information
- `%ADDRESS%` - Full property address
- `%LISTPRICE%` - Formatted listing price
- `%MLSNUMBER%` - MLS number
- `%BEDROOMS%` - Number of bedrooms
- `%BATHROOMS%` - Number of bathrooms
- `%SQFOOTAGE%` - Square footage
- `%DESCRIPTION%` - Property description
- `%STREETNUMBER%` - Parsed street number
- `%STREETNAME%` - Parsed street name
- `%APT_NUM%` - Unit/apartment number

#### Universal Image Placeholders
- `%FEATURED_IMAGE%` - WordPress featured image HTML
- `%IMAGE_GALLERY%` - WordPress gallery shortcode
- `%LISTING_IMAGES%` - Comma-separated image URLs
- `%LISTING_IMAGE_1%` to `%LISTING_IMAGE_10%` - Individual image URLs
- `%BASE64IMAGES%` - Base64 encoded URLs (Visual Composer compatible)

#### Location & Mapping
- `%MAPLAT%` - Latitude coordinate (OpenStreetMap geocoding with Toronto fallback)
- `%MAPLNG%` - Longitude coordinate (OpenStreetMap geocoding with Toronto fallback)
- `%WALKSCORECODE%` - WalkScore widget (if configured)

#### Additional Features
- `%GOOGLEMAPCODE%` - Google Maps widget (requires API key)
- `%VIRTUALTOUR%` - Virtual tour link (if available)
- `%WPBLOG%` - WordPress site URL

## WP-CLI Commands

The plugin provides comprehensive command-line interface support:

### Sync Listings
```bash
# Run normal sync
wp shift8-treb sync

# Test sync without creating posts
wp shift8-treb sync --dry-run --verbose

# Override listing age filter (in days)
wp shift8-treb sync --listing-age=30

# Skip image downloads for faster sync
wp shift8-treb sync --skip-images

# Use sequential processing instead of default batch processing (for compatibility)
wp shift8-treb sync --sequential-images

# Combine options for testing
wp shift8-treb sync --dry-run --verbose --listing-age=7 --skip-images
```

### View Settings
```bash
# Display current configuration
wp shift8-treb settings

# Export settings as JSON
wp shift8-treb settings --format=json
```

### Test API Connection
```bash
# Test main API connection
wp shift8-treb test_api

# Test media API for specific listing
wp shift8-treb test_media W12345678

# Show raw JSON response
wp shift8-treb test_media W12345678 --raw
```

### Manage Logs
```bash
# View recent log entries
wp shift8-treb logs

# Show only error entries
wp shift8-treb logs --level=error

# Clear all logs
wp shift8-treb clear_logs --yes
```

### Direct MLS Import
```bash
# Import specific MLS numbers
wp shift8-treb import W12345678,C12345679

# Import with dry-run
wp shift8-treb import W12345678 --dry-run

# Import with verbose output
wp shift8-treb import W12345678 --verbose
```

### API Diagnostics
```bash
# Analyze raw API response for diagnostics
wp shift8-treb analyze --limit=100 --show-agents

# Search for specific MLS numbers
wp shift8-treb analyze --search=W12345678,C12345679

# Check listings from last 30 days
wp shift8-treb analyze --days=30 --limit=200
```

### Sync Mode Management
```bash
# Check current sync status
wp shift8-treb sync_status

# Reset incremental sync (forces age-based sync)
wp shift8-treb reset_sync

# Reset with confirmation skip
wp shift8-treb reset_sync --yes
```

## Technical Details

### System Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- cURL extension for API communication
- Write permissions for wp-content/uploads directory

### File Structure
```
shift8-treb/
├── admin/
│   ├── class-shift8-treb-admin.php
│   ├── partials/
│   │   └── settings-page.php
│   ├── css/
│   └── js/
├── includes/
│   ├── class-shift8-treb-ampre-service.php
│   ├── class-shift8-treb-post-manager.php
│   └── class-shift8-treb-cli.php
├── languages/
└── shift8-treb.php
```

### Data Storage
- Plugin settings stored in WordPress options table with automatic migration
- Sensitive data (API tokens) encrypted using WordPress salts with intelligent handling
- Logs stored in wp-content/uploads/shift8-treb-logs/ with automatic rotation
- Images integrated into WordPress media library with proper metadata
- Geocoding results cached using WordPress transients (7-day expiration for OpenStreetMap)
- Incremental sync timestamps tracked for efficient API usage

### Security Features
- All user input sanitized and validated
- Nonce verification for all AJAX requests
- Encrypted storage of sensitive credentials
- Protected log directory with .htaccess
- WordPress capability checks for all administrative functions

## Logging and Debugging

### Log Levels
- **Info**: General operational messages and successful operations
- **Warning**: Non-fatal issues that should be monitored
- **Error**: Fatal errors and exceptions that prevent operation

### Log Management
- Automatic log rotation when files exceed 5MB
- Configurable log retention (keeps last 5 backup files)
- Real-time log viewing through admin interface
- Command-line log access via WP-CLI

### Troubleshooting
1. Enable debug mode in plugin settings
2. Run manual sync to generate detailed logs
3. Check log viewer for specific error messages
4. Test API connection using built-in connection tester
5. Verify WordPress cron is functioning properly

## Integration with Shift8 Ecosystem

This plugin is designed to work seamlessly with other Shift8 plugins:
- Shares common administrative menu structure
- Follows established coding standards and security practices
- Compatible with existing Shift8 plugin configurations
- Maintains consistent user interface patterns

## Support and Development

### WordPress.org Compliance
This plugin is built to meet WordPress.org plugin directory standards:
- All output properly escaped
- Input sanitization and nonce verification
- No direct file operations (uses WP_Filesystem API)
- Proper internationalization support
- Clean uninstall process

### Performance Considerations
- Efficient API request batching
- Proper WordPress cron integration
- Optimized database queries
- Image optimization and proper storage
- Minimal impact on site performance

## Changelog

### Version 1.6.4 (Current)
- **📸 Conditional Publishing**: Intelligent image-based post status management
  - Posts remain as drafts if images fail to download
  - Prevents imageless listings from appearing on site
  - Automatic resolution when images become available
- **🔄 Automatic Image Retry**: Smart failure recovery system
  - Failed images automatically retried on subsequent syncs
  - External image references stored with attempt tracking
  - Comprehensive failure management and logging
- **🚀 Auto-Publish Functionality**: Draft posts automatically publish when images succeed
  - Status transitions logged for debugging
  - Clear draft queue for manual review
  - Improved user experience with automatic resolution
- **⚙️ WP-CLI Retry Command**: Manual control over image retry process
  - `wp shift8-treb retry-images` with progress tracking
  - Filter by post status (draft/publish/any)
  - Dry-run mode and limit options
  - Detailed statistics and summary output
- **🧪 Enhanced Test Coverage**: 116 tests passing (382 assertions)
  - 6 new tests for conditional publishing scenarios
  - Retry logic and auto-publish validation
  - Comprehensive mocking for WordPress functions
- **🔧 WordPress.org Compliance**: Plugin directory standards
  - Fixed contributors list and external services documentation
  - Proper enqueue functions for CSS/JS assets
  - Enhanced code organization and maintainability

### Version 1.6.3
- **🔧 WordPress.org Compliance**: Plugin directory standards
  - Fixed contributors list to 'shift8'
  - Added comprehensive external services documentation
  - Replaced inline CSS/JS with proper wp_enqueue functions
  - Fixed transient prefixes to use proper plugin prefix
  - Created dedicated frontend CSS file
  - Improved WalkScore integration

### Version 1.6.2
- **🔧 Critical Bug Fixes**: Comprehensive resolution of three major issues
  - **Duplicate Image Prevention**: Enhanced detection handles WordPress `-1.jpg` suffixes with automatic cleanup
  - **Geocoding Accuracy**: Intelligent address cleaning preserves street name components (Upper/Lower/North/South/East/West)
  - **Duplicate Post Prevention**: Multi-layered detection with race condition protection and proactive cleanup
- **🧪 Comprehensive Test Coverage**: 107 assertions covering diverse Toronto area addresses
  - Real-world address patterns: condos, apartments, complex street names, directional components
  - Edge cases: hyphenated streets, apostrophes, multiple directional words, Toronto area codes
  - Robust validation ensures fixes prevent future regressions
- **🛡️ Enhanced Reliability**: Zero-tolerance testing approach with 97 tests passing (437 assertions)
  - Improved duplicate detection with fallback methods (meta → tags → title search)
  - Automatic attachment cleanup and orphaned image correction
  - Proactive duplicate post cleanup with attachment migration
- **⚡ Performance Optimizations**: Intelligent processing with comprehensive logging
  - Rate-limiting compliance for OpenStreetMap geocoding
  - Batch image processing with duplicate prevention
  - Enhanced error handling and debugging capabilities

### Version 1.6.1
- **📝 Post Excerpt Template System**: Customizable template for WordPress post excerpts
  - Full placeholder support identical to main listing template
  - HTML formatting support with wp_kses_post sanitization
  - Positioned logically in admin interface below listing template
  - Consistent template processing across content and excerpts
- **🎨 Enhanced HTML Support**: Both templates now preserve HTML formatting
  - Safe HTML tags allowed: `<p>`, `<br>`, `<strong>`, `<em>`, `<a>`, `<div>`, etc.
  - WordPress security standards maintained with wp_kses_post
  - Rich formatting capabilities for better content presentation
- **🔧 Improved Template Processing**: Unified placeholder replacement system
  - Consistent behavior between listing content and excerpt generation
  - Better error handling and sanitization
  - Enhanced user experience with logical field organization

### Version 1.6.0
- **🏷️ Sold Listing Management**: Intelligent handling of sold/closed listings
  - Automatically detects sold listings using ContractStatus and StandardStatus fields
  - Updates existing listings to sold status with "(SOLD)" title prefix
  - Adds "Sold" tag to sold listings for easy filtering
  - Skips importing new sold listings (only updates existing ones)
  - Comprehensive logging of sold listing status changes
- **🧪 Enhanced Test Coverage**: Added 5 new tests for sold listing functionality
  - Sold listing detection from API responses
  - Post sold status detection and validation
  - Complete sold listing workflow testing
  - API filter inclusion for sold/closed listings

### Version 1.5.0
- **🛡️ OpenStreetMap Rate Limiting Compliance**: Comprehensive implementation to prevent API abuse
  - Strict 1-request-per-second rate limiting using WordPress transients
  - Automatic sleep() enforcement with detailed logging
  - Enhanced 429 (Too Many Requests) error handling with exponential backoff
  - Prevents IP blocking and ensures sustainable API usage
- **🎯 Enhanced Address Geocoding**: Multiple fallback strategies for improved accuracy
  - Multiple address cleaning variations (aggressive, conservative, basic)
  - Intelligent unit number removal for TREB-specific address formats
  - Automatic Canada suffix addition for better OpenStreetMap results
  - Smart caching: 7-day success cache, 1-hour failure cache
- **🧪 Comprehensive Test Coverage**: Expanded from 72 to 87 tests (292 assertions)
  - Complete OpenStreetMap geocoding test suite (9 new tests)
  - Members-only API filtering validation (6 new tests)
  - Rate limiting, error handling, and edge case coverage
  - Security testing for input sanitization and SQL injection prevention
- **📚 Enhanced Documentation**: Updated .cursorrules with new patterns
  - OpenStreetMap integration best practices
  - External API rate limiting patterns
  - Geocoding error handling strategies
  - Testing considerations for WordPress constants
- **🔒 Security Improvements**: Comprehensive input validation and output escaping
  - All geocoding inputs properly sanitized
  - Enhanced error logging with context data
  - Robust handling of malformed API responses

### Version 1.4.0
- **🎯 API-Level Member Filtering**: New `--members-only` CLI flag for efficient member-specific sync
  - Filters `ListAgentKey` at API level instead of client-side (3 listings vs 100+)
  - Dramatic performance improvement for member-focused operations
  - Validates member ID configuration before allowing flag usage
  - Proper OData filter syntax: `(ListAgentKey eq 'ID1' or ListAgentKey eq 'ID2')`
- **🚀 Enhanced CLI Experience**: Improved user experience and documentation
  - Clear progress indicators with emoji icons (🎯 for targeting, ⚡ for performance)
  - Comprehensive help documentation with practical examples
  - Actionable error messages with validation feedback
- **⚙️ Flexible Settings Architecture**: Robust settings override system for CLI operations
  - Temporary CLI overrides without affecting stored settings
  - Settings dependency validation for CLI flags
  - Transparent settings display in verbose mode
- **📚 Expanded .cursorrules**: Added new patterns for future development
  - API-level filtering best practices
  - CLI user experience patterns
  - Settings architecture patterns

### Version 1.3.0
- **🗺️ OpenStreetMap Geocoding**: Replaced Google Maps geocoding with free OpenStreetMap Nominatim API
  - No API key required for geocoding (eliminates REQUEST_DENIED errors)
  - Unique coordinates for each listing instead of default Toronto fallback
  - Intelligent address cleaning for better geocoding accuracy
  - 7-day caching for improved performance
  - Google Maps API key now only needed for map display, not geocoding
- **🧪 Enhanced Testing**: Updated test suite for OpenStreetMap integration
- **📚 Updated Documentation**: Clarified geocoding vs map display API requirements

### Version 1.2.0
- **🚀 Unlimited Image Import**: Removed 5-image limit - now imports ALL available photos per listing
- **⚡ Cross-Hosting Batch Processing**: Default batch processing with adaptive sizing based on hosting environment
  - Memory-aware batch sizing (2-8 images per batch)
  - Adaptive timeouts (5-12 seconds) based on execution limits
  - Intelligent delays between batches for server compatibility
- **🔗 Enhanced Media Management**: Fixed post_parent linking with debugging and auto-correction
- **🗺️ Google Maps Integration**: Interactive maps with conditional display and WordPress best practices
  - Custom HTML placeholder: `%GOOGLEMAPCODE%`
  - Unique function naming following WordPress conventions
  - Only displays on single listing posts to avoid conflicts
- **🔧 Direct MLS Import**: New WP-CLI command for importing specific MLS numbers
- **📊 API Diagnostics**: Raw API response analysis for troubleshooting without full import
- **⚙️ Sync Mode Management**: Control over incremental vs age-based synchronization
  - `wp shift8-treb sync_status` - View current sync mode and settings
  - `wp shift8-treb reset_sync` - Reset incremental sync timestamp
- **🐛 Bug Fixes**: Fixed "Clear Logs" multiple confirmation dialogs
- **🧪 Comprehensive Test Coverage**: 67 tests with 157 assertions - zero tolerance policy maintained
- **📚 Updated Documentation**: Enhanced .cursorrules with performance patterns and best practices

### Version 1.1.0
- **Enhanced Media Integration**: Complete WordPress media library integration with batch processing
- **Universal Template System**: Page builder agnostic placeholders supporting Visual Composer, Elementor, Gutenberg, Bricks
- **Advanced Image Management**: Featured image priority, retry logic, external URL fallbacks
- **Google Maps Integration**: Geocoding with intelligent caching and fallback coordinates
- **WalkScore Integration**: Conditional widget generation (simplified - no API key required, just ID)
- **Improved Categorization**: Dynamic category assignment based on agent membership
- **Performance Optimizations**: Batch image processing, incremental sync, optimized timeouts
- **Enhanced CLI**: New commands for media testing, listing age overrides, image processing options
- **Comprehensive Testing**: Zero-tolerance test suite with 62 passing tests

### Version 1.0.0
- Initial release
- AMPRE API integration
- WordPress post creation
- Administrative interface
- WP-CLI command support
- Comprehensive logging system
- Automated synchronization

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Shift8 Web for integration with the AMPRE API and Toronto Real Estate Board listing management.