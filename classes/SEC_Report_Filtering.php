<?php

class SEC_Report_Filtering extends Group_Buying_Controller {

	public static function init() {
		add_filter( 'set_merchant_purchases_report_data_column', array( get_class(), 'reports_columns' ), 10, 2 );
		add_filter( 'gb_merch_purchases_record_item', array( get_class(), 'reports_record' ), 10, 2 );

		// Filter custom report
		add_filter( 'set_merchant_voucher_report_data_column_custom', array( get_class(), 'reports_columns' ), 10, 2 );
		add_filter( 'gb_merch_purchases_record_item_custom', array( get_class(), 'reports_record' ), 10, 2 );
	}

	/**
	 * Add the report coloumns.
	 *
	 * @param array
	 * @return null
	 */
	public function reports_columns( $columns ) {
		// Add as many as you want with their own key that will be used later.
		$columns['order_status'] = self::__( 'Orders Status' );
		return $columns;
	}

	/**
	 * Add the report record for deal purchase and merchant report.
	 *
	 * @param array
	 * @return null
	 */
	public function reports_record( $array, $purchase ) {
		// check all vouchers for status
		$pending_voucher_ids = array();
		$voucher_ids = $purchase->get_vouchers();
		foreach ( $voucher_ids as $voucher_id ) {
			$voucher = Group_Buying_Voucher::get_instance( $voucher_id );

			// error prevention
			if ( !is_a( $voucher, 'Group_Buying_Voucher' ) )
				continue;
			
			if ( !$voucher->is_active() ) {
				$pending_voucher_ids[] = $voucher->get_id();
			}
		}
		$status = self::__('Complete');
		if ( !empty( $pending_voucher_ids ) ) {
			// overwite the messaging
			$status = self::__('Pending Vouchers: #') . implode(', #', $pending_voucher_ids );
		}
		$array['order_status'] = $status;
		return $array;
	}


}


// Initiate the add-on
class SEC_Report_Filtering_Addon extends Group_Buying_Controller {

	public static function init() {
		// Hook this plugin into the GBS add-ons controller
		add_filter( 'gb_addons', array( get_class(), 'load_addon' ), 10, 1 );
	}

	public static function load_addon( $addons ) {
		$addons['twmp_report_filtering'] = array(
			'label' => self::__( 'TWMP: Purchase History Report Filters' ),
			'description' => self::__( 'add/removed infor from report.' ),
			'files' => array(
				__FILE__,
			),
			'callbacks' => array(
				array( 'SEC_Report_Filtering', 'init' ),
			),
		);
		return $addons;
	}
}