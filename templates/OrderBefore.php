<?php
if(get_option('ipq_device_tracker_orders') == '1'){
	wp_enqueue_script('ipq_order', plugin_dir_url( __FILE__ ).'assets/order_before.js', ['jquery']);
} elseif(get_option('ipq_device_tracker_orders') == '5') {
	wp_enqueue_script('ipq_order', plugin_dir_url( __FILE__ ).'assets/order_before_block.js', ['jquery']);
} elseif(get_option('ipq_device_tracker_orders') == '6'){
	wp_enqueue_script('ipq_order', plugin_dir_url( __FILE__ ).'assets/order_before_wait_new.js', ['jquery']);
}

wp_localize_script('ipq_order', 'IPQSData', ['MaxWait' => get_option('ipq_max_wait_device_tracker_orders')]);

$script_src = '%s/api/%s/%s/learn.js';
wp_enqueue_script('ipq_tracker', sprintf($script_src,
	get_option('ipq_custom_js_domain') !== '' ? get_option('ipq_custom_js_domain') : static::BASE_URL,
	get_option('ipq_tracker_domain'),
	get_option('ipq_tracker_key')
), ['ipq_order']);
