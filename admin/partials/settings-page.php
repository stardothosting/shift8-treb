<?php
/**
 * Admin settings page template
 *
 * @package Shift8_TREB
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings with defaults
$settings = wp_parse_args(get_option('shift8_treb_settings', array()), array(
    'bearer_token' => '',
    'sync_frequency' => 'daily',
    'max_listings_per_query' => 100,
    'debug_enabled' => '0',
    'google_maps_api_key' => '',
    'walkscore_api_key' => '',
    'walkscore_id' => '',
    'listing_status_filter' => 'Active',
    'city_filter' => 'Toronto',
    'property_type_filter' => '',
    'min_price' => '0',
    'max_price' => '999999999',
    'listing_template' => 'Property Details:\n\nAddress: %ADDRESS%\nPrice: %PRICE%\nMLS: %MLS%\nBedrooms: %BEDROOMS%\nBathrooms: %BATHROOMS%\nSquare Feet: %SQFT%\n\nDescription:\n%DESCRIPTION%',
    'post_excerpt_template' => '%ADDRESS%\n%LISTPRICE%\nMLS : %MLSNUMBER%'
));

// Get sync status
$sync_status = array(
    'next_sync' => wp_next_scheduled('shift8_treb_sync_listings') ? 
        wp_date('Y-m-d H:i:s', wp_next_scheduled('shift8_treb_sync_listings')) : 
        esc_html__('Not scheduled', 'shift8-real-estate-listings-for-treb'),
    'last_sync' => get_option('shift8_treb_last_sync', esc_html__('Never', 'shift8-real-estate-listings-for-treb')),
    'is_scheduled' => wp_next_scheduled('shift8_treb_sync_listings') !== false
);
?>

<div class="wrap">
    <h1><?php esc_html_e('Shift8 TREB Real Estate Listings', 'shift8-real-estate-listings-for-treb'); ?></h1>
    
    <?php settings_errors('shift8_treb_settings'); ?>
    
    <div class="shift8-treb-admin-container">
        <div class="shift8-treb-main-content">
            <form method="post" action="options.php">
                <?php 
                settings_fields('shift8_treb_settings');
                do_settings_sections('shift8_treb_settings');
                ?>
                
                <!-- API Configuration Section -->
                <div class="card">
                    <h2 class="title"><?php esc_html_e('AMPRE API Configuration', 'shift8-real-estate-listings-for-treb'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="bearer_token"><?php esc_html_e('Bearer Token', 'shift8-real-estate-listings-for-treb'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
<?php if (!empty($settings['bearer_token'])): ?>
                                <input type="password" 
                                       id="bearer_token" 
                                       name="shift8_treb_settings[bearer_token]" 
                                       value="" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Token is set - enter new token to change', 'shift8-real-estate-listings-for-treb'); ?>" />
                                <p class="description" style="color: green; font-weight: bold;">
                                    ✓ <?php esc_html_e('Bearer token is currently set and encrypted', 'shift8-real-estate-listings-for-treb'); ?>
                                </p>
                                <?php else: ?>
                                <input type="password" 
                                       id="bearer_token" 
                                       name="shift8_treb_settings[bearer_token]" 
                                       value="" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Enter your AMPRE API bearer token', 'shift8-real-estate-listings-for-treb'); ?>" />
                                <?php endif; ?>
                                <p class="description">
                                    <?php esc_html_e('Your AMPRE API bearer token for authentication.', 'shift8-real-estate-listings-for-treb'); ?>
                                    <button type="button" id="test-api-connection" class="button button-secondary" style="margin-left: 10px;">
                                        <?php esc_html_e('Test Connection', 'shift8-real-estate-listings-for-treb'); ?>
                                    </button>
                                </p>
                                <div id="api-test-result" style="margin-top: 10px;"></div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Sync Configuration Section -->
                <div class="card">
                    <h2 class="title"><?php esc_html_e('Sync Configuration', 'shift8-real-estate-listings-for-treb'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="sync_frequency"><?php esc_html_e('Sync Frequency', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <select id="sync_frequency" name="shift8_treb_settings[sync_frequency]">
                                    <option value="hourly" <?php selected($settings['sync_frequency'], 'hourly'); ?>><?php esc_html_e('Hourly', 'shift8-real-estate-listings-for-treb'); ?></option>
                                    <option value="shift8_treb_8hours" <?php selected($settings['sync_frequency'], 'shift8_treb_8hours'); ?>><?php esc_html_e('Every 8 Hours', 'shift8-real-estate-listings-for-treb'); ?></option>
                                    <option value="shift8_treb_12hours" <?php selected($settings['sync_frequency'], 'shift8_treb_12hours'); ?>><?php esc_html_e('Every 12 Hours', 'shift8-real-estate-listings-for-treb'); ?></option>
                                    <option value="daily" <?php selected($settings['sync_frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'shift8-real-estate-listings-for-treb'); ?></option>
                                    <option value="weekly" <?php selected($settings['sync_frequency'], 'weekly'); ?>><?php esc_html_e('Weekly', 'shift8-real-estate-listings-for-treb'); ?></option>
                                    <option value="shift8_treb_biweekly" <?php selected($settings['sync_frequency'], 'shift8_treb_biweekly'); ?>><?php esc_html_e('Bi-weekly', 'shift8-real-estate-listings-for-treb'); ?></option>
                                    <option value="shift8_treb_monthly" <?php selected($settings['sync_frequency'], 'shift8_treb_monthly'); ?>><?php esc_html_e('Monthly', 'shift8-real-estate-listings-for-treb'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('How often to sync listings from AMPRE API.', 'shift8-real-estate-listings-for-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="max_listings_per_query"><?php esc_html_e('Max Listings Per Query', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="max_listings_per_query" 
                                       name="shift8_treb_settings[max_listings_per_query]" 
                                       value="<?php echo esc_attr($settings['max_listings_per_query']); ?>" 
                                       min="1" 
                                       max="1000" 
                                       class="small-text" />
                                <p class="description"><?php esc_html_e('Maximum number of listings to fetch per API call (1-1000).', 'shift8-real-estate-listings-for-treb'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Agent Configuration Section -->
                <div class="card">
                    <h2 class="title"><?php esc_html_e('Agent Configuration', 'shift8-real-estate-listings-for-treb'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="member_id"><?php esc_html_e('Member ID', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="member_id" 
                                       name="shift8_treb_settings[member_id]" 
                                       value="<?php echo esc_attr($settings['member_id'] ?? ''); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('e.g., 2229166,9580044', 'shift8-real-estate-listings-for-treb'); ?>" />
                                <p class="description"><?php esc_html_e('Your Member ID(s) (ListAgentKey) to identify your own listings. Use comma-separated values for multiple agents (e.g., 2229166,9580044). Listings matching any of these IDs will be categorized as "Listings", others as "OtherListings".', 'shift8-real-estate-listings-for-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="excluded_member_ids"><?php esc_html_e('Member IDs to Exclude', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="excluded_member_ids" 
                                       name="shift8_treb_settings[excluded_member_ids]" 
                                       value="<?php echo esc_attr($settings['excluded_member_ids'] ?? ''); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('e.g., 1234567,8901234', 'shift8-real-estate-listings-for-treb'); ?>" />
                                <p class="description"><?php esc_html_e('Member IDs to completely exclude from sync. Listings from these agents will be skipped entirely. Use comma-separated values for multiple agents (e.g., 1234567,8901234).', 'shift8-real-estate-listings-for-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="listing_age_days"><?php esc_html_e('Listing Age (days)', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="listing_age_days" 
                                       name="shift8_treb_settings[listing_age_days]" 
                                       value="<?php echo esc_attr($settings['listing_age_days'] ?? '30'); ?>" 
                                       min="1" 
                                       max="365" 
                                       class="small-text" />
                                <p class="description"><?php esc_html_e('Only sync listings modified within the last X days. This filters by ModificationTimestamp.', 'shift8-real-estate-listings-for-treb'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Additional Settings Section -->
                <div class="card">
                    <h2 class="title"><?php esc_html_e('Additional Settings', 'shift8-real-estate-listings-for-treb'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="google_maps_api_key"><?php esc_html_e('Google Maps API Key', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="google_maps_api_key" 
                                       name="shift8_treb_settings[google_maps_api_key]" 
                                       value="<?php echo esc_attr($settings['google_maps_api_key']); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Optional: For geocoding and mapping', 'shift8-real-estate-listings-for-treb'); ?>" />
                                <p class="description"><?php esc_html_e('Google Maps API key for geocoding addresses to lat/lng coordinates and mapping features.', 'shift8-real-estate-listings-for-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="walkscore_id"><?php esc_html_e('WalkScore ID', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="walkscore_id" 
                                       name="shift8_treb_settings[walkscore_id]" 
                                       value="<?php echo esc_attr($settings['walkscore_id']); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Optional: Leave empty to disable WalkScore', 'shift8-real-estate-listings-for-treb'); ?>" />
                                <p class="description"><?php esc_html_e('WalkScore ID for embedding walkability widgets in listings. No API key required - just enter your WalkScore ID to enable.', 'shift8-real-estate-listings-for-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="debug_enabled"><?php esc_html_e('Debug Mode', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="debug_enabled" 
                                           name="shift8_treb_settings[debug_enabled]" 
                                           value="1" 
                                           <?php checked($settings['debug_enabled'], '1'); ?> />
                                    <?php esc_html_e('Enable debug logging', 'shift8-real-estate-listings-for-treb'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Enable detailed logging for troubleshooting.', 'shift8-real-estate-listings-for-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="listing_template"><?php esc_html_e('Listing Template', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <textarea id="listing_template" 
                                          name="shift8_treb_settings[listing_template]" 
                                          rows="10" 
                                          cols="50" 
                                          class="large-text"><?php echo esc_textarea($settings['listing_template']); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="post_excerpt_template"><?php esc_html_e('Post Excerpt Template', 'shift8-real-estate-listings-for-treb'); ?></label>
                            </th>
                            <td>
                                <textarea id="post_excerpt_template" 
                                          name="shift8_treb_settings[post_excerpt_template]" 
                                          rows="4" 
                                          cols="50" 
                                          class="large-text"><?php echo esc_textarea($settings['post_excerpt_template']); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Template for the WordPress post excerpt. Uses the same placeholders as the listing template above. This will be displayed in post previews, search results, and theme excerpt areas.', 'shift8-real-estate-listings-for-treb'); ?>
                                </p>
                                <p class="description">
                                    <strong><?php esc_html_e('Example:', 'shift8-real-estate-listings-for-treb'); ?></strong> 
                                    <code>%ADDRESS%\n%LISTPRICE%\nMLS : %MLSNUMBER%</code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="description">
                                    <p><strong><?php esc_html_e('Available Template Placeholders:', 'shift8-real-estate-listings-for-treb'); ?></strong></p>
                                    <div class="shift8-treb-placeholders">
                                        <div class="placeholder-section">
                                            <h4><?php esc_html_e('Property Information', 'shift8-real-estate-listings-for-treb'); ?></h4>
                                            <ul>
                                                <li><code>%ADDRESS%</code> - <?php esc_html_e('Full property address', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%STREETNUMBER%</code> - <?php esc_html_e('Street number only', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%STREETNAME%</code> - <?php esc_html_e('Street name only', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%APT_NUM%</code> - <?php esc_html_e('Apartment/unit number', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%PRICE%</code> - <?php esc_html_e('Formatted listing price', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%LISTPRICE%</code> - <?php esc_html_e('Formatted listing price (alias)', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%MLS%</code> - <?php esc_html_e('MLS number', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%MLSNUMBER%</code> - <?php esc_html_e('MLS number (alias)', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%BEDROOMS%</code> - <?php esc_html_e('Number of bedrooms', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%BATHROOMS%</code> - <?php esc_html_e('Number of bathrooms', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%SQFT%</code> - <?php esc_html_e('Square footage', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%SQFOOTAGE%</code> - <?php esc_html_e('Square footage (alias)', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%DESCRIPTION%</code> - <?php esc_html_e('Property description', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%PROPERTY_TYPE%</code> - <?php esc_html_e('Property type', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%CITY%</code> - <?php esc_html_e('City name', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%POSTAL_CODE%</code> - <?php esc_html_e('Postal code', 'shift8-real-estate-listings-for-treb'); ?></li>
                                            </ul>
                                        </div>
                                        
                                        <div class="placeholder-section">
                                            <h4><?php esc_html_e('Universal Image Placeholders', 'shift8-real-estate-listings-for-treb'); ?></h4>
                                            <ul>
                                                <li><code>%LISTING_IMAGES%</code> - <?php esc_html_e('Comma-separated list of all image URLs', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%BASE64IMAGES%</code> - <?php esc_html_e('Base64 encoded image URLs (Visual Composer format)', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%LISTING_IMAGE_1%</code> - <?php esc_html_e('First image URL', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%LISTING_IMAGE_2%</code> - <?php esc_html_e('Second image URL', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%LISTING_IMAGE_3%</code> - <?php esc_html_e('Third image URL', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><em><?php esc_html_e('... up to %LISTING_IMAGE_10%', 'shift8-real-estate-listings-for-treb'); ?></em></li>
                                            </ul>
                                        </div>
                                        
                                        <div class="placeholder-section">
                                            <h4><?php esc_html_e('WordPress Native Placeholders', 'shift8-real-estate-listings-for-treb'); ?></h4>
                                            <ul>
                                                <li><code>%FEATURED_IMAGE%</code> - <?php esc_html_e('WordPress featured image HTML', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%IMAGE_GALLERY%</code> - <?php esc_html_e('WordPress gallery shortcode HTML', 'shift8-real-estate-listings-for-treb'); ?></li>
                                            </ul>
                                        </div>
                                        
                                        <div class="placeholder-section">
                                            <h4><?php esc_html_e('Additional Features', 'shift8-real-estate-listings-for-treb'); ?></h4>
                                            <ul>
                                                <li><code>%VIRTUALTOUR%</code> - <?php esc_html_e('Virtual tour link', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%VIRTUAL_TOUR_SECTION%</code> - <?php esc_html_e('Formatted virtual tour section', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%WALKSCORECODE%</code> - <?php esc_html_e('WalkScore integration code', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%GOOGLEMAPCODE%</code> - <?php esc_html_e('Google Maps widget (requires API key)', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%WPBLOG%</code> - <?php esc_html_e('WordPress site URL', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%MAPLAT%</code> - <?php esc_html_e('Property latitude', 'shift8-real-estate-listings-for-treb'); ?></li>
                                                <li><code>%MAPLNG%</code> - <?php esc_html_e('Property longitude', 'shift8-real-estate-listings-for-treb'); ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <style>
                                .shift8-treb-placeholders {
                                    margin-top: 15px;
                                    display: grid;
                                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                                    gap: 20px;
                                }
                                
                                .placeholder-section {
                                    background: #f9f9f9;
                                    padding: 15px;
                                    border-radius: 5px;
                                    border-left: 4px solid #0073aa;
                                }
                                
                                .placeholder-section h4 {
                                    margin: 0 0 10px 0;
                                    color: #0073aa;
                                    font-size: 14px;
                                }
                                
                                .placeholder-section ul {
                                    margin: 0;
                                    padding-left: 20px;
                                }
                                
                                .placeholder-section li {
                                    margin-bottom: 5px;
                                    font-size: 13px;
                                    line-height: 1.4;
                                }
                                
                                .placeholder-section code {
                                    background: #fff;
                                    padding: 2px 6px;
                                    border-radius: 3px;
                                    font-weight: bold;
                                    color: #d63384;
                                    border: 1px solid #ddd;
                                }
                                
                                @media (max-width: 768px) {
                                    .shift8-treb-placeholders {
                                        grid-template-columns: 1fr;
                                    }
                                }
                                </style>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <!-- Sidebar -->
        <div class="shift8-treb-sidebar">
            <!-- Sync Status -->
            <div class="card">
                <h3><?php esc_html_e('Sync Status', 'shift8-real-estate-listings-for-treb'); ?></h3>
                <?php
                // Check incremental sync status
                $last_sync_timestamp = get_option('shift8_treb_last_sync', '');
                $is_incremental = !empty($last_sync_timestamp);
                ?>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Next Sync:', 'shift8-real-estate-listings-for-treb'); ?></strong></td>
                            <td><?php echo esc_html($sync_status['next_sync']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Last Sync:', 'shift8-real-estate-listings-for-treb'); ?></strong></td>
                            <td><?php echo esc_html($sync_status['last_sync']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Status:', 'shift8-real-estate-listings-for-treb'); ?></strong></td>
                            <td>
                                <span class="<?php echo $sync_status['is_scheduled'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $sync_status['is_scheduled'] ? esc_html__('Scheduled', 'shift8-real-estate-listings-for-treb') : esc_html__('Not Scheduled', 'shift8-real-estate-listings-for-treb'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Sync Mode:', 'shift8-real-estate-listings-for-treb'); ?></strong></td>
                            <td>
                                <?php if ($is_incremental): ?>
                                    <span style="color: #0073aa;">📊 <?php esc_html_e('Incremental', 'shift8-real-estate-listings-for-treb'); ?></span>
                                    <small style="display: block; color: #666; margin-top: 2px;">
                                        <?php esc_html_e('Only fetches listings modified since last sync (efficient)', 'shift8-real-estate-listings-for-treb'); ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color: #46b450;">📅 <?php esc_html_e('Age-Based', 'shift8-real-estate-listings-for-treb'); ?></span>
                                    <small style="display: block; color: #666; margin-top: 2px;">
                                        <?php 
                                        /* translators: %d is the number of days for listing age filter */
                                        printf(esc_html__('Fetches all listings from last %d days', 'shift8-real-estate-listings-for-treb'), intval($settings['listing_age_days'] ?? 90)); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="sync-actions">
                    <button type="button" id="manual-sync" class="button button-primary">
                        <?php esc_html_e('Run Manual Sync', 'shift8-real-estate-listings-for-treb'); ?>
                    </button>
                    
                    <?php if ($is_incremental): ?>
                        <button type="button" id="reset-sync" class="button button-secondary" 
                                title="<?php esc_attr_e('Reset incremental sync to force re-import of deleted posts. Next sync will use age-based filtering instead of incremental sync.', 'shift8-real-estate-listings-for-treb'); ?>">
                            <?php esc_html_e('Reset Sync Mode', 'shift8-real-estate-listings-for-treb'); ?>
                        </button>
                        <div style="margin-top: 8px; padding: 8px; background: #fff3cd; border-left: 4px solid #ffc107; font-size: 12px;">
                            <strong>⚠️ <?php esc_html_e('Note:', 'shift8-real-estate-listings-for-treb'); ?></strong> 
                            <?php esc_html_e('Incremental mode will not re-import deleted posts. Use "Reset Sync Mode" if you have deleted posts and want to re-import them.', 'shift8-real-estate-listings-for-treb'); ?>
                        </div>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <h3><?php esc_html_e('Quick Stats', 'shift8-real-estate-listings-for-treb'); ?></h3>
                <?php
                // Count posts in both 'Listings' and 'OtherListings' categories
                $listings_category = get_category_by_slug('listings');
                $other_listings_category = get_category_by_slug('otherlistings');
                
                $listing_count = 0;
                if ($listings_category) {
                    $listing_count += $listings_category->count;
                }
                if ($other_listings_category) {
                    $listing_count += $other_listings_category->count;
                }
                ?>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Total Listings:', 'shift8-real-estate-listings-for-treb'); ?></strong></td>
                            <td><?php echo esc_html(number_format($listing_count)); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Post Type:', 'shift8-real-estate-listings-for-treb'); ?></strong></td>
                            <td><code>post</code></td>
                        </tr>
                    </tbody>
                </table>
                
                <p>
                    <?php
                    // Build URL to show both Listings and OtherListings categories
                    $listings_cat = get_category_by_slug('listings');
                    $other_listings_cat = get_category_by_slug('otherlistings');
                    
                    $category_ids = array();
                    if ($listings_cat) {
                        $category_ids[] = $listings_cat->term_id;
                    }
                    if ($other_listings_cat) {
                        $category_ids[] = $other_listings_cat->term_id;
                    }
                    
                    $view_url = admin_url('edit.php');
                    if (!empty($category_ids)) {
                        $view_url = add_query_arg('cat', implode(',', $category_ids), $view_url);
                    }
                    ?>
                    <a href="<?php echo esc_url($view_url); ?>" class="button button-secondary">
                        <?php esc_html_e('View All Listings', 'shift8-real-estate-listings-for-treb'); ?>
                    </a>
                </p>
            </div>

            <!-- Debug Logs -->
            <?php if ($settings['debug_enabled'] === '1'): ?>
            <div class="card">
                <h3><?php esc_html_e('Debug Logs', 'shift8-real-estate-listings-for-treb'); ?></h3>
                <p>
                    <button type="button" id="view-logs" class="button button-secondary">
                        <?php esc_html_e('View Recent Logs', 'shift8-real-estate-listings-for-treb'); ?>
                    </button>
                    <button type="button" id="clear-logs" class="button button-secondary">
                        <?php esc_html_e('Clear Logs', 'shift8-real-estate-listings-for-treb'); ?>
                    </button>
                </p>
                <div id="log-viewer" style="display: none;">
                    <textarea id="log-content" rows="15" style="width: 100%; font-family: monospace; font-size: 11px;" readonly></textarea>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documentation -->
            <div class="card">
                <h3><?php esc_html_e('Documentation', 'shift8-real-estate-listings-for-treb'); ?></h3>
                <ul>
                    <li><a href="https://developer.ampre.ca/docs/" target="_blank"><?php esc_html_e('AMPRE API Documentation', 'shift8-real-estate-listings-for-treb'); ?></a></li>
                    <li><a href="https://ddwiki.reso.org/display/DDW17/Property+Resource" target="_blank"><?php esc_html_e('RESO Property Resource', 'shift8-real-estate-listings-for-treb'); ?></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.shift8-treb-admin-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
    width: 100%;
}

