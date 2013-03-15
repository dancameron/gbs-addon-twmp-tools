<?php

class GBS_Vouchers_Extension {

	/** @var GBS_Vouchers_Extension */
	private static $instance;

	private function add_hooks() {
		add_filter( 'gb_addons', array( $this, 'load_custom_addons' ), 10, 1 );
	}

	public static function load_custom_addons( $addons ) {
		$addons['gbs_sf_advvoucherexpiry_addon'] = array(
			'label' => __( 'Adv. Voucher Expiry (Advanced)' ),
			'description' => __( 'Add option for Voucher Expiration by Days after Purchase. Disable access to vouchers after expiration.' ),
			'files' => array(
				__FILE__,
				dirname( __FILE__ ) . '/library/template-tags.php',
			),
			'callbacks' => array(
				array( 'GBS_VoucherExpiry_Addon_Adv', 'init' ),
			),
		);
		return $addons;
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
	 * @return GBS_Vouchers_Extension
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
		$this->add_hooks();
	}
}



class GBS_VoucherExpiry_Addon_Adv extends Group_Buying_Controller {
	const EXP_OPTION = 'gb_voucher_exp_option';
	private static $exp;

	private static $meta_keys = array(
		'expiry_onoff' => 'gbs_adv_voucher_expiry_onoff', // string
		'expiry_count' => 'gbs_adv_voucher_expiry_count', // int
	);

	public static function init() {
		// Options
		self::$exp = floatval( get_option( self::EXP_OPTION, '' ) );
		add_action( 'admin_init', array( get_class(), 'register_settings_fields' ), 10, 0 );
		add_action( 'admin_footer',  array( get_class(), 'admin_css' ) );

		//Set new Expiration date on GBS Voucher (or display the existing date)
		add_filter( 'gb_get_voucher_expiration_date',  array( get_class(), 'voucher_expiration_date' ), 10, 1 );

		//Check if the voucher page is expired
		add_action( 'template_redirect',  array( get_class(), 'check_voucher_expiration' ), 999, 1 );

		// Merchant Options
		add_filter( 'gb_deal_submission_fields', array( get_class(), 'filter_deal_submission_fields' ), 10, 1 );
	}

	public function admin_css() {
		// Hide the expiration option on the deal edit page, it's easier than re-building the meta box.
		echo '<style>#voucher_expiration_edit { display: none; } </style>';
	}

	/**
	 * Redirect away from a voucher that's expired.
	 *
	 * @return
	 */
	public static function check_voucher_expiration() {

		// Are we on a single voucher page
		if ( is_single() && Group_Buying_Voucher::is_voucher_query() ) {

			if ( self::check_if_voucher_expired( get_the_ID() ) ) {
				gb_set_message( gb__( 'Voucher has Expired.' ), 'error' );
				wp_redirect( gb_get_account_url() );
				exit();
			}

		}
	}

	/**
	 * Check if voucher is expired
	 *
	 * @param int     $voucher_id
	 * @return bool
	 */
	public static function check_if_voucher_expired( $voucher_id = NULL ) {
		if ( get_post_type( $voucher_id ) !== Group_Buying_Voucher::POST_TYPE )
			return;

		if ( self::voucher_expiration_date( $voucher_id ) ) {
			if ( self::voucher_expiration_date( $voucher_id ) < time() ) {
				return TRUE; // Expired
			}
		}
		return FALSE;
	}

	/**
	 * Filtered voucher expiration date
	 *
	 * @param string  $date
	 * @param integer $voucher_id
	 * @return
	 */
	public function voucher_expiration_date( $date = '', $voucher_id = 0 ) {
		if ( !$voucher_id ) {
			global $post;
			$voucher_id = $post->ID;
		}
		if ( !$voucher_id )
			return '';

		$voucher_date = get_the_time( 'U', $voucher_id );
		$new_date = $voucher_date + ( self::$exp * 86400 );
		return $new_date;
	}

	/**
	 * Filter the merchant submission page
	 *
	 * @param array   $fields
	 * @return array
	 */
	public function filter_deal_submission_fields( $fields ) {
		unset($fields['voucher_expiration']);
		return $fields;
	}

	//////////////
	// Settings //
	//////////////

	public static function register_settings_fields() {
		$page = Group_Buying_UI::get_settings_page();
		$section = 'gb_general_voucher_settings';
		register_setting( $page, self::EXP_OPTION );

		// Fields
		add_settings_field( self::EXP_OPTION, self::__( 'Voucher Expiry (by Days) after Creation' ), array( get_class(), 'display_option' ), $page, $section );
	}

	public static function display_option() {
		echo '<input name="'.self::EXP_OPTION.'" id="'.self::EXP_OPTION.'" type="text" size="3" value="'.self::$exp.'">';
	}

}
