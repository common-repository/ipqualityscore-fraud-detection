var Overview = {
	Init: function(){
		jQuery.ajax({
			url : '/wp-admin/admin.php?page=ipq_dashboard_json',
			type: "POST",
			dataType : "json",
			success: function(response){
				google.charts.load('current', {packages: ['corechart', 'bar']});
				google.charts.setOnLoadCallback(function(){
					jQuery('.loading').hide();
					jQuery('.chart').each(function(){
						var data = google.visualization.arrayToDataTable(response.statistics);
						var materialOptions = {
							chart: {
								title: 'API Calls By Hour',
								subtitle: 'Based on estimated activity.'
							},
							bars: 'vertical'
						};

						var materialChart = new google.charts.Bar(document.getElementById(jQuery(this).attr('id')));
						materialChart.draw(data, materialOptions);
					});
				});
			}
		});
	}
}

jQuery(document).ready(function(){
	Overview.Init();
})