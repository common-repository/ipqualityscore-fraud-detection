<?php wp_enqueue_style('ipq_wrapper_base', plugin_dir_url( __FILE__ ) .'assets/wrapper-base.css'); ?>
<?php wp_enqueue_style('ipq_wrapper', plugin_dir_url( __FILE__ ) .'assets/wrapper.css'); ?>

<div class="wrap" data-url="<?php echo htmlentities(admin_url()); ?>">
	<?php echo $content; ?>
</div>