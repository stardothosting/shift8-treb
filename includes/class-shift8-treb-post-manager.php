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

            // Check if agent should be excluded (skip entirely)
            $agent_id = isset($listing['ListAgentKey']) ? sanitize_text_field($listing['ListAgentKey']) : '';
            if ($this->is_excluded_agent($agent_id)) {
                shift8_treb_log('Skipping excluded agent', array(
                    'agent_id' => esc_html($agent_id),
                    'listing_key' => esc_html($listing['ListingKey'] ?? 'unknown')
                ));
                return array(
                    'success' => false,
                    'action' => 'skipped',
                    'post_id' => null,
                    'title' => $listing['UnparsedAddress'] ?? 'Unknown Address',
                    'mls_number' => $listing['ListingKey'] ?? 'Unknown',
                    'reason' => 'Agent excluded'
                );
            }

            $mls_number = sanitize_text_field($listing['ListingKey']);

            shift8_treb_log('Processing listing', array(
                'mls_number' => esc_html($mls_number)
            ));

            // Check if listing is sold
            $is_sold = $this->is_listing_sold($listing);
            
            // Check if post already exists
            $existing_post_id = $this->get_existing_listing_id($mls_number);
            if ($existing_post_id) {
                if ($is_sold) {
                    // Handle sold listing update
                    $this->handle_sold_listing_update($existing_post_id, $listing);
                    
                    shift8_treb_log('Listing marked as sold', array(
                        'post_id' => $existing_post_id,
                        'mls_number' => esc_html($mls_number)
                    ));
                    
                    return array(
                        'success' => true,
                        'action' => 'marked_sold',
                        'post_id' => $existing_post_id,
                        'title' => sanitize_text_field($listing['UnparsedAddress']),
                        'mls_number' => $mls_number
                    );
                } else {
                    // Update existing available listing
                    shift8_treb_log('Listing already exists, updating', array(
                        'mls_number' => esc_html($mls_number),
                        'existing_post_id' => $existing_post_id
                    ));
                    
                    $post_id = $this->update_listing_post($existing_post_id, $listing);
                    if (!$post_id) {
                        return false;
                    }
                    
                    // Update MLS number meta
                    update_post_meta($post_id, 'listing_mls_number', sanitize_text_field($listing['ListingKey']));
                    
                    // Update comprehensive listing data as custom meta fields
                    $this->store_listing_meta_fields($post_id, $listing);
                    
                    // Process listing images (same as create path)
                    $image_stats = $this->process_listing_images($post_id, $listing);

                    // Update post content with actual image HTML after processing
                    $this->update_post_content_with_images($post_id);
                    
                    shift8_treb_log('Listing updated successfully', array(
                        'post_id' => $post_id,
                        'mls_number' => esc_html($mls_number),
                        'image_stats' => $image_stats
                    ));
                    
                    return array(
                        'success' => true,
                        'action' => 'updated',
                        'post_id' => $post_id,
                        'title' => sanitize_text_field($listing['UnparsedAddress']),
                        'mls_number' => $mls_number
                    );
                }
            }

            // Skip importing new sold listings (we only update existing ones to sold status)
            if ($is_sold) {
                shift8_treb_log('Skipping new sold listing', array(
                    'mls_number' => esc_html($mls_number),
                    'reason' => 'Already sold, not importing new'
                ));
                
                return array(
                    'success' => false,
                    'action' => 'skipped',
                    'post_id' => null,
                    'title' => sanitize_text_field($listing['UnparsedAddress']),
                    'mls_number' => $mls_number,
                    'reason' => 'Sold listing - not importing new'
                );
            }


            // Final safety check: clean up any existing duplicates before creating new post
            $cleanup_result = $this->cleanup_duplicate_posts($mls_number);
            if ($cleanup_result['cleaned_up'] > 0) {
                shift8_treb_log('Cleaned up duplicates before creating new post', array(
                    'mls_number' => esc_html($mls_number),
                    'cleaned_up_count' => $cleanup_result['cleaned_up']
                ));
                
                // Re-check for existing post after cleanup
                $existing_post_id = $this->get_existing_listing_id($mls_number);
                if ($existing_post_id) {
                    // Update the existing post instead of creating new
                    $post_id = $this->update_listing_post($existing_post_id, $listing);
                    if (!$post_id) {
                        return false;
                    }
                    
                    return array(
                        'success' => true,
                        'action' => 'updated_after_cleanup',
                        'post_id' => $post_id,
                        'title' => sanitize_text_field($listing['UnparsedAddress']),
                        'mls_number' => $mls_number
                    );
                }
            }

            // Create the WordPress post
            $post_id = $this->create_listing_post($listing);
            
            if (!$post_id) {
                return false;
            }

            // IMMEDIATELY store MLS number as both meta and tag for duplicate detection
            // This prevents race conditions during rapid processing
            update_post_meta($post_id, 'listing_mls_number', sanitize_text_field($listing['ListingKey']));
            wp_set_post_tags($post_id, array(sanitize_text_field($listing['ListingKey'])), true);
            
            // Store comprehensive listing data as custom meta fields
            $this->store_listing_meta_fields($post_id, $listing);

            // Process listing images
            $image_stats = $this->process_listing_images($post_id, $listing);

            // Update post content with actual image HTML after processing
            $this->update_post_content_with_images($post_id);

            shift8_treb_log('Listing processed successfully', array(
                'post_id' => $post_id,
                'mls_number' => esc_html($mls_number),
                'image_stats' => $image_stats
            ));

            // Return detailed result for CLI reporting
            return array(
                'success' => true,
                'action' => 'created', // For now, assume all are new (we can enhance this later)
                'post_id' => $post_id,
                'title' => sanitize_text_field($listing['UnparsedAddress']),
                'mls_number' => $mls_number
            );

        } catch (Exception $e) {
            shift8_treb_log('Error processing listing', array(
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
                shift8_treb_log('Invalid listing - missing required field', array(
                    'missing_field' => esc_html($field)
                ));
                return false;
            }
        }

        return true;
    }


    /**
     * Get existing listing post ID by MLS number
     *
     * @since 1.0.0
     * @param string $mls_number MLS number
     * @return int|false Post ID if exists, false otherwise
     */
    private function get_existing_listing_id($mls_number) {
        // Primary check: by post meta (most reliable)
        $existing_posts = get_posts(array(
            'post_type' => 'post',
            'meta_key' => 'listing_mls_number',
            'meta_value' => $mls_number,
            'numberposts' => 1,
            'post_status' => 'any',
            'fields' => 'ids'
        ));

        if (!empty($existing_posts)) {
            return $existing_posts[0];
        }

        // Fallback check: by tag (handles race conditions during rapid processing)
        $tag_posts = get_posts(array(
            'post_type' => 'post',
            'tag' => sanitize_text_field($mls_number),
            'numberposts' => 1,
            'post_status' => 'any',
            'fields' => 'ids'
        ));

        if (!empty($tag_posts)) {
            // Found by tag but missing meta - fix the meta
            $post_id = $tag_posts[0];
            update_post_meta($post_id, 'listing_mls_number', sanitize_text_field($mls_number));
            
            shift8_treb_log('Fixed missing listing meta during duplicate check', array(
                'post_id' => $post_id,
                'mls_number' => esc_html($mls_number)
            ));
            
            return $post_id;
        }

        // Additional safety check: look for posts with MLS in title (last resort)
        $title_posts = get_posts(array(
            'post_type' => 'post',
            's' => $mls_number,
            'numberposts' => 1,
            'post_status' => 'any',
            'fields' => 'ids'
        ));

        if (!empty($title_posts)) {
            $post_id = $title_posts[0];
            
            // Verify this is actually the same listing by checking if MLS is in content or title
            $post = get_post($post_id);
            if ($post && (strpos($post->post_title, $mls_number) !== false || strpos($post->post_content, $mls_number) !== false)) {
                // Fix missing meta and tag
                update_post_meta($post_id, 'listing_mls_number', sanitize_text_field($mls_number));
                wp_set_post_tags($post_id, array(sanitize_text_field($mls_number)), true);
                
                shift8_treb_log('Fixed missing meta and tag during duplicate check', array(
                    'post_id' => $post_id,
                    'mls_number' => esc_html($mls_number),
                    'method' => 'title_search'
                ));
                
                return $post_id;
            }
        }

        return false;
    }

    /**
     * Clean up duplicate posts with the same MLS number
     *
     * @since 1.6.2
     * @param string $mls_number MLS number to check for duplicates
     * @return array Cleanup results
     */
    private function cleanup_duplicate_posts($mls_number) {
        // Find all posts with this MLS number
        $all_posts = get_posts(array(
            'post_type' => 'post',
            'meta_key' => 'listing_mls_number',
            'meta_value' => $mls_number,
            'numberposts' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        ));

        // Also check by tag
        $tag_posts = get_posts(array(
            'post_type' => 'post',
            'tag' => sanitize_text_field($mls_number),
            'numberposts' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        ));

        // Merge and deduplicate, then reindex array
        $all_duplicates = array_values(array_unique(array_merge($all_posts, $tag_posts)));

        if (count($all_duplicates) <= 1) {
            return array('duplicates_found' => 0, 'cleaned_up' => 0);
        }

        // Keep the first (oldest) post, remove others
        $keep_post_id = $all_duplicates[0];
        $removed_count = 0;

        for ($i = 1; $i < count($all_duplicates); $i++) {
            $duplicate_id = $all_duplicates[$i];
            
            // Move attachments from duplicate to primary post
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'post_parent' => $duplicate_id,
                'numberposts' => -1,
                'fields' => 'ids'
            ));

            foreach ($attachments as $attachment_id) {
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_parent' => $keep_post_id
                ));
            }

            // Delete the duplicate post
            wp_delete_post($duplicate_id, true);
            $removed_count++;
        }

        shift8_treb_log('Cleaned up duplicate posts', array(
            'mls_number' => esc_html($mls_number),
            'kept_post_id' => $keep_post_id,
            'removed_count' => $removed_count,
            'total_found' => count($all_duplicates)
        ));

        return array(
            'duplicates_found' => count($all_duplicates),
            'cleaned_up' => $removed_count,
            'kept_post_id' => $keep_post_id
        );
    }

    /**
     * Check if a listing is sold based on API data
     *
     * @since 1.5.1
     * @param array $listing Listing data from AMPRE API
     * @return bool True if listing is sold
     */
    private function is_listing_sold($listing) {
        // Check ContractStatus for sold indicators
        $contract_status = isset($listing['ContractStatus']) ? strtolower(trim($listing['ContractStatus'])) : '';
        if (in_array($contract_status, array('sold', 'closed'), true)) {
            return true;
        }

        // Check StandardStatus as fallback
        $standard_status = isset($listing['StandardStatus']) ? strtolower(trim($listing['StandardStatus'])) : '';
        if (in_array($standard_status, array('sold', 'closed'), true)) {
            return true;
        }

        return false;
    }

    /**
     * Handle updating an existing listing to sold status
     *
     * @since 1.5.1
     * @param int $post_id Existing post ID
     * @param array $listing Listing data from AMPRE API
     * @return bool True on success
     */
    private function handle_sold_listing_update($post_id, $listing) {
        try {
            // Get current post data
            $post = get_post($post_id);
            if (!$post) {
                return false;
            }

            // Check if already marked as sold (avoid duplicate processing)
            if ($this->is_post_marked_as_sold($post_id)) {
                shift8_treb_log('Post already marked as sold', array(
                    'post_id' => $post_id,
                    'mls_number' => esc_html($listing['ListingKey'] ?? 'unknown')
                ));
                return true;
            }

            // Update title to include (SOLD) prefix
            $current_title = $post->post_title;
            $new_title = '(SOLD) ' . $current_title;

            // Update the post
            $updated_post = array(
                'ID' => $post_id,
                'post_title' => sanitize_text_field($new_title)
            );

            $result = wp_update_post($updated_post);
            if (is_wp_error($result)) {
                shift8_treb_log('Failed to update sold listing title', array(
                    'post_id' => $post_id,
                    'error' => $result->get_error_message()
                ));
                return false;
            }

            // Add "Sold" tag
            wp_set_post_tags($post_id, array('Sold'), true); // true = append to existing tags

            // Update listing meta fields with sold status
            $this->store_listing_meta_fields($post_id, $listing);

            shift8_treb_log('Successfully marked listing as sold', array(
                'post_id' => $post_id,
                'old_title' => esc_html($current_title),
                'new_title' => esc_html($new_title),
                'mls_number' => esc_html($listing['ListingKey'] ?? 'unknown')
            ));

            return true;

        } catch (Exception $e) {
            shift8_treb_log('Error handling sold listing update', array(
                'post_id' => $post_id,
                'error' => esc_html($e->getMessage()),
                'mls_number' => esc_html($listing['ListingKey'] ?? 'unknown')
            ));
            return false;
        }
    }

    /**
     * Check if a post is already marked as sold
     *
     * @since 1.5.1
     * @param int $post_id Post ID
     * @return bool True if already marked as sold
     */
    private function is_post_marked_as_sold($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Check if title already contains (SOLD)
        if (strpos($post->post_title, '(SOLD)') !== false) {
            return true;
        }

        // Check if post has "Sold" tag
        $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
        if (in_array('Sold', $tags, true)) {
            return true;
        }

        return false;
    }


    /**
     * Check if agent is one of our agents
     *
     * @since 1.0.0
     * @param string $agent_id Agent ID
     * @return bool True if our agent
     */
    private function is_our_agent($agent_id) {
        $member_ids = isset($this->settings['member_id']) ? trim($this->settings['member_id']) : '';
        if (empty($member_ids)) {
            return false;
        }

        // Handle comma-separated list of member IDs
        $member_list = array_map('trim', explode(',', $member_ids));
        return in_array($agent_id, $member_list, true);
    }

    /**
     * Check if agent is excluded
     *
     * @since 1.0.0
     * @param string $agent_id Agent ID
     * @return bool True if excluded
     */
    private function is_excluded_agent($agent_id) {
        $excluded_member_ids = isset($this->settings['excluded_member_ids']) ? trim($this->settings['excluded_member_ids']) : '';
        if (empty($excluded_member_ids)) {
            return false;
        }

        // Handle comma-separated list of excluded member IDs
        $excluded_list = array_map('trim', explode(',', $excluded_member_ids));
        return in_array($agent_id, $excluded_list, true);
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
        $post_title = sanitize_text_field($listing['UnparsedAddress']);
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
            shift8_treb_log('Failed to create post', array(
                'error' => esc_html($post_id->get_error_message())
            ));
            return false;
        }

        return $post_id;
    }

    /**
     * Update existing listing post
     *
     * @since 1.0.0
     * @param int $post_id Existing post ID
     * @param array $listing Listing data from AMPRE API
     * @return int|false Post ID on success, false on failure
     */
    private function update_listing_post($post_id, $listing) {
        $post_data = array(
            'ID' => $post_id,
            'post_title' => $this->generate_post_title($listing),
            'post_content' => $this->generate_post_content($listing, $post_id),
            'post_excerpt' => $this->generate_post_excerpt($listing),
            'post_status' => 'publish',
            'post_date' => current_time('mysql'),
            'post_modified' => current_time('mysql')
        );

        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return false;
        }

        // Update category assignment
        $category_id = $this->get_listing_category_id($listing);
        if ($category_id) {
            wp_set_post_categories($post_id, array($category_id));
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
    private function generate_post_content($listing, $post_id = null) {
        $template = isset($this->settings['listing_template']) ? $this->settings['listing_template'] : $this->get_default_template();

        // Parse address components
        $address_parts = $this->parse_address($listing['UnparsedAddress']);
        
        // Prepare replacement variables (supporting both old and new placeholder formats)
        $replacements = array(
            // Original placeholders (keep for backward compatibility)
            '%ADDRESS%' => sanitize_text_field($listing['UnparsedAddress']),
            '%PRICE%' => '$' . number_format(intval($listing['ListPrice'])),
            '%MLS%' => sanitize_text_field($listing['ListingKey']),
            '%BEDROOMS%' => isset($listing['BedroomsTotal']) ? sanitize_text_field($listing['BedroomsTotal']) : 'N/A',
            '%BATHROOMS%' => isset($listing['BathroomsTotal']) ? sanitize_text_field($listing['BathroomsTotal']) : 'N/A',
            '%SQFT%' => isset($listing['LivingArea']) ? number_format(intval($listing['LivingArea'])) : 'N/A',
            '%DESCRIPTION%' => isset($listing['PublicRemarks']) ? wp_kses_post($listing['PublicRemarks']) : '',
            '%PROPERTY_TYPE%' => isset($listing['PropertyType']) ? sanitize_text_field($listing['PropertyType']) : 'N/A',
            '%CITY%' => isset($listing['City']) ? sanitize_text_field($listing['City']) : '',
            '%POSTAL_CODE%' => isset($listing['PostalCode']) ? sanitize_text_field($listing['PostalCode']) : '',
            
            // Template-specific placeholders (matching actual template usage)
            '%MLSNUMBER%' => sanitize_text_field($listing['ListingKey']),
            '%LISTPRICE%' => '$' . number_format(intval($listing['ListPrice'])),
            '%SQFOOTAGE%' => isset($listing['LivingArea']) ? number_format(intval($listing['LivingArea'])) : 'N/A',
            '%STREETNUMBER%' => $address_parts['number'],
            '%STREETNAME%' => $address_parts['street'],
            '%APT_NUM%' => $address_parts['unit'],
            
            // Additional template placeholders
            '%VIRTUALTOUR%' => $this->get_virtual_tour_link($listing),
            '%WALKSCORECODE%' => $this->get_walkscore_html($listing),
            '%WPBLOG%' => get_site_url(),
            
            // Universal image placeholders (work with any page builder)
            '%LISTING_IMAGES%' => '<!-- LISTING_IMAGES_PLACEHOLDER -->',
            '%BASE64IMAGES%' => '<!-- BASE64IMAGES_PLACEHOLDER -->',
            '%LISTING_IMAGE_1%' => '<!-- LISTING_IMAGE_1_PLACEHOLDER -->',
            '%LISTING_IMAGE_2%' => '<!-- LISTING_IMAGE_2_PLACEHOLDER -->',
            '%LISTING_IMAGE_3%' => '<!-- LISTING_IMAGE_3_PLACEHOLDER -->',
            '%LISTING_IMAGE_4%' => '<!-- LISTING_IMAGE_4_PLACEHOLDER -->',
            '%LISTING_IMAGE_5%' => '<!-- LISTING_IMAGE_5_PLACEHOLDER -->',
            '%LISTING_IMAGE_6%' => '<!-- LISTING_IMAGE_6_PLACEHOLDER -->',
            '%LISTING_IMAGE_7%' => '<!-- LISTING_IMAGE_7_PLACEHOLDER -->',
            '%LISTING_IMAGE_8%' => '<!-- LISTING_IMAGE_8_PLACEHOLDER -->',
            '%LISTING_IMAGE_9%' => '<!-- LISTING_IMAGE_9_PLACEHOLDER -->',
            '%LISTING_IMAGE_10%' => '<!-- LISTING_IMAGE_10_PLACEHOLDER -->',
            
            // WordPress native placeholders for modern themes
            '%FEATURED_IMAGE%' => '<!-- FEATURED_IMAGE_PLACEHOLDER -->',
            '%IMAGE_GALLERY%' => '<!-- IMAGE_GALLERY_PLACEHOLDER -->',
            '%VIRTUAL_TOUR_SECTION%' => $this->get_virtual_tour_section($listing),
            
            
            // Map coordinates for Google Maps (with geocoding fallback)
            '%MAPLAT%' => $this->get_listing_latitude($listing, $post_id),
            '%MAPLNG%' => $this->get_listing_longitude($listing, $post_id),
            '%GOOGLEMAPCODE%' => $this->get_google_map_html($listing, $post_id)
        );

        // Replace placeholders in template
        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        return $content;
    }

    /**
     * Update post content with image placeholders after images are processed
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @return void
     */
    private function update_post_content_with_images($post_id) {
        $post_content = get_post_field('post_content', $post_id);
        
        // Get all image URLs for this post
        $image_urls = $this->get_listing_image_urls($post_id);
        
        // Replace universal image placeholders
        $post_content = str_replace('<!-- LISTING_IMAGES_PLACEHOLDER -->', implode(',', $image_urls), $post_content);
        
        // Replace base64 encoded image URLs (for Visual Composer compatibility)
        $base64_images = $this->get_base64_encoded_images($image_urls);
        $post_content = str_replace('<!-- BASE64IMAGES_PLACEHOLDER -->', $base64_images, $post_content);
        
        // Replace individual image placeholders (1-10)
        for ($i = 1; $i <= 10; $i++) {
            $image_url = isset($image_urls[$i - 1]) ? $image_urls[$i - 1] : '';
            $post_content = str_replace("<!-- LISTING_IMAGE_{$i}_PLACEHOLDER -->", $image_url, $post_content);
        }
        
        // Replace WordPress native placeholders
        $featured_image_html = $this->get_featured_image_html($post_id);
        $gallery_html = $this->get_image_gallery_html($post_id);
        
        $post_content = str_replace('<!-- FEATURED_IMAGE_PLACEHOLDER -->', $featured_image_html, $post_content);
        $post_content = str_replace('<!-- IMAGE_GALLERY_PLACEHOLDER -->', $gallery_html, $post_content);
        
        
        // Update the post content
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $post_content
        ));
    }

    /**
     * Generate post excerpt
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return string Post excerpt
     */
    private function generate_post_excerpt($listing) {
        // Get excerpt template from settings
        $template = isset($this->settings['post_excerpt_template']) ? 
            $this->settings['post_excerpt_template'] : 
            '%ADDRESS%\n%LISTPRICE%\nMLS : %MLSNUMBER%';

        // Parse address components
        $address_parts = $this->parse_address($listing['UnparsedAddress']);
        
        // Prepare replacement variables (same as main content template)
        $replacements = array(
            // Original placeholders (keep for backward compatibility)
            '%ADDRESS%' => sanitize_text_field($listing['UnparsedAddress']),
            '%PRICE%' => '$' . number_format(floatval($listing['ListPrice']), 2),
            '%MLS%' => sanitize_text_field($listing['ListingKey']),
            '%BEDROOMS%' => isset($listing['BedroomsTotal']) ? sanitize_text_field($listing['BedroomsTotal']) : 'N/A',
            '%BATHROOMS%' => isset($listing['BathroomsTotal']) ? sanitize_text_field($listing['BathroomsTotal']) : 'N/A',
            '%SQFT%' => isset($listing['LivingArea']) ? number_format(intval($listing['LivingArea'])) : 'N/A',
            '%DESCRIPTION%' => isset($listing['PublicRemarks']) ? wp_kses_post($listing['PublicRemarks']) : '',
            '%PROPERTY_TYPE%' => isset($listing['PropertyType']) ? sanitize_text_field($listing['PropertyType']) : 'N/A',
            '%CITY%' => isset($listing['City']) ? sanitize_text_field($listing['City']) : '',
            '%POSTAL_CODE%' => isset($listing['PostalCode']) ? sanitize_text_field($listing['PostalCode']) : '',
            
            // Template-specific placeholders (matching actual template usage)
            '%MLSNUMBER%' => sanitize_text_field($listing['ListingKey']),
            '%LISTPRICE%' => '$' . number_format(floatval($listing['ListPrice']), 2),
            '%SQFOOTAGE%' => isset($listing['LivingArea']) ? number_format(intval($listing['LivingArea'])) : 'N/A',
            '%STREETNUMBER%' => $address_parts['number'],
            '%STREETNAME%' => $address_parts['street'],
            '%APT_NUM%' => $address_parts['unit'],
            
            // Additional template placeholders (simplified for excerpt)
            '%VIRTUALTOUR%' => $this->get_virtual_tour_link($listing),
            '%WPBLOG%' => get_site_url(),
            
            // Coordinates (if available)
            '%MAPLAT%' => $this->get_listing_latitude($listing),
            '%MAPLNG%' => $this->get_listing_longitude($listing),
        );

        // Replace placeholders in template
        $excerpt = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Allow HTML in excerpts - just sanitize with wp_kses_post to allow safe HTML
        return wp_kses_post($excerpt);
    }

    /**
     * Store comprehensive listing data as WordPress custom meta fields
     * 
     * Following WordPress best practices:
     * - Plugin-unique meta key prefix: 'shift8_treb_'
     * - Sanitized values appropriate to data type
     * - Comprehensive field mapping for extensibility
     *
     * @since 1.2.0
     * @param int $post_id WordPress post ID
     * @param array $listing Raw listing data from AMPRE API
     * @return void
     */
    private function store_listing_meta_fields($post_id, $listing) {
        // Define meta field mappings with sanitization
        $meta_fields = array(
            // Core listing identifiers
            'shift8_treb_listing_key' => array('field' => 'ListingKey', 'sanitize' => 'text'),
            'shift8_treb_mls_number' => array('field' => 'ListingKey', 'sanitize' => 'text'), // Alias for compatibility
            'shift8_treb_list_agent_key' => array('field' => 'ListAgentKey', 'sanitize' => 'text'),
            'shift8_treb_listing_id' => array('field' => 'ListingId', 'sanitize' => 'text'),
            
            // Address and location data
            'shift8_treb_unparsed_address' => array('field' => 'UnparsedAddress', 'sanitize' => 'text'),
            'shift8_treb_street_number' => array('field' => 'StreetNumber', 'sanitize' => 'text'),
            'shift8_treb_street_name' => array('field' => 'StreetName', 'sanitize' => 'text'),
            'shift8_treb_unit_number' => array('field' => 'UnitNumber', 'sanitize' => 'text'),
            'shift8_treb_city' => array('field' => 'City', 'sanitize' => 'text'),
            'shift8_treb_state_province' => array('field' => 'StateOrProvince', 'sanitize' => 'text'),
            'shift8_treb_postal_code' => array('field' => 'PostalCode', 'sanitize' => 'text'),
            'shift8_treb_country' => array('field' => 'Country', 'sanitize' => 'text'),
            
            // Geographic coordinates
            'shift8_treb_latitude' => array('field' => 'Latitude', 'sanitize' => 'float'),
            'shift8_treb_longitude' => array('field' => 'Longitude', 'sanitize' => 'float'),
            
            // Pricing information
            'shift8_treb_list_price' => array('field' => 'ListPrice', 'sanitize' => 'int'),
            'shift8_treb_original_list_price' => array('field' => 'OriginalListPrice', 'sanitize' => 'int'),
            'shift8_treb_close_price' => array('field' => 'ClosePrice', 'sanitize' => 'int'),
            
            // Property characteristics
            'shift8_treb_property_type' => array('field' => 'PropertyType', 'sanitize' => 'text'),
            'shift8_treb_property_subtype' => array('field' => 'PropertySubType', 'sanitize' => 'text'),
            'shift8_treb_bedrooms_total' => array('field' => 'BedroomsTotal', 'sanitize' => 'int'),
            'shift8_treb_bathrooms_total' => array('field' => 'BathroomsTotal', 'sanitize' => 'float'),
            'shift8_treb_bathrooms_full' => array('field' => 'BathroomsFull', 'sanitize' => 'int'),
            'shift8_treb_bathrooms_half' => array('field' => 'BathroomsHalf', 'sanitize' => 'int'),
            'shift8_treb_living_area' => array('field' => 'LivingArea', 'sanitize' => 'int'),
            'shift8_treb_lot_size_area' => array('field' => 'LotSizeArea', 'sanitize' => 'float'),
            'shift8_treb_lot_size_units' => array('field' => 'LotSizeUnits', 'sanitize' => 'text'),
            'shift8_treb_year_built' => array('field' => 'YearBuilt', 'sanitize' => 'int'),
            
            // Status and dates
            'shift8_treb_standard_status' => array('field' => 'StandardStatus', 'sanitize' => 'text'),
            'shift8_treb_contract_status' => array('field' => 'ContractStatus', 'sanitize' => 'text'),
            'shift8_treb_listing_contract_date' => array('field' => 'ListingContractDate', 'sanitize' => 'datetime'),
            'shift8_treb_modification_timestamp' => array('field' => 'ModificationTimestamp', 'sanitize' => 'datetime'),
            'shift8_treb_on_market_date' => array('field' => 'OnMarketDate', 'sanitize' => 'datetime'),
            'shift8_treb_off_market_date' => array('field' => 'OffMarketDate', 'sanitize' => 'datetime'),
            'shift8_treb_close_date' => array('field' => 'CloseDate', 'sanitize' => 'datetime'),
            
            // Descriptive content
            'shift8_treb_public_remarks' => array('field' => 'PublicRemarks', 'sanitize' => 'textarea'),
            'shift8_treb_private_remarks' => array('field' => 'PrivateRemarks', 'sanitize' => 'textarea'),
            'shift8_treb_marketing_remarks' => array('field' => 'MarketingRemarks', 'sanitize' => 'textarea'),
            
            // Virtual tour and media
            'shift8_treb_virtual_tour_url' => array('field' => 'VirtualTourURLUnbranded', 'sanitize' => 'url'),
            'shift8_treb_virtual_tour_branded' => array('field' => 'VirtualTourURLBranded', 'sanitize' => 'url'),
            'shift8_treb_photo_count' => array('field' => 'PhotosCount', 'sanitize' => 'int'),
            
            // Additional property details
            'shift8_treb_stories' => array('field' => 'Stories', 'sanitize' => 'int'),
            'shift8_treb_garage_spaces' => array('field' => 'GarageSpaces', 'sanitize' => 'int'),
            'shift8_treb_parking_total' => array('field' => 'ParkingTotal', 'sanitize' => 'int'),
            'shift8_treb_pool_private_yn' => array('field' => 'PoolPrivateYN', 'sanitize' => 'boolean'),
            'shift8_treb_waterfront_yn' => array('field' => 'WaterfrontYN', 'sanitize' => 'boolean'),
            
            // MLS specific fields
            'shift8_treb_mls_area_major' => array('field' => 'MLSAreaMajor', 'sanitize' => 'text'),
            'shift8_treb_mls_area_minor' => array('field' => 'MLSAreaMinor', 'sanitize' => 'text'),
            'shift8_treb_subdivision_name' => array('field' => 'SubdivisionName', 'sanitize' => 'text'),
            
            // Agent and office information
            'shift8_treb_list_agent_full_name' => array('field' => 'ListAgentFullName', 'sanitize' => 'text'),
            'shift8_treb_list_agent_first_name' => array('field' => 'ListAgentFirstName', 'sanitize' => 'text'),
            'shift8_treb_list_agent_last_name' => array('field' => 'ListAgentLastName', 'sanitize' => 'text'),
            'shift8_treb_list_office_key' => array('field' => 'ListOfficeKey', 'sanitize' => 'text'),
            'shift8_treb_list_office_name' => array('field' => 'ListOfficeName', 'sanitize' => 'text'),
            
            // Co-agent information
            'shift8_treb_co_list_agent_key' => array('field' => 'CoListAgentKey', 'sanitize' => 'text'),
            'shift8_treb_co_list_agent_full_name' => array('field' => 'CoListAgentFullName', 'sanitize' => 'text'),
            'shift8_treb_co_list_office_key' => array('field' => 'CoListOfficeKey', 'sanitize' => 'text'),
            'shift8_treb_co_list_office_name' => array('field' => 'CoListOfficeName', 'sanitize' => 'text'),
            
            // Financial details
            'shift8_treb_tax_annual_amount' => array('field' => 'TaxAnnualAmount', 'sanitize' => 'float'),
            'shift8_treb_tax_year' => array('field' => 'TaxYear', 'sanitize' => 'int'),
            'shift8_treb_association_fee' => array('field' => 'AssociationFee', 'sanitize' => 'float'),
            'shift8_treb_association_fee_frequency' => array('field' => 'AssociationFeeFrequency', 'sanitize' => 'text'),
            
            // Utility and features
            'shift8_treb_heating' => array('field' => 'Heating', 'sanitize' => 'text'),
            'shift8_treb_cooling' => array('field' => 'Cooling', 'sanitize' => 'text'),
            'shift8_treb_utilities' => array('field' => 'Utilities', 'sanitize' => 'text'),
            'shift8_treb_appliances' => array('field' => 'Appliances', 'sanitize' => 'text'),
            'shift8_treb_architectural_style' => array('field' => 'ArchitecturalStyle', 'sanitize' => 'text'),
            'shift8_treb_construction_materials' => array('field' => 'ConstructionMaterials', 'sanitize' => 'text'),
            'shift8_treb_roof' => array('field' => 'Roof', 'sanitize' => 'text'),
            
            // School information
            'shift8_treb_elementary_school' => array('field' => 'ElementarySchool', 'sanitize' => 'text'),
            'shift8_treb_middle_school' => array('field' => 'MiddleOrJuniorSchool', 'sanitize' => 'text'),
            'shift8_treb_high_school' => array('field' => 'HighSchool', 'sanitize' => 'text'),
            'shift8_treb_school_district' => array('field' => 'SchoolDistrict', 'sanitize' => 'text'),
        );

        // Store each field as meta if it exists in the listing data
        foreach ($meta_fields as $meta_key => $config) {
            $field_name = $config['field'];
            $sanitize_type = $config['sanitize'];
            
            if (isset($listing[$field_name])) {
                $value = $listing[$field_name];
                
                // Sanitize based on data type
                $sanitized_value = $this->sanitize_meta_value($value, $sanitize_type);
                
                // Only store non-empty values
                if ($sanitized_value !== '' && $sanitized_value !== null) {
                    update_post_meta($post_id, $meta_key, $sanitized_value);
                }
            }
        }
        
        // Store parsed address components as separate meta fields
        $address_parts = $this->parse_address($listing['UnparsedAddress']);
        update_post_meta($post_id, 'shift8_treb_parsed_street_number', sanitize_text_field($address_parts['number']));
        update_post_meta($post_id, 'shift8_treb_parsed_street_name', sanitize_text_field($address_parts['street']));
        update_post_meta($post_id, 'shift8_treb_parsed_unit', sanitize_text_field($address_parts['unit']));
        
        // Store calculated/derived fields
        update_post_meta($post_id, 'shift8_treb_price_per_sqft', $this->calculate_price_per_sqft($listing));
        update_post_meta($post_id, 'shift8_treb_days_on_market', $this->calculate_days_on_market($listing));
        update_post_meta($post_id, 'shift8_treb_import_date', current_time('mysql'));
        update_post_meta($post_id, 'shift8_treb_last_updated', current_time('mysql'));
        
        // Store geocoded coordinates if AMPRE API didn't provide them
        $this->store_geocoded_coordinates($post_id, $listing);
        
        shift8_treb_log('Stored listing meta fields', array(
            'post_id' => $post_id,
            'mls_number' => esc_html($listing['ListingKey'] ?? 'unknown'),
            'meta_fields_stored' => count($meta_fields)
        ));
    }

    /**
     * Sanitize meta value based on data type
     *
     * @since 1.2.0
     * @param mixed $value Raw value from API
     * @param string $type Sanitization type
     * @return mixed Sanitized value
     */
    private function sanitize_meta_value($value, $type) {
        if ($value === null || $value === '') {
            return '';
        }
        
        switch ($type) {
            case 'text':
                return sanitize_text_field($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'url':
                return esc_url_raw($value);
                
            case 'int':
                return intval($value);
                
            case 'float':
                return floatval($value);
                
            case 'boolean':
                // Handle various boolean representations from API
                if (is_bool($value)) {
                    return $value ? '1' : '0';
                }
                $value = strtolower(trim($value));
                return in_array($value, array('true', '1', 'yes', 'y'), true) ? '1' : '0';
                
            case 'datetime':
                // Ensure datetime is in MySQL format
                $timestamp = strtotime($value);
                return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : '';
                
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Store geocoded coordinates as meta fields if not provided by AMPRE API
     *
     * @since 1.2.0
     * @param int $post_id Post ID
     * @param array $listing Listing data
     * @return void
     */
    private function store_geocoded_coordinates($post_id, $listing) {
        // Skip if AMPRE API already provided coordinates
        if (isset($listing['Latitude']) && isset($listing['Longitude']) && 
            !empty($listing['Latitude']) && !empty($listing['Longitude'])) {
            // AMPRE provided coordinates, they're already stored by the meta fields loop
            shift8_treb_log('Using AMPRE API coordinates', array(
                'mls_number' => esc_html($listing['ListingKey']),
                'latitude' => $listing['Latitude'],
                'longitude' => $listing['Longitude']
            ));
            return;
        }
        
        // Attempt geocoding with OpenStreetMap (no API key required)
        
        $address = $listing['UnparsedAddress'];
        $coordinates = $this->geocode_address($address);
        
        if ($coordinates && isset($coordinates['lat']) && isset($coordinates['lng'])) {
            // Store geocoded coordinates as meta fields
            update_post_meta($post_id, 'shift8_treb_latitude', floatval($coordinates['lat']));
            update_post_meta($post_id, 'shift8_treb_longitude', floatval($coordinates['lng']));
            
            shift8_treb_log('Stored geocoded coordinates', array(
                'mls_number' => esc_html($listing['ListingKey']),
                'address' => esc_html($address),
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng']
            ));
        } else {
            // Store default Toronto coordinates so maps still work
            update_post_meta($post_id, 'shift8_treb_latitude', 43.6532);
            update_post_meta($post_id, 'shift8_treb_longitude', -79.3832);
            
            shift8_treb_log('Geocoding failed, stored default Toronto coordinates', array(
                'mls_number' => esc_html($listing['ListingKey']),
                'address' => esc_html($address),
                'latitude' => 43.6532,
                'longitude' => -79.3832
            ));
        }
    }

    /**
     * Calculate price per square foot
     *
     * @since 1.2.0
     * @param array $listing Listing data
     * @return float Price per square foot or 0 if cannot calculate
     */
    private function calculate_price_per_sqft($listing) {
        $price = isset($listing['ListPrice']) ? floatval($listing['ListPrice']) : 0;
        $sqft = isset($listing['LivingArea']) ? floatval($listing['LivingArea']) : 0;
        
        if ($price > 0 && $sqft > 0) {
            return round($price / $sqft, 2);
        }
        
        return 0;
    }

    /**
     * Calculate days on market
     *
     * @since 1.2.0
     * @param array $listing Listing data
     * @return int Days on market or 0 if cannot calculate
     */
    private function calculate_days_on_market($listing) {
        $on_market_date = isset($listing['OnMarketDate']) ? $listing['OnMarketDate'] : 
                         (isset($listing['ListingContractDate']) ? $listing['ListingContractDate'] : '');
        
        if (empty($on_market_date)) {
            return 0;
        }
        
        $market_timestamp = strtotime($on_market_date);
        if (!$market_timestamp) {
            return 0;
        }
        
        return max(0, floor((time() - $market_timestamp) / (24 * 60 * 60)));
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
        $is_our_agent = $this->is_our_agent($agent_id);
        $category_name = $is_our_agent ? 'Listings' : 'OtherListings';
        
        shift8_treb_log('Category assignment', array(
            'mls_number' => isset($listing['ListingKey']) ? esc_html($listing['ListingKey']) : 'unknown',
            'agent_id' => esc_html($agent_id),
            'member_ids' => esc_html($this->settings['member_id']),
            'is_our_agent' => $is_our_agent,
            'category_name' => esc_html($category_name)
        ));
        
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
     * Process listing images using AMPRE Media API
     * 
     * Best Practice: Single-process import for atomic operations and immediate user experience
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @param array $listing Listing data
     * @return array Statistics about image processing
     */
    private function process_listing_images($post_id, $listing) {
        $mls_number = sanitize_text_field($listing['ListingKey']);
        $stats = array(
            'total' => 0,
            'downloaded' => 0,
            'failed' => 0,
            'featured_set' => false
        );
        
        // Get media from AMPRE API
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::line("  Fetching media for MLS {$mls_number}...");
        }
        
        require_once SHIFT8_TREB_PLUGIN_DIR . 'includes/class-shift8-treb-ampre-service.php';
        $ampre_service = new Shift8_TREB_AMPRE_Service($this->settings);
        
        $media_items = $ampre_service->get_media_for_listing($mls_number);
        
        if (is_wp_error($media_items) || empty($media_items)) {
            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::line("  No media found for MLS {$mls_number}");
            }
            shift8_treb_log('No media found for listing', array(
                'mls_number' => esc_html($mls_number),
                'error' => is_wp_error($media_items) ? $media_items->get_error_message() : 'No media items'
            ));
            return $stats;
        }

        $stats['total'] = count($media_items);
        
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::line("  Found {$stats['total']} images for MLS {$mls_number}");
        }
        
        shift8_treb_log('Processing listing images', array(
            'mls_number' => esc_html($mls_number),
            'image_count' => $stats['total']
        ));

        $featured_image_set = false;
        $preferred_image_id = null;
        $first_successful_image = null;

        // Performance: Allow unlimited images by default, but provide filter for hosting constraints
        // Following .cursorrules performanceOptimizationPatterns: make expensive operations conditional
        $original_count = count($media_items);
        $max_images = apply_filters('shift8_treb_max_images_per_listing', 0); // 0 = unlimited
        if ($max_images > 0) {
            $media_items = array_slice($media_items, 0, $max_images);
            shift8_treb_log('Limited images per listing', array(
                'mls_number' => esc_html($mls_number),
                'original_count' => $original_count,
                'limited_count' => count($media_items),
                'max_images' => $max_images
            ));
        }

        // Performance: Check processing mode
        // Following .cursorrules performanceOptimizationPatterns: batch processing default for better performance
        $skip_images = isset($this->settings['skip_image_download']) && $this->settings['skip_image_download'];
        $batch_mode = !isset($this->settings['batch_image_processing']) || $this->settings['batch_image_processing']; // Default: true
        
        if ($skip_images) {
            shift8_treb_log('Skipping image download for fast sync', array(
                'mls_number' => esc_html($mls_number)
            ));
            
            // Store external references only
            foreach ($media_items as $index => $media) {
                if (isset($media['MediaURL']) && !empty($media['MediaURL'])) {
                    $is_preferred = isset($media['PreferredPhotoYN']) && $media['PreferredPhotoYN'] === true;
                    $this->store_external_image_reference($post_id, $media['MediaURL'], $mls_number, $index + 1, $is_preferred);
                }
            }
            return $stats;
        }

        // Batch processing mode for better performance
        if ($batch_mode) {
            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::line("  Starting batch download for MLS {$mls_number}...");
            }
            return $this->process_images_batch($post_id, $media_items, $mls_number, $stats);
        }

        foreach ($media_items as $index => $media) {
            if (!isset($media['MediaURL']) || empty($media['MediaURL'])) {
                continue;
            }

            $image_url = esc_url_raw($media['MediaURL']);
            $is_preferred = isset($media['PreferredPhotoYN']) && $media['PreferredPhotoYN'] === true;
            
            // Best Practice: Download with retry and fallback to external URL
            $attachment_id = $this->download_and_attach_image_with_retry($image_url, $post_id, $mls_number, $index + 1);

            if ($attachment_id) {
                $stats['downloaded']++;
                
                // Track first successful image as fallback
                if (!$first_successful_image) {
                    $first_successful_image = $attachment_id;
                }
                
                // Track preferred image for featured image (highest priority)
                if ($is_preferred) {
                    $preferred_image_id = $attachment_id;
                }
            } else {
                $stats['failed']++;
                
                // Fallback: Store external URL for future retry or display
                $this->store_external_image_reference($post_id, $image_url, $mls_number, $index + 1, $is_preferred);
                
                shift8_treb_log('Image download failed, stored external reference', array(
                    'mls_number' => esc_html($mls_number),
                    'image_url' => esc_html($image_url),
                    'index' => $index
                ));
            }
        }

        // Featured image priority logic:
        // 1. First image (image_number = 1) - ALWAYS gets priority for consistency
        // 2. Preferred image (PreferredPhotoYN = true) - secondary priority
        // 3. First successfully downloaded image - fallback
        
        // Find the first image (image_number = 1) if it was successfully downloaded
        $first_image_id = null;
        foreach ($media_items as $index => $media) {
            if (($index + 1) == 1) { // First image
                $existing_attachment = $this->get_existing_attachment($mls_number, 1);
                if ($existing_attachment) {
                    $first_image_id = $existing_attachment;
                } elseif ($first_successful_image && ($index + 1) == 1) {
                    $first_image_id = $first_successful_image;
                }
                break;
            }
        }
        
        // Set featured image with priority: First image > Preferred > First successful
        $featured_image_id = $first_image_id ?: ($preferred_image_id ?: $first_successful_image);
        
        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
            $stats['featured_set'] = true;
            
            $priority_type = $first_image_id ? 'first_image' : ($preferred_image_id ? 'preferred' : 'first_successful');
            
            shift8_treb_log('Set featured image', array(
                'mls_number' => esc_html($mls_number),
                'attachment_id' => $featured_image_id,
                'priority_type' => $priority_type
            ));
        }

        // Log final statistics
        shift8_treb_log('Image processing completed', array(
            'mls_number' => esc_html($mls_number),
            'stats' => $stats
        ));

        return $stats;
    }

    /**
     * Download and attach image to post with enhanced error handling
     * 
     * Best Practice: Robust download with proper validation and cleanup
     *
     * @since 1.0.0
     * @param string $image_url Image URL
     * @param int $post_id Post ID
     * @param string $mls_number MLS number
     * @param int $image_number Image number
     * @return int|false Attachment ID on success, false on failure
     */
    private function download_and_attach_image($image_url, $post_id, $mls_number, $image_number) {
        try {
            // Best Practice: Validate URL before attempting download
            if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                shift8_treb_log('Invalid image URL', array(
                    'url' => esc_html($image_url),
                    'mls_number' => esc_html($mls_number)
                ));
                return false;
            }

            // Best Practice: Check if image already exists to avoid duplicates
            $existing_attachment = $this->get_existing_attachment($mls_number, $image_number);
            if ($existing_attachment) {
                return $existing_attachment;
            }

            // Performance: Optimized download settings with faster timeout
            $response = wp_remote_get($image_url, array(
                'timeout' => 10, // Reduced from 30 to 10 seconds
                'user-agent' => 'WordPress/TREB-Plugin',
                'headers' => array(
                    'Accept' => 'image/*'
                ),
                'sslverify' => false // Skip SSL verification for speed (external images)
            ));
            
            if (is_wp_error($response)) {
                shift8_treb_log('Image download failed', array(
                    'url' => esc_html($image_url),
                    'error' => $response->get_error_message(),
                    'mls_number' => esc_html($mls_number)
                ));
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                shift8_treb_log('Image download HTTP error', array(
                    'url' => esc_html($image_url),
                    'response_code' => $response_code,
                    'mls_number' => esc_html($mls_number)
                ));
                return false;
            }

            $image_data = wp_remote_retrieve_body($response);
            if (empty($image_data)) {
                return false;
            }

            // Best Practice: Validate image data
            $image_size = strlen($image_data);
            if ($image_size < 1024) { // Less than 1KB is likely not a valid image
                shift8_treb_log('Image too small', array(
                    'url' => esc_html($image_url),
                    'size' => $image_size,
                    'mls_number' => esc_html($mls_number)
                ));
                return false;
            }

            // Best Practice: Determine file extension from content type
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            $extension = $this->get_file_extension_from_content_type($content_type);
            
            // Prepare filename with proper extension
            $filename = sanitize_file_name($mls_number . '_' . $image_number . '.' . $extension);
            
            // Upload to WordPress media library
            $upload = wp_upload_bits($filename, null, $image_data);
            
            if ($upload['error']) {
                shift8_treb_log('WordPress upload failed', array(
                    'error' => $upload['error'],
                    'mls_number' => esc_html($mls_number),
                    'filename' => esc_html($filename)
                ));
                return false;
            }

            // Create attachment with proper metadata
            $attachment = array(
                'post_mime_type' => $content_type ?: 'image/jpeg',
                'post_title' => sanitize_text_field($mls_number . ' - Photo ' . $image_number),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_excerpt' => sanitize_text_field('Property photo for MLS ' . $mls_number)
            );

            $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

            if (is_wp_error($attachment_id)) {
                // Clean up uploaded file if attachment creation fails
                if (file_exists($upload['file'])) {
                    wp_delete_file($upload['file']);
                }
                shift8_treb_log('Attachment creation failed', array(
                    'error' => esc_html($attachment_id->get_error_message()),
                    'post_id' => $post_id,
                    'mls_number' => esc_html($mls_number),
                    'image_number' => $image_number
                ), 'error');
                return false;
            }

            // Verify post_parent was set correctly (debugging for post_parent issue)
            $created_attachment = get_post($attachment_id);
            if ($created_attachment && $created_attachment->post_parent != $post_id) {
                shift8_treb_log('Post parent mismatch detected', array(
                    'attachment_id' => $attachment_id,
                    'expected_parent' => $post_id,
                    'actual_parent' => $created_attachment->post_parent,
                    'mls_number' => esc_html($mls_number),
                    'image_number' => $image_number
                ), 'warning');
                
                // Attempt to fix the post_parent
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_parent' => $post_id
                ));
            }

            // Generate attachment metadata (thumbnails, etc.)
            if (!function_exists('wp_generate_attachment_metadata')) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }
            
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_data);

            // Best Practice: Add custom meta for easier management
            update_post_meta($attachment_id, '_treb_mls_number', sanitize_text_field($mls_number));
            update_post_meta($attachment_id, '_treb_image_number', intval($image_number));
            update_post_meta($attachment_id, '_treb_source_url', esc_url_raw($image_url));

            shift8_treb_log('Image downloaded successfully', array(
                'attachment_id' => $attachment_id,
                'mls_number' => esc_html($mls_number),
                'image_number' => $image_number,
                'file_size' => $image_size
            ));

            return $attachment_id;

        } catch (Exception $e) {
            shift8_treb_log('Image download exception', array(
                'url' => esc_html($image_url),
                'error' => esc_html($e->getMessage()),
                'mls_number' => esc_html($mls_number)
            ));
            return false;
        }
    }

    /**
     * Get existing attachment for MLS listing image
     *
     * @since 1.0.0
     * @param string $mls_number MLS number
     * @param int $image_number Image number
     * @return int|false Attachment ID if exists, false otherwise
     */
    private function get_existing_attachment($mls_number, $image_number) {
        // First check by meta (most reliable)
        $existing = get_posts(array(
            'post_type' => 'attachment',
            'meta_query' => array(
                array(
                    'key' => '_treb_mls_number',
                    'value' => sanitize_text_field($mls_number),
                    'compare' => '='
                ),
                array(
                    'key' => '_treb_image_number',
                    'value' => intval($image_number),
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if (!empty($existing)) {
            return $existing[0];
        }

        // Fallback: Check for WordPress duplicate filenames (e.g., image-1.jpg, image-2.jpg)
        // This handles cases where meta wasn't set properly during previous imports
        $filename_pattern = sanitize_text_field($mls_number) . '_' . intval($image_number);
        
        $duplicate_check = get_posts(array(
            'post_type' => 'attachment',
            'meta_query' => array(
                array(
                    'key' => '_wp_attached_file',
                    'value' => $filename_pattern,
                    'compare' => 'LIKE'
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        if (!empty($duplicate_check)) {
            // Return the first one and clean up duplicates
            $primary_attachment = $duplicate_check[0];
            
            if (count($duplicate_check) > 1) {
                shift8_treb_log('Found duplicate attachments, cleaning up', array(
                    'mls_number' => esc_html($mls_number),
                    'image_number' => $image_number,
                    'duplicates_found' => count($duplicate_check),
                    'keeping_attachment_id' => $primary_attachment
                ));
                
                // Clean up duplicates (keep the first, remove others)
                for ($i = 1; $i < count($duplicate_check); $i++) {
                    wp_delete_attachment($duplicate_check[$i], true);
                }
            }
            
            // Ensure proper meta is set on the primary attachment
            update_post_meta($primary_attachment, '_treb_mls_number', sanitize_text_field($mls_number));
            update_post_meta($primary_attachment, '_treb_image_number', intval($image_number));
            
            return $primary_attachment;
        }

        return false;
    }

    /**
     * Get file extension from content type
     *
     * @since 1.0.0
     * @param string $content_type Content type header
     * @return string File extension
     */
    private function get_file_extension_from_content_type($content_type) {
        $mime_to_ext = array(
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        );

        return isset($mime_to_ext[$content_type]) ? $mime_to_ext[$content_type] : 'jpg';
    }

    /**
     * Download and attach image with retry logic
     *
     * @since 1.0.0
     * @param string $image_url Image URL
     * @param int $post_id Post ID
     * @param string $mls_number MLS number
     * @param int $image_number Image number
     * @return int|false Attachment ID on success, false on failure
     */
    private function download_and_attach_image_with_retry($image_url, $post_id, $mls_number, $image_number) {
        // First attempt
        $attachment_id = $this->download_and_attach_image($image_url, $post_id, $mls_number, $image_number);
        
        if ($attachment_id) {
            return $attachment_id;
        }

        // Performance: Short delay before retry
        sleep(1);
        
        // Second attempt (one retry only)
        shift8_treb_log('Retrying image download', array(
            'mls_number' => esc_html($mls_number),
            'image_url' => esc_html($image_url),
            'attempt' => 2
        ));
        
        $attachment_id = $this->download_and_attach_image($image_url, $post_id, $mls_number, $image_number);
        
        if (!$attachment_id) {
            shift8_treb_log('Image download failed after retry', array(
                'mls_number' => esc_html($mls_number),
                'image_url' => esc_html($image_url)
            ));
        }
        
        return $attachment_id;
    }

    /**
     * Store external image reference for fallback/future processing
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @param string $image_url External image URL
     * @param string $mls_number MLS number
     * @param int $image_number Image number
     * @param bool $is_preferred Whether this is the preferred image
     * @return void
     */
    private function store_external_image_reference($post_id, $image_url, $mls_number, $image_number, $is_preferred = false) {
        // Store as post meta for future processing
        $external_images = get_post_meta($post_id, '_treb_external_images', true);
        if (!is_array($external_images)) {
            $external_images = array();
        }

        $external_images[] = array(
            'url' => esc_url_raw($image_url),
            'mls_number' => sanitize_text_field($mls_number),
            'image_number' => intval($image_number),
            'is_preferred' => (bool) $is_preferred,
            'failed_attempts' => 2, // Already tried twice
            'last_attempt' => current_time('mysql')
        );

        update_post_meta($post_id, '_treb_external_images', $external_images);

        // If this is the preferred image and no featured image is set, store the URL
        if ($is_preferred && !has_post_thumbnail($post_id)) {
            update_post_meta($post_id, '_treb_preferred_external_image', esc_url_raw($image_url));
        }
    }

    /**
     * Process images in batch mode for better performance
     * 
     * Uses parallel HTTP requests and optimized processing
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @param array $media_items Media items from API
     * @param string $mls_number MLS number
     * @param array $stats Statistics array
     * @return array Updated statistics
     */
    private function process_images_batch($post_id, $media_items, $mls_number, $stats) {
        shift8_treb_log('Starting batch image processing', array(
            'mls_number' => esc_html($mls_number),
            'image_count' => count($media_items)
        ));

        $stats['total'] = count($media_items);
        
        // Prepare batch download requests
        $batch_requests = array();
        $preferred_index = null;
        
        foreach ($media_items as $index => $media) {
            if (!isset($media['MediaURL']) || empty($media['MediaURL'])) {
                continue;
            }

            $image_url = esc_url_raw($media['MediaURL']);
            $is_preferred = isset($media['PreferredPhotoYN']) && $media['PreferredPhotoYN'] === true;
            
            if ($is_preferred) {
                $preferred_index = $index;
            }

            // Check if already exists and fix orphaned attachments
            $existing_attachment = $this->get_existing_attachment($mls_number, $index + 1);
            if ($existing_attachment) {
                // Check if attachment is properly linked to post
                $attachment_post = get_post($existing_attachment);
                if ($attachment_post && $attachment_post->post_parent != $post_id) {
                    // Fix orphaned attachment
                    wp_update_post(array(
                        'ID' => $existing_attachment,
                        'post_parent' => $post_id
                    ));
                    
                    shift8_treb_log('Fixed orphaned attachment', array(
                        'attachment_id' => $existing_attachment,
                        'mls_number' => esc_html($mls_number),
                        'image_number' => $index + 1,
                        'old_parent' => $attachment_post->post_parent,
                        'new_parent' => $post_id
                    ));
                }
                
                $stats['downloaded']++;
                continue;
            }

            $batch_requests[] = array(
                'url' => $image_url,
                'index' => $index,
                'image_number' => $index + 1,
                'is_preferred' => $is_preferred,
                'request' => array(
                    'url' => $image_url,
                    'type' => 'GET',
                    'timeout' => 8, // Shorter timeout for batch
                    'user-agent' => 'WordPress/TREB-Plugin-Batch',
                    'headers' => array(
                        'Accept' => 'image/*'
                    ),
                    'sslverify' => false
                )
            );
        }

        if (empty($batch_requests)) {
            shift8_treb_log('No images to download in batch', array(
                'mls_number' => esc_html($mls_number)
            ));
            
            // Even if no new downloads, we still need to set featured image from existing attachments
            $this->set_featured_image_from_existing_attachments($post_id, $mls_number, $stats);
            return $stats;
        }

        // Execute batch HTTP requests
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::line("  Downloading " . count($batch_requests) . " images...");
        }
        $batch_responses = $this->execute_batch_http_requests($batch_requests);
        
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::line("  Processing " . count($batch_responses) . " image responses...");
        }
        
        // Process responses
        $featured_image_id = null;
        $preferred_image_id = null;
        $first_successful_image = null;
        $first_image_id = null;

        foreach ($batch_responses as $response_data) {
            $index = $response_data['index'];
            $image_number = $response_data['image_number'];
            $is_preferred = $response_data['is_preferred'];
            $response = $response_data['response'];

            if (is_wp_error($response)) {
                $stats['failed']++;
                $this->store_external_image_reference($post_id, $response_data['url'], $mls_number, $image_number, $is_preferred);
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $stats['failed']++;
                $this->store_external_image_reference($post_id, $response_data['url'], $mls_number, $image_number, $is_preferred);
                continue;
            }

            // Process successful download
            $attachment_id = $this->process_batch_image_response($response, $post_id, $mls_number, $image_number);
            
            if ($attachment_id) {
                $stats['downloaded']++;
                
                if (defined('WP_CLI') && WP_CLI) {
                    WP_CLI::line("    ✅ Image {$image_number}: Attachment ID {$attachment_id}");
                }
                
                if (!$first_successful_image) {
                    $first_successful_image = $attachment_id;
                }
                
                if ($is_preferred) {
                    $preferred_image_id = $attachment_id;
                }
                
                // Track first image (image_number = 1) for featured image priority
                if ($image_number == 1) {
                    $first_image_id = $attachment_id;
                }
            } else {
                $stats['failed']++;
                if (defined('WP_CLI') && WP_CLI) {
                    WP_CLI::line("    ❌ Image {$image_number}: Failed to process");
                }
                $this->store_external_image_reference($post_id, $response_data['url'], $mls_number, $image_number, $is_preferred);
            }
        }
        
        // Set featured image with priority: First image > Preferred > First successful
        $featured_image_id = $first_image_id ?: ($preferred_image_id ?: $first_successful_image);
        
        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
            $stats['featured_set'] = true;
            
            $priority_type = $first_image_id ? 'first_image' : ($preferred_image_id ? 'preferred' : 'first_successful');
            
            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::line("    🖼️  Set featured image: ID {$featured_image_id} ({$priority_type})");
            }
            
            shift8_treb_log('Set featured image (batch)', array(
                'mls_number' => esc_html($mls_number),
                'attachment_id' => $featured_image_id,
                'priority_type' => $priority_type
            ));
        }

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::line("  Completed MLS {$mls_number}: {$stats['downloaded']} downloaded, {$stats['failed']} failed");
        }
        
        shift8_treb_log('Batch image processing completed', array(
            'mls_number' => esc_html($mls_number),
            'stats' => $stats
        ));

        return $stats;
    }

    /**
     * Set featured image from existing attachments when no new downloads are needed
     *
     * @since 1.2.0
     * @param int $post_id Post ID
     * @param string $mls_number MLS number
     * @param array $stats Statistics array (passed by reference)
     * @return void
     */
    private function set_featured_image_from_existing_attachments($post_id, $mls_number, &$stats) {
        // Get all attachments for this post ordered by image number
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_parent' => $post_id,
            'meta_key' => '_treb_image_number',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'posts_per_page' => -1
        ));

        if (empty($attachments)) {
            return;
        }

        // Find the first image (image_number = 1) for featured image priority
        $first_image_id = null;
        $preferred_image_id = null;
        $first_successful_image = null;

        foreach ($attachments as $attachment) {
            $image_number = get_post_meta($attachment->ID, '_treb_image_number', true);
            $is_preferred = get_post_meta($attachment->ID, '_treb_is_preferred', true);

            if (!$first_successful_image) {
                $first_successful_image = $attachment->ID;
            }

            if ($image_number == 1) {
                $first_image_id = $attachment->ID;
            }

            if ($is_preferred) {
                $preferred_image_id = $attachment->ID;
            }
        }

        // Set featured image with priority: First image > Preferred > First successful
        $featured_image_id = $first_image_id ?: ($preferred_image_id ?: $first_successful_image);

        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
            $stats['featured_set'] = true;

            $priority_type = $first_image_id ? 'first_image' : ($preferred_image_id ? 'preferred' : 'first_successful');

            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::line("    🖼️  Set featured image from existing: ID {$featured_image_id} ({$priority_type})");
            }

            shift8_treb_log('Set featured image from existing attachments', array(
                'mls_number' => esc_html($mls_number),
                'attachment_id' => $featured_image_id,
                'priority_type' => $priority_type
            ));
        }
    }

    /**
     * Execute batch HTTP requests using WordPress HTTP API
     * 
     * Performance: Uses wp_remote_request with concurrent processing where possible
     *
     * @since 1.0.0
     * @param array $batch_requests Array of request configurations
     * @return array Array of responses with metadata
     */
    private function execute_batch_http_requests($batch_requests) {
        $responses = array();
        
        // Cross-hosting compatibility: Adaptive batch sizing based on environment
        // Following .cursorrules performanceOptimizationPatterns for hosting environment compatibility
        $batch_size = $this->get_optimal_batch_size();
        $timeout = $this->get_optimal_timeout();
        $delay = $this->get_optimal_delay();
        
        $batches = array_chunk($batch_requests, $batch_size);
        
        shift8_treb_log('Starting batch image processing', array(
            'total_requests' => count($batch_requests),
            'batch_count' => count($batches),
            'batch_size' => $batch_size,
            'timeout' => $timeout,
            'delay_ms' => $delay * 1000
        ));
        
        foreach ($batches as $batch_index => $batch) {
            $batch_start = microtime(true);
            
            if (defined('WP_CLI') && WP_CLI && count($batches) > 1) {
                WP_CLI::line("    Batch " . ($batch_index + 1) . "/" . count($batches) . " (" . count($batch) . " images)");
            }
            
            // Process each request in the batch with optimized settings
            foreach ($batch as $request_data) {
                // Override timeout in request settings for cross-hosting compatibility
                $request_data['request']['timeout'] = $timeout;
                
                if (defined('WP_CLI') && WP_CLI) {
                    WP_CLI::line("      Downloading image " . $request_data['image_number'] . "...");
                }
                
                $response = wp_remote_get($request_data['url'], $request_data['request']);
                
                if (defined('WP_CLI') && WP_CLI) {
                    if (is_wp_error($response)) {
                        WP_CLI::line("      ❌ Image " . $request_data['image_number'] . ": " . $response->get_error_message());
                    } else {
                        $code = wp_remote_retrieve_response_code($response);
                        WP_CLI::line("      📥 Image " . $request_data['image_number'] . ": HTTP {$code}");
                    }
                }
                
                $responses[] = array(
                    'url' => $request_data['url'],
                    'index' => $request_data['index'],
                    'image_number' => $request_data['image_number'],
                    'is_preferred' => $request_data['is_preferred'],
                    'response' => $response
                );
            }
            
            $batch_time = microtime(true) - $batch_start;
            
            // Adaptive delay based on batch performance and hosting constraints
            if ($batch_index < count($batches) - 1) {
                $adaptive_delay = max($delay, $batch_time * 0.1); // At least 10% of batch time
                usleep($adaptive_delay * 1000000); // Convert to microseconds
            }
            
            shift8_treb_log('Batch completed', array(
                'batch' => $batch_index + 1,
                'batch_time' => round($batch_time, 2),
                'requests_in_batch' => count($batch)
            ));
        }
        
        return $responses;
    }

    /**
     * Get optimal batch size based on hosting environment
     *
     * @since 1.1.0
     * @return int Optimal batch size
     */
    private function get_optimal_batch_size() {
        // Check for memory constraints
        $memory_limit = $this->get_memory_limit_mb();
        
        if ($memory_limit < 64) {
            return 2; // Very constrained hosting
        } elseif ($memory_limit < 128) {
            return 3; // Standard shared hosting
        } elseif ($memory_limit < 256) {
            return 5; // Better hosting
        } else {
            return 8; // High-performance hosting
        }
    }

    /**
     * Get optimal timeout based on hosting environment
     *
     * @since 1.1.0
     * @return int Timeout in seconds
     */
    private function get_optimal_timeout() {
        // Check execution time limit
        $max_execution_time = ini_get('max_execution_time');
        
        if ($max_execution_time > 0 && $max_execution_time < 60) {
            return 5; // Very limited execution time
        } elseif ($max_execution_time > 0 && $max_execution_time < 120) {
            return 8; // Standard hosting
        } else {
            return 12; // Generous hosting or CLI
        }
    }

    /**
     * Get optimal delay between batches
     *
     * @since 1.1.0
     * @return float Delay in seconds
     */
    private function get_optimal_delay() {
        // Base delay, can be filtered for specific hosting needs
        return apply_filters('shift8_treb_batch_delay', 0.25);
    }

    /**
     * Get memory limit in MB
     *
     * @since 1.1.0
     * @return int Memory limit in MB
     */
    private function get_memory_limit_mb() {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit == -1) {
            return 512; // Unlimited, assume high-performance
        }
        
        $value = (int) $memory_limit;
        $unit = strtoupper(substr($memory_limit, -1));
        
        switch ($unit) {
            case 'G':
                return $value * 1024;
            case 'M':
                return $value;
            case 'K':
                return $value / 1024;
            default:
                return $value / (1024 * 1024); // Bytes to MB
        }
    }

    /**
     * Process a successful batch image response
     *
     * @since 1.0.0
     * @param array $response HTTP response
     * @param int $post_id Post ID
     * @param string $mls_number MLS number
     * @param int $image_number Image number
     * @return int|false Attachment ID or false
     */
    private function process_batch_image_response($response, $post_id, $mls_number, $image_number) {
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return false;
        }

        // Validate image size
        $image_size = strlen($image_data);
        if ($image_size < 1024) {
            return false;
        }

        // Get content type and extension
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $extension = $this->get_file_extension_from_content_type($content_type);
        
        // Prepare filename
        $filename = sanitize_file_name($mls_number . '_' . $image_number . '.' . $extension);
        
        // Upload to WordPress
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if ($upload['error']) {
            return false;
        }

        // Create attachment
        $attachment = array(
            'post_mime_type' => $content_type ?: 'image/jpeg',
            'post_title' => sanitize_text_field($mls_number . ' - Photo ' . $image_number),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_excerpt' => sanitize_text_field('Property photo for MLS ' . $mls_number)
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            if (file_exists($upload['file'])) {
                wp_delete_file($upload['file']);
            }
            shift8_treb_log('Batch attachment creation failed', array(
                'error' => esc_html($attachment_id->get_error_message()),
                'post_id' => $post_id,
                'mls_number' => esc_html($mls_number),
                'image_number' => $image_number
            ), 'error');
            return false;
        }

        // Verify post_parent was set correctly (debugging for post_parent issue)
        $created_attachment = get_post($attachment_id);
        if ($created_attachment && $created_attachment->post_parent != $post_id) {
            shift8_treb_log('Batch post parent mismatch detected', array(
                'attachment_id' => $attachment_id,
                'expected_parent' => $post_id,
                'actual_parent' => $created_attachment->post_parent,
                'mls_number' => esc_html($mls_number),
                'image_number' => $image_number
            ), 'warning');
            
            // Attempt to fix the post_parent
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_parent' => $post_id
            ));
        }

        // Generate metadata
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Add custom meta
        update_post_meta($attachment_id, '_treb_mls_number', sanitize_text_field($mls_number));
        update_post_meta($attachment_id, '_treb_image_number', intval($image_number));

        return $attachment_id;
    }


    /**
     * Get default listing template
     *
     * @since 1.0.0
     * @return string Default template
     */
    private function get_default_template() {
        return '<!-- TREB Listing Template - Modern WordPress Version -->
<div class="treb-listing-container">
    <!-- Hero Section with Featured Image -->
    <div class="treb-hero-section">
        %FEATURED_IMAGE%
        <div class="treb-price-overlay">
            <h2 class="treb-price">%PRICE%</h2>
            <p class="treb-mls">MLS# %MLS%</p>
        </div>
    </div>
    
    <!-- Property Header -->
    <div class="treb-property-header">
        <h1 class="treb-address">%ADDRESS%</h1>
        <div class="treb-quick-stats">
            <span class="treb-stat"><strong>%BEDROOMS%</strong> Bedrooms</span>
            <span class="treb-stat"><strong>%BATHROOMS%</strong> Bathrooms</span>
            <span class="treb-stat"><strong>%SQFT%</strong> sq ft</span>
        </div>
    </div>
    
    <!-- Image Gallery -->
    <div class="treb-gallery-section">
        %IMAGE_GALLERY%
    </div>
    
    <!-- Property Details -->
    <div class="treb-details-section">
        <div class="treb-details-grid">
            <div class="treb-detail-item">
                <strong>Property Type:</strong> %PROPERTY_TYPE%
            </div>
            <div class="treb-detail-item">
                <strong>City:</strong> %CITY%
            </div>
            <div class="treb-detail-item">
                <strong>Postal Code:</strong> %POSTAL_CODE%
            </div>
            %VIRTUAL_TOUR_SECTION%
        </div>
    </div>
    
    <!-- Description -->
    <div class="treb-description-section">
        <h3>Property Description</h3>
        <div class="treb-description-content">
            %DESCRIPTION%
        </div>
    </div>
    
    <!-- Contact Section -->
    <div class="treb-contact-section">
        <div class="treb-contact-info">
            <h3>Contact Information</h3>
        </div>
    </div>
</div>';
    }

    /**
     * Parse address into components
     *
     * @since 1.0.0
     * @param string $address Full address string
     * @return array Address components
     */
    private function parse_address($address) {
        $parts = array(
            'number' => '',
            'street' => '',
            'unit' => ''
        );

        if (empty($address)) {
            return $parts;
        }

        // Basic address parsing - can be enhanced later
        $address = trim($address);
        
        // Extract unit number (look for patterns like "Unit 123", "Apt 456", "#789")
        if (preg_match('/(?:Unit|Apt|Suite|#)\s*([A-Za-z0-9]+)/i', $address, $matches)) {
            $parts['unit'] = $matches[1];
            $address = preg_replace('/(?:Unit|Apt|Suite|#)\s*[A-Za-z0-9]+/i', '', $address);
        }

        // Extract street number (first number in the address)
        if (preg_match('/^(\d+)/', trim($address), $matches)) {
            $parts['number'] = $matches[1];
            $address = preg_replace('/^\d+\s*/', '', $address);
        }

        // Remaining is street name (clean up extra spaces and commas)
        $parts['street'] = trim(preg_replace('/,.*$/', '', $address));

        return $parts;
    }


    /**
     * Get virtual tour link for listing
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return string Virtual tour HTML or empty string
     */
    private function get_virtual_tour_link($listing) {
        if (isset($listing['VirtualTourURLUnbranded']) && !empty($listing['VirtualTourURLUnbranded'])) {
            $url = esc_url($listing['VirtualTourURLUnbranded']);
            return '<a href="' . $url . '" target="_blank" class="virtual-tour-link">Virtual Tour</a>';
        }
        return '';
    }

    /**
     * Get WalkScore HTML div for listing (similar to Google Maps approach)
     *
     * @since 1.6.2
     * @param array $listing Listing data
     * @return string WalkScore HTML div or empty string
     */
    private function get_walkscore_html($listing) {
        // Check if WalkScore is enabled - only requires walkscore_id
        if (empty($this->settings['walkscore_id'])) {
            return '';
        }

        // Return only the div element - JavaScript will be loaded separately
        return '<div id="ws-walkscore-tile" class="shift8-treb-walkscore">Loading WalkScore...</div>';
    }

    /**
     * Get WalkScore code for listing (legacy method for direct HTML)
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return string WalkScore embed code or empty string
     */
    private function get_walkscore_code($listing) {
        // Check if WalkScore is enabled - only requires walkscore_id
        if (empty($this->settings['walkscore_id'])) {
            return '';
        }

        // Parse address components
        $address_parts = $this->parse_address($listing['UnparsedAddress']);
        
        // Get address components
        $street_number = $address_parts['number'];
        $street_name = $address_parts['street'];
        $street_suffix = ''; // Not typically provided in AMPRE data
        $municipality = isset($listing['City']) ? sanitize_text_field($listing['City']) : '';
        $province = isset($listing['StateOrProvince']) ? sanitize_text_field($listing['StateOrProvince']) : 'ON';
        $country = 'Canada';
        
        // Return only the div - WalkScore scripts are enqueued properly in the main plugin file
        return '<div id="ws-walkscore-tile"></div>';
    }

    /**
     * Get listing latitude with geocoding fallback
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @param int $post_id Optional post ID to check stored coordinates
     * @return string Latitude coordinate
     */
    private function get_listing_latitude($listing, $post_id = null) {
        // First, try to use AMPRE API provided coordinates
        if (isset($listing['Latitude']) && !empty($listing['Latitude'])) {
            return floatval($listing['Latitude']);
        }
        
        // Second, check if we have stored coordinates from previous geocoding
        if ($post_id) {
            $stored_lat = get_post_meta($post_id, 'shift8_treb_latitude', true);
            if (!empty($stored_lat)) {
                return floatval($stored_lat);
            }
        }

        // Third, attempt geocoding with OpenStreetMap (no API key required)
        $coordinates = $this->geocode_address($listing['UnparsedAddress']);
        if ($coordinates && isset($coordinates['lat'])) {
            return $coordinates['lat'];
        }

        // Default to Toronto coordinates if no API key or geocoding fails
        return '43.6532';
    }

    /**
     * Get listing longitude with geocoding fallback
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @param int $post_id Optional post ID to check stored coordinates
     * @return string Longitude coordinate
     */
    private function get_listing_longitude($listing, $post_id = null) {
        // First, try to use AMPRE API provided coordinates
        if (isset($listing['Longitude']) && !empty($listing['Longitude'])) {
            return floatval($listing['Longitude']);
        }
        
        // Second, check if we have stored coordinates from previous geocoding
        if ($post_id) {
            $stored_lng = get_post_meta($post_id, 'shift8_treb_longitude', true);
            if (!empty($stored_lng)) {
                return floatval($stored_lng);
            }
        }

        // Third, attempt geocoding with OpenStreetMap (no API key required)
        $coordinates = $this->geocode_address($listing['UnparsedAddress']);
        if ($coordinates && isset($coordinates['lng'])) {
            return $coordinates['lng'];
        }

        // Default to Toronto coordinates if no API key or geocoding fails
        return '-79.3832';
    }

    /**
     * Geocode address using OpenStreetMap Nominatim API with multiple fallback attempts
     *
     * @since 1.2.0
     * @param string $address Address to geocode
     * @return array|false Array with 'lat' and 'lng' keys, or false on failure
     */
    private function geocode_address($address) {
        // Check cache first (to avoid repeated API calls for same address)
        $cache_key = 'shift8_treb_geocode_' . md5($address);
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            shift8_treb_log('Using cached geocoding result', array(
                'address' => esc_html($address),
                'coordinates' => $cached_result
            ));
            return $cached_result;
        }

        // Use multi-service geocoding for 99%+ success rate
        $geocoding_result = $this->clean_address_for_geocoding($address);
        
        shift8_treb_log('Multi-service geocoding completed', array(
            'original_address' => esc_html($address),
            'success' => $geocoding_result['success'],
            'service_used' => esc_html($geocoding_result['service']),
            'address_used' => esc_html($geocoding_result['address_used'])
        ));

        // Extract coordinates
        $coordinates = array('lat' => $geocoding_result['lat'], 'lng' => $geocoding_result['lng']);
        
        if ($geocoding_result['success']) {
            // Cache successful result for 7 days
            set_transient($cache_key, $coordinates, 7 * 24 * 3600);
        } else {
            // Cache failures for 1 hour only
            set_transient($cache_key, false, 1 * 3600);
        }
        
        return $coordinates;
    }

    /**
     * Attempt geocoding for a single address variation
     *
     * @since 1.4.0
     * @param string $clean_address Cleaned address to geocode
     * @param string $original_address Original address for logging
     * @param int $attempt_number Current attempt number
     * @param int $total_attempts Total number of attempts
     * @return array|false Coordinates or false on failure
     */
    private function attempt_geocoding($clean_address, $original_address, $attempt_number, $total_attempts) {
        $encoded_address = urlencode($clean_address);
        
        // Use OpenStreetMap Nominatim API (free, no API key required)
        $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ca&q={$encoded_address}";

        // Respect OpenStreetMap's 1 request per second rate limit
        // Store last request time to ensure we don't exceed rate limits
        $last_request_time = get_transient('shift8_treb_osm_last_request');
        if ($last_request_time !== false) {
            $time_since_last = time() - $last_request_time;
            if ($time_since_last < 1) {
                $sleep_time = 1 - $time_since_last;
                shift8_treb_log('OpenStreetMap rate limiting - sleeping', array(
                    'sleep_seconds' => $sleep_time,
                    'attempt' => "{$attempt_number}/{$total_attempts}",
                    'address' => esc_html($clean_address)
                ));
                sleep($sleep_time);
            }
        }
        
        // Record this request time (expires after 2 minutes to handle edge cases)
        set_transient('shift8_treb_osm_last_request', time(), 2 * 60);

        shift8_treb_log('Making OpenStreetMap geocoding request', array(
            'attempt' => "{$attempt_number}/{$total_attempts}",
            'original_address' => esc_html($original_address),
            'clean_address' => esc_html($clean_address),
            'url' => esc_html($url)
        ));

        $response = wp_remote_get($url, array(
            'timeout' => 30, // Increased timeout for better reliability
            'sslverify' => true, // Ensure SSL verification
            'headers' => array(
                'User-Agent' => 'WordPress/TREB-Plugin/1.6.2 (https://shift8web.ca; Real Estate Listings)',
                'Accept' => 'application/json',
                'Accept-Language' => 'en-CA,en;q=0.9'
            )
        ));

        if (is_wp_error($response)) {
            shift8_treb_log('OpenStreetMap geocoding error', array(
                'attempt' => "{$attempt_number}/{$total_attempts}",
                'original_address' => esc_html($original_address),
                'clean_address' => esc_html($clean_address),
                'error' => $response->get_error_message()
            ));
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        // Enhanced HTTP status code handling
        if ($response_code === 429) {
            shift8_treb_log('OpenStreetMap rate limit exceeded (429)', array(
                'attempt' => "{$attempt_number}/{$total_attempts}",
                'clean_address' => esc_html($clean_address),
                'message' => 'Sleeping extra 2 seconds before next attempt'
            ));
            // Sleep extra time if we hit rate limit
            sleep(2);
            return false;
        }
        
        if ($response_code !== 200) {
            shift8_treb_log('OpenStreetMap geocoding HTTP error', array(
                'attempt' => "{$attempt_number}/{$total_attempts}",
                'original_address' => esc_html($original_address),
                'clean_address' => esc_html($clean_address),
                'response_code' => $response_code,
                'response_body' => wp_remote_retrieve_body($response)
            ));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            shift8_treb_log('OpenStreetMap geocoding JSON error', array(
                'attempt' => "{$attempt_number}/{$total_attempts}",
                'original_address' => esc_html($original_address),
                'clean_address' => esc_html($clean_address),
                'json_error' => json_last_error_msg()
            ));
            return false;
        }

        if (empty($data) || !isset($data[0]['lat']) || !isset($data[0]['lon'])) {
            shift8_treb_log('OpenStreetMap geocoding no results', array(
                'attempt' => "{$attempt_number}/{$total_attempts}",
                'original_address' => esc_html($original_address),
                'clean_address' => esc_html($clean_address),
                'response_count' => is_array($data) ? count($data) : 0
            ));
            return false;
        }

        // Extract coordinates (note: OpenStreetMap uses 'lon', we convert to 'lng')
        $coordinates = array(
            'lat' => floatval($data[0]['lat']),
            'lng' => floatval($data[0]['lon'])
        );

        shift8_treb_log('OpenStreetMap geocoding successful', array(
            'attempt' => "{$attempt_number}/{$total_attempts}",
            'original_address' => esc_html($original_address),
            'clean_address' => esc_html($clean_address),
            'coordinates' => $coordinates,
            'osm_display_name' => isset($data[0]['display_name']) ? esc_html($data[0]['display_name']) : 'N/A'
        ));

        return $coordinates;
    }

    /**
     * Geocode address using multi-service approach for 99%+ success rate
     *
     * @since 1.7.0 (Updated to use MultiGeocodingService)
     * @param string $address Raw address from AMPRE API
     * @return array Geocoding result with lat, lng, success, service, address_used
     */
    private function clean_address_for_geocoding($address) {
        // In test environment, return mock coordinates to prevent HTTP requests
        if (defined('SHIFT8_TREB_TESTING') && SHIFT8_TREB_TESTING) {
            return [
                'lat' => 43.6532,
                'lng' => -79.3832,
                'success' => true,
                'service' => 'test_mock',
                'address_used' => $address
            ];
        }
        
        // Use the new MultiGeocodingService for 99%+ success rate
        require_once(dirname(__FILE__) . '/Services/MultiGeocodingService.php');
        
        $geocoder = new \Shift8\TREB\Services\MultiGeocodingService();
        $result = $geocoder->geocode($address);
        
        if ($result['success']) {
            return [
                'lat' => $result['lat'],
                'lng' => $result['lng'],
                'success' => true,
                'service' => $result['service_used'],
                'address_used' => $result['address_used']
            ];
        }
        
        // Fallback to default coordinates
        return [
            'lat' => 43.6532, // Toronto center
            'lng' => -79.3832,
            'success' => false,
            'service' => 'fallback',
            'address_used' => 'Default Toronto coordinates'
        ];
    }

    /**
     * Ensure address has Canada suffix
     *
     * @since 1.4.0
     * @param string $address Address to check
     * @return string Address with Canada suffix
     */
    private function ensure_canada_suffix($address) {
        if (!preg_match('/,\s*(Canada|CA)\s*$/i', $address)) {
            $address .= ', Canada';
        }
        return $address;
    }

    /**
     * Get listing images as base64 encoded string for gallery
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return string Base64 encoded images or empty string
     */
    /**
     * Get featured image HTML for template
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @return string Featured image HTML or empty string
     */
    private function get_featured_image_html($post_id) {
        if (!has_post_thumbnail($post_id)) {
            return '<div class="treb-no-image">No image available</div>';
        }

        // Get post object to ensure proper context
        $post = get_post($post_id);
        if (!$post) {
            return '<div class="treb-no-image">Post not found</div>';
        }

        // Get featured image with responsive sizes
        $featured_image = get_the_post_thumbnail($post_id, 'large', array(
            'class' => 'treb-featured-image',
            'loading' => 'lazy',
            'alt' => esc_attr($post->post_title) . ' - Property Photo'
        ));

        return $featured_image;
    }

    /**
     * Get image gallery HTML for template
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @return string Gallery HTML or empty string
     */
    private function get_image_gallery_html($post_id) {
        // Verify post exists
        $post = get_post($post_id);
        if (!$post) {
            return '<p class="treb-no-gallery">Post not found</p>';
        }

        // Get all attachments for this post
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_parent' => $post_id,
            'meta_key' => '_treb_image_number',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'posts_per_page' => -1
        ));

        if (empty($attachments)) {
            return '<p class="treb-no-gallery">No additional images available</p>';
        }

        // Skip the first image if it's the featured image
        $featured_image_id = get_post_thumbnail_id($post_id);
        $gallery_images = array();
        
        foreach ($attachments as $attachment) {
            if ($attachment->ID != $featured_image_id) {
                $gallery_images[] = $attachment->ID;
            }
        }

        if (empty($gallery_images)) {
            return '<p class="treb-single-image">Additional images will appear here when available</p>';
        }

        // Create WordPress gallery shortcode
        $gallery_ids = implode(',', $gallery_images);
        $gallery_shortcode = '[gallery ids="' . esc_attr($gallery_ids) . '" columns="3" size="medium" link="file"]';
        
        // Process the shortcode to generate HTML
        $gallery_html = do_shortcode($gallery_shortcode);
        
        return '<div class="treb-image-gallery">' . $gallery_html . '</div>';
    }

    /**
     * Get listing image URLs in order
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @return array Array of image URLs
     */
    private function get_listing_image_urls($post_id) {
        // Verify post exists
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        // Get all attachments for this post ordered by image number
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_parent' => $post_id,
            'meta_key' => '_treb_image_number',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'posts_per_page' => -1
        ));

        $image_urls = array();
        foreach ($attachments as $attachment) {
            $image_url = wp_get_attachment_url($attachment->ID);
            if ($image_url) {
                $image_urls[] = $image_url;
            }
        }

        return $image_urls;
    }

    /**
     * Get base64 encoded image URLs for Visual Composer compatibility
     *
     * @since 1.0.0
     * @param array $image_urls Array of image URLs
     * @return string Base64 encoded URL-encoded comma-separated image URLs
     */
    private function get_base64_encoded_images($image_urls) {
        if (empty($image_urls)) {
            return '';
        }

        // URL encode each image URL
        $encoded_urls = array_map('urlencode', $image_urls);
        
        // Join with commas
        $comma_separated = implode(',', $encoded_urls);
        
        // Base64 encode the entire string
        $base64_encoded = base64_encode($comma_separated);
        
        return $base64_encoded;
    }

    /**
     * Get Google Map HTML for listing
     *
     * @since 1.2.0
     * @param array $listing Listing data
     * @param int $post_id Optional post ID to check stored coordinates
     * @return string Google Map HTML div or empty string
     */
    private function get_google_map_html($listing, $post_id = null) {
        // Only show map if we have Google Maps API key and coordinates
        if (!shift8_treb_has_google_maps_api_key() || !shift8_treb_has_listing_coordinates($listing, $post_id)) {
            return '';
        }
        
        return '<div class="shift8-treb-googlemap" id="shift8-treb-map" style="height: 400px; width: 100%;">Loading map...</div>';
    }

    /**
     * Get virtual tour section HTML
     *
     * @since 1.0.0
     * @param array $listing Listing data
     * @return string Virtual tour section HTML or empty string
     */
    private function get_virtual_tour_section($listing) {
        $virtual_tour_url = $this->get_virtual_tour_link($listing);
        
        if (empty($virtual_tour_url)) {
            return '';
        }

        return '<div class="treb-detail-item treb-virtual-tour">
            <strong>Virtual Tour:</strong>
            <a href="' . esc_url($virtual_tour_url) . '" target="_blank" class="treb-virtual-tour-link">
                View Virtual Tour
            </a>
        </div>';
    }

}