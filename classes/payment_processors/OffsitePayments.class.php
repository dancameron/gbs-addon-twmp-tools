<?php

class Group_Buying_Offsite_Manual_Purchasing_Custom extends Group_Buying_Offsite_Processors {

	const LOGO_OPTION = 'gb_offsite_purchasing_logo';
	const PAYMENT_METHOD = 'Off-site Purchase';
	protected static $instance;
	private static $logo;

	public static function get_instance() {
		if ( !( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		parent::__construct();
		self::$logo = get_option( self::LOGO_OPTION );

		add_action( 'admin_init', array( $this, 'register_settings' ), 10, 0 );

		add_filter( 'gb_checkout_payment_controls', array( $this, 'payment_controls' ), 20, 2 );

		// Prevent vouchers from being activated if the payment is mixed with offsite payments.
		remove_action( 'payment_captured', array( 'Group_Buying_Vouchers', 'activate_vouchers' ) );
		add_action( 'payment_captured', array( get_class(), 'activate_vouchers' ), 10, 2 );
	}

	public function reports() {
		// Reports
		add_filter( 'set_merchant_purchase_report_column', array( get_class(), 'set_purchase_report_data_column' ), 10, 1 );
		add_filter( 'set_merchant_purchase_report_records', array( get_class(), 'set_purchase_report_data_records' ), 10, 1 );
		add_filter( 'set_merchant_voucher_report_data_column', array( get_class(), 'set_purchase_report_data_column' ), 10, 1 );
		add_filter( 'set_merchant_voucher_report_data_records', array( get_class(), 'set_purchase_report_data_records' ), 10, 1 );
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, self::__( 'Custom Off-site Purchasing' ) );
	}


	public function get_payment_method() {
		return self::__( self::PAYMENT_METHOD );
	}

	/**
	 * Process a payment
	 *
	 * @param Group_Buying_Checkouts $checkout
	 * @param Group_Buying_Purchase $purchase
	 * @return Group_Buying_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( Group_Buying_Checkouts $checkout, Group_Buying_Purchase $purchase ) {
		if ( $purchase->get_total( self::get_payment_method() ) < 0.01 ) {
			// Nothing to do here, another payment handler intercepted and took care of everything
			// See if we can get that payment and just return it
			$payments = Group_Buying_Payment::get_payments_for_purchase( $purchase->get_id() );
			foreach ( $payments as $payment_id ) {
				$payment = Group_Buying_Payment::get_instance( $payment_id );
				return $payment;
			}
		}

		// create loop of deals for the payment post
		$deal_info = array();
		foreach ( $purchase->get_products() as $item ) {
			if ( isset( $item['payment_method'][self::get_payment_method()] ) ) {
				if ( !isset( $deal_info[$item['deal_id']] ) ) {
					$deal_info[$item['deal_id']] = array();
				}
				$deal_info[$item['deal_id']][] = $item;
			}
		}
		// create new payment
		$payment_id = Group_Buying_Payment::new_payment( array(
				'payment_method' => self::get_payment_method(),
				'purchase' => $purchase->get_id(),
				'amount' => $purchase->get_total( $this->get_payment_method() ),
				'data' => array(
					'api_response' => self::__( 'Offsite Purchase' ),
					'uncaptured_deals' => $deal_info
				),
				// 'transaction_id' => $response[], // TODO set the transaction ID
				'deals' => $deal_info,
			), Group_Buying_Payment::STATUS_PENDING );
		if ( !$payment_id ) {
			return FALSE;
		}
		$payment = Group_Buying_Payment::get_instance( $payment_id );
		do_action( 'payment_pending', $payment );
		do_action( 'offsite_payment_pending', $payment );
		// apply credits since offsite payments are never marked complete
		Group_Buying_Affiliates::apply_credits( $payment );
		return $payment;
	}

	public function register_settings() {
		$page = Group_Buying_Payment_Processors::get_settings_page();
		$section = 'gb_offsite_settings';
		add_settings_section( $section, self::__( 'Offsite Purchasing' ), array( $this, 'display_settings_section' ), $page );
		register_setting( $page, self::LOGO_OPTION );
		add_settings_field( self::LOGO_OPTION, self::__( 'Checkout Button Image' ), array( get_class(), 'display_logo_field' ), $page, $section );
	}

	public function display_logo_field() {
		echo '<input type="text" name="'.self::LOGO_OPTION.'" value="'.self::$logo.'" />';
		echo '<br/><small>Image should be 150px wide and 50px high</small>';
	}

	public function payment_controls( $controls, Group_Buying_Checkouts $checkout ) {
		if ( isset( $controls['review'] ) ) {
			if ( !empty( self::$logo ) && self::$logo != '' ) {
				$style = 'style="box-shadow: none;-moz-box-shadow: none;-webkit-box-shadow: none; display: block; width: 150px; height: 50px; background-color: transparent; background-image: url('.self::$logo.'); background-position: 0 0; padding: 50px 0 0 0; border: none; cursor: pointer; text-indent: -9000px; margin-top: 12px;"';
			}
			$controls['review'] = str_replace( 'value="'.self::__( 'Review' ).'"', $style . ' value="'.self::__( 'Offsite Purchase' ).'"', $controls['review'] );
		}
		return $controls;
	}

	/**
	 * Activate any pending vouchers if the purchased deal is now successful
	 *
	 * @static
	 * @param Group_Buying_Purchase $purchase
	 * @return void
	 */
	public static function activate_vouchers( Group_Buying_Payment $payment, $items_captured ) {
		$purchase_id = $payment->get_purchase();
		$purchase = Group_Buying_Purchase::get_instance( $purchase_id );
		$products = $purchase->get_products();
		foreach ( $products as $product ) {
			if ( isset( $product['payment_method'][self::__(self::PAYMENT_METHOD)] ) ) { // Don't activate vouchers automatically
				continue;
			}
			if ( in_array( $product['deal_id'], $items_captured ) ) {
				$deal = Group_Buying_Deal::get_instance( $product['deal_id'] );
				if ( $deal->is_successful() ) {
					$vouchers = Group_Buying_Voucher::get_pending_vouchers( $product['deal_id'], $purchase_id ); // Added purchase id 4.3.x so that only the purchased vouchers are activated.
					foreach ( $vouchers as $voucher_id ) {
						$voucher = Group_Buying_Voucher::get_instance( $voucher_id );
						$voucher->activate();
					}
				}
			}
		}
	}

	/////////////
	// Reports //
	/////////////

	public static function set_purchase_report_data_column( $columns ) {
		$columns['payment_pending'] = gb__( 'Order Total Pending' );
		return $columns;
	}

	public static function set_purchase_report_data_records( $array ) {
		if ( !is_array( $array ) ) {
			return; // nothing to do.
		}
		$new_array = array();
		foreach ( $array as $records ) {
			$total = 0;
			$purchase = Group_Buying_Purchase::get_instance( $records['id']);
			foreach ( $purchase->get_products() as $item  ) {
				if ( isset( $item['payment_method'][self::__(self::PAYMENT_METHOD)] ) ) {
					$total += $item['payment_method'][self::__(self::PAYMENT_METHOD)];
				}
			}
			$pending = array( 'payment_pending' => gb_get_formatted_money( $total ) );
			$new_array[] = array_merge( $records, $pending );
		}
		return $new_array;
	}
}
Group_Buying_Offsite_Manual_Purchasing_Custom::reports();