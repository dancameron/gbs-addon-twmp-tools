<?php

/**
* Dynamic Rewards
*/
class SEC_Dynamic_Rewards extends Group_Buying_Controller {
	const REWARD_META_KEY = 'gb_offers_meta_key';

	public static function init() {

		add_filter( 'group_buying_template_meta-boxes/offer-type-deal/price.php', array( __CLASS__, 'modify_reward_meta_box' ) );
		add_filter( 'group_buying_template_meta_boxes/deal-price.php', array( __CLASS__, 'modify_reward_meta_box' ) );

		add_action( 'save_post', array( __CLASS__, 'save_meta_boxes' ), 10, 2 );

		// filter in for Deal Based Rewards support
		add_filter( 'sec_deal_based_credit', array( __CLASS__, 'deal_based_credit' ), 10, 2 );
	}

	public static function deal_based_credit( $credit, $offer ) {
		$reward = self::get_reward( null, $offer, $credit );
		if ( is_numeric( $reward ) ) {
			return $reward;
		}
		return $credit;
	}

	public static function modify_reward_meta_box( $file ) {
		return dirname( __FILE__ ) . '/views/deal-price.php';
	}


	public static function save_meta_boxes( $post_id, $post ) {
		// Don't save meta boxes when the importer is used.
		if ( isset( $_GET['import'] ) && $_GET['import'] == 'wordpress' ) {
			return;
		}

		// only continue if it's a deal post
		if ( $post->post_type != Group_Buying_Deal::POST_TYPE ) {
			return;
		}
		// don't do anything on autosave, auto-draft, bulk edit, or quick edit
		if ( wp_is_post_autosave( $post_id ) || $post->post_status == 'auto-draft' || defined( 'DOING_AJAX' ) || isset( $_GET['bulk_edit'] ) ) {
			return;
		}
		// Since the save_box_gb_deal_[meta] functions don't check if there's a _POST, a nonce was added to safe guard save_post actions from ... scheduled posts, etc.
		if ( !isset( $_POST['gb_deal_submission'] ) && ( empty( $_POST ) || !check_admin_referer( 'gb_save_metaboxes', 'gb_save_metaboxes_field' ) ) ) {
			return;
		}

		// save all the meta boxes
		$offer = Group_Buying_Deal::get_instance( $post_id );

		$rewards = array();
		if ( isset( $_POST['deal_dynamic_reward'] ) ) {
			if ( is_numeric( $_POST['deal_dynamic_reward'] ) ) {
				$rewards[0] = $_POST['deal_dynamic_reward'];
			}
			$dynamic_rewards = isset( $_POST['deal_dynamic_reward'] ) ? (array) $_POST['deal_dynamic_reward'] : array();
			foreach ( $dynamic_rewards as $qty => $reward ) {
				if ( is_numeric( $qty ) && is_numeric( $reward ) ) {
					$rewards[(int)$qty] = $reward;
				}
			}
		}
		if ( empty($rewards) ) {
			return;
		}
		ksort( $rewards );
		self::set_rewards( $rewards, $offer );

	}

	public function set_rewards( $rewards = array(), $offer ) {
		$base = 0;
		$dynamic = array();
		foreach ( $rewards as $qty => $reward ) {
			if ( $qty == 0 ) {
				$base = $reward;
			} else {
				$dynamic[$qty] = $reward;
			}
		}
		$offer->save_post_meta( array(
				self::REWARD_META_KEY => $dynamic,
			) );
	}

	public static function get_dynamic_reward( $offer = 0 ) {
		if ( !$offer ) {
			$offer = Group_Buying_Deal::get_instance( get_the_ID() );
		}
		$dynamic_reward = $offer->get_post_meta( self::REWARD_META_KEY );
		if ( empty( $dynamic_reward ) ) return;
		return (array)$dynamic_reward;
	}

	public static function get_reward( $qty = NULL, $offer = 0, $original_credit = 0 ) {
		if ( !is_a( $offer, 'Group_Buying_Deal' ) ) {
			$offer = Group_Buying_Deal::get_instance( get_the_ID() );
		}
		if ( is_null( $qty ) ) {
			$qty = $offer->get_number_of_purchases();
		}

		$dynamic_rewards = self::get_dynamic_reward( $offer );
		if ( 0 == $qty ) {
			$reward = apply_filters( 'gb_tw_deal_get_reward', $original_credit, $offer, $qty );
			return $reward;
		}

		$max_qty_found = 0;
		if ( !empty( $dynamic_rewards ) ) {
			foreach ( $dynamic_rewards as $qty_required => $new_reward ) {
				if ( $qty >= $qty_required && $qty_required > $max_qty_found ) {
					$reward = $new_reward;
					$max_qty_found = $qty_required;
				}
			}
		}

		return apply_filters( 'gb_tw_deal_get_reward', $reward, $offer, $qty );
	}


}



// Initiate the add-on
class SEC_Dynamic_Rewards_Addon extends Group_Buying_Controller {

	public static function init() {
		// Hook this plugin into the GBS add-ons controller
		add_filter( 'gb_addons', array( get_class(), 'load_addon' ), 10, 1 );
	}

	public static function load_addon( $addons ) {
		$addons['cashback_dynamic_rewards'] = array(
			'label' => self::__( 'Dynamic Cashback Rewards' ),
			'description' => self::__( 'Set cashback rewards based on dynamic purchasing.' ),
			'files' => array(
				__FILE__,
			),
			'callbacks' => array(
				array( 'SEC_Dynamic_Rewards', 'init' ),
			),
		);
		return $addons;
	}
}