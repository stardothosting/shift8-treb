<?php
/**
 * Post Manager for Shift8 TREB
 *
 * @package Shift8\TREB
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post manager class
 *
 * Handles creating and updating WordPress posts from TREB listing data
 * using modern WordPress practices.
 *
 * @since 1.0.0
 */
class Shift8_TREB_Post_Manager {

    /**
     * Plugin settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param array $settings Plugin settings
     */
    public function __construct($settings) {
        $this->settings = $settings;
    }

    /**
     * Process a single listing from AMPRE API
     *
     * @since 1.0.0
     * @param array $listing Listing data from AMPRE API
     * @return bool Success status
     */
    public function process_listing($listing) {
        try {
            // Validate required data
            if (!$this->validate_listing($listing)) {
                return false;
            }

            $mls_number = sanitize_text_field($listing['ListingKey']);

            shift8_treb_debug_log('Processing listing', array(
                'mls_number' => esc_html($mls_number)
            ));

            // Check if post already exists
            if ($this->listing_exists($mls_number)) {
                shift8_treb_debug_log('Listing already exists, skipping', array(
                    'mls_number' => esc_html($mls_number)
                ));
                return false;
            }

            // Apply business rules (agent filtering, price limits, etc.)
            if (!$this->should_process_listing($listing)) {
                return false;
            }

            // Create the WordPress post
            $post_id = $this->create_listing_post($listing);
            
            if (!$post_id) {
                return false;
            }

            // Handle images
            $this->process_listing_images($post_id, $listing);

            // Store additional metadata
            $this->store_listing_metadata($post_id, $listing);

            shift8_treb_debug_log('Listing processed successfully', array(
                'post_id' => $post_id,
                'mls_number' => esc_html($mls_number)
            ));

            return true;

        } catch (Exception $e) {
            shift8_treb_debug_log('Error processing listing', array(
                'error' => esc_html($e->getMessage()),
                'mls_number' => isset($listing['ListingKey']) ? esc_html($listing['ListingKey']) : 'unknown'
            ));
            return false;
        }
    }

    /**
     * Validate listing data
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return bool True if valid
     */
    private function validate_listing($listing) {
        $required_fields = array('ListingKey', 'UnparsedAddress', 'ListPrice');
        
        foreach ($required_fields as $field) {
            if (!isset($listing[$field]) || empty($listing[$field])) {
                shift8_treb_debug_log('Invalid listing - missing required field', array(
                    'missing_field' => esc_html($field)
                ));
                return false;
            }
        }

        return true;
    }

    /**
     * Check if listing already exists (using MLS number as unique identifier)
     *
     * @since 1.0.0
     * @param string $mls_number MLS number
     * @return bool True if exists
     */
    private function listing_exists($mls_number) {
        // Check by post meta (more reliable than tags)
        $existing_posts = get_posts(array(
            'post_type' => 'post',
            'meta_key' => 'listing_mls_number',
            'meta_value' => $mls_number,
            'numberposts' => 1,
            'post_status' => 'any'
        ));

        return !empty($existing_posts);
    }

    /**
     * Apply business rules to determine if listing should be processed
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return bool True if should process
     */
    private function should_process_listing($listing) {
        $agent_id = isset($listing['ListAgentKey']) ? sanitize_text_field($listing['ListAgentKey']) : '';
        $price = isset($listing['ListPrice']) ? intval($listing['ListPrice']) : 0;

        // Check if this is our agent's listing
        if ($this->is_our_agent($agent_id)) {
            return true; // Always process our agent's listings
        }

        // For other agents, check minimum price
        $min_price = isset($this->settings['min_price']) ? intval($this->settings['min_price']) : 0;
        if ($min_price > 0 && $price < $min_price) {
            shift8_treb_debug_log('Listing below minimum price, skipping', array(
                'price' => $price,
                'min_price' => $min_price,
                'mls_number' => esc_html($listing['ListingKey'])
            ));
            return false;
        }

        // Check maximum price
        $max_price = isset($this->settings['max_price']) ? intval($this->settings['max_price']) : 0;
        if ($max_price > 0 && $price > $max_price) {
            shift8_treb_debug_log('Listing above maximum price, skipping', array(
                'price' => $price,
                'max_price' => $max_price,
                'mls_number' => esc_html($listing['ListingKey'])
            ));
            return false;
        }

        // Check if agent is excluded
        if ($this->is_excluded_agent($agent_id)) {
            shift8_treb_debug_log('Agent is excluded, skipping', array(
                'agent_id' => esc_html($agent_id),
                'mls_number' => esc_html($listing['ListingKey'])
            ));
            return false;
        }

        return true;
    }

