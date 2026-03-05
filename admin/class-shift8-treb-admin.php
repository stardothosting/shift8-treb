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
        add_action('wp_ajax_shift8_treb_reset_sync', array($this, 'ajax_reset_sync'));
        add_action('wp_ajax_shift8_treb_get_cities', array($this, 'ajax_get_cities'));
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
            esc_html__('Shift8 TREB Real Estate Listings', 'shift8-real-estate-listings-for-treb'),
            esc_html__('TREB Listings', 'shift8-real-estate-listings-for-treb'),
            'manage_options',
            'shift8-real-estate-listings-for-treb',
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
            <h1><?php esc_html_e('Shift8 Settings', 'shift8-real-estate-listings-for-treb'); ?></h1>
            <p><?php esc_html_e('Welcome to the Shift8 settings page. Use the menu on the left to configure your Shift8 plugins.', 'shift8-real-estate-listings-for-treb'); ?></p>
            
            <div class="card">
                <h2><?php esc_html_e('Available Plugins', 'shift8-real-estate-listings-for-treb'); ?></h2>
                <ul>
                    <li><strong><?php esc_html_e('TREB Listings', 'shift8-real-estate-listings-for-treb'); ?></strong> - <?php esc_html_e('Toronto Real Estate Board listings integration via AMPRE API', 'shift8-real-estate-listings-for-treb'); ?></li>
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'shift8-real-estate-listings-for-treb'));
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
            'city_filter' => '',
            'property_type_filter' => '',
            'min_price' => '',
            'max_price' => '',
            'geographic_filter_type' => '',
            'postal_code_prefixes' => '',
            'listing_template' => ''
        ));

        // Include settings template
        include SHIFT8_TREB_PLUGIN_DIR . 'admin/partials/settings-page.php';
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
        if (strpos($hook, 'shift8-real-estate-listings-for-treb') === false) {
            return;
        }

        // Add plugin-specific styles
        wp_enqueue_style(
            'shift8-treb-admin',
            SHIFT8_TREB_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            SHIFT8_TREB_VERSION
        );

        // jQuery UI Autocomplete ships with WordPress core
        wp_enqueue_script('jquery-ui-autocomplete');

        // Add plugin-specific scripts
        wp_enqueue_script(
            'shift8-treb-admin',
            SHIFT8_TREB_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'jquery-ui-autocomplete'),
            SHIFT8_TREB_VERSION,
            true
        );

        // Localize script with security nonce
        wp_localize_script('shift8-treb-admin', 'shift8TREB', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shift8_treb_nonce'),
            'strings' => array(
                'testing' => esc_html__('Testing...', 'shift8-real-estate-listings-for-treb'),
                'syncing' => esc_html__('Syncing...', 'shift8-real-estate-listings-for-treb'),
                'success' => esc_html__('Success!', 'shift8-real-estate-listings-for-treb'),
                'error' => esc_html__('Error:', 'shift8-real-estate-listings-for-treb'),
                'confirm_sync' => esc_html__('Are you sure you want to run a manual sync? This may take several minutes.', 'shift8-real-estate-listings-for-treb')
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
                'message' => esc_html__('Security check failed.', 'shift8-real-estate-listings-for-treb')
            ));
        }
        
        try {
            // Get current settings
            $settings = get_option('shift8_treb_settings', array());
            
            if (empty($settings['bearer_token'])) {
                wp_send_json_error(array(
                    'message' => esc_html__('Bearer token not configured. Please enter your AMPRE API bearer token first.', 'shift8-real-estate-listings-for-treb')
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
                    'message' => esc_html__('Successfully connected to AMPRE API!', 'shift8-real-estate-listings-for-treb')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => esc_html($result['message'])
                ));
            }
            
        } catch (Exception $e) {
            shift8_treb_log('API connection test failed', array(
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
                'message' => esc_html__('Security check failed.', 'shift8-real-estate-listings-for-treb')
            ));
        }
        
        // Check if sync is already running (prevent simultaneous syncs)
        $sync_lock = get_transient('shift8_treb_sync_lock');
        if ($sync_lock) {
            wp_send_json_error(array(
                'message' => esc_html__('Sync is already running. Please wait for it to complete.', 'shift8-real-estate-listings-for-treb')
            ));
        }
        
        try {
            // Set sync lock (expires in 10 minutes as safety)
            set_transient('shift8_treb_sync_lock', time(), 600);
            
            shift8_treb_log('=== MANUAL SYNC STARTED ===', array('user' => wp_get_current_user()->user_login), 'info');
            
            // Get the main plugin instance and trigger sync
            $plugin = Shift8_TREB::get_instance();
            $plugin->sync_listings_cron(true);
            
            shift8_treb_log('=== MANUAL SYNC COMPLETED ===', array(), 'info');
            
            wp_send_json_success(array(
                'message' => esc_html__('Manual sync completed successfully. Check the logs for details.', 'shift8-real-estate-listings-for-treb')
            ));
            
        } catch (Exception $e) {
            shift8_treb_log('Manual sync failed', array('error' => esc_html($e->getMessage())), 'error');
            
            wp_send_json_error(array(
                'message' => esc_html__('Sync failed: ', 'shift8-real-estate-listings-for-treb') . esc_html($e->getMessage())
            ));
        } finally {
            // Always clear the sync lock
            delete_transient('shift8_treb_sync_lock');
        }
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
            'next_sync' => $next_sync ? gmdate('Y-m-d H:i:s', $next_sync) : esc_html__('Not scheduled', 'shift8-real-estate-listings-for-treb'),
            'last_sync' => $last_sync ? gmdate('Y-m-d H:i:s', $last_sync) : esc_html__('Never', 'shift8-real-estate-listings-for-treb'),
            'is_scheduled' => (bool) $next_sync
        );
    }

    /**
     * AJAX handler for fetching city lookup values
     *
     * Returns cached city list from AMPRE Lookup API, refreshing if stale.
     * Accepts optional ?refresh=1 to force cache refresh.
     *
     * @since 1.7.0
     */
    public function ajax_get_cities() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shift8_treb_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'shift8-real-estate-listings-for-treb')));
        }

        $force_refresh = !empty($_POST['refresh']);
        $transient_key = 'shift8_treb_city_lookups';

        if (!$force_refresh) {
            $cached = get_transient($transient_key);
            if (false !== $cached && is_array($cached)) {
                wp_send_json_success(array('cities' => $cached, 'source' => 'cache'));
            }
        }

        try {
            $settings = get_option('shift8_treb_settings', array());

            if (empty($settings['bearer_token'])) {
                wp_send_json_error(array('message' => esc_html__('Bearer token not configured. Save your API credentials first.', 'shift8-real-estate-listings-for-treb')));
            }

            $bearer_token = shift8_treb_decrypt_data($settings['bearer_token']);
            $settings['bearer_token'] = $bearer_token;

            require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
            $service = new Shift8_TREB_AMPRE_Service($settings);
            $cities = $service->get_city_lookups();

            if (is_wp_error($cities)) {
                wp_send_json_error(array('message' => esc_html($cities->get_error_message())));
            }

            set_transient($transient_key, $cities, 30 * 86400);

            wp_send_json_success(array('cities' => $cities, 'source' => 'api', 'count' => count($cities)));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => esc_html($e->getMessage())));
        }
    }

    /**
     * AJAX handler for resetting sync mode
     *
     * @since 1.2.0
     */
    public function ajax_reset_sync() {
        // Verify nonce and capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shift8_treb_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-real-estate-listings-for-treb')
            ));
        }

        try {
            $last_sync = get_option('shift8_treb_last_sync', '');
            
            if (empty($last_sync)) {
                wp_send_json_success(array(
                    'message' => esc_html__('Incremental sync is already disabled.', 'shift8-real-estate-listings-for-treb')
                ));
                return;
            }

            // Delete the incremental sync timestamp
            delete_option('shift8_treb_last_sync');
            
            // Log the reset action
            shift8_treb_log('Incremental sync reset by user', array(
                'previous_timestamp' => esc_html($last_sync),
                'user_id' => get_current_user_id()
            ), 'info');

            wp_send_json_success(array(
                'message' => esc_html__('Sync mode reset successfully. Next sync will use age-based filtering.', 'shift8-real-estate-listings-for-treb')
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => esc_html__('Error resetting sync mode: ', 'shift8-real-estate-listings-for-treb') . esc_html($e->getMessage())
            ));
        }
    }
}
