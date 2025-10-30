<?php
/**
 * Sync Service for Shift8 TREB Plugin
 *
 * Centralizes sync logic to eliminate duplication between CLI and Cron sync methods.
 *
 * @package Shift8\TREB
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sync service class
 *
 * Handles shared sync logic for both CLI and cron operations.
 *
 * @since 1.1.0
 */
class Shift8_TREB_Sync_Service {

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * AMPRE service instance
     *
     * @var Shift8_TREB_AMPRE_Service
     */
    private $ampre_service;

    /**
     * Post manager instance
     *
     * @var Shift8_TREB_Post_Manager
     */
    private $post_manager;

    /**
     * Constructor
     *
     * @since 1.1.0
     * @param array $settings Plugin settings
     */
    public function __construct($settings = array()) {
        $this->settings = $this->prepare_settings($settings);
        $this->initialize_services();
    }

    /**
     * Prepare and validate settings
     *
     * @since 1.1.0
     * @param array $input_settings Input settings
     * @return array Prepared settings
     */
    private function prepare_settings($input_settings) {
        // Get base settings from WordPress options
        $base_settings = get_option('shift8_treb_settings', array());
        
        // Add last sync timestamp for incremental sync (before overrides)
        $last_sync = get_option('shift8_treb_last_sync', '');
        if (!empty($last_sync)) {
            $base_settings['last_sync_timestamp'] = $last_sync;
        }
        
        // Merge with any overrides (this allows overriding last_sync_timestamp)
        $settings = array_merge($base_settings, $input_settings);
        
        // Decrypt bearer token if needed
        if (!empty($settings['bearer_token'])) {
            $settings['bearer_token'] = shift8_treb_decrypt_data($settings['bearer_token']);
        }
        
        return $settings;
    }

    /**
     * Initialize service instances
     *
     * @since 1.1.0
     */
    private function initialize_services() {
        // Include required classes
        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-post-manager.php';
        
        // Initialize services
        $this->ampre_service = new Shift8_TREB_AMPRE_Service($this->settings);
        $this->post_manager = new Shift8_TREB_Post_Manager($this->settings);
    }

    /**
     * Test API connection
     *
     * @since 1.1.0
     * @return array Connection test result
     */
    public function test_connection() {
        return $this->ampre_service->test_connection();
    }

