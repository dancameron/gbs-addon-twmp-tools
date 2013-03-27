<?php
/*
Plugin Name: Group Buying Addon - GBS Tools
Version: 0.9
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
if ( !function_exists('gbs_twmp_toolchain_load') ) { // play nice
	function gbs_twmp_toolchain_load() {
		require dirname(__FILE__).'/classes/gbsVoucher.class.php';
		require dirname(__FILE__).'/classes/gbsRewards.class.php';
		require dirname(__FILE__).'/classes/gbsCredits.class.php';
		GBS_Vouchers_Extension::init();
		GB_Affiliates_Ext::init();
		GBS_Credits_Extension::init();
	}
	add_action( 'plugins_loaded', 'gbs_twmp_toolchain_load', 1000 ); // Attempt to load up the toolchain after the other plugins.
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