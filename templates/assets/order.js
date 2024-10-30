jQuery(document).ready(function(){
	var result;
	jQuery('form.checkout').addClass('processing');
	Startup.Trigger('#place_order', function(){
		jQuery('form.checkout').find('input, select, textarea').each(function(k, field){
			var f = jQuery(field);
			Startup.Field(f.attr('name'), '#' + f.attr('id'));
		});
	});

	Startup.AfterResult(function(r){
		result = r;
		jQuery.each(r, function(k, v){
			jQuery('form.checkout').append(jQuery('<input type="hidden">').attr('name', k).val(v));
		});

		jQuery('form.checkout').removeClass('processing');
		jQuery('form.checkout').submit();
	});
});