    /**
     * Execute sync operation
     *
     * @since 1.1.0
     * @param array $options Sync options (dry_run, verbose, limit, etc.)
     * @return array Sync results
     */
    public function execute_sync($options = array()) {
        $dry_run = isset($options['dry_run']) ? $options['dry_run'] : false;
        $verbose = isset($options['verbose']) ? $options['verbose'] : false;
        $limit = isset($options['limit']) ? intval($options['limit']) : null;

        $results = array(
            'success' => false,
            'total_listings' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'message' => '',
            'listings' => array()
        );

        try {
            // Validate bearer token
            if (empty($this->settings['bearer_token'])) {
                throw new Exception('Bearer token not configured');
            }

            // Log sync start
            shift8_treb_log('=== SYNC STARTED ===', array(
                'dry_run' => $dry_run,
                'verbose' => $verbose,
                'limit' => $limit,
                'settings' => array(
                    'sync_frequency' => $this->settings['sync_frequency'] ?? 'unknown',
                    'member_id' => $this->settings['member_id'] ?? '',
                    'listing_age_days' => $this->settings['listing_age_days'] ?? 30,
                    'last_sync_timestamp' => $this->settings['last_sync_timestamp'] ?? 'none'
                )
            ), 'info');

            // Test API connection
            $connection_test = $this->test_connection();
            if (!$connection_test['success']) {
                throw new Exception('API connection failed: ' . esc_html($connection_test['message']));
            }

            // Fetch listings from AMPRE API
            $listings = $this->ampre_service->get_listings();
            
            if (is_wp_error($listings)) {
                throw new Exception('API request failed: ' . esc_html($listings->get_error_message()));
            }

            if (empty($listings)) {
                $results['message'] = 'No listings returned from API';
                shift8_treb_log('No listings returned from AMPRE API', array(
                    'settings' => array(
                        'listing_age_days' => $this->settings['listing_age_days'] ?? 30,
                        'last_sync_timestamp' => $this->settings['last_sync_timestamp'] ?? 'none'
                    )
                ));
                return $results;
            }

            $results['total_listings'] = count($listings);
            $results['listings'] = $listings;

            shift8_treb_log('Fetched listings from AMPRE', array('count' => count($listings)));
            
            // Simple progress feedback for CLI
            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::line("Found {$results['total_listings']} listings to process");
            }

            // Apply limit if specified
            if ($limit && $limit > 0) {
                $listings = array_slice($listings, 0, $limit);
                shift8_treb_log('Limited listings for processing', array(
                    'original_count' => $results['total_listings'],
                    'limited_count' => count($listings)
                ));
            }

            // Deduplicate listings by MLS number (in case API returns duplicates)
            $unique_listings = array();
            $duplicate_count = 0;
            foreach ($listings as $listing) {
                $mls_number = isset($listing['ListingKey']) ? sanitize_text_field($listing['ListingKey']) : '';
                if (!empty($mls_number)) {
                    if (!isset($unique_listings[$mls_number])) {
                        $unique_listings[$mls_number] = $listing;
                    } else {
                        $duplicate_count++;
                        shift8_treb_log('Duplicate MLS in API response', array(
                            'mls_number' => esc_html($mls_number),
                            'duplicate_count' => $duplicate_count
                        ));
                    }
                }
            }
            
            // Convert back to indexed array
            $listings = array_values($unique_listings);
            
            if ($duplicate_count > 0) {
                shift8_treb_log('Deduplicated API response', array(
                    'original_count' => $results['total_listings'],
                    'unique_count' => count($listings),
                    'duplicates_removed' => $duplicate_count
                ));
            }

            // Process each unique listing
            $processed_count = 0;
            $total_to_process = count($listings);
            
            foreach ($listings as $listing) {
                $processed_count++;
                
                // Simple progress feedback for CLI (every 10 listings or at end)
                if (defined('WP_CLI') && WP_CLI && ($processed_count % 10 == 0 || $processed_count == $total_to_process)) {
                    $mls = isset($listing['ListingKey']) ? $listing['ListingKey'] : 'Unknown';
                    WP_CLI::line("Processing {$processed_count}/{$total_to_process}: MLS {$mls}");
                }
                
                try {
                    if ($dry_run) {
                        // In dry run mode, just validate and count
                        $results['processed']++;
                        $results['created']++; // Assume all would be created in dry run
                        continue;
                    }

                    $result = $this->post_manager->process_listing($listing);
                    
                    if ($result && is_array($result)) {
                        $results['processed']++;
                        
                        if ($result['success']) {
                            switch ($result['action']) {
                                case 'created':
                                    $results['created']++;
                                    break;
                                case 'updated':
                                    $results['updated']++;
                                    break;
                                case 'skipped':
                                    $results['skipped']++;
                                    break;
                            }
                        } else {
                            $results['skipped']++;
                        }
                    } else {
                        $results['errors']++;
                    }

                } catch (Exception $e) {
                    $results['errors']++;
                    shift8_treb_log('Error processing listing', array(
                        'listing_key' => isset($listing['ListingKey']) ? esc_html($listing['ListingKey']) : 'unknown',
                        'error' => esc_html($e->getMessage())
                    ), 'error');
                }
            }

            // Note: Cleanup of terminated listings is handled by separate weekly cron job
            // to avoid impacting sync performance

            // Update last sync timestamp (only if not dry run)
            if (!$dry_run) {
                $current_timestamp = current_time('c'); // ISO 8601 format
                update_option('shift8_treb_last_sync', $current_timestamp);
            }

            $results['success'] = true;
            $results['message'] = sprintf(
                'Sync completed: %d processed, %d created, %d updated, %d skipped, %d errors',
                $results['processed'],
                $results['created'], 
                $results['updated'],
                $results['skipped'],
                $results['errors']
            );

            // Log completion
            shift8_treb_log('=== SYNC COMPLETED ===', array(
                'total_listings' => $results['total_listings'],
                'processed' => $results['processed'],
                'created' => $results['created'],
                'updated' => $results['updated'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors'],
                'dry_run' => $dry_run,
                'next_sync_from' => !$dry_run ? $current_timestamp : 'unchanged'
            ), 'info');

        } catch (Exception $e) {
            $results['message'] = 'Sync failed: ' . $e->getMessage();
            shift8_treb_log('Sync failed', array('error' => esc_html($e->getMessage())), 'error');
        }

        return $results;
    }

    /**
     * Get current settings
     *
     * @since 1.1.0
     * @return array Current settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Update settings
     *
     * @since 1.1.0
     * @param array $new_settings New settings to merge
     */
    public function update_settings($new_settings) {
        $this->settings = array_merge($this->settings, $new_settings);
        
        // Reinitialize services with new settings
        $this->initialize_services();
    }

