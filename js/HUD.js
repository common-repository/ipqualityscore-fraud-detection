var HUD = {
	Init: function(){
		jQuery('.hud').each(function(){
			jQuery.ajax({
				url : jQuery('.wrap').attr('data-url') + 'admin.php?page=ipq_hud_json&type=' + encodeURIComponent(jQuery(this).attr('data-type')),
				type: "POST",
				dataType : "json",
				success: function(response){
					if(response.graph.length > 1){
						jQuery('#hud').css('height', '400px');
						google.charts.load('current', {'packages':['bar']});
						google.charts.setOnLoadCallback(function drawChart() {
							var data = google.visualization.arrayToDataTable(response.graph);
							var options = {
								title: 'Daily Fraud Average',
								hAxis: {title: "Date - Request Count",  titleTextStyle: {color: '#333'}},
								vAxis: {minValue: 0}
							};

							var chart = new google.charts.Bar(document.getElementById('hud'));
							chart.draw(data, google.charts.Bar.convertOptions(options));
						});
					} else {
						jQuery('#hud').css('height', '50px');
						jQuery('#hud').html('<div align="center">Unfortunately there are no statistics available right now. Please send some traffic in order for us to begin showing statistics.</div>');
					}
				}
			});
		});
		
		jQuery('.gbu_holder').each(function(){
			HUD.GBUReload(jQuery(this).val());
		});
		
		jQuery('body').on('change', '.gbu_timeframe', function(){
			HUD.GBUReload(jQuery(this).val());
		});
		
		jQuery('.custom-datatable').dataTable({
			"order": [[ 5, "desc" ]]
		});
	},
	GBUReload: function(time){
		jQuery.ajax({
			url :  jQuery('.wrap').attr('data-url') + 'admin.php?page=ipq_gbu_json&type=' + encodeURIComponent(jQuery('.gbu').attr('data-type')),
			type: "POST",
			dataType : "json",
			data: 'timeframe=' + time,
			success: function(response){
				if(response.graph.length > 1){
					if(response.graph[1][1] == 0 && response.graph[2][1] == 0 && response.graph[3][1] == 0){
						jQuery('.gbu').css('height', '50px');
						jQuery('.gbu').html('<br><div class="alert alert-warning">There are no records for this time period.</div>');
					} else {
						jQuery('.gbu').css('height', '400px');
						google.charts.load('current', {'packages':['corechart']});
						google.charts.setOnLoadCallback(function drawChart() {
							var data = google.visualization.arrayToDataTable(response.graph);
							var options = {
								title : "Quality Breakdown",
								pieHole: 0.4,
								sliceVisibilityThreshold:0,
								slices: {
									0: { color: 'green' },
									1: { color: 'orange' },
									2: { color: 'red' }
								}
							};

							var chart = new google.visualization.PieChart(document.getElementById('gbu'));
							chart.draw(data, options);
						});
					}
				}
			}
		});
	}
};

jQuery(document).ready(function(){
	HUD.Init();
});