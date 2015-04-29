<?php

class SEC_Report_Filtering extends Group_Buying_Controller {

	public static function init() {
		// Add date filter to reports
		add_action( 'gb_report_view_table_start', array( get_class(), 'date_filter_form_table' ) );
		add_action( 'wp_head', array( get_class(), 'register_scripts' ) );

		// Filter voucher report
		add_filter( 'set_merchant_voucher_report_data_column', array( get_class(), 'voucher_reports_columns' ), 10, 1 );
		add_filter( 'gb_merch_deal_voucher_record_item', array( get_class(), 'voucher_reports_record' ), 10, 4 );

		// Filter merchant_purchase report
		add_filter( 'set_merchant_purchase_report_column', array( get_class(), 'reports_columns' ), 10, 2 );
		add_filter( 'gb_merch_purchase_record_item', array( get_class(), 'reports_record' ), 10, 3 );

		// Filter merchant_purchases report
		add_filter( 'set_merchant_purchases_report_data_column', array( get_class(), 'get_purchase_report_columns' ), 10, 2 );
		add_filter( 'set_merchant_purchases_report_data_records', array( get_class(), 'set_merchant_purchases_report_data_custom' ), 10, 2 );

	}

	/////////////////////
	// Date filtering //
	/////////////////////


	public static function register_scripts() {
		if ( isset( $_GET['report'] ) && 'merchant_purchases' === $_GET['report'] ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'filter_report', GB_TWMP_TOOLS_URL . '/classes/resources/js/filterreport.js' );
			wp_enqueue_style( 'filter_report', GB_TWMP_TOOLS_URL . '/classes/resources/css/filterreport.css' );
		}
	}

	public static function date_filter_form_table() {
		if ( isset( $_GET['report'] ) && 'merchant_purchases' === $_GET['report'] ) {
			include( GB_TWMP_TOOLS_PATH . 'classes/views/date-filter-form.php' );
		}
	}

	//////////////
	// Columns //
	//////////////

	/**
	 * Add the report coloumns.
	 *
	 * @param array
	 * @return null
	 */
	public function voucher_reports_columns( $columns ) {
		// Add as many as you want with their own key that will be used later.
		$columns['order_status'] = self::__( 'Orders Status' );
		$columns['paid_with_credit'] = self::__( 'Paid with credit' );
		$columns['mobile'] = self::__( 'Mobile' );
		return $columns;
	}

	/**
	 * Add the report record for deal purchase and merchant report.
	 *
	 * @param array
	 * @return null
	 */
	public function voucher_reports_record( $array, $voucher, $purchase, $account ) {
		// paid
		$products = get_post_meta( $purchase->get_ID(), '_products', true );
		$purchase_credit = 0;
		if ( count( $products ) > 0 ) {
			foreach ( $products as $product ) {
				if ( isset( $product['payment_method']['Account Credit (Affiliate)'] ) ) {
					$purchase_credit += $product['payment_method']['Account Credit (Affiliate)'];
				}
			}
		}
		$array['paid_with_credit'] = gb_get_formatted_money( $purchase_credit );

		// mobile
		$array['mobile'] = get_post_meta( $account->get_ID(), '_gb_account_mobile_code', true ) . ' ' . get_post_meta( $account->get_ID(), '_gb_account_mobile', true );

		return $array;
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
		$columns['paid_with_credit'] = self::__( 'Paid with credit' );
		return $columns;
	}

	/**
	 * Add the report record for deal purchase and merchant report.
	 *
	 * @param array
	 * @return null
	 */
	public function reports_record( $array, $purchase, $account ) {
		// check all vouchers for status
		$pending_voucher_ids = array();
		$voucher_ids = $purchase->get_vouchers();
		foreach ( $voucher_ids as $voucher_id ) {
			$voucher = Group_Buying_Voucher::get_instance( $voucher_id );

			// error prevention
			if ( ! is_a( $voucher, 'Group_Buying_Voucher' ) ) {
				continue;
			}

			if ( ! $voucher->is_active() ) {
				$pending_voucher_ids[] = $voucher->get_id();
			}
		}
		$status = self::__( 'Complete' );
		if ( ! empty( $pending_voucher_ids ) ) {
			// overwite the messaging
			$status = self::__( 'Pending Vouchers: #' ) . implode( ', #', $pending_voucher_ids );
		}
		$array['order_status'] = $status;

		// paid
		$products = get_post_meta( $purchase->get_ID(), '_products', true );
		$purchase_credit = 0;
		if ( count( $products ) > 0 ) {
			foreach ( $products as $product ) {
				if ( isset( $product['payment_method']['Account Credit (Affiliate)'] ) ) {
					$purchase_credit += $product['payment_method']['Account Credit (Affiliate)'];
				}
			}
		}
		$array['paid_with_credit'] = gb_get_formatted_money( $purchase_credit );

		return $array;
	}


	public function get_purchase_report_columns($columns){
		$columns = array(
			'date' => self::__( 'Ημερομηνία' ),
			'deal_name' => self::__( 'Deal' ),
			'qty' => self::__( 'Ποσότητα' ),
			'id' => self::__( '# Συναλλαγής' ),
			'subtotal' => self::__( 'Υποσύνολο' ),
			'tax' => self::__( 'Tax' ),
			'shipping' => self::__( 'Shipping' ),
			'total' => self::__( 'Σύνολο' ),
			'paid_with_credit' => self::__( 'Πληρωμή με credits' ),
			'non_paid_amount' => self::__( 'Απλήρωτο ποσό' ),
			'name' => self::__( 'Όνομα' ),
			'order_status' => self::__( 'Orders Status' ),
		);
		$columns = apply_filters( 'set_merchant_voucher_report_data_column_custom', $columns );
		return $columns;
	}

	public function set_merchant_purchases_report_data_custom() {
		$filter = ( isset( $_GET['filter'] ) && in_array( $_GET['filter'], array( 'any', 'publish', 'draft', 'private', 'trash' ) ) ) ? $_GET['filter'] : 'publish';

		$showpage = ( isset( $_GET['showpage'] ) ) ? (int)$_GET['showpage'] : 0 ;
		$args = array(
				'post_type' => Group_Buying_Purchase::POST_TYPE,
				'post__in' => gb_get_merchants_purchase_ids( gb_account_merchant_id() ),
				'post_status' => $filter,
				'posts_per_page' => apply_filters( 'gb_reports_show_records', 100, 'merchant_purchases' ),
				'paged' => $showpage,
				'orderby' => 'date',
				'order' => 'desc',
			);
		add_filter( 'posts_where', array( get_class(), 'filter_where' ) );
		$merch_purchases = new WP_Query( $args );
		remove_filter( 'posts_where', array( get_class(), 'filter_where' ) );

		$gb_report_pages = $merch_purchases->max_num_pages; // set the global for later pagination
		$purchase_array = array();
		if ( $merch_purchases->have_posts() ) {

			while ( $merch_purchases->have_posts() ) : $merch_purchases->the_post();

				$purchase = Group_Buying_Purchase::get_instance( get_the_ID() );
				if ( ! is_a( $purchase, 'Group_Buying_Purchase' ) ) {
					continue;
				}
				$user_id = $purchase->get_user();
				if ( $user_id > 0 ) {
					$user = get_userdata( $user_id );
					$account_id = $purchase->get_account_id();
					$account = Group_Buying_Account::get_instance_by_id( $account_id );
					if ( is_a( $account, 'Group_Buying_Account' ) ) {
						$address = $account->get_address();
						$get_name = $account->get_name();
						$name = ( strlen( $get_name ) <= 1  ) ? get_the_title( $account_id ) : $get_name;
						$email = $user->user_email;
					}
				} else {
					$gift_id = Group_Buying_Gift::get_gift_for_purchase( $purchase->get_ID() );
					$gift = Group_Buying_Gift::get_instance( $gift_id );
					$address = null;
					$name = self::__( 'Unclaimed Gift' );
					$email = $gift->get_recipient();
				}
				$products			= array();
				$deals				= '';
				$quanties			= '';
				$products			= get_post_meta( get_the_ID(), '_products', true );
				$offsite_purcahse	= '';
				$purchase_credit	= 0;
				if ( count( $products ) > 0 ) {

					foreach ( $products as $product ) {
						$deal_name	= get_the_title( $product['deal_id'] );
						$qty		= $product['quantity'];
						$deals		.= "$deal_name <br><br>";
						$quanties	.= "$qty <br><br>";
						$offsite_purcahse	+= $product['payment_method']['Off-site Purchase'];
						if ( isset( $product['payment_method']['Account Credit (Affiliate)'] ) ) {
							$purchase_credit += $product['payment_method']['Account Credit (Affiliate)'];
						}
						if ( isset( $product['payment_method']['Account Credit (Affiliate)'] ) ) {
							$purchase_credit	+= $product['payment_method']['Account Credit (Affiliate)'];
						}
					}
				}

				// check all vouchers for status
				$pending_voucher_ids = array();
				$voucher_ids = $purchase->get_vouchers();
				foreach ( $voucher_ids as $voucher_id ) {
					$voucher = Group_Buying_Voucher::get_instance( $voucher_id );

					// error prevention
					if ( ! is_a( $voucher, 'Group_Buying_Voucher' ) ) {
						continue;
					}

					if ( ! $voucher->is_active() ) {
						$pending_voucher_ids[] = $voucher->get_id();
					}
				}
				$status = self::__( 'Complete' );
				if ( ! empty( $pending_voucher_ids ) ) {
					// overwite the messaging
					$status = self::__( 'Pending Vouchers: #' ) . implode( ', #', $pending_voucher_ids );
				}

				$postdate = get_the_date( 'd-m-Y', get_the_ID() );
				if ( is_a( $account, 'Group_Buying_Account' ) ) {
					$purchase_array[] = apply_filters( 'gb_merch_purchases_record_item_custom', array(
							'date' => $postdate,
							'deal_name' => $deals,
							'qty' => $quanties,
							'id' => $purchase->get_ID(),
							'subtotal' => gb_get_formatted_money( $purchase->get_subtotal() ),
							'tax' => gb_get_formatted_money( $purchase->get_tax_total() ),
							'shipping' => gb_get_formatted_money( $purchase->get_shipping_total() ),
							'total' => gb_get_formatted_money( $purchase->get_total() ),
							'paid_with_credit' => gb_get_formatted_money( $purchase_credit ),
							'non_paid_amount' => gb_get_formatted_money( $offsite_purcahse ),
							'name' => $name,
							'order_status' => $status,
							//'email' => $email,
						), $purchase, $account );
				}
			endwhile;
		}

		$purchase_array = apply_filters( 'set_merchant_voucher_report_data_record_custom', $purchase_array );
		return $purchase_array;
	}

	public static function filter_where( $where = '' ) {
		// posts in the last 30 days
		if ( isset( $_GET['action'] ) && 'filter' === $_GET['action'] ){
			if ( ! empty($_GET['fromdate']) ) {
				$fromdate			= $_GET['fromdate'];
				$fromdate_arr		= explode( '-',$fromdate );
				$fromdate_mktime	= mktime( 0,0,0,$fromdate_arr[1],$fromdate_arr[0],$fromdate_arr[2] );
				$where .= " AND post_date >= '" . date( 'Y-m-d H:i:s', $fromdate_mktime ) . "'";
			}
			if ( ! empty($_GET['todate']) ) {
				$todate			= $_GET['todate'];
				$todate_arr		= explode( '-',$todate );
				$todate_mktime	= mktime( 23,59,59,$todate_arr[1],$todate_arr[0],$todate_arr[2] );
				$where .= " AND post_date <= '" . date( 'Y-m-d H:i:s', $todate_mktime ) . "'";
			}
		}
		return $where;
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
			'label' => self::__( 'TWMP: Report Filters and Customization' ),
			'description' => self::__( 'add/removed info from report.' ),
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