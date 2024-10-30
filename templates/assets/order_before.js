var IPQ = {
	Callback: function(){
		Startup.AfterResult(function(r){
			jQuery.each(r, function(k, v){
				jQuery('form.checkout').append(jQuery('<input type="hidden">').attr('name', k).val(v));
			});
		});

		Startup.Init();
	}
};