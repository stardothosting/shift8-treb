<?php
/**
 * Tests for today's changes:
 * - Transaction type prefix in post titles
 * - Transaction type filtering
 * - Weekly cleanup job
 * - Terminated listings query
 *
 * @package Shift8\TREB\Tests\Unit
 */

namespace Shift8\TREB\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class TodaysChangesTest extends TestCase {

    protected $post_manager;
    protected $sync_service;
    protected $ampre_service;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock WordPress functions
        Functions\when('get_option')->justReturn(array());
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('wp_insert_post')->alias(function($args) {
            return 123; // Mock post ID
        });
        Functions\when('wp_update_post')->justReturn(123);
        Functions\when('get_posts')->justReturn(array());
        Functions\when('wp_trash_post')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('get_post')->justReturn((object) array('post_status' => 'publish'));
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('current_time')->justReturn('2025-10-30 10:00:00');
        Functions\when('wp_salt')->justReturn('test_salt_key_12345');

        // Include required files
        require_once dirname(__DIR__, 2) . '/includes/class-shift8-treb-post-manager.php';
        require_once dirname(__DIR__, 2) . '/includes/class-shift8-treb-ampre-service.php';
        require_once dirname(__DIR__, 2) . '/includes/class-shift8-treb-sync-service.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test transaction type prefix in post titles - For Sale
     */
    public function test_post_title_for_sale_prefix() {
        $listing_data = array(
            'ListingKey' => 'W12345678',
            'UnparsedAddress' => '123 Main Street, Toronto, ON M5H 2N2',
            'TransactionType' => 'For Sale',
            'City' => 'Toronto',
            'StateOrProvince' => 'ON',
            'ListPrice' => 500000,
            'PublicRemarks' => 'Test description',
            'BedroomsTotal' => 3,
            'BathroomsTotalInteger' => 2
        );

        $post_manager = new \Shift8_TREB_Post_Manager(array());
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($post_manager);
        $method = $reflection->getMethod('generate_post_title');
        $method->setAccessible(true);
        
        $title = $method->invoke($post_manager, $listing_data);
        
        $this->assertStringStartsWith('For Sale: ', $title, 'Title should start with "For Sale: "');
        $this->assertStringContainsString('123 Main Street', $title, 'Title should contain address');
    }

    /**
     * Test transaction type prefix in post titles - For Lease
     */
    public function test_post_title_for_lease_prefix() {
        $listing_data = array(
            'ListingKey' => 'W12345678',
            'UnparsedAddress' => '456 Queen Street W, Toronto, ON M5V 2A4',
            'TransactionType' => 'For Lease',
            'City' => 'Toronto',
            'StateOrProvince' => 'ON',
            'ListPrice' => 2500,
            'PublicRemarks' => 'Test description',
            'BedroomsTotal' => 2,
            'BathroomsTotalInteger' => 1
        );

        $post_manager = new \Shift8_TREB_Post_Manager(array());
        
        $reflection = new \ReflectionClass($post_manager);
        $method = $reflection->getMethod('generate_post_title');
        $method->setAccessible(true);
        
        $title = $method->invoke($post_manager, $listing_data);
        
        $this->assertStringStartsWith('For Lease: ', $title, 'Title should start with "For Lease: "');
        $this->assertStringContainsString('456 Queen Street W', $title, 'Title should contain address');
    }

    /**
     * Test transaction type prefix - For Sale or Lease
     */
    public function test_post_title_for_sale_or_lease_prefix() {
        $listing_data = array(
            'ListingKey' => 'W12345678',
            'UnparsedAddress' => '789 Bay Street, Toronto, ON M5G 2R3',
            'TransactionType' => 'For Sale or Lease',
            'City' => 'Toronto',
            'StateOrProvince' => 'ON',
            'ListPrice' => 750000,
            'PublicRemarks' => 'Test description',
            'BedroomsTotal' => 4,
            'BathroomsTotalInteger' => 3
        );

        $post_manager = new \Shift8_TREB_Post_Manager(array());
        
        $reflection = new \ReflectionClass($post_manager);
        $method = $reflection->getMethod('generate_post_title');
        $method->setAccessible(true);
        
        $title = $method->invoke($post_manager, $listing_data);
        
        $this->assertStringStartsWith('For Sale or Lease: ', $title, 'Title should start with "For Sale or Lease: "');
    }

