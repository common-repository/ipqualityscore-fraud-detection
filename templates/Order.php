<?php 
$script_src = '%s/api/%s/%s/learn.js';
wp_enqueue_script('ipq_tracker', sprintf($script_src,
	static::BASE_URL,
	get_option('ipq_tracker_domain'),
	get_option('ipq_tracker_key')
));
wp_enqueue_script('ipq_order', plugin_dir_url( __FILE__ ).'assets/order.js', ['jquery', 'ipq_tracker']);
?>