jQuery(document).ready(function ($) {

    /**
     * Search and Filter Functionality
     */
    $('#of-booking-search').on('keyup', function () {
        var value = $(this).val().toLowerCase();

        $('.of-booking-card').filter(function () {
            var searchData = $(this).data('search');
            var matches = searchData.indexOf(value) > -1;

            // Should also respect the status filter
            var statusFilter = $('#of-status-filter').val();
            var status = $(this).data('status');
            var statusMatch = (statusFilter === 'all' || status === statusFilter);

            $(this).toggle(matches && statusMatch);
        });

        checkEmptyState();
    });

    $('#of-status-filter').on('change', function () {
        var statusFilter = $(this).val();
        var searchValue = $('#of-booking-search').val().toLowerCase();

        $('.of-booking-card').filter(function () {
            var status = $(this).data('status');
            var searchData = $(this).data('search');

            var statusMatch = (statusFilter === 'all' || status === statusFilter);
            var searchMatch = searchData.indexOf(searchValue) > -1;

            $(this).toggle(statusMatch && searchMatch);
        });

        checkEmptyState();
    });

    function checkEmptyState() {
        var visible = $('.of-booking-card:visible').length;
        if (visible === 0) {
            if ($('#of-no-results').length === 0) {
                $('#of-bookings-grid').append('<p id="of-no-results" style="text-align:center; grid-column:1/-1; padding:20px; color:#b2bec3;">No bookings match your filter.</p>');
            }
        } else {
            $('#of-no-results').remove();
        }
    }

    /**
     * Submit Edit Modal
     */
    window.ofSubmitEdit = function () {
        var id = $('#edit-booking-id').val();
        var mobile = $('#edit-agent-mobile').val();
        var btn = $('button[onclick="ofSubmitEdit()"]');

        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: of_agent_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'of_update_appointment',
                nonce: of_agent_vars.nonce,
                appointment_id: id,
                field: 'agent_mobile',
                value: mobile
            },
            success: function (response) {
                if (response.success) {
                    // Close modal
                    $('#of-edit-modal').hide();

                    // Update UI
                    var card = $('.of-booking-card[data-id="' + id + '"]');
                    card.find('.value:contains("Mobile")').next().text(mobile); // Fallback logic
                    // Better: finding by data attribute if we added it, but let's refresh page or update DOM
                    location.reload(); // Simplest to ensure data consistency
                } else {
                    alert('Error: ' + response.data);
                    btn.prop('disabled', false).text('Save Changes');
                }
            },
            error: function () {
                alert('Connection error');
                btn.prop('disabled', false).text('Save Changes');
            }
        });
    };

    /**
     * Cancel Booking
     */
    window.ofCancelBooking = function (id) {
        if (!confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: of_agent_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'of_cancel_appointment',
                nonce: of_agent_vars.nonce,
                appointment_id: id
            },
            success: function (response) {
                if (response.success) {
                    $('.of-booking-card[data-id="' + id + '"]').fadeOut(300, function () {
                        $(this).remove();
                        checkEmptyState();
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function () {
                alert('Connection error');
            }
        });
    };

});
