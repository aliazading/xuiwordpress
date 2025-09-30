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
        var request = $.post(xui_admin_settings.ajax_url, data);

        request.done(function (response) {
            if (response.success) {
                notice.removeClass('notice-error').addClass('notice-success is-dismissible').html('<p>' + response.data.message + '</p>').show();

                // Populate inbounds list
                if (response.data.inbounds && response.data.inbounds.length > 0) {
                    $.each(response.data.inbounds, function (index, inbound) {
                        var checked = '';
                        if (response.data.enabled_inbounds && response.data.enabled_inbounds.includes(inbound.id.toString())) {
                            checked = 'checked';
                        }
                        var remark = inbound.remark || 'No name';
                        inboundsList.append(
                            '<label><input type="checkbox" name="xui_enabled_inbounds[]" value="' + inbound.id + '" ' + checked + '> ' +
                            '<span>' + remark + ' (' + inbound.protocol + ')</span></label><br>'
                        );
                    });
                } else {
                    inboundsList.html('<p>' + 'No inbounds found.' + '</p>');
                }

            } else {
                notice.removeClass('notice-success').addClass('notice-error is-dismissible').html('<p>' + response.data + '</p>').show();
            }
        });

        request.fail(function (jqXHR, textStatus, errorThrown) {
            var errorMessage = 'An unknown error occurred. Please check the browser console for more details.';
            if (errorThrown) {
                errorMessage = 'AJAX Error: ' + errorThrown;
            } else if (textStatus === 'timeout') {
                errorMessage = 'The request timed out. Please check your panel URL and server status.';
            }
            notice.removeClass('notice-success').addClass('notice-error is-dismissible').html('<p>' + errorMessage + '</p>').show();
        });

        request.always(function () {
            // Restore button state
            button.prop('disabled', false).text('Test Connection & Fetch Inbounds');
        });
    });
});