    /**
     * Test post title without transaction type (should not have prefix)
     */
    public function test_post_title_no_transaction_type() {
        $listing_data = array(
            'ListingKey' => 'W12345678',
            'UnparsedAddress' => '999 Test Avenue, Toronto, ON M1M 1M1',
            'City' => 'Toronto',
            'StateOrProvince' => 'ON',
            'ListPrice' => 600000,
            'PublicRemarks' => 'Test description',
            'BedroomsTotal' => 3,
            'BathroomsTotalInteger' => 2
        );

        $post_manager = new \Shift8_TREB_Post_Manager(array());
        
        $reflection = new \ReflectionClass($post_manager);
        $method = $reflection->getMethod('generate_post_title');
        $method->setAccessible(true);
        
        $title = $method->invoke($post_manager, $listing_data);
        
        $this->assertStringNotContainsString('For Sale:', $title, 'Title should not have prefix without transaction type');
        $this->assertStringNotContainsString('For Lease:', $title, 'Title should not have prefix without transaction type');
        $this->assertStringStartsWith('999 Test Avenue', $title, 'Title should start with address');
    }

    /**
     * Test transaction type filtering - For Sale
     */
    public function test_transaction_type_filter_for_sale() {
        $settings = array(
            'bearer_token' => 'test_token',
            'transaction_type_filter' => 'For Sale',
            'listing_age_days' => 30
        );

        $ampre_service = new \Shift8_TREB_AMPRE_Service($settings);
        
        $reflection = new \ReflectionClass($ampre_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $query_params = $method->invoke($ampre_service);
        
        $this->assertStringContainsString('TransactionType eq \'For Sale\'', $query_params, 'Should filter for sale listings');
    }

    /**
     * Test transaction type filtering - For Lease
     */
    public function test_transaction_type_filter_for_lease() {
        $settings = array(
            'bearer_token' => 'test_token',
            'transaction_type_filter' => 'For Lease',
            'listing_age_days' => 30
        );

        $ampre_service = new \Shift8_TREB_AMPRE_Service($settings);
        
        $reflection = new \ReflectionClass($ampre_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $query_params = $method->invoke($ampre_service);
        
        $this->assertStringContainsString('TransactionType eq \'For Lease\'', $query_params, 'Should filter for lease listings');
    }

    /**
     * Test transaction type filtering - For Sale or Lease
     */
    public function test_transaction_type_filter_both() {
        $settings = array(
            'bearer_token' => 'test_token',
            'transaction_type_filter' => 'For Sale or Lease',
            'listing_age_days' => 30
        );

        $ampre_service = new \Shift8_TREB_AMPRE_Service($settings);
        
        $reflection = new \ReflectionClass($ampre_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $query_params = $method->invoke($ampre_service);
        
        $this->assertStringContainsString('TransactionType eq \'For Sale or Lease\'', $query_params, 'Should filter for both');
    }

    /**
     * Test no transaction type filter
     */
    public function test_no_transaction_type_filter() {
        $settings = array(
            'bearer_token' => 'test_token',
            'listing_age_days' => 30
            // No transaction_type_filter set
        );

        $ampre_service = new \Shift8_TREB_AMPRE_Service($settings);
        
        $reflection = new \ReflectionClass($ampre_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $query_params = $method->invoke($ampre_service);
        
        $this->assertStringNotContainsString('TransactionType', $query_params, 'Should not filter by transaction type when not set');
    }

    /**
     * Test fetch terminated listings from API
     */
    public function test_fetch_terminated_listings_api_query() {
        Functions\when('shift8_treb_log')->justReturn(null);
        Functions\when('wp_remote_get')->alias(function() {
            return array(
                'response' => array('code' => 200),
                'body' => json_encode(array(
                    'value' => array(
                        array(
                            'ListingKey' => 'W12345678',
                            'StandardStatus' => 'Cancelled',
                            'ContractStatus' => 'Unavailable'
                        ),
                        array(
                            'ListingKey' => 'W87654321',
                            'StandardStatus' => 'Expired',
                            'ContractStatus' => 'Unavailable'
                        )
                    )
                ))
            );
        });
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });
        Functions\when('is_wp_error')->justReturn(false);

        $settings = array('bearer_token' => 'test_token');
        $ampre_service = new \Shift8_TREB_AMPRE_Service($settings);
        $sync_service = new \Shift8_TREB_Sync_Service($settings);
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($sync_service);
        $method = $reflection->getMethod('fetch_terminated_listings_from_api');
        $method->setAccessible(true);
        
        $terminated_listings = $method->invoke($sync_service);
        
        $this->assertIsArray($terminated_listings, 'Should return array');
        $this->assertCount(2, $terminated_listings, 'Should return 2 terminated listings');
        $this->assertEquals('W12345678', $terminated_listings[0]['ListingKey'], 'Should have correct MLS number');
        $this->assertEquals('Cancelled', $terminated_listings[0]['StandardStatus'], 'Should have cancelled status');
    }

    /**
     * Test cleanup terminated listings - finds and removes WordPress posts
     */
    public function test_cleanup_terminated_listings_removes_posts() {
        Functions\when('shift8_treb_log')->justReturn(null);
        
        // Mock get_posts to return a post with terminated MLS
        $mock_post = (object) array(
            'ID' => 456,
            'post_title' => 'Test Listing'
        );
        
        Functions\when('get_posts')->alias(function($args) use ($mock_post) {
            // If querying for specific MLS, return the mock post
            if (isset($args['meta_query'])) {
                return array($mock_post);
            }
            return array();
        });
        
        Functions\when('wp_trash_post')->justReturn(true);
        Functions\when('wp_remote_get')->alias(function() {
            return array(
                'response' => array('code' => 200),
                'body' => json_encode(array(
                    'value' => array(
                        array(
                            'ListingKey' => 'W12345678',
                            'StandardStatus' => 'Cancelled',
                            'ContractStatus' => 'Unavailable'
                        )
                    )
                ))
            );
        });
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });
        Functions\when('is_wp_error')->justReturn(false);

        $settings = array('bearer_token' => 'test_token');
        $sync_service = new \Shift8_TREB_Sync_Service($settings);
        
        $results = $sync_service->cleanup_terminated_listings();
        
        $this->assertIsArray($results, 'Should return results array');
        $this->assertArrayHasKey('api_terminated_count', $results, 'Should have terminated count');
        $this->assertArrayHasKey('checked', $results, 'Should have checked count');
        $this->assertArrayHasKey('removed', $results, 'Should have removed count');
        $this->assertEquals(1, $results['api_terminated_count'], 'Should find 1 terminated listing in API');
        $this->assertEquals(1, $results['checked'], 'Should check 1 listing');
    }

