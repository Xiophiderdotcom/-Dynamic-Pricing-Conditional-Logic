/**
 * Advance Booking System - Main Application JavaScript
 */
jQuery(document).ready(function($) {

    // Debug mode
    var debug = typeof abs_config !== 'undefined' && abs_config.debug_mode;
    function log(msg) { if (debug) console.log('[ABS]', msg); }

    // ========================================
    // 1. GOOGLE PLACES AUTOCOMPLETE
    // ========================================
    function initAutocomplete() {
        var $inputs = $('.abs-address-input input, [class*="address"] input[type="text"]');
        
        $inputs.each(function() {
            var input = this;
            if (input.dataset.absInit) return;
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') return;

            try {
                var autocomplete = new google.maps.places.Autocomplete(input, {
                    types: ['address'],
                    componentRestrictions: { country: 'au' }
                });

                autocomplete.addListener('place_changed', function() {
                    var place = autocomplete.getPlace();
                    if (!place.geometry) return;

                    log('Place selected: ' + place.formatted_address);

                    // Find closest form and populate hidden fields
                    var $form = $(input).closest('form');
                    if ($form.length === 0) $form = $(input).closest('.ssa-booking');
                    
                    // Populate hidden fields
                    $form.find('.abs_place_id input, [name*="place_id"]').val(place.place_id);
                    $form.find('.abs_lat input, [name*="lat"]').val(place.geometry.location.lat());
                    $form.find('.abs_lng input, [name*="lng"]').val(place.geometry.location.lng());
                });

                input.dataset.absInit = "true";
                log('Autocomplete initialized for: ' + input.name);
            } catch(e) {
                log('Autocomplete error: ' + e.message);
            }
        });
    }

    // Poll for SSA form loads
    setInterval(initAutocomplete, 1000);

    // ========================================
    // 2. DUSK TIME RESTRICTION
    // ========================================
    function checkDusk() {
        if (typeof abs_config === 'undefined') return;
        
        var isDusk = false;
        var duskTrigger = abs_config.dusk_trigger || 'Dusk';
        var duskCutoff = abs_config.dusk_cutoff || 16;

        // Check if Dusk add-on is selected
        $('label').each(function() {
            var labelText = $(this).text();
            if (labelText.indexOf(duskTrigger) !== -1) {
                var $checkbox = $(this).find('input[type="checkbox"]');
                if ($checkbox.length === 0) {
                    var id = $(this).attr('for');
                    $checkbox = $('#' + id);
                }
                if ($checkbox.is(':checked')) {
                    isDusk = true;
                }
            }
        });

        // Toggle Time Slots
        var $slots = $('.ssa-time-slot, .ssa-slot-button');
        if (isDusk) {
            $slots.each(function() {
                var timeText = $(this).text().trim();
                // Parse time (e.g., "2:00 pm", "4:30 PM")
                var match = timeText.match(/(\d{1,2}):?(\d{2})?\s*(am|pm)/i);
                if (match) {
                    var hour = parseInt(match[1]);
                    var period = match[3].toLowerCase();
                    
                    if (period === 'pm' && hour !== 12) hour += 12;
                    if (period === 'am' && hour === 12) hour = 0;
                    
                    if (hour < duskCutoff) {
                        $(this).addClass('abs-hidden-slot').hide();
                    } else {
                        $(this).removeClass('abs-hidden-slot').show();
                    }
                }
            });

            // Show dusk alert
            if ($('.abs-dusk-alert').length === 0) {
                var $container = $('.ssa-times-container, .ssa-time-slots');
                if ($container.length) {
                    $container.prepend('<div class="abs-dusk-alert"><span class="alert-icon">🌅</span> Dusk photography selected – Only late afternoon slots (4pm+) are available.</div>');
                }
            }
        } else {
            $slots.removeClass('abs-hidden-slot').show();
            $('.abs-dusk-alert').remove();
        }
    }

    $(document).on('change', 'input[type="checkbox"]', checkDusk);
    // Watch for dynamic content changes
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(checkDusk).observe(document.body, {childList: true, subtree: true});
    }

    // ========================================
    // 3. ADD-ON PRICING DISPLAY
    // ========================================
    function updateAddonTotal() {
        if (typeof abs_config === 'undefined') return;
        
        var total = 0;
        var selectedAddons = [];

        // Check Drone
        $('label').each(function() {
            var text = $(this).text().toLowerCase();
            var $cb = $(this).find('input[type="checkbox"]');
            if ($cb.length === 0) {
                var id = $(this).attr('for');
                $cb = $('#' + id);
            }
            
            if ($cb.is(':checked')) {
                if (text.indexOf('drone') !== -1) {
                    total += abs_config.drone_price || 0;
                    selectedAddons.push('Drone ($' + abs_config.drone_price + ')');
                }
                if (text.indexOf('dusk') !== -1) {
                    total += abs_config.dusk_price || 0;
                    selectedAddons.push('Dusk ($' + abs_config.dusk_price + ')');
                }
                if (text.indexOf('proofing') !== -1) {
                    total += abs_config.proofing_price || 0;
                    selectedAddons.push('Proofing ($' + abs_config.proofing_price + ')');
                }
            }
        });

        // Update display
        var $totalEl = $('#abs-addon-total');
        if ($totalEl.length) {
            if (total > 0) {
                $totalEl.text('+ $' + total);
                $('#abs-pricing-summary').addClass('has-addons');
            } else {
                $totalEl.text('$0');
                $('#abs-pricing-summary').removeClass('has-addons');
            }
        }
    }

    $(document).on('change', 'input[type="checkbox"]', updateAddonTotal);

    // ========================================
    // 4. EXTRA IMAGES LIMIT
    // ========================================
    function enforceExtraImagesLimit() {
        if (typeof abs_config === 'undefined') return;
        
        var limit = abs_config.extra_images_limit || 5;
        
        $('input[type="number"]').each(function() {
            var $input = $(this);
            var label = $input.closest('.ssa-field').find('label').text().toLowerCase();
            
            if (label.indexOf('extra') !== -1 && label.indexOf('image') !== -1) {
                $input.attr('max', limit);
                $input.attr('min', 0);
                
                $input.on('change input', function() {
                    var val = parseInt($(this).val()) || 0;
                    if (val > limit) {
                        $(this).val(limit);
                        alert('Maximum ' + limit + ' extra images allowed.');
                    }
                    if (val < 0) $(this).val(0);
                });
            }
        });
    }

    setInterval(enforceExtraImagesLimit, 1000);

    // ========================================
    // 5. INITIALIZATION
    // ========================================
    log('Advance Booking System initialized');
    
    // Initial calls
    setTimeout(function() {
        initAutocomplete();
        checkDusk();
        updateAddonTotal();
        enforceExtraImagesLimit();
    }, 500);

});