<div class="date_filter">
	<span class="date_dropdown">
		<select name="predefined_dates">
			<option><?php gb_e( 'All' ) ?></option>
			<option value="today"><?php gb_e( 'Today' ) ?></option>
			<option value="last_7days"><?php gb_e( 'Last 7 days' ) ?></option>
			<option value="this_week"><?php gb_e( 'This week' ) ?></option>
			<option value="last_week"><?php gb_e( 'Last week' ) ?></option>
			<option value="this_month"><?php gb_e( 'This month' ) ?></option>
			<option value="last_month"><?php gb_e( 'Last month' ) ?></option>
		</select>
	</span>
	<?php gb_e( 'From:' ); ?> <input type="text" name="fromdate" id="fromdate">
	<?php gb_e( 'To:' ); ?>   <input type="text" name="todate" id="todate">
	<input type="button" value="<?php gb_e( 'Submit' ); ?>" value="submit" class="form-submit">
</div>	
<div class="reportblock">