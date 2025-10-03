<?php
/**
 * Plugin Name: Shift8 TREB Real Estate Listings
 * Plugin URI: https://github.com/stardothosting/shift8-treb
 * Description: Integrates Toronto Real Estate Board (TREB) listings via AMPRE API, automatically importing property listings into WordPress. Replaces the Python script with native WordPress functionality.
 * Version: 1.2.0
 * Author: Shift8 Web
 * Author URI: https://shift8web.ca
 * Text Domain: shift8-treb
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SHIFT8_TREB_VERSION', '1.2.0');
define('SHIFT8_TREB_PLUGIN_FILE', __FILE__);
define('SHIFT8_TREB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHIFT8_TREB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SHIFT8_TREB_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Sanitize sensitive data for logging
 *
 * Recursively sanitizes array data to prevent sensitive information
 * like API tokens and passwords from appearing in logs.
 *
 * @since 1.0.0
 * @param mixed $data The data to sanitize
 * @return mixed Sanitized data with sensitive fields redacted
 */
function shift8_treb_sanitize_log_data($data) {
    if (is_array($data)) {
        $sanitized = array();
        foreach ($data as $key => $value) {
            $key_lower = strtolower($key);
            if (in_array($key_lower, array('token', 'api_token', 'password', 'pass', 'pwd', 'authorization'), true)) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (in_array($key_lower, array('username', 'user', 'login'), true)) {
                $sanitized[$key] = strlen($value) > 2 ? substr($value, 0, 2) . '***' : '***';
            } elseif (is_array($value)) {
                $sanitized[$key] = shift8_treb_sanitize_log_data($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    return $data;
}

/**
 * Global debug logging function
 *
 * Checks if debug logging is enabled before logging. Automatically
 * sanitizes sensitive data to prevent credential exposure.
 *
 * @since 1.0.0
 * @param string $message The log message
 * @param mixed  $data    Optional data to include in the log
 */
// Removed duplicate function - using the new logging system below

/**
 * Encrypt sensitive data for storage
 *
 * @since 1.0.0
 * @param string $data The data to encrypt
 * @return string The encrypted data
 */
function shift8_treb_encrypt_data($data) {
    if (empty($data)) {
        return '';
    }
    
    // Use WordPress salts for encryption key
    $key = wp_salt('auth');
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data from storage
 *
 * @since 1.0.0
 * @param string $encrypted_data The encrypted data
 * @return string The decrypted data
 */
function shift8_treb_decrypt_data($encrypted_data) {
    if (empty($encrypted_data)) {
        return '';
    }
    
    // Check if this looks like a JWT token (not encrypted)
    if (strpos($encrypted_data, 'eyJ') === 0) {
        // This is a plain JWT token, return as-is
        return $encrypted_data;
    }
    
    // This should be encrypted data, try to decrypt it
    $key = wp_salt('auth');
    $data = base64_decode($encrypted_data);
    
    // Check if base64 decode was successful and we have enough data
    if ($data === false || strlen($data) < 16) {
        return '';
    }
    
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    
    // Return decrypted data, or empty string if decryption failed
    return $decrypted !== false ? $decrypted : '';
}

// Check for minimum PHP version
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Shift8 TREB Real Estate Listings requires PHP 7.4 or higher. Please upgrade PHP.', 'shift8-treb');
        echo '</p></div>';
    });
    return;
}

/**
 * Main plugin class
 *
 * Handles plugin initialization, AMPRE API integration,
 * and WordPress post management for TREB listings.
 *
 * @since 1.0.0
 */
class Shift8_TREB {
    
    /**
     * Plugin instance
     *
     * @since 1.0.0
     * @var Shift8_TREB|null
     */
    private static $instance = null;
    
    /**
     * Get plugin instance (Singleton pattern)
     *
     * @since 1.0.0
     * @return Shift8_TREB
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     *
     * Sets up plugin hooks and initialization.
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Note: No custom post types - we create regular posts like the Python script
        
        // Add custom cron schedules
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
        
        // Cron hooks for TREB data synchronization
        add_action('shift8_treb_sync_listings', array($this, 'sync_listings_cron'));
        
        // Admin hooks - register settings on admin_init regardless of is_admin() check
        add_action('admin_init', array($this, 'register_settings'));
        
        // Include WP-CLI commands if WP-CLI is available
        if (defined('WP_CLI') && WP_CLI) {
            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-cli.php';
        }
        
        // Hook for Google Maps script enqueue
        add_action('wp_enqueue_scripts', array($this, 'enqueue_google_maps_scripts'));
    }
    
    /**
     * Initialize plugin
     *
     * Loads textdomain and sets up integrations.
     *
     * @since 1.0.0
     */
    public function init() {
        shift8_treb_log('Plugin init() called');
        
        // Load text domain for translations
        load_plugin_textdomain('shift8-treb', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin functionality
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Manage cron scheduling based on plugin state
        $this->manage_cron_scheduling();
    }
    
    /**
     * Initialize admin functionality
     *
     * @since 1.0.0
     */
    public function init_admin() {
        require_once SHIFT8_TREB_PLUGIN_DIR . 'admin/class-shift8-treb-admin.php';
        new Shift8_TREB_Admin();
    }
    
    /**
     * Register plugin settings
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting(
            'shift8_treb_settings',
            'shift8_treb_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array(
                    'bearer_token' => '',
                    'sync_frequency' => 'daily',
                    'max_listings_per_query' => 100,
                    'debug_enabled' => '0',
                    'google_maps_api_key' => '',
                    'walkscore_id' => '',
                    'member_id' => '',
                    'excluded_member_ids' => '',
                    'listing_age_days' => '30',
                    'listing_template' => 'Property Details:\n\nAddress: %ADDRESS%\nPrice: %PRICE%\nMLS: %MLS%\nBedrooms: %BEDROOMS%\nBathrooms: %BATHROOMS%\nSquare Feet: %SQFT%\n\nDescription:\n%DESCRIPTION%'
                )
            )
        );
    }
    
    /**
     * Sanitize settings
     *
     * @since 1.0.0
     * @param array $input Raw input data
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize Bearer Token
        if (isset($input['bearer_token'])) {
            $token = trim($input['bearer_token']);
            if (!empty($token)) {
                $sanitized['bearer_token'] = shift8_treb_encrypt_data($token);
            } else {
                // Keep existing token if new one is empty
                $existing_settings = get_option('shift8_treb_settings', array());
                $sanitized['bearer_token'] = isset($existing_settings['bearer_token']) ? $existing_settings['bearer_token'] : '';
            }
        }
        
        // Sanitize sync frequency with extended options
        $allowed_frequencies = array('hourly', 'shift8_treb_8hours', 'shift8_treb_12hours', 'daily', 'weekly', 'shift8_treb_biweekly', 'shift8_treb_monthly');
        if (isset($input['sync_frequency']) && in_array($input['sync_frequency'], $allowed_frequencies, true)) {
            $new_frequency = $input['sync_frequency'];
            $old_frequency = get_option('shift8_treb_settings')['sync_frequency'] ?? 'daily';
            
            // If frequency changed, reschedule cron
            if ($new_frequency !== $old_frequency) {
                wp_clear_scheduled_hook('shift8_treb_sync_listings');
                // Schedule will be recreated in init() method
            }
            
            $sanitized['sync_frequency'] = $new_frequency;
        } else {
            $sanitized['sync_frequency'] = 'daily';
        }
        
        // Sanitize max listings per query
        if (isset($input['max_listings_per_query'])) {
            $max_listings = absint($input['max_listings_per_query']);
            $sanitized['max_listings_per_query'] = max(1, min(1000, $max_listings)); // Between 1 and 1000
        }
        
        // Sanitize debug setting
        $sanitized['debug_enabled'] = isset($input['debug_enabled']) ? '1' : '0';
        
        // Sanitize Google Maps API key
        if (isset($input['google_maps_api_key'])) {
            $sanitized['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key']);
        }
        
        // Sanitize WalkScore ID (no API key needed)
        if (isset($input['walkscore_id'])) {
            $sanitized['walkscore_id'] = sanitize_text_field($input['walkscore_id']);
        }
        
        // Sanitize member ID (supports comma-separated values)
        if (isset($input['member_id'])) {
            $member_ids = sanitize_text_field($input['member_id']);
            // Clean up comma-separated values: remove extra spaces, empty values
            $member_list = array_filter(array_map('trim', explode(',', $member_ids)));
            $sanitized['member_id'] = implode(',', $member_list);
        }
        
        // Sanitize excluded member IDs (supports comma-separated values)
        if (isset($input['excluded_member_ids'])) {
            $excluded_ids = sanitize_text_field($input['excluded_member_ids']);
            // Clean up comma-separated values: remove extra spaces, empty values
            $excluded_list = array_filter(array_map('trim', explode(',', $excluded_ids)));
            $sanitized['excluded_member_ids'] = implode(',', $excluded_list);
        }
        
        // Sanitize listing age days
        if (isset($input['listing_age_days'])) {
            $listing_age = absint($input['listing_age_days']);
            $sanitized['listing_age_days'] = max(1, min(365, $listing_age)); // Between 1 and 365 days
        }
        
        if (isset($input['listing_template'])) {
            $sanitized['listing_template'] = wp_kses_post($input['listing_template']);
        }
        
        // Add success message
        add_settings_error(
            'shift8_treb_settings',
            'settings_updated',
            esc_html__('Settings saved successfully.', 'shift8-treb'),
            'updated'
        );
        
        return $sanitized;
    }
    
    
    /**
     * Add custom cron schedules
     *
     * @since 1.0.0
     * @param array $schedules Existing cron schedules
     * @return array Modified cron schedules
     */
    public function add_custom_cron_schedules($schedules) {
        // Only register the schedule that's currently being used
        $settings = get_option('shift8_treb_settings', array());
        $sync_frequency = isset($settings['sync_frequency']) ? $settings['sync_frequency'] : 'daily';
        
        // Define all available custom schedules
        $custom_schedules = array(
            'shift8_treb_8hours' => array(
                'interval' => 8 * HOUR_IN_SECONDS,
                'display'  => esc_html__('Every 8 Hours', 'shift8-treb')
            ),
            'shift8_treb_12hours' => array(
                'interval' => 12 * HOUR_IN_SECONDS,
                'display'  => esc_html__('Every 12 Hours', 'shift8-treb')
            ),
            'shift8_treb_biweekly' => array(
                'interval' => 2 * 7 * DAY_IN_SECONDS,
                'display'  => esc_html__('Bi-Weekly (Every 2 Weeks)', 'shift8-treb')
            ),
            'shift8_treb_monthly' => array(
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => esc_html__('Monthly', 'shift8-treb')
            )
        );
        
        // Only register the schedule that's currently being used
        if (isset($custom_schedules[$sync_frequency])) {
            $schedules[$sync_frequency] = $custom_schedules[$sync_frequency];
            shift8_treb_log('Registered cron schedule', array(
                'schedule' => $sync_frequency,
                'interval' => $custom_schedules[$sync_frequency]['interval']
            ));
        }
        
        return $schedules;
    }
    
    /**
     * Schedule TREB data synchronization
     *
     * Replaces the Python cron job: 15 10 * * * (daily at 10:15 AM)
     *
     * @since 1.0.0
     */
    public function schedule_sync() {
        $settings = get_option('shift8_treb_settings', array());
        $sync_frequency = isset($settings['sync_frequency']) ? $settings['sync_frequency'] : 'daily';
        
        // Schedule the sync event
        wp_schedule_event(time(), $sync_frequency, 'shift8_treb_sync_listings');
        
        shift8_treb_log('TREB sync scheduled', array(
            'frequency' => $sync_frequency,
            'next_run' => wp_next_scheduled('shift8_treb_sync_listings')
        ));
        
        return true;
    }
    
    /**
     * Manage cron scheduling based on plugin state
     * 
     * Following shift8-zoom pattern for proper cron management
     *
     * @since 1.0.0
     */
    public function manage_cron_scheduling() {
        $settings = get_option('shift8_treb_settings', array());
        $bearer_token = isset($settings['bearer_token']) ? $settings['bearer_token'] : '';
        $sync_frequency = isset($settings['sync_frequency']) ? $settings['sync_frequency'] : 'daily';
        
        // Check if sync is enabled (has required settings)
        $sync_enabled = !empty($bearer_token);
        
        if ($sync_enabled) {
            $next_scheduled = wp_next_scheduled('shift8_treb_sync_listings');
            
            if (!$next_scheduled) {
                // No cron scheduled, create one
                $this->schedule_sync();
                shift8_treb_log('TREB cron scheduled', array(
                    'frequency' => $sync_frequency,
                    'next_run' => wp_next_scheduled('shift8_treb_sync_listings')
                ));
            } else {
                // Check if frequency matches current setting
                $current_cron = wp_get_scheduled_event('shift8_treb_sync_listings');
                if ($current_cron && $current_cron->schedule !== $sync_frequency) {
                    // Frequency changed, reschedule
                    wp_clear_scheduled_hook('shift8_treb_sync_listings');
                    $this->schedule_sync();
                    shift8_treb_log('TREB cron rescheduled', array(
                        'old_frequency' => $current_cron->schedule,
                        'new_frequency' => $sync_frequency,
                        'next_run' => wp_next_scheduled('shift8_treb_sync_listings')
                    ));
                }
            }
        } else {
            // Sync disabled, clear any existing cron
            if (wp_next_scheduled('shift8_treb_sync_listings')) {
                wp_clear_scheduled_hook('shift8_treb_sync_listings');
                shift8_treb_log('TREB cron cleared - sync disabled (no bearer token)');
            }
        }
    }
    
    /**
     * Cron job handler for syncing TREB listings
     *
     * This replaces the Python script functionality
     *
     * @since 1.0.0
     */
    public function sync_listings_cron() {
        shift8_treb_log('=== TREB CRON SYNC STARTED ===');
        
        try {
            // Include and initialize sync service
            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
            $sync_service = new Shift8_TREB_Sync_Service();

            // Execute sync (cron uses incremental sync by default)
            $results = $sync_service->execute_sync(array(
                'dry_run' => false,
                'verbose' => false
            ));

            if (!$results['success']) {
                throw new Exception(esc_html($results['message']));
            }

            shift8_treb_log('=== TREB CRON SYNC COMPLETED ===', array(
                'total_listings' => $results['total_listings'],
                'processed' => $results['processed'],
                'created' => $results['created'],
                'updated' => $results['updated'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors']
            ));
            
        } catch (Exception $e) {
            shift8_treb_log('TREB cron sync failed', array(
                'error' => esc_html($e->getMessage())
            ), 'error');
        }
    }
    
    /**
     * Plugin activation
     *
     * Sets up default options and performs initial setup.
     *
     * @since 1.0.0
     */
    public function activate() {
        // Set default options if they don't exist
        if (!get_option('shift8_treb_settings')) {
            add_option('shift8_treb_settings', array(
                'ampre_api_token' => '',
                'sync_frequency' => 'daily',
                'debug_enabled' => '0',
                'google_maps_api_key' => '',
                'listing_status_filter' => 'Active',
                'city_filter' => 'Toronto',
                'max_listings_per_sync' => 100
            ));
        }
        
        // Create uploads directory for logs if it doesn't exist
        $upload_dir = wp_upload_dir();
        if (!is_dir($upload_dir['basedir'])) {
            wp_mkdir_p($upload_dir['basedir']);
        }
        
        // No custom post types needed - we use regular WordPress posts like the Python script
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Schedule the sync cron job
        $this->schedule_sync();
        
        shift8_treb_log('Plugin activated');
    }
    
    /**
     * Plugin deactivation
     *
     * Cleans up scheduled events and temporary data.
     *
     * @since 1.0.0
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('shift8_treb_sync_listings');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        shift8_treb_log('Plugin deactivated');
    }
    
    /**
     * Enqueue Google Maps scripts for TREB listings
     *
     * @since 1.2.0
     */
    public function enqueue_google_maps_scripts() {
        // Only enqueue on single posts that are TREB listings
        if (!is_single() || !shift8_treb_is_listing_post()) {
            return;
        }
        
        // Only enqueue if we have Google Maps API key
        if (!shift8_treb_has_google_maps_api_key()) {
            return;
        }
        
        // Get current post data to check if it has coordinates
        $post_id = get_the_ID();
        $mls_number = get_post_meta($post_id, 'listing_mls_number', true);
        
        if (empty($mls_number)) {
            return;
        }
        
        // Get settings for API key
        $settings = get_option('shift8_treb_settings', array());
        $api_key = $settings['google_maps_api_key'];
        
        // Get listing coordinates from post content or meta
        $coordinates = $this->get_post_coordinates($post_id);
        
        if (!$coordinates) {
            return;
        }
        
        // Parse address for marker title
        $post_title = get_the_title($post_id);
        $address_parts = $this->parse_post_address($post_title);
        
        // Enqueue Google Maps API
        wp_enqueue_script(
            'shift8-treb-google-maps-api',
            'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&callback=shift8_treb_init_map',
            array(),
            SHIFT8_TREB_VERSION,
            true
        );
        
        // Add inline script with map initialization
        $map_script = sprintf("
function shift8_treb_init_map() {
    var mapElement = document.getElementById('shift8-treb-map');
    if (!mapElement) {
        return; // Map element not found on this page
    }
    
    var theLatLng = {lat: %s, lng: %s};
    var map = new google.maps.Map(mapElement, {
        center: theLatLng,
        zoom: 15
    });
    var marker = new google.maps.Marker({
        position: theLatLng,
        map: map,
        title: '%s'
    });
}",
            floatval($coordinates['lat']),
            floatval($coordinates['lng']),
            esc_js($address_parts['street_number'] . ' ' . $address_parts['street_name'])
        );
        
        wp_add_inline_script('shift8-treb-google-maps-api', $map_script, 'before');
    }
    
    /**
     * Get coordinates for a post
     *
     * @since 1.2.0
     * @param int $post_id Post ID
     * @return array|false Coordinates array or false
     */
    private function get_post_coordinates($post_id) {
        // Try to extract coordinates from post content
        $post_content = get_post_field('post_content', $post_id);
        
        // Look for latitude and longitude in the content (from template placeholders)
        if (preg_match('/lat:\s*([0-9.-]+)/', $post_content, $lat_matches) &&
            preg_match('/lng:\s*([0-9.-]+)/', $post_content, $lng_matches)) {
            return array(
                'lat' => $lat_matches[1],
                'lng' => $lng_matches[1]
            );
        }
        
        // Fallback to Toronto coordinates if not found
        return array(
            'lat' => '43.6532',
            'lng' => '-79.3832'
        );
    }
    
    /**
     * Parse address from post title
     *
     * @since 1.2.0
     * @param string $title Post title (address)
     * @return array Address components
     */
    private function parse_post_address($title) {
        $parts = array(
            'street_number' => '',
            'street_name' => ''
        );
        
        if (empty($title)) {
            return $parts;
        }
        
        // Extract street number (first number in the title)
        if (preg_match('/^(\d+)/', trim($title), $matches)) {
            $parts['street_number'] = $matches[1];
            $title = preg_replace('/^\d+\s*/', '', $title);
        }
        
        // Remaining is street name (clean up extra info)
        $parts['street_name'] = trim(preg_replace('/,.*$/', '', $title));
        
        return $parts;
    }
}


/**
 * Main logging function
 *
 * @since 1.0.0
 * @param string $message Log message
 * @param array $context Additional context data
 * @param string $level Log level (info, warning, error)
 */
function shift8_treb_log($message, $context = array(), $level = 'info') {
    $settings = get_option('shift8_treb_settings', array());
    
    // Always log if debug is enabled OR if it's an error/warning
    if (empty($settings['debug_enabled']) || $settings['debug_enabled'] !== '1') {
        if (!in_array($level, array('error', 'warning'))) {
            return;
        }
    }
    
    $timestamp = current_time('Y-m-d H:i:s');
    $context_str = '';
    
    if (!empty($context)) {
        $context_str = ' | Context: ' . wp_json_encode($context);
    }
    
    $log_entry = sprintf('[%s] [%s] %s%s', $timestamp, strtoupper($level), $message, $context_str);
    
    // Write to WordPress debug log if available
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SHIFT8-TREB: ' . $log_entry);
        }
    }
    
    // Write to our custom log file
    shift8_treb_write_log_file($log_entry);
}

/**
 * Write to custom log file
 *
 * @since 1.0.0
 * @param string $log_entry Formatted log entry
 */
function shift8_treb_write_log_file($log_entry) {
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/shift8-treb-logs';
    
    // Create log directory if it doesn't exist
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
        
        // Add .htaccess to protect log files
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents($log_dir . '/.htaccess', $htaccess_content);
    }
    
    $log_file = $log_dir . '/treb-sync.log';
    
    // Rotate log if it gets too large (5MB)
    if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
        $backup_file = $log_dir . '/treb-sync-' . date('Y-m-d-H-i-s') . '.log';
        rename($log_file, $backup_file);
        
        // Keep only last 5 backup files
        $backup_files = glob($log_dir . '/treb-sync-*.log');
        if (count($backup_files) > 5) {
            usort($backup_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            for ($i = 0; $i < count($backup_files) - 5; $i++) {
                unlink($backup_files[$i]);
            }
        }
    }
    
    // Write log entry
    file_put_contents($log_file, $log_entry . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Get recent log entries
 *
 * @since 1.0.0
 * @param int $lines Number of lines to retrieve
 * @return array Log entries
 */
function shift8_treb_get_logs($lines = 100) {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/shift8-treb-logs/treb-sync.log';
    
    if (!file_exists($log_file)) {
        return array('No log file found. Enable debug mode and run a sync to generate logs.');
    }
    
    $log_content = file_get_contents($log_file);
    if (empty($log_content)) {
        return array('Log file is empty.');
    }
    
    $log_lines = explode("\n", trim($log_content));
    
    // Get last N lines
    if (count($log_lines) > $lines) {
        $log_lines = array_slice($log_lines, -$lines);
    }
    
    return array_reverse($log_lines); // Most recent first
}

/**
 * Clear log files
 *
 * @since 1.0.0
 * @return bool Success
 */
function shift8_treb_clear_logs() {
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/shift8-treb-logs';
    
    if (!file_exists($log_dir)) {
        return true;
    }
    
    $log_files = glob($log_dir . '/*.log');
    foreach ($log_files as $file) {
        unlink($file);
    }
    
    shift8_treb_log('Log files cleared by user', array(), 'info');
    return true;
}

/**
 * Check if Google Maps API key is configured
 *
 * @since 1.2.0
 * @return bool True if API key is configured
 */
function shift8_treb_has_google_maps_api_key() {
    $settings = get_option('shift8_treb_settings', array());
    return !empty($settings['google_maps_api_key']);
}

/**
 * Check if listing has valid coordinates
 *
 * @since 1.2.0
 * @param array $listing Listing data
 * @return bool True if listing has coordinates or can be geocoded
 */
function shift8_treb_has_listing_coordinates($listing) {
    // Check if AMPRE API provided coordinates
    if (isset($listing['Latitude']) && isset($listing['Longitude']) && 
        !empty($listing['Latitude']) && !empty($listing['Longitude'])) {
        return true;
    }
    
    // Check if we have an address for geocoding and Google Maps API key
    if (!empty($listing['UnparsedAddress']) && shift8_treb_has_google_maps_api_key()) {
        return true;
    }
    
    return false;
}

/**
 * Check if current post is a TREB listing
 *
 * @since 1.2.0
 * @param int $post_id Optional post ID, defaults to current post
 * @return bool True if post is a TREB listing
 */
function shift8_treb_is_listing_post($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    if (!$post_id) {
        return false;
    }
    
    // Check if post has MLS number meta (indicates it's a TREB listing)
    $mls_number = get_post_meta($post_id, 'listing_mls_number', true);
    return !empty($mls_number);
}

// Initialize plugin
shift8_treb_log('Plugin file loaded, initializing...');
Shift8_TREB::get_instance();
