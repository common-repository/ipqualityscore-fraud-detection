var IPQ = {
	Executed: false,
	Callback: function(){
		var page_content = jQuery('.page-content');
		
		if(IPQSData.Abort == 'true'){
			page_content.hide();
			page_content.after(jQuery('.ipqs-loading-bar-template').removeClass('ipqs-loading-bar-template').addClass('ipqs-loading-bar'));
			var loading_bar = jQuery('.ipqs-loading-bar');
			loading_bar.show();
		}
		
		setTimeout(function(){
			Startup.AfterResult(function(result){
				jQuery.ajax({
					type: "POST",
					url: IPQSData.BaseURL + "/?ipqs=dtpost",
					data: jQuery.param(result) + "&order_id=" + IPQSData.OrderID,
					dataType: 'json',
					success: function(data){
						if(data.success){
							loading_bar.hide();
							page_content.show();
						} else {
							if(IPQSData.Abort == 'true'){
								window.location = IPQSData.RejectedURI;
							}
						}
					}
				});
			});

			Startup.Init(); // Start the fraud tracker.
		}, 10);
	}
};