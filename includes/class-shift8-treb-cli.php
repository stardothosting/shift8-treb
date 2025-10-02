<?php
/**
 * WP-CLI Commands for Shift8 TREB Plugin (Refactored)
 *
 * @package Shift8_TREB
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI commands for TREB listing management
 *
 * @since 1.1.0
 */
class Shift8_TREB_CLI {

    /**
     * Run manual sync of TREB listings
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Run without actually creating/updating posts
     *
     * [--verbose]
     * : Show detailed output
     *
     * [--limit=<number>]
     * : Limit number of listings to process (overrides settings)
     *
     * [--force]
     * : Force sync even if bearer token is not configured
     *
     * [--listing-age=<days>]
     * : Override listing age in days (ignores incremental sync)
     *
     * [--skip-images]
     * : Skip image downloads for faster sync (stores external URLs only)
     *
     * [--sequential-images]
     * : Use sequential processing instead of default batch processing (slower but more compatible)
     *
     * ## EXAMPLES
     *
     *     # Run normal sync
     *     wp shift8-treb sync
     *
     *     # Run dry-run to see what would happen
     *     wp shift8-treb sync --dry-run
     *
     *     # Run with verbose output
     *     wp shift8-treb sync --verbose
     *
     *     # Limit to 10 listings
     *     wp shift8-treb sync --limit=10
     *
     *     # Override listing age (ignores incremental sync)
     *     wp shift8-treb sync --listing-age=7
     *
     * @when after_wp_load
     */
    public function sync($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $verbose = isset($assoc_args['verbose']);
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : null;
        $force = isset($assoc_args['force']);
        $listing_age = isset($assoc_args['listing-age']) ? intval($assoc_args['listing-age']) : null;

        WP_CLI::line('=== Shift8 TREB Manual Sync ===');
        
        if ($dry_run) {
            WP_CLI::warning('DRY RUN MODE - No posts will be created or updated');
        }

        try {
            // Prepare settings overrides for CLI
            $settings_overrides = array();
            
            // Check bearer token before proceeding
            $base_settings = get_option('shift8_treb_settings', array());
            if (empty($base_settings['bearer_token']) && !$force) {
                WP_CLI::error('Bearer token not configured. Use --force to override or configure in admin settings.');
            }

            // Handle listing age override
            if ($listing_age !== null) {
                $settings_overrides['listing_age_days'] = $listing_age;
                $settings_overrides['last_sync_timestamp'] = null; // Force age-based filtering
                if ($verbose) {
                    WP_CLI::line("Using listing age override: {$listing_age} days");
                }
            } else {
                // For manual CLI sync, always use listing age instead of incremental sync
                $settings_overrides['last_sync_timestamp'] = null;
                if ($verbose) {
                    $listing_age_days = $base_settings['listing_age_days'] ?? 30;
                    WP_CLI::line("Using WordPress setting: {$listing_age_days} days (manual sync ignores incremental)");
                }
            }

            // Handle image processing options
            if (isset($assoc_args['skip-images'])) {
                $settings_overrides['skip_image_download'] = true;
                if ($verbose) {
                    WP_CLI::line("🚀 Fast sync mode: Skipping image downloads (storing external URLs only)");
                }
            }

            if (isset($assoc_args['sequential-images'])) {
                $settings_overrides['batch_image_processing'] = false;
                if ($verbose) {
                    WP_CLI::line("🐌 Sequential mode: Using sequential image processing (slower but more compatible)");
                }
            } else {
                // Batch processing is now the default
                if ($verbose) {
                    WP_CLI::line("⚡ Batch mode: Using optimized batch image processing (default)");
                }
            }

            // Include and initialize sync service
            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
            $sync_service = new Shift8_TREB_Sync_Service($settings_overrides);
            $settings = $sync_service->get_settings();

            if ($verbose) {
                WP_CLI::line('Settings:');
                WP_CLI::line('  Bearer Token: ' . (empty($settings['bearer_token']) ? 'Not set' : 'Set'));
                WP_CLI::line('  Sync Frequency: ' . ($settings['sync_frequency'] ?? 'daily'));
                WP_CLI::line('  Max Listings Per Query: ' . ($settings['max_listings_per_query'] ?? 100));
                WP_CLI::line('  Member ID: ' . ($settings['member_id'] ?? 'Not set'));
                WP_CLI::line('  Excluded Member IDs: ' . ($settings['excluded_member_ids'] ?? 'None'));
                WP_CLI::line('  Listing Age (days): ' . ($settings['listing_age_days'] ?? 30));
                WP_CLI::line('');
            }

            // Test API connection first
            WP_CLI::line('Testing API connection...');
            $connection_test = $sync_service->test_connection();
            
            if (!$connection_test['success']) {
                WP_CLI::error('API connection failed: ' . $connection_test['message']);
            }
            
            WP_CLI::success('API connection successful');

            // Execute sync using the sync service
            WP_CLI::line('Executing sync...');
            
            $sync_options = array(
                'dry_run' => $dry_run,
                'verbose' => $verbose,
                'limit' => $limit
            );

            $results = $sync_service->execute_sync($sync_options);

            if (!$results['success']) {
                WP_CLI::error($results['message']);
            }

            // Display results
            if ($results['total_listings'] > 0) {
                WP_CLI::line("Found {$results['total_listings']} listings");
                
                if ($limit && $limit > 0) {
                    WP_CLI::line("Limited to {$limit} listings for processing");
                }

                // Show sample data in verbose mode
                if ($verbose && !empty($results['listings'])) {
                    WP_CLI::line('Sample listing data:');
                    $sample = $results['listings'][0];
                    WP_CLI::line('- ListingKey: ' . ($sample['ListingKey'] ?? 'N/A'));
                    WP_CLI::line('- Address: ' . ($sample['UnparsedAddress'] ?? 'N/A'));
                    WP_CLI::line('- Price: $' . number_format($sample['ListPrice'] ?? 0));
                    WP_CLI::line('- Status: ' . ($sample['StandardStatus'] ?? 'N/A'));
                    WP_CLI::line('');
                    
                    // Show progress bar
                    $progress = \WP_CLI\Utils\make_progress_bar('Processing listings', count($results['listings']));
                    $progress->finish();
                }
            } else {
                WP_CLI::warning('No listings returned from API');
                return;
            }

            // Show summary
            WP_CLI::line('');
            WP_CLI::line('Summary:');
            WP_CLI::line("  Total listings: {$results['total_listings']}");
            WP_CLI::line("  Processed: {$results['processed']}");
            WP_CLI::line("  Created: {$results['created']}");
            WP_CLI::line("  Updated: {$results['updated']}");
            WP_CLI::line("  Skipped: {$results['skipped']}");
            WP_CLI::line("  Errors: {$results['errors']}");

            if ($results['errors'] > 0) {
                WP_CLI::warning(sprintf('Sync completed with %d errors. Check logs for details.', $results['errors']));
            } else {
                WP_CLI::success('Sync completed successfully!');
            }

        } catch (Exception $e) {
            shift8_treb_log('WP-CLI sync failed', array('error' => esc_html($e->getMessage())), 'error');
            WP_CLI::error('Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Show current plugin settings
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp shift8-treb settings
     *     wp shift8-treb settings --format=json
     *
     * @when after_wp_load
     */
    public function settings($args, $assoc_args) {
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        try {
            $settings = get_option('shift8_treb_settings', array());
            
            // Prepare settings for display (hide sensitive data)
            $display_settings = array();
            foreach ($settings as $key => $value) {
                if ($key === 'bearer_token') {
                    $display_settings[$key] = !empty($value) ? 'Set (encrypted)' : 'Not set';
                } else {
                    $display_settings[$key] = $value;
                }
            }
            
            // Add last sync info
            $last_sync = get_option('shift8_treb_last_sync', '');
            $display_settings['last_sync'] = !empty($last_sync) ? $last_sync : 'Never';
            
            if ($format === 'table') {
                $items = array();
                foreach ($display_settings as $key => $value) {
                    $items[] = array(
                        'Setting' => $key,
                        'Value' => is_array($value) ? wp_json_encode($value) : (string) $value
                    );
                }
                WP_CLI\Utils\format_items('table', $items, array('Setting', 'Value'));
            } else {
                WP_CLI\Utils\format_items($format, array($display_settings), array_keys($display_settings));
            }
            
        } catch (Exception $e) {
            WP_CLI::error('Failed to retrieve settings: ' . $e->getMessage());
        }
    }

    /**
     * Test API connection
     *
     * ## EXAMPLES
     *
     *     wp shift8-treb test-api
     *
     * @when after_wp_load
     */
    public function test_api($args, $assoc_args) {
        try {
            // Include and initialize sync service
            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
            $sync_service = new Shift8_TREB_Sync_Service();

            WP_CLI::line('Testing AMPRE API connection...');
            
            $result = $sync_service->test_connection();
            
            if ($result['success']) {
                WP_CLI::success('API connection successful: ' . $result['message']);
            } else {
                WP_CLI::error('API connection failed: ' . $result['message']);
            }
            
        } catch (Exception $e) {
            WP_CLI::error('API test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test Media API for a specific listing
     *
     * ## OPTIONS
     *
     * <listing_key>
     * : The MLS listing key to test
     *
     * [--raw]
     * : Show raw API response
     *
     * ## EXAMPLES
     *
     *     wp shift8-treb test_media W12438713
     *     wp shift8-treb test_media W12438713 --raw
     *
     * @when after_wp_load
     */
    public function test_media($args, $assoc_args) {
        $listing_key = isset($args[0]) ? $args[0] : 'W12438713';
        
        WP_CLI::line("Testing Media API for listing: $listing_key");
        
        try {
            // Include required classes
            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
            
            // Get plugin settings
            $settings = get_option('shift8_treb_settings', array());
            
            // Decrypt bearer token
            if (!empty($settings['bearer_token'])) {
                $settings['bearer_token'] = shift8_treb_decrypt_data($settings['bearer_token']);
            }
            
            // Create AMPRE service
            $ampre_service = new Shift8_TREB_AMPRE_Service($settings);
            
            // Test get_media_for_listing method
            $result = $ampre_service->get_media_for_listing($listing_key);
            
            if (is_wp_error($result)) {
                WP_CLI::error('API Error: ' . $result->get_error_message());
                return;
            }
            
            WP_CLI::success('Media data retrieved successfully');
            
            // Show media data
            if (is_array($result) && count($result) > 0) {
                foreach ($result as $index => $media) {
                    WP_CLI::line("Photo " . ($index + 1) . ":");
                    WP_CLI::line("  - MediaURL: " . (isset($media['MediaURL']) ? $media['MediaURL'] : 'N/A'));
                    WP_CLI::line("  - MediaType: " . (isset($media['MediaType']) ? $media['MediaType'] : 'N/A'));
                    WP_CLI::line("  - Order: " . (isset($media['Order']) ? $media['Order'] : 'N/A'));
                    WP_CLI::line("  - PreferredPhoto: " . (isset($media['PreferredPhotoYN']) ? ($media['PreferredPhotoYN'] ? 'Yes' : 'No') : 'N/A'));
                    WP_CLI::line("  - Size: " . (isset($media['ImageSizeDescription']) ? $media['ImageSizeDescription'] : 'N/A'));
                    WP_CLI::line("");
                }
            } else {
                WP_CLI::line("No photos found for this listing");
            }
            
            // Show raw response structure
            if (isset($assoc_args['raw'])) {
                WP_CLI::line("Raw response:");
                WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
            }
            
        } catch (Exception $e) {
            WP_CLI::error('Exception: ' . $e->getMessage());
        }
    }

    /**
     * Clear all TREB sync logs
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     # Clear logs with confirmation
     *     wp shift8-treb clear_logs
     *
     *     # Clear logs without confirmation
     *     wp shift8-treb clear_logs --yes
     *
     * @when after_wp_load
     */
    public function clear_logs($args, $assoc_args) {
        $skip_confirm = isset($assoc_args['yes']);
        
        if (!$skip_confirm) {
            WP_CLI::confirm('Are you sure you want to clear all TREB sync logs?');
        }
        
        try {
            $result = shift8_treb_clear_logs();
            
            if ($result) {
                WP_CLI::success('All logs cleared successfully.');
            } else {
                WP_CLI::error('Failed to clear logs.');
            }
            
        } catch (Exception $e) {
            WP_CLI::error('Failed to clear logs: ' . $e->getMessage());
        }
    }
}

// Register WP-CLI commands if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('shift8-treb', 'Shift8_TREB_CLI');
}
