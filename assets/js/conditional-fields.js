/**
 * Advance Booking System - Conditional Fields Handler
 */
jQuery(document).ready(function ($) {

    // Debug
    var debug = typeof abs_config !== 'undefined' && abs_config.debug_mode;
    function log(msg) { if (debug) console.log('[ABS Conditional]', msg); }

    // ========================================
    // PROPERTY ACCESS CONDITIONAL FIELDS
    // ========================================

    var accessFieldMap = {
        'keysafe': ['.abs-keysafe-code', '[class*="keysafe-code"]', '[class*="keysafe_code"]'],
        'vendor': ['.abs-vendor-name', '.abs-vendor-contact', '[class*="vendor-name"]', '[class*="vendor-contact"]', '[class*="tenant"]'],
        'tenant': ['.abs-vendor-name', '.abs-vendor-contact', '[class*="vendor-name"]', '[class*="vendor-contact"]', '[class*="tenant"]'],
        'pick up': ['.abs-office-location', '[class*="office"]'],
        'office': ['.abs-office-location', '[class*="office"]'],
        'other': ['.abs-access-details', '[class*="access-detail"]', '[class*="other-access"]']
    };

    function hideAllConditionalFields() {
        var allSelectors = [];
        for (var key in accessFieldMap) {
            allSelectors = allSelectors.concat(accessFieldMap[key]);
        }

        $(allSelectors.join(', ')).each(function () {
            $(this).closest('.ssa-field, .ssa-form-field, .abs-form-field').hide().addClass('abs-conditional-hidden');
        });
    }

    function showFieldsForAccess(accessValue) {
        if (!accessValue) return;

        var lowerValue = accessValue.toLowerCase();

        for (var key in accessFieldMap) {
            if (lowerValue.indexOf(key) !== -1) {
                var selectors = accessFieldMap[key];
                $(selectors.join(', ')).each(function () {
                    $(this).closest('.ssa-field, .ssa-form-field, .abs-form-field').show().removeClass('abs-conditional-hidden');
                });
            }
        }
    }

    function handleAccessChange() {
        // Find property access dropdown/select
        var $accessField = $('[class*="property-access"] select, [class*="access-type"] select, .abs-property-access select');

        if ($accessField.length === 0) {
            // Try radio buttons
            $accessField = $('[class*="property-access"] input:checked, [class*="access-type"] input:checked');
        }

        if ($accessField.length === 0) return;

        var value = $accessField.val() || $accessField.filter(':checked').val() || '';

        log('Access value: ' + value);

        hideAllConditionalFields();
        showFieldsForAccess(value);
    }

    // Listen for changes
    $(document).on('change', '[class*="property-access"] select, [class*="property-access"] input, [class*="access-type"] select, [class*="access-type"] input, .abs-property-access select, .abs-property-access input', function () {
        handleAccessChange();
    });

    // ========================================
    // EXTRA IMAGES CONDITIONAL
    // ========================================

    function handleExtraImagesToggle() {
        var $extraImagesCheckbox = $('label').filter(function () {
            return $(this).text().toLowerCase().indexOf('extra') !== -1 &&
                $(this).text().toLowerCase().indexOf('image') !== -1;
        }).find('input[type="checkbox"]').addBack('input[type="checkbox"]');

        if ($extraImagesCheckbox.length) {
            var $countField = $('[class*="extra-images-count"], [class*="extra_images_count"]').closest('.ssa-field, .ssa-form-field');

            if ($extraImagesCheckbox.is(':checked')) {
                $countField.show().removeClass('abs-conditional-hidden');
            } else {
                $countField.hide().addClass('abs-conditional-hidden');
            }
        }
    }

    $(document).on('change', 'input[type="checkbox"]', function () {
        var label = $(this).closest('label').text() || $('label[for="' + this.id + '"]').text();
        if (label.toLowerCase().indexOf('extra') !== -1) {
            handleExtraImagesToggle();
        }
    });

    // ========================================
    // OFFICE LOCATION DYNAMIC POPULATION
    // ========================================

    function populateOfficeDropdown() {
        if (typeof abs_config === 'undefined') return;

        var $officeSelect = $('[class*="office-location"] select, [class*="office_location"] select, .abs-office-location select');

        $officeSelect.each(function () {
            var $select = $(this);
            if ($select.data('abs-populated')) return;

            // Determine if this is rentals or sales form
            var $form = $select.closest('.ssa-booking, form');
            var formType = 'rentals';

            if ($form.attr('class') && $form.attr('class').indexOf('sales') !== -1) {
                formType = 'sales';
            }
            if ($form.find('[class*="sales"]').length || $form.closest('#form-sales').length) {
                formType = 'sales';
            }

            var offices = formType === 'sales' ? abs_config.sales_offices : abs_config.rental_offices;

            if (offices && offices.length) {
                // Clear existing options except first
                $select.find('option:not(:first)').remove();

                offices.forEach(function (office) {
                    office = office.trim();
                    if (office) {
                        $select.append('<option value="' + office + '">' + office + '</option>');
                    }
                });

                $select.data('abs-populated', true);
                log('Populated office dropdown with ' + offices.length + ' options');
            }
        });
    }

    // ========================================
    // FORM TYPE DETECTION (for sales-specific fields)
    // ========================================

    function handleFormTypeFields() {
        // Dusk and Proofing should only show on Sales forms
        var $rentalsForm = $('#form-rentals, .ssa-booking[class*="rental"]');
        var $salesForm = $('#form-sales, .ssa-booking[class*="sales"]');

        // Hide dusk/proofing in rentals
        $rentalsForm.find('[class*="dusk"], [class*="proofing"]').closest('.ssa-field, .ssa-form-field').hide();

        // Ensure they're visible in sales
        $salesForm.find('[class*="dusk"], [class*="proofing"]').closest('.ssa-field, .ssa-form-field').show();
    }

    // ========================================
    // INITIALIZATION
    // ========================================

    function initConditionalFields() {
        hideAllConditionalFields();
        handleAccessChange();
        handleExtraImagesToggle();
        populateOfficeDropdown();
        handleFormTypeFields();
    }

    // Run on load and periodically (for SSA AJAX loads)
    setTimeout(initConditionalFields, 500);
    setInterval(function () {
        handleAccessChange();
        populateOfficeDropdown();
    }, 1500);

    // Re-init when SSA steps change
    $(document).on('click', '.ssa-next-button, .ssa-prev-button', function () {
        setTimeout(initConditionalFields, 300);
    });

    log('Conditional fields handler initialized');

});
