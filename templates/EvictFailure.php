<div class="wrap" align="center">
	We were unfortunately unable to validate your installation of IPQualityScore. Please contact us if this error persists.
</div>
<?php
$evict_failure = <<<'SCRIPT'
	if (top.location != location) {
		top.location.href = document.location.href;
	}
SCRIPT;

wp_add_inline_script('evict_failure', $evict_failure);
?>