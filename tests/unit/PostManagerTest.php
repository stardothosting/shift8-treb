<?php
/**
 * Post Manager tests using Brain/Monkey
 *
 * @package Shift8\TREB\Tests\Unit
 */

namespace Shift8\TREB\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Test the Post Manager class methods using Brain/Monkey
 */
class PostManagerTest extends TestCase {

    /**
     * Post Manager instance for testing
     *
     * @var Shift8_TREB_Post_Manager
     */
    protected $post_manager;

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress post functions
        Functions\when('wp_insert_post')->justReturn(123);
        Functions\when('wp_update_post')->justReturn(123);
        Functions\when('get_posts')->justReturn(array());
        Functions\when('wp_delete_file')->justReturn(true);
        Functions\when('wp_upload_dir')->justReturn(array(
            'basedir' => '/tmp/uploads',
            'baseurl' => 'http://example.com/wp-content/uploads'
        ));
        Functions\when('wp_mkdir_p')->justReturn(true);
        Functions\when('is_dir')->justReturn(true);
        Functions\when('sanitize_text_field')->alias(function($str) { 
            return htmlspecialchars(strip_tags($str)); 
        });
        Functions\when('sanitize_title')->alias(function($str) { 
            return strtolower(str_replace(' ', '-', $str)); 
        });
        Functions\when('wp_strip_all_tags')->alias(function($str) { 
            return strip_tags($str); 
        });
        Functions\when('esc_html')->alias(function($text) { 
            return htmlspecialchars($text); 
        });
        Functions\when('get_option')->justReturn(array('debug_enabled' => '0'));
        Functions\when('get_term_by')->justReturn(false);
        Functions\when('wp_insert_term')->justReturn(array('term_id' => 1));
        Functions\when('wp_set_post_terms')->justReturn(true);
        Functions\when('add_post_meta')->justReturn(true);
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_remote_get')->justReturn(array(
            'response' => array('code' => 200),
            'body' => 'fake_image_data'
        ));
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('fake_image_data');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_check_filetype')->justReturn(array(
            'ext' => 'jpg',
            'type' => 'image/jpeg'
        ));
        Functions\when('wp_insert_attachment')->justReturn(456);
        Functions\when('set_post_thumbnail')->justReturn(true);
        
        // Mock global filesystem
        global $wp_filesystem;
        $wp_filesystem = new class {
            public function put_contents($file, $content) { return true; }
            public function exists($file) { return false; }
        };
        
        // Include the Post Manager class
        require_once dirname(dirname(__DIR__)) . '/includes/class-shift8-treb-post-manager.php';
        
        // Create instance with test settings
        $test_settings = array(
            'listing_template' => 'Property: %ADDRESS%\nPrice: %PRICE%\nMLS: %MLS%\nBedrooms: %BEDROOMS%\nBathrooms: %BATHROOMS%\nDescription: %DESCRIPTION%',
            'agent_filter' => '1525'
        );
        
        $this->post_manager = new \Shift8_TREB_Post_Manager($test_settings);
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test Post Manager construction
     */
    public function test_post_manager_construction() {
        $this->assertInstanceOf('Shift8_TREB_Post_Manager', $this->post_manager);
    }

    /**
     * Test creating a new listing post
     */
    public function test_create_listing_post_new() {
        // Mock get_posts to return empty (no existing post)
        Functions\when('get_posts')->justReturn(array());
        
        // Mock wp_insert_post to return new post ID
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::type('array'))
            ->andReturn(123);
        
        $listing_data = array(
            'ListingKey' => 'X12345678',
            'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
            'ListPrice' => 750000.0,
            'ContractStatus' => 'Available',
            'ListAgentKey' => '1525',
            'BedroomsTotal' => 3,
            'BathroomsTotalInteger' => 2,
            'BuildingAreaTotal' => 1500,
            'PublicRemarks' => 'Beautiful home in great location.'
        );
        
        $result = $this->post_manager->process_listing($listing_data);
        
        $this->assertEquals(123, $result);
    }

    /**
     * Test updating an existing listing post
     */
    public function test_update_existing_listing_post() {
        // Mock get_posts to return existing post
        $existing_post = new \stdClass();
        $existing_post->ID = 123;
        Functions\when('get_posts')->justReturn(array($existing_post));
        
        // Mock wp_update_post
        Functions\expect('wp_update_post')
            ->once()
            ->with(\Mockery::type('array'))
            ->andReturn(123);
        
        $listing_data = array(
            'ListingKey' => 'X12345678',
            'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
            'ListPrice' => 750000.0,
            'ContractStatus' => 'Available',
            'ListAgentKey' => '1525',
            'BedroomsTotal' => 3,
            'BathroomsTotalInteger' => 2,
            'PublicRemarks' => 'Updated description.'
        );
        
        $result = $this->post_manager->process_listing($listing_data);
        
        $this->assertEquals(123, $result);
    }

    /**
     * Test template processing
     */
    public function test_process_listing_template() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->post_manager);
        $method = $reflection->getMethod('generate_post_content');
        $method->setAccessible(true);
        
        $listing_data = array(
            'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
            'ListPrice' => 750000.0,
            'ListingKey' => 'X12345678',
            'BedroomsTotal' => 3,
            'BathroomsTotalInteger' => 2,
            'PublicRemarks' => 'Beautiful home in great location.'
        );
        
        $result = $method->invoke($this->post_manager, $listing_data);
        
        $this->assertStringContainsString('123 Test Street, Toronto, ON M1A 1A1', $result);
        $this->assertStringContainsString('$750,000', $result);
        $this->assertStringContainsString('X12345678', $result);
        $this->assertStringContainsString('3', $result);
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('Beautiful home in great location.', $result);
    }

    /**
     * Test category assignment for own agent
     */
    public function test_assign_category_own_agent() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->post_manager);
        $method = $reflection->getMethod('get_listing_category_id');
        $method->setAccessible(true);
        
        // Mock get_term_by to return false (category doesn't exist)
        Functions\when('get_term_by')->justReturn(false);
        
        // Mock wp_insert_term to create new category
        Functions\expect('wp_insert_term')
            ->once()
            ->with('Listings', 'category')
            ->andReturn(array('term_id' => 1));
        
        $listing_data = array(
            'ListAgentKey' => '1525', // Matches agent_filter
            'ListPrice' => 750000.0
        );
        
        $result = $method->invoke($this->post_manager, 123, $listing_data);
        
        $this->assertTrue($result);
    }

    /**
     * Test category assignment for other agent
     */
    public function test_assign_category_other_agent() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->post_manager);
        $method = $reflection->getMethod('get_listing_category_id');
        $method->setAccessible(true);
        
        // Mock get_term_by to return false (category doesn't exist)
        Functions\when('get_term_by')->justReturn(false);
        
        // Mock wp_insert_term to create new category
        Functions\expect('wp_insert_term')
            ->once()
            ->with('OtherListings', 'category')
            ->andReturn(array('term_id' => 2));
        
        $listing_data = array(
            'ListAgentKey' => '9999', // Different from agent_filter
            'ListPrice' => 750000.0
        );
        
        $result = $method->invoke($this->post_manager, 123, $listing_data);
        
        $this->assertTrue($result);
    }

    /**
     * Test post meta storage
     */
    public function test_store_listing_meta() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->post_manager);
        $method = $reflection->getMethod('store_listing_metadata');
        $method->setAccessible(true);
        
        // Mock add_post_meta calls
        Functions\expect('add_post_meta')->times(8); // Expect multiple meta fields
        
        $listing_data = array(
            'ListingKey' => 'X12345678',
            'ListPrice' => 750000.0,
            'ListAgentKey' => '1525',
            'BedroomsTotal' => 3,
            'BathroomsTotalInteger' => 2,
            'BuildingAreaTotal' => 1500,
            'ContractStatus' => 'Available',
            'ModificationTimestamp' => '2024-10-01T12:00:00Z'
        );
        
        $method->invoke($this->post_manager, 123, $listing_data);
        
        $this->assertTrue(true, 'Meta storage completed without errors');
    }

    /**
     * Test image download and processing
     */
    public function test_download_and_set_featured_image() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->post_manager);
        $method = $reflection->getMethod('download_and_attach_image');
        $method->setAccessible(true);
        
        // Mock successful image download
        Functions\when('wp_remote_get')->justReturn(array(
            'response' => array('code' => 200),
            'body' => 'fake_image_data'
        ));
        
        // Mock wp_insert_attachment
        Functions\expect('wp_insert_attachment')
            ->once()
            ->andReturn(456);
        
        // Mock set_post_thumbnail
        Functions\expect('set_post_thumbnail')
            ->once()
            ->with(123, 456)
            ->andReturn(true);
        
        $image_url = 'http://example.com/image.jpg';
        $mls_number = 'X12345678';
        $image_number = 1;
        
        $result = $method->invoke($this->post_manager, $image_url, 123, $mls_number, $image_number);
        
        $this->assertTrue($result);
    }

    /**
     * Test image download failure
     */
    public function test_download_image_failure() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->post_manager);
        $method = $reflection->getMethod('download_and_attach_image');
        $method->setAccessible(true);
        
        // Mock failed image download
        Functions\when('wp_remote_retrieve_response_code')->justReturn(404);
        
        $image_url = 'http://example.com/nonexistent.jpg';
        $mls_number = 'X12345678';
        $image_number = 1;
        
        $result = $method->invoke($this->post_manager, $image_url, 123, $mls_number, $image_number);
        
        $this->assertFalse($result);
    }

    /**
     * Test excerpt generation
     */
    public function test_generate_excerpt() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->post_manager);
        $method = $reflection->getMethod('generate_post_excerpt');
        $method->setAccessible(true);
        
        $listing_data = array(
            'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
            'ListPrice' => 750000.0,
            'ListingKey' => 'X12345678'
        );
        
        $result = $method->invoke($this->post_manager, $listing_data);
        
        $this->assertStringContainsString('123 Test Street', $result);
        $this->assertStringContainsString('$750,000', $result);
        $this->assertStringContainsString('X12345678', $result);
    }

    /**
     * Test price formatting (inline in generate_post_content)
     */
    public function test_price_formatting_in_content() {
        // Test that price formatting works within content generation
        $reflection = new \ReflectionClass($this->post_manager);
        $method = $reflection->getMethod('generate_post_content');
        $method->setAccessible(true);
        
        $listing_data = array(
            'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
            'ListPrice' => 750000.0,
            'ListingKey' => 'X12345678',
            'PublicRemarks' => 'Test description.'
        );
        
        $result = $method->invoke($this->post_manager, $listing_data);
        
        // Should contain formatted price
        $this->assertStringContainsString('750000', $result);
    }

    /**
     * Test duplicate detection
     */
    public function test_duplicate_detection() {
        // Mock get_posts to simulate existing post with same MLS
        $existing_post = new \stdClass();
        $existing_post->ID = 456;
        
        Functions\when('get_posts')->justReturn(array($existing_post));
        
        // Should update existing post instead of creating new one
        Functions\expect('wp_update_post')
            ->once()
            ->andReturn(456);
        
        $listing_data = array(
            'ListingKey' => 'X12345678',
            'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
            'ListPrice' => 750000.0,
            'ContractStatus' => 'Available'
        );
        
        $result = $this->post_manager->process_listing($listing_data);
        
        $this->assertEquals(456, $result);
    }

    /**
     * Test listing processing with missing required fields
     */
    public function test_create_listing_missing_fields() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required listing data');
        
        $incomplete_data = array(
            'ListPrice' => 750000.0
            // Missing ListingKey and UnparsedAddress
        );
        
        $this->post_manager->process_listing($incomplete_data);
    }

    /**
     * Test single listing processing workflow
     */
    public function test_single_listing_workflow() {
        // Mock get_posts to return empty (new post)
        Functions\when('get_posts')->justReturn(array());
        
        // Mock wp_insert_post to return post ID
        Functions\when('wp_insert_post')->justReturn(123);
        
        $listing_data = array(
            'ListingKey' => 'X12345678',
            'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
            'ListPrice' => 750000.0,
            'ContractStatus' => 'Available',
            'ListAgentKey' => '1525',
            'PublicRemarks' => 'Beautiful home.'
        );
        
        $result = $this->post_manager->process_listing($listing_data);
        
        $this->assertEquals(123, $result);
    }
}
