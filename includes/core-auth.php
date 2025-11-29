<?php
/**
 * Core Authentication Module
 *
 * Handles activation and deactivation of the plugin via remote API.
 * API Base: https://ecomaticbd.com/wp-json/my-license/v1/
 *
 * @package BusinessReport
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if the license is currently active.
 * Used to gatekeep plugin features.
 *
 * @return bool True if active, false otherwise.
 */
function br_is_license_active() {
    $license_data = get_option('br_license_data', []);
    
    // Basic check: Status must be 'active' and key must exist
    if ( empty($license_data['key']) || empty($license_data['status']) || $license_data['status'] !== 'active' ) {
        return false;
    }

    // Optional: Check expiration date locally to save API calls
    // If expires_at is set and is in the past, return false.
    if ( !empty($license_data['expires']) && $license_data['expires'] !== 'Lifetime' ) {
        $expiry = strtotime($license_data['expires']);
        if ( $expiry && $expiry < time() ) {
            return false;
        }
    }

    return true;
}

/**
 * Renders the License Settings Tab HTML.
 */
function br_license_settings_tab_html() {
    $license_data = get_option('br_license_data', []);
    $license_key = isset($license_data['key']) ? $license_data['key'] : '';
    $status = isset($license_data['status']) ? $license_data['status'] : 'inactive';
    $expires_at = isset($license_data['expires']) ? $license_data['expires'] : '';
    
    $is_active = ($status === 'active');
    ?>
    <div class="br-settings-header">
        <h3><?php _e('License Management', 'business-report'); ?></h3>
    </div>
    
    <?php if (!$is_active): ?>
    <div class="notice notice-error inline">
        <p><strong><?php _e('Plugin Inactive:', 'business-report'); ?></strong> <?php _e('Please activate your license key to unlock all features.', 'business-report'); ?></p>
    </div>
    <?php endif; ?>

    <div class="br-license-box <?php echo $is_active ? 'active-box' : 'inactive-box'; ?>">
        <div class="br-license-status-row">
            <span><?php _e('Status:', 'business-report'); ?></span>
            <?php if ($is_active): ?>
                <span class="br-license-badge active"><?php _e('Active', 'business-report'); ?></span>
            <?php else: ?>
                <span class="br-license-badge inactive"><?php _e('Inactive', 'business-report'); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($is_active && $expires_at): ?>
        <div class="br-license-expiry-row">
            <span><?php _e('Expires:', 'business-report'); ?></span>
            <strong><?php echo esc_html($expires_at); ?></strong>
        </div>
        <?php endif; ?>

        <form id="br-license-form" class="br-settings-form" style="margin-top: 20px;">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('License Key', 'business-report'); ?></th>
                    <td>
                        <input type="text" id="br_license_key" name="license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" <?php echo $is_active ? 'readonly' : ''; ?>>
                        <p class="description"><?php _e('Enter the license key provided with your purchase.', 'business-report'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Action', 'business-report'); ?></th>
                    <td>
                        <?php if ($is_active): ?>
                            <button type="button" id="br-deactivate-license-btn" class="button"><?php _e('Deactivate License', 'business-report'); ?></button>
                        <?php else: ?>
                            <button type="button" id="br-activate-license-btn" class="button button-primary"><?php _e('Activate License', 'business-report'); ?></button>
                        <?php endif; ?>
                        <span id="br-license-feedback" style="margin-left: 10px; font-weight: 600;"></span>
                    </td>
                </tr>
            </table>
        </form>
    </div>
    <?php
}

/**
 * Helper: Send Remote Request to License Server
 */
function br_remote_license_request($endpoint, $body_args) {
    $url = 'https://ecomaticbd.com/wp-json/my-license/v1/' . $endpoint;
    
    $response = wp_remote_post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ],
        'body'    => json_encode($body_args),
        'timeout' => 15,
        'sslverify' => false 
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'message' => $response->get_error_message()];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $code = wp_remote_retrieve_response_code($response);

    if ($code !== 200) {
        $msg = isset($data['message']) ? $data['message'] : 'Unknown API error';
        return ['success' => false, 'message' => $msg, 'code' => $data['code'] ?? ''];
    }

    return array_merge(['success' => true], $data);
}

/**
 * AJAX: Activate License
 */
function br_ajax_activate_license() {
    check_ajax_referer('br_settings_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) { wp_send_json_error(['message' => 'Permission denied']); }

    $license_key = sanitize_text_field($_POST['license_key']);
    $domain = preg_replace('#^https?://#', '', get_site_url()); 

    if (empty($license_key)) {
        wp_send_json_error(['message' => 'Please enter a license key.']);
    }

    $result = br_remote_license_request('activate', [
        'license_key' => $license_key,
        'domain'      => $domain
    ]);

    if (!$result['success']) {
        wp_send_json_error(['message' => $result['message']]);
    }

    $save_data = [
        'key'     => $license_key,
        'status'  => 'active',
        'expires' => $result['expires_at'] ?? 'Lifetime'
    ];
    update_option('br_license_data', $save_data);

    wp_send_json_success(['message' => 'License activated successfully!']);
}
add_action('wp_ajax_br_activate_license', 'br_ajax_activate_license');

/**
 * AJAX: Deactivate License
 */
function br_ajax_deactivate_license() {
    check_ajax_referer('br_settings_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) { wp_send_json_error(['message' => 'Permission denied']); }

    $license_data = get_option('br_license_data', []);
    $license_key = $license_data['key'] ?? '';
    $domain = preg_replace('#^https?://#', '', get_site_url());

    if (empty($license_key)) {
        delete_option('br_license_data');
        wp_send_json_success(['message' => 'Local license data cleared.']);
    }

    $result = br_remote_license_request('deactivate', [
        'license_key' => $license_key,
        'domain'      => $domain
    ]);
    
    delete_option('br_license_data');

    if (!$result['success']) {
        wp_send_json_success(['message' => 'License deactivated locally. Remote message: ' . $result['message']]);
    }

    wp_send_json_success(['message' => 'License deactivated successfully.']);
}
add_action('wp_ajax_br_deactivate_license', 'br_ajax_deactivate_license');