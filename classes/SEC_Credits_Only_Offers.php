<?php

/**
* Dynamic Rewards
*/
class SEC_Credits_Only_Offers extends Group_Buying_Controller {
	const TAX = 'sec_credit_purchase_only';
	const TERM = 'credit-purchase-only';

	public static function init() {
		// Meta Boxes
		add_action( 'add_meta_boxes', array(get_class(), 'add_meta_boxes'));
		add_action( 'save_post', array( get_class(), 'save_meta_boxes' ), 10, 2 );

		// modify cart
		add_action( 'gb_cart_get_items', array( get_class(), 'maybe_adjust_cart_items' ), 100, 2 );

		// Adjust checkout options
		add_filter( 'gb_payment_fields', array( get_class(), 'payment_fields' ), 10, 3 );

		// Can purchase
		add_filter( 'account_can_purchase', array( get_class(), 'can_purchase_pod' ), 500, 2 );

	}

	public function can_purchase_pod( $qty, $offer_id ) {
		$deal = Group_Buying_Deal::get_instance( $offer_id );
		if ( self::is_pod( $deal ) ) {
			$offer_price = $deal->get_price( $qty );
			$account = Group_Buying_Account::get_instance();
			$reward_balance = $account->get_credit_balance( Group_Buying_Affiliates::CREDIT_TYPE )/Group_Buying_Payment_Processors::get_credit_exchange_rate( Group_Buying_Affiliates::CREDIT_TYPE );
			// basic check to see if the price is more than the balance
			if ( $reward_balance < $offer_price ) {
				return FALSE;
			}
			// check to see if the cart has pods
			$price_of_pods_in_cart = self::cart_have_pod();
			if ( $price_of_pods_in_cart > 0.00 ) {
				// deduct what is in the cart with their balance to prevent add-to-cart
				$reward_balance = $reward_balance-$price_of_pods_in_cart;
				if ( $reward_balance < $offer_price ) {
					return FALSE;
				}
			}
		}
		return $qty;
	}

	////////////////////////
	// Cart Manipulation //
	////////////////////////

	public static function maybe_adjust_cart_items( $products, Group_Buying_Cart $cart ) {
		$pods = array();
		$has_non_pod = FALSE;
		foreach ( $products as $key => $product ) {
			$deal = Group_Buying_Deal::get_instance( $product['deal_id'] );
			if ( is_a( $deal, 'Group_Buying_Deal' ) ) {
				if ( !self::is_pod( $deal ) ) {
					$has_non_pod = TRUE;
				}
				else {
					$pods[] = $key;
				}
			}
		}

		// Check to see that the cart doesn't have too many rewards
		$price_of_pods_in_cart = self::cart_have_pod();
		if ( $price_of_pods_in_cart > 0.00 ) {
			// check if the user has enough rewards
			$reward_balance = $account->get_credit_balance( Group_Buying_Affiliates::CREDIT_TYPE )/Group_Buying_Payment_Processors::get_credit_exchange_rate( Group_Buying_Affiliates::CREDIT_TYPE );
			foreach ( $products as $key => $product ) {
				$deal = Group_Buying_Deal::get_instance( $product['deal_id'] );
				if ( self::is_pod( $deal ) ) {
					$offer_price = $deal->get_price();
					// balance doesn't have enough for this offer.
					if ( $reward_balance < $offer_price ) {
						self::set_message( sprintf( '<a href="%s">%s</a> was removed since it can only be purchased with credits.', get_permalink( $product['deal_id'] ), $deal->get_title() ) );
						unset( $products[$key] );
						$cart->remove_item( $product['deal_id'], $product['data'] );
						// since it's removed there isn't a price to deduct later.
						$offer_price = 0;
					}
					// run a tally.
					$reward_balance = $reward_balance-$offer_price;
				}
			}
		}

		// Check if cart is mixed
		if ( !empty( $pods ) && $has_non_pod ) {
			foreach ( $products as $key => $product ) {
				$deal = Group_Buying_Deal::get_instance( $product['deal_id'] );
				if ( self::is_pod( $deal ) ) {
					self::set_message( sprintf( '<a href="%s">%s</a> was removed since it can only be purchased with credits.', get_permalink( $product['deal_id'] ), $deal->get_title() ) );
					unset( $products[$key] );
					$cart->remove_item( $product['deal_id'], $product['data'] );
				}
			}
		}
		return $products;
	}


	public function payment_fields( $fields, $payment_processor_class, $checkout ) {
		if ( self::cart_have_pod() > 0.00 ) {
			unset( $fields['payment_method'] );
			unset( $fields['account_balance'] );
		}
		return $fields;
	}

