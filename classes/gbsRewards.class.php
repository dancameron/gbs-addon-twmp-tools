<?php

class GB_Affiliates_Ext {
	const NEW_REGISTRATION_TIME = 86400;

	/** @var GBS_Rewards_Extension */
	private static $instance;

	private function add_addon() {
		add_filter( 'gb_addons', array( $this, 'load_custom_addons' ), 10, 1 );
	}

	public static function load_custom_addons( $addons ) {
		$addons['new_user_affiliate_credits'] = array(
			'label' => __( 'Affiliate Credits Restrictions' ),
			'description' => __( 'Only apply credits to affiliates when the purchase was from a new user. This modification may interfere with the Deal Based Rewards Add-on.' ),
			'files' => array(
				__FILE__,
				dirname( __FILE__ ) . '/library/template-tags.php',
			),
			'callbacks' => array(
				array( __CLASS__, 'add_hooks' ),
			),
		);
		return $addons;
	}

	private function add_hooks() {
		remove_action( 'gb_apply_credits', array( 'Group_Buying_Notifications', 'applied_credits' ), 10, 4 );
		add_action( 'gb_apply_credits', array( get_class(), 're_apply_credits' ), 10, 1 );
	}

	////////////////
	// Overriding //
	////////////////

	
	public static function re_apply_credits( Group_Buying_Account $affiliate_account, Group_Buying_Payment $payment, $applied_credits, $credit_type ) {
		
		// Start off by removing the credit that was just applied.
		$affiliate_account->deduct_credit( $applied_credits, $credit_type );
		// Get the registration date.
		$purchaser_account = $payment->get_account();
		$purchaser_registration_time = get_the_time( $purchaser_account->get_id() );

		// Check to see if the registration date plus the buffer is before the current time. 
		if ( time() > ( $purchaser_registration_time + self::NEW_REGISTRATION_TIME ) ) {
			// apply the credits back
			$affiliate_account->add_credit( $applied_credits, $credit_type );
			do_action( 'gb_apply_credits_with_reg_restriction', $affiliate_account, $payment, $applied_credits, $credit_type );
			// Fire off the notification manually
			Group_Buying_Notifications::applied_credits( $affiliate_account, $payment, $applied_credits, $credit_type );
		}
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