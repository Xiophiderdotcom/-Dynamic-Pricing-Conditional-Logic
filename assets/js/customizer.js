/**
 * Customizer JavaScript
 * Handles real-time preview, color pickers, and saving
 */

(function ($) {
    'use strict';

    let previewFrame;
    let currentCustomizations = {};

    $(document).ready(function () {
        initCustomizer();
    });

    function initCustomizer() {
        // Initialize color pickers
        $('.color-picker').wpColorPicker({
            change: function (event, ui) {
                const input = $(event.target);
                const key = input.attr('name');
                const value = ui.color.toString();
                updatePreview(key, value);
            },
            clear: function (event) {
                const input = $(event.target);
                const key = input.attr('name');
                const defaultColor = input.data('default-color');
                updatePreview(key, defaultColor);
            }
        });

        // Text input changes
        $('.text-input').on('input change', function () {
            const key = $(this).attr('name');
            const value = $(this).val();
            updatePreview(key, value);
        });

        // Tab switching
        $('.tab-btn').on('click', function () {
            const tab = $(this).data('tab');
            $('.tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.customizer-section').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });

        // Save button
        $('#abs-save-customizer').on('click', saveCustomizations);

        // Reset button
        $('#abs-reset-customizer').on('click', resetCustomizations);

        // Initialize preview frame
        previewFrame = document.getElementById('abs-preview-frame');

        // Load current customizations
        currentCustomizations = absCustomizer.current;

        // Wait for iframe to load
        $(previewFrame).on('load', function () {
            console.log('Preview frame loaded');
        });
    }

    function updatePreview(key, value) {
        // Update current customizations
        currentCustomizations[key] = value;

        // Send message to preview iframe
        if (previewFrame && previewFrame.contentWindow) {
            previewFrame.contentWindow.postMessage({
                type: 'abs_customizer_update',
                key: key,
                value: value
            }, '*');
        }
    }

    function saveCustomizations() {
        const button = $('#abs-save-customizer');
        const originalText = button.text();
        button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: absCustomizer.ajaxUrl,
            type: 'POST',
            data: {
                action: 'abs_save_customizations',
                nonce: absCustomizer.nonce,
                data: currentCustomizations
            },
            success: function (response) {
                if (response.success) {
                    showNotice('✓ Saved successfully!', 'success');
                } else {
                    showNotice('✗ Save failed: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function () {
                showNotice('✗ Network error while saving', 'error');
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function resetCustomizations() {
        if (!confirm('Reset all customizations to defaults? This cannot be undone.')) {
            return;
        }

        const button = $('#abs-reset-customizer');
        const originalText = button.text();
        button.prop('disabled', true).text('Resetting...');

        $.ajax({
            url: absCustomizer.ajaxUrl,
            type: 'POST',
            data: {
                action: 'abs_reset_customizations',
                nonce: absCustomizer.nonce
            },
            success: function (response) {
                if (response.success) {
                    showNotice('✓ Reset to defaults!', 'success');
                    // Reload page to show defaults
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice('✗ Reset failed', 'error');
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function () {
                showNotice('✗ Network error', 'error');
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function showNotice(message, type) {
        const notice = $('<div>')
            .addClass('abs-save-notice')
            .addClass(type === 'error' ? 'error' : '')
            .text(message)
            .appendTo('body');

        setTimeout(function () {
            notice.fadeOut(300, function () {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery);