    /**
     * Test terminated listings query format
     */
    public function test_terminated_listings_query_format() {
        Functions\when('shift8_treb_log')->justReturn(null);
        Functions\when('wp_remote_get')->alias(function($url) {
            // Verify the URL contains correct filters
            $this->assertStringContainsString('StandardStatus eq \'Cancelled\'', $url, 'Should filter cancelled');
            $this->assertStringContainsString('StandardStatus eq \'Expired\'', $url, 'Should filter expired');
            $this->assertStringContainsString('StandardStatus eq \'Withdrawn\'', $url, 'Should filter withdrawn');
            $this->assertStringContainsString('StandardStatus eq \'Terminated\'', $url, 'Should filter terminated');
            $this->assertStringContainsString('ModificationTimestamp ge', $url, 'Should filter by modification date');
            $this->assertStringContainsString('$top=200', $url, 'Should limit to 200 results');
            
            return array(
                'response' => array('code' => 200),
                'body' => json_encode(array('value' => array()))
            );
        });
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });
        Functions\when('is_wp_error')->justReturn(false);

        $settings = array('bearer_token' => 'test_token');
        $sync_service = new \Shift8_TREB_Sync_Service($settings);
        
        $reflection = new \ReflectionClass($sync_service);
        $method = $reflection->getMethod('fetch_terminated_listings_from_api');
        $method->setAccessible(true);
        
        $method->invoke($sync_service);
    }

    /**
     * Test StandardStatus filter excludes terminated listings
     */
    public function test_standard_status_excludes_terminated() {
        $settings = array(
            'bearer_token' => 'test_token',
            'listing_age_days' => 30
        );

        $ampre_service = new \Shift8_TREB_AMPRE_Service($settings);
        
        $reflection = new \ReflectionClass($ampre_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $query_params = $method->invoke($ampre_service);
        
        // Should include Active, Pending, Closed
        $this->assertStringContainsString('StandardStatus eq \'Active\'', $query_params);
        $this->assertStringContainsString('StandardStatus eq \'Pending\'', $query_params);
        $this->assertStringContainsString('StandardStatus eq \'Closed\'', $query_params);
        
        // Should NOT include Cancelled, Expired, Withdrawn, Terminated
        $this->assertStringNotContainsString('StandardStatus eq \'Cancelled\'', $query_params);
        $this->assertStringNotContainsString('StandardStatus eq \'Expired\'', $query_params);
        $this->assertStringNotContainsString('StandardStatus eq \'Withdrawn\'', $query_params);
        $this->assertStringNotContainsString('StandardStatus eq \'Terminated\'', $query_params);
    }

    /**
     * Test ContractStatus exclusion filter
     */
    public function test_contract_status_unavailable_excluded() {
        $settings = array(
            'bearer_token' => 'test_token',
            'listing_age_days' => 30
        );

        $ampre_service = new \Shift8_TREB_AMPRE_Service($settings);
        
        $reflection = new \ReflectionClass($ampre_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $query_params = $method->invoke($ampre_service);
        
        // Should explicitly exclude Unavailable
        $this->assertStringContainsString('ContractStatus ne \'Unavailable\'', $query_params);
        
        // Should NOT include the old filter format
        $this->assertStringNotContainsString('ContractStatus eq \'Available\'', $query_params);
    }
}