.shift8-treb-main-content {
    flex: 1;
    min-width: 0; /* Allows flex item to shrink below content size */
    width: calc(100% - 320px); /* Explicit width calculation */
}

.shift8-treb-sidebar {
    width: 300px;
    flex-shrink: 0;
    flex-basis: 300px;
}

/* Use more specific selector to override WordPress default .card styles */
.shift8-treb-main-content .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
    padding: 20px;
    width: 100%;
    max-width: none; /* Override WordPress default max-width: 520px */
    min-width: auto; /* Override WordPress default min-width: 255px */
    box-sizing: border-box;
}

.card h2.title,
.card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    color: #23282d;
}

.shift8-treb-main-content .form-table {
    width: 100%;
    max-width: none;
}

.shift8-treb-main-content .form-table th {
    width: 200px;
    padding-right: 20px;
}

.shift8-treb-main-content .form-table td {
    width: auto;
}

.required {
    color: #d63638;
}

.status-active {
    color: #00a32a;
    font-weight: 600;
}

.status-inactive {
    color: #d63638;
    font-weight: 600;
}

.sync-actions {
    text-align: center;
    margin-top: 15px;
}

#api-test-result {
    padding: 8px 12px;
    border-radius: 3px;
    display: none;
}

#api-test-result.success {
    background-color: #d1eddd;
    border-left: 4px solid #00a32a;
    color: #00a32a;
}

