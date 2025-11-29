<?php
/**
 * Plugin Name:       Business Report
 * Plugin URI:        https://example.com/
 * Description:       A comprehensive reporting tool for WooCommerce.
 * Version:           1.8.0
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       business-report
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Add initial log to confirm file loading
error_log("BR Log: business-report.php main file started loading.");

// Version bumped for License Enforced feature
define( 'BR_PLUGIN_VERSION', '1.8.0' );
define( 'BR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // Define constants early
define( 'BR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class.
 */
final class Business_Report {

	private static $instance;

	public static function instance() {
		error_log("BR Log: Business_Report::instance() called."); // Log instance creation
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Business_Report ) ) {
			self::$instance = new Business_Report();
			self::$instance->includes(); // Include files early
			self::$instance->hooks();
			error_log("BR Log: Business_Report instance created and hooks initialized."); // Log successful init
		} else {
            error_log("BR Log: Business_Report instance already exists.");
        }
		return self::$instance;
	}

	private function includes() {
        error_log("BR Log: includes() method started.");
        $required_files = [
            'includes/settings-page.php',
            'includes/cogs-functions.php', // Core COGS functions
            'includes/meta-ads.php',
            'includes/expense-management.php',
            'includes/order-report.php',
            'includes/product-report.php', // Main product report page handler
            'includes/customer-report.php', // Customer Report Module
            'includes/telegram-notifications.php', // Telegram Module
            'includes/core-auth.php', // NEW: Obscured License Manager
            'includes/classes/class-br-product-summary-list-table.php',
            'includes/dashboard.php', // Dashboard Logic
        ];
        foreach ($required_files as $file) {
            $path = BR_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
                error_log("BR Log: Successfully included {$file}");
            } else {
                error_log("BR Log Error: Failed to include {$file} - File not found at {$path}");
            }
        }
        error_log("BR Log: includes() method finished.");
	}


	private function hooks() {
        error_log("BR Log: hooks() method started.");
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
        add_action( 'admin_init', [ $this, 'check_for_updates' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles_and_scripts' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'remove_admin_notices' ] );
		
        // Action Scheduler Hooks
        add_action( 'br_monthly_expense_daily_as', [ $this, 'execute_monthly_expense_cron' ] );
        add_action( 'br_meta_ads_daily_sync_as', [ $this, 'execute_meta_ads_sync_cron' ] );
        add_action( 'br_telegram_daily_report_as', [ $this, 'execute_telegram_report_cron' ] );

        // Init to schedule Action Scheduler events
        add_action( 'init', [ $this, 'schedule_action_scheduler_events' ] );

		// --- ORDER REPORT HOOKS ---
        add_action( 'save_post_shop_order', 'br_save_or_update_order_report_data_on_save', 40, 3 );
        add_action( 'oms_order_created', 'br_save_or_update_order_report_data', 10, 1 );
		add_action( 'woocommerce_order_status_changed', 'br_order_status_changed_update_report', 40, 4 );
		// --- END ORDER REPORT HOOKS ---
         error_log("BR Log: hooks() method finished.");
	}

    /**
     * Runs on plugin activation ONLY.
     */
    public function activate() {
        error_log("BR Log: Activation hook fired."); // Log activation
        $this->run_db_install(); // Create/update tables using dbDelta
        $this->force_add_missing_columns(); // Force add columns if needed
        
        // Set default options if they don't exist
        if ( get_option( 'br_converted_order_statuses', false ) === false ) {
			add_option( 'br_converted_order_statuses', ['completed'] );
            error_log("BR Log: Default converted statuses set."); // Log option set
		}
        // Set current version on activation
        update_option( 'br_plugin_version', BR_PLUGIN_VERSION );
        error_log("BR Log: Plugin version updated to " . BR_PLUGIN_VERSION); // Log version update
        flush_rewrite_rules(); // Good practice on activation
    }

    /**
     * Remove non-plugin admin notices from plugin pages.
     */
    public function remove_admin_notices() {
        // Only run if page is set and we are in admin area
        if ( ! is_admin() || ! isset( $_GET['page'] ) ) return;

        $page = sanitize_key($_GET['page']);
        if ( strpos( $page, 'br-' ) === 0 || $page === 'business-report' ) {
            // Use a hook that runs later to remove notices effectively
            add_action('in_admin_header', function() {
                 remove_all_actions( 'admin_notices' );
                 remove_all_actions( 'all_admin_notices' );
                 error_log("BR Log: Removed admin notices for page: " . sanitize_key($_GET['page']));
            }, 1000); // High priority
        }
    }


	public function admin_menu() {
        error_log("BR Log: admin_menu hook fired. Registering menus."); // Log menu registration
		
        // Check License
        $is_licensed = function_exists('br_is_license_active') && br_is_license_active();

        $main_page_hook = add_menu_page(
            __( 'Business Report', 'business-report' ),
            __( 'Business Report', 'business-report' ),
            'manage_woocommerce', // Capability check
            'business-report', // Menu slug
            $is_licensed ? 'br_dashboard_page_html' : 'br_settings_page_html', // Redirect to settings if no license
            'dashicons-chart-bar', // Icon
            56 // Position
        );

        // Check if main page was added successfully before adding submenus
        if ($main_page_hook) {
            
            // --- RESTRICTED PAGES: Only added if license is valid ---
            if ( $is_licensed ) {
                add_submenu_page( 'business-report', __( 'Order Report', 'business-report' ), __( 'Order Report', 'business-report' ), 'manage_woocommerce', 'br-order-report', 'br_order_report_page_html' );
                add_submenu_page( 'business-report', __( 'Customer Report', 'business-report' ), __( 'Customer Report', 'business-report' ), 'manage_woocommerce', 'br-customer-report', 'br_customer_report_page_html' );
                add_submenu_page( 'business-report', __( 'Product Report', 'business-report' ), __( 'Product Report', 'business-report' ), 'manage_woocommerce', 'br-product-report', 'br_product_report_page_html' );
                add_submenu_page( 'business-report', __( 'Expense', 'business-report' ), __( 'Expense', 'business-report' ), 'manage_woocommerce', 'br-expense', 'br_expense_page_html' );
                add_submenu_page( 'business-report', __( 'Meta Ads', 'business-report' ), __( 'Meta Ads', 'business-report' ), 'manage_woocommerce', 'br-meta-ads', 'br_meta_ads_page_html' );
            }
            
            // --- ALWAYS VISIBLE ---
            add_submenu_page( 'business-report', __( 'Settings', 'business-report' ), __( 'Settings', 'business-report' ), 'manage_options', 'br-settings', 'br_settings_page_html' );
            
            error_log("BR Log: Submenus registered. Licensed: " . ($is_licensed ? 'YES' : 'NO'));
        } else {
             error_log("BR Log Error: Failed to add main menu page 'business-report'. Submenus not added.");
        }
	}

	/**
     * Enqueue styles and scripts based on the current admin page.
     */
	public function enqueue_styles_and_scripts( $hook ) {
        // Check if page is set before accessing $_GET['page']
        if ( ! is_admin() || ! isset( $_GET['page'] ) ) return;

        $page = sanitize_key($_GET['page']);

        // Only enqueue on plugin pages
        if (strpos($page, 'business-report') !== 0 && strpos($page, 'br-') !== 0) {
             return;
        }

        error_log("BR Log: Enqueueing styles for hook: {$hook}, page: {$page}");

        // --- Enqueue Global Styles ---
        $global_css_path = BR_PLUGIN_DIR . 'assets/css/admin-global.css';
        if ( file_exists( $global_css_path ) ) {
            $global_css_version = filemtime( $global_css_path );
            wp_enqueue_style( 'br-admin-global-styles', BR_PLUGIN_URL . 'assets/css/admin-global.css', [], $global_css_version );
            error_log("BR Log: Enqueued admin-global.css version {$global_css_version}");
        } else {
             error_log("BR Log Error: admin-global.css not found at {$global_css_path}");
        }

        // --- Conditionally Enqueue Page-Specific Styles ---
        $page_specific_css = '';
        switch ( $page ) {
            case 'business-report': // Main Dashboard Page
                wp_enqueue_style( 'br-dashboard-css', BR_PLUGIN_URL . 'assets/css/admin-dashboard.css', [], time() );
                // Enqueue Chart.js for dashboard charts
                wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true );
                wp_enqueue_script( 'br-dashboard-js', BR_PLUGIN_URL . 'assets/js/admin-dashboard.js', ['jquery', 'chart-js'], time(), true );
                break;
            case 'br-order-report':
                $page_specific_css = 'admin-order-report.css';
                break;
            case 'br-customer-report': // New Customer Report Style if needed (reusing global/dashboard mostly)
                break;
            case 'br-product-report':
                $page_specific_css = 'admin-product-report.css';
                break;
            case 'br-expense':
                $page_specific_css = 'admin-expense.css';
                break;
            case 'br-meta-ads':
                $page_specific_css = 'admin-meta-ads.css';
                break;
             case 'br-settings':
                $page_specific_css = 'admin-settings.css';
                // Enqueue Meta Ads CSS for Settings page
                $meta_css_path = BR_PLUGIN_DIR . 'assets/css/admin-meta-ads.css';
                if ( file_exists( $meta_css_path ) ) {
                    wp_enqueue_style( 'br-admin-meta-ads-styles', BR_PLUGIN_URL . 'assets/css/admin-meta-ads.css', ['br-admin-global-styles'], filemtime( $meta_css_path ) );
                }
                // Load script for Settings (for Telegram Test button)
                wp_enqueue_script('br-admin-settings-js', BR_PLUGIN_URL . 'assets/js/admin-settings.js', ['jquery'], time(), true);
                wp_localize_script('br-admin-settings-js', 'br_settings_ajax', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('br_settings_nonce'),
                ]);
                break;
        }

        if ( ! empty( $page_specific_css ) ) {
            $page_css_path = BR_PLUGIN_DIR . 'assets/css/' . $page_specific_css;
            if ( file_exists( $page_css_path ) ) {
                $page_css_version = filemtime( $page_css_path );
                $handle = 'br-' . str_replace( '.css', '', $page_specific_css ) . '-styles'; // e.g., br-admin-order-report-styles
                wp_enqueue_style( $handle, BR_PLUGIN_URL . 'assets/css/' . $page_specific_css, ['br-admin-global-styles'], $page_css_version );
                error_log("BR Log: Enqueued {$page_specific_css} version {$page_css_version}");
            } else {
                error_log("BR Log Error: {$page_specific_css} not found at {$page_css_path}");
            }
        }
	}

    /**
     * Check if plugin version changed and run updates.
     */
    public function check_for_updates() {
        $installed_version = get_option( 'br_plugin_version', '0.0.0' );
        if ( version_compare($installed_version, BR_PLUGIN_VERSION, '<') ) {
             error_log("BR Log: Updating plugin from version {$installed_version} to " . BR_PLUGIN_VERSION);
             $this->run_db_install(); // Run dbDelta first
             $this->force_add_missing_columns(); // Then force add columns
             
             // Cleanup old WP Cron hook
             wp_clear_scheduled_hook( 'br_daily_add_monthly_expenses_event' );
             error_log("BR Log: Cleared old WP cron hook 'br_daily_add_monthly_expenses_event'.");

             update_option( 'br_plugin_version', BR_PLUGIN_VERSION );
             error_log("BR Log: Update complete. New version: " . BR_PLUGIN_VERSION);
        }
    }

    /**
     * Force add missing columns using ALTER TABLE if dbDelta failed.
     */
    private function force_add_missing_columns() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'br_orders';
        $column_name = 'shipping_cost';
        $full_table_name = $wpdb->prefix . 'br_orders';

        // Check if table exists
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) != $full_table_name) {
            return;
        }

        // Check if the shipping_cost column exists
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $column_exists_result = $wpdb->get_results($wpdb->prepare("DESCRIBE {$full_table_name} %s", $column_name));

        if (empty($column_exists_result)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("ALTER TABLE `{$full_table_name}` ADD COLUMN `{$column_name}` DECIMAL(12,2) DEFAULT 0.00 AFTER `discount`");
            $wpdb->flush(); 
        } 

        // --- Force add courier_status column if it doesn't exist ---
        $courier_column_name = 'courier_status';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $courier_column_exists_result = $wpdb->get_results($wpdb->prepare("DESCRIBE {$full_table_name} %s", $courier_column_name));

        if (empty($courier_column_exists_result)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("ALTER TABLE `{$full_table_name}` ADD COLUMN `{$courier_column_name}` TINYINT(1) DEFAULT NULL AFTER `order_status`");
            $wpdb->flush();
        }
    }


    /**
     * Schedule Action Scheduler Events
     */
    public function schedule_action_scheduler_events() {
        // Requires License
        if ( function_exists('br_is_license_active') && !br_is_license_active() ) {
            return;
        }

        if ( function_exists( 'as_next_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
            
            // 1. Meta Ads Sync (Every 12 hours)
            if ( false === as_next_scheduled_action( 'br_meta_ads_daily_sync_as' ) ) {
                as_schedule_recurring_action( time() + 60, 43200, 'br_meta_ads_daily_sync_as' );
            }

            // 2. Monthly Expenses (Daily at 2:00 AM)
            if ( false === as_next_scheduled_action( 'br_monthly_expense_daily_as' ) ) {
                $next_run = strtotime( '02:00:00' );
                if ( $next_run < time() ) { $next_run += 86400; }
                as_schedule_recurring_action( $next_run, 86400, 'br_monthly_expense_daily_as' );
            }

            // 3. Telegram Report (Daily at 10:00 AM)
            if ( false === as_next_scheduled_action( 'br_telegram_daily_report_as' ) ) {
                $next_run = strtotime( '10:00:00' );
                if ( $next_run < time() ) { $next_run += 86400; }
                as_schedule_recurring_action( $next_run, 86400, 'br_telegram_daily_report_as' );
                error_log("BR Log: Scheduled Action Scheduler event 'br_telegram_daily_report_as' for 10 AM.");
            }
        }
    }

	public function execute_monthly_expense_cron() {
        if ( function_exists('br_add_monthly_expenses_to_list') ) {
            br_add_monthly_expenses_to_list();
        }
    }

    public function execute_meta_ads_sync_cron() {
        if ( function_exists('br_auto_sync_meta_ads_data') ) {
            br_auto_sync_meta_ads_data();
        }
    }

    public function execute_telegram_report_cron() {
        if ( function_exists('br_execute_telegram_daily_report') ) {
            br_execute_telegram_daily_report();
        } else {
            error_log("BR Error: Function br_execute_telegram_daily_report not found.");
        }
    }


	public function run_db_install() {
        error_log("BR Log: Running run_db_install (dbDelta).");
        global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// COGS Table
		$cogs_table_name = $wpdb->prefix . 'br_product_cogs';
		$sql_cogs = "CREATE TABLE $cogs_table_name ( id mediumint(9) NOT NULL AUTO_INCREMENT, post_id bigint(20) UNSIGNED NOT NULL, cost decimal(10,2) NOT NULL DEFAULT '0.00', last_updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY  (id), UNIQUE KEY post_id (post_id) ) $charset_collate;";
		dbDelta( $sql_cogs );

		// Meta Ads Accounts Table
		$accounts_table = $wpdb->prefix . 'br_meta_ad_accounts';
		$sql_accounts = "CREATE TABLE $accounts_table ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, account_name VARCHAR(255) NOT NULL, app_id VARCHAR(255) DEFAULT NULL, app_secret TEXT DEFAULT NULL, access_token TEXT NOT NULL, ad_account_id VARCHAR(255) NOT NULL, usd_to_bdt_rate DECIMAL(10, 4) NOT NULL DEFAULT 0.0000, is_active TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (id) ) $charset_collate;";
		dbDelta($sql_accounts);

		// Meta Ads Summary Table
        $summary_table = $wpdb->prefix . 'br_meta_ad_summary';
        $sql_summary = "CREATE TABLE $summary_table ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, account_fk_id BIGINT(20) UNSIGNED NOT NULL, report_date DATE NOT NULL, spend_usd DECIMAL(12, 2) DEFAULT 0.00, purchases INT(11) UNSIGNED DEFAULT 0, purchase_value DECIMAL(12, 2) DEFAULT 0.00, adds_to_cart INT(11) UNSIGNED DEFAULT 0, initiate_checkouts INT(11) UNSIGNED DEFAULT 0, impressions INT(11) UNSIGNED DEFAULT 0, clicks INT(11) UNSIGNED DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY account_date (account_fk_id, report_date), KEY report_date (report_date) ) $charset_collate;";
		dbDelta($sql_summary);

		// Meta Ads Campaign Table
        $campaign_table = $wpdb->prefix . 'br_meta_campaign_data';
        $sql_campaign = "CREATE TABLE $campaign_table ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, campaign_id VARCHAR(255) NOT NULL, campaign_name TEXT DEFAULT NULL, account_fk_id BIGINT(20) UNSIGNED NOT NULL, report_date DATE NOT NULL, objective VARCHAR(255) DEFAULT NULL, spend_usd DECIMAL(12, 2) DEFAULT 0.00, impressions INT(11) UNSIGNED DEFAULT 0, reach INT(11) UNSIGNED DEFAULT 0, frequency DECIMAL(10, 4) DEFAULT 0.0000, clicks INT(11) UNSIGNED DEFAULT 0, ctr DECIMAL(10, 4) DEFAULT 0.0000, cpc DECIMAL(10, 4) DEFAULT 0.0000, cpm DECIMAL(10, 4) DEFAULT 0.0000, roas DECIMAL(10, 4) DEFAULT 0.0000, purchases INT(11) UNSIGNED DEFAULT 0, adds_to_cart INT(11) UNSIGNED DEFAULT 0, initiate_checkouts INT(11) UNSIGNED DEFAULT 0, purchase_value DECIMAL(12, 2) DEFAULT 0.00, PRIMARY KEY (id), UNIQUE KEY campaign_date (campaign_id, report_date), KEY report_date (report_date), KEY account_fk_id (account_fk_id) ) $charset_collate;";
		dbDelta($sql_campaign);

		// Expense Categories Table
		$expense_cat_table = $wpdb->prefix . 'br_expense_categories';
		$sql_expense_cat = "CREATE TABLE $expense_cat_table ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(191) NOT NULL, PRIMARY KEY (id), UNIQUE KEY name (name) ) $charset_collate;";
		dbDelta($sql_expense_cat);

		$uncategorized_exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $expense_cat_table WHERE name = %s", 'Uncategorized' ) );
		if ( ! $uncategorized_exists ) {
			$wpdb->insert( $expense_cat_table, [ 'name' => 'Uncategorized' ], [ '%s' ] );
		}

		// Expenses Table
		$expenses_table = $wpdb->prefix . 'br_expenses';
		$sql_expenses = "CREATE TABLE $expenses_table ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, reason TEXT DEFAULT NULL, category_id BIGINT(20) UNSIGNED NOT NULL, amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00, expense_date DATE NOT NULL, PRIMARY KEY (id), KEY category_id (category_id), KEY expense_date (expense_date) ) $charset_collate;";
		dbDelta($sql_expenses);

		// Monthly Expenses Table
		$monthly_expenses_table = $wpdb->prefix . 'br_monthly_expenses';
		$sql_monthly_expenses = "CREATE TABLE $monthly_expenses_table ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, reason TEXT DEFAULT NULL, category_id BIGINT(20) UNSIGNED NOT NULL, amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00, listed_date TINYINT UNSIGNED NOT NULL, last_added_month TINYINT UNSIGNED DEFAULT NULL, last_added_year SMALLINT UNSIGNED DEFAULT NULL, PRIMARY KEY (id), KEY category_id (category_id) ) $charset_collate;";
		dbDelta($sql_monthly_expenses);

		// Order Report table (definition including shipping_cost and courier_status)
		$orders_table_name = $wpdb->prefix . 'br_orders';
		$sql_orders = "CREATE TABLE $orders_table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			order_date DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
			customer_id BIGINT UNSIGNED DEFAULT NULL,
			customer_name VARCHAR(255) DEFAULT NULL,
			customer_phone VARCHAR(50) DEFAULT NULL,
			customer_email VARCHAR(191) DEFAULT NULL,
			total_items INT UNSIGNED DEFAULT 0,
			product_ids TEXT DEFAULT NULL,
			variation_ids TEXT DEFAULT NULL,
			category_ids TEXT DEFAULT NULL,
			total_order_value DECIMAL(12,2) DEFAULT 0.00,
			total_value DECIMAL(12,2) DEFAULT 0.00,
			cogs_total DECIMAL(12,2) DEFAULT 0.00,
			discount DECIMAL(12,2) DEFAULT 0.00,
			shipping_cost DECIMAL(12,2) DEFAULT 0.00,
			payment_method VARCHAR(100) DEFAULT NULL,
			order_status VARCHAR(50) DEFAULT NULL,
			is_converted TINYINT(1) DEFAULT 0,
			courier_status TINYINT(1) DEFAULT NULL,
			source VARCHAR(100) DEFAULT NULL,
			gross_profit DECIMAL(12,2) DEFAULT 0.00,
			net_profit DECIMAL(12,2) DEFAULT 0.00,
			profit_margin DECIMAL(6,2) DEFAULT 0.00,
			notes TEXT DEFAULT NULL,
			created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id),
            KEY order_date (order_date),
            KEY is_converted (is_converted),
            KEY courier_status (courier_status),
            KEY customer_phone (customer_phone)
		) $charset_collate;";
		$delta_result = dbDelta($sql_orders);
        error_log("BR Log: dbDelta result for br_orders table: " . print_r($delta_result, true));

	}
}

/**
 * Renders the Dashboard page using the new dashboard.php logic.
 */
function br_dashboard_page_html() {
	if (function_exists('br_render_dashboard_page')) {
        br_render_dashboard_page();
    } else {
        echo '<div class="wrap"><h1>Error</h1><p>Dashboard module not loaded. Please check if includes/dashboard.php exists.</p></div>';
    }
}


/**
 * Begins execution of the plugin. Checks for WooCommerce first.
 */
function business_report_init() {
    error_log("BR Log: business_report_init() called."); // Log initialization start
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e( 'Business Report plugin requires WooCommerce to be installed and active.', 'business-report' ); ?></p>
            </div>
            <?php
        });
         error_log("BR Log Error: WooCommerce class not found. Business Report not initialized."); // Log WC missing
        return; // Stop initialization if WC is not active
    }
    error_log("BR Log: WooCommerce check passed. Initializing Business_Report instance."); // Log WC check passed
	return Business_Report::instance();
}
// Initialize after plugins are loaded, with slightly higher priority to ensure WC is loaded.
add_action( 'plugins_loaded', 'business_report_init', 11 );