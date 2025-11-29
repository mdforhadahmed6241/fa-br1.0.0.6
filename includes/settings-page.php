<?php
/**
 * Settings Page Functionality for Business Report Plugin
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include the modules
require_once plugin_dir_path( __FILE__ ) . 'telegram-notifications.php';
require_once plugin_dir_path( __FILE__ ) . 'core-auth.php'; // Renamed License Module

/**
 * =================================================================================
 * 1. ADMIN PAGE & SETTINGS REGISTRATION
 * =================================================================================
 */

function br_settings_page_html() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;
    
    // Check License Status
    $is_licensed = function_exists('br_is_license_active') && br_is_license_active();
    
    // If NOT licensed, force active tab to 'license'
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'order';
    if ( !$is_licensed ) {
        $active_tab = 'license';
    }

    ?>
    <div class="wrap br-wrap">
        <div class="br-header">
            <h1><?php _e( 'Plugin Settings', 'business-report' ); ?></h1>
        </div>

        <h2 class="nav-tab-wrapper">
            <?php if ( $is_licensed ): ?>
                <a href="?page=br-settings&tab=order" class="nav-tab <?php echo $active_tab == 'order' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Order', 'business-report' ); ?></a>
                <a href="?page=br-settings&tab=cogs" class="nav-tab <?php echo $active_tab == 'cogs' ? 'nav-tab-active' : ''; ?>"><?php _e( 'COGS', 'business-report' ); ?></a>
                <a href="?page=br-settings&tab=courier" class="nav-tab <?php echo $active_tab == 'courier' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Courier', 'business-report' ); ?></a>
                <a href="?page=br-settings&tab=meta_ads" class="nav-tab <?php echo $active_tab == 'meta_ads' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Meta Ads', 'business-report' ); ?></a>
                <a href="?page=br-settings&tab=telegram" class="nav-tab <?php echo $active_tab == 'telegram' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Telegram', 'business-report' ); ?></a>
            <?php endif; ?>
            
            <!-- License Tab is always visible -->
            <a href="?page=br-settings&tab=license" class="nav-tab <?php echo $active_tab == 'license' ? 'nav-tab-active' : ''; ?>"><?php _e( 'License', 'business-report' ); ?></a>
        </h2>
        <div class="br-page-content">
            <?php 
            
            // If not licensed, only show license tab content
            if ( !$is_licensed && $active_tab !== 'license' ) {
                echo '<div class="notice notice-error"><p>' . __('Please activate your license to access settings.', 'business-report') . '</p></div>';
            } 
            elseif ( $active_tab == 'meta_ads' ) {
                if ( function_exists( 'br_meta_ads_settings_tab_html' ) ) br_meta_ads_settings_tab_html();
            } elseif ( $active_tab == 'telegram' ) {
                if ( function_exists( 'br_telegram_settings_tab_html' ) ) br_telegram_settings_tab_html();
            } elseif ( $active_tab == 'license' ) {
                if ( function_exists( 'br_license_settings_tab_html' ) ) br_license_settings_tab_html();
            } else {
                // Standard Settings API Tabs
                ?>
                <form class="br-settings-form" method="post" action="options.php">
                    <?php
                    if ( $active_tab == 'order' ) {
                        settings_fields( 'br_order_settings_group' );
                        do_settings_sections( 'br_order_settings_page' );
                    } elseif ( $active_tab == 'cogs' ) {
                        settings_fields( 'br_cogs_settings_group' );
                        do_settings_sections( 'br_cogs_settings_page' );
                    } elseif ( $active_tab == 'courier' ) {
                        settings_fields( 'br_courier_settings_group' );
                        do_settings_sections( 'br_courier_settings_page' );
                    }
                    submit_button();
                    ?>
                </form>
                <?php
            }
            ?>
        </div>
        <?php
        if ( $active_tab == 'meta_ads' && function_exists( 'br_meta_ads_account_modal_html' ) ) {
            br_meta_ads_account_modal_html();
        }
        ?>
    </div>
    <?php
}

