<?php

class GBS_Credits_Extension {

	/** @var GBS_Rewards_Extension */
	private static $instance;

	private function add_hooks() {
		add_filter( 'gb_addons', array( $this, 'load_custom_addons' ), 10, 1 );
	}

	public static function load_custom_addons( $addons ) {
		$addons['credit_limiter'] = array( // same key so that it overrides the original in case it's already set.
			'label' => __( 'Credit Limit (Advanced)' ),
			'description' => __( 'Add-on creates an option for limiting the number of credits a customer can use per _deal_.' ),
			'files' => array(
				__FILE__,
			),
			'callbacks' => array(
				array( 'Group_Buying_Credit_Limiter_Adv', 'init' ),
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
		$this->add_hooks();
	}
}

class Group_Buying_Credit_Limiter_Adv extends Group_Buying_Controller {
	const LIMIT_OPTION = '_gb_credit_limiter';
	private static $limit;
	const DEBUG = TRUE;

	public static function init() {
		parent::init();

		if ( is_admin() ) {
			// Admin options
			add_action( 'add_meta_boxes', array( get_class(), 'add_meta_boxes' ) );
			add_action( 'save_post', array( get_class(), 'save_meta_boxes' ), 100, 2 );
		}

		// Merchant Options
		add_filter( 'gb_deal_submission_fields', array( get_class(), 'filter_deal_submission_fields' ), 10, 1 );
		add_action( 'submit_deal',  array( get_class(), 'submit_deal' ), 10, 1 );
		add_action( 'edit_deal',  array( get_class(), 'submit_deal' ), 10, 1 );


		// Process payment
		add_action( 'gb_checkout_action_'.Group_Buying_Checkouts::PAYMENT_PAGE, array( get_class(), 'limit_credits' ), 5, 1 );
	}

	/**
	 * Add meta boxes to the deal post page
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'gb_credit_limiter', self::__( 'Credit Limit Options' ), array( get_class(), 'show_meta_box' ), Group_Buying_Deal::POST_TYPE, 'side', 'default' );
	}

	/**
	 * Actually show the meta box content
	 */
	public static function show_meta_box( $post, $metabox ) {
		$deal = Group_Buying_Deal::get_instance( $post->ID );
		switch ( $metabox['id'] ) {
		case 'gb_credit_limiter':
			self::show_meta_box_gb_credit_limiter( $deal, $post, $metabox );
			break;
		default:
			self::unknown_meta_box( $metabox['id'] );
			break;
		}
	}

	/**
	 * Display the credit limiter options
	 *
	 * @static
	 * @param Group_Buying_Deal $deal
	 * @param int     $post
	 * @param array   $metabox
	 * @return void
	 */
	public static function show_meta_box_gb_credit_limiter( Group_Buying_Deal $deal, $post, $metabox ) {
		$limit = self::get_credit_limit( $deal->get_id() ); // limit can be zero
?>
			<p>
				<label for="<?php echo self::LIMIT_OPTION ?>"><strong><?php echo self::_e( 'Credit Limit' ); ?></strong></label> <input type="text" id="<?php echo self::LIMIT_OPTION ?>" name="<?php echo self::LIMIT_OPTION ?>" value="<?php echo $limit; ?>" size="2" />% <?php self::_e( 'per item' ) ?>
			</p>
		<?php
	}

	/**
	 * Save the meta boxes
	 */
	public static function save_meta_boxes( $post_id, $post ) {

		// don't do anything on autosave, auto-draft, bulk edit, or quick edit
		if ( wp_is_post_autosave( $post_id ) || $post->post_status == 'auto-draft' || defined( 'DOING_AJAX' ) || isset( $_GET['bulk_edit'] ) ) {
			return;
		}
		// only continue if it's a deal post
		if ( $post->post_type != Group_Buying_Deal::POST_TYPE ) {
			return;
		}

		// save all the meta boxes
		self::save_meta_box_gb_credit_limiter( $post_id, $post );
	}

	/**
	 * Save the credit limit meta box
	 *
	 * @static
	 * @param Group_Buying_Deal $deal
	 * @param int     $post_id
	 * @param object  $post
	 * @return void
	 */
	public static function save_meta_box_gb_credit_limiter( $post_id, $post ) {
		$limit = isset( $_POST[self::LIMIT_OPTION] ) ? $_POST[self::LIMIT_OPTION] : '';
		self::set_credit_limit( $post_id, $limit );
	}

	/**
	 * Filter the merchant submission page
	 *
	 * @param array   $fields
	 * @return array
	 */
	public function filter_deal_submission_fields( $fields ) {
		global $wp; // get_query_var won't work at this point
		$default = ( isset( $wp->query_vars[Group_Buying_Deals_Edit::EDIT_DEAL_QUERY_VAR] ) ) ? self::get_credit_limit( $wp->query_vars[Group_Buying_Deals_Edit::EDIT_DEAL_QUERY_VAR] ) : '' ;
		$fields[self::LIMIT_OPTION] = array(
			'label' => self::__( 'Credit Limit' ),
			'weight' => 18,
			'type' => 'text',
			'default' => $default,
			'required' => FALSE,
			'description' => self::__( 'Limit the amount of credits a customer can use towards this deal when purchasing.' )
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
		$post_id = $deal->get_id();
		$limit = isset( $_POST['gb_deal_'.self::LIMIT_OPTION] ) ? $_POST['gb_deal_'.self::LIMIT_OPTION] : '';
		self::set_credit_limit( $post_id, $limit );
	}

	/**
	 * Do the dirty work and calculate what the credit limit for the cart is, provide an error if they go over.
	 *
	 * @param Group_Buying_Checkouts $checkout
	 * @return
	 */
	public function limit_credits( Group_Buying_Checkouts $checkout ) {

		if ( isset( $_POST['gb_credit_affiliate_credits'] ) && $_POST['gb_credit_affiliate_credits'] > 0.01 ) {

			$cart = $checkout->get_cart();
			$products = $cart->get_items();
			$total_allowed = 0;
			foreach ( $products as $key => $item ) {
				$deal = Group_Buying_Deal::get_instance( $item['deal_id'] );
				$limit = self::get_credit_limit( $item['deal_id'] );
				if ( $limit !== '' ) {
					$total_allowed += ($deal->get_price( NULL, $item['data'] )*$item['quantity'])*($limit/100);
				}
				else { // if limit is not set, add the total to the allowed amount
					$total_allowed += $deal->get_price( NULL, $item['data'] )*$item['quantity'];
				}
			}

			if ( $_POST['gb_credit_affiliate_credits'] > $total_allowed ) {
				$_POST['gb_credit_affiliate_credits'] = $total_allowed;
				self::set_message( sprintf( self::__( 'Sorry, you may only use %s of your credits per purchase.' ), $total_allowed, self::MESSAGE_STATUS_ERROR ) );
			}
		}

	}

	/**
	 * Get the credit limit for the deal
	 *
	 * @param int     $deal_id
	 * @return int
	 */
	public static function get_credit_limit( $deal_id ) {
		$meta = get_post_meta( $deal_id, self::LIMIT_OPTION, true );
		return $meta;
	}

	/**
	 * Set the credit limit for the deal
	 *
	 * @param int     $deal_id
	 * @param int     $meta
	 * @return int
	 */
	public static function set_credit_limit( $deal_id, $meta ) {
		$meta = update_post_meta( $deal_id, self::LIMIT_OPTION, $meta );
		return $meta;
	}

}