    /**
     * Query API for terminated/cancelled listings and remove matching WordPress posts
     * 
     * This queries the API specifically for Cancelled/Expired/Terminated listings,
     * then checks if any of those MLS numbers exist in WordPress and removes them.
     * 
     * Runs weekly to reduce API load.
     *
     * @since 1.6.7
     * @return array Cleanup results
     */
    public function cleanup_terminated_listings() {
        $results = array(
            'api_terminated_count' => 0,
            'checked' => 0,
            'removed' => 0,
            'errors' => 0
        );

        try {
            shift8_treb_log('Starting terminated listings cleanup', array(), 'info');
            
            // Query API for terminated/cancelled listings
            // Build a query that specifically targets terminated statuses
            $terminated_listings = $this->fetch_terminated_listings_from_api();
            
            if (empty($terminated_listings)) {
                shift8_treb_log('No terminated listings found in API', array(), 'info');
                return $results;
            }
            
            $results['api_terminated_count'] = count($terminated_listings);
            
            // Extract MLS numbers from terminated listings
            $terminated_mls_numbers = array();
            foreach ($terminated_listings as $listing) {
                if (!empty($listing['ListingKey'])) {
                    $terminated_mls_numbers[] = sanitize_text_field($listing['ListingKey']);
                }
            }
            
            if (empty($terminated_mls_numbers)) {
                return $results;
            }
            
            shift8_treb_log('Found terminated listings in API', array(
                'count' => count($terminated_mls_numbers)
            ), 'info');

            // Now check which of these terminated MLS numbers exist in WordPress
            foreach ($terminated_mls_numbers as $mls_number) {
                $results['checked']++;
                
                // Find WordPress post with this MLS number
                $args = array(
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => 'shift8_treb_listing_key',
                            'value' => $mls_number,
                            'compare' => '='
                        )
                    )
                );
                
                $posts = get_posts($args);
                
                if (!empty($posts)) {
                    foreach ($posts as $post) {
                        wp_trash_post($post->ID);
                        $results['removed']++;
                        shift8_treb_log('Removed terminated listing', array(
                            'post_id' => $post->ID,
                            'mls_number' => esc_html($mls_number)
                        ), 'info');
                    }
                }
            }

        } catch (Exception $e) {
            $results['errors']++;
            shift8_treb_log('Error during terminated listings cleanup', array(
                'error' => esc_html($e->getMessage())
            ), 'error');
        }

        return $results;
    }
    
    /**
     * Fetch terminated/cancelled listings from AMPRE API
     * 
     * Queries specifically for Cancelled, Expired, Withdrawn, and Terminated listings
     * Returns up to 200 results
     *
     * @since 1.6.7
     * @return array Array of terminated listings
     */
    private function fetch_terminated_listings_from_api() {
        try {
            // Build a custom query for terminated listings only
            $terminated_statuses = array('Cancelled', 'Expired', 'Withdrawn', 'Terminated');
            
            // Build OData filter for terminated statuses
            $status_filters = array();
            foreach ($terminated_statuses as $status) {
                $status_filters[] = "StandardStatus eq '" . $status . "'";
            }
            $filter = '(' . implode(' or ', $status_filters) . ')';
            
            // Add time filter - only check listings modified in last 30 days
            // (listings terminated more than 30 days ago are unlikely to still be on the site)
            $thirty_days_ago = gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days'));
            $filter .= " and ModificationTimestamp ge " . $thirty_days_ago;
            
            $query_params = array(
                '$filter=' . $filter,
                '$top=200', // Limit to 200 terminated listings
                '$orderby=ModificationTimestamp desc'
            );
            
            $endpoint = 'Property?' . implode('&', $query_params);
            
            shift8_treb_log('Querying API for terminated listings', array(
                'endpoint' => esc_html($endpoint)
            ), 'info');
            
            // Use reflection to call the private make_request method
            $reflection = new ReflectionClass($this->ampre_service);
            $method = $reflection->getMethod('make_request');
            $method->setAccessible(true);
            $response = $method->invoke($this->ampre_service, $endpoint);
            
            if (is_wp_error($response)) {
                shift8_treb_log('Failed to fetch terminated listings', array(
                    'error' => esc_html($response->get_error_message())
                ), 'error');
                return array();
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                shift8_treb_log('API returned non-200 status for terminated listings', array(
                    'status_code' => $response_code
                ), 'error');
                return array();
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['value'])) {
                shift8_treb_log('Invalid JSON response for terminated listings', array(), 'error');
                return array();
            }
            
            return $data['value'];
            
        } catch (Exception $e) {
            shift8_treb_log('Exception fetching terminated listings', array(
                'error' => esc_html($e->getMessage())
            ), 'error');
            return array();
        }
    }
}
