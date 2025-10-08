<?php
/**
 * Main Plugin Class tests using Brain/Monkey
 *
 * @package Shift8\TREB\Tests\Unit
 */

namespace Shift8\TREB\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Test the main Shift8_TREB class methods using Brain/Monkey
 */
class MainPluginTest extends TestCase {

    /**
     * Plugin instance for testing
     *
     * @var Shift8_TREB
     */
    protected $plugin;

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Setup global test options
        global $_test_options;
        $_test_options = array();
        
        // Mock basic WordPress functions
        Functions\when('plugin_dir_path')->justReturn('/test/plugin/path/');
        Functions\when('plugin_dir_url')->justReturn('http://example.com/wp-content/plugins/shift8-treb/');
        Functions\when('plugin_basename')->justReturn('shift8-treb/shift8-treb.php');
        
        // Get plugin instance
        $this->plugin = \Shift8_TREB::get_instance();
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test plugin singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = \Shift8_TREB::get_instance();
        $instance2 = \Shift8_TREB::get_instance();
        
        $this->assertSame($instance1, $instance2, 'Plugin should follow singleton pattern');
        $this->assertInstanceOf('Shift8_TREB', $instance1, 'Instance should be of correct type');
    }

    /**
     * Test plugin initialization
     */
    public function test_plugin_init() {
        // Mock get_option for debug logging
        Functions\when('get_option')
            ->justReturn(array('debug_enabled' => '0'));
        
        // Mock is_admin
        Functions\when('is_admin')->justReturn(false);
        
        // Mock add_action calls
        Functions\when('add_action')->justReturn(true);
        
        // Mock load_plugin_textdomain
        Functions\when('load_plugin_textdomain')->justReturn(true);
        
        // Mock wp_next_scheduled
        Functions\when('wp_next_scheduled')->justReturn(false);
        
        // Mock wp_schedule_event
        Functions\when('wp_schedule_event')->justReturn(true);
        
        // Test that init can be called without errors
        $this->plugin->init();
        
        $this->assertTrue(true, 'Plugin init completed without errors');
    }

    /**
     * Test plugin activation
     */
    public function test_plugin_activation() {
        // Mock get_option to return false (no existing settings)
        Functions\when('get_option')
            ->justReturn(false);
        
        // Mock add_option
        Functions\when('add_option')
            ->justReturn(true);
        
        // Mock wp_upload_dir
        Functions\when('wp_upload_dir')
            ->justReturn(array('basedir' => '/tmp/uploads'));
        
        // Mock directory operations
        Functions\when('is_dir')->justReturn(false);
        Functions\when('wp_mkdir_p')->justReturn(true);
        
        // Mock flush_rewrite_rules
        Functions\when('flush_rewrite_rules')->justReturn(true);
        Functions\when('wp_schedule_event')->justReturn(true);
        Functions\when('wp_next_scheduled')->justReturn(false);
        
        // Test activation
        $this->plugin->activate();
        
        $this->assertTrue(true, 'Plugin activation completed without errors');
    }

    /**
     * Test plugin deactivation
     */
    public function test_plugin_deactivation() {
        // Mock get_option for debug logging
        Functions\when('get_option')
            ->justReturn(array('debug_enabled' => '0'));
        
        // Mock wp_clear_scheduled_hook
        Functions\when('wp_clear_scheduled_hook')
            ->justReturn(true);
        
        // Mock flush_rewrite_rules
        Functions\when('flush_rewrite_rules')->justReturn(true);
        
        // Test deactivation
        $this->plugin->deactivate();
        
        $this->assertTrue(true, 'Plugin deactivation completed without errors');
    }

    /**
     * Test custom cron schedules
     */
    public function test_custom_cron_schedules() {
        // Mock esc_html__
        Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });
        
        // Mock get_option to return specific sync frequency
        Functions\when('get_option')->justReturn(array(
            'sync_frequency' => 'shift8_treb_8hours'
        ));
        
        $existing_schedules = array(
            'hourly' => array('interval' => 3600, 'display' => 'Once Hourly'),
            'daily' => array('interval' => 86400, 'display' => 'Once Daily')
        );
        
        $result = $this->plugin->add_custom_cron_schedules($existing_schedules);
        
        $this->assertIsArray($result, 'Should return array');
        $this->assertArrayHasKey('shift8_treb_8hours', $result, 'Should add only the active schedule (shift8_treb_8hours)');
        $this->assertArrayNotHasKey('shift8_treb_12hours', $result, 'Should NOT add inactive schedules');
        $this->assertArrayNotHasKey('shift8_treb_biweekly', $result, 'Should NOT add inactive schedules');
        
        // Test specific schedule values
        $this->assertEquals(28800, $result['shift8_treb_8hours']['interval'], 'Eight hours should be 28800 seconds');
        $this->assertEquals('Every 8 Hours', $result['shift8_treb_8hours']['display'], 'Should have correct display name');
    }

    /**
     * Test settings registration
     */
    public function test_register_settings() {
        // Mock register_setting
        Functions\expect('register_setting')
            ->once()
            ->with('shift8_treb_settings', 'shift8_treb_settings', \Mockery::type('array'))
            ->andReturn(true);
        
        $this->plugin->register_settings();
        
        $this->assertTrue(true, 'Settings registration completed without errors');
    }

    /**
     * Test settings sanitization with valid data
     */
    public function test_sanitize_settings_valid_data() {
        // Mock add_settings_error for success message
        Functions\expect('add_settings_error')
            ->once()
            ->with('shift8_treb_settings', 'settings_updated', \Mockery::type('string'), 'updated')
            ->andReturn(true);
        
        // Mock sanitization functions
        Functions\when('sanitize_text_field')->alias(function($input) { return trim($input); });
        Functions\when('sanitize_email')->alias(function($input) { return filter_var($input, FILTER_SANITIZE_EMAIL); });
        Functions\when('esc_url_raw')->alias(function($input) { return filter_var($input, FILTER_SANITIZE_URL); });
        
        // Mock wp_salt
        Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
        Functions\when('wp_kses_post')->alias(function($content) { return strip_tags($content); });
        Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });
        Functions\when('get_option')->justReturn(array('sync_frequency' => 'daily'));
        Functions\when('wp_clear_scheduled_hook')->justReturn(true);
        
        $input = array(
            'bearer_token' => 'test_token_123',
            'sync_frequency' => 'shift8_treb_8hours',
            'max_listings_per_query' => '50',
            'debug_enabled' => '1',
            'google_maps_api_key' => 'AIza_test_key',
            'listing_status_filter' => 'Active',
            'city_filter' => 'Toronto',
            'property_type_filter' => 'Residential',
            'agent_filter' => '1525',
            'min_price' => '100000',
            'max_price' => '2000000',
            'listing_template' => 'Property: %ADDRESS%\nPrice: %PRICE%'
        );
        
        $result = $this->plugin->sanitize_settings($input);
        
        $this->assertIsArray($result, 'Should return sanitized array');
        $this->assertEquals('shift8_treb_8hours', $result['sync_frequency'], 'Should preserve valid sync frequency');
        $this->assertEquals('50', $result['max_listings_per_query'], 'Should preserve valid query limit');
        $this->assertEquals('1', $result['debug_enabled'], 'Should preserve debug setting');
    }

    /**
     * Test settings sanitization with invalid sync frequency
     */
    public function test_sanitize_settings_invalid_sync_frequency() {
        Functions\when('sanitize_text_field')->alias(function($input) { return trim($input); });
        Functions\when('add_settings_error')->justReturn(true);
        Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
        Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });
        Functions\when('wp_kses_post')->alias(function($content) { return strip_tags($content); });
        Functions\when('get_option')->justReturn(array('sync_frequency' => 'daily'));
        Functions\when('wp_clear_scheduled_hook')->justReturn(true);
        
        $input = array(
            'sync_frequency' => 'invalid_frequency',
            'bearer_token' => 'test_token'
        );
        
        $result = $this->plugin->sanitize_settings($input);
        
        $this->assertEquals('daily', $result['sync_frequency'], 'Should default to daily for invalid frequency');
    }

    /**
     * Test cron sync method with missing token
     */
    public function test_sync_listings_cron_missing_token() {
        // Mock get_option to return settings without bearer token
        Functions\when('get_option')
            ->justReturn(array('bearer_token' => ''));
        
        // Mock transient functions for sync locking
        Functions\when('get_transient')->justReturn(false); // No lock exists
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
        
        // Mock shift8_treb_log function
        Functions\when('esc_html')->alias(function($text) { return htmlspecialchars($text); });
        Functions\when('shift8_treb_log')->justReturn(true);
        
        $this->plugin->sync_listings_cron();
        
        $this->assertTrue(true, 'Should handle missing token gracefully');
    }

    /**
     * Test cron sync method with valid token
     */
    public function test_sync_listings_cron_with_token() {
        // Mock get_option to return settings with bearer token
        Functions\when('get_option')
            ->justReturn(array(
                'bearer_token' => base64_encode('encrypted_test_token'),
                'max_listings_per_query' => 100,
                'city_filter' => 'Toronto'
            ));
        
        // Mock transient functions for sync locking
        Functions\when('get_transient')->justReturn(false); // No lock exists
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
        
        // Mock decryption function
        Functions\when('shift8_treb_decrypt_token')
            ->justReturn('decrypted_test_token');
        
        // Mock shift8_treb_log function
        Functions\when('shift8_treb_log')->justReturn(true);
        Functions\when('esc_html')->alias(function($text) { return htmlspecialchars($text); });
        
        // Mock the service classes (they will be included in the sync method)
        Functions\when('class_exists')->justReturn(true);
        
        $this->plugin->sync_listings_cron();
        
        $this->assertTrue(true, 'Should handle sync with valid token');
    }

    /**
     * Test encryption and decryption functions
     */
    public function test_token_encryption_decryption() {
        // Mock wp_salt for encryption
        Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
        
        $original_token = 'test_bearer_token_12345';
        
        // Test encryption
        $encrypted = \shift8_treb_encrypt_data($original_token);
        $this->assertNotEquals($original_token, $encrypted, 'Token should be encrypted');
        $this->assertNotEmpty($encrypted, 'Encrypted token should not be empty');
        
        // Test decryption
        $decrypted = \shift8_treb_decrypt_data($encrypted);
        $this->assertEquals($original_token, $decrypted, 'Decrypted token should match original');
    }

    /**
     * Test logging functions
     */
    public function test_logging_functions() {
        // Mock wp_upload_dir
        Functions\when('wp_upload_dir')
            ->justReturn(array('basedir' => '/tmp/uploads'));
        
        // Mock file operations
        Functions\when('is_dir')->justReturn(true);
        Functions\when('wp_mkdir_p')->justReturn(true);
        
        // Mock global filesystem
        global $wp_filesystem;
        $wp_filesystem = new class {
            public function put_contents($file, $content) { return true; }
            public function exists($file) { return true; }
            public function get_contents($file) { return 'Test log entry'; }
        };
        
        // Mock file system functions
        Functions\when('is_dir')->justReturn(true);
        Functions\when('wp_mkdir_p')->justReturn(true);
        Functions\when('file_put_contents')->justReturn(100);
        Functions\when('file_get_contents')->justReturn('Test log entry');
        Functions\when('unlink')->justReturn(true);
        
        // Mock get_option for logging
        Functions\when('get_option')->justReturn(array('debug_enabled' => '1'));
        
        // Mock current_time
        Functions\when('current_time')->justReturn(date('Y-m-d H:i:s'));
        
        // Test logging
        \shift8_treb_log('Test message', array('context' => 'test'), 'info');
        
        // Test log retrieval
        $logs = \shift8_treb_get_logs(10);
        $this->assertIsArray($logs, 'Should return log content as array');
        
        // Test log clearing
        $result = \shift8_treb_clear_logs();
        $this->assertTrue($result, 'Should clear logs successfully');
        
        $this->assertTrue(true, 'Logging functions completed without errors');
    }

    /**
     * Test admin initialization
     */
    public function test_init_admin() {
        // Mock is_admin
        Functions\when('is_admin')->justReturn(true);
        
        // Mock file inclusion - just test it doesn't error
        $this->plugin->init_admin();
        
        $this->assertTrue(true, 'Admin initialization completed without errors');
    }

    /**
     * Test CLI initialization when WP_CLI is available
     */
    public function test_init_cli_when_available() {
        // Just test that WP_CLI constant can be defined
        if (!defined('WP_CLI')) {
            define('WP_CLI', true);
        }
        
        // Test CLI initialization (this happens in constructor when WP_CLI is defined)
        $this->assertTrue(defined('WP_CLI'), 'WP_CLI should be defined for CLI tests');
    }

    /**
     * Test cron scheduling management
     */
    public function test_manage_cron_scheduling() {
        // Mock required functions
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => 'test_token',
            'sync_frequency' => 'daily'
        ));
        Functions\when('wp_next_scheduled')->justReturn(false);
        Functions\when('wp_clear_scheduled_hook')->justReturn(true);
        Functions\when('wp_schedule_event')->justReturn(true);
        
        // Test scheduling when no cron exists
        $this->plugin->manage_cron_scheduling();
        
        // Test with existing cron that needs rescheduling
        Functions\when('wp_next_scheduled')->justReturn(time() + 3600);
        Functions\when('wp_get_scheduled_event')->justReturn((object) array('schedule' => 'hourly'));
        
        $this->plugin->manage_cron_scheduling();
        
        $this->assertTrue(true, 'Cron scheduling management completed without errors');
    }

    /**
     * Test schedule sync method
     */
    public function test_schedule_sync() {
        // Mock required functions
        Functions\when('get_option')->justReturn(array('sync_frequency' => 'daily'));
        Functions\when('wp_schedule_event')->justReturn(true);
        Functions\when('wp_next_scheduled')->justReturn(false);
        Functions\when('current_time')->justReturn(time());
        
        $result = $this->plugin->schedule_sync();
        
        $this->assertTrue($result, 'Should schedule sync successfully');
    }

    /**
     * Test settings sanitization edge cases
     */
    public function test_sanitize_settings_edge_cases() {
        // Mock required functions
        Functions\when('sanitize_text_field')->alias(function($input) { return trim($input); });
        Functions\when('esc_url_raw')->alias(function($input) { return filter_var($input, FILTER_SANITIZE_URL); });
        Functions\when('wp_kses_post')->alias(function($content) { return strip_tags($content); });
        Functions\when('get_option')->justReturn(array('sync_frequency' => 'daily'));
        Functions\when('wp_clear_scheduled_hook')->justReturn(true);
        Functions\when('add_settings_error')->justReturn(true);
        Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });
        
        // Test with empty input
        $result = $this->plugin->sanitize_settings(array());
        $this->assertIsArray($result, 'Should return array even with empty input');
        
        // Test with invalid member IDs (with spaces and empty values)
        $input = array(
            'member_id' => ' 123 , , 456 , ',
            'excluded_member_ids' => '789, ,  ,  999  '
        );
        $result = $this->plugin->sanitize_settings($input);
        $this->assertEquals('123,456', $result['member_id'], 'Should clean up member ID list');
        $this->assertEquals('789,999', $result['excluded_member_ids'], 'Should clean up excluded member ID list');
        
        // Test with extreme listing age values
        $input = array('listing_age_days' => '999');
        $result = $this->plugin->sanitize_settings($input);
        $this->assertEquals(365, $result['listing_age_days'], 'Should cap listing age at 365 days');
        
        $input = array('listing_age_days' => '-5');
        $result = $this->plugin->sanitize_settings($input);
        $this->assertEquals(5, $result['listing_age_days'], 'absint(-5) should return 5, then max(1, 5) returns 5');
    }

    /**
     * Test sanitization of new WalkScore and Google Maps settings
     */
    public function test_sanitize_walkscore_and_maps_settings() {
        // Mock required functions
        Functions\when('sanitize_text_field')->alias(function($input) { return trim($input); });
        Functions\when('add_settings_error')->justReturn(true);
        Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });

        $input = array(
            'google_maps_api_key' => '  AIzaSyTest123  ',
            'walkscore_id' => '  ws_12345  '
        );

        $result = $this->plugin->sanitize_settings($input);

        $this->assertEquals('AIzaSyTest123', $result['google_maps_api_key']);
        $this->assertEquals('ws_12345', $result['walkscore_id']);
    }

    /**
     * Test bearer token preservation when empty input provided
     */
    public function test_bearer_token_preservation() {
        // Mock existing settings with a token
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => 'existing_encrypted_token'
        ));
        Functions\when('add_settings_error')->justReturn(true);
        Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });

        $input = array(
            'bearer_token' => '', // Empty token should preserve existing
            'sync_frequency' => 'daily'
        );

        $result = $this->plugin->sanitize_settings($input);

        $this->assertEquals('existing_encrypted_token', $result['bearer_token']);
        $this->assertEquals('daily', $result['sync_frequency']);
    }

    /**
     * Test listing age days bounds checking
     */
    public function test_listing_age_days_bounds() {
        Functions\when('add_settings_error')->justReturn(true);
        Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });

        $test_cases = array(
            array('input' => 0, 'expected' => 1),     // Below minimum
            array('input' => 1, 'expected' => 1),     // At minimum
            array('input' => 30, 'expected' => 30),   // Normal value
            array('input' => 365, 'expected' => 365), // At maximum
            array('input' => 500, 'expected' => 365), // Above maximum
        );

        foreach ($test_cases as $case) {
            $input = array('listing_age_days' => $case['input']);
            $result = $this->plugin->sanitize_settings($input);
            $this->assertEquals($case['expected'], $result['listing_age_days'], 
                "Failed for input: {$case['input']}");
        }
    }
}
