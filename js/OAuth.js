jQuery(document).ready(function() {
	var height = jQuery(window).height() - 110;
	var base = jQuery('.wrap').attr('data-domain');
	var local = jQuery('.wrap').attr('data-localhost');
	jQuery('.wrap').html('<iframe src="' + base + '/oauth/Wordpress/login?domain=' + encodeURIComponent(local) + '" style="width:100%;height:' + height + 'px" />');
});