    /**
     * Check if agent is one of our agents
     *
     * @since 1.0.0
     * @param string $agent_id Agent ID
     * @return bool True if our agent
     */
    private function is_our_agent($agent_id) {
        $our_agents = isset($this->settings['agent_id']) ? $this->settings['agent_id'] : '';
        if (empty($our_agents)) {
            return false;
        }

        $agent_list = array_map('trim', explode(',', $our_agents));
        return in_array($agent_id, $agent_list);
    }

    /**
     * Check if agent is excluded
     *
     * @since 1.0.0
     * @param string $agent_id Agent ID
     * @return bool True if excluded
     */
    private function is_excluded_agent($agent_id) {
        $excluded_agents = isset($this->settings['agent_exclude']) ? $this->settings['agent_exclude'] : '';
        if (empty($excluded_agents)) {
            return false;
        }

        $excluded_list = array_map('trim', explode(',', $excluded_agents));
        return in_array($agent_id, $excluded_list);
    }

    /**
     * Create WordPress post for listing
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return int|false Post ID on success, false on failure
     */
    private function create_listing_post($listing) {
        // Prepare post data
        $post_title = $this->generate_post_title($listing);
        $post_content = $this->generate_post_content($listing);
        $post_excerpt = $this->generate_post_excerpt($listing);
        $category_id = $this->get_listing_category_id($listing);

        $post_data = array(
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => $post_excerpt,
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_category' => array($category_id),
            'tags_input' => array(sanitize_text_field($listing['ListingKey'])) // MLS as tag
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            shift8_treb_debug_log('Failed to create post', array(
                'error' => esc_html($post_id->get_error_message())
            ));
            return false;
        }

        return $post_id;
    }

    /**
     * Generate post title
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return string Post title
     */
    private function generate_post_title($listing) {
        $address = sanitize_text_field($listing['UnparsedAddress']);
        $city = isset($listing['City']) ? sanitize_text_field($listing['City']) : '';
        $province = isset($listing['StateOrProvince']) ? sanitize_text_field($listing['StateOrProvince']) : '';

        $title_parts = array($address);
        if (!empty($city)) {
            $title_parts[] = $city;
        }
        if (!empty($province)) {
            $title_parts[] = $province;
        }

        return implode(', ', $title_parts);
    }

