# Shift8 TREB Real Estate Listings

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
- `%PHONEMSG%` - Contact phone message
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

### Version 1.3.0 (Current)
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