function br_register_all_settings() {
    // ... (Previous Settings Registration Code - No changes needed here for License as it uses custom option) ...
    
    // 1. Order Settings
    register_setting( 'br_order_settings_group', 'br_converted_order_statuses' );
    add_settings_section( 'br_order_status_section', __( 'Order Report Settings', 'business-report' ), 'br_order_status_section_callback', 'br_order_settings_page' );
    add_settings_field( 'converted_statuses', __( 'Converted Order Statuses', 'business-report' ), 'br_order_statuses_field_html', 'br_order_settings_page', 'br_order_status_section' );

    // 2. COGS Settings
    register_setting('br_cogs_settings_group','br_cogs_settings','br_cogs_settings_sanitize');
    add_settings_section('br_cogs_general_rules_section',__('Set General Cost','business-report'),'br_cogs_general_rules_section_callback','br_cogs_settings_page');
    add_settings_field('general_mode',__('General Cost Mode','business-report'),'br_cogs_field_general_mode_html','br_cogs_settings_page','br_cogs_general_rules_section');
    add_settings_field('general_value',__('Value for Calculation','business-report'),'br_cogs_field_general_value_html','br_cogs_settings_page','br_cogs_general_rules_section');
    add_settings_section('br_cogs_dynamic_rules_section',__('Set Cost Dynamically by Price Range','business-report'),'br_cogs_dynamic_rules_section_callback','br_cogs_settings_page');
    add_settings_field('dynamic_rules',__('Conditional Rules','business-report'),'br_cogs_field_dynamic_rules_html','br_cogs_settings_page','br_cogs_dynamic_rules_section');
    add_settings_section('br_cogs_apply_rules_section',__('Apply Rules to Existing Products','business-report'),'br_cogs_apply_rules_section_callback','br_cogs_settings_page');
    
    // 3. Courier Settings
    register_setting('br_courier_settings_group', 'br_courier_delivered_statuses');
    register_setting('br_courier_settings_group', 'br_courier_returned_statuses');
    register_setting('br_courier_settings_group', 'br_courier_partial_returned_statuses');
    register_setting('br_courier_settings_group', 'br_courier_return_cost'); 
    
    add_settings_section('br_courier_status_section', __('Courier Report Statuses', 'business-report'), 'br_courier_status_section_callback', 'br_courier_settings_page');
    add_settings_field('delivered_statuses', __('Delivered Order Statuses', 'business-report'), 'br_courier_delivered_statuses_html', 'br_courier_settings_page', 'br_courier_status_section');
    add_settings_field('returned_statuses', __('Full Return Order Statuses', 'business-report'), 'br_courier_returned_statuses_html', 'br_courier_settings_page', 'br_courier_status_section');
    add_settings_field('partial_returned_statuses', __('Partial Return Order Statuses', 'business-report'), 'br_courier_partial_returned_statuses_html', 'br_courier_settings_page', 'br_courier_status_section');
    add_settings_field('return_cost', __('Return Courier Charge', 'business-report'), 'br_courier_return_cost_html', 'br_courier_settings_page', 'br_courier_status_section'); 

    // 4. Telegram Settings
    register_setting('br_telegram_settings_group', 'br_telegram_settings', 'br_telegram_settings_sanitize');
}
add_action( 'admin_init', 'br_register_all_settings' );

// ... (Callbacks remain the same) ...
function br_telegram_settings_sanitize($input) {
    $output = [];
    $output['enabled'] = isset($input['enabled']) ? 1 : 0;
    $output['bot_token'] = sanitize_text_field($input['bot_token']);
    $output['chat_id'] = sanitize_text_field($input['chat_id']);
    $output['metrics'] = isset($input['metrics']) && is_array($input['metrics']) ? array_map('sanitize_key', $input['metrics']) : [];
    return $output;
}

function br_order_status_section_callback() {
    echo '<p class="settings-section-description">' . __( 'Select which WooCommerce order statuses should be counted as a "Converted" order in your reports.', 'business-report' ) . '</p>';
}

function br_order_statuses_field_html() {
    $saved_statuses = get_option( 'br_converted_order_statuses', ['completed'] );
    if ( ! is_array( $saved_statuses ) ) { $saved_statuses = ['completed']; }
    $wc_statuses = wc_get_order_statuses();
    ?>
    <select id="br_converted_order_statuses" name="br_converted_order_statuses[]" multiple="multiple" class="wc-enhanced-select" style="min-width: 300px;" data-placeholder="<?php _e( 'Select statuses...', 'business-report' ); ?>">
        <?php
        foreach ( $wc_statuses as $key => $label ) {
            $status_key = str_replace( 'wc-', '', $key );
            $selected = in_array( $status_key, $saved_statuses ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $status_key ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        ?>
    </select>
    <p class="description"><?php _e( 'Hold CTRL (or CMD on Mac) to select multiple statuses.', 'business-report' ); ?></p>
    <?php wp_enqueue_script( 'wc-enhanced-select' );
}

function br_courier_status_section_callback() {
    echo '<p class="settings-section-description">' . __( 'Define the order statuses that determine the courier-related metrics (Delivered, Returned, Partially Returned) and costs.', 'business-report' ) . '</p>';
}

function br_courier_status_select_html($option_name) {
    $saved_statuses = get_option($option_name, []); 
    if ( ! is_array( $saved_statuses ) ) { $saved_statuses = []; }
    $wc_statuses = wc_get_order_statuses();
    ?>
    <select id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>[]" multiple="multiple" class="wc-enhanced-select" style="min-width: 300px;" data-placeholder="<?php _e( 'Select statuses...', 'business-report' ); ?>">
        <?php
        foreach ( $wc_statuses as $key => $label ) {
            $status_key = str_replace( 'wc-', '', $key );
            $selected = in_array( $status_key, $saved_statuses ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $status_key ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        ?>
    </select>
    <p class="description"><?php _e( 'Hold CTRL (or CMD on Mac) to select multiple statuses.', 'business-report' ); ?></p>
    <?php wp_enqueue_script( 'wc-enhanced-select' );
}

function br_courier_delivered_statuses_html() { br_courier_status_select_html('br_courier_delivered_statuses'); }
function br_courier_returned_statuses_html() { br_courier_status_select_html('br_courier_returned_statuses'); }
function br_courier_partial_returned_statuses_html() { br_courier_status_select_html('br_courier_partial_returned_statuses'); }
function br_courier_return_cost_html() {
    $cost = get_option('br_courier_return_cost', '');
    echo '<input type="number" step="0.01" name="br_courier_return_cost" value="' . esc_attr($cost) . '" placeholder="0.00" /> ';
    echo '<p class="description">' . __('Amount charged by courier for every returned order.', 'business-report') . '</p>';
}