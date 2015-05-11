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

function sec_can_only_be_purchased_with_rewards( $offer_id = 0, $check_rewards = TRUE ) {
	if ( !$offer_id ) {
		$offer_id = get_the_ID();
	}
	$offer = Group_Buying_Deal::get_instance( $offer_id );
	return SEC_Credits_Only_Offers::is_pod( $offer );
}

function sec_can_be_purchased_with_rewards( $offer_id = 0, $check_rewards = TRUE ) {
	if ( !$offer_id ) {
		$offer_id = get_the_ID();
	}
	$offer = Group_Buying_Deal::get_instance( $offer_id );
	$is_pod = SEC_Credits_Only_Offers::is_pod( $offer );
	if ( ! $is_pod ) {
		return true;
	}
	$offer_price = $offer->get_price( 1 );
	$account = Group_Buying_Account::get_instance();
	$reward_balance = $account->get_credit_balance( Group_Buying_Affiliates::CREDIT_TYPE ) / Group_Buying_Payment_Processors::get_credit_exchange_rate( Group_Buying_Affiliates::CREDIT_TYPE );
	if ( $reward_balance < $offer_price ) {
		return false;
	}

	$price_of_pods_in_cart = SEC_Credits_Only_Offers::cart_have_pod( $offer_id );
	if ( $price_of_pods_in_cart > 0.00 ) {
		// deduct what is in the cart with their balance to prevent add-to-cart
		$reward_balance = $reward_balance - $price_of_pods_in_cart;
		if ( $reward_balance < $offer_price ) {
			return false;
		}
	}
	return true;
}