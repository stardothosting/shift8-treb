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
- Secure Bearer token authentication
- Comprehensive API connection testing
- Configurable listing filters (status, city, property type, price range)
- Rate limiting and error handling

### Content Management
- Creates standard WordPress posts (not custom post types)
- Customizable listing templates with placeholder support
- Automatic image downloading and featured image assignment
- Proper categorization (Listings/OtherListings) based on agent association
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

## Installation

1. Upload the plugin files to `/wp-content/plugins/shift8-treb/`
2. Activate the plugin through the WordPress admin interface
3. Navigate to Shift8 > TREB Listings in the admin menu
4. Configure your AMPRE API bearer token and sync preferences

## Configuration

### Required Settings
- **Bearer Token**: Your AMPRE API authentication token
- **Sync Frequency**: How often to check for new listings
- **Max Listings Per Query**: API request batch size (1-1000)

### Optional Settings
- **Google Maps API Key**: For enhanced mapping features
- **Listing Filters**: Status, city, property type, price range
- **Listing Template**: Customizable post content template
- **Debug Mode**: Enable detailed logging for troubleshooting

### Template Placeholders
The listing template supports the following placeholders:
- `%ADDRESS%` - Full property address
- `%PRICE%` - Listing price
- `%MLS%` - MLS number
- `%BEDROOMS%` - Number of bedrooms
- `%BATHROOMS%` - Number of bathrooms
- `%SQFT%` - Square footage
- `%DESCRIPTION%` - Property description
- `%PROPERTY_TYPE%` - Property type
- `%CITY%` - City name
- `%POSTAL_CODE%` - Postal code

## WP-CLI Commands

The plugin provides comprehensive command-line interface support:

### Sync Listings
```bash
# Run normal sync
wp shift8-treb sync

# Test sync without creating posts
wp shift8-treb sync --dry-run --verbose

# Limit processing to specific number of listings
wp shift8-treb sync --limit=50

# Force sync even without configured token
wp shift8-treb sync --force
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
wp shift8-treb test_api
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
- Plugin settings stored in WordPress options table
- Sensitive data (API tokens) encrypted using WordPress salts
- Logs stored in wp-content/uploads/shift8-treb-logs/
- Images downloaded to wp-content/uploads/treb/{mlsnumber}/

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