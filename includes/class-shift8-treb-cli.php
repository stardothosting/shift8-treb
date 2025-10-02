<?php
/**
 * WP-CLI Commands for Shift8 TREB Plugin
 *
 * @package Shift8_TREB
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI commands for TREB listing management
 *
 * @since 1.0.0
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
     * @when after_wp_load
     */
    public function sync($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $verbose = isset($assoc_args['verbose']);
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : null;
        $force = isset($assoc_args['force']);

        WP_CLI::line('=== Shift8 TREB Manual Sync ===');
        
        if ($dry_run) {
            WP_CLI::warning('DRY RUN MODE - No posts will be created or updated');
        }

        try {
            // Get settings
            $settings = get_option('shift8_treb_settings', array());
            
            if (empty($settings['bearer_token']) && !$force) {
                WP_CLI::error('Bearer token not configured. Use --force to override or configure in admin settings.');
            }

            // Log sync start
            shift8_treb_log('=== WP-CLI MANUAL SYNC STARTED ===', array(
                'dry_run' => $dry_run,
                'verbose' => $verbose,
                'limit' => $limit,
                'user' => 'wp-cli'
            ), 'info');

            if ($verbose) {
                WP_CLI::line('Settings loaded:');
                WP_CLI::line('- Bearer token: ' . (!empty($settings['bearer_token']) ? 'Set' : 'Not set'));
                WP_CLI::line('- Sync frequency: ' . ($settings['sync_frequency'] ?? 'daily'));
                WP_CLI::line('- Max listings per query: ' . ($settings['max_listings_per_query'] ?? 100));
                WP_CLI::line('- City filter: ' . ($settings['city_filter'] ?? 'Toronto'));
                WP_CLI::line('- Status filter: ' . ($settings['listing_status_filter'] ?? 'Active'));
                WP_CLI::line('');
            }

            // Initialize services
            if (!class_exists('Shift8_TREB_AMPRE_Service')) {
                require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
            }
            if (!class_exists('Shift8_TREB_Post_Manager')) {
                require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-post-manager.php';
            }

            // Decrypt bearer token (it's encrypted when stored)
            if (!empty($settings['bearer_token'])) {
                $bearer_token = shift8_treb_decrypt_data($settings['bearer_token']);
                $settings['bearer_token'] = $bearer_token;
            }
            
            $ampre_service = new Shift8_TREB_AMPRE_Service($settings);
            $post_manager = new Shift8_TREB_Post_Manager($settings);

            // Test API connection first
            WP_CLI::line('Testing API connection...');
            $connection_test = $ampre_service->test_connection();
            
            if (!$connection_test['success']) {
                WP_CLI::error('API connection failed: ' . $connection_test['message']);
            }
            
            WP_CLI::success('API connection successful');

            // Get listings
            WP_CLI::line('Fetching listings from AMPRE API...');
            
            $query_limit = $limit ?? ($settings['max_listings_per_query'] ?? 100);
            $listings = $ampre_service->get_listings();

            // Check for WP_Error
            if (is_wp_error($listings)) {
                WP_CLI::error('API request failed: ' . $listings->get_error_message());
            }

            if (empty($listings)) {
                WP_CLI::warning('No listings returned from API');
                shift8_treb_log('No listings returned from AMPRE API', array(), 'warning');
                return;
            }

            WP_CLI::success(sprintf('Fetched %d listings from API', count($listings)));
            shift8_treb_log('Fetched listings from AMPRE', array('count' => count($listings)), 'info');

            if ($verbose) {
                WP_CLI::line('Sample listing data:');
                if (!empty($listings[0])) {
                    $sample = $listings[0];
                    WP_CLI::line('- ListingKey: ' . ($sample['ListingKey'] ?? 'N/A'));
                    WP_CLI::line('- Address: ' . ($sample['UnparsedAddress'] ?? 'N/A'));
                    WP_CLI::line('- Price: $' . number_format($sample['ListPrice'] ?? 0));
                    WP_CLI::line('- Status: ' . ($sample['StandardStatus'] ?? 'N/A'));
                }
                WP_CLI::line('');
            }

            // Process listings
            WP_CLI::line('Processing listings...');
            
            $processed = 0;
            $errors = 0;
            $skipped = 0;
            $created = 0;
            $updated = 0;

            $progress = WP_CLI\Utils\make_progress_bar('Processing listings', count($listings));

            foreach ($listings as $listing) {
                try {
                    if ($dry_run) {
                        // In dry run, just validate the data
                        $listing_key = $listing['ListingKey'] ?? 'unknown';
                        $address = $listing['UnparsedAddress'] ?? 'No address';
                        $price = $listing['ListPrice'] ?? 0;
                        
                        if ($verbose) {
                            WP_CLI::line(sprintf('Would process: %s - %s ($%s)', 
                                $listing_key, $address, number_format($price)));
                        }
                        
                        $processed++;
                    } else {
                        // Actually process the listing
                        $result = $post_manager->process_listing($listing);
                        
                        if ($result) {
                            if ($result['action'] === 'created') {
                                $created++;
                            } elseif ($result['action'] === 'updated') {
                                $updated++;
                            }
                            $processed++;
                            
                            if ($verbose) {
                                WP_CLI::line(sprintf('%s: %s (ID: %d)', 
                                    ucfirst($result['action']), 
                                    $result['title'], 
                                    $result['post_id']));
                            }
                        } else {
                            $skipped++;
                        }
                    }
                } catch (Exception $e) {
                    $errors++;
                    $listing_key = isset($listing['ListingKey']) ? $listing['ListingKey'] : 'unknown';
                    
                    shift8_treb_log('Error processing listing', array(
                        'listing_key' => $listing_key,
                        'error' => $e->getMessage()
                    ), 'error');
                    
                    if ($verbose) {
                        WP_CLI::warning(sprintf('Error processing %s: %s', $listing_key, $e->getMessage()));
                    }
                }
                
                $progress->tick();
            }

            $progress->finish();

            // Show results
            WP_CLI::line('');
            WP_CLI::line('=== Sync Results ===');
            WP_CLI::line(sprintf('Total listings: %d', count($listings)));
            WP_CLI::line(sprintf('Processed: %d', $processed));
            
            if (!$dry_run) {
                WP_CLI::line(sprintf('Created: %d', $created));
                WP_CLI::line(sprintf('Updated: %d', $updated));
                WP_CLI::line(sprintf('Skipped: %d', $skipped));
            }
            
            WP_CLI::line(sprintf('Errors: %d', $errors));

            // Log completion
            shift8_treb_log('=== WP-CLI MANUAL SYNC COMPLETED ===', array(
                'total_listings' => count($listings),
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'dry_run' => $dry_run
            ), 'info');

            if ($errors > 0) {
                WP_CLI::warning(sprintf('Sync completed with %d errors. Check logs for details.', $errors));
            } else {
                WP_CLI::success('Sync completed successfully!');
            }

        } catch (Exception $e) {
            shift8_treb_log('WP-CLI sync failed', array('error' => $e->getMessage()), 'error');
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
        $format = $assoc_args['format'] ?? 'table';
        
        $settings = get_option('shift8_treb_settings', array());
        
        // Sanitize sensitive data for display
        $display_settings = array();
        foreach ($settings as $key => $value) {
            if ($key === 'bearer_token') {
                $display_settings[$key] = !empty($value) ? 'Set (encrypted)' : 'Not set';
            } elseif ($key === 'google_maps_api_key') {
                $display_settings[$key] = !empty($value) ? 'Set' : 'Not set';
            } else {
                $display_settings[$key] = $value;
            }
        }

        if ($format === 'table') {
            $table_data = array();
            foreach ($display_settings as $key => $value) {
                $table_data[] = array(
                    'Setting' => $key,
                    'Value' => is_array($value) ? wp_json_encode($value) : (string)$value
                );
            }
            WP_CLI\Utils\format_items('table', $table_data, array('Setting', 'Value'));
        } else {
            WP_CLI\Utils\format_items($format, array($display_settings), array_keys($display_settings));
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
        WP_CLI::line('Testing AMPRE API connection...');
        
        try {
            $settings = get_option('shift8_treb_settings', array());
            
            if (empty($settings['bearer_token'])) {
                WP_CLI::error('Bearer token not configured. Please configure in admin settings first.');
            }

            if (!class_exists('Shift8_TREB_AMPRE_Service')) {
                require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
            }

            // Decrypt bearer token (it's encrypted when stored)
            $bearer_token = shift8_treb_decrypt_data($settings['bearer_token']);
            $settings['bearer_token'] = $bearer_token;
            
            $ampre_service = new Shift8_TREB_AMPRE_Service($settings);
            $result = $ampre_service->test_connection();
            
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
     * View recent logs
     *
     * ## OPTIONS
     *
     * [--lines=<number>]
     * : Number of log lines to show
     * ---
     * default: 50
     * ---
     *
     * [--level=<level>]
     * : Filter by log level (info, warning, error)
     *
     * ## EXAMPLES
     *
     *     wp shift8-treb logs
     *     wp shift8-treb logs --lines=100
     *     wp shift8-treb logs --level=error
     *
     * @when after_wp_load
     */
    public function logs($args, $assoc_args) {
        $lines = isset($assoc_args['lines']) ? intval($assoc_args['lines']) : 50;
        $level_filter = $assoc_args['level'] ?? null;
        
        try {
            $logs = shift8_treb_get_logs($lines);
            
            if (empty($logs) || (count($logs) === 1 && strpos($logs[0], 'No log file') !== false)) {
                WP_CLI::warning('No logs found. Enable debug mode and run a sync to generate logs.');
                return;
            }

            WP_CLI::line(sprintf('=== Last %d log entries ===', count($logs)));
            
            foreach ($logs as $log_line) {
                if ($level_filter) {
                    $upper_level = strtoupper($level_filter);
                    if (strpos($log_line, "[$upper_level]") === false) {
                        continue;
                    }
                }
                
                // Color code log levels
                if (strpos($log_line, '[ERROR]') !== false) {
                    WP_CLI::error_multi_line(array($log_line));
                } elseif (strpos($log_line, '[WARNING]') !== false) {
                    WP_CLI::warning($log_line);
                } else {
                    WP_CLI::line($log_line);
                }
            }
            
        } catch (Exception $e) {
            WP_CLI::error('Failed to retrieve logs: ' . $e->getMessage());
        }
    }

    /**
     * Clear all logs
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp shift8-treb clear-logs
     *     wp shift8-treb clear-logs --yes
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
