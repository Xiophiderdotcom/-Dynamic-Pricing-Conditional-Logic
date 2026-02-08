/**
 * Customizer Preview Script
 * Runs inside the preview iframe to apply real-time changes
 */

(function ($) {
    'use strict';

    // Listen for messages from parent customizer
    window.addEventListener('message', function (event) {
        // Security: verify origin if needed
        // if (event.origin !== window.location.origin) return;

        if (event.data.type === 'abs_customizer_update') {
            applyCustomization(event.data.key, event.data.value);
        }
    });

    function applyCustomization(key, value) {
        // Map keys to CSS variables or DOM elements
        const cssVarMap = {
            'primary_color': '--abs-primary',
            'secondary_color': '--abs-secondary',
            'accent_color': '--abs-accent',
            'success_color': '--abs-success',
            'error_color': '--abs-error',
            'hero_bg_start': '--abs-hero-start',
            'hero_bg_end': '--abs-hero-end',
            'card_bg_color': '--abs-card-bg',
            'form_bg_color': '--abs-form-bg',
            'text_primary': '--abs-text-primary',
            'text_secondary': '--abs-text-secondary',
            'text_muted': '--abs-text-muted',
            'heading_font': '--abs-heading-font',
            'body_font': '--abs-body-font',
            'base_font_size': '--abs-base-size',
            'heading_font_weight': '--abs-heading-weight',
            'body_font_weight': '--abs-body-weight'
        };

        // Text content map
        const textMap = {
            'hero_heading': '.abs-booking-hero h1',
            'hero_subheading': '.abs-booking-hero p',
            'rental_card_title': '.abs-type-card[data-type="rental"] h3',
            'rental_card_desc': '.abs-type-card[data-type="rental"] > p',
            'sales_card_title': '.abs-type-card[data-type="sales"] h3',
            'sales_card_desc': '.abs-type-card[data-type="sales"] > p',
            'submit_button_text': '.abs-btn-submit',
            'confirmation_heading': '.confirmation-box h2',
            'confirmation_message': '.confirmation-box > p'
        };

        // Apply CSS variable
        if (cssVarMap[key]) {
            document.documentElement.style.setProperty(cssVarMap[key], value);
        }

        // Apply text content
        if (textMap[key]) {
            $(textMap[key]).text(value);
        }

        console.log('Applied customization:', key, value);
    }

})(jQuery);
