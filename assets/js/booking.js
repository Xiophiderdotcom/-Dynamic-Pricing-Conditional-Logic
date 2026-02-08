/**
 * Advance Booking System v2.1 - Booking JS
 */
jQuery(document).ready(function ($) {

    var selectedType = '';

    // ===== GET USER LOCATION ON LOAD =====
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            $('#submit_lat').val(pos.coords.latitude);
            $('#submit_lng').val(pos.coords.longitude);

            // Try to get city name via reverse geocoding (if Google Maps available)
            if (typeof google !== 'undefined' && google.maps) {
                var geocoder = new google.maps.Geocoder();
                var latlng = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                geocoder.geocode({ location: latlng }, function (results, status) {
                    if (status === 'OK' && results[0]) {
                        // Find city/suburb component
                        var city = '';
                        results[0].address_components.forEach(function (c) {
                            if (c.types.includes('locality') || c.types.includes('sublocality')) {
                                city = c.long_name;
                            }
                        });
                        $('#submit_location').val(city || results[0].formatted_address);
                    }
                });
            }
        }, function (err) {
            console.log('Geolocation not available:', err.message);
        }, { timeout: 5000 });
    }

    // ===== TYPE SELECTION =====
    $('.abs-type-card').on('click', function () {
        selectedType = $(this).data('type');
        $('#booking_type').val(selectedType);

        // Show/hide sales-only addons
        if (selectedType === 'sales') {
            $('.sales-only').show();
        } else {
            $('.sales-only').hide().find('input').prop('checked', false);
        }

        // Populate office dropdown
        var offices = selectedType === 'sales' ? absData.salesOffices : absData.rentalOffices;
        var $office = $('#office_location');
        $office.empty().append('<option value="">Select...</option>');
        offices.forEach(function (o) {
            $office.append('<option value="' + $.trim(o) + '">' + $.trim(o) + '</option>');
        });

        goToStep(2);
    });

    // ===== STEP NAVIGATION =====
    function goToStep(step) {
        $('.abs-step').removeClass('active');
        $('#step-' + step).addClass('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    window.absGoBack = function () { goToStep(1); };

    // ===== ADDON TOTAL =====
    function updateAddonTotal() {
        var total = 0;
        var hasQuoteItem = false;

        if ($('input[name="addon_drone"]').is(':checked')) total += absData.dronePrice;
        if ($('input[name="addon_dusk"]').is(':checked')) total += absData.duskPrice;
        if ($('input[name="addon_proofing"]').is(':checked')) total += absData.proofingPrice;
        if ($('input[name="addon_extra"]').is(':checked')) hasQuoteItem = true;

        if (hasQuoteItem && total > 0) {
            $('#addon-total').text('$' + total + ' + Quote');
        } else if (hasQuoteItem) {
            $('#addon-total').text('Quote Required');
        } else {
            $('#addon-total').text('$' + total);
        }
    }

    $('.addon-card input').on('change', updateAddonTotal);

    // ===== DUSK TIME FILTER =====
    $('#addon_dusk').on('change', function () {
        var isDusk = $(this).is(':checked');
        var $timeSelect = $('#booking_time');

        if (isDusk) {
            $('#dusk-note').show();
            $timeSelect.find('option').each(function () {
                var hour = $(this).data('hour');
                if (hour !== undefined && hour < 16) {
                    $(this).hide();
                    if ($(this).is(':selected')) $timeSelect.val('');
                } else {
                    $(this).show();
                }
            });
        } else {
            $('#dusk-note').hide();
            $timeSelect.find('option').show();
        }
    });

    // ===== PROPERTY ACCESS CONDITIONALS =====
    $('#property_access').on('change', function () {
        var val = $(this).val();
        $('.conditional-field').hide();

        switch (val) {
            case 'keysafe': $('#field-keysafe').show(); break;
            case 'pickup_keys': $('#field-office').show(); break;
            case 'vendor_tenant': $('#field-vendor').show(); break;
            case 'other': $('#field-other').show(); break;
        }
    });

    // ===== GOOGLE PLACES AUTOCOMPLETE =====
    function initAutocomplete() {
        if (typeof google === 'undefined' || !google.maps) return;

        var input = document.getElementById('property_address');
        if (!input || input.dataset.init) return;

        var autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['address'],
            componentRestrictions: { country: 'au' }
        });

        input.dataset.init = 'true';
    }

    setTimeout(initAutocomplete, 500);

    // ===== FORM SUBMISSION =====
    $('#abs-booking-form').on('submit', function (e) {
        e.preventDefault();

        var $btn = $('.abs-btn-submit');
        var btnText = $btn.text();
        $btn.prop('disabled', true).text('Submitting...');

        var formData = $(this).serialize();
        formData += '&action=abs_submit_booking&nonce=' + absData.nonce;

        $.post(absData.ajaxUrl, formData, function (response) {
            if (response.success) {
                var d = response.data.data;
                var summary = '<p><strong>Booking #' + response.data.id + '</strong></p>';
                summary += '<p>📍 ' + d.property_address + '</p>';
                summary += '<p>📅 ' + formatDate(d.booking_date) + ' at ' + formatTime(d.booking_time) + '</p>';
                summary += '<p>📧 Confirmation sent to ' + d.agent_email + '</p>';

                $('#booking-summary').html(summary);
                goToStep(3);
            } else {
                alert('Error: ' + response.data);
                $btn.prop('disabled', false).text(btnText);
            }
        }).fail(function () {
            alert('Connection error. Please try again.');
            $btn.prop('disabled', false).text(btnText);
        });
    });

    // ===== HELPERS =====
    function formatDate(dateStr) {
        var d = new Date(dateStr);
        return d.toLocaleDateString('en-AU', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    function formatTime(timeStr) {
        var parts = timeStr.split(':');
        var h = parseInt(parts[0]);
        var m = parts[1];
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + m + ' ' + ampm;
    }

    // ===== DATE VALIDATION (Mon-Thu default) =====
    $('#booking_date').on('change', function () {
        var date = new Date($(this).val());
        var day = date.getDay();

        // 0=Sun, 5=Fri, 6=Sat
        if (day === 0 || day === 5 || day === 6) {
            if (!confirm('This is a weekend/Friday. Weekend shoots may not be available. Continue anyway?')) {
                $(this).val('');
            }
        }
    });

    // Pre-fill user data if logged in
    if (absData.userEmail && !$('input[name="agent_email"]').val()) {
        $('input[name="agent_email"]').val(absData.userEmail);
    }
    if (absData.userName && !$('input[name="agent_name"]').val()) {
        $('input[name="agent_name"]').val(absData.userName);
    }

});
