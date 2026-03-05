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

        // Validate geographic filter fields based on selected type
        var geoType = $('#geographic_filter_type').val();
        if (geoType === 'postal_prefix') {
            var postalVal = $('#postal_code_prefixes').val().trim();
            if (!postalVal) {
                errors.push('Postal code prefix filter is selected but no prefixes specified.');
                isValid = false;
            } else {
                var invalidPrefixes = validatePostalPrefixes(postalVal);
                if (invalidPrefixes.length > 0) {
                    errors.push('Invalid postal code prefix(es): ' + invalidPrefixes.join(', ') + '. Format must be letter-digit-letter (e.g., M5V).');
                    isValid = false;
                }
            }
        } else if (geoType === 'city') {
            var cityVal = $('#city_filter').val().trim();
            if (!cityVal) {
                errors.push('City filter is selected but no city specified.');
                isValid = false;
            } else if (cityListCache && cityListCache.length > 0) {
                var enteredCities = splitValues(cityVal).filter(function(c) { return c.trim(); });
                var lowered = cityListCache.map(function(c) { return c.toLowerCase(); });
                var unknown = [];
                for (var i = 0; i < enteredCities.length; i++) {
                    if (lowered.indexOf(enteredCities[i].trim().toLowerCase()) === -1) {
                        unknown.push(enteredCities[i].trim());
                    }
                }
                if (unknown.length > 0) {
                    errors.push('Unrecognised city name(s): ' + unknown.join(', ') + '. Use the autocomplete suggestions or click the refresh button.');
                    isValid = false;
                }
            }
        }

        // Show errors if any
        if (!isValid) {
            showNotice('error', 'Please fix the following errors:<br>• ' + errors.join('<br>• '));
        }

        return isValid;
    }

    /**
     * Validate postal code prefixes and return array of invalid ones
     */
    function validatePostalPrefixes(value) {
        if (!value) return [];
        var fsaPattern = /^[A-Za-z]\d[A-Za-z]$/;
        var prefixes = value.split(',');
        var invalid = [];
        for (var i = 0; i < prefixes.length; i++) {
            var p = prefixes[i].trim();
            if (p && !fsaPattern.test(p)) {
                invalid.push(p);
            }
        }
        return invalid;
    }

    /**
     * Toggle geographic filter fields based on selected type
     */
    function initGeographicFilterToggle() {
        var $select = $('#geographic_filter_type');
        if (!$select.length) return;

        function toggleRows() {
            var type = $select.val();
            $('#postal_prefix_row').toggle(type === 'postal_prefix');
            $('#city_filter_row').toggle(type === 'city');
            if (type === 'city') {
                loadCityAutocomplete(false);
            }
        }

        $select.on('change', toggleRows);
        toggleRows();
    }

    var cityListCache = null;

    /**
     * Load city list and initialise jQuery UI Autocomplete (multi-value)
     */
    function loadCityAutocomplete(forceRefresh) {
        var $field = $('#city_filter');
        var $status = $('#city-cache-status');
        if (!$field.length) return;

        var data = {
            action: 'shift8_treb_get_cities',
            nonce: shift8TREB.nonce
        };
        if (forceRefresh) {
            data.refresh = 1;
        }

        $status.text('Loading cities...').css('color', '#0073aa').show();

        $.post(shift8TREB.ajaxurl, data, function(response) {
            if (response.success && response.data.cities) {
                cityListCache = response.data.cities;
                var src = response.data.source === 'cache' ? 'cached' : 'API';
                $status.text(cityListCache.length + ' cities loaded (' + src + ')').css('color', '#00a32a');
                initCityAutocomplete($field, cityListCache);
            } else {
                var msg = response.data && response.data.message ? response.data.message : 'Could not load cities';
                $status.text(msg).css('color', '#d63638');
            }
        }).fail(function() {
            $status.text('Network error loading cities').css('color', '#d63638');
        });
    }

    function splitValues(val) {
        return val.split(/,\s*/);
    }

    function extractLast(term) {
        return splitValues(term).pop();
    }

    function initCityAutocomplete($field, cities) {
        if ($field.data('ui-autocomplete')) {
            $field.autocomplete('destroy');
        }

        $field.on('keydown.shift8autocomplete', function(event) {
            if (event.keyCode === $.ui.keyCode.TAB && $(this).autocomplete('instance').menu.active) {
                event.preventDefault();
            }
        });

        $field.autocomplete({
            minLength: 1,
            source: function(request, response) {
                var term = extractLast(request.term).toLowerCase();
                var matches = $.grep(cities, function(city) {
                    return city.toLowerCase().indexOf(term) === 0;
                });
                response(matches.slice(0, 15));
            },
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var terms = splitValues(this.value);
                terms.pop();
                terms.push(ui.item.value);
                terms.push('');
                this.value = terms.join(', ');
                return false;
            }
        });
    }

    /**
     * Real-time validation for postal code prefix field
     */
    function initPostalPrefixValidation() {
        var $field = $('#postal_code_prefixes');
        if (!$field.length) return;

        $field.on('blur', function() {
            var val = $(this).val().trim();
            var $feedback = $(this).next('.shift8-treb-validation-msg');
            if (!$feedback.length) {
                $feedback = $('<span class="shift8-treb-validation-msg"></span>');
                $(this).after($feedback);
            }

            if (!val) {
                $feedback.text('').hide();
                return;
            }

            var invalid = validatePostalPrefixes(val);
            if (invalid.length > 0) {
                $feedback.text('Invalid: ' + invalid.join(', ') + ' — expected format: A1A (e.g., M5V)')
                         .css('color', '#d63638').show();
            } else {
                var count = val.split(',').filter(function(v) { return v.trim(); }).length;
                $feedback.text(count + ' valid prefix' + (count !== 1 ? 'es' : ''))
                         .css('color', '#00a32a').show();
            }
        });
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
        initGeographicFilterToggle();
        initPostalPrefixValidation();

        $('#refresh-city-list').on('click', function() {
            loadCityAutocomplete(true);
        });
        
        // Form validation on submit
        $('form').on('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
    });

})(jQuery);
