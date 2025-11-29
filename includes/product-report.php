<?php
/**
 * Product Report Page Functionality for Business Report Plugin
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include the tab files
require_once BR_PLUGIN_DIR . 'includes/tabs/product-summary-tab.php';
require_once BR_PLUGIN_DIR . 'includes/tabs/product-list-tab.php';
require_once BR_PLUGIN_DIR . 'includes/tabs/product-performance-tab.php'; // NEW: Include performance tab

// Include the List Table class for the summary tab
require_once BR_PLUGIN_DIR . 'includes/classes/class-br-product-summary-list-table.php';
// NEW: Include the List Table class for the performance tab
require_once BR_PLUGIN_DIR . 'includes/classes/class-br-product-performance-list-table.php';


/**
 * NEW: Enqueue scripts for the Product Report page.
 */
function br_product_report_admin_enqueue_scripts( $hook ) {
	// Enqueue script only on the product report page
	if ( 'business-report_page_br-product-report' !== $hook ) {
		return;
	}

	// Enqueue datepicker
	wp_enqueue_script( 'jquery-ui-datepicker' );
	wp_enqueue_style( 'wp-jquery-ui-dialog' ); // Styles needed for datepicker

	// Ensure the JS file exists before trying to enqueue
	$js_file_path = plugin_dir_path( __FILE__ ) . '../assets/js/admin-product-report.js';
	if ( file_exists( $js_file_path ) ) {
		$js_version = filemtime( $js_file_path );
		wp_enqueue_script(
			'br-product-report-admin-js',
			plugin_dir_url( __FILE__ ) . '../assets/js/admin-product-report.js',
			[ 'jquery', 'jquery-ui-datepicker' ],
			$js_version,
			true
		);
	} else {
		error_log( "Business Report Error: admin-product-report.js not found at $js_file_path" );
	}
}
add_action( 'admin_enqueue_scripts', 'br_product_report_admin_enqueue_scripts' );


/**
 * Renders the main Product Report page.
 */
function br_product_report_page_html() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// Default to 'summary' tab
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'summary';
	?>
	<div class="wrap br-wrap">
		<div class="br-header">
			<h1><?php _e( 'Product Report', 'business-report' ); ?></h1>
		</div>

		<h2 class="nav-tab-wrapper">
			<a href="?page=br-product-report&tab=summary" class="nav-tab <?php echo $active_tab == 'summary' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Summary', 'business-report' ); ?></a>
			<a href="?page=br-product-report&tab=product_list" class="nav-tab <?php echo $active_tab == 'product_list' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Product List (COGS)', 'business-report' ); ?></a>
			<a href="?page=br-product-report&tab=performance" class="nav-tab <?php echo $active_tab == 'performance' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Product Performance', 'business-report' ); ?></a>
		</h2>
		<div class="br-page-content">
		<?php
		switch ( $active_tab ) {
			case 'summary':
				br_product_summary_tab_html();
				break;
			case 'product_list':
				br_product_list_tab_html();
				break;
			case 'performance':
				br_product_performance_tab_html();
				break;
			default:
				br_product_summary_tab_html();
				break;
		}
		?>
		</div>
	</div>
	<?php
}
