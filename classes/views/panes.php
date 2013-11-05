<fieldset id="gb-account-fields-info">
	<h2 class="register_section_heading gb_ff"><?php self::_e('Additional Information'); ?></h2>
	<table class="account-fields collapsable subscription form-table">
		<tbody>
			<?php foreach ( $fields as $key => $data ): ?>
				<tr>
					<?php if ( $data['type'] != 'checkbox' ): ?>
						<td><?php gb_form_label($key, $data, 'account_fields'); ?></td>
						<td>
							<?php gb_form_field($key, $data, 'account_fields'); ?>
							<?php if ( $data['desc'] != '' ): ?>
								<br/><small><?php echo $data['desc']  ?></small>	
							<?php endif ?>
						</td>
					<?php else: ?>
						<td colspan="2">
							<label for="gb_account_<?php echo $key; ?>"><?php gb_form_field($key, $data, 'account_fields'); ?> <?php echo $data['label']; ?></label>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</fieldset>