<?php

function SanitizeIPQBoolean($value, $option, $original_value){
	if((string) $value !== '0' &&  (string) $value !== '1'){
		return $original_value;
	}

	return (string) $value;
}

function SanitizeIPQURL($value, $option, $original_value){
	if($value !== ''){
		return esc_url($value);
	}
	
	return $value;
}

function SanitizeIPQNumber($value, $option, $original_value){
	if(is_numeric($value)){
		return (int) $value;
	}
	
	return $original_value;
}

foreach($this->GetSettings() as $setting){
	switch($setting['type']){
		case 'boolean':
			add_filter(sprintf("sanitize_option_%s", $setting['name']), 'SanitizeIPQBoolean', 10, 3);
			break;
		case 'url':
			add_filter(sprintf("sanitize_option_%s", $setting['name']), 'SanitizeIPQURL', 10, 3);
			break;
		case 'number':
			add_filter(sprintf("sanitize_option_%s", $setting['name']), 'SanitizeIPQNumber', 10, 3);
			break;
	}
}

if($_SERVER['REQUEST_METHOD'] == "POST") {
	foreach($this->GetSettings() as $setting){
		if(isset($_POST[$setting['name']])){
			if($setting['type'] === 'boolean'){
				$this->CreateValue($setting['name'], sanitize_option($setting['name'], $_POST[$setting['name']]));
			}
			
			if($setting['type'] === 'url'){
				$this->CreateValue($setting['name'], sanitize_option($setting['name'], $_POST[$setting['name']]));
			}
			
			if($setting['type'] === 'number'){
				if(is_numeric($_POST[$setting['name']])){
					$this->CreateValue($setting['name'], sanitize_option($setting['name'], $_POST[$setting['name']]));
				} else {
					$this->CreateValue($setting['name'], 0);
				}
			}
			
			/*
			* This is sanitized. Only values in the $setting['options'] list are allowed.
			*/
			if($setting['type'] === 'select'){
				$value = (string) $_POST[$setting['name']];
				if(isset($setting['options'][$value])){
					$this->CreateValue($setting['name'], $value);
				}
			}
			
			/*
			* Stub left for if this ever becomes a thing.
			* if($setting['type'] === 'string'){
			*	$this->CreateValue($setting['name'], sanitize_option($setting['name'], $_POST[$setting['name']]));
			* }
			*/
		}
		
		/*
		* This is sanitized. Only values in the $setting['options'] list are allowed.
		*/
		if($setting['type'] === 'multiselect'){
			$store = array();
			if(isset($_POST[$setting['name']])){
				foreach($_POST[$setting['name']] as $value){
					if(isset($setting['options'][$value])){
						$store[] = $value;
					}
				}
			}
			
			$this->CreateValue($setting['name'], json_encode($store));
		}
	}
}

add_action('admin_init', function() {
	foreach($this->GetSettings() as $setting){
		register_setting('ipqualityscore', $setting['name']);
	}
});

function IPQConditions($setting){
	if(isset($setting['conditions'])){
		echo 'data-conditions="'.htmlentities(json_encode($setting['conditions'])).'" ';
	}
	
	echo 'class="'.$setting['name'].'" ';
	echo 'name="'.$setting['name'].(($setting['type'] === 'multiselect') ? '[]' : '').'" ';
	echo 'data-name="'.$setting['name'].(($setting['type'] === 'multiselect') ? '[]' : '').'" ';
}

function IPQOptionJSON($json, $key){
	$json = json_decode($json, true);
	if(is_array($json)){
		if(in_array($key, $json)){
			return true;
		}
	}
	
	return false;
}

wp_enqueue_style('ipq_settings_style_sheet', plugin_dir_url( __FILE__ ) .'assets/settings.css');
?>

