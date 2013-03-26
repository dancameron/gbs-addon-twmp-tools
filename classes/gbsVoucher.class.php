<?php

class GBS_Vouchers_Extension {
	const NOTIFICATION_TYPE = 'voucher_payment_reminder_notification';
	const NOTIFICATION_TYPE_3DAY = 'voucher_payment_reminder_notification_3day';
	const NOTIFICATION_TYPE_FINAL = 'voucher_payment_reminder_notification_final';
	const NOTIFICATION_SENT_META_KEY = '_gb_voucher_notification_sent10';
	const NONCE = 'vouchers_extension_nonce';

	/** @var GBS_Vouchers_Extension */
	private static $instance;

	private function add_hooks() {
		add_filter( 'gb_addons', array( $this, 'load_custom_addons' ), 10, 1 );
	}

	public static function load_custom_addons( $addons ) {
		$addons['voucherexpiry'] = array(
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
		$addons['voucher_notifications_addon'] = array(
			'label' => __( 'Send Notification Reminders to Purchasers' ),
			'description' => __( 'Send a reminder to purchasers 1-day after purchase to remind them to pay; Send a notification to the purchaser if the voucher is not activated within 3-days; After the deal tips and the voucher has not been activated they must pay within the next day.' ),
			'files' => array(
				__FILE__,
			),
			'callbacks' => array(
				array( __CLASS__, 'voucher_notification_hooks' ),
			),
		);
		$addons['voucher_merchant_activation'] = array(
			'label' => __( 'Merchant Activation/Deactivation' ),
			'description' => __( 'Allow for merchant to activate and deactivate vouchers from the vouchers report.' ),
			'files' => array(
				__FILE__,
			),
			'callbacks' => array(
				array( __CLASS__, 'voucher_merchant_activation_hooks' ),
			),
		);
		return $addons;
	}

	public function voucher_notification_hooks() {
		// Find notification to be sent
		add_action( 'init', array( get_class(), 'find_pending_vouchers' ) );
		// add_action( 'gb_cron', array( get_class(), 'find_pending_vouchers' ) );

		// Register Notifications
		add_filter( 'gb_notification_types', array( get_class(), 'register_notification_type' ), 10, 1 );
		//add_filter( 'gb_notification_shortcodes', array( get_class(), 'register_notification_shortcodes' ), 10, 1 );
	}

	public function voucher_merchant_activation_hooks() {
		add_filter( 'set_merchant_voucher_report_data_column', array( get_class(), 'set_voucher_report_data_column' ), 10, 1 );
		add_filter( 'set_merchant_voucher_report_data_records', array( get_class(), 'set_voucher_report_data_records' ), 10, 1 );
		add_action( 'wp_ajax_gb_merchant_toggle_voucher_status',  array( get_class(), 'toggle_status' ), 10, 0 );
		add_action( 'wp_footer', array( get_class(), 'print_js' ) );
	}

	public function find_pending_vouchers() {

		// Filter the post query so that it returns only pending vouchers a day+ old
		add_filter( 'posts_where', array( get_class(), 'filter_where' ) );
		$args = array(
				'post_type' => Group_Buying_Voucher::POST_TYPE,
				'post_status' => 'pending',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'gb_bypass_filter' => TRUE );
		$vouchers = new WP_Query($args);
		remove_filter( 'posts_where', array( get_class(), 'filter_where' ) );

		foreach ( $vouchers->posts as $voucher_id ) {
			self::maybe_send_notification( $voucher_id );
		}
		
	}

	public function register_notification_type( $notifications ) {
		$notifications[self::NOTIFICATION_TYPE] = array(
			'name' => gb__( 'Payment Reminder (1-day)' ),
			'description' => gb__( "Customize the notification sent to the customer one day after purchase, if their voucher is still not activated. This notice will be sent per voucher, not per purchase." ),
			'shortcodes' => array( 'date', 'name', 'username', 'purchase_details', 'transid', 'site_title', 'site_url', 'credits_used', 'rewards_used', 'total', 'billing_address', 'shipping_address', 'voucher_url', 'voucher_logo', 'voucher_serial', 'voucher_expiration', 'voucher_how_to', 'voucher_locations', 'voucher_fine_print', 'voucher_security' ),
			'default_title' => gb__( 'Payment Reminder ' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'This is a reminder that you need to pay for your voucher before it will be automatically removed from your account at %s.', get_bloginfo( 'name' ) ),
			'allow_preference' => TRUE
		);
		$notifications[self::NOTIFICATION_TYPE_3DAY] = array(
			'name' => gb__( 'Payment Reminder (3-day)' ),
			'description' => gb__( "Customize the notification sent to the customer three days after purchase, if their voucher is still not activated. This notice will be sent per voucher, not per purchase." ),
			'shortcodes' => array( 'date', 'name', 'username', 'purchase_details', 'transid', 'site_title', 'site_url', 'credits_used', 'rewards_used', 'total', 'billing_address', 'shipping_address' ),
			'default_title' => gb__( 'Payment Reminder ' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'This is a reminder that you need to pay for your voucher before it will be automatically removed from your account at %s.', get_bloginfo( 'name' ) ),
			'allow_preference' => TRUE
		);
		$notifications[self::NOTIFICATION_TYPE_FINAL] = array(
			'name' => gb__( 'Payment Reminder (final notice after tip)' ),
			'description' => gb__( "Customize the notification sent to the customer immediately after a deal tips, if their voucher is still not activated. This notice will be sent per voucher, not per purchase." ),
			'shortcodes' => array( 'date', 'name', 'username', 'purchase_details', 'transid', 'site_title', 'site_url', 'credits_used', 'rewards_used', 'total', 'billing_address', 'shipping_address' ),
			'default_title' => gb__( 'Payment Reminder ' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'This is a reminder that you need to pay for your voucher before it will be automatically removed from your account at %s.', get_bloginfo( 'name' ) ),
			'allow_preference' => TRUE
		);
		return $notifications;
	}

	public function maybe_send_notification( $voucher_id, $set_current_time = 0 ) {
		
		error_log( "voucher id maybe: " . print_r( $voucher_id, true ) );

		// Check if final notification was sent, if so we don't want to send any others.
		if ( self::was_notification_sent( $voucher_id, self::NOTIFICATION_TYPE_FINAL ) )
			return FALSE;

		$voucher = Group_Buying_Voucher::get_instance( $voucher_id );
		$deal = $voucher->get_deal();

		// Attempt to send the final notification first, since it's of higher priority and we don't want to send
		// the 1/3 day notifications immediately before this one in case the customer just purchased before the deal closure.
		if ( $deal->is_closed() ) {
			error_log( "closed notification sent: " . print_r( TRUE, true ) );
			self::voucher_notification( self::NOTIFICATION_TYPE_FINAL, $voucher );
			return TRUE;
		}

		// Date based notifications
		$voucher_date = get_the_time( 'U', $voucher_id );
		$set_current_time = ( $set_current_time ) ? $set_current_time : current_time('timestamp'); // Allow the time to be set.

		// If the voucher is older than 3 days we can assume the 1 day voucher notification was already sent.
		if ( $voucher_date <= ( $set_current_time - 259200 ) ) { // If older than three days
			error_log( "3day sent: " . print_r( TRUE, true ) );
			self::voucher_notification( self::NOTIFICATION_TYPE_3DAY, $voucher );
			return TRUE;
		}

		// If we got this far no notifications have been sent at all.
		if ( $voucher_date <= ( $set_current_time - 86400 ) ) { // If older than one day
			error_log( "1day sent: " . print_r( TRUE, true ) );
			self::voucher_notification( self::NOTIFICATION_TYPE, $voucher );
			return TRUE;
		}

		return FALSE;
	}

	function voucher_notification( $type, Group_Buying_Voucher $voucher ) {
		$voucher_id = $voucher->get_id();
		if ( self::was_notification_sent( $voucher_id, $type ) )
			return FALSE;

		$purchase = $voucher->get_purchase();
		$deal = $voucher->get_deal();

		$user_id = $purchase->get_user();
		if ( $user_id !== -1 ) { // purchase will be set to -1 if it's a gift.
			$recipient = Group_Buying_Notifications::get_user_email( $user_id );

			$data = array(
				'user_id' => $user_id,
				'voucher' => $voucher,
				'purchase' => $purchase,
				'deal' => $deal
			);

			Group_Buying_Notifications::send_notification( $type, $data, $to );
			self::mark_notification_sent( $voucher_id, $type );
		}
	}

	public function mark_notification_sent( $voucher_id, $type ) {
		return update_post_meta( $voucher_id, self::NOTIFICATION_SENT_META_KEY.'_'.$type, time() );
	}

	public function was_notification_sent( $voucher_id, $type ) {
		$notification_sent = get_post_meta( $voucher_id, self::NOTIFICATION_SENT_META_KEY.'_'.$type, TRUE );
		if ( $notification_sent ) {
			return TRUE;
		}
		return;
	}
	
	public function filter_where( $where = '' ) {
		// posts 1+ old
		$where .= " AND post_date <= '" . date('Y-m-d', current_time('timestamp')-86400 ) . "'";
		return $where;
	}

	public static function set_voucher_report_data_column( $columns ) {
		$columns['activation_links'] = gb__( 'Management' );
		return $columns;
	}

	public static function set_voucher_report_data_records( $array ) {
		if ( !is_array( $array ) ) {
			return; // nothing to do.
		}
		$new_array = array();
		foreach ( $array as $records ) {
			$mngt_item = array( 'activation_links' => self::activate_buttons( $records['voucher_id'] ) );
			$new_array[] = array_merge( $records, $mngt_item );
		}
		return $new_array;
	}

	public function activate_buttons( $voucher_id ) {
		if ( get_post_status( $voucher_id ) != 'publish' ) {
			$view = '<p><span id="'.$voucher_id.'_activate_result"></span><a href="javascript:void(0)" class="gb_activate button btn" id="'.$voucher_id.'_activate" ref="'.$voucher_id.'">'.gb__('Activate').'</a></p>';
		} else {
			$view = '<p><span id="'.$voucher_id.'_deactivate_result"></span><a href="javascript:void(0)" class="gb_deactivate button alt_button btn btn_inverse" id="'.$voucher_id.'_deactivate" ref="'.$voucher_id.'">'.gb__('Deactivate').'</a></p>';
		}
		return $view;
	}

	public function print_js() {
		?>
			<script type="text/javascript" charset="utf-8">
			jQuery(document).ready(function($){
				var voucher_activation_ajax_url = '<?php echo admin_url() ?>admin-ajax.php';
				jQuery(".gb_activate").on('click', function(event) {
					event.preventDefault();
						if( confirm( '<?php gb_e("Are you sure? This will make the voucher immediately available for download.") ?>' ) ){
							var $activate_voucher_id = $( this ),
							activate_voucher_id = $activate_voucher_id.attr( 'ref' );
							$( "#"+activate_voucher_id+"_activate" ).fadeOut('slow');
							$.post( voucher_activation_ajax_url, { action: 'gb_merchant_toggle_voucher_status', voucher_id: activate_voucher_id, activate_voucher_nonce: '<?php echo wp_create_nonce( self::NONCE ) ?>' },
								function( data ) {
										$( "#"+activate_voucher_id+"_activate_result" ).append( '<?php gb_e( 'Activated' ) ?>' ).fadeIn();
									}
								);
						} else {
							// nothing to do.
						}
				});
				jQuery(".gb_deactivate").on('click', function(event) {
					event.preventDefault();
						if( confirm( '<?php gb_e("Are you sure? This will immediately remove the voucher from customer access.") ?>' ) ) {
							var $deactivate_button = $( this ),
							deactivate_voucher_id = $deactivate_button.attr( 'ref' );
							$( "#"+deactivate_voucher_id+"_deactivate" ).fadeOut('slow');
							$.post( voucher_activation_ajax_url, { action: 'gb_merchant_toggle_voucher_status', voucher_id: deactivate_voucher_id, activate_voucher_nonce: '<?php echo wp_create_nonce( self::NONCE ) ?>' },
								function( data ) {
										$( "#"+deactivate_voucher_id+"_deactivate_result" ).append( '<?php gb_e( 'Deactivated' ) ?>' ).fadeIn();
									}
								);
						} else {
							// nothing to do.
						}
				});
			});
		</script>
		<?php
	}

	public static function toggle_status() {
		if ( !isset( $_REQUEST['activate_voucher_nonce'] ) )
			wp_die( 'Forget something?' );

		$nonce = $_REQUEST['activate_voucher_nonce'];
		if ( !wp_verify_nonce( $nonce, self::NONCE ) )
        	wp_die( 'Not going to fall for it!' );

        // Get the voucher object
        $voucher_id = $_REQUEST['voucher_id'];
        $voucher = Group_Buying_Voucher::get_instance( $voucher_id );
        if ( !is_a( $voucher, 'Group_Buying_Voucher' ) )
				return;

		// Check to make sure this merchant can activate/deactivate this deal
        $deal_id = $voucher->get_deal_id();
        $deal_merchant_id = gb_get_merchant_id( $deal_id );
        $authorized_merchants = gb_get_merchants_by_account( get_current_user_id() );
        if ( !empty( $authorized_merchants ) && in_array( $deal_merchant_id, $authorized_merchants ) ) {
        	// If active than we are deactivating
			if ( $voucher->is_active() ) {
				if ( method_exists( $voucher, 'mark_pending' ) ) { // added in future release
					$voucher->mark_pending();
				}
				else {
					$post_obj = get_post( $voucher_id );
					$post_obj->post_status = 'pending';
					wp_update_post( $post_obj );
					do_action( 'voucher_marked_pending', $this );
				}
			} 
			else {
				$voucher->activate();
			}
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
		add_filter( 'gb_get_voucher_expiration_date',  array( get_class(), 'voucher_expiration_date' ), 10, 2 );

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

		if ( get_post_status( $voucher_id ) != 'publish' ) {
			return 0;
		}

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
