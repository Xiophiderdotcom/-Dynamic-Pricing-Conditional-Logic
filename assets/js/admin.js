/**
 * Advance Booking System v2.1 - Admin JS
 */
jQuery(document).ready(function ($) {

    // Status change
    $('.status-select').on('change', function () {
        var id = $(this).data('id');
        var status = $(this).val();

        $.post(absAdmin.ajaxUrl, {
            action: 'abs_update_status',
            nonce: absAdmin.nonce,
            id: id,
            status: status
        }, function (r) {
            if (r.success) {
                // Visual feedback
                var $row = $('[data-id="' + id + '"]').closest('tr');
                $row.css('background', '#fffbeb').delay(500).queue(function () {
                    $(this).css('background', '').dequeue();
                });
            }
        });
    });

    // Delete booking
    $('.btn-delete').on('click', function () {
        if (!confirm('Delete this booking permanently?')) return;

        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $.post(absAdmin.ajaxUrl, {
            action: 'abs_delete_booking',
            nonce: absAdmin.nonce,
            id: id
        }, function (r) {
            if (r.success) {
                $row.fadeOut(300, function () { $(this).remove(); });
            }
        });
    });

    // ========================================
    // REAL-TIME API KEY TESTING
    // ========================================
    
    var apiKeyInput = $('#abs_google_api_key');
    var testBtn = $('#abs_test_api_btn');
    var statusSpan = $('#abs_api_status');
    var resultDiv = $('#abs_api_result');
    var testTimeout;

    // Auto-test when API key is changed (with debounce)
    if (apiKeyInput.length) {
        apiKeyInput.on('input', function () {
            clearTimeout(testTimeout);
            var apiKey = $(this).val().trim();
            
            if (apiKey.length > 20) {
                // Debounce: wait 1.5s after typing stops
                testTimeout = setTimeout(function() {
                    testApiKey(apiKey, true);
                }, 1500);
            } else {
                // Clear status if key is too short
                statusSpan.removeClass().addClass('api-status').text('');
                resultDiv.hide();
            }
        });
    }

    // Manual test button
    testBtn.on('click', function (e) {
        e.preventDefault();
        var apiKey = apiKeyInput.val().trim();
        
        if (!apiKey) {
            alert('Please enter an API key to test.');
            apiKeyInput.focus();
            return;
        }
        
        testApiKey(apiKey, false);
    });

    // API Testing Function
    function testApiKey(apiKey, isAutoTest) {
        // Update UI to testing state
        testBtn.addClass('testing').prop('disabled', true);
        statusSpan.removeClass().addClass('api-status testing').text('🔄 Testing...');
        resultDiv.hide();

        $.ajax({
            url: absAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'abs_test_api_key',
                nonce: absAdmin.nonce,
                api_key: apiKey
            },
            success: function (response) {
                testBtn.removeClass('testing').prop('disabled', false);
                
                if (response.success) {
                    // Success
                    statusSpan.removeClass().addClass('api-status valid').text('✅ Valid');
                    showResult(true, response.data.message, response.data.details);
                } else {
                    // Error
                    var errorStatus = response.data.status || 'error';
                    statusSpan.removeClass().addClass('api-status ' + errorStatus).text('❌ Invalid');
                    showResult(false, response.data.message, response.data.details);
                }
            },
            error: function (xhr, status, error) {
                testBtn.removeClass('testing').prop('disabled', false);
                statusSpan.removeClass().addClass('api-status error').text('❌ Error');
                showResult(false, 'Connection error', 'Unable to test API key. Please try again.');
            },
            timeout: 15000
        });
    }

    // Show test result
    function showResult(isSuccess, message, details) {
        resultDiv.removeClass('success error').addClass(isSuccess ? 'success' : 'error');
        resultDiv.find('.result-message').text(message);
        
        var detailsContent = '';
        
        if (details) {
            if (typeof details === 'object') {
                // Format object details
                for (var key in details) {
                    if (details.hasOwnProperty(key)) {
                        var value = details[key];
                        
                        // Format specific fields
                        if (key === 'location' && value) {
                            value = 'Lat: ' + value.lat + ', Lng: ' + value.lng;
                        } else if (typeof value === 'object') {
                            value = JSON.stringify(value, null, 2);
                        }
                        
                        var label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        detailsContent += '<div class="detail-row"><div class="detail-label">' + label + ':</div><div class="detail-value">' + value + '</div></div>';
                    }
                }
            } else {
                // String details
                detailsContent = '<div class="detail-value">' + details + '</div>';
            }
            
            resultDiv.find('.details-content').html(detailsContent);
            resultDiv.find('.result-details').show();
        } else {
            resultDiv.find('.result-details').hide();
        }
        
        resultDiv.fadeIn(300);
    }

    // Toggle details expansion (if needed in future)
    $(document).on('click', '.toggle-details', function() {
        $(this).toggleClass('expanded');
        $(this).next('.result-details').slideToggle(200);
    });

});
