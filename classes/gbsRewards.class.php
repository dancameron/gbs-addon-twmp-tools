<?php

class GB_Affiliates_Ext {
	const RECORD_TYPE = 'delayed_credit';
	const NEW_REGISTRATION_TIME = 86400;

	/**
	 *  @var GBS_Rewards_Extension
	 */
	private static $instance;

	private function add_addon() {
		add_filter( 'gb_addons', array( $this, 'load_custom_addons' ), 5, 1 ); // Load later than others
	}

	public static function load_custom_addons( $addons ) {
		$addons['affiliate_credit_restrictions_pwc'] = array(
			'label' => __( 'Deal Based Rewards Modification' ),
			'description' => __( 'Only apply credits if the purchase used credits and allow for merchants to edit the credit field. This requires "Deal Based Rewards" to be loaded.' ),
			'files' => array(
				__FILE__,
				dirname( __FILE__ ) . '/library/template-tags.php',
			),
			'callbacks' => array(
				array( __CLASS__, 'add_pwc_hooks' ),
			),
		);
		$addons['affiliate_credit_restrictions_delay'] = array(
			'label' => __( 'Affiliate Credits Restrictions (14-day delay)' ),
			'description' => __( 'Delay all credits 14 days to verify if voucher is active.' ),
			'files' => array(
				__FILE__,
				dirname( __FILE__ ) . '/library/template-tags.php',
			),
			'callbacks' => array(
				array( __CLASS__, 'add_delay_hooks' ),
			),
		);
		return $addons;
	}

	public function add_pwc_hooks() {
		// prevent credits when a purchase uses credits
		add_filter( 'gb_dbr_prevent_credits_from_a_credit_purchase', '__return_true' );

		// Merchant Options
		add_filter( 'gb_deal_submission_fields', array( get_class(), 'filter_deal_submission_fields' ), 10, 1 );
		add_action( 'submit_deal',  array( get_class(), 'submit_deal' ), 10, 1 );
		add_action( 'edit_deal',  array( get_class(), 'submit_deal' ), 10, 1 );
	}

	public function add_delay_hooks() {
		remove_action( 'gb_apply_credits', array( 'Group_Buying_Notifications', 'applied_credits' ), 10, 4 );
		add_action( 'gb_apply_credits', array( get_class(), 'delay_credits' ), 10, 1 );

		add_action( 'gb_cron', array( get_class(), 'find_delayed_credits' ) );
	}

	////////////////
	// Overriding //
	////////////////


	public static function delay_credits( Group_Buying_Account $affiliate_account, Group_Buying_Payment $payment, $applied_credits, $credit_type ) {

		// Start off by removing the credit that was just applied.
		$affiliate_account->deduct_credit( $applied_credits, $credit_type );

		// Create a record of the delayed credit
		$data = array(
			'account_id' => $affiliate_account->get_id(),
			'payment_id' => $payment->get_id(),
			'credits' => $applied_credits,
			'credit_type' => $credit_type,
			'current_time' => current_time('timestamp')
		);
		Group_Buying_Records::new_record( '', self::RECORD_TYPE, 'Delayed Credit: #'.$data['account_id'], $data['account_id'], $data['account_id'], $data );

	}

	public function find_delayed_credits() {

		// filter to get 14+ day old records
		add_filter( 'posts_where', array( get_class(), 'filter_where' ) );
		$record_ids = Group_Buying_Post_Type::find_by_meta( Group_Buying_Record::POST_TYPE, array( '_type' => self::RECORD_TYPE ) );
		remove_filter( 'posts_where', array( get_class(), 'filter_where' ) );
		// Remove filter

		// Loop through all the records and attempt to apply credits, or delete.
		foreach ( $record_ids as $record_id ) {
			$record = Group_Buying_Record::get_instance( $record_id );
			$data = $record->get_data();
			$affiliate_account = Group_Buying_Account::get_instance( $data['account_id'] );
			$payment = Group_Buying_Payment::get_instance( $data['payment_id'] );
			$applied = self::maybe_apply_credit( $affiliate_account, $payment, $data['credits'], $data['credit_type'], $data['current_time'] );
			if ( !$applied ) { // If not applied the credit is no longer valid.
				wp_delete_post( $record_id, TRUE );
			}
		}
	}