<div class="wrap">
	<div id="poststuff" class="metabox-holder has-right-sidebar">
		<div class="inner-sidebar">
			<div id="sm_pnres" class="postbox">
				<div class="inside"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/assets/logo-email.png" class="settings_logo">
				</div>
			</div>
			<div id="side-sortables" class="meta-box-sortabless ui-sortable" style="position:relative;">
				<div id="sm_pnres" class="postbox">
					<h3 class="hndle"><span>Quick Links</span></h3>
					<div class="inside">
						<a class="sm_button" href="#dashboard" onClick="window.open('<?php echo admin_url(); ?>admin.php?page=ipq_dashboard', '_blank')">IPQS User Dashboard</a>
						<a class="sm_button" href="#faq" onClick="window.open('<?php echo admin_url(); ?>admin.php?page=ipq_faq', '_blank')">Help / FAQ</a>
						<a class="sm_button" href="#newticket" onClick="window.open('<?php echo admin_url(); ?>admin.php?page=ipq_new_ticket', '_blank')">Submit a Support Ticket</a>
						<a class="sm_button" href="#plans" onClick="window.open('<?php echo admin_url(); ?>admin.php?page=ipq_plans', '_blank')">Plans &amp; Credits</a>
						<a class="sm_button" href="#whitelist" onClick="window.open('<?php echo admin_url(); ?>admin.php?page=ipq_whitelists', '_blank')">Whitelists</a>
						<a class="sm_button" href="#blacklist" onClick="window.open('<?php echo admin_url(); ?>admin.php?page=ipq_blacklists', '_blank')">Blacklists</a>
					</div>
				</div>
			</div>
		</div>
		<div class="has-sidebar sm-padded">
			<div id="post-body-content" class="has-sidebar-content">
				<div class="meta-box-sortabless">
					<div id="sm_basic_options" class="postbox">
						<form method="post" class="update_settings_form" action="<?php echo admin_url(); ?>admin.php?page=ipq_settings">
							<?php settings_fields( 'ipqualityscore' ); ?>
							<?php do_settings_sections( 'ipqualityscore' ); ?>

							<h1>IPQualityScore WordPress Settings</h1>
							<div style="text-align:right;padding-right:5px;">
								<input type="button" class="button button-primary update_settings" value="Save Changes" style="margin-top:5px;">
							</div>
							<br>
							<div>
								<nav class="nav-tab-wrapper" style="height:40px;">
									<a href="#site-wide" class="nav-tab nav-tab-active">Site Wide</a>
									<a href="#email" class="nav-tab">Email Validation</a>
									<a href="#pages" class="nav-tab">Pages & Posts</a>
									<a href="#users" class="nav-tab">Users</a>
									<?php if(defined('WC_VERSION')){ ?>
										<a href="#orders" class="nav-tab">Orders</a>
									<?php } ?>
									<?php if ( class_exists( 'GFCommon' ) ) { ?>
										<a href="#gravity" class="nav-tab">Gravity Forms</a>
									<?php } ?>
								</nav>

								<div class="tab-content" id="nav-tabContent" style="display:none">
									<div class="tab-pane fade show active" id="site-wide" role="tabpanel" aria-labelledby="site-wide">
										<table class="form-table">
											<?php foreach($this->GetSettings() as $setting){ ?>
												<?php if($setting['group'] === 'site'){ ?>
													<?php include('SettingsTable.php'); ?>
												<?php } ?>
											<?php } ?>
										</table>
									</div>
									<div class="tab-pane fade show" id="email" role="tabpanel" aria-labelledby="email">
										<table class="form-table">
											<?php foreach($this->GetSettings() as $setting){ ?>
												<?php if($setting['group'] === 'email'){ ?>
													<?php include('SettingsTable.php'); ?>
												<?php } ?>
											<?php } ?>
										</table>
									</div>
									<div class="tab-pane fade show" id="pages" role="tabpanel" aria-labelledby="pages">
										<table class="form-table">
											<?php foreach($this->GetSettings() as $setting){ ?>
												<?php if($setting['group'] === 'pages'){ ?>
													<?php include('SettingsTable.php'); ?>
												<?php } ?>
											<?php } ?>
										</table>
									</div>
									<div class="tab-pane fade show" id="users" role="tabpanel" aria-labelledby="users">
										<table class="form-table">
											<?php foreach($this->GetSettings() as $setting){ ?>
												<?php if($setting['group'] === 'users'){ ?>
													<?php include('SettingsTable.php'); ?>
												<?php } ?>
											<?php } ?>
										</table>
									</div>
									<div class="tab-pane fade show" id="orders" role="tabpanel" aria-labelledby="orders">
										<table class="form-table">
											<?php foreach($this->GetSettings() as $setting){ ?>
												<?php if($setting['group'] === 'orders'){ ?>
													<?php include('SettingsTable.php'); ?>
												<?php } ?>
											<?php } ?>
										</table>
									</div>
									<div class="tab-pane fade show" id="gravity" role="tabpanel" aria-labelledby="gravity">
										<table class="form-table">
											<?php foreach($this->GetSettings() as $setting){ ?>
												<?php if($setting['group'] === 'gravity'){ ?>
													<?php include('SettingsTable.php'); ?>
												<?php } ?>
											<?php } ?>
										</table>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>