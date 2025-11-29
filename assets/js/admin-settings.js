jQuery(function($) {
    'use strict';

    // --- Telegram Test Button ---
    $('#br-telegram-test-btn').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const statusSpan = $('#br-telegram-test-status');
        const form = button.closest('form');
        
        const botToken = form.find('input[name="br_telegram_settings[bot_token]"]').val();
        const chatId = form.find('input[name="br_telegram_settings[chat_id]"]').val();

        if (!botToken || !chatId) {
            alert('Please enter a Bot Token and Chat ID first.');
            return;
        }

        button.prop('disabled', true);
        statusSpan.text('Sending...').css('color', '#666');

        $.ajax({
            url: br_settings_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'br_send_telegram_test',
                nonce: br_settings_ajax.nonce,
                bot_token: botToken,
                chat_id: chatId
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.text('✅ ' + response.data.message).css('color', '#057A55');
                } else {
                    statusSpan.text('❌ ' + response.data.message).css('color', '#E02424');
                }
            },
            error: function() {
                statusSpan.text('❌ Server error occurred.').css('color', '#E02424');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // --- License Activation Button ---
    $('#br-activate-license-btn').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const feedback = $('#br-license-feedback');
        const key = $('#br_license_key').val().trim();

        if (!key) {
            alert('Please enter your license key.');
            return;
        }

        button.prop('disabled', true).text('Activating...');
        feedback.text('');

        $.post(br_settings_ajax.ajax_url, {
            action: 'br_activate_license',
            nonce: br_settings_ajax.nonce,
            license_key: key
        })
        .done(function(response) {
            if (response.success) {
                feedback.text(response.data.message).css('color', '#057A55');
                setTimeout(() => window.location.reload(), 1000); // Reload to show active state
            } else {
                feedback.text('Error: ' + response.data.message).css('color', '#E02424');
                button.prop('disabled', false).text('Activate License');
            }
        })
        .fail(function() {
            feedback.text('Communication error.').css('color', '#E02424');
            button.prop('disabled', false).text('Activate License');
        });
    });

    // --- License Deactivation Button ---
    $('#br-deactivate-license-btn').on('click', function(e) {
        e.preventDefault();
        
        if(!confirm('Are you sure you want to deactivate the license?')) return;

        const button = $(this);
        const feedback = $('#br-license-feedback');

        button.prop('disabled', true).text('Deactivating...');
        feedback.text('');

        $.post(br_settings_ajax.ajax_url, {
            action: 'br_deactivate_license',
            nonce: br_settings_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                feedback.text(response.data.message).css('color', '#057A55');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                feedback.text('Error: ' + response.data.message).css('color', '#E02424');
                button.prop('disabled', false).text('Deactivate License');
            }
        })
        .fail(function() {
            feedback.text('Communication error.').css('color', '#E02424');
            button.prop('disabled', false).text('Deactivate License');
        });
    });

});