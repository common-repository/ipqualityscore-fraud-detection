var IPQ = {
	Setup: false,
	HasReturned: false,
	Finished: false,
	TotalWait: 0,
	HasSubmitted: false,
	Callback: function(){
		IPQ.Setup = true;
		jQuery('form.checkout').addClass('processing');
		Startup.AfterResult(function(r){
			jQuery.each(r, function(k, v){
				jQuery('form.checkout').append(jQuery('<input type="hidden">').attr('name', k).val(v));
			});
			
			IPQ.HasReturned = true;
		});

		Startup.Init();
	},
	EventualSubmit(){
		if(IPQ.HasReturned == true || IPQ.TotalWait > IPQSData.MaxWait){
			if(IPQ.HasReturned == false){
				jQuery('form.checkout').append(jQuery('<input type="hidden">').attr('name', 'no_dt_submitted').val('1'));
			}
			
			if(IPQ.HasSubmitted === false){
				IPQ.HasSubmitted = true;
				jQuery('form.checkout').removeClass('processing');
				jQuery('form.checkout').submit();
			}
		} else {
			IPQ.TotalWait = IPQ.TotalWait + 50;
			setTimeout(function(){
				IPQ.EventualSubmit();
			}, 50);
		}
	}
};

jQuery(document).ready(function(){
	jQuery('form.checkout').addClass('processing');
	jQuery('body').on('click', '#place_order', function(e){
		e.preventDefault();

		if(IPQ.Setup === false){
			IPQ.EventualSubmit();
		}
	});
});