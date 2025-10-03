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
     * [--mls=<number>]
     * : Import specific MLS number(s) - comma separated (e.g. W12436591,C12380184)
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
     *     # Import specific MLS numbers
     *     wp shift8-treb sync --mls=W12436591,C12380184
     *
     * @when after_wp_load
     */
    public function sync($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $verbose = isset($assoc_args['verbose']);
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : null;
        $force = isset($assoc_args['force']);
        $listing_age = isset($assoc_args['listing-age']) ? intval($assoc_args['listing-age']) : null;
        $mls_numbers = isset($assoc_args['mls']) ? $assoc_args['mls'] : null;

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

            // Handle MLS-specific import
            if ($mls_numbers !== null) {
                $mls_list = array_map('trim', explode(',', $mls_numbers));
                WP_CLI::line("🎯 Direct MLS Import Mode: " . implode(', ', $mls_list));
                
                // For MLS-specific import, we'll handle this separately
                return $this->import_specific_mls($mls_list, $dry_run, $verbose, $base_settings);
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
                if ($limit && $limit > 0) {
                    WP_CLI::line("Limited to {$limit} listings for processing");
                }

                // Show sample data in verbose mode
                if ($verbose && !empty($results['listings'])) {
                    WP_CLI::line('');
                    WP_CLI::line('Sample listing data:');
                    $sample = $results['listings'][0];
                    WP_CLI::line('- ListingKey: ' . ($sample['ListingKey'] ?? 'N/A'));
                    WP_CLI::line('- Address: ' . ($sample['UnparsedAddress'] ?? 'N/A'));
                    WP_CLI::line('- Price: $' . number_format($sample['ListPrice'] ?? 0));
                    WP_CLI::line('- Status: ' . ($sample['StandardStatus'] ?? 'N/A'));
                    WP_CLI::line('');
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
     * Analyze raw API response for diagnostics
     *
     * @since 1.2.0
     * 
     * ## OPTIONS
     * 
     * [--limit=<number>]
     * : Maximum number of listings to analyze
     * ---
     * default: 50
     * ---
     * 
     * [--search=<mls>]
     * : Search for specific MLS number(s) (comma-separated)
     * 
     * [--show-agents]
     * : Show unique agent IDs and their listing counts
     * 
     * [--days=<number>]
     * : Number of days to look back
     * ---
     * default: 90
     * ---
     * 
     * ## EXAMPLES
     * 
     *     wp shift8-treb analyze --limit=100 --show-agents
     *     wp shift8-treb analyze --search=W12436591,C12380184
     *     wp shift8-treb analyze --days=30 --limit=200
     * 
     * @when after_wp_load
     */
    public function analyze($args, $assoc_args) {
        $limit = intval($assoc_args['limit'] ?? 50);
        $search_mls = $assoc_args['search'] ?? '';
        $show_agents = isset($assoc_args['show-agents']);
        $days = intval($assoc_args['days'] ?? 90);
        
        WP_CLI::line('=== TREB API Diagnostic Analysis ===');
        WP_CLI::line("Limit: {$limit} listings");
        WP_CLI::line("Days back: {$days}");
        
        if ($search_mls) {
            WP_CLI::line("Searching for: {$search_mls}");
        }
        
        // Get settings and initialize AMPRE service
        $settings = get_option('shift8_treb_settings', array());
        
        if (empty($settings['bearer_token'])) {
            WP_CLI::error('Bearer token not configured');
        }
        
        // Decrypt token using the plugin's decryption function
        $decrypted_token = shift8_treb_decrypt_data($settings['bearer_token']);
        
        if (empty($decrypted_token)) {
            WP_CLI::error('Failed to decrypt bearer token');
        }
        
        // Prepare settings for AMPRE service
        $ampre_settings = array_merge($settings, array(
            'bearer_token' => $decrypted_token,
            'listing_age_days' => $days
        ));
        
        // Initialize AMPRE service
        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
        $ampre_service = new Shift8_TREB_AMPRE_Service($ampre_settings);
        
        WP_CLI::line('Testing API connection...');
        
        try {
            $connection_result = $ampre_service->test_connection();
            if (!$connection_result['success']) {
                WP_CLI::error('API connection failed: ' . $connection_result['message']);
            }
            WP_CLI::success('API connection successful');
        } catch (Exception $e) {
            WP_CLI::error('API connection error: ' . esc_html($e->getMessage()));
        }
        
        WP_CLI::line('Fetching listings data...');
        
        try {
            $listings_result = $ampre_service->get_listings();
            
            if (is_wp_error($listings_result)) {
                WP_CLI::error('Failed to fetch listings: ' . $listings_result->get_error_message());
            }
            
            $listings = $listings_result;
            $total_found = count($listings);
            
            // Limit the listings for analysis
            if ($limit > 0 && $total_found > $limit) {
                $listings = array_slice($listings, 0, $limit);
            }
            
            WP_CLI::success("Found {$total_found} total listings, analyzing first " . count($listings));
            
            // Analyze the data
            $this->analyze_listings_data($listings, $search_mls, $show_agents, $settings);
            
        } catch (Exception $e) {
            WP_CLI::error('API error: ' . esc_html($e->getMessage()));
        }
    }
    
    /**
     * Analyze listings data for diagnostics
     *
     * @since 1.2.0
     * @param array $listings Listings data
     * @param string $search_mls MLS numbers to search for
     * @param bool $show_agents Whether to show agent analysis
     * @param array $settings Plugin settings
     */
    private function analyze_listings_data($listings, $search_mls, $show_agents, $settings) {
        $search_list = array();
        if (!empty($search_mls)) {
            $search_list = array_map('trim', explode(',', $search_mls));
        }
        
        $agent_counts = array();
        $found_listings = array();
        $member_ids = isset($settings['member_id']) ? trim($settings['member_id']) : '';
        $configured_members = array();
        
        if (!empty($member_ids)) {
            $configured_members = array_map('trim', explode(',', $member_ids));
        }
        
        WP_CLI::line('');
        WP_CLI::line('=== ANALYSIS RESULTS ===');
        
        foreach ($listings as $listing) {
            $mls_number = $listing['ListingKey'] ?? 'unknown';
            $agent_id = $listing['ListAgentKey'] ?? 'unknown';
            $address = $listing['UnparsedAddress'] ?? 'unknown';
            $price = isset($listing['ListPrice']) ? '$' . number_format($listing['ListPrice']) : 'unknown';
            
            // Count agents
            if ($agent_id !== 'unknown') {
                $agent_counts[$agent_id] = ($agent_counts[$agent_id] ?? 0) + 1;
            }
            
            // Check for specific MLS numbers
            if (!empty($search_list) && in_array($mls_number, $search_list)) {
                $is_our_agent = in_array($agent_id, $configured_members, true);
                $category = $is_our_agent ? 'Listings' : 'OtherListings';
                
                // Calculate listing age
                $modification_timestamp = $listing['ModificationTimestamp'] ?? '';
                $listing_date = $listing['ListingContractDate'] ?? '';
                $days_ago = '';
                $date_info = '';
                
                if (!empty($modification_timestamp)) {
                    $mod_time = strtotime($modification_timestamp);
                    if ($mod_time) {
                        $days_since_mod = floor((time() - $mod_time) / (24 * 60 * 60));
                        $date_info = "Modified: " . gmdate('Y-m-d H:i', $mod_time) . " ({$days_since_mod} days ago)";
                    }
                }
                
                if (!empty($listing_date)) {
                    $list_time = strtotime($listing_date);
                    if ($list_time) {
                        $days_since_list = floor((time() - $list_time) / (24 * 60 * 60));
                        $list_info = "Listed: " . gmdate('Y-m-d', $list_time) . " ({$days_since_list} days ago)";
                        $date_info = $date_info ? $date_info . " | " . $list_info : $list_info;
                    }
                }
                
                $found_listings[] = array(
                    'mls' => $mls_number,
                    'agent_id' => $agent_id,
                    'address' => $address,
                    'price' => $price,
                    'category' => $category,
                    'is_configured' => $is_our_agent,
                    'date_info' => $date_info,
                    'modification_timestamp' => $modification_timestamp,
                    'listing_date' => $listing_date
                );
            }
        }
        
        // Show search results
        if (!empty($search_list)) {
            WP_CLI::line('');
            WP_CLI::line('=== SEARCH RESULTS ===');
            
            if (empty($found_listings)) {
                WP_CLI::warning('None of the searched MLS numbers were found in the current data:');
                foreach ($search_list as $mls) {
                    WP_CLI::line("  ❌ {$mls} - NOT FOUND");
                }
            } else {
                WP_CLI::success('Found ' . count($found_listings) . ' of ' . count($search_list) . ' searched listings:');
                
                foreach ($found_listings as $listing) {
                    $status_icon = $listing['is_configured'] ? '✅' : '❌';
                    $category_info = $listing['is_configured'] ? 'OUR AGENT (Listings)' : 'OTHER AGENT (OtherListings)';
                    
                    WP_CLI::line("  {$status_icon} {$listing['mls']} - Agent: {$listing['agent_id']} - {$category_info}");
                    WP_CLI::line("      Address: {$listing['address']}");
                    WP_CLI::line("      Price: {$listing['price']}");
                    
                    if (!empty($listing['date_info'])) {
                        WP_CLI::line("      📅 {$listing['date_info']}");
                    }
                    
                    WP_CLI::line('');
                }
            }
            
            // Show missing listings
            $found_mls = array_column($found_listings, 'mls');
            $missing_mls = array_diff($search_list, $found_mls);
            
            if (!empty($missing_mls)) {
                WP_CLI::warning('Missing listings (not in current API data):');
                foreach ($missing_mls as $mls) {
                    WP_CLI::line("  ❌ {$mls}");
                }
            }
            
            // Analyze sync timing issues
            if (!empty($found_listings)) {
                WP_CLI::line('');
                WP_CLI::line('=== SYNC TIMING ANALYSIS ===');
                
                // Get last sync timestamp
                $last_sync = get_option('shift8_treb_last_sync', '');
                $sync_cutoff_days = intval($settings['listing_age_days'] ?? 90);
                
                WP_CLI::line("Plugin sync settings:");
                WP_CLI::line("  Last incremental sync: " . ($last_sync ? $last_sync : 'Never (uses age-based filter)'));
                WP_CLI::line("  Listing age filter: {$sync_cutoff_days} days");
                WP_CLI::line('');
                
                foreach ($found_listings as $listing) {
                    WP_CLI::line("Analysis for {$listing['mls']}:");
                    
                    if (!empty($listing['modification_timestamp'])) {
                        $mod_time = strtotime($listing['modification_timestamp']);
                        $days_since_mod = floor((time() - $mod_time) / (24 * 60 * 60));
                        
                        if ($days_since_mod > $sync_cutoff_days) {
                            WP_CLI::warning("  ⚠️  Modified {$days_since_mod} days ago - OLDER than {$sync_cutoff_days} day filter!");
                            WP_CLI::line("      This listing would be EXCLUDED from age-based sync");
                        } else {
                            WP_CLI::success("  ✅ Modified {$days_since_mod} days ago - within {$sync_cutoff_days} day filter");
                        }
                        
                        if (!empty($last_sync)) {
                            $last_sync_time = strtotime($last_sync);
                            if ($last_sync_time && $mod_time > $last_sync_time) {
                                WP_CLI::success("  ✅ Modified AFTER last sync - would be included in incremental sync");
                            } else {
                                WP_CLI::warning("  ⚠️  Modified BEFORE last sync - would be EXCLUDED from incremental sync");
                            }
                        }
                    }
                    
                    WP_CLI::line('');
                }
                
                WP_CLI::line('💡 Possible reasons for previous non-matching:');
                WP_CLI::line('   1. Listings were older than the age filter when first synced');
                WP_CLI::line('   2. Agent IDs changed after initial import');
                WP_CLI::line('   3. Incremental sync missed updates due to timing');
                WP_CLI::line('   4. Manual sync with different age settings was used');
            }
        }
        
        // Show agent analysis
        if ($show_agents) {
            WP_CLI::line('');
            WP_CLI::line('=== AGENT ANALYSIS ===');
            WP_CLI::line("Configured Member IDs: {$member_ids}");
            WP_CLI::line('');
            
            arsort($agent_counts);
            $top_agents = array_slice($agent_counts, 0, 20, true);
            
            WP_CLI::line('Top 20 Agent IDs by listing count:');
            
            foreach ($top_agents as $agent_id => $count) {
                $is_configured = in_array($agent_id, $configured_members, true);
                $status = $is_configured ? '✅ CONFIGURED' : '❌ Not configured';
                WP_CLI::line("  {$agent_id}: {$count} listings - {$status}");
            }
            
            // Check if any configured agents have listings
            $configured_with_listings = array_intersect_key($agent_counts, array_flip($configured_members));
            
            if (empty($configured_with_listings)) {
                WP_CLI::warning('');
                WP_CLI::warning('ISSUE: None of your configured member IDs have listings in the current data!');
                WP_CLI::line('Consider updating member_id setting with active agent IDs from the list above.');
            } else {
                WP_CLI::success('');
                WP_CLI::success('Your configured member IDs found in data:');
                foreach ($configured_with_listings as $agent_id => $count) {
                    WP_CLI::line("  ✅ {$agent_id}: {$count} listings");
                }
            }
        }
        
        WP_CLI::line('');
        WP_CLI::line("Total listings analyzed: " . count($listings));
        WP_CLI::line("Unique agents found: " . count($agent_counts));
    }

    /**
     * Import specific MLS numbers directly
     *
     * @since 1.2.0
     * @param array $mls_list List of MLS numbers to import
     * @param bool $dry_run Whether this is a dry run
     * @param bool $verbose Whether to show verbose output
     * @param array $settings Plugin settings
     */
    private function import_specific_mls($mls_list, $dry_run, $verbose, $settings) {
        try {
            // Decrypt token
            $decrypted_token = shift8_treb_decrypt_data($settings['bearer_token']);
            if (empty($decrypted_token)) {
                WP_CLI::error('Failed to decrypt bearer token');
            }

            // Prepare settings for services
            $service_settings = array_merge($settings, array(
                'bearer_token' => $decrypted_token
            ));

            // Initialize services
            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-post-manager.php';
            
            $ampre_service = new Shift8_TREB_AMPRE_Service($service_settings);
            $post_manager = new Shift8_TREB_Post_Manager($service_settings);

            WP_CLI::line('Testing API connection...');
            $connection_result = $ampre_service->test_connection();
            if (!$connection_result['success']) {
                WP_CLI::error('API connection failed: ' . $connection_result['message']);
            }
            WP_CLI::success('API connection successful');

            $results = array(
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'not_found' => 0
            );

            WP_CLI::line('');
            WP_CLI::line('Importing specific MLS numbers...');

            foreach ($mls_list as $mls_number) {
                WP_CLI::line("Processing MLS: {$mls_number}");
                
                try {
                    // Get specific property from API
                    $property_data = $ampre_service->get_property($mls_number);
                    
                    if (is_wp_error($property_data)) {
                        WP_CLI::warning("  ❌ API Error: " . $property_data->get_error_message());
                        $results['errors']++;
                        continue;
                    }

                    if (empty($property_data)) {
                        WP_CLI::warning("  ❌ Not found in API");
                        $results['not_found']++;
                        continue;
                    }

                    if ($verbose) {
                        WP_CLI::line("  📍 Address: " . ($property_data['UnparsedAddress'] ?? 'N/A'));
                        WP_CLI::line("  💰 Price: $" . number_format($property_data['ListPrice'] ?? 0));
                        WP_CLI::line("  👤 Agent: " . ($property_data['ListAgentKey'] ?? 'N/A'));
                    }

                    if (!$dry_run) {
                        // Process the listing
                        $post_result = $post_manager->process_listing($property_data);
                        
                        if ($post_result['success']) {
                            if (isset($post_result['created']) && $post_result['created']) {
                                WP_CLI::success("  ✅ Created new post (ID: {$post_result['post_id']})");
                                $results['created']++;
                            } else {
                                WP_CLI::success("  ✅ Updated existing post (ID: {$post_result['post_id']})");
                                $results['updated']++;
                            }
                        } else {
                            WP_CLI::warning("  ❌ Failed to process: " . ($post_result['message'] ?? 'Unknown error'));
                            $results['errors']++;
                        }
                    } else {
                        WP_CLI::line("  ✅ Would be processed (dry run)");
                    }

                    $results['processed']++;

                } catch (Exception $e) {
                    WP_CLI::warning("  ❌ Exception: " . esc_html($e->getMessage()));
                    $results['errors']++;
                }

                WP_CLI::line('');
            }

            // Display summary
            WP_CLI::line('');
            WP_CLI::line('=== IMPORT SUMMARY ===');
            WP_CLI::line("MLS Numbers Requested: " . count($mls_list));
            WP_CLI::line("Successfully Processed: {$results['processed']}");
            
            if (!$dry_run) {
                WP_CLI::line("Created: {$results['created']}");
                WP_CLI::line("Updated: {$results['updated']}");
            }
            
            WP_CLI::line("Not Found: {$results['not_found']}");
            WP_CLI::line("Errors: {$results['errors']}");

            if ($results['errors'] > 0) {
                WP_CLI::warning('Some MLS numbers could not be imported. Check the output above for details.');
            } else if ($results['processed'] === count($mls_list)) {
                WP_CLI::success('All requested MLS numbers processed successfully!');
            }

        } catch (Exception $e) {
            WP_CLI::error('Import failed: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Reset incremental sync timestamp
     *
     * Forces next sync to use age-based filtering instead of incremental sync.
     * Useful when you've deleted posts and want to re-import them.
     *
     * @since 1.2.0
     * 
     * ## OPTIONS
     * 
     * [--yes]
     * : Skip confirmation prompt
     * 
     * ## EXAMPLES
     * 
     *     wp shift8-treb reset-sync
     *     wp shift8-treb reset-sync --yes
     * 
     * @when after_wp_load
     */
    public function reset_sync($args, $assoc_args) {
        $skip_confirm = isset($assoc_args['yes']);
        
        $last_sync = get_option('shift8_treb_last_sync', '');
        
        if (empty($last_sync)) {
            WP_CLI::success('Incremental sync is already disabled (using age-based filtering).');
            return;
        }
        
        WP_CLI::line('Current incremental sync timestamp: ' . $last_sync);
        WP_CLI::line('');
        WP_CLI::line('Resetting will force the next sync to use age-based filtering');
        WP_CLI::line('instead of incremental sync. This is useful when you have deleted');
        WP_CLI::line('posts and want to re-import them.');
        WP_CLI::line('');
        
        if (!$skip_confirm) {
            WP_CLI::confirm('Reset incremental sync timestamp?');
        }
        
        delete_option('shift8_treb_last_sync');
        
        WP_CLI::success('Incremental sync timestamp reset successfully!');
        WP_CLI::line('Next sync will use age-based filtering (listing_age_days setting).');
    }

    /**
     * Show current sync status and settings
     *
     * @since 1.2.0
     * 
     * ## EXAMPLES
     * 
     *     wp shift8-treb sync-status
     * 
     * @when after_wp_load
     */
    public function sync_status($args, $assoc_args) {
        $settings = get_option('shift8_treb_settings', array());
        $last_sync = get_option('shift8_treb_last_sync', '');
        $listing_age_days = $settings['listing_age_days'] ?? 90;
        
        WP_CLI::line('=== TREB Sync Status ===');
        WP_CLI::line('');
        
        if (!empty($last_sync)) {
            $last_sync_time = strtotime($last_sync);
            $hours_ago = round((time() - $last_sync_time) / 3600, 1);
            
            WP_CLI::line('📊 Sync Mode: INCREMENTAL');
            WP_CLI::line("📅 Last Sync: {$last_sync} ({$hours_ago} hours ago)");
            WP_CLI::line('🔄 Next Sync: Will only fetch listings modified after last sync');
            WP_CLI::warning('⚠️  Deleted posts will NOT be re-imported in incremental mode');
            WP_CLI::line('');
            WP_CLI::line('💡 To re-import deleted posts, run: wp shift8-treb reset-sync');
        } else {
            WP_CLI::line('📊 Sync Mode: AGE-BASED');
            WP_CLI::line("📅 Age Filter: {$listing_age_days} days");
            WP_CLI::line('🔄 Next Sync: Will fetch all listings from last ' . $listing_age_days . ' days');
            WP_CLI::success('✅ Deleted posts will be re-imported in age-based mode');
        }
        
        WP_CLI::line('');
        WP_CLI::line('Settings:');
        WP_CLI::line("  Listing Age Days: {$listing_age_days}");
        WP_CLI::line("  Max Listings Per Query: " . ($settings['max_listings_per_query'] ?? 1000));
        WP_CLI::line("  Member IDs: " . ($settings['member_id'] ?? 'Not configured'));
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
