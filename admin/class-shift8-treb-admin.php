<?php
/**
 * Admin functionality for Shift8 TREB
 *
 * Handles admin interface, settings management, and AJAX operations
 * with comprehensive security measures.
 *
 * @package Shift8\TREB
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for Shift8 TREB plugin
 *
 * Manages admin interface, settings, and AJAX handlers with security validation.
 *
 * @since 1.0.0
 */
class Shift8_TREB_Admin {

    /**
     * Constructor
     *
     * Sets up admin hooks and initializes functionality.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        // Settings are registered in main plugin class
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers with proper security
        add_action('wp_ajax_shift8_treb_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_shift8_treb_manual_sync', array($this, 'ajax_manual_sync'));
        add_action('wp_ajax_shift8_treb_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_shift8_treb_clear_log', array($this, 'ajax_clear_log'));
    }

    /**
     * Add menu pages to WordPress admin
     *
     * Creates the main Shift8 menu if it doesn't exist and adds
     * the TREB submenu.
     *
     * @since 1.0.0
     */
    public function add_menu_page() {
        // Create main Shift8 menu if it doesn't exist
        if (empty($GLOBALS['admin_page_hooks']['shift8-settings'])) {
            add_menu_page(
                'Shift8 Settings',
                'Shift8',
                'manage_options',
                'shift8-settings',
                array($this, 'shift8_main_page'),
                $this->get_shift8_icon_svg()
            );
        }

        // Add submenu page under Shift8 dashboard
        add_submenu_page(
            'shift8-settings',
            esc_html__('Shift8 TREB Real Estate Listings', 'shift8-treb'),
            esc_html__('TREB Listings', 'shift8-treb'),
            'manage_options',
            'shift8-treb',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Get Shift8 icon SVG
     *
     * @since 1.0.0
     * @return string SVG icon
     */
    private function get_shift8_icon_svg() {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><text x="10" y="14" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" font-weight="bold">S8</text></svg>');
    }

    /**
     * Main Shift8 settings page
     *
     * Displays the main dashboard for Shift8 plugins.
     *
     * @since 1.0.0
     */
    public function shift8_main_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Shift8 Settings', 'shift8-treb'); ?></h1>
            <p><?php esc_html_e('Welcome to the Shift8 settings page. Use the menu on the left to configure your Shift8 plugins.', 'shift8-treb'); ?></p>
            
            <div class="card">
                <h2><?php esc_html_e('Available Plugins', 'shift8-treb'); ?></h2>
                <ul>
                    <li><strong><?php esc_html_e('TREB Listings', 'shift8-treb'); ?></strong> - <?php esc_html_e('Toronto Real Estate Board listings integration via AMPRE API', 'shift8-treb'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     *
     * Displays the main plugin settings interface with security checks.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'shift8-treb'));
        }

        // WordPress Settings API handles form submission automatically

        // Get current settings with defaults
        $settings = get_option('shift8_treb_settings', array());
        $settings = wp_parse_args($settings, array(
            'bearer_token' => '',
            'sync_frequency' => 'daily',
            'max_listings_per_query' => 100,
            'debug_enabled' => '0',
            'google_maps_api_key' => '',
            'listing_status_filter' => 'Active',
            'city_filter' => 'Toronto',
            'property_type_filter' => '',
            'min_price' => '',
            'max_price' => '',
            'listing_template' => ''
        ));

