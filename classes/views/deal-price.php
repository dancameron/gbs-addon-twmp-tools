<?php do_action( 'gb_meta_box_deal_price_pre', $price, $dynamic_price, $shipping, $shippable, $shipping_dyn, $shipping_mode, $tax, $taxable, $taxrate ) ?>
<script type="text/javascript">
	var gb_currency_symbol = "<?php gb_currency_symbol(); ?>";
</script>
<div id="deal_pricing_meta_wrap" class="clearfix">
	<div class="clearfix">
		<p>
			<label for="deal_base_price"><strong><?php gb_e( 'Price' ); ?>:</strong></label>
			&nbsp;
			<?php gb_currency_symbol();  ?><input id="deal_base_price" type="text" size="5" value="<?php echo $price; ?>" name="deal_base_price" />
		</p>
		<?php do_action( 'gb_meta_box_deal_price_left', $price, $dynamic_price, $shipping, $shippable, $shipping_dyn, $shipping_mode, $tax, $taxable, $taxrate ) ?>
	</div>
	<div class="clearfix">
		<div id="dynamic_pricing_alt">
			<legend><h3><?php gb_e( 'Dynamic Pricing' ); ?></h3></legend>
			<table id="dyn_price_table_alt" class="widefat">
				<thead>
					<tr>
						<th class="left"><?php gb_e( 'Purchases' ); ?></th>
						<th><?php gb_e( 'Price' ); ?></th>
						<th><?php gb_e( 'Reward' ); ?></th>
						<th><?php gb_e( 'Remove' ); ?></th>
					</tr>
				</thead>

				<tbody id="dynamic_costs">
					<tr>
						<td class="centered_text">
							<input type="text" name="dynamic_purchase_total" id="dynamic_purchase_total" class="tiny-text" size="5"/>
						</td>
						<td>
							<?php gb_currency_symbol(); ?><input type="text" name="dynamic_purchase_cost" id="dynamic_purchase_cost" class="tiny-text" size="5"/>
						</td>
						<td>
							&nbsp;
						</td>
						<td>
							<a id="add_dyn_cost" class="add-dyn-cost button hide-if-no-js"><?php gb_e( 'Add' ); ?></a>
						</td>
					</tr>
					<?php
if ( is_array( $dynamic_price ) ) {
	foreach ( $dynamic_price as $total => $cost ) {

		$reward = SEC_Dynamic_Rewards::get_reward( $total );
?>
									<tr>
										<td class="centered_text">
											<?php echo $total; ?>
										</td>
										<td>
											<?php gb_currency_symbol(); ?><input type="text" name="deal_dynamic_price[<?php echo $total; ?>]" value="<?php echo $cost; ?>" class="tiny-text" size="5">
										</td>
										<td>
											<input type="text" name="deal_dynamic_reward[<?php echo $total; ?>]" value="<?php echo $reward; ?>" class="tiny-text" size="5"><span class="dashicons dashicons-awards"></span>
										</td>
										<td>
											<a id="delete_dyn_cost" class="delete-dyn-cost button hide-if-no-js"><?php gb_e( 'Remove' )  ?></a>
										</td>
									</tr>
								<?php
	}
}
?>
				</tbody>
			</table>
		</div>
		<?php do_action( 'gb_meta_box_deal_price_right', $price, $dynamic_price, $shipping, $shippable, $shipping_dyn, $shipping_mode, $tax, $taxable, $taxrate ) ?>
	</div>
</div>


<?php do_action( 'gb_meta_box_deal_price', $price, $dynamic_price, $shipping, $shippable, $shipping_dyn, $shipping_mode, $tax, $taxable, $taxrate ) ?>
