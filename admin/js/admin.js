/**
 * Admin JavaScript for Shift8 TREB plugin
 *
 * @package Shift8\TREB
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize admin functionality
     */
    function initAdmin() {
        // Test API Connection
        $('#test-api-connection').on('click', function(e) {
            e.preventDefault();
            testApiConnection();
        });

        // Manual Sync
        $('#manual-sync').on('click', function(e) {
            e.preventDefault();
            if (confirm(shift8TREB.strings.confirm_sync)) {
                runManualSync();
            }
        });

        // View Logs
        $('#view-logs').on('click', function(e) {
            e.preventDefault();
            toggleLogViewer();
        });

        // Clear Logs
        $('#clear-logs').on('click', function(e) {
            e.preventDefault();
            if (confirm(shift8TREB.strings.confirm_clear)) {
                clearLogs();
            }
        });

        // Auto-refresh sync status every 30 seconds
        setInterval(refreshSyncStatus, 30000);
    }

    /**
     * Test API connection
     */
    function testApiConnection() {
        var $button = $('#test-api-connection');
        var $result = $('#api-test-result');
        
        // Check if bearer token is entered or if there's an existing token
        var bearerToken = $('#bearer_token').val();
        var hasExistingToken = $('#bearer_token').attr('placeholder').indexOf('Token is set') !== -1;
        
        if (!bearerToken && !hasExistingToken) {
            showResult($result, 'error', shift8TREB.strings.error + ' Please enter a bearer token first.');
            return;
        }

        // Set loading state
        $button.addClass('loading').prop('disabled', true);
        showResult($result, 'loading', shift8TREB.strings.testing);

        // Make AJAX request
        $.ajax({
            url: shift8TREB.ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_test_api_connection',
                nonce: shift8TREB.nonce
            },
            success: function(response) {
                if (response.success) {
                    showResult($result, 'success', shift8TREB.strings.success + ' ' + response.data.message);
                } else {
                    showResult($result, 'error', shift8TREB.strings.error + ' ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showResult($result, 'error', shift8TREB.strings.error + ' ' + error);
            },
            complete: function() {
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    }

    /**
     * Run manual sync
     */
    function runManualSync() {
        var $button = $('#manual-sync');
        
        // Set loading state
        $button.addClass('loading').prop('disabled', true);
        $button.text(shift8TREB.strings.syncing);

        // Make AJAX request
        $.ajax({
            url: shift8TREB.ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_manual_sync',
                nonce: shift8TREB.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    // Refresh sync status after a short delay
                    setTimeout(refreshSyncStatus, 2000);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', shift8TREB.strings.error + ' ' + error);
            },
            complete: function() {
                $button.removeClass('loading').prop('disabled', false);
                $button.text('Run Manual Sync');
            }
        });
    }

    /**
     * Toggle log viewer
     */
    function toggleLogViewer() {
        var $logViewer = $('#log-viewer');
        var $button = $('#view-logs');
        
        if ($logViewer.is(':visible')) {
            $logViewer.hide();
            $button.text('View Recent Logs');
        } else {
            $button.addClass('loading').prop('disabled', true);
            
            // Load logs
            $.ajax({
                url: shift8TREB.ajaxurl,
                type: 'POST',
                data: {
                    action: 'shift8_treb_get_logs',
                    nonce: shift8TREB.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var logs = response.data.logs;
                        var logContent = logs.join('\n');
                        $('#log-content').val(logContent);
                        $logViewer.show();
                        $button.text('Hide Logs');
                        
                        // Show log size info
                        if (response.data.log_size) {
                            showNotice('info', 'Log file size: ' + response.data.log_size);
                        }
                    } else {
                        showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', shift8TREB.strings.error + ' ' + error);
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        }
    }

    /**
     * Clear logs
     */
    function clearLogs() {
        var $button = $('#clear-logs');
        
        $button.addClass('loading').prop('disabled', true);

        $.ajax({
            url: shift8TREB.ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_treb_clear_log',
                nonce: shift8TREB.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    $('#log-content').val('');
                    $('#log-viewer').hide();
                    $('#view-logs').text('View Recent Logs');
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', shift8TREB.strings.error + ' ' + error);
            },
            complete: function() {
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    }

    /**
     * Refresh sync status
     */
    function refreshSyncStatus() {
        // This would typically make an AJAX call to get updated sync status
        // For now, we'll just reload the page section if needed
        // Implementation can be added later when the sync functionality is complete
    }

    /**
     * Show result message
     */
    function showResult($element, type, message) {
        $element.removeClass('success error loading')
                .addClass(type)
                .text(message)
                .show();
        
        // Auto-hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $element.fadeOut();
            }, 5000);
        }
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var noticeClass = 'notice notice-' + type;
        var $notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Insert after the page title
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Form validation
     */
    function validateForm() {
        var isValid = true;
        var errors = [];

        // Validate bearer token
        var bearerToken = $('#bearer_token').val();
        var hasExistingToken = $('#bearer_token').attr('placeholder').indexOf('Token is set') !== -1;
        
        if (!bearerToken && !hasExistingToken) {
            errors.push('Bearer token is required.');
            isValid = false;
        }

        // Validate max listings
        var maxListings = parseInt($('#max_listings_per_query').val());
        if (isNaN(maxListings) || maxListings < 1 || maxListings > 1000) {
            errors.push('Max listings per query must be between 1 and 1000.');
            isValid = false;
        }

        // Validate price range
        var minPrice = $('#min_price').val();
        var maxPrice = $('#max_price').val();
        
        if (minPrice && maxPrice && parseInt(minPrice) >= parseInt(maxPrice)) {
            errors.push('Minimum price must be less than maximum price.');
            isValid = false;
        }

        // Show errors if any
        if (!isValid) {
            showNotice('error', 'Please fix the following errors:<br>• ' + errors.join('<br>• '));
        }

        return isValid;
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        // Add tooltips to form fields with descriptions
        $('.form-table .description').each(function() {
            var $description = $(this);
            var $field = $description.siblings('input, select, textarea').first();
            
            if ($field.length) {
                $field.attr('title', $description.text());
            }
        });
    }

    /**
     * Handle frequency change
     */
    function handleFrequencyChange() {
        $('#sync_frequency').on('change', function() {
            var frequency = $(this).val();
            var $notice = $('.frequency-notice');
            
            // Remove existing notice
            $notice.remove();
            
            // Show warning for frequent syncs
            if (frequency === 'hourly') {
                var notice = '<div class="frequency-notice shift8-treb-notice notice-warning">' +
                           '<p><strong>Warning:</strong> Hourly syncing may put significant load on your server and the AMPRE API. ' +
                           'Consider using a less frequent interval unless you specifically need real-time updates.</p>' +
                           '</div>';
                $(this).closest('td').append(notice);
            }
        });
    }

    /**
     * Auto-save draft settings - DISABLED
     * This was causing annoying "unsaved changes" warnings
     */
    function initAutoSave() {
        // Disabled - was showing annoying warnings
        // Remove notice when form is submitted
        $('form').on('submit', function() {
            $('.unsaved-changes-notice').remove();
        });
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initAdmin();
        initTooltips();
        handleFrequencyChange();
        initAutoSave();
        
        // Form validation on submit
        $('form').on('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
    });

})(jQuery);
