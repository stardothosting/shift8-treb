<?php
/**
 * AMPRE API Service for Shift8 TREB
 *
 * @package Shift8\TREB
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AMPRE API service class
 *
 * Handles all interactions with the AMPRE API using modern WordPress practices.
 *
 * @since 1.0.0
 */
class Shift8_TREB_AMPRE_Service {

    /**
     * AMPRE API base URL
     */
    const API_BASE_URL = 'https://query.ampre.ca/odata/';

    /**
     * Plugin settings
     */
    private $settings;

    /**
     * Bearer token for API authentication
     */
    private $bearer_token;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param array $settings Plugin settings
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->bearer_token = isset($settings['bearer_token']) ? $settings['bearer_token'] : '';
    }

    /**
     * Test API connection
     *
     * @since 1.0.0
     * @return array Response with success status and message
     */
    public function test_connection() {
        try {
            if (empty($this->bearer_token)) {
                return array(
                    'success' => false,
                    'message' => 'Bearer token is required'
                );
            }

            // Test with a simple metadata request
            $response = $this->make_request('$metadata');
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Connection failed: ' . esc_html($response->get_error_message())
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                return array(
                    'success' => true,
                    'message' => 'Connection successful'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'API returned status code: ' . $response_code
                );
            }

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Connection test failed: ' . esc_html($e->getMessage())
            );
        }
    }

    /**
     * Get property listings from AMPRE API
     *
     * @since 1.0.0
     * @return array|WP_Error Array of listings or WP_Error on failure
     */
    public function get_listings() {
        try {
            if (empty($this->bearer_token)) {
                throw new Exception('Bearer token is required');
            }

            // Build query parameters based on settings
            $query_params = $this->build_query_parameters();
            $endpoint = 'Property?' . $query_params;

            shift8_treb_debug_log('Making AMPRE API request', array(
                'endpoint' => esc_html($endpoint)
            ));

            $response = $this->make_request($endpoint);

            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                throw new Exception('API returned status code: ' . $response_code);
            }

            $data = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from API');
            }

            if (!isset($data['value']) || !is_array($data['value'])) {
                throw new Exception('Unexpected API response format');
            }

            shift8_treb_debug_log('AMPRE API response received', array(
                'listings_count' => count($data['value'])
            ));

            return $data['value'];

        } catch (Exception $e) {
            shift8_treb_debug_log('AMPRE API error', array(
                'error' => esc_html($e->getMessage())
            ));
            return new WP_Error('ampre_api_error', $e->getMessage());
        }
    }

    /**
     * Make HTTP request to AMPRE API
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @return array|WP_Error HTTP response or WP_Error
     */
    private function make_request($endpoint) {
        $url = self::API_BASE_URL . ltrim($endpoint, '/');

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->bearer_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Shift8-TREB-Plugin/' . SHIFT8_TREB_VERSION
            ),
            'timeout' => 30,
            'sslverify' => true
        );

        return wp_remote_get($url, $args);
    }

    /**
     * Build query parameters for AMPRE API request
     *
     * @since 1.0.0
     * @return string Query string
     */
    private function build_query_parameters() {
        $params = array();

        // Add filters based on settings
        $filters = array();

        // Use ContractStatus instead of StandardStatus for available listings
        $filters[] = "ContractStatus eq 'Available'";

        // City filter (e.g., 'Toronto')
        if (!empty($this->settings['city_filter'])) {
            $filters[] = "City eq '" . sanitize_text_field($this->settings['city_filter']) . "'";
        }

        // Property type filter
        if (!empty($this->settings['property_type_filter'])) {
            $filters[] = "PropertyType eq '" . sanitize_text_field($this->settings['property_type_filter']) . "'";
        }

        // Price range filters
        if (!empty($this->settings['min_price']) && intval($this->settings['min_price']) > 0) {
            $filters[] = "ListPrice ge " . intval($this->settings['min_price']);
        }

        if (!empty($this->settings['max_price']) && intval($this->settings['max_price']) > 0) {
            $filters[] = "ListPrice le " . intval($this->settings['max_price']);
        }

        // Agent filter (ListAgentKey)
        if (!empty($this->settings['agent_filter'])) {
            $filters[] = "ListAgentKey eq '" . sanitize_text_field($this->settings['agent_filter']) . "'";
        }

        // Combine filters
        if (!empty($filters)) {
            $params[] = '$filter=' . implode(' and ', $filters);
        }

        // Limit results
        $max_listings = isset($this->settings['max_listings_per_query']) ? intval($this->settings['max_listings_per_query']) : 50;
        if ($max_listings > 0) {
            $params[] = '$top=' . $max_listings;
        }

        // Order by modification timestamp (newest first)
        $params[] = '$orderby=ModificationTimestamp desc';

        // Expand media data for images
        $params[] = '$expand=Media';

        return implode('&', $params);
    }

    /**
     * Get specific property by listing key
     *
     * @since 1.0.0
     * @param string $listing_key Listing key/MLS number
     * @return array|WP_Error Property data or WP_Error
     */
    public function get_property($listing_key) {
        try {
            if (empty($this->bearer_token)) {
                throw new Exception('Bearer token is required');
            }

            if (empty($listing_key)) {
                throw new Exception('Listing key is required');
            }

            $endpoint = "Property('" . sanitize_text_field($listing_key) . "')?\$expand=Media";
            
            $response = $this->make_request($endpoint);

            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                throw new Exception('API returned status code: ' . $response_code);
            }

            $data = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from API');
            }

            return $data;

        } catch (Exception $e) {
            shift8_treb_debug_log('AMPRE API get_property error', array(
                'listing_key' => esc_html($listing_key),
                'error' => esc_html($e->getMessage())
            ));
            return new WP_Error('ampre_api_error', $e->getMessage());
        }
    }

    /**
     * Validate API response data
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return bool True if valid
     */
    public function validate_listing_data($listing) {
        $required_fields = array('ListingKey', 'UnparsedAddress', 'ListPrice', 'StandardStatus');
        
        foreach ($required_fields as $field) {
            if (!isset($listing[$field]) || empty($listing[$field])) {
                shift8_treb_debug_log('Invalid listing data - missing field', array(
                    'missing_field' => esc_html($field),
                    'listing_key' => isset($listing['ListingKey']) ? esc_html($listing['ListingKey']) : 'unknown'
                ));
                return false;
            }
        }

        return true;
    }
}