	public function maybe_apply_credit( Group_Buying_Account $affiliate_account, Group_Buying_Payment $payment, $applied_credits, $credit_type, $set_current_time ) {
		$purchase_id = $payment->get_purchase();

		// Loop through all the vouchers associated with the payment and tally up the credits that apply.
		$credit = 0;
		$vouchers = Group_Buying_Post_Type::find_by_meta( self::POST_TYPE, array( '_purchase_id' => $purchase_id ) );
		foreach ( $vouchers as $voucher_id ) {
			$voucher = Group_Buying_Voucher::get_instance( $voucher_id );
			if ( $voucher->is_active() ) { // Check to make sure the voucher is active
				if ( class_exists('Group_Buying_Deal_Rewards') ) {
					$deal_id = $voucher->get_deal_id();
					$account_id = $affiliate_account->get_id();
					// apply the credits but base it off Group_Buying_Deal_Rewards::get_product_credits again.
					$credit += Group_Buying_Deal_Rewards::get_product_credits( $deal_id, $account_id, $affiliate_account, $set_current_time );
				} else { // fallback in case Deal Based Rewards is not active.
					$credit = $applied_credits; // this will be set multiple times but be the same.
					if ( GBS_DEV ) error_log( "deal based rewards class not found: " . print_r( $applied_credits, true ) );
				}
			}
		}
		// If we have credits apply them, fire an action and send the notification
		if ( $credit ) {
			$affiliate_account->add_credit( $credit, $credit_type );
			do_action( 'gb_apply_credits_with_reg_restriction', $affiliate_account, $payment, $credit, $credit_type );
			// Fire off the notification manually
			Group_Buying_Notifications::applied_credits( $affiliate_account, $payment, $credit, $credit_type );
			return $credit;
		}
		return;
		
	}
	
	public function filter_where( $where = '' ) {
		// posts 14+ old
		$where .= " AND post_date <= '" . date('Y-m-d', strtotime('-14 days')) . "'";
		return $where;
	}

	/**
	 * Filter the merchant submission page
	 *
	 * @param array   $fields
	 * @return array
	 */
	public function filter_deal_submission_fields( $fields ) {
		global $wp; // get_query_var won't work at this point
		$default = '';
		if ( isset( $wp->query_vars[Group_Buying_Deals_Edit::EDIT_DEAL_QUERY_VAR] ) ) {
			$deal_id = $wp->query_vars[Group_Buying_Deals_Edit::EDIT_DEAL_QUERY_VAR];
			$deal = Group_Buying_Deal::get_instance( $deal_id );
			$default = Group_Buying_Deal_Reward::get_credits( $deal );
		}
		$fields['dbr_credit'] = array(
			'label' => gb__( 'Credits' ),
			'weight' => 17,
			'type' => 'text',
			'default' => $default,
			'required' => FALSE,
			'description' => gb__( 'How many credits should the affiliate get.' )
		);
		return $fields;
	}

	/**
	 * Save the credit field after the submission
	 *
	 * @param Group_Buying_Deal $deal
	 * @return
	 */
	public function submit_deal( Group_Buying_Deal $deal ) {
		$credit = isset( $_POST['gb_deal_dbr_credit'] ) ? $_POST['gb_deal_dbr_credit'] : '';
		Group_Buying_Deal_Reward::set_credits( $deal, $credit );
	}

	/********** Singleton *************/

	/**
	 * Create the instance of the class
	 *
	 * @static
	 * @return void
	 */
	public static function init() {
		self::$instance = self::get_instance();
	}

	/**
	 * Get (and instantiate, if necessary) the instance of the class
	 *
	 * @static
	 * @return GBS_Rewards_Extension
	 */
	public static function get_instance() {
		if ( !is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	final public function __clone() {
		trigger_error( "Singleton. No cloning allowed!", E_USER_ERROR );
	}

	final public function __wakeup() {
		trigger_error( "Singleton. No serialization allowed!", E_USER_ERROR );
	}

	protected function __construct() {
		//$this->add_hooks();
		$this->add_addon();
	}
}
