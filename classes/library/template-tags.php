<?php 

///////////////////
// Merchant Meta //
///////////////////

function gb_get_is_featured_merchant( $merchant_id = 0 ) {
	if ( !$merchant_id ) {
		$merchant_id = gb_account_merchant_id();
	}
	$merchant = Group_Buying_Merchant::get_instance( $merchant_id );
	return apply_filters( 'gb_get_is_featured_merchant', GB_Merchant_Meta::is_featured( $merchant ) );
}
function gb_get_featured_merchant_url() {
	return apply_filters('gb_get_featured_merchant_url',GB_Merchant_Meta::get_url());
}
	function gb_featured_merchant_url() {
		$url = gb_get_featured_merchant_url();
		if ( !is_a($url,'WP_Error')) {
			echo apply_filters('gb_featured_merchant_url',$url);
		}
	}

function sec_can_only_be_purchased_with_rewards( $offer_id = 0 ) {
	if ( !$offer_id ) {
		$offer_id = get_the_ID();
	}
	$offer = Group_Buying_Deal::get_instance( $offer_id );
	return SEC_Credits_Only_Offers::is_pod( $offer );
}