<?php
	
/**
 * Load via GBS Add-On API
 */
class Group_Buying_Registration_Fields_Addon extends Group_Buying_Controller {

	public static function init() {
		// Hook this plugin into the GBS add-ons controller
		add_filter('gb_addons', array(get_class(),'gb_addon'), 10, 1);
	}

	public static function gb_addon( $addons ) {
		$addons['twm_registration_fields'] = array(
			'label' => self::__('TWM Tools: Registration Fields'),
			'description' => self::__('Additional registrations fields.'),
			'files' => array(
				__FILE__
			),
			'callbacks' => array(
				array('Registration_Fields', 'init'),
			),
		);
		return $addons;
	}

}

class Registration_Fields extends Group_Buying_Controller {
	
	// List all of your field IDs here as constants
	const MOBILE_CODE = 'gb_account_mobile_code';
	const MOBILE = 'gb_account_mobile';

	public static function init() {
		error_log( "loaded: " . print_r( TRUE, true ) );
		// registration hooks
		add_filter('gb_account_registration_panes', array(get_class(), 'get_registration_panes'),100);
		add_filter('gb_validate_account_registration', array(get_class(), 'validate_account_fields'), 10, 4);
		add_action('gb_registration', array(get_class(), 'process_registration'), 50, 5);
		
		// Add the options to the account edit screens
		add_filter('gb_account_edit_panes', array(get_class(), 'get_edit_fields'), 0, 2);
		add_action('gb_process_account_edit_form',array(get_class(), 'process_edit_account'));
		
		// Hook into the reports
		add_filter('set_deal_purchase_report_data_column', array(get_class(), 'reports_columns'), 10, 2);
		add_filter('set_merchant_purchase_report_column', array(get_class(), 'reports_columns'), 10, 2);
		add_filter('set_accounts_report_data_column', array(get_class(), 'reports_columns'), 10, 2);
		add_filter('gb_deal_purchase_record_item', array(get_class(), 'reports_record'), 10, 3);
		add_filter('gb_merch_purchase_record_item', array(get_class(), 'reports_record'), 10, 3);
		add_filter('gb_accounts_record_item', array(get_class(), 'reports_account_record'), 10, 3);
		
	}
	
	/**
	 * Add the report coloumns.
	 *
	 * @param array
	 * @return null 
	 */
	public function reports_columns( $columns ) {
		// Add as many as you want with their own key that will be used later.
		$columns['mobile_number'] = self::__('Mobile');
		return $columns;
	}
	
	/**
	 * Add the report record for deal purchase and merchant report.
	 *
	 * @param array
	 * @return null 
	 */
	public function reports_record( $array, $purchase, $account ) {
		if ( !is_a($account,'Group_Buying_Account')) {
			return $array;
		}
		// Add as many as you want with their own matching key from the reports_column
		$array['mobile_number'] = get_post_meta( $account->get_ID(), '_'.self::MOBILE_CODE, TRUE ) . ' ' . get_post_meta( $account->get_ID(), '_'.self::MOBILE, TRUE );
		return $array;
	}
	
	/**
	 * Add the report record for account report
	 *
	 * @param array
	 * @return null 
	 */
	public function reports_account_record( $array, $account ) {
		// Add as many as you want with their own matching key from the reports_column
		$array['mobile_number'] = get_post_meta( $account->get_ID(), '_'.self::MOBILE_CODE, TRUE ) . ' ' . get_post_meta( $account->get_ID(), '_'.self::MOBILE, TRUE );
		return $array;
	}
	
	/**
	 * Hook into the process registration action
	 *
	 * @param array
	 * @return null 
	 */
	public function process_registration( $user = null, $user_login = null, $user_email = null, $password = null, $post = null ) {
		$account = Group_Buying_Account::get_instance($user->ID);
		// using the single callback below
		self::process_form($account);
	}
	
	/**
	 * Hook into the process edit account action
	 *
	 * @param array
	 * @return null 
	 */
	public static function process_edit_account( Group_Buying_Account $account ) {
		// using the single callback below
		self::process_form($account);
	}
	
	/**
	 * Process the form submission and save the meta
	 *
	 * @param array  | Group_Buying_Account
	 * @return null 
	 */
	public static function process_form( Group_Buying_Account $account ) {
		// Copy all of the new fields below, copy the below if it's a basic field.
		if ( isset($_POST[self::MOBILE]) && $_POST[self::MOBILE] != '' ) {
			delete_post_meta( $account->get_ID(), '_'.self::MOBILE );
			add_post_meta( $account->get_ID(), '_'.self::MOBILE, $_POST[self::MOBILE] );
			delete_post_meta( $account->get_ID(), '_'.self::MOBILE_CODE );
			add_post_meta( $account->get_ID(), '_'.self::MOBILE_CODE, $_POST[self::MOBILE_CODE] );
		}
		// Below is a commented out process to uploaded images
		/*/
		if ( !empty($_FILES[self::UPLOAD]) ) {
		 	// Set the uploaded field as an attachment
			self::set_attachement( $account->get_ID(), $_FILES );
		}
		/**/
	}
	
	/**
	 * Add a file as a post attachment.
	 *
	 * @return null 
	 */
	public static function set_attachement( $post_id, $files ) {
		if (!function_exists('wp_generate_attachment_metadata')){
			require_once(ABSPATH . 'wp-admin' . '/includes/image.php');
			require_once(ABSPATH . 'wp-admin' . '/includes/file.php');
			require_once(ABSPATH . 'wp-admin' . '/includes/media.php');
		}
		foreach ($files as $file => $array) {
			if ($files[$file]['error'] !== UPLOAD_ERR_OK) {
				self::set_message('upload error : ' . $files[$file]['error']);
			}
			$attach_id = media_handle_upload( $file, $post_id );
		}
		// Make it a thumbnail while we're at it.
		if ($attach_id > 0){
			update_post_meta($post_id,'_thumbnail_id',$attach_id);
		}
		return $attach_id;
	}
	
