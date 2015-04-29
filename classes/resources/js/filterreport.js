jQuery(document).ready(function($){
	jQuery('#fromdate').datepicker({
		dateFormat : 'dd-mm-yy',
		onSelect: function(selected) {
		  jQuery("#todate").datepicker("option","minDate", selected)
		}
	});
	jQuery('#todate').datepicker({
		dateFormat : 'dd-mm-yy',
		maxDate: new Date(),
		onSelect: function(selected) {
		  jQuery("#fromdate").datepicker("option","maxDate", selected)
		}
	});
	jQuery('.date_filter .form-submit').click(function(){
			var relUrl	=	document.URL;
			
			var fromdate	=	jQuery("#fromdate").val();		
			var todate		=	jQuery("#todate").val();
			
			if( relUrl.indexOf( '?' ) != -1 ){
				relUrl += '&action=filter';
			}else{
				relUrl += '?action=filter';
			}
			relUrl	+= '&fromdate='+fromdate+'&todate='+todate;
			
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