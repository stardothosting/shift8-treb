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

            // Apply limit if specified
            if ($limit && $limit > 0) {
                $listings = array_slice($listings, 0, $limit);
                shift8_treb_log('Limited listings for processing', array(
                    'original_count' => $results['total_listings'],
                    'limited_count' => count($listings)
                ));
            }

            // Process each listing
            foreach ($listings as $listing) {
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
}
