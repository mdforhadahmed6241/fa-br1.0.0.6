<?php
/**
 * Product Report - Product Performance Tab
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the HTML for the custom date range modal.
 */
function br_product_report_custom_range_filter_modal_html() {
	$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
	$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'summary';
	?>
	<div id="br-product-custom-range-filter-modal" class="br-modal" style="display: none;">
		<div class="br-modal-content">
			<button class="br-modal-close">&times;</button>
			<h3><?php _e( 'Select Custom Date Range', 'business-report' ); ?></h3>
			<p><?php _e( 'Filter the report by a specific date range.', 'business-report' ); ?></p>
			<form id="br-product-custom-range-filter-form" method="GET">
				<input type="hidden" name="page" value="br-product-report">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>">
				<div class="form-row">
					<div>
						<label for="br_product_filter_start_date"><?php _e( 'Start Date', 'business-report' ); ?></label>
						<input type="text" id="br_product_filter_start_date" name="start_date" class="br-datepicker" value="<?php echo esc_attr( $start_date ); ?>" autocomplete="off" required>
					</div>
					<div>
						<label for="br_product_filter_end_date"><?php _e( 'End Date', 'business-report' ); ?></label>
						<input type="text" id="br_product_filter_end_date" name="end_date" class="br-datepicker" value="<?php echo esc_attr( $end_date ); ?>" autocomplete="off" required>
					</div>
				</div>
				<div class="form-footer">
					<div></div>
					<div>
						<button type="button" class="button br-modal-cancel"><?php _e( 'Cancel', 'business-report' ); ?></button>
						<button type="submit" class="button button-primary"><?php _e( 'Apply Filter', 'business-report' ); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
	<?php
}


/**
 * Renders the date filter buttons and dropdown.
 * UPDATED: Now includes search box
 */
function br_product_report_render_date_filters_html( $current_tab = 'summary', $list_table_object = null ) {
	$current_range_key = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'today';
	$start_date_get    = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : null;
	$end_date_get      = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : null;
	$is_custom_range   = ! empty( $start_date_get ) && ! empty( $end_date_get );

	$filters_main     = [
		'today'       => 'Today',
		'yesterday'   => 'Yesterday',
		'last_7_days' => '7D',
		'last_30_days' => '30D',
	];
	$filters_dropdown = [
		'this_month' => 'This Month',
		'this_year'  => 'This Year',
		'lifetime'   => 'Lifetime',
		'custom'     => 'Custom Range',
	];
	?>
	<div class="br-filters br-performance-filters">
		<div class="br-date-filters">
			<?php
			foreach ( $filters_main as $key => $label ) {
				$is_active = ( $current_range_key === $key ) && ! $is_custom_range;

				// Set 'Today' as default if no range is set
				if ( ! isset( $_GET['range'] ) && ! $is_custom_range && $key === 'today' ) {
					$is_active = true;
				}

				echo sprintf( '<a href="?page=br-product-report&tab=%s&range=%s" class="button %s">%s</a>', esc_attr( $current_tab ), esc_attr( $key ), $is_active ? 'active' : '', esc_html( $label ) );
			}
			?>
			<div class="br-dropdown">
				<button class="button br-dropdown-toggle <?php echo ( $is_custom_range || in_array( $current_range_key, array_keys( $filters_dropdown ) ) ) ? 'active' : ''; ?>">...</button>
				<div class="br-dropdown-menu">
					<?php
					foreach ( $filters_dropdown as $key => $label ) {
						if ( $key === 'custom' ) {
							echo sprintf( '<a href="#" id="br-product-custom-range-trigger">%s</a>', esc_html( $label ) );
						} else {
							echo sprintf( '<a href="?page=br-product-report&tab=%s&range=%s">%s</a>', esc_attr( $current_tab ), esc_attr( $key ), esc_html( $label ) );
						}
					}
					?>
				</div>
			</div>
		</div>

		<?php
		// Add search box to the right side
		if ( $list_table_object ) {
			$list_table_object->search_box( __( 'Search Products', 'business-report' ), 'product' );
		}
		?>
	</div>
	<?php
}

/**
 * Renders the HTML for the Product Performance tab.
 */
function br_product_performance_tab_html() {
	$performance_list_table = new BR_Product_Performance_List_Table();
	$performance_list_table->prepare_items();

	?>
	
	<!-- 
	  FIX: This form now wraps ALL filters (date, search, category)
	  to ensure all parameters are submitted together.
	-->
	<form id="br-product-performance-form" method="get">
		<!-- Hidden fields must be inside the form -->
		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
		<input type="hidden" name="tab" value="performance">
		<?php
		// Add hidden fields to preserve date range on search/filter
		$current_range_key = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'today';
		$start_date_get    = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
		$end_date_get      = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';
		?>
		<input type="hidden" name="range" value="<?php echo esc_attr( $current_range_key ); ?>">
		<input type="hidden" name="start_date" value="<?php echo esc_attr( $start_date_get ); ?>">
		<input type="hidden" name="end_date" value="<?php echo esc_attr( $end_date_get ); ?>">

		<?php
		// Render date filters and search box (now inside the form)
		br_product_report_render_date_filters_html( 'performance', $performance_list_table );
		
		// Display the table. This will also render the category/brand filters (extra_tablenav)
		$performance_list_table->display();
		?>
	</form>
	
	<?php
	// Render the modal for custom date range
	br_product_report_custom_range_filter_modal_html();
}

