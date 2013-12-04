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
		$addons['affiliate_credit_restrictions_merchant_edit'] = array(
			'label' => __( 'TWM Tools: Deal Based Rewards Modification (Merchants)' ),
			'description' => __( 'Allow for merchants to edit the credit field for each deal. This requires "Deal Based Rewards" add-on to be loaded/activated.' ),
			'files' => array(
				__FILE__,
				dirname( __FILE__ ) . '/library/template-tags.php',
			),
			'callbacks' => array(
				array( __CLASS__, 'add_pwc_hooks' ),
			),
		);
		$addons['affiliate_credit_restrictions_percentage'] = array(
			'label' => __( 'TWM Tools: Deal Based Rewards Modification (Purchase Limits)' ),
			'description' => __( 'If both money and credits are used for a purchase then cashback should be earned proportionally for the portion of the purchase that money was used. This requires "Deal Based Rewards" add-on to be loaded/activated.' ),
			'files' => array(
				__FILE__
			),
			'callbacks' => array(
				array( __CLASS__, 'add_calc_restriction_hooks' ),
			),
		);
		$addons['affiliate_credit_restrictions_delay'] = array(
			'label' => __( 'TWM Tools: Affiliate Credits Restrictions (14-day delay)' ),
			'description' => __( 'Delay all credits 15 days to verify if voucher is active. ' ),
			'files' => array(
				__FILE__,
				dirname( __FILE__ ) . '/library/template-tags.php',
			),
			'callbacks' => array(
				array( __CLASS__, 'add_delay_hooks' ),
			),
		);
		$addons['cashback_rewards'] = array(
			'label' => gb__( 'TWM Tools: Cashback Rewards (Advanced with 14-day delay)' ),
			'description' => gb__( 'Set a cashback reward/credit per deal that’s given after purchase. Send notification when reward is applied to the account (Credits Rewarded Notification). If both money and credits are used for a purchase then reward should be earned proportionally for the portion of the purchase that money was used. Reward is not credited to user account until 14 days after purchase and the voucher is activated.' ),
			'files' => array(
				__FILE__,
			),
			'callbacks' => array(
				array( 'Group_Buying_Cashback_Rewards_Adv', 'init' ),
			),
			'active' => TRUE,
		);
		return $addons;
	}

	public function add_pwc_hooks() {
		// Merchant Options
		add_filter( 'gb_deal_submission_fields', array( get_class(), 'filter_deal_submission_fields' ), 10, 1 );
		add_action( 'submit_deal',  array( get_class(), 'submit_deal' ), 10, 1 );
		add_action( 'edit_deal',  array( get_class(), 'submit_deal' ), 10, 1 );
	}

	public function add_calc_restriction_hooks() {
		/**
		 * Deal Based Rewards has these restrictions built in now, otherwise their abstraction would be too complex
		 * and possibly cause serious problems with my health while testing.
		 */
		// prevent credits when a purchase uses credits
		add_filter( 'gb_dbr_prevent_credits_from_a_credit_purchase', '__return_true' );
		// calculate based off the credits used
		add_filter( 'gb_dbr_calculate_credits_based_on_credits_used', '__return_true' );
		// allow for customers to a credit more than once.
		add_filter( 'gb_rewards_get_account_share_records', '__return_empty_array' );
	}

	public function add_delay_hooks() {
		remove_action( 'gb_apply_credits', array( 'Group_Buying_Notifications', 'applied_credits' ) );
		add_action( 'gb_apply_credits', array( get_class(), 'delay_credits' ), 10, 4 );

		add_action( 'init', array( get_class(), 'find_delayed_credits' ) ); // TODO switch
		//add_action( 'gb_cron', array( get_class(), 'find_delayed_credits' ) );
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
		// if ( GBS_DEV ) error_log( "record data: " . print_r( $data, true ) );
		$record_id = Group_Buying_Records::new_record( '', self::RECORD_TYPE, 'Delayed Credit: #'.$data['account_id'], $data['account_id'], $data['account_id'], $data );
		if ( !$record_id ) {
			$records = Group_Buying_Record::get_records_by_type_and_association( $data['account_id'], self::RECORD_TYPE );
			$record_id = max( $records );
		}
		// if ( GBS_DEV ) error_log( "credit ++++++++ record_id: " . print_r( $record_id, true ) );
		do_action( 'delay_credits_function', $record_id, $affiliate_account, $payment, $applied_credits, $credit_type );

	}

	public function find_delayed_credits() {
		// filter to get 14+ day old records
		add_filter( 'posts_where', array( get_class(), 'filter_where' ) );
		if ( defined( Group_Buying_Record::TAXONOMY ) && taxonomy_exists( Group_Buying_Record::TAXONOMY ) ) { // In case the records post type moves to use taxonomies and not meta for types.
			$args = array(
				'post_type' => Group_Buying_Record::POST_TYPE,
				'post_status' => 'pending',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'gb_bypass_filter' => TRUE,
				Group_Buying_Record::TAXONOMY => self::RECORD_TYPE
					 );
		}
		else {
			$args = array(
				'post_type' => Group_Buying_Record::POST_TYPE,
				'post_status' => 'pending',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'gb_bypass_filter' => TRUE,
				'meta_query' => array(
							array( 'key' => '_type', 'value' => self::RECORD_TYPE )
						)
					 );
		}
		$records = new WP_Query($args);
		$record_ids = $records->posts;
		remove_filter( 'posts_where', array( get_class(), 'filter_where' ) ); // Remove filter
		// if ( GBS_DEV ) error_log( "returned records delayed credits: " . print_r( $record_ids, true ) );

		if ( empty( $record_ids ) )
			return;

		// Loop through all the records and attempt to apply credits, or delete.
		foreach ( $record_ids as $record_id ) {
			$record = Group_Buying_Record::get_instance( $record_id );
			// if ( GBS_DEV ) error_log( "credit +++++++ record: " . print_r( $record_id, true ) );
			$data = $record->get_data();
			// if ( GBS_DEV ) error_log( "record data: " . print_r( $data, true ) );
			$affiliate_account = Group_Buying_Account::get_instance_by_id( $data['account_id'] );
			$payment = Group_Buying_Payment::get_instance( $data['payment_id'] );
			if ( !is_a( $payment, 'Group_Buying_Payment' ) ) {
				wp_delete_post( $record_id, TRUE );
				return;
			}
			$applied = self::maybe_apply_credit( $affiliate_account, $payment, $data['credits'], $data['credit_type'], $data['current_time'] );
			// if ( GBS_DEV ) error_log( "applied: " . print_r( $applied, true ) );
			if ( $applied < 0 || $applied > 0 ) { // If applied remove the record, -1 is given if the record should not be checked again.
				wp_delete_post( $record_id, TRUE );
			}
			return;
		}
	}

	public function maybe_apply_credit( Group_Buying_Account $affiliate_account, Group_Buying_Payment $payment, $applied_credits, $credit_type, $set_current_time ) {
		$purchaser_account = $payment->get_account();
		$purchase_id = $payment->get_purchase();

		// Loop through all the vouchers associated with the payment and tally up the credits that apply.
		$credit = 0;
		$vouchers_active = TRUE;
		$vouchers = Group_Buying_Post_Type::find_by_meta( Group_Buying_Voucher::POST_TYPE, array( '_purchase_id' => $purchase_id ) );
		foreach ( $vouchers as $voucher_id ) {
			$voucher = Group_Buying_Voucher::get_instance( $voucher_id );
			if ( $voucher->is_active() ) { // Check to make sure the voucher is active
				if ( class_exists('Group_Buying_Deal_Rewards') ) {
					$deal_id = $voucher->get_deal_id();
					$user_id = $affiliate_account->get_user_id();
					// if ( GBS_DEV ) error_log( "user id: " . print_r( $user_id, true ) );
					// apply the credits but base it off Group_Buying_Deal_Rewards::get_product_credits again.
					$credit += Group_Buying_Deal_Rewards::get_product_credits( $deal_id, $user_id, $affiliate_account, $purchaser_account, $set_current_time );
					// if ( GBS_DEV ) error_log( "active voucher credits: " . print_r( $credit, true ) );
				} else { // fallback in case Deal Based Rewards is not active.
					$credit = $applied_credits; // this will be set multiple times but be the same.
					// if ( GBS_DEV ) error_log( "deal based rewards class not found: " . print_r( $applied_credits, true ) );
				}
			}
			else {
				$vouchers_active = FALSE;
			}
		}
		if ( !$credit && !$vouchers_active ) { // If there are no credits to apply and the vouchers are not active
			error_log( "++++++++++++++++++ Vouchers not activated no sharing applied and the record is deleted +++++++++++++++++++++++++++++++: " . print_r( TRUE, true ) );
			return -1; // don't allow for this check again since a voucher not activated after 14 days is not good.
		}
		// if ( GBS_DEV ) error_log( "credit to apply back: " . print_r( $credit, true ) );
		// If we have credits apply them, fire an action and send the notification
		if ( $credit ) {
			$credit = floor($credit);
			// if ( GBS_DEV ) error_log( "to apply: " . print_r( $credit, true ) );
			$affiliate_account->add_credit( $credit, $credit_type );
			do_action( 'gb_apply_credits_with_reg_restriction', $affiliate_account, $payment, $credit, $credit_type );
			// Fire off the notification manually
			Group_Buying_Notifications::applied_credits( $affiliate_account, $payment, $credit, $credit_type );
			// Record reward
			self::affiliate_record( $affiliate_account, $purchaser_account, $payment->get_ID(), $credit, $credit_type, $set_current_time);
		}
		return $credit;

	}

	// TODO move this to records controller
	public static function affiliate_record( $account, $purchaser_account, $payment_id, $credits, $type, $set_current_time ) {
		$account_id = $account->get_ID();
		$purchaser_id = $purchaser_account->get_ID();
		$purchaser_name = $purchaser_account->get_name();
		$balance = $account->get_credit_balance( $type );
		$data = array();
		$data['account_id'] = $account_id;
		$data['payment_id'] = $payment_id;
		$data['credits'] = $credits;
		$data['type'] = $type;
		$data['current_total_'.$type] = $balance;
		$data['change_'.$type] = $credits;
		$data['adjustment_value'] = $credits;
		$data['current_total'] = $balance;
		$data['prior_total'] = $balance-$credits;
		Group_Buying_Records::new_record( sprintf( gb__( '%s Points from %s (#%s) - Delayed from %s.' ), ucfirst( $type ), $purchaser_name, $purchaser_id, date( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), $set_current_time ) ), $type, sprintf( gb__( '%s Points from %s (#%s) - Delayed from %s.' ), ucfirst( $type ), $purchaser_name, $purchaser_id, date( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), $set_current_time ) ), 1, $account_id, $data );
	}

	public function filter_where( $where = '' ) {
		// posts 15+ old
		$offset = apply_filters( 'gb_twm_where_filter_delay', date('Y-m-d', strtotime('-15 days')) );
		$where .= " AND post_date <= '" . $offset . "'";
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
			$default = ( Group_Buying_Deal_Reward::get_credits( $deal ) ) ? Group_Buying_Deal_Reward::get_credits( $deal ) : get_option( Group_Buying_Deal_Rewards::OPTION_CREDIT, 0 );
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

class Group_Buying_Cashback_Rewards_Adv extends Group_Buying_Controller {
	const RECORD_TYPE = 'delayed_rewards';
	const NOTIFICATION_TYPE = 'purchase_rewarded_notification';
	private static $credit_type;

	private static $meta_keys = array(
		'reward' => '_gbs_purchase_credit_option', // bool
		'qty_option' => '_gbs_purchase_credit_option_quanity', // int
	);

	public static function init() {
		self::$credit_type = Group_Buying_Affiliates::CREDIT_TYPE;

		// Hook into purchases and provide credits based on deal
		add_action( 'payment_complete', array( get_class(), 'delay_apply_rewards' ), 10, 1 ); // Do the dirty work
		add_action( 'offsite_payment_pending', array( get_class(), 'delay_apply_rewards' ), 10, 1 ); // Allow for offsite payments, since they're always pending.


		// Meta Boxes
		add_action( 'add_meta_boxes', array( get_class(), 'add_meta_boxes' ) );
		add_action( 'save_post', array( get_class(), 'save_meta_boxes' ), 10, 2 );

		add_action( 'init', array( get_class(), 'find_delayed_credits' ) ); // TODO switch
		//add_action( 'gb_cron', array( get_class(), 'find_delayed_credits' ) );

		// Merchant Options
		add_filter( 'gb_deal_submission_fields', array( get_class(), 'filter_deal_submission_fields' ), 10, 1 );
		add_action( 'submit_deal',  array( get_class(), 'submit_deal' ), 10, 1 );
		add_action( 'edit_deal',  array( get_class(), 'submit_deal' ), 10, 1 );

		// notification
		add_filter( 'gb_notification_types', array( get_class(), 'register_notification_type' ), 10, 1 );
	}

	/**
	 * Delay all credits from being applied, the record will be reviewed later
	 * and credits will be applied based on the purchase and voucher activation.
	 *
	 *
	 * @param Group_Buying_Purchase $purchase
	 * @return void
	 */
	public static function delay_apply_rewards( Group_Buying_Payment $payment ) {

		// Rewards are not provided to payments with credits applied. This will also prevent duplicate records
		// since purchases will not consist of two payment processors without credits being the second.
		$payment_method = $payment->get_payment_method();
		// if ( GBS_DEV ) error_log( "delay_apply_rewards - payment method: " . print_r( $payment_method, true ) );
		if ( $payment_method == Group_Buying_Account_Balance_Payments::PAYMENT_METHOD || $payment_method == Group_Buying_Affiliate_Credit_Payments::PAYMENT_METHOD ) {
			// if ( GBS_DEV ) error_log( "credit payment don't apply rewards: " . print_r( TRUE, true ) );
			return;
		}

		$account = $payment->get_account();
		$purchase_id = $payment->get_purchase();
		$purchase = Group_Buying_Purchase::get_instance( $purchase_id );
		$products = $purchase->get_products();

		$possible_rewards = FALSE;
		foreach ( $products as $product ) {
			$deal_id = (int) $product['deal_id'];
			$deal = Group_Buying_Deal::get_instance( $deal_id );
			$reward = self::get_reward( $deal );
			if ( $reward >= 0.01 ) {
				$possible_rewards = TRUE;
			}
		}
		// if ( GBS_DEV ) error_log( "possible_rewards: " . print_r( $reward, true ) );

		if ( !$possible_rewards ) { // If the purchase doesn't have any rewards stop before creating records
			return;
		}

		// Create a record of the delayed credit
		$data = array(
			'account_id' => $account->get_id(),
			'payment_id' => $payment->get_id(),
			'credit_type' => self::$credit_type,
			'current_time' => current_time('timestamp')
		);
		// if ( GBS_DEV ) error_log( "record data: " . print_r( $data, true ) );
		$record_id = Group_Buying_Records::new_record( '', self::RECORD_TYPE, 'Delayed Reward: #'.$data['account_id'], $data['account_id'], $data['account_id'], $data );
		if ( !$record_id ) {
			$records = Group_Buying_Record::get_records_by_type_and_association( $data['account_id'], self::RECORD_TYPE );
			$record_id = max( $records );
		}
		// if ( GBS_DEV ) error_log( "record record_id: " . print_r( $record_id, true ) );
		do_action( 'delay_rewards_function', $record_id, $account, $payment, self::$credit_type );
	}

	public function find_delayed_credits() {

		// filter to get 14+ day old records
		add_filter( 'posts_where', array( get_class(), 'filter_where' ) );
		if ( defined( Group_Buying_Record::TAXONOMY ) && taxonomy_exists( Group_Buying_Record::TAXONOMY ) ) { // In case the records post type moves to use taxonomies and not meta for types.
			$args = array(
				'post_type' => Group_Buying_Record::POST_TYPE,
				'post_status' => 'pending',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'gb_bypass_filter' => TRUE,
				Group_Buying_Record::TAXONOMY => self::RECORD_TYPE
					 );
		}
		else {
			$args = array(
				'post_type' => Group_Buying_Record::POST_TYPE,
				'post_status' => 'pending',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'gb_bypass_filter' => TRUE,
				'meta_query' => array(
							array( 'key' => '_type', 'value' => self::RECORD_TYPE )
						)
					 );
		}
		$records = new WP_Query($args);
		$record_ids = $records->posts;
		remove_filter( 'posts_where', array( get_class(), 'filter_where' ) ); // Remove filter

		// if ( GBS_DEV ) error_log( "returned reward records: " . print_r( $record_ids, true ) );

		if ( empty( $record_ids ) )
			return;

		// Loop through all the records and attempt to apply credits, or delete.
		foreach ( $record_ids as $record_id ) {
			// if ( GBS_DEV ) error_log( "reward record : " . print_r( $record_id, true ) );
			$record = Group_Buying_Record::get_instance( $record_id );
			$data = $record->get_data();
			// if ( GBS_DEV ) error_log( "data: " . print_r( $data, true ) );
			$account = Group_Buying_Account::get_instance_by_id( $data['account_id'] );
			$payment = Group_Buying_Payment::get_instance( $data['payment_id'] );
			if ( !is_a( $payment, 'Group_Buying_Payment' ) ) {
				wp_delete_post( $record_id, TRUE );
				return;
			}
			$applied = self::maybe_apply_reward( $account, $payment, $data['credit_type'], $data['current_time'] );
			// if ( GBS_DEV ) error_log( "applied: " . print_r( $applied, true ) );
			if ( $applied < 0 || $applied > 0 ) { // If applied remove the record, -1 is given if the record should not be checked again.
				// if ( GBS_DEV ) error_log( "deleted: " . print_r( $record_id, true ) );
				wp_delete_post( $record_id, TRUE );
			}
		}
	}

	public function maybe_apply_reward( Group_Buying_Account $account, Group_Buying_Payment $payment, $credit_type, $set_current_time  ) {
		$purchaser_account = $payment->get_account();
		$purchase_id = $payment->get_purchase();

		$reward = 0;
		$vouchers_active = TRUE;
		$vouchers = Group_Buying_Post_Type::find_by_meta( Group_Buying_Voucher::POST_TYPE, array( '_purchase_id' => $purchase_id ) );

		// Loop through all the vouchers associated with the payment and tally up the credits that apply.
		foreach ( $vouchers as $voucher_id ) {
			$voucher = Group_Buying_Voucher::get_instance( $voucher_id );
			if ( $voucher->is_active() ) { // Check to make sure the voucher is active
				$deal_id = $voucher->get_deal_id();
				$deal = Group_Buying_Deal::get_instance( $deal_id );
				if ( is_a( $deal, 'Group_Buying_Deal' ) ) {
					$reward_option = self::get_reward($deal);
					// qty does not need to be considered since every voucher is looped.
					if ( $reward_option >= 0.01 ) {
						$reward += $reward_option;
					}
				}
			}
			else {
				$vouchers_active = FALSE;
			}
		}
		if ( !$reward && !$vouchers_active ) { // If there are no credits to apply and the vouchers are not active
			return -1; // don't allow for this check again since a voucher not activated after 14 days is not good.
		}
		// if ( GBS_DEV ) error_log( "reward to apply: " . print_r( $reward, true ) );
		// If we have credits apply them, fire an action and send the notification
		if ( $reward ) {

			$purchase = Group_Buying_Purchase::get_instance( $purchase_id );

			// Check if the purchase used rewards
			if ( $credits_used = self::purchase_used_credits( $purchase ) ) {
				// if ( GBS_DEV ) error_log( "purchase did have credits: " . print_r( $credits_used, true ) );

				$purchase_total = $purchase->get_total();
				$subtotal = $purchase_total-$credits_used;
				$percentage = $subtotal/$purchase_total;
				// if ( GBS_DEV ) error_log( "percentage: " . print_r( $percentage, true ) );
				// Calculate the credits to reward based on the purchase, otherwise just return 0.
				$reward = $reward*$percentage;
				// if ( GBS_DEV ) error_log( "percentage: " . print_r( $reward, true ) );
			}

			$reward = floor($reward);
			$account->add_credit( $reward, $credit_type ); // Round down
			do_action( 'gb_apply_credits_with_reg_restriction', $account, $payment, $reward, $credit_type );
			// Fire off the notification manually
			self::send_notification( $account, $payment, $reward, $credit_type );
			// Record reward
			self::reward_applied_record( $account, $payment->get_ID(), $reward, $credit_type );
		}
		return $reward;

	}

	public static function reward_applied_record( $account, $payment_id, $credits, $type ) {
		$account_id = $account->get_ID();
		$balance = $account->get_credit_balance( $type );
		$data = array();
		$data['account_id'] = $account_id;
		$data['payment_id'] = $payment_id;
		$data['credits'] = $credits;
		$data['type'] = $type;
		$data['current_total_'.$type] = $balance;
		$data['change_'.$type] = $credits;
		$data['adjustment_value'] = $credits;
		$data['current_total'] = $balance;
		$data['prior_total'] = $balance-$credits;
		// if ( GBS_DEV ) error_log( "data: " . print_r( $data, true ) );
		Group_Buying_Records::new_record( sprintf( gb__( 'Purchase Reward from Payment #%s' ), $payment_id ), $type, sprintf( gb__( 'Purchase Reward from Payment #%s' ), $payment_id ), 1, $account_id, $data );
	}

	public function send_notification( Group_Buying_Account $account, $payment, $credits, $type ) {
		$user_id = $account->get_user_id();
		$to = Group_Buying_Notifications::get_user_email( $user_id );
		$data = array(
			'user_id' => $user_id,
			'applied_credits' => $credits,
			'payment' => $payment,
			'type' => $type
		);
		// if ( GBS_DEV ) error_log( "notification data: " . print_r( $data, true ) );
		Group_Buying_Notifications::send_notification( self::NOTIFICATION_TYPE, $data, $to );
	}

	public function filter_where( $where = '' ) {
		/**
		 * Disabling delay... */ /*/
		// posts 15+ old
		$offset = apply_filters( 'gb_twm_where_filter_delay', date('Y-m-d', strtotime('-15 days')) );
		$where .= " AND post_date <= '" . $offset . "'";
		/**/
		return $where;
	}

	/**
	 * Does the purchase for this payment contain other payments that use credits
	 *
	 * @static
	 * @return int
	 */
	public static function purchase_used_credits( Group_Buying_Purchase $purchase ) {
		$credits_used = 0;
		$payments = $purchase->get_payments();
		foreach ( $payments as $payment_id ) {
			$payment = Group_Buying_Payment::get_instance( $payment_id );
			$payment_method = $payment->get_payment_method();
			// if ( GBS_DEV ) error_log( "payment method: " . print_r( $payment_method, true ) );
			if ( $payment_method == Group_Buying_Account_Balance_Payments::PAYMENT_METHOD || $payment_method == Group_Buying_Affiliate_Credit_Payments::PAYMENT_METHOD ) {
				// if ( GBS_DEV ) error_log( "payment amount: " . print_r( $payment->get_amount(), true ) );
				$credits_used += $payment->get_amount();
			}
		}
		// if ( GBS_DEV ) error_log( "purchase used credits: " . print_r( $credits_used, true ) );
		return $credits_used;
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
			$default = self::get_reward( $deal );
		}
		$fields['cb_rewards'] = array(
			'label' => gb__( 'Rewards' ),
			'weight' => 17,
			'type' => 'text',
			'default' => $default,
			'required' => FALSE,
			'description' => gb__( 'How many credits should the purchaser get.' )
		);
		return $fields;
	}

	public function register_notification_type( $notifications ) {
		$notifications[self::NOTIFICATION_TYPE] = array(
			'name' => gb__( 'Purchase Rewarded' ),
			'description' => gb__( "Customize the notification sent to the customer after a purchase reward is added to their account." ),
			'shortcodes' => array( 'date', 'name', 'username', 'site_title', 'site_url', 'reward' ),
			'default_title' => gb__( 'Purchase Reward' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'You have received a purchase reward at %s.', get_bloginfo( 'name' ) ),
			'allow_preference' => TRUE
		);
		return $notifications;
	}

	/**
	 * Save the credit field after the submission
	 *
	 * @param Group_Buying_Deal $deal
	 * @return
	 */
	public function submit_deal( Group_Buying_Deal $deal ) {
		$reward = isset( $_POST['gb_deal_cb_rewards'] ) ? $_POST['gb_deal_cb_rewards'] : '';
		self::set_reward( $deal, $reward );
	}

	public static function add_meta_boxes() {
		add_meta_box( 'purchase_rewards', self::__( 'Purchase Rewards' ), array( get_class(), 'show_meta_boxes' ), Group_Buying_Deal::POST_TYPE, 'side' );
	}

	public static function show_meta_boxes( $post, $metabox ) {
		switch ( $metabox['id'] ) {
		case 'purchase_rewards':
			$deal = Group_Buying_Deal::get_instance( $post->ID );
			self::show_meta_box( $deal, $post, $metabox );
			break;
		default:
			self::unknown_meta_box( $metabox['id'] );
			break;
		}
	}

	private static function show_meta_box( Group_Buying_Deal $deal, $post, $metabox ) {
		$reward = self::get_reward( $deal );
		?>
			<p>
				<label for="purchase_reward"><?php gb_e('Purchase Reward:') ?> </label><input type="text" value="<?php echo $reward; ?>" name="purchase_reward" id="purchase_reward" placeholder="0" />
			</p>
		<?php
	}

	public static function save_meta_boxes( $post_id, $post ) {
		// only continue if it's an account post
		if ( $post->post_type != Group_Buying_Deal::POST_TYPE ) {
			return;
		}
		// don't do anything on autosave, auto-draft, bulk edit, or quick edit
		if ( wp_is_post_autosave( $post_id ) || $post->post_status == 'auto-draft' || defined( 'DOING_AJAX' ) || isset( $_GET['bulk_edit'] ) ) {
			return;
		}
		// save all the meta boxes
		$deal = Group_Buying_Deal::get_instance( $post_id );
		if ( !is_a( $deal, 'Group_Buying_Deal' ) ) {
			return; // The account doesn't exist
		}
		self::save_meta_box( $deal, $post_id, $post );
	}

	private static function save_meta_box( Group_Buying_Deal $deal, $post_id, $post ) {

		self::set_reward_qty_option( $deal, 0 );
		if ( isset( $_POST['purchase_reward_qty_option'] ) && 'TRUE' == $_POST['purchase_reward_qty_option'] ) {
			self::set_reward_qty_option( $deal, 1 );
		}

		if ( isset( $_POST['purchase_reward'] ) && (int)$_POST['purchase_reward'] > 0 ) {
			$reward = (int)$_POST['purchase_reward'];
			self::set_reward( $deal, $reward );
		}

	}


	public function get_reward( Group_Buying_Deal $deal ) {
		return $deal->get_post_meta( self::$meta_keys['reward'] );
	}
	public function set_reward( Group_Buying_Deal $deal, $reward ) {
		return $deal->save_post_meta( array( self::$meta_keys['reward'] => $reward ) );
	}

	public function get_reward_qty_option( Group_Buying_Deal $deal ) {
		return $deal->get_post_meta( self::$meta_keys['qty_option'] );
	}
	public function set_reward_qty_option( Group_Buying_Deal $deal, $qty_option ) {
		return $deal->save_post_meta( array( self::$meta_keys['qty_option'] => $qty_option ) );
	}
}