<div class="date_filter">
	<span class="date_dropdown">
		<select name="predefined_dates">
			<option></option>
			<option value="today"><?php gb_e( 'Today' ) ?></option>
			<option value="last_7days"><?php gb_e( 'Last 7 days' ) ?></option>
			<option value="this_week"><?php gb_e( 'This week' ) ?></option>
			<option value="last_week"><?php gb_e( 'Last week' ) ?></option>
			<option value="this_month"><?php gb_e( 'This month' ) ?></option>
			<option value="last_month"><?php gb_e( 'Last month' ) ?></option>
		</select>
	</span>
  <?php 
  	$start_time = ( isset( $_REQUEST['reports_start_date'] ) && strtotime( $_REQUEST['reports_start_date'] ) <= current_time( 'timestamp' ) ) ? strtotime( $_REQUEST['reports_start_date'] ) : current_time( 'timestamp' )-604800;
  	$end_time = ( isset( $_REQUEST['reports_end_date'] ) && strtotime( $_REQUEST['reports_end_date'] ) <= current_time( 'timestamp' ) ) ? strtotime( $_REQUEST['reports_end_date'] ) : current_time( 'timestamp' );
  	?>
	<?php gb_e( 'From:' ); ?> <input type="text" name="reports_start_date" id="reports_start_date" value="<?php echo date( 'm/d/Y', $start_time ); ?>" >
	<?php gb_e( 'To:' ); ?>   <input type="text" name="reports_end_date" id="reports_end_date" value="<?php echo date( 'm/d/Y', $end_time ); ?>" >
	<input type="button" value="<?php gb_e( 'Submit' ); ?>" value="submit" class="form-submit">
</div>	
<div class="reportblock report">