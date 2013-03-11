<?php
/*
Plugin Name: Group Buying Addon - GBS Tools
Version: 0.1
Description: Multiple Add-ons and Customizations for the the GBS platform.
Plugin URI: http://groupbuyingsite.com/
Author: GroupBuyingSite.com
Author URI: http://groupbuyingsite.com/features
Plugin Author: Dan Cameron
Plugin Author URI: http://sproutventure.com/
Contributors: Dan Cameron
Text Domain: group-buying
*/


/**
 * Load all the plugin files and initialize appropriately
 *
 * @return void
 */
if ( !function_exists('gbs_twmp_tools_load') ) { // play nice
	function gbs_twmp_tools_load( $addons ) {
		$addons['twmp_tools'] = array(
			'label' => __( 'GBS Tools' ),
			'description' => __( 'Multiple Add-ons and Customizations for the the GBS platform.' ),
			'files' => array(
				dirname(__FILE__).'/classes/gbsCredits.class.php',
				dirname(__FILE__).'/classes/gbsRewards.class.php',
				dirname(__FILE__).'/classes/gbsVouchers.class.php'
			),
			'callbacks' => array(
				array( 'GBS_Vouchers_Extension', 'init' ),
				array( 'GBS_Rewards_Extension', 'init' ),
				array( 'GBS_Credits_Extension', 'init' ),
			),
		);
		return $addons;
	}

	add_filter('gb_addons', 'gbs_twmp_tools_load', 10, 1);
}

/**
 * Load up the payment processor
 */
if ( !function_exists( 'gb_load_custom_offsite_purchase_gateway' ) ) {
	function gb_load_custom_offsite_purchase_gateway() {
		require_once( dirname(__FILE__). '/classes/payment_processors/OffsitePayments.class.php');
		Group_Buying_Offsite_Manual_Purchasing_Custom::register();
	}

	add_action('gb_register_processors', 'gb_load_custom_offsite_purchase_gateway');
}

