jQuery(document).ready(function ($) {
    $('#xui-test-connection').on('click', function () {
        var button = $(this);
        var notice = $('#xui-test-connection-notice');
        var inboundsList = $('#xui-inbounds-list');

        // Show loading feedback
        button.prop('disabled', true).text('Testing...');
        notice.hide();
        inboundsList.empty();

        // Get credentials from the form
        var data = {
            action: 'xui_test_connection',
            nonce: xui_admin_settings.nonce,
            url: $('#xui_api_url').val(),
            username: $('#xui_api_username').val(),
            password: $('#xui_api_password').val()
        };

        // Make the AJAX call
        $.post(ajaxurl, data, function (response) {
            if (response.success) {
                notice.removeClass('notice-error').addClass('notice-success').html('<p>' + response.data.message + '</p>').show();

                // Populate inbounds list
                if (response.data.inbounds && response.data.inbounds.length > 0) {
                    $.each(response.data.inbounds, function (index, inbound) {
                        var checked = response.data.enabled_inbounds.includes(inbound.id.toString()) ? 'checked' : '';
                        inboundsList.append(
                            '<label><input type="checkbox" name="xui_enabled_inbounds[]" value="' + inbound.id + '" ' + checked + '> ' +
                            '<span>' + inbound.remark + ' (' + inbound.protocol + ')</span></label><br>'
                        );
                    });
                } else {
                    inboundsList.html('<p>' + 'No inbounds found.' + '</p>');
                }

            } else {
                notice.removeClass('notice-success').addClass('notice-error').html('<p>' + response.data + '</p>').show();
            }

            // Restore button state
            button.prop('disabled', false).text('Test Connection & Fetch Inbounds');
        });
    });
});