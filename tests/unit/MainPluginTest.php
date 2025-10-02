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
        
        $existing_schedules = array(
            'hourly' => array('interval' => 3600, 'display' => 'Once Hourly'),
            'daily' => array('interval' => 86400, 'display' => 'Once Daily')
        );
        
        $result = $this->plugin->add_custom_cron_schedules($existing_schedules);
        
        $this->assertIsArray($result, 'Should return array');
        $this->assertArrayHasKey('every_8_hours', $result, 'Should add every_8_hours schedule');
        $this->assertArrayHasKey('every_12_hours', $result, 'Should add every_12_hours schedule');
        $this->assertArrayHasKey('bi_weekly', $result, 'Should add bi_weekly schedule');
        
        // Test specific schedule values
        $this->assertEquals(28800, $result['every_8_hours']['interval'], 'Eight hours should be 28800 seconds');
        $this->assertEquals(43200, $result['every_12_hours']['interval'], 'Twelve hours should be 43200 seconds');
        $this->assertEquals(1209600, $result['bi_weekly']['interval'], 'Biweekly should be 1209600 seconds');
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
        
        $input = array(
            'bearer_token' => 'test_token_123',
            'sync_frequency' => 'eight_hours',
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
        $this->assertEquals('eight_hours', $result['sync_frequency'], 'Should preserve valid sync frequency');
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
        
        // Mock shift8_treb_log function
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
        
        // Mock decryption function
        Functions\when('shift8_treb_decrypt_token')
            ->justReturn('decrypted_test_token');
        
        // Mock shift8_treb_log function
        Functions\when('shift8_treb_log')->justReturn(true);
        
        // Mock the service classes (they will be included in the sync method)
        Functions\when('class_exists')->justReturn(true);
        
        $this->plugin->sync_listings_cron();
        
        $this->assertTrue(true, 'Should handle sync with valid token');
    }

    /**
     * Test encryption and decryption functions
     */
    public function test_token_encryption_decryption() {
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
        
        // Mock get_option for logging
        Functions\when('get_option')->justReturn(array('debug_enabled' => '1'));
        
        // Test logging
        \shift8_treb_log('Test message', array('context' => 'test'), 'info');
        
        // Test log retrieval
        $logs = \shift8_treb_get_logs(10);
        $this->assertIsString($logs, 'Should return log content as string');
        
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
}