	/**
	 * Validate the form submitted
	 * 
	 * @return array
	 */
	public function validate_account_fields( $errors, $username, $email_address, $post ) {
		// If the field is required it should 
		if ( isset($post[self::MOBILE]) && $post[self::MOBILE] == '' ) {
			$errors[] = self::__('"Mobile" is required.');
		}
		return $errors;
	}

	/**
	 * Add the default pane to the account edit form
	 * @param array $panes
	 * @return array
	 */
	public function get_registration_panes( array $panes ) {
		$panes['custom_fields'] = array(
			'weight' => 10,
			'body' => self::rf_load_view_string('panes', array( 'fields' => self::fields() )),
		);
		return $panes;
	}
	
	/**
	 * Add the fields to the registration form
	 * 
	 * @param Group_Buying_Account $account
	 * @return array
	 */
	private function fields( $account = NULL ) {
		$fields = array(
			'source' => array(
				'weight' => 0, // sort order
				'label' => self::__('Mobile'), // the label of the field
				'type' => 'bypass', // type of field (e.g. text, textarea, checkbox, etc. )
				'required' => TRUE, // If this is false then don't validate the post in validate_account_fields
				'output' => self::get_mobile_input( $account )
			),
			// add new fields here within the current array.
		);
		$fields = apply_filters('custom_registration_fields', $fields);
		return $fields;
	}

	/**
	 * Add the default pane to the account edit form
	 * 
	 * @param array $panes
	 * @param Group_Buying_Account $account
	 * @return array
	 */
	public function get_edit_fields( array $panes, Group_Buying_Account $account ) {
		$panes['custom'] = array(
			'weight' => 99,
			'body' => self::rf_load_view_string('panes', array( 'fields' => self::edit_fields($account) )),
		);
		return $panes;
	}

	
	/**
	 * Add the fields to the account form
	 * 
	 * @param Group_Buying_Account $account
	 * @return array
	 */
	private function edit_fields( $account = NULL ) {
		$fields = array(
			'source' => array(
				'weight' => 0, // sort order
				'label' => self::__('Mobile'), // the label of the field
				'type' => 'bypass', // type of field (e.g. text, textarea, checkbox, etc. )
				'required' => TRUE, // If this is false then don't validate the post in validate_account_fields
				'default' => $custom, // the default value
				'output' => self::get_mobile_input( $account )
			),
			// add new fields here within the current array.
		);
		uasort($fields, array(get_class(), 'sort_by_weight'));
		$fields = apply_filters('invite_only_fields', $fields);
		return $fields;
	}

	public function get_mobile_input( $account = NULL, $default_code = '', $default_number = '' ) {
		if ( is_a( $account, 'Group_Buying_Account' ) ) {
			$default_code = get_post_meta( $account->get_ID(), '_'.self::MOBILE_CODE, TRUE );
			$default_number = get_post_meta( $account->get_ID(), '_'.self::MOBILE, TRUE );
		}
		$codes = array(
				'Greece' => '+30',
				'Cyprus' => '+357',
				'Albania' => '+355',
				'Andorra' => '+376',
				'Armenia' => '+374',
				'Australia' => '+61',
				'Austria' => '+43',
				'Belarus' => '+375',
				'Belgium' => '+32',
				'Bosnia and Herzegovina' => '+387',
				'Bulgaria' => '+359',
				'Croatia' => '+385',
				'Czech Republic' => '+420',
				'Denmark' => '+45',
				'Estonia' => '+372',
				'Finland' => '+358',
				'France' => '+33',
				'FYROM' => '+389',
				'Georgia' => '+995',
				'Germany' => '+49',
				'Hungary' => '+36',
				'Iceland' => '+354',
				'Ireland, Republic of' => '+353',
				'Italy' => '+39',
				'Latvia' => '+371',
				'Liechtenstein' => '+423',
				'Lithuania' => '+370',
				'Luxembourg' => '+352',
				'Malta' => '+356',
				'Moldova' => '+373',
				'Monaco' => '+377',
				'Montenegro' => '+382',
				'Netherlands' => '+31',
				'Norway' => '+47',
				'Poland' => '+48',
				'Portugal' => '+351',
				'Romania' => '+40',
				'Russia' => '+7',
				'San Marino' => '+378',
				'Serbia' => '+381',
				'Slovakia' => '+421',
				'Slovenia' => '+386',
				'Spain' => '+34',
				'Sweden' => '+46',
				'Switzerland' => '+41',
				'Turkey' => '+90',
				'Ukraine' => '+380',
				'United Kingdom' => '+44',
				'United States' => '+1'
			);
		ob_start();
		?>
			<select name="<?php echo self::MOBILE_CODE ?>" class="mobile_code_select">
				<?php foreach ( $codes as $country => $code ): ?>
					<option value="<?php echo $code ?>" <?php selected( $default_code, $code ) ?>><?php echo $country ?> (<?php echo $code ?>)</option>
				<?php endforeach ?>
			</select>
			<input type="text" name="<?php echo self::MOBILE ?>" value="<?php echo $default_number ?>" placeholder="xxx-xxx-xxxx" class="mobile_field_input"/>
		<?php
		return ob_get_clean();
		
	}
	
	/**
	 * return a view as a string.
	 * 
	 */
	private static function rf_load_view_string( $path, $args ) {
		ob_start();
		if (!empty($args)) extract($args);
		@include('views/'.$path.'.php');
		return ob_get_clean();
	}
}