#api-test-result.error {
    background-color: #f8d7da;
    border-left: 4px solid #d63638;
    color: #d63638;
}

/* WordPress admin override - using specific selectors instead of !important */
.wrap .shift8-treb-admin-container {
    display: flex;
    width: 100%;
    max-width: none;
}

.wrap .shift8-treb-main-content {
    flex: 1;
    min-width: 0;
    width: calc(100% - 320px);
}

@media (max-width: 782px) {
    .wrap .shift8-treb-admin-container {
        flex-direction: column;
    }
    
    .wrap .shift8-treb-main-content {
        width: 100%;
    }
    
    .wrap .shift8-treb-sidebar {
        width: 100%;
        max-width: none;
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test API Connection
    $('#test-api-connection').on('click', function() {
        var button = $(this);
        var resultDiv = $('#api-test-result');
        var token = $('#bearer_token').val();
        
        if (!token) {
            resultDiv.removeClass('success').addClass('error').text('<?php esc_html_e('Please enter a bearer token first.', 'shift8-real-estate-listings-for-treb'); ?>').show();
            return;
        }
        
        button.prop('disabled', true).text('<?php esc_html_e('Testing...', 'shift8-real-estate-listings-for-treb'); ?>');
        resultDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_test_api_connection',
                nonce: '<?php echo esc_js(wp_create_nonce('shift8_treb_nonce')); ?>',
                bearer_token: token
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.removeClass('error').addClass('success').text(response.data.message).show();
                } else {
                    resultDiv.removeClass('success').addClass('error').text(response.data.message).show();
                }
            },
            error: function() {
                resultDiv.removeClass('success').addClass('error').text('<?php esc_html_e('Connection test failed.', 'shift8-real-estate-listings-for-treb'); ?>').show();
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Test Connection', 'shift8-real-estate-listings-for-treb'); ?>');
            }
        });
    });
    
    // Reset Sync Mode
    $('#reset-sync').on('click', function() {
        var button = $(this);
        
        if (!confirm('<?php esc_html_e('Reset incremental sync mode? This will force the next sync to use age-based filtering and may re-import deleted posts.', 'shift8-real-estate-listings-for-treb'); ?>')) {
            return;
        }
        
        button.prop('disabled', true).text('<?php esc_html_e('Resetting...', 'shift8-real-estate-listings-for-treb'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_reset_sync',
                nonce: '<?php echo esc_js(wp_create_nonce('shift8_treb_nonce')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Sync mode reset successfully! Page will reload.', 'shift8-real-estate-listings-for-treb'); ?>');
                    location.reload();
                } else {
                    alert('<?php esc_html_e('Failed to reset sync mode: ', 'shift8-real-estate-listings-for-treb'); ?>' + response.data.message);
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to reset sync mode.', 'shift8-real-estate-listings-for-treb'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Reset Sync Mode', 'shift8-real-estate-listings-for-treb'); ?>');
            }
        });
    });

    // Manual Sync
    $('#manual-sync').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php esc_html_e('Syncing...', 'shift8-real-estate-listings-for-treb'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_manual_sync',
                nonce: '<?php echo esc_js(wp_create_nonce('shift8_treb_nonce')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('<?php esc_html_e('Sync failed: ', 'shift8-real-estate-listings-for-treb'); ?>' + response.data.message);
                }
            },
            error: function() {
                alert('<?php esc_html_e('Sync request failed.', 'shift8-real-estate-listings-for-treb'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Run Manual Sync', 'shift8-real-estate-listings-for-treb'); ?>');
            }
        });
    });
    
    // View Logs
    $('#view-logs').on('click', function() {
        var button = $(this);
        var logViewer = $('#log-viewer');
        var logContent = $('#log-content');
        
        if (logViewer.is(':visible')) {
            logViewer.hide();
            button.text('<?php esc_html_e('View Recent Logs', 'shift8-real-estate-listings-for-treb'); ?>');
            return;
        }
        
        button.prop('disabled', true).text('<?php esc_html_e('Loading...', 'shift8-real-estate-listings-for-treb'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_get_logs',
                nonce: '<?php echo esc_js(wp_create_nonce('shift8_treb_nonce')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    logContent.val(response.data.logs);
                    logViewer.show();
                    button.text('<?php esc_html_e('Hide Logs', 'shift8-real-estate-listings-for-treb'); ?>');
                } else {
                    alert('<?php esc_html_e('Failed to load logs: ', 'shift8-real-estate-listings-for-treb'); ?>' + response.data.message);
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to load logs.', 'shift8-real-estate-listings-for-treb'); ?>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Clear Logs handler is in admin.js - no duplicate handler needed here
});
</script>