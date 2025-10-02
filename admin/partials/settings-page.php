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
    'listing_status_filter' => 'Active',
    'city_filter' => 'Toronto',
    'property_type_filter' => '',
    'min_price' => '0',
    'max_price' => '999999999',
    'listing_template' => 'Property Details:\n\nAddress: %ADDRESS%\nPrice: %PRICE%\nMLS: %MLS%\nBedrooms: %BEDROOMS%\nBathrooms: %BATHROOMS%\nSquare Feet: %SQFT%\n\nDescription:\n%DESCRIPTION%'
));

// Get sync status
$sync_status = array(
    'next_sync' => wp_next_scheduled('shift8_treb_sync_listings') ? 
        wp_date('Y-m-d H:i:s', wp_next_scheduled('shift8_treb_sync_listings')) : 
        esc_html__('Not scheduled', 'shift8-treb'),
    'last_sync' => get_option('shift8_treb_last_sync', esc_html__('Never', 'shift8-treb')),
    'is_scheduled' => wp_next_scheduled('shift8_treb_sync_listings') !== false
);
?>

<div class="wrap">
    <h1><?php esc_html_e('Shift8 TREB Real Estate Listings', 'shift8-treb'); ?></h1>
    
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
                    <h2 class="title"><?php esc_html_e('AMPRE API Configuration', 'shift8-treb'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="bearer_token"><?php esc_html_e('Bearer Token', 'shift8-treb'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="bearer_token" 
                                       name="shift8_treb_settings[bearer_token]" 
                                       value="<?php echo esc_attr($settings['bearer_token']); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php echo !empty($settings['bearer_token']) ? esc_attr__('Token is set', 'shift8-treb') : esc_attr__('Enter your AMPRE API bearer token', 'shift8-treb'); ?>" />
                                <p class="description">
                                    <?php esc_html_e('Your AMPRE API bearer token for authentication.', 'shift8-treb'); ?>
                                    <button type="button" id="test-api-connection" class="button button-secondary" style="margin-left: 10px;">
                                        <?php esc_html_e('Test Connection', 'shift8-treb'); ?>
                                    </button>
                                </p>
                                <div id="api-test-result" style="margin-top: 10px;"></div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Sync Configuration Section -->
                <div class="card">
                    <h2 class="title"><?php esc_html_e('Sync Configuration', 'shift8-treb'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="sync_frequency"><?php esc_html_e('Sync Frequency', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <select id="sync_frequency" name="shift8_treb_settings[sync_frequency]">
                                    <option value="hourly" <?php selected($settings['sync_frequency'], 'hourly'); ?>><?php esc_html_e('Hourly', 'shift8-treb'); ?></option>
                                    <option value="eight_hours" <?php selected($settings['sync_frequency'], 'eight_hours'); ?>><?php esc_html_e('Every 8 Hours', 'shift8-treb'); ?></option>
                                    <option value="twelve_hours" <?php selected($settings['sync_frequency'], 'twelve_hours'); ?>><?php esc_html_e('Every 12 Hours', 'shift8-treb'); ?></option>
                                    <option value="daily" <?php selected($settings['sync_frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'shift8-treb'); ?></option>
                                    <option value="weekly" <?php selected($settings['sync_frequency'], 'weekly'); ?>><?php esc_html_e('Weekly', 'shift8-treb'); ?></option>
                                    <option value="biweekly" <?php selected($settings['sync_frequency'], 'biweekly'); ?>><?php esc_html_e('Bi-weekly', 'shift8-treb'); ?></option>
                                    <option value="monthly" <?php selected($settings['sync_frequency'], 'monthly'); ?>><?php esc_html_e('Monthly', 'shift8-treb'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('How often to sync listings from AMPRE API.', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="max_listings_per_query"><?php esc_html_e('Max Listings Per Query', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="max_listings_per_query" 
                                       name="shift8_treb_settings[max_listings_per_query]" 
                                       value="<?php echo esc_attr($settings['max_listings_per_query']); ?>" 
                                       min="1" 
                                       max="1000" 
                                       class="small-text" />
                                <p class="description"><?php esc_html_e('Maximum number of listings to fetch per API call (1-1000).', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Listing Filters Section -->
                <div class="card">
                    <h2 class="title"><?php esc_html_e('Listing Filters', 'shift8-treb'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="listing_status_filter"><?php esc_html_e('Status Filter', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="listing_status_filter" 
                                       name="shift8_treb_settings[listing_status_filter]" 
                                       value="<?php echo esc_attr($settings['listing_status_filter']); ?>" 
                                       class="regular-text" 
                                       placeholder="Active" />
                                <p class="description"><?php esc_html_e('Filter listings by status (e.g., Active, Sold).', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="city_filter"><?php esc_html_e('City Filter', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="city_filter" 
                                       name="shift8_treb_settings[city_filter]" 
                                       value="<?php echo esc_attr($settings['city_filter']); ?>" 
                                       class="regular-text" 
                                       placeholder="Toronto" />
                                <p class="description"><?php esc_html_e('Filter listings by city.', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="property_type_filter"><?php esc_html_e('Property Type Filter', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="property_type_filter" 
                                       name="shift8_treb_settings[property_type_filter]" 
                                       value="<?php echo esc_attr($settings['property_type_filter']); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Leave empty for all types', 'shift8-treb'); ?>" />
                                <p class="description"><?php esc_html_e('Filter listings by property type (e.g., Residential, Commercial).', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="min_price"><?php esc_html_e('Minimum Price', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="min_price" 
                                       name="shift8_treb_settings[min_price]" 
                                       value="<?php echo esc_attr($settings['min_price']); ?>" 
                                       min="0" 
                                       class="regular-text" />
                                <p class="description"><?php esc_html_e('Minimum listing price filter.', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="max_price"><?php esc_html_e('Maximum Price', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="max_price" 
                                       name="shift8_treb_settings[max_price]" 
                                       value="<?php echo esc_attr($settings['max_price']); ?>" 
                                       min="0" 
                                       class="regular-text" />
                                <p class="description"><?php esc_html_e('Maximum listing price filter.', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Additional Settings Section -->
                <div class="card">
                    <h2 class="title"><?php esc_html_e('Additional Settings', 'shift8-treb'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="google_maps_api_key"><?php esc_html_e('Google Maps API Key', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="google_maps_api_key" 
                                       name="shift8_treb_settings[google_maps_api_key]" 
                                       value="<?php echo esc_attr($settings['google_maps_api_key']); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Optional: For enhanced mapping features', 'shift8-treb'); ?>" />
                                <p class="description"><?php esc_html_e('Google Maps API key for enhanced mapping features (optional).', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="debug_enabled"><?php esc_html_e('Debug Mode', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="debug_enabled" 
                                           name="shift8_treb_settings[debug_enabled]" 
                                           value="1" 
                                           <?php checked($settings['debug_enabled'], '1'); ?> />
                                    <?php esc_html_e('Enable debug logging', 'shift8-treb'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Enable detailed logging for troubleshooting.', 'shift8-treb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="listing_template"><?php esc_html_e('Listing Template', 'shift8-treb'); ?></label>
                            </th>
                            <td>
                                <textarea id="listing_template" 
                                          name="shift8_treb_settings[listing_template]" 
                                          rows="10" 
                                          cols="50" 
                                          class="large-text"><?php echo esc_textarea($settings['listing_template']); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Template for listing post content. Use placeholders like %ADDRESS%, %PRICE%, %MLS%, %BEDROOMS%, %BATHROOMS%, %SQFT%, %DESCRIPTION%, %PROPERTY_TYPE%, %CITY%, %POSTAL_CODE%', 'shift8-treb'); ?>
                                </p>
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
                <h3><?php esc_html_e('Sync Status', 'shift8-treb'); ?></h3>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Next Sync:', 'shift8-treb'); ?></strong></td>
                            <td><?php echo esc_html($sync_status['next_sync']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Last Sync:', 'shift8-treb'); ?></strong></td>
                            <td><?php echo esc_html($sync_status['last_sync']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Status:', 'shift8-treb'); ?></strong></td>
                            <td>
                                <span class="<?php echo $sync_status['is_scheduled'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $sync_status['is_scheduled'] ? esc_html__('Scheduled', 'shift8-treb') : esc_html__('Not Scheduled', 'shift8-treb'); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="sync-actions">
                    <button type="button" id="manual-sync" class="button button-primary">
                        <?php esc_html_e('Run Manual Sync', 'shift8-treb'); ?>
                    </button>
                </p>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <h3><?php esc_html_e('Quick Stats', 'shift8-treb'); ?></h3>
                <?php
                // Count posts in 'Listings' category
                $listings_category = get_category_by_slug('listings');
                $listing_count = 0;
                if ($listings_category) {
                    $listing_count = $listings_category->count;
                }
                ?>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Total Listings:', 'shift8-treb'); ?></strong></td>
                            <td><?php echo esc_html(number_format($listing_count)); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Post Type:', 'shift8-treb'); ?></strong></td>
                            <td><code>post</code></td>
                        </tr>
                    </tbody>
                </table>
                
                <p>
                    <a href="<?php echo esc_url(admin_url('edit.php?category_name=listings')); ?>" class="button button-secondary">
                        <?php esc_html_e('View All Listings', 'shift8-treb'); ?>
                    </a>
                </p>
            </div>

            <!-- Debug Logs -->
            <?php if ($settings['debug_enabled'] === '1'): ?>
            <div class="card">
                <h3><?php esc_html_e('Debug Logs', 'shift8-treb'); ?></h3>
                <p>
                    <button type="button" id="view-logs" class="button button-secondary">
                        <?php esc_html_e('View Recent Logs', 'shift8-treb'); ?>
                    </button>
                    <button type="button" id="clear-logs" class="button button-secondary">
                        <?php esc_html_e('Clear Logs', 'shift8-treb'); ?>
                    </button>
                </p>
                <div id="log-viewer" style="display: none;">
                    <textarea id="log-content" rows="15" style="width: 100%; font-family: monospace; font-size: 11px;" readonly></textarea>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documentation -->
            <div class="card">
                <h3><?php esc_html_e('Documentation', 'shift8-treb'); ?></h3>
                <ul>
                    <li><a href="https://developer.ampre.ca/docs/" target="_blank"><?php esc_html_e('AMPRE API Documentation', 'shift8-treb'); ?></a></li>
                    <li><a href="https://ddwiki.reso.org/display/DDW17/Property+Resource" target="_blank"><?php esc_html_e('RESO Property Resource', 'shift8-treb'); ?></a></li>
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
}

.shift8-treb-main-content {
    flex: 1;
}

.shift8-treb-sidebar {
    width: 300px;
    flex-shrink: 0;
}

.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
    padding: 20px;
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

@media (max-width: 782px) {
    .shift8-treb-admin-container {
        flex-direction: column;
    }
    
    .shift8-treb-sidebar {
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
            resultDiv.removeClass('success').addClass('error').text('<?php esc_html_e('Please enter a bearer token first.', 'shift8-treb'); ?>').show();
            return;
        }
        
        button.prop('disabled', true).text('<?php esc_html_e('Testing...', 'shift8-treb'); ?>');
        resultDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_test_api_connection',
                nonce: '<?php echo wp_create_nonce('shift8_treb_nonce'); ?>',
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
                resultDiv.removeClass('success').addClass('error').text('<?php esc_html_e('Connection test failed.', 'shift8-treb'); ?>').show();
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Test Connection', 'shift8-treb'); ?>');
            }
        });
    });
    
    // Manual Sync
    $('#manual-sync').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php esc_html_e('Syncing...', 'shift8-treb'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_manual_sync',
                nonce: '<?php echo wp_create_nonce('shift8_treb_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('<?php esc_html_e('Sync failed: ', 'shift8-treb'); ?>' + response.data.message);
                }
            },
            error: function() {
                alert('<?php esc_html_e('Sync request failed.', 'shift8-treb'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Run Manual Sync', 'shift8-treb'); ?>');
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
            button.text('<?php esc_html_e('View Recent Logs', 'shift8-treb'); ?>');
            return;
        }
        
        button.prop('disabled', true).text('<?php esc_html_e('Loading...', 'shift8-treb'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_get_logs',
                nonce: '<?php echo wp_create_nonce('shift8_treb_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    logContent.val(response.data.logs);
                    logViewer.show();
                    button.text('<?php esc_html_e('Hide Logs', 'shift8-treb'); ?>');
                } else {
                    alert('<?php esc_html_e('Failed to load logs: ', 'shift8-treb'); ?>' + response.data.message);
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to load logs.', 'shift8-treb'); ?>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Clear Logs
    $('#clear-logs').on('click', function() {
        if (!confirm('<?php esc_html_e('Are you sure you want to clear all logs?', 'shift8-treb'); ?>')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('<?php esc_html_e('Clearing...', 'shift8-treb'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_clear_log',
                nonce: '<?php echo wp_create_nonce('shift8_treb_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#log-content').val('');
                    $('#log-viewer').hide();
                    $('#view-logs').text('<?php esc_html_e('View Recent Logs', 'shift8-treb'); ?>');
                    alert(response.data.message);
                } else {
                    alert('<?php esc_html_e('Failed to clear logs: ', 'shift8-treb'); ?>' + response.data.message);
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to clear logs.', 'shift8-treb'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Clear Logs', 'shift8-treb'); ?>');
            }
        });
    });
});
</script>