    /**
     * Generate post content using template
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return string Post content
     */
    private function generate_post_content($listing) {
        $template = isset($this->settings['listing_template']) ? $this->settings['listing_template'] : $this->get_default_template();

        // Prepare replacement variables
        $replacements = array(
            '%ADDRESS%' => sanitize_text_field($listing['UnparsedAddress']),
            '%PRICE%' => '$' . number_format(intval($listing['ListPrice'])),
            '%MLS%' => sanitize_text_field($listing['ListingKey']),
            '%BEDROOMS%' => isset($listing['BedroomsTotal']) ? sanitize_text_field($listing['BedroomsTotal']) : 'N/A',
            '%BATHROOMS%' => isset($listing['BathroomsTotal']) ? sanitize_text_field($listing['BathroomsTotal']) : 'N/A',
            '%SQFT%' => isset($listing['LivingArea']) ? number_format(intval($listing['LivingArea'])) : 'N/A',
            '%DESCRIPTION%' => isset($listing['PublicRemarks']) ? wp_kses_post($listing['PublicRemarks']) : '',
            '%PROPERTY_TYPE%' => isset($listing['PropertyType']) ? sanitize_text_field($listing['PropertyType']) : 'N/A',
            '%CITY%' => isset($listing['City']) ? sanitize_text_field($listing['City']) : '',
            '%POSTAL_CODE%' => isset($listing['PostalCode']) ? sanitize_text_field($listing['PostalCode']) : ''
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Generate post excerpt
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return string Post excerpt
     */
    private function generate_post_excerpt($listing) {
        $address = sanitize_text_field($listing['UnparsedAddress']);
        $price = '$' . number_format(intval($listing['ListPrice']));
        $mls = sanitize_text_field($listing['ListingKey']);

        return sprintf(
            '<div class="listing-excerpt">
                <div class="listing-address">%s</div>
                <div class="listing-price">%s</div>
                <div class="listing-mls">MLS: %s</div>
            </div>',
            esc_html($address),
            esc_html($price),
            esc_html($mls)
        );
    }

    /**
     * Get category ID for listing
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return int Category ID
     */
    private function get_listing_category_id($listing) {
        $agent_id = isset($listing['ListAgentKey']) ? sanitize_text_field($listing['ListAgentKey']) : '';
        
        // Determine category based on agent
        $category_name = $this->is_our_agent($agent_id) ? 'Listings' : 'Other Listings';
        
        return $this->get_or_create_category($category_name);
    }

    /**
     * Get or create category
     *
     * @since 1.0.0
     * @param string $category_name Category name
     * @return int Category ID
     */
    private function get_or_create_category($category_name) {
        $category = get_category_by_slug(sanitize_title($category_name));
        
        if ($category) {
            return $category->term_id;
        }

        // Create category
        $result = wp_insert_category(array(
            'cat_name' => $category_name,
            'category_nicename' => sanitize_title($category_name)
        ));

        return is_wp_error($result) ? 1 : $result; // Default to uncategorized if error
    }

    /**
     * Process listing images
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @param array $listing Listing data
     * @return void
     */
    private function process_listing_images($post_id, $listing) {
        if (!isset($listing['Media']) || !is_array($listing['Media']) || empty($listing['Media'])) {
            return;
        }

        $mls_number = sanitize_text_field($listing['ListingKey']);
        
        // Create directory for images
        $upload_dir = wp_upload_dir();
        $listing_dir = $upload_dir['basedir'] . '/treb/' . $mls_number;
        
        if (!file_exists($listing_dir)) {
            wp_mkdir_p($listing_dir);
        }

        $featured_image_set = false;

        foreach ($listing['Media'] as $index => $media) {
            if (!isset($media['MediaURL']) || empty($media['MediaURL'])) {
                continue;
            }

            $image_url = esc_url_raw($media['MediaURL']);
            $attachment_id = $this->download_and_attach_image($image_url, $post_id, $mls_number, $index + 1);

            // Set first image as featured image
            if ($attachment_id && !$featured_image_set) {
                set_post_thumbnail($post_id, $attachment_id);
                $featured_image_set = true;
            }
        }
    }

    /**
     * Download and attach image to post
     *
     * @since 1.0.0
     * @param string $image_url Image URL
     * @param int $post_id Post ID
     * @param string $mls_number MLS number
     * @param int $image_number Image number
     * @return int|false Attachment ID on success, false on failure
     */
    private function download_and_attach_image($image_url, $post_id, $mls_number, $image_number) {
        // Download image
        $response = wp_remote_get($image_url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return false;
        }

        // Prepare filename
        $filename = $mls_number . '_' . $image_number . '.jpg';
        
        // Upload to WordPress media library
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if ($upload['error']) {
            return false;
        }

        // Create attachment
        $attachment = array(
            'post_mime_type' => 'image/jpeg',
            'post_title' => $mls_number . ' - Image ' . $image_number,
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }

    /**
     * Store additional listing metadata
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @param array $listing Listing data
     * @return void
     */
    private function store_listing_metadata($post_id, $listing) {
        // Store key listing data as post meta
        $meta_fields = array(
            'listing_mls_number' => 'ListingKey',
            'listing_price' => 'ListPrice',
            'listing_bedrooms' => 'BedroomsTotal',
            'listing_bathrooms' => 'BathroomsTotal',
            'listing_living_area' => 'LivingArea',
            'listing_property_type' => 'PropertyType',
            'listing_city' => 'City',
            'listing_province' => 'StateOrProvince',
            'listing_postal_code' => 'PostalCode',
            'listing_agent_key' => 'ListAgentKey',
            'listing_status' => 'StandardStatus',
            'listing_last_updated' => 'ModificationTimestamp'
        );

        foreach ($meta_fields as $meta_key => $api_field) {
            if (isset($listing[$api_field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($listing[$api_field]));
            }
        }

        // Store sync timestamp
        update_post_meta($post_id, 'listing_sync_date', current_time('mysql'));
    }

    /**
     * Get default listing template
     *
     * @since 1.0.0
     * @return string Default template
     */
    private function get_default_template() {
        return '<div class="treb-listing">
    <h3>Property Details</h3>
    <ul class="property-details">
        <li><strong>Address:</strong> %ADDRESS%</li>
        <li><strong>Price:</strong> %PRICE%</li>
        <li><strong>MLS Number:</strong> %MLS%</li>
        <li><strong>Property Type:</strong> %PROPERTY_TYPE%</li>
        <li><strong>Bedrooms:</strong> %BEDROOMS%</li>
        <li><strong>Bathrooms:</strong> %BATHROOMS%</li>
        <li><strong>Living Area:</strong> %SQFT% sq ft</li>
        <li><strong>City:</strong> %CITY%</li>
        <li><strong>Postal Code:</strong> %POSTAL_CODE%</li>
    </ul>
    
    <h3>Description</h3>
    <div class="property-description">
        %DESCRIPTION%
    </div>
</div>';
    }
}