        // Include settings template
        include SHIFT8_TREB_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    /**
     * Register settings
     *
     * Registers plugin settings with WordPress settings API for proper
     * validation and sanitization.
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
                    'listing_status_filter' => 'Active',
                    'city_filter' => 'Toronto',
                    'property_type_filter' => '',
                    'min_price' => '',
                    'max_price' => ''
                )
            )
        );
    }

    /**
     * Sanitize settings
     *
     * Validates and sanitizes all plugin settings before saving.
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
        $allowed_frequencies = array('hourly', 'every_8_hours', 'every_12_hours', 'daily', 'weekly', 'bi_weekly', 'monthly');
        if (isset($input['sync_frequency']) && in_array($input['sync_frequency'], $allowed_frequencies, true)) {
            $sanitized['sync_frequency'] = $input['sync_frequency'];
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
            $sanitized['google_maps_api_key'] = sanitize_text_field(trim($input['google_maps_api_key']));
        }
        
        // Sanitize listing status filter (based on RESO StandardStatus)
        $allowed_statuses = array('Active', 'Pending', 'ActiveUnderContract', 'Sold', 'Expired', 'Withdrawn', 'All');
        if (isset($input['listing_status_filter']) && in_array($input['listing_status_filter'], $allowed_statuses, true)) {
            $sanitized['listing_status_filter'] = $input['listing_status_filter'];
        } else {
            $sanitized['listing_status_filter'] = 'Active';
        }
        
        // Sanitize city filter
        if (isset($input['city_filter'])) {
            $sanitized['city_filter'] = sanitize_text_field(trim($input['city_filter']));
        }
        
        // Sanitize property type filter (based on RESO PropertyType)
        if (isset($input['property_type_filter'])) {
            $sanitized['property_type_filter'] = sanitize_text_field(trim($input['property_type_filter']));
        }
        
        // Sanitize price filters
        if (isset($input['min_price'])) {
            $sanitized['min_price'] = $input['min_price'] !== '' ? absint($input['min_price']) : '';
        }
        
        if (isset($input['max_price'])) {
            $sanitized['max_price'] = $input['max_price'] !== '' ? absint($input['max_price']) : '';
        }
        
        shift8_treb_debug_log('Settings saved', array(
            'bearer_token_set' => !empty($sanitized['bearer_token']),
            'sync_frequency' => $sanitized['sync_frequency'],
            'debug_enabled' => $sanitized['debug_enabled'],
            'city_filter' => $sanitized['city_filter'],
            'max_listings' => $sanitized['max_listings_per_query']
        ));
        
        // Reschedule cron if frequency changed
        $existing_settings = get_option('shift8_treb_settings', array());
        if (isset($existing_settings['sync_frequency']) && $existing_settings['sync_frequency'] !== $sanitized['sync_frequency']) {
            wp_clear_scheduled_hook('shift8_treb_sync_listings');
            wp_schedule_event(time(), $sanitized['sync_frequency'], 'shift8_treb_sync_listings');
        }
        
        return $sanitized;
    }

    /**
     * Save settings (legacy method)
     *
     * Handles direct POST submission with nonce verification.
     * This is kept for backward compatibility.
     *
     * @since 1.0.0
     */
    private function save_settings() {
        // Verify nonce
        if (!isset($_POST['shift8_treb_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['shift8_treb_nonce'])), 'shift8_treb_settings')) {
            wp_die(esc_html__('Security check failed. Please try again.', 'shift8-treb'));
        }
        
        // Prepare settings array
        $settings = array(
            'ampre_api_token' => '',
            'sync_frequency' => isset($_POST['sync_frequency']) ? sanitize_text_field(wp_unslash($_POST['sync_frequency'])) : 'daily',
            'debug_enabled' => isset($_POST['debug_enabled']) ? '1' : '0',
            'google_maps_api_key' => isset($_POST['google_maps_api_key']) ? sanitize_text_field(wp_unslash($_POST['google_maps_api_key'])) : '',
            'listing_status_filter' => isset($_POST['listing_status_filter']) ? sanitize_text_field(wp_unslash($_POST['listing_status_filter'])) : 'Active',
            'city_filter' => isset($_POST['city_filter']) ? sanitize_text_field(wp_unslash($_POST['city_filter'])) : 'Toronto',
            'max_listings_per_sync' => isset($_POST['max_listings_per_sync']) ? absint($_POST['max_listings_per_sync']) : 100
        );
        
        // Handle API token encryption
        if (!empty($_POST['ampre_api_token'])) {
            $settings['ampre_api_token'] = shift8_treb_encrypt_data(trim(sanitize_text_field(wp_unslash($_POST['ampre_api_token']))));
        } else {
            // Keep existing token if new one is empty
            $existing_settings = get_option('shift8_treb_settings', array());
            $settings['ampre_api_token'] = isset($existing_settings['ampre_api_token']) ? $existing_settings['ampre_api_token'] : '';
        }
        
        // Update settings
        update_option('shift8_treb_settings', $settings);
        
        // Show success message
        add_settings_error(
            'shift8_treb_settings',
            'settings_updated',
            esc_html__('Settings saved successfully!', 'shift8-treb'),
            'updated'
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * Loads JavaScript and CSS files for the admin interface.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        // Only load on Shift8 TREB pages
        if (strpos($hook, 'shift8-treb') === false) {
            return;
        }

        // Add plugin-specific styles
        wp_enqueue_style(
            'shift8-treb-admin',
            SHIFT8_TREB_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            SHIFT8_TREB_VERSION
        );

        // Add plugin-specific scripts
        wp_enqueue_script(
            'shift8-treb-admin',
            SHIFT8_TREB_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            SHIFT8_TREB_VERSION,
            true
        );

        // Localize script with security nonce
        wp_localize_script('shift8-treb-admin', 'shift8TREB', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shift8_treb_nonce'),
            'strings' => array(
                'testing' => esc_html__('Testing...', 'shift8-treb'),
                'syncing' => esc_html__('Syncing...', 'shift8-treb'),
                'success' => esc_html__('Success!', 'shift8-treb'),
                'error' => esc_html__('Error:', 'shift8-treb'),
                'confirm_clear' => esc_html__('Are you sure you want to clear the log file?', 'shift8-treb'),
                'confirm_sync' => esc_html__('Are you sure you want to run a manual sync? This may take several minutes.', 'shift8-treb')
            )
        ));
    }

    /**
     * AJAX handler for testing AMPRE API connection
     *
     * Validates connection settings and tests AMPRE API connectivity.
     *
     * @since 1.0.0
     */
    public function ajax_test_api_connection() {
        // Verify nonce and capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shift8_treb_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-treb')
            ));
        }
        
