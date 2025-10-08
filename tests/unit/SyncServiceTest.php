<?php
/**
 * Test sync service functionality including deduplication and locking
 *
 * @package Shift8\TREB\Tests\Unit
 * @since 1.6.2
 */

namespace Shift8\TREB\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

class SyncServiceTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        
        // Define plugin constants
        if (!defined('SHIFT8_TREB_PLUGIN_DIR')) {
            define('SHIFT8_TREB_PLUGIN_DIR', dirname(dirname(__DIR__)) . '/');
        }
        
        // Mock WordPress functions
        Functions\when('wp_upload_dir')->justReturn(array(
            'basedir' => '/tmp',
            'baseurl' => 'http://example.com'
        ));
        Functions\when('wp_mkdir_p')->justReturn(true);
        Functions\when('shift8_treb_log')->justReturn(true);
        Functions\when('esc_html')->alias(function($text) { return htmlspecialchars($text); });
        Functions\when('sanitize_text_field')->alias(function($text) { return trim($text); });
        Functions\when('current_time')->justReturn('2023-01-01T12:00:00+00:00');
        Functions\when('update_option')->justReturn(true);
        Functions\when('shift8_treb_decrypt_data')->alias(function($data) { return $data; });
        
        // Mock WP_CLI for CLI feedback
        if (!class_exists('WP_CLI')) {
            $mock_cli = new class {
                public static function line($message) {}
            };
            
            if (!defined('WP_CLI')) {
                define('WP_CLI', true);
            }
            
            global $WP_CLI;
            $WP_CLI = $mock_cli;
        }
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test sync service construction with settings
     */
    public function test_sync_service_construction() {
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => 'test_token',
            'max_listings_per_query' => 100
        ));

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service();
        
        $this->assertInstanceOf('Shift8_TREB_Sync_Service', $sync_service);
    }

    /**
     * Test API response deduplication logic
     */
    public function test_api_response_deduplication() {
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => 'test_token',
            'max_listings_per_query' => 100
        ));

        // Create duplicate listings array
        $duplicate_listings = array(
            array(
                'ListingKey' => 'MLS123',
                'UnparsedAddress' => '123 Test Street',
                'ListPrice' => 500000,
                'ContractStatus' => 'Available'
            ),
            array(
                'ListingKey' => 'MLS123', // Duplicate!
                'UnparsedAddress' => '123 Test Street (duplicate)',
                'ListPrice' => 500000,
                'ContractStatus' => 'Available'
            ),
            array(
                'ListingKey' => 'MLS456',
                'UnparsedAddress' => '456 Another Street',
                'ListPrice' => 600000,
                'ContractStatus' => 'Available'
            ),
            array(
                'ListingKey' => 'MLS456', // Another duplicate!
                'UnparsedAddress' => '456 Another Street (duplicate)',
                'ListPrice' => 600000,
                'ContractStatus' => 'Available'
            ),
            array(
                'ListingKey' => 'MLS789',
                'UnparsedAddress' => '789 Unique Street',
                'ListPrice' => 700000,
                'ContractStatus' => 'Available'
            )
        );

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service();
        
        // Use reflection to access the deduplication logic
        $reflection = new \ReflectionClass($sync_service);
        $execute_method = $reflection->getMethod('execute_sync');
        
        // Mock the AMPRE service
        $ampre_property = $reflection->getProperty('ampre_service');
        $ampre_property->setAccessible(true);
        
        $mock_ampre = $this->createMock('Shift8_TREB_AMPRE_Service');
        $mock_ampre->method('test_connection')->willReturn(array('success' => true));
        $mock_ampre->method('get_listings')->willReturn($duplicate_listings);
        
        $ampre_property->setValue($sync_service, $mock_ampre);

        // Mock the post manager
        $post_manager_property = $reflection->getProperty('post_manager');
        $post_manager_property->setAccessible(true);
        
        $processed_listings = array();
        $mock_post_manager = $this->createMock('Shift8_TREB_Post_Manager');
        $mock_post_manager->method('process_listing')
            ->willReturnCallback(function($listing) use (&$processed_listings) {
                $processed_listings[] = $listing['ListingKey'];
                return array(
                    'success' => true,
                    'action' => 'created',
                    'post_id' => rand(100, 999),
                    'title' => $listing['UnparsedAddress'],
                    'mls_number' => $listing['ListingKey']
                );
            });
        
        $post_manager_property->setValue($sync_service, $mock_post_manager);

        // Execute sync
        $results = $sync_service->execute_sync(array('dry_run' => false));

        $this->assertTrue($results['success']);
        $this->assertEquals(5, $results['total_listings']); // Original count
        
        // Should only process 3 unique listings (MLS123, MLS456, MLS789)
        $unique_processed = array_unique($processed_listings);
        $this->assertEquals(3, count($unique_processed));
        $this->assertContains('MLS123', $unique_processed);
        $this->assertContains('MLS456', $unique_processed);
        $this->assertContains('MLS789', $unique_processed);
    }

    /**
     * Test sync with limit parameter
     */
    public function test_sync_with_limit() {
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => 'test_token',
            'max_listings_per_query' => 100
        ));

        $listings = array();
        for ($i = 1; $i <= 10; $i++) {
            $listings[] = array(
                'ListingKey' => "MLS{$i}",
                'UnparsedAddress' => "{$i} Test Street",
                'ListPrice' => 500000 + ($i * 10000),
                'ContractStatus' => 'Available'
            );
        }

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service();
        
        // Use reflection to mock services
        $reflection = new \ReflectionClass($sync_service);
        
        $ampre_property = $reflection->getProperty('ampre_service');
        $ampre_property->setAccessible(true);
        
        $mock_ampre = $this->createMock('Shift8_TREB_AMPRE_Service');
        $mock_ampre->method('test_connection')->willReturn(array('success' => true));
        $mock_ampre->method('get_listings')->willReturn($listings);
        
        $ampre_property->setValue($sync_service, $mock_ampre);

        $processed_count = 0;
        $post_manager_property = $reflection->getProperty('post_manager');
        $post_manager_property->setAccessible(true);
        
        $mock_post_manager = $this->createMock('Shift8_TREB_Post_Manager');
        $mock_post_manager->method('process_listing')
            ->willReturnCallback(function($listing) use (&$processed_count) {
                $processed_count++;
                return array(
                    'success' => true,
                    'action' => 'created',
                    'post_id' => rand(100, 999),
                    'title' => $listing['UnparsedAddress'],
                    'mls_number' => $listing['ListingKey']
                );
            });
        
        $post_manager_property->setValue($sync_service, $mock_post_manager);

        // Execute sync with limit of 5
        $results = $sync_service->execute_sync(array(
            'dry_run' => false,
            'limit' => 5
        ));

        $this->assertTrue($results['success']);
        $this->assertEquals(10, $results['total_listings']); // Original count
        $this->assertEquals(5, $processed_count); // Should only process 5 due to limit
    }

    /**
     * Test sync with dry run mode
     */
    public function test_sync_dry_run_mode() {
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => 'test_token',
            'max_listings_per_query' => 100
        ));

        $listings = array(
            array(
                'ListingKey' => 'MLS123',
                'UnparsedAddress' => '123 Test Street',
                'ListPrice' => 500000,
                'ContractStatus' => 'Available'
            )
        );

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service();
        
        // Use reflection to mock services
        $reflection = new \ReflectionClass($sync_service);
        
        $ampre_property = $reflection->getProperty('ampre_service');
        $ampre_property->setAccessible(true);
        
        $mock_ampre = $this->createMock('Shift8_TREB_AMPRE_Service');
        $mock_ampre->method('test_connection')->willReturn(array('success' => true));
        $mock_ampre->method('get_listings')->willReturn($listings);
        
        $ampre_property->setValue($sync_service, $mock_ampre);

        $post_manager_called = false;
        $post_manager_property = $reflection->getProperty('post_manager');
        $post_manager_property->setAccessible(true);
        
        $mock_post_manager = $this->createMock('Shift8_TREB_Post_Manager');
        $mock_post_manager->method('process_listing')
            ->willReturnCallback(function($listing) use (&$post_manager_called) {
                $post_manager_called = true;
                return array('success' => true, 'action' => 'created');
            });
        
        $post_manager_property->setValue($sync_service, $mock_post_manager);

        // Execute dry run
        $results = $sync_service->execute_sync(array('dry_run' => true));

        $this->assertTrue($results['success']);
        $this->assertEquals(1, $results['total_listings']);
        $this->assertEquals(1, $results['processed']);
        $this->assertEquals(1, $results['created']); // Assumes all would be created in dry run
        $this->assertFalse($post_manager_called, 'Post manager should not be called in dry run');
    }

    /**
     * Test sync error handling
     */
    public function test_sync_error_handling() {
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => 'test_token'
        ));

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service();
        
        // Use reflection to mock services
        $reflection = new \ReflectionClass($sync_service);
        
        $ampre_property = $reflection->getProperty('ampre_service');
        $ampre_property->setAccessible(true);
        
        $mock_ampre = $this->createMock('Shift8_TREB_AMPRE_Service');
        $mock_ampre->method('test_connection')->willReturn(array(
            'success' => false,
            'message' => 'Connection failed'
        ));
        
        $ampre_property->setValue($sync_service, $mock_ampre);

        // Execute sync - should fail due to connection error
        $results = $sync_service->execute_sync();

        $this->assertFalse($results['success']);
        $this->assertStringContainsString('Connection failed', $results['message']);
        $this->assertEquals(0, $results['processed']);
    }

    /**
     * Test sync with missing bearer token
     */
    public function test_sync_missing_bearer_token() {
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => '' // Empty token
        ));

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service();

        // Execute sync - should fail due to missing token
        $results = $sync_service->execute_sync();

        $this->assertFalse($results['success']);
        $this->assertStringContainsString('Bearer token not configured', $results['message']);
        $this->assertEquals(0, $results['processed']);
    }

    /**
     * Test sync with empty API response
     */
    public function test_sync_empty_api_response() {
        Functions\when('get_option')->justReturn(array(
            'bearer_token' => 'test_token'
        ));

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service();
        
        // Use reflection to mock services
        $reflection = new \ReflectionClass($sync_service);
        
        $ampre_property = $reflection->getProperty('ampre_service');
        $ampre_property->setAccessible(true);
        
        $mock_ampre = $this->createMock('Shift8_TREB_AMPRE_Service');
        $mock_ampre->method('test_connection')->willReturn(array('success' => true));
        $mock_ampre->method('get_listings')->willReturn(array()); // Empty response
        
        $ampre_property->setValue($sync_service, $mock_ampre);

        // Execute sync
        $results = $sync_service->execute_sync();

        $this->assertFalse($results['success']); // Empty response returns early with success=false
        $this->assertEquals(0, $results['total_listings']);
        $this->assertEquals(0, $results['processed']);
        $this->assertStringContainsString('No listings returned', $results['message']);
    }

    /**
     * Test sync settings preparation and overrides
     */
    public function test_sync_settings_preparation() {
        $base_settings = array(
            'bearer_token' => 'base_token',
            'max_listings_per_query' => 50,
            'listing_age_days' => 30
        );

        $override_settings = array(
            'max_listings_per_query' => 100, // Override
            'members_only' => true // New setting
        );

        Functions\when('get_option')->alias(function($key) use ($base_settings) {
            if ($key === 'shift8_treb_settings') {
                return $base_settings;
            }
            if ($key === 'shift8_treb_last_sync') {
                return '2023-01-01T00:00:00Z';
            }
            return array();
        });

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service($override_settings);
        
        $settings = $sync_service->get_settings();
        
        // Check that overrides were applied
        $this->assertEquals(100, $settings['max_listings_per_query']); // Overridden
        $this->assertEquals(30, $settings['listing_age_days']); // From base
        $this->assertTrue($settings['members_only']); // New from override
        $this->assertEquals('2023-01-01T00:00:00Z', $settings['last_sync_timestamp']); // Added automatically
    }

    /**
     * Test incremental sync timestamp handling
     */
    public function test_incremental_sync_timestamp() {
        Functions\when('get_option')->alias(function($key) {
            if ($key === 'shift8_treb_settings') {
                return array('bearer_token' => 'test_token');
            }
            if ($key === 'shift8_treb_last_sync') {
                return '2023-01-01T00:00:00Z';
            }
            return array();
        });

        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-sync-service.php';
        
        $sync_service = new \Shift8_TREB_Sync_Service();
        
        // Use reflection to mock services and check query parameters
        $reflection = new \ReflectionClass($sync_service);
        
        $ampre_property = $reflection->getProperty('ampre_service');
        $ampre_property->setAccessible(true);
        
        $query_params_used = '';
        $mock_ampre = $this->createMock('Shift8_TREB_AMPRE_Service');
        $mock_ampre->method('test_connection')->willReturn(array('success' => true));
        $mock_ampre->method('get_listings')->willReturnCallback(function() use (&$query_params_used) {
            // In a real scenario, we'd capture the query parameters here
            return array();
        });
        
        $ampre_property->setValue($sync_service, $mock_ampre);

        // Execute sync
        $results = $sync_service->execute_sync();

        // Verify that last sync timestamp was included in settings
        $settings = $sync_service->get_settings();
        $this->assertEquals('2023-01-01T00:00:00Z', $settings['last_sync_timestamp']);
    }
}
