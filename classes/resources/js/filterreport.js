jQuery(document).ready(function($){
	jQuery('#reports_start_date').datepicker({
		dateFormat : 'dd-mm-yy',
		onSelect: function(selected) {
		  jQuery("#reports_end_date").datepicker("option","minDate", selected)
		}
	});
	jQuery('#reports_end_date').datepicker({
		dateFormat : 'dd-mm-yy',
		maxDate: new Date(),
		onSelect: function(selected) {
		  jQuery("#reports_start_date").datepicker("option","maxDate", selected)
		}
	});
	jQuery('.date_filter .form-submit').click(function(){
			var relUrl	=	document.URL;
			
			var fromdate	=	jQuery("#reports_start_date").val();		
			var todate		=	jQuery("#reports_end_date").val();
			
			if( relUrl.indexOf( '?' ) != -1 ){
				relUrl += '&action=filter';
			}else{
				relUrl += '?action=filter';
			}
			relUrl	+= '&reports_start_date='+fromdate+'&reports_end_date='+todate;
			
			jQuery.ajax({
				type: "GET",
				headers: { "cache-control": "no-cache" },
				cache: false,
				url: relUrl,
				dataType: "html",
				success: function(out){
					
					var result = jQuery(out).find('.report table');
					jQuery('.reportblock').html(result);
					
					jQuery("#report_rows tr:odd").addClass("odd");
					jQuery("#report_rows tr:not(.odd)").addClass("even");  
						
					jQuery(".report table").tablesorter({debug:true});
					

					if(jQuery.trim(jQuery('.reportblock').html())==""){
						jQuery('.reportblock').html('<div class="nodata">No Data</div>');
					}
									
				},
				complete: function() {
					
				}
			});
	});
	jQuery('select[name="predefined_dates"]').change(function(){
			var relUrl	=	document.URL;
						
			if( relUrl.indexOf( '?' ) != -1 ){
				relUrl += '&action=filter';
			} else {
				relUrl += '?action=filter';
			}
			relUrl	+= '&pre_filter='+$( this ).val();
			
			jQuery.ajax({
				type: "GET",
				headers: { "cache-control": "no-cache" },
				cache: false,
				url: relUrl,
				dataType: "html",
				success: function(out){
					
					var result = jQuery(out).find('.report table');
					jQuery('.reportblock').html(result);
					
					jQuery("#report_rows tr:odd").addClass("odd");
					jQuery("#report_rows tr:not(.odd)").addClass("even");  
						
					jQuery(".report table").tablesorter({debug:true});
					

					if(jQuery.trim(jQuery('.reportblock').html())==""){
						jQuery('.reportblock').html('<div class="nodata">No Data</div>');
					}
									
				},
				complete: function() {
					
				}
			});
	});
});