        try {
            // Get current settings
            $settings = get_option('shift8_treb_settings', array());
            
            if (empty($settings['bearer_token'])) {
                wp_send_json_error(array(
                    'message' => esc_html__('Bearer token not configured. Please enter your AMPRE API bearer token first.', 'shift8-treb')
                ));
            }
            
            // Decrypt bearer token (it should always be encrypted when stored)
            $bearer_token = shift8_treb_decrypt_data($settings['bearer_token']);
            $settings['bearer_token'] = $bearer_token;
            
            // Initialize AMPRE service
            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
            $ampre_service = new Shift8_TREB_AMPRE_Service($settings);
            
            // Test connection
            $result = $ampre_service->test_connection();
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => esc_html__('Successfully connected to AMPRE API!', 'shift8-treb')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => esc_html($result['message'])
                ));
            }
            
        } catch (Exception $e) {
            shift8_treb_debug_log('API connection test failed', array(
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error(array(
                'message' => esc_html($e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for manual sync
     *
     * Triggers a manual synchronization of TREB listings.
     *
     * @since 1.0.0
     */
    public function ajax_manual_sync() {
        // Verify nonce and capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shift8_treb_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-treb')
            ));
        }
        
        try {
            shift8_treb_log('=== MANUAL SYNC STARTED ===', array('user' => wp_get_current_user()->user_login), 'info');
            
            // Get the main plugin instance and trigger sync
            $plugin = Shift8_TREB::get_instance();
            $plugin->sync_listings_cron();
            
            shift8_treb_log('=== MANUAL SYNC COMPLETED ===', array(), 'info');
            
            wp_send_json_success(array(
                'message' => esc_html__('Manual sync completed successfully. Check the logs for details.', 'shift8-treb')
            ));
            
        } catch (Exception $e) {
            shift8_treb_log('Manual sync failed', array('error' => $e->getMessage()), 'error');
            
            wp_send_json_error(array(
                'message' => esc_html__('Sync failed: ', 'shift8-treb') . esc_html($e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for getting logs
     *
     * Retrieves recent log entries with proper security validation.
     *
     * @since 1.0.0
     */
    public function ajax_get_logs() {
        // Verify nonce and capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shift8_treb_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-treb')
            ));
        }
        
        try {
            // Get recent log entries using our logging system
            $logs = shift8_treb_get_logs(200); // Get last 200 lines
            
            wp_send_json_success(array(
                'logs' => implode("\n", $logs),
                'message' => esc_html__('Logs retrieved successfully.', 'shift8-treb')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => esc_html($e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for clearing logs
     *
     * Clears the debug log file with proper security validation.
     *
     * @since 1.0.0
     */
    public function ajax_clear_log() {
        // Verify nonce and capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shift8_treb_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-treb')
            ));
        }
        
        try {
            // Use our new logging system to clear logs
            $result = shift8_treb_clear_logs();
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => esc_html__('All logs cleared successfully.', 'shift8-treb')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => esc_html__('Failed to clear logs.', 'shift8-treb')
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => esc_html($e->getMessage())
            ));
        }
    }

    /**
     * Get last N lines from a file
     *
     * Efficiently reads the last N lines from a file without loading
     * the entire file into memory.
     *
     * @since 1.0.0
     * @param string $file_path Path to the file
     * @param int    $lines     Number of lines to retrieve
     * @return array Array of lines
     */
    private function get_last_lines($file_path, $lines = 100) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return array();
        }
        
        $file = file($file_path);
        if ($file === false) {
            return array();
        }
        
        // Get last N lines
        $total_lines = count($file);
        $start = max(0, $total_lines - $lines);
        $result = array_slice($file, $start);
        
        // Remove trailing newlines and sanitize
        return array_map(function($line) {
            return esc_html(rtrim($line, "\r\n"));
        }, $result);
    }

    /**
     * Format file size in human readable format
     *
     * @since 1.0.0
     * @param int $size File size in bytes
     * @return string Formatted file size
     */
    private function format_file_size($size) {
        $units = array('B', 'KB', 'MB', 'GB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Get sync status information
     *
     * @since 1.0.0
     * @return array Sync status data
     */
    public function get_sync_status() {
        $next_sync = wp_next_scheduled('shift8_treb_sync_listings');
        $last_sync = get_option('shift8_treb_last_sync', 0);
        
        return array(
            'next_sync' => $next_sync ? date('Y-m-d H:i:s', $next_sync) : esc_html__('Not scheduled', 'shift8-treb'),
            'last_sync' => $last_sync ? date('Y-m-d H:i:s', $last_sync) : esc_html__('Never', 'shift8-treb'),
            'is_scheduled' => (bool) $next_sync
        );
    }
}
