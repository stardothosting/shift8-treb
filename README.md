# Shift8 Real Estate Listings for TRREB

A WordPress plugin that synchronizes real estate listings from the Toronto Regional Real Estate Board (TRREB) via the PropTx RESO Web API and automatically creates WordPress posts for property listings. TRREB was formerly called TREB (Toronto Real Estate Board).

## Overview

This plugin replaces manual listing management by automatically fetching property data from the PropTx RESO Web API and creating properly formatted WordPress posts. It integrates seamlessly with the existing Shift8 plugin ecosystem and provides comprehensive administrative controls for managing real estate listing synchronization.

**[Read our detailed blog post about this plugin](https://shift8web.ca/how-to-import-trreb-proptx-real-estate-listings-into-your-wordpress-site/)** for technical insights, implementation details, and the story behind migrating from RETS to RESO Web API.

## Features

### Automated Synchronization
- Scheduled synchronization using WordPress cron system
- Configurable sync frequency (hourly to monthly intervals)
- Manual sync capability with real-time progress tracking
- Duplicate detection and prevention using MLS numbers

### PropTx RESO Web API Integration
- Secure Bearer token authentication with intelligent encryption handling
- Comprehensive API connection testing
- Incremental synchronization using ModificationTimestamp for efficiency
- Configurable listing age filters (days) for targeted data retrieval
- **Geographic region filtering** by postal code prefix (FSA) or city name
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
- **Listing preview** -- query API and view summary without creating posts
- **Geographic filter overrides** via `--postal-prefix` and `--city` flags
- **Sync mode management** (incremental vs age-based)

## Installation

1. Upload the plugin files to `/wp-content/plugins/shift8-treb/`
2. Activate the plugin through the WordPress admin interface
3. Navigate to Shift8 > TREB Listings in the admin menu
4. Configure your PropTx RESO Web API bearer token and sync preferences

## Configuration

### Required Settings
- **Bearer Token**: Your PropTx RESO Web API authentication token (automatically encrypted)
- **Sync Frequency**: How often to check for new listings
- **Max Listings Per Query**: API request batch size (1-1000)

### Agent Configuration
- **Member ID**: Comma-separated list of agent IDs for "Listings" category
- **Member IDs to Exclude**: Comma-separated list of agent IDs to skip entirely
- **Listing Age (days)**: Maximum age of listings to sync (1-365 days)

### Geographic Filtering
- **Filter Type**: Select "By Postal Code Prefix" or "By City" (mutually exclusive)
- **Postal Code Prefixes**: Comma-separated FSAs (e.g., `M5V,M6H,M8X`) -- validated as `[A-Z][0-9][A-Z]`
- **City Filter**: Comma-separated city names with jQuery UI Autocomplete from AMPRE Lookup API

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

All commands are registered under the `wp shift8-treb` namespace.

---

### `wp shift8-treb sync`

Run manual sync of TREB listings from the PropTx RESO Web API.

**Options:**

| Option | Description |
|--------|-------------|
| `--dry-run` | Run without creating or updating any posts. Useful for testing. |
| `--verbose` | Show detailed output including settings, sample data, and progress. |
| `--limit=<number>` | Limit the number of listings to process. Overrides the admin setting. |
| `--force` | Force sync even if the bearer token is not configured. |
| `--listing-age=<days>` | Override listing age in days. Ignores incremental sync and uses age-based filtering instead. |
| `--mls=<numbers>` | Import specific MLS number(s), comma-separated. Bypasses normal sync and fetches only these listings. Example: `W12436591,C12380184` |
| `--members-only` | Sync only listings from configured member IDs. Applies the filter at the API level for much faster queries. Requires `member_id` to be configured in settings. |
| `--postal-prefix=<prefixes>` | Override geographic filter with postal code prefixes (FSA format). Comma-separated. Example: `M5V,M6H`. Mutually exclusive with `--city`. |
| `--city=<cities>` | Override geographic filter with city names. Comma-separated, quote if spaces. Example: `"Toronto W08,Mississauga"`. Mutually exclusive with `--postal-prefix`. |
| `--skip-images` | Skip image downloads entirely. Stores external image URLs only for faster sync. |
| `--sequential-images` | Use sequential image processing instead of the default batch processing. Slower but more compatible with limited hosting environments. |

**Examples:**

```bash
wp shift8-treb sync
wp shift8-treb sync --dry-run --verbose
wp shift8-treb sync --listing-age=7 --limit=50
wp shift8-treb sync --mls=W12436591,C12380184
wp shift8-treb sync --members-only --skip-images
wp shift8-treb sync --postal-prefix=M5V,M6H
wp shift8-treb sync --city="Brampton,Oakville" --limit=20
wp shift8-treb sync --dry-run --verbose --listing-age=7 --skip-images
```

---

### `wp shift8-treb preview`

Query the API and display a summary of matching listings without creating any posts. Useful for verifying filters and settings before running a real sync.

Output includes price range and median, city breakdown, property type breakdown, and top agents.

**Options:**

| Option | Description |
|--------|-------------|
| `--limit=<number>` | Limit number of listings to fetch from the API. |
| `--listing-age=<days>` | Override listing age in days. |
| `--members-only` | Only show listings from configured member IDs. |
| `--postal-prefix=<prefixes>` | Override geographic filter with postal code prefixes. Example: `M5V,M6H`. Mutually exclusive with `--city`. |
| `--city=<cities>` | Override geographic filter with city names. Example: `"Toronto W08,Mississauga"`. Mutually exclusive with `--postal-prefix`. |
| `--format=<format>` | Output format: `table` (default) or `json`. |

**Examples:**

```bash
wp shift8-treb preview
wp shift8-treb preview --postal-prefix=M5V,M6H,M8X
wp shift8-treb preview --city="Toronto W08,Mississauga"
wp shift8-treb preview --limit=20 --members-only
wp shift8-treb preview --format=json
```

---

### `wp shift8-treb analyze`

Fetch raw API data for diagnostic analysis without creating posts. Supports searching for specific MLS numbers and showing agent breakdowns. Useful for troubleshooting sync issues or verifying which agents and listings appear in API results.

**Options:**

| Option | Description |
|--------|-------------|
| `--limit=<number>` | Maximum number of listings to analyze. Default: `50`. |
| `--search=<mls>` | Search for specific MLS number(s) in the results. Comma-separated. Example: `W12436591,C12380184` |
| `--show-agents` | Show unique agent IDs and their listing counts, with indicators for configured vs unconfigured agents. |
| `--days=<number>` | Number of days to look back. Default: `90`. |
| `--members-only` | Only analyze listings from configured member IDs. |
| `--postal-prefix=<prefixes>` | Override geographic filter with postal code prefixes. Mutually exclusive with `--city`. |
| `--city=<cities>` | Override geographic filter with city names. Mutually exclusive with `--postal-prefix`. |

**Examples:**

```bash
wp shift8-treb analyze --limit=100 --show-agents
wp shift8-treb analyze --search=W12436591,C12380184
wp shift8-treb analyze --days=30 --limit=200
wp shift8-treb analyze --city="Mississauga" --days=30
wp shift8-treb analyze --postal-prefix=M5V --show-agents
```

---

### `wp shift8-treb settings`

Display current plugin configuration. Sensitive values (bearer token) are masked.

**Options:**

| Option | Description |
|--------|-------------|
| `--format=<format>` | Output format: `table` (default), `json`, or `yaml`. |

**Examples:**

```bash
wp shift8-treb settings
wp shift8-treb settings --format=json
```

---

### `wp shift8-treb test_api`

Test the PropTx RESO Web API connection using the configured bearer token. Reports success or failure with details.

**Options:** None.

**Examples:**

```bash
wp shift8-treb test_api
```

---

### `wp shift8-treb test_media <listing_key>`

Test the Media API for a specific listing. Shows available photos including URLs, types, order, and preferred photo status.

**Arguments:**

| Argument | Description |
|----------|-------------|
| `<listing_key>` | The MLS listing key to query. Example: `W12438713` |

**Options:**

| Option | Description |
|--------|-------------|
| `--raw` | Show the full raw JSON API response. |

**Examples:**

```bash
wp shift8-treb test_media W12438713
wp shift8-treb test_media W12438713 --raw
```

---

### `wp shift8-treb sync_status`

Show current sync mode (incremental or age-based), last sync timestamp, and relevant settings. Indicates whether deleted posts would be re-imported.

**Options:** None.

**Examples:**

```bash
wp shift8-treb sync_status
```

---

### `wp shift8-treb reset_sync`

Reset the incremental sync timestamp. Forces the next sync to use age-based filtering, which re-imports listings that may have been deleted locally.

**Options:**

| Option | Description |
|--------|-------------|
| `--yes` | Skip the confirmation prompt. |

**Examples:**

```bash
wp shift8-treb reset_sync
wp shift8-treb reset_sync --yes
```

---

### `wp shift8-treb clear_logs`

Clear all plugin sync logs.

**Options:**

| Option | Description |
|--------|-------------|
| `--yes` | Skip the confirmation prompt. |

**Examples:**

```bash
wp shift8-treb clear_logs
wp shift8-treb clear_logs --yes
```

---

### `wp shift8-treb retry-images`

Retry downloading failed images for posts that have stored external image references. Posts created as drafts due to missing images will be auto-published if the retry succeeds.

**Options:**

| Option | Description |
|--------|-------------|
| `--limit=<number>` | Limit number of posts to process. Default: unlimited. |
| `--dry-run` | Show what would be processed without actually downloading anything. |
| `--status=<status>` | Only process posts with a specific status: `draft`, `publish`, or `any` (default). |

**Examples:**

```bash
wp shift8-treb retry-images
wp shift8-treb retry-images --dry-run
wp shift8-treb retry-images --status=draft
wp shift8-treb retry-images --limit=10
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
│   ├── class-shift8-treb-sync-service.php
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

### Version 1.8.0 (Current)
- **Geographic Region Filtering**: Restrict listings by postal code prefix (FSA) or city name
  - Postal Code Prefix: Uses OData `startswith(PostalCode, 'M5V')` with FSA format validation
  - City Name: Uses `City eq 'CityName'` with autocomplete from AMPRE Lookup API
  - Mutually exclusive via admin dropdown (None / Postal Prefix / City)
  - City autocomplete powered by jQuery UI with cached canonical city names (30-day cache)
  - Server-side and client-side validation against canonical city list
- **Listing Preview Command**: `wp shift8-treb preview` shows API results without creating posts
  - Includes price range/median, city breakdown, property type breakdown, and agent summary
  - Supports all CLI filter overrides (`--postal-prefix`, `--city`, `--members-only`, `--limit`)
- **CLI Code Consolidation**: Eliminated code duplication across WP-CLI commands
  - Extracted shared `build_settings_overrides()` helper for consistent CLI flag parsing
  - Added `Sync_Service::fetch_listings()` for read-only API access using the same query path as sync
  - `analyze` command now routes through Sync_Service (supports geographic filters)
- **AMPRE API Compatibility**: Removed `tolower()` from city queries (AMPRE returns 501 for OData string functions)
- **Test Coverage**: 182 tests passing with 557 assertions
  - 4 new `fetch_listings()` tests (success, missing token, API error, empty response)
  - 8 new settings sanitization tests for geographic filters
  - All city filter tests updated for direct `City eq` syntax
- **Settings Consolidation**: Eliminated duplicate settings registration; single authoritative sanitize callback

### Version 1.7.4
- **🕒 Sync Status Fix**: "Last sync" now updates even when API returns zero listings
  - Manual and scheduled syncs consistently record completion time
  - Dry-run syncs continue to leave last sync unchanged
- **🧪 Regression Tests**: Added coverage for empty results and dry-run behavior

### Version 1.7.3
- **🔄 Re-listing Detection**: Address-based duplicate detection for agent re-listings
  - Problem: Agents delete and re-list properties with NEW MLS numbers, creating duplicates
  - Solution: Secondary check by address + transaction type + agent after MLS lookup fails
  - When detected: Updates existing post with new MLS number instead of creating duplicate
  - Reduces database bloat from repeated re-listings (verified 4x duplicate reduction in testing)
- **🏠 Smart Duplicate Logic**: Intelligent matching criteria
  - Same address + same transaction type + same agent = **DUPLICATE** (update existing)
  - Same address + **DIFFERENT** transaction type = **LEGITIMATE** (keep both - dual listing)
  - Example: For Sale @ $735,000 AND For Lease @ $3,000 = TWO valid separate posts
- **📊 Transaction Type Storage**: Added `shift8_treb_transaction_type` meta field
  - Enables efficient duplicate queries
  - Fallback to title prefix parsing for legacy posts
  - Critical for distinguishing For Sale vs For Lease vs For Sale or Lease
- **🧪 Enhanced Test Coverage**: 153 tests passing with 490 assertions (+5 new tests)
  - Address-based duplicate detection scenarios
  - Different transaction types NOT detected as duplicates
  - Legacy posts fallback to title parsing
  - No match returns false
  - Transaction type stored in meta verification
- **✅ Real-World Verified**: Tested with actual duplicate examples
  - Cleaned up 4x duplicates for single property (same agent re-listed 4 times)
  - Verified dual listings correctly preserved
  - Confirmed future syncs update existing instead of creating new

### Version 1.7.2
- **🏠 TREB Display Format Enhancement**: Bedroom count now matches official TREB/realtor.ca "X+Y" format
  - Issue: Plugin displayed "2 bedrooms" while TREB/realtor.ca shows "1+1" for condos with dens
  - Solution: Implemented ITSO standard format using BedroomsAboveGrade + BedroomsBelowGrade breakdown
  - When both above and below grade bedrooms exist → Display as "X+Y" (e.g., "1+1", "5+1")
  - When only above grade exists → Display single number (e.g., "3")
  - Smart fallback to BedroomsTotal for commercial properties
- **✅ Real-World Validation**: Tested across 8 different property scenarios
  - Condo with den (1+1) - MLS C12468133 ✓ Verified in production
  - Detached house with basement bedroom (5+1) ✓ Verified from API
  - Single-level condos (1, 2, 3 bedrooms) ✓ Verified from API
  - Studio apartments (0 bedrooms), commercial properties, missing data ✓ Edge cases tested
- **🧪 Enhanced Test Coverage**: 148 tests passing with 484 assertions (+6 bedroom format tests)
  - TREB "X+Y" format scenarios (1+1, 5+1, 2+1)
  - Standard single-level properties (1, 2, 3 bedrooms)
  - Fallback to BedroomsTotal for commercial listings
  - Edge cases: zero bedrooms, NULL values, missing data
  - Real-world property type scenarios (condos, detached, townhouses)
- **📚 Official Standard Compliance**: Matches ITSO/TRREB/REALTOR.ca standard for bedroom display
  - Information Technology Systems Ontario (ITSO) mandates "+1" format for dens
  - Ensures consistency with how listings appear across all MLS systems
  - Aligns with real estate professional and buyer expectations

### Version 1.7.1
- **🔧 Critical Data Fix**: Corrected API field mappings for bathrooms and square footage
  - Issue: Data always displayed as "N/A" despite being available in API
  - Root Cause: Plugin looked for `BathroomsTotal` and `LivingArea` but API uses `BathroomsTotalInteger` and `LivingAreaRange`
  - Fixed template placeholder replacement to use correct API fields
- **🛠️ Enhanced Data Handling**: Created `format_square_footage()` helper method
  - Intelligent fallback chain: `LivingArea` (exact) → `LivingAreaRange` (range) → `BuildingAreaTotal` (alternative) → N/A
  - Handles both exact values (1,500 sq ft) and ranges (600-699 sq ft) gracefully
  - Properly handles missing and empty field values
- **📊 Additional Meta Fields**: Enhanced data storage for better extensibility
  - Added `shift8_treb_bedrooms_above_grade` for main floor bedrooms
  - Added `shift8_treb_bedrooms_below_grade` for basement bedrooms
  - Added `shift8_treb_living_area_range` for property size ranges
  - Updated `shift8_treb_bathrooms_total` to use correct API field
- **🧪 Comprehensive Test Coverage**: 142 tests passing with 468 assertions (+13 new tests)
  - Helper method tests with exact values, fallbacks, empty data, priority order
  - Meta field storage verification for all new fields
  - Edge case handling (missing data, empty strings, backward compatibility)
  - Integration tests for complete field mapping workflows
  - Real-world data from actual MLS listings used in test fixtures
- **📚 Documentation Enhancement**: Added comprehensive troubleshooting workflow to `.cursorrules`
  - `apiFieldMappingPatterns`: Best practices for mapping external API fields
  - `troubleshootingWorkflow`: 8-step systematic process for debugging missing data
  - Prevention checklist for avoiding API field issues in future development

### Version 1.7.0
- **🏷️ Transaction Type Differentiation**: Post titles now prefixed with transaction type
  - "For Sale:", "For Lease:", or "For Sale or Lease:" prefixes added automatically
  - Helps differentiate same-address properties with different transaction types
  - Especially useful for dual listings (same property for sale and lease)
- **🔍 Transaction Type Filtering**: Optional API filter for targeted sync
  - New setting: `transaction_type_filter` (For Sale, For Lease, For Sale or Lease, or All)
  - Reduces API calls and processing time when only specific listing types needed
  - Configurable via admin interface
- **🗑️ Weekly Cleanup Job**: Automated terminated listing removal
  - Intelligent API querying for terminated/cancelled/expired listings
  - Runs weekly (not daily) for optimal performance (200x reduction in API calls)
  - Single API query fetches up to 200 terminated listings vs 200 individual calls
  - Automatically removes from website within 7 days
- **⚡ Enhanced API Filtering**: Improved accuracy and performance
  - Replaced `ContractStatus` filter with `StandardStatus` (Active, Pending, Closed)
  - Added `ContractStatus ne 'Unavailable'` exclusion to prevent importing unavailable listings
  - Prevents importing terminated, cancelled, expired, or withdrawn listings at API level
- **🧪 Comprehensive Test Coverage**: 129 tests passing with 428 assertions (+13 new tests)
  - Code coverage improvement: Sync Service from 55.56% to 83.95%
  - All new functionality covered by unit and integration tests
  - Zero-tolerance testing policy maintained

### Version 1.6.6
- **📖 Documentation Enhancement**: Added blog post link
  - Prominent link to technical blog post about RETS to RESO migration
  - Provides detailed implementation insights and PropTx integration details
  - Links to: https://shift8web.ca/how-to-import-trreb-proptx-real-estate-listings-into-your-wordpress-site/
- **🏷️ Tag Optimization**: Updated WordPress.org tags for better discoverability
- **🔗 Enhanced Credits**: Added clickable link to Shift8 Web website

### Version 1.6.5
- **📝 Branding Update**: Updated all readme documentation
  - Replaced "AMPRE" references with "PropTx RESO Web API"
  - Improved documentation consistency and accuracy
  - Reflects official API provider naming convention

### Version 1.6.4
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
- PropTx RESO Web API integration
- WordPress post creation
- Administrative interface
- WP-CLI command support
- Comprehensive logging system
- Automated synchronization

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Shift8 Web](https://shift8web.ca) for integration with the PropTx RESO Web API and Toronto Real Estate Board listing management.