	public static function cart_have_pod() {
		$price_of_pods_in_cart = (float) 0;
		$cart = Group_Buying_Cart::get_instance();
		foreach ( $cart->get_products() as $key => $product ) {
			$deal = Group_Buying_Deal::get_instance( $product['deal_id'] );
			if ( self::is_pod( $deal ) ) {
				$price_of_pods_in_cart += $deal->get_price();
			}
		}
		return $price_of_pods_in_cart;

	}

	/////////////////
	// Meta boxes //
	/////////////////

	public static function add_meta_boxes() {
		add_meta_box( 'sec_pod', self::__('Pay by Credits Only'), array( get_class(), 'show_meta_boxes' ), Group_Buying_Deal::POST_TYPE, 'side' );
	}

	public static function show_meta_boxes( $post, $metabox ) {
		$deal = Group_Buying_Deal::get_instance($post->ID);
		switch ( $metabox['id'] ) {
			case 'sec_pod':
				self::show_meta_box($deal, $post, $metabox);
				break;
			default:
				self::unknown_meta_box($metabox['id']);
				break;
		}
	}

	private static function show_meta_box( Group_Buying_Deal $deal, $post, $metabox ) {
		$pod = self::is_pod($deal);
		include('views/pod_metabox.php');
	}
	
	public static function save_meta_boxes( $post_id, $post ) {
		// only continue if it's an account post
		if ( $post->post_type != Group_Buying_Deal::POST_TYPE ) {
			return;
		}
		// don't do anything on autosave, auto-draft, bulk edit, or quick edit
		if ( wp_is_post_autosave( $post_id ) || $post->post_status == 'auto-draft' || defined('DOING_AJAX') || isset($_GET['bulk_edit']) ) {
			return;
		}
		// save all the meta boxes
		$deal = Group_Buying_Deal::get_instance($post_id);
		if ( !is_a($deal, 'Group_Buying_Deal') ) {
			return; // The account doesn't exist
		}
		self::save_meta_box($deal, $post_id, $post);
	}

	private static function save_meta_box( Group_Buying_Deal $deal, $post_id, $post ) {
		$terms = ( isset( $_POST['gb_pod'] ) && $_POST['gb_pod'] == '1' ) ? self::get_term_slug() : null;
		wp_set_object_terms( $post_id, $terms, self::TAX );
	}

	//////////////
	// Utility //
	//////////////

	public static function get_term_slug() {
		$term = get_term_by( 'slug', self::TERM, self::TAX );
		if ( !empty($term->slug) ) {
			return $term->slug;
		} else {
			$return = wp_insert_term(
				self::TERM, // the term 
				self::TAX, // the taxonomy
					array(
						'description'=> 'This is a credit purchase only deal.',
						'slug' => self::TERM, )
				);
			return $return['slug'];
		}

	}

	public static function is_pod( Group_Buying_Deal $deal ) {
		$post_id = $deal->get_ID();
		$terms = wp_get_object_terms( $post_id, self::TAX );
		$term = array_pop( $terms );
		$pod = FALSE;
		if ( !empty($term) && $term->slug = self::get_term_slug() ) {
			$pod = TRUE;
		}
		return $pod;
	}

}
class SEC_Credits_Only_Offer extends Group_Buying_Deal {

	public static function init() {
		// register Locations taxonomy
		$singular = 'COP';
		$plural = 'COPS';
		$taxonomy_args = array(
			'hierarchical' => TRUE,
			'public' => FALSE,
			'show_ui' => FALSE
		);
		self::register_taxonomy( SEC_Credits_Only_Offers::TAX, array( Group_Buying_Deal::POST_TYPE ), $singular, $plural, $taxonomy_args );
	}
}


// Initiate the add-on
class SEC_Credits_Only_Offers_Addon extends Group_Buying_Controller {

	public static function init() {
		// Hook this plugin into the GBS add-ons controller
		add_filter( 'gb_addons', array( get_class(), 'load_addon' ), 10, 1 );
	}

	public static function load_addon( $addons ) {
		$addons['pay_by_credit_offers'] = array(
			'label' => self::__( 'Pay by Credit Only Offers' ),
			'description' => self::__( 'Set an offer to only be purchasable by rewards. An offer with this selection cannot be purchased with any other offer; it will be removed from a cart with any other non pay by credit only offer. It will also not be eligible to purchase if the user does not have enough rewards.' ),
			'files' => array(
				__FILE__,
			),
			'callbacks' => array(
				array( 'SEC_Credits_Only_Offer', 'init' ),
				array( 'SEC_Credits_Only_Offers', 'init' ),
			),
		);
		return $addons;
	}
}