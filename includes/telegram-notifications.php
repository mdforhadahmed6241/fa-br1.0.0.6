<?php
/**
 * Telegram Notification Module for Business Report Plugin
 *
 * Handles sending daily business reports via Telegram.
 *
 * @package BusinessReport
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * 1. SETTINGS PAGE CONTENT
 */
function br_telegram_settings_tab_html() {
    $options = get_option('br_telegram_settings', []);
    $bot_token = $options['bot_token'] ?? '';
    $chat_id = $options['chat_id'] ?? '';
    $enabled = $options['enabled'] ?? 0;
    $selected_metrics = $options['metrics'] ?? [];

    // Define available metrics
    $available_metrics = [
        'total_orders' => __('Total Order Place', 'business-report'),
        'confirmed_orders' => __('Total Order Confirm', 'business-report'),
        'cancelled_orders' => __('Total Order Cancel', 'business-report'),
        'delivered_orders' => __('Total Delivered', 'business-report'),
        'returned_orders' => __('Total Return', 'business-report'),
        'ad_cost_per_order' => __('Ads Cost/Order', 'business-report'),
        'ad_cost_per_confirmed' => __('Ads Cost/Confirm Order', 'business-report'),
        'total_revenue' => __('Total Revenue', 'business-report'),
        'total_expense' => __('Total Expense', 'business-report'),
        'total_ad_cost' => __('Total Ad Cost', 'business-report'),
        'gross_profit' => __('Total Profit (Gross)', 'business-report'),
        'net_profit' => __('Total Net Profit', 'business-report'),
    ];
    ?>
    <div class="br-settings-header">
        <h3><?php _e('Telegram Notifications', 'business-report'); ?></h3>
    </div>
    <p class="settings-section-description">
        <?php _e('Receive daily business reports on Telegram every morning at 10 AM (showing yesterday\'s data).', 'business-report'); ?>
    </p>

    <table class="form-table br-settings-form">
        <tr>
            <th scope="row"><?php _e('Enable Notifications', 'business-report'); ?></th>
            <td>
                <label class="br-switch">
                    <input type="checkbox" name="br_telegram_settings[enabled]" value="1" <?php checked($enabled, 1); ?>>
                    <span class="br-slider"></span>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Bot Token', 'business-report'); ?></th>
            <td>
                <input type="text" name="br_telegram_settings[bot_token]" value="<?php echo esc_attr($bot_token); ?>" class="regular-text" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
                <p class="description"><?php _e('Create a bot via @BotFather on Telegram to get this token.', 'business-report'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Chat ID', 'business-report'); ?></th>
            <td>
                <input type="text" name="br_telegram_settings[chat_id]" value="<?php echo esc_attr($chat_id); ?>" class="regular-text" placeholder="-1001234567890">
                <p class="description"><?php _e('The unique ID for your Telegram chat or channel.', 'business-report'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Report Data', 'business-report'); ?></th>
            <td>
                <fieldset class="br-telegram-metrics-list">
                    <?php foreach ($available_metrics as $key => $label) : ?>
                        <label class="br-metric-checkbox">
                            <input type="checkbox" name="br_telegram_settings[metrics][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_metrics)); ?>>
                            <?php echo esc_html($label); ?>
                        </label><br>
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Test Configuration', 'business-report'); ?></th>
            <td>
                <button type="button" id="br-telegram-test-btn" class="button button-secondary"><?php _e('Send Test Message', 'business-report'); ?></button>
                <span id="br-telegram-test-status" style="margin-left: 10px;"></span>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * 2. HELPER: FETCH YESTERDAY'S STATS
 */
function br_telegram_get_stats() {
    // Reuse dashboard logic to fetch stats for "Yesterday"
    if (!function_exists('br_get_dashboard_stats')) {
        require_once BR_PLUGIN_DIR . 'includes/dashboard.php';
    }

    // Logic for "Yesterday"
    $start_date = date('Y-m-d', strtotime('-1 day'));
    $end_date = $start_date; // Same day

    // Fetch raw stats using the dashboard helper
    return br_get_dashboard_stats($start_date, $end_date);
}

/**
 * 3. HELPER: BUILD MESSAGE
 */
function br_telegram_build_message($stats, $settings) {
    $metrics = $settings['metrics'] ?? [];
    $date = date('F j, Y', strtotime('-1 day'));
    
    $msg = "📊 *Business Report - {$date}*\n";
    $msg .= "--------------------------------\n";

    $labels = [
        'total_orders' => '📦 Total Order Place',
        'confirmed_orders' => '✅ Total Order Confirm',
        'cancelled_orders' => '🚫 Total Order Cancel',
        'delivered_orders' => '🚚 Total Delivered',
        'returned_orders' => '↩️ Total Return',
        'ad_cost_per_order' => '📉 Ads Cost/Order',
        'ad_cost_per_confirmed' => '📉 Ads Cost/Confirm',
        'total_revenue' => '💰 Total Revenue',
        'total_expense' => '💸 Total Expense', // Calculated as total_expenses_all in dashboard.php
        'total_ad_cost' => '📢 Total Ad Cost', // Maps to total_ad_spend
        'gross_profit' => '📈 Total Profit', // Gross
        'net_profit' => '💵 Total Net Profit', // True Net
    ];

    foreach ($labels as $key => $label) {
        // Map keys from dashboard.php to our keys if needed
        $data_key = $key;
        if ($key === 'total_expense') $data_key = 'total_expenses_all';
        if ($key === 'total_ad_cost') $data_key = 'total_ad_spend';
        if ($key === 'net_profit') $data_key = 'true_net_profit';

        // Only include if selected in settings
        if (in_array($key, $metrics)) {
            $val = $stats[$data_key] ?? 0;
            
            // Format based on key type
            if (strpos($key, 'revenue') !== false || strpos($key, 'cost') !== false || strpos($key, 'profit') !== false || strpos($key, 'expense') !== false) {
                // Remove HTML entities for currency symbol if any, Telegram doesn't like them
                $val = html_entity_decode(strip_tags(wc_price($val)));
            } else {
                $val = number_format_i18n($val);
            }

            $msg .= "{$label}: *{$val}*\n";
        }
    }
    
    $msg .= "--------------------------------\n";
    $msg .= "Report generated by Business Report Plugin";

    return $msg;
}

/**
 * 4. HELPER: SEND TO TELEGRAM
 */
function br_telegram_send_message($message) {
    $settings = get_option('br_telegram_settings', []);
    $bot_token = $settings['bot_token'] ?? '';
    $chat_id = $settings['chat_id'] ?? '';

    if (empty($bot_token) || empty($chat_id)) {
        return new WP_Error('missing_config', 'Bot Token or Chat ID is missing.');
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $args = [
        'body' => [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ],
        'timeout' => 15,
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!$body['ok']) {
        return new WP_Error('api_error', $body['description']);
    }

    return true;
}

/**
 * 5. EXECUTION FUNCTION (Triggered by Action Scheduler)
 */
function br_execute_telegram_daily_report() {
    $settings = get_option('br_telegram_settings', []);
    if (empty($settings['enabled'])) {
        return;
    }

    $stats = br_telegram_get_stats();
    $message = br_telegram_build_message($stats, $settings);
    
    $result = br_telegram_send_message($message);
    
    if (is_wp_error($result)) {
        error_log("BR Telegram Error: " . $result->get_error_message());
    } else {
        error_log("BR Log: Telegram daily report sent successfully.");
    }
}

/**
 * 6. AJAX: TEST MESSAGE
 */
function br_ajax_send_telegram_test() {
    check_ajax_referer('br_settings_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    // Save temporary settings passed via AJAX just for the test context (optional)
    // Or better, just use the posted data directly without saving to test unsaved values
    $test_token = sanitize_text_field($_POST['bot_token']);
    $test_chat_id = sanitize_text_field($_POST['chat_id']);

    if (empty($test_token) || empty($test_chat_id)) {
        wp_send_json_error(['message' => 'Please enter Bot Token and Chat ID.']);
    }

    $message = "✅ *Business Report Plugin Test*\n\nThis is a test message to confirm your configuration is correct.";
    
    $url = "https://api.telegram.org/bot{$test_token}/sendMessage";
    $args = [
        'body' => [
            'chat_id' => $test_chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ],
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Connection Failed: ' . $response->get_error_message()]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!$body['ok']) {
        wp_send_json_error(['message' => 'Telegram API Error: ' . $body['description']]);
    }

    wp_send_json_success(['message' => 'Message sent successfully!']);
}
add_action('wp_ajax_br_send_telegram_test', 'br_ajax_send_telegram_test');