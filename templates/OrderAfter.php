<?php
wp_enqueue_script('ipq_order', plugin_dir_url( __FILE__ ).'assets/order_after.js', ['jquery', 'ipq_tracker']);
wp_localize_script('ipq_order', 'IPQSData', ['OrderID' => $order_id, 'BaseURL' => get_home_url(), 'RejectedURI' => get_post_permalink(get_option('ipq_block_order_post_id')), 'Abort' => (get_option('ipq_device_tracker_orders') == '4') ? 'true' : 'false']);

$script_src = '%s/api/%s/%s/learn.js';
wp_enqueue_script('ipq_tracker', sprintf($script_src,
	get_option('ipq_custom_js_domain') !== '' ? get_option('ipq_custom_js_domain') : static::BASE_URL,
	get_option('ipq_tracker_domain'),
	get_option('ipq_tracker_key')
));
?>
<div class="ipqs-loading-bar-template" style="display:none;">
	<?php echo get_post_field('post_content', get_option('ipq_loading_order_post_id')); ?>
</div>