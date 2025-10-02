<?php
/**
 * AMPRE Service tests using Brain/Monkey
 *
 * @package Shift8\TREB\Tests\Unit
 */

namespace Shift8\TREB\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Test the AMPRE Service class methods using Brain/Monkey
 */
class AMPREServiceTest extends TestCase {

    /**
     * AMPRE Service instance for testing
     *
     * @var Shift8_TREB_AMPRE_Service
     */
    protected $ampre_service;

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions commonly used in AMPRE Service
        Functions\when('wp_remote_get')->justReturn(array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                '@odata.context' => '$metadata#Property',
                'value' => array(
                    array(
                        'ListingKey' => 'X12345678',
                        'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
                        'ListPrice' => 750000.0,
                        'ContractStatus' => 'Available'
                    )
                )
            ))
        ));
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array(
            '@odata.context' => '$metadata#Property',
            'value' => array()
        )));
        Functions\when('wp_remote_retrieve_headers')->justReturn(array());
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('esc_html')->alias(function($text) { return htmlspecialchars($text); });
        Functions\when('esc_url_raw')->alias(function($url) { return filter_var($url, FILTER_SANITIZE_URL); });
        Functions\when('sanitize_text_field')->alias(function($str) { return htmlspecialchars(strip_tags($str)); });
        Functions\when('get_option')->justReturn(array('debug_enabled' => '0'));
        
        // Include the AMPRE Service class
        require_once dirname(dirname(__DIR__)) . '/includes/class-shift8-treb-ampre-service.php';
        
        // Create instance with test settings
        $test_settings = array(
            'bearer_token' => 'test_bearer_token_12345',
            'max_listings_per_query' => 100,
            'city_filter' => 'Toronto',
            'listing_status_filter' => 'Active',
            'property_type_filter' => 'Residential',
            'agent_filter' => '1525',
            'min_price' => '100000',
            'max_price' => '2000000'
        );
        
        $this->ampre_service = new \Shift8_TREB_AMPRE_Service($test_settings);
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Mock sequential HTTP responses for AMPRE service testing
     * 
     * @param array $responses Array of response bodies
     * @param array $codes Array of response codes (optional)
     */
    private function mock_sequential_http_responses($responses, $codes = null) {
        if ($codes === null) {
            $codes = array_fill(0, count($responses), 200);
        }
        
        $call_count = 0;
        
        Functions\when('wp_remote_get')->alias(function() use ($responses, $codes, &$call_count) {
            $response_body = isset($responses[$call_count]) ? $responses[$call_count] : '{}';
            $response_code = isset($codes[$call_count]) ? $codes[$call_count] : 200;
            $call_count++;
            
            return array(
                'response' => array('code' => $response_code),
                'body' => $response_body
            );
        });
        
        Functions\when('wp_remote_retrieve_response_code')->alias(function() use ($codes, &$call_count) {
            $code_index = max(0, $call_count - 1);
            return isset($codes[$code_index]) ? $codes[$code_index] : 200;
        });
        
        Functions\when('wp_remote_retrieve_body')->alias(function() use ($responses, &$call_count) {
            $response_index = max(0, $call_count - 1);
            return isset($responses[$response_index]) ? $responses[$response_index] : '{}';
        });
    }

    /**
     * Test AMPRE Service construction with various settings
     */
    public function test_ampre_service_construction() {
        $this->assertInstanceOf('Shift8_TREB_AMPRE_Service', $this->ampre_service);
    }

    /**
     * Test connection testing with successful response
     */
    public function test_connection_success() {
        // Mock successful connection test response
        Functions\when('wp_remote_get')->justReturn(array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                '@odata.context' => '$metadata#Property',
                'value' => array(
                    array('ListingKey' => 'TEST123')
                )
            ))
        ));
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array(
            '@odata.context' => '$metadata#Property',
            'value' => array(
                array('ListingKey' => 'TEST123')
            )
        )));
        
        $result = $this->ampre_service->test_connection();
        
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Connection successful', $result['message']);
    }

    /**
     * Test connection testing with authentication failure
     */
    public function test_connection_authentication_failure() {
        // Mock authentication failure response
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array(
            'error' => array(
                'message' => 'Unauthorized access'
            )
        )));
        
        $result = $this->ampre_service->test_connection();
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('401', $result['message']);
    }

    /**
     * Test successful listings retrieval
     */
    public function test_get_listings_success() {
        // Mock successful listings response
        $this->mock_sequential_http_responses(
            array(
                json_encode(array(
                    '@odata.context' => '$metadata#Property',
                    'value' => array(
                        array(
                            'ListingKey' => 'X12345678',
                            'UnparsedAddress' => '123 Test Street, Toronto, ON M1A 1A1',
                            'ListPrice' => 750000.0,
                            'ContractStatus' => 'Available',
                            'ModificationTimestamp' => '2024-10-01T12:00:00Z',
                            'ListAgentKey' => '1525',
                            'BedroomsTotal' => 3,
                            'BathroomsTotalInteger' => 2,
                            'PublicRemarks' => 'Beautiful home in great location.'
                        ),
                        array(
                            'ListingKey' => 'X87654321',
                            'UnparsedAddress' => '456 Another Street, Toronto, ON M2B 2B2',
                            'ListPrice' => 850000.0,
                            'ContractStatus' => 'Available',
                            'ModificationTimestamp' => '2024-10-01T13:00:00Z',
                            'ListAgentKey' => '1525',
                            'BedroomsTotal' => 4,
                            'BathroomsTotalInteger' => 3,
                            'PublicRemarks' => 'Spacious family home.'
                        )
                    )
                ))
            ),
            array(200)
        );
        
        $result = $this->ampre_service->get_listings();
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('X12345678', $result[0]['ListingKey']);
        $this->assertEquals('X87654321', $result[1]['ListingKey']);
        $this->assertEquals(750000.0, $result[0]['ListPrice']);
        $this->assertEquals(850000.0, $result[1]['ListPrice']);
    }

    /**
     * Test listings retrieval with API error
     */
    public function test_get_listings_api_error() {
        // Mock API error response
        Functions\when('wp_remote_retrieve_response_code')->justReturn(400);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array(
            'error' => array(
                'message' => 'Bad Request - Invalid filter'
            )
        )));
        
        $result = $this->ampre_service->get_listings();
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertStringContainsString('400', $result->get_error_message());
    }

    /**
     * Test listings retrieval with network error
     */
    public function test_get_listings_network_error() {
        // Mock network error
        $wp_error = new \WP_Error('http_request_failed', 'Connection timed out');
        Functions\when('wp_remote_get')->justReturn($wp_error);
        Functions\when('is_wp_error')->justReturn(true);
        
        $result = $this->ampre_service->get_listings();
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertStringContainsString('Connection timed out', $result->get_error_message());
    }

    /**
     * Test query parameter building
     */
    public function test_build_query_parameters() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->ampre_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $params = $method->invoke($this->ampre_service);
        
        $this->assertIsString($params);
        
        // Test that ContractStatus filter is included
        $this->assertStringContainsString("ContractStatus eq 'Available'", $params);
        
        // Test that city filter is included
        $this->assertStringContainsString("City eq 'Toronto'", $params);
        
        // Test that agent filter is included
        $this->assertStringContainsString("ListAgentKey eq '1525'", $params);
        
        // Test top parameter
        $this->assertStringContainsString('$top=100', $params);
        
        // Test orderby parameter
        $this->assertStringContainsString('ModificationTimestamp', $params);
    }

    /**
     * Test query parameter building with minimal settings
     */
    public function test_build_query_parameters_minimal() {
        // Create service with minimal settings
        $minimal_settings = array(
            'bearer_token' => 'test_token',
            'max_listings_per_query' => 50
        );
        
        $minimal_service = new \Shift8_TREB_AMPRE_Service($minimal_settings);
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($minimal_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $params = $method->invoke($minimal_service);
        
        $this->assertIsString($params);
        
        // Should still have ContractStatus filter
        $this->assertStringContainsString("ContractStatus eq 'Available'", $params);
        
        // Should have correct top value
        $this->assertStringContainsString('$top=50', $params);
    }

    /**
     * Test listings retrieval with empty response
     */
    public function test_get_listings_empty_response() {
        // Mock empty response
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array(
            '@odata.context' => '$metadata#Property',
            'value' => array()
        )));
        
        $result = $this->ampre_service->get_listings();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test listings retrieval with malformed JSON response
     */
    public function test_get_listings_malformed_json() {
        // Mock malformed JSON response
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('invalid json response');
        
        $result = $this->ampre_service->get_listings();
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertStringContainsString('Invalid JSON', $result->get_error_message());
    }

    /**
     * Test API URL construction
     */
    public function test_api_url_construction() {
        // Test the API_BASE_URL constant
        $reflection = new \ReflectionClass($this->ampre_service);
        $base_url = $reflection->getConstant('API_BASE_URL');
        
        $this->assertEquals('https://query.ampre.ca/odata/', $base_url);
    }

    /**
     * Test bearer token handling
     */
    public function test_bearer_token_handling() {
        // Use reflection to access private property
        $reflection = new \ReflectionClass($this->ampre_service);
        $token_property = $reflection->getProperty('bearer_token');
        $token_property->setAccessible(true);
        
        $token = $token_property->getValue($this->ampre_service);
        
        $this->assertEquals('test_bearer_token_12345', $token);
    }

    /**
     * Test settings handling in constructor
     */
    public function test_settings_handling() {
        // Test with missing bearer token - should not throw exception
        $settings_without_token = array(
            'max_listings_per_query' => 100
            // Missing bearer_token
        );
        
        $service = new \Shift8_TREB_AMPRE_Service($settings_without_token);
        $this->assertInstanceOf('Shift8_TREB_AMPRE_Service', $service);
    }

    /**
     * Test price filter building
     */
    public function test_price_filter_building() {
        // Create service with price filters
        $price_settings = array(
            'bearer_token' => 'test_token',
            'min_price' => '500000',
            'max_price' => '1000000'
        );
        
        $price_service = new \Shift8_TREB_AMPRE_Service($price_settings);
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($price_service);
        $method = $reflection->getMethod('build_query_parameters');
        $method->setAccessible(true);
        
        $params = $method->invoke($price_service);
        
        // Test that price filters are included
        $this->assertStringContainsString('ListPrice ge 500000', $params);
        $this->assertStringContainsString('ListPrice le 1000000', $params);
    }
}
