<?php
/*
* Plugin Name: IPQualityScore Fraud Detection
* Plugin URI: https://www.ipqualityscore.com
* Description: IPQualityScore Fraud Detection and Fraud Prevention Tools identify malicious behavior and fraudulent activity on your site including WooCommerce orders. These features include Proxy & VPN Detection, Email Address Verification, Disposable Email Detection, Device Fingerprinting, & Transaction Scoring. This plugin can work on any page of your site and supports WooCommerce transactions to prevent fraudulent orders.
* Version: 1.83
* Author: IPQualityScore
* Author URI: https://www.ipqualityscore.com
*/

if(!class_exists('IPQualityScore')){
	class IPQualityScore {
		const BASE_URL = 'https://www.ipqualityscore.com';
		const ADMIN_ICON = 'assets/wordpress_icon.png';
		const VALIDATE_KEY_ACTION = 'webhooks/Wordpress/validate';
		const RETRIEVE_OVERVIEW_ACTION = 'webhooks/Wordpress/overview';
		const GENERIC_RETRIEVE_ACTION = 'webhooks/Wordpress/%s';
		const ONETIME_LOGIN_URL = 'webhooks/Wordpress/login?secret=%s&destination=%s';
		const REQUEST_ONETIME_LOGIN = 'token';
		public function Install(){
			$this->CreateValue('ipqualityscore_key');
			$this->CreateValue('ipq_allow_crawlers', '1');
			$this->CreateValue('ipq_strictness', 0);
			$this->CreateValue('ipq_allow_public_access_points', 1);
			$this->CreateValue('ipq_allow_timed_out_emails', 1);
			$this->SetupTables();
		}
		
		/*
		* Not sure if we need to do anything to deactivate. Perhaps disable certain listeners?
		*/
		public function Destruct(){
			delete_option('ipq_tracker_key');
			delete_option('ipq_tracker_domain');
			update_option('ipqualityscore_key', 'null');
			foreach($this->GetSettings() as $setting){
				delete_option($setting['name']);
			}
		}
		
		/* 
		* Upgrade database.
		*/
		public function Upgrade(){
			$db_version = get_option('ipq_database_version');
			if($db_version !== null && (float) $db_version < 1.1){
				global $wpdb;
				
				if(file_exists(ABSPATH.'wp-admin/includes/upgrade.php')){
					require_once(ABSPATH.'wp-admin/includes/upgrade.php');
				} else {
					$admin_path = str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, get_admin_url() );
					if(substr($admin_path, -1, 1) === '/'){
						require_once($admin_path.'includes/upgrade.php');
					} else {
						require_once($admin_path.'/includes/upgrade.php');
					}
				}
				
				dbDelta(sprintf(static::$create_email_cache_table, $this->EmailCacheTable(), $wpdb->get_charset_collate(), $this->EmailCacheTable()));
				$this->CreateValue('ipq_database_version', '1.1');
			}
			
			if($db_version !== null && (float) $db_version < 1.2){
				$post_id = get_option('ipq_block_order_post_id');
				if(empty($post_id)){
					$id = wp_insert_post(array(
						'post_content' => file_get_contents(plugin_dir_path(__FILE__).'templates/default_order_declined.html'),
						'post_title' => 'Order Rejected',
						'post_status' => 'publish',
						'post_type' => 'ipqualityscore'
					));
						
					$this->CreateValue('ipq_block_order_post_id', $id);
				}
				
				$post_id = get_option('ipq_loading_order_post_id');
				if(empty($post_id)){
					$id = wp_insert_post(array(
						'post_content' => file_get_contents(plugin_dir_path(__FILE__).'templates/default_loading_order_page.html'),
						'post_title' => 'Loading Page Content',
						'post_status' => 'publish',
						'post_type' => 'ipqualityscore'
					));
						
					$this->CreateValue('ipq_loading_order_post_id', $id);
				}
				
				$this->CreateValue('ipq_database_version', '1.2');
			}
			
			if($db_version !== null && (float) $db_version < 1.3){
				$this->CreateValue('ipq_device_tracker_orders', '0');
				$this->CreateValue('ipq_database_version', '1.3');
			}
		}
		
		public function SetupOrderDenied(){
			if(defined('WC_VERSION')){
				register_post_type('ipqualityscore', array(
					'labels' => array(
						'name' => __( 'Edit Order Pages' ),
						'singular_name' => __( 'Edit Order Page' )
					),
					'public' => true,
					'has_archive' => false,
					'show_in_menu' => false,
					'rewrite' => array('slug' => 'ipqualityscore'),
				));
			}
		}
		
		/*
		* Show the end user a oauth page if they haven't completed setup yet.
		* Otherwise show them the dashboard.
		*/
		public function AdminOverview(){
			if(get_option('ipqualityscore_key') === 'null' || get_option('ipqualityscore_key') === null || get_option('ipqualityscore_key') === false){
				wp_enqueue_script('ipqualityscore_oauth', plugins_url( '/js/OAuth.js', __FILE__ ), array('jquery'));
				return require_once('templates/Setup.php');
			}
			
			$content = $this->FetchContent(static::RETRIEVE_OVERVIEW_ACTION);
			return require_once('templates/Wrapper.php');
		}
		
		/*
		* User is returning from OAuth in theory with a secret.
		* Validate said secret and retrieve a new secret that the user hasn't trapsed across the internet in their get variables.
		*/
		public function SetupOne(){
			if(isset($_REQUEST['secret']) && strlen($_REQUEST['secret']) > 1){
				$request = wp_remote_post($this->GetURL(static::VALIDATE_KEY_ACTION), [
					'method' => 'POST',
					'body' => array(
						'key' => urlencode(get_site_url()),
						'secret' => $_REQUEST['secret']
					)
				]);
				
				$result = json_decode($request['body'], true);
				if($result['success'] === true){
					$this->CreateValue('ipqualityscore_key', $result['secret']);
					exit(header(sprintf("Location: %sadmin.php?page=ipq_overview", admin_url())));
				}
			}
			
			return $this->EvictFailure();
		}
		
		/*
		* User is returning from OAuth, but something went wrong.
		*/
		public function FailureOne(){
			wp_enqueue_script('ipqualityscore_oauth', plugins_url( '/js/Evict.js', __FILE__ ), array('jquery'));
			return require_once('templates/EvictFailure.php');
		}
		
		/*
		* We want to log the user into IPQualityScore, but we don't want to dump a variable into their history that would allow anyone to login so we ask for a one time login credential then forward the user to IPQ.
		* I double checked to make sure Wordpress WILL verify admin logins before executing this code otherwise anyone could just hit up ?page=ipq_login and bam! they're in an account.
		*/
		private static $destinations = [
			'ipq_whitelists' => 'user/whitelist',
			'ipq_blacklists' => 'user/blacklist',
			'ipq_dashboard' => 'user/dashboard',
			'ipq_faq' => 'user/support/overview',
			'ipq_new_ticket' => 'user/support/new',
			'ipq_plans' => 'user/plans',
			'ipq_login' => 'user/dashboard'
		];
		public function Login(){
			global $pagenow;
			
			if($pagenow === 'admin.php' && isset($_REQUEST['page']) && in_array($_REQUEST['page'], array_keys(static::$destinations))){	
				$destination = null;
				if(isset(static::$destinations[$_REQUEST['page']])){
					$destination = static::$destinations[$_REQUEST['page']];
				}
				
				$result = json_decode($this->FetchContent(sprintf(static::GENERIC_RETRIEVE_ACTION, static::REQUEST_ONETIME_LOGIN)), true);
				if($result['success'] === true && isset($result['secret'])){
					exit(header(sprintf(
						"Location: %s/%s",
						static::BASE_URL,
						sprintf(static::ONETIME_LOGIN_URL, $result['secret'], urlencode($destination))
					)));
				}

				return $this->EvictFailure();
			}
			
			if($pagenow === 'admin.php' && isset($_REQUEST['page']) && $_REQUEST['page'] === 'ipq_report_order'){
				return $this->ReportOrder();
			}
		}
		
		/*
		* Displays and handles saves for the settings page.
		*/
		public function Settings(){
			wp_enqueue_script('ipqualityscore_settings', plugins_url( '/js/Settings.js', __FILE__ ), array('jquery'));
			require_once('templates/Settings.php');
		}
		
		/*
		* Display a API failure message.
		*/
		public function EvictFailure(){
			require_once('templates/EvictFailure.php');
		}
		
		/*
		* Tests for and does a proxy check if one needs to be completed.
		*/
		public function DetectProxies(){
			global $pagenow;
			if($this->AllowProxyCheck()){
				$result = $this->FetchProxyCheck();
				if(!is_array($result)){
					return;
				}
				
				if(
					$result['proxy'] === true && 
					get_option('ipq_proxy_all_pages') === '1' && 
					(int) $result['fraud_score'] >= 85
				){
					if(!(get_option('ipq_allow_crawlers') === '1' && $result['is_crawler'] === true)){
						$this->ExitValidation();
					}
				}
				
				if($result['vpn'] === true && get_option('ipq_vpn_all_pages') === '1' && (int) $result['fraud_score'] >= 85){
					if(!(get_option('ipq_allow_crawlers') === '1' && $result['is_crawler'] === true)){
						$this->ExitValidation();
					}
				}
				
				if($result['tor'] === true && get_option('ipq_tor_all_pages') === '1' && (int) $result['fraud_score'] >= 85){
					$this->ExitValidation();
				}
				
				if($this->PageIsProxyPrevented() === true && ((int) $result['fraud_score'] > get_option('ipq_max_fraud_score') && get_option('ipq_max_fraud_score') > 0)){
					if(!(get_option('ipq_allow_crawlers') === '1' && $result['is_crawler'] === true)){
						$this->ExitValidation();
					}
				}
			}
		}
		
		/*
		* Marks incomming comments that are Proxies/Invalid Email as spam if the site owner has requested us to check comments.
		*/
		public function ValidateComment($id, $approved){
			$mark = false;
			if(get_option('ipq_prevent_proxy_comments') === '1'){
				$result = $this->FetchProxyCheck();
				if(isset($result['proxy'], $result['fraud_score']) && ($result['vpn'] === true || ($result['proxy'] === true && (int) $result['fraud_score'] >= 85) || (get_option('ipq_max_fraud_score') < $result['fraud_score'] && get_option('ipq_max_fraud_score') > 0))){
					$mark = true;
				}
			}
			
			if(get_option('ipq_validate_comment_emails') === '1' && $mark === false){
				$comment = get_comment($id);
				$result = $this->FetchEmailCheck($comment->comment_author_email);
				if(isset($result['success']) && $result['success'] !== false){
					if(isset($result['valid'], $result['disposable'], $result['timed_out'], $result['fraud_score']) && ($result['valid'] !== true || $result['disposable'] === true || (int) $result['fraud_score'] === 100)){
						if(get_option('ipq_allow_timed_out_emails') === '1' && $result['timed_out'] === false || get_option('ipq_allow_timed_out_emails') !== '1' || $result['disposable'] === true || (int) $result['fraud_score'] === 100){
							$mark = true;
						}
					}
				}
			}
			
			if($mark === true){
				$comment = array();
				$comment['comment_ID'] = $id;
				$comment['comment_approved'] = 'spam';
				wp_update_comment( $comment );
			}
		}
		
		/*
		* Prevents registrations of users that are Proxies/Invalid Email if the site owner has requested us to check registrations.
		*/
		private $registration_email_check_done = false;
		public function ValidateUser($errors, $ul, $user_email){
			if(get_option('ipq_prevent_proxy_registrations') === '1'){
				$result = $this->FetchProxyCheck();
				if(isset($result['success']) && $result['success'] !== false){
					if(isset($result['proxy'], $result['fraud_score']) && ($result['vpn'] === true || ($result['proxy'] === true && (int) $result['fraud_score'] >= 85) || (get_option('ipq_max_fraud_score') < $result['fraud_score'] && get_option('ipq_max_fraud_score') > 0))){
						$errors->add('proxy_error', 'Your account creation has been denied. Please disable your Proxy or VPN connection.');
					}
				}
			}

			if(get_option('ipq_validate_account_emails') === '1' && $this->registration_email_check_done === false){
				$result = $this->FetchEmailCheck($user_email);
				$this->registration_email_check_done = true;
				if(isset($result['success']) && $result['success'] !== false){
					if(isset($result['valid'], $result['disposable'], $result['timed_out'], $result['fraud_score']) && ($result['valid'] !== true || $result['disposable'] === true || (int) $result['fraud_score'] === 100)){
						if(get_option('ipq_allow_timed_out_emails') === '1' && $result['timed_out'] === false || get_option('ipq_allow_timed_out_emails') !== '1' || $result['disposable'] === true || (int) $result['fraud_score'] === 100){
							$errors->add('email_validation_error', 'Please enter a valid email address.');
						}
					}
				}
			}
			
			return $errors;
		}
		
		public function ValidateUserEmail($user_login, $user_email, $errors) {
			if(get_option('ipq_validate_account_emails') === '1' && $this->registration_email_check_done === false){
				$result = $this->FetchEmailCheck($user_email);
				$this->registration_email_check_done = true;
				if(isset($result['success']) && $result['success'] !== false){
					if(isset($result['valid'], $result['disposable'], $result['timed_out'], $result['fraud_score']) && ($result['valid'] !== true || $result['disposable'] === true || (int) $result['fraud_score'] === 100)){
						if(get_option('ipq_allow_timed_out_emails') === '1' && $result['timed_out'] === false || get_option('ipq_allow_timed_out_emails') !== '1' || $result['disposable'] === true || (int) $result['fraud_score'] === 100){
							$errors->add('email_validation_error', 'Please enter a valid email address.');
						}
					}
				}
			}
		}

		/*
		* If Gravity Forms is installed add the settings to allow for email fraud checks.
		*/
		public function CheckForGravityForms(){
			if ( class_exists( 'GFCommon' ) ) {
				$this->additional_settings[] = [
					'name' => 'ipq_gravity_forms_validate_email',
					'friendly_name' => 'Validate Email Addresses on Gravity Forms',
					'group' => 'gravity',
					'help_text' => 'Validate the email address provided on gravity forms and prevent submission if the email is invalid, disposable or abusive.',
					'type' => 'boolean',
					'default' => '0'
				];
				
				if (get_option('ipq_gravity_forms_validate_email') === '1'){
					add_filter( 'gform_field_validation', [$this, 'ValidateGravityFormsEmail'], 10, 4);
				}
			}
		}
		
		public function ValidateGravityFormsEmail($return, $value, $form, $field) {
			if (get_option('ipq_gravity_forms_validate_email') === '1' && $field->get_input_type() === 'email') {
				$return['is_valid'] = true;
				if(empty($value)){
					$return['is_valid'] = false;
					$return['message']  = 'Email is invalid. Please check the email and try again.';
					return $return;
				}
				
				$result = $this->FetchEmailCheck($value);
				if(isset($result['success']) && $result['success'] !== false){
					if(is_array($result) && isset($result['valid'], $result['timed_out'], $result['dns_valid'], $result['disposable'], $result['recent_abuse'], $result['fraud_score'])){
						if($result['valid'] === false || (get_option('ipq_allow_timed_out_emails') === '0' && $result['timed_out'] === true) || ($result['dns_valid'] === false) || $result['disposable'] === true || $result['recent_abuse'] === true || (int) $result['fraud_score'] === 100){
							$return['is_valid'] = false;
							$return['message']  = 'Email is invalid. Please check the email and try again.';
							return $return;
						}
					}
				}

			}

			return $return;
		}
		
		/*
		* If WooCommerce is installed add the settings to allow for order fraud checks.
		*/
		public function CheckForWoo(){
			if(defined('WC_VERSION')){
				$this->additional_settings[] = [
					'name' => 'ipq_proxy_check_orders',
					'friendly_name' => 'Enable IP Reputation for WooCommerce Orders',
					'group' => 'orders',
					'help_text' => 'Evaluate the IP reputation for WooCommerce orders. You can view this data on the order details page (recommended).',
					'type' => 'boolean',
					'default' => '0'
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_prevent_invalid_proxy_orders',
					'friendly_name' => 'Prevent Orders Submitted by Proxies &amp; High Risk Connections',
					'help_text' => 'Automatically block orders from high risk IPs such as Proxies, VPNs, and TOR connections.',
					'type' => 'boolean',
					'group' => 'orders',
					'default' => '0',
					'conditions' => [
						'ipq_proxy_check_orders' => true
					]
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_proxy_risk_score',
					'group' => 'orders',
					'friendly_name' => 'Max IP Fraud Score',
					'type' => 'number',
					'help_text' => 'Orders with a IP Risk Score equal to or greater than this threshold will automatically be blocked. 89 is the recommended starting threshold. (Zero is disabled)',
					'default' => 0,
					'conditions' => [
						'ipq_proxy_check_orders' => true
					]
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_email_check_orders',
					'group' => 'orders',
					'friendly_name' => 'Enable Email Validation for WooCommerce Orders',
					'help_text' => 'Evaluate the email address reputation for WooCommerce orders. You can view this data on the order details page (recommended).',
					'type' => 'boolean',
					'default' => '0'
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_prevent_invalid_email_orders',
					'group' => 'orders',
					'friendly_name' => 'Block Orders Submitted by Invalid &amp; High Risk Email Addresses',
					'help_text' => 'Automatically prevent orders from high risk email addresses such as invalid emails, disposable or temporary email services, and fraudulent accounts.',
					'type' => 'boolean',
					'default' => '0',
					'conditions' => [
						'ipq_email_check_orders' => true
					]
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_device_tracker_orders',
					'group' => 'orders',
					'friendly_name' => 'Enable Device Fingerprinting for WooCommerce Orders',
					'help_text' => 'Evaluate the Device Fingerprint &amp; Fraud Score for WooCommerce orders. You can view this data on the order details page (recommended). Preventing fraudulent orders only blocks the order if JavaScript is enabled and the order exceeds the maximum fraud score. Blocking fraudulent orders will prevent anyone from checking out without a device fingerprint. Canceling fraudulent orders will automatically cancel and refund fraudulent orders that fail the device fingerprint check.',
					'type' => 'select',
					'options' => [
						'0' => 'Disabled',
						'1' => 'Before Order (recommended)',
						'2' => 'After Order',
						'5' => 'Before Order (Prevent Fraudulent Orders)',
						'6' => 'Before Order (Delay and Prevent Fraudulent Orders)',
						'3' => 'Before Order (Block Fraudulent Orders)',
						'4' => 'After Order (Cancel Fraudulent Orders)'
					],
					'default' => '0'
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_cancel_fraudulent_orders_max_fraud_score',
					'group' => 'orders',
					'friendly_name' => 'Block Orders That Exceed This Device Tracker Fraud Score',
					'type' => 'number',
					'help_text' => 'Orders with a Device Fingerprint Fraud Score above this threshold will automatically be blocked/cancelled. 85 is the recommended starting threshold. (0 is disabled)',
					'default' => 0,
					'conditions' => [
						'ipq_device_tracker_orders' => '3'
					]
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_cancel_fraudulent_orders_max_fraud_score',
					'group' => 'orders',
					'friendly_name' => 'Cancel Orders That Exceed This Device Tracker Fraud Score',
					'type' => 'number',
					'help_text' => 'Orders with a Device Fingerprint Fraud Score above this threshold will automatically be blocked/cancelled. 85 is the recommended starting threshold.',
					'default' => 85,
					'conditions' => [
						'ipq_device_tracker_orders' => '4'
					]
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_prevent_fraudulent_orders_max_fraud_score',
					'group' => 'orders',
					'friendly_name' => 'Max Device Tracker Fraud Score',
					'type' => 'number',
					'help_text' => 'Orders with a Device Fingerprint Fraud Score equal to or greater than this threshold will be prevented. If no fraud score is captured the order will be allowed to continue. 85 is the recommended starting threshold.',
					'default' => 85,
					'conditions' => [
						'ipq_device_tracker_orders' => '5'
					]
				];

				$this->additional_settings[] = [
					'name' => 'ipq_prevent_fraudulent_orders_max_fraud_score',
					'group' => 'orders',
					'friendly_name' => 'Max Device Tracker Fraud Score',
					'type' => 'number',
					'help_text' => 'Orders with a Device Fingerprint Fraud Score equal to or greater than this threshold will be prevented. If no fraud score is captured the order will be allowed to continue. 85 is the recommended starting threshold.',
					'default' => 85,
					'conditions' => [
						'ipq_device_tracker_orders' => '6'
					]
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_max_wait_device_tracker_orders',
					'group' => 'orders',
					'friendly_name' => 'Maximum Time To Wait For Device Tracker',
					'type' => 'number',
					'help_text' => 'Maximum time in miliseconds to wait for the user\'s computer to submit a device tracker score before processing the order anyway.',
					'default' => 5000,
					'conditions' => [
						'ipq_device_tracker_orders' => '6'
					]
				];
				
				$this->additional_settings[] = [
					'name' => 'ipq_prevent_timed_out_device_tracker_orders',
					'friendly_name' => 'Prevent Orders That Time Out',
					'help_text' => 'Automatically block orders that time out while waiting for a device tracker response.',
					'type' => 'boolean',
					'group' => 'orders',
					'default' => '0',
					'conditions' => [
						'ipq_device_tracker_orders' => '6'
					]
				];

				$this->additional_settings[] = [
					'name' => 'ipq_advanced_woocommerce_settings',
					'friendly_name' => 'Enable Advanced Settings',
					'help_text' => 'Not reccomended for most users unless you\'ve been instructed by IPQualityScore Support to enable.',
					'type' => 'boolean',
					'group' => 'orders',
					'default' => '0'
				];

				$this->additional_settings[] = [
					'name' => 'ipq_custom_js_domain',
					'friendly_name' => 'Custom Javascript Domain',
					'help_text' => 'Use a custom domain for device fingerprinting. Will only work with approved IPQualityScore domains. Must include https://. Blank is disabled.',
					'type' => 'url',
					'group' => 'orders',
					'default' => '',
					'conditions' => [
						'ipq_advanced_woocommerce_settings' => true
					]
				];
				
				add_action('woocommerce_after_checkout_validation', [$this, 'ValidateOrder'], 10, 2);
				add_action('woocommerce_checkout_order_processed', [$this, 'StoreOrder'], 10, 3);
				add_action('woocommerce_after_checkout_form', [$this, 'OrderTrackerJSBefore']);
				add_action('woocommerce_thankyou', [$this, 'OrderTrackerJSAfter'], 10, 1);
				add_filter('manage_edit-shop_order_columns', [$this, 'AddOrderColumn']);
				add_action('manage_shop_order_posts_custom_column', [$this, 'AddOrderColumnData']);
				add_action('add_meta_boxes', [$this, 'AddBoxes']);
			}
		}
		
		/*
		* Prevents logins of users that are Proxies if the site owner has requested us to check logins.
		*/
		public function ValidateLogin($user, $password){
			if(get_option('ipq_allow_admin_login') !== '1' || !$user->has_cap('manage_options')){
				if(get_option('ipq_prevent_proxy_logins') === '1'){
					$result = $this->FetchProxyCheck();
					if(isset($result['success']) && $result['success'] !== false){
						if(isset($result['proxy'], $result['fraud_score']) && ($result['vpn'] === true || ($result['proxy'] === true && (int) $result['fraud_score'] >= 85) || (get_option('ipq_max_fraud_score') < $result['fraud_score'] && get_option('ipq_max_fraud_score') > 0))){
							return (new WP_Error( 'proxy_error', 'Invalid login, please try again with your Proxy or VPN connection disabled.'));
						}
					}					
				}
			}
			
			return $user;
		}
		
		/*
		* Validates a WooCommerce order before the user is sent to the payment gateway.
		*/
		private $last_proxy_check;
		private $last_email_check;
		private $last_device_check;
		public function ValidateOrder($data, $errors){
			if(get_option('ipq_proxy_check_orders') === '1'){
				$this->last_proxy_check = $this->FetchProxyCheck($_REQUEST);
				if(isset($this->last_proxy_check['transaction_details']['risk_score'])){
					$this->last_proxy_check['fraud_score'] = $this->last_proxy_check['transaction_details']['risk_score'];
				}
				
				if(get_option('ipq_prevent_invalid_proxy_orders') === '1'){
					if(isset($this->last_proxy_check['proxy'], $this->last_proxy_check['fraud_score'])){
						if($this->last_proxy_check['proxy'] === true && (int) $this->last_proxy_check['fraud_score'] >= 85){
							$errors->add('proxy_error', 'Your order does not meet our criteria. Please contact us if this error persists.');
						}
					}
				}
				
				if(isset($this->last_proxy_check['proxy'], $this->last_proxy_check['fraud_score']) && (get_option('ipq_proxy_risk_score') <= $this->last_proxy_check['fraud_score'] && get_option('ipq_proxy_risk_score') > 0)){
					$errors->add('proxy_error', 'Your order does not meet our criteria. Please contact us if this error persists.');
				}
			}
			
			if(in_array(get_option('ipq_device_tracker_orders'), ['1', '3', '5', '6'])){
				$this->last_device_check = $this->ValidateDeviceTracker($_REQUEST['request_id']);
				if(isset($this->last_device_check['fraud_score']) && get_option('ipq_device_tracker_orders') === '3' && (int) get_option('ipq_cancel_fraudulent_orders_max_fraud_score') >= 0 && get_option('ipq_cancel_fraudulent_orders_max_fraud_score') < $this->last_device_check['fraud_score']){
					$errors->add('device_error', 'Your order does not meet our criteria. Please contact us if this error persists.');
				}
				
				if(get_option('ipq_device_tracker_orders') === '3' && !isset($this->last_device_check['fraud_score'])){
					$errors->add('device_error', 'Your order does not meet our criteria. Please contact us if this error persists.');
				}
				
				if(isset($this->last_device_check['fraud_score']) && get_option('ipq_device_tracker_orders') === '5' && (int) get_option('ipq_prevent_fraudulent_orders_max_fraud_score') >= 0 && get_option('ipq_prevent_fraudulent_orders_max_fraud_score') < $this->last_device_check['fraud_score']){
					$errors->add('device_error', 'Your order does not meet our criteria. Please contact us if this error persists.');
				}
				
				if(isset($this->last_device_check['fraud_score']) && get_option('ipq_device_tracker_orders') === '6' && (int) get_option('ipq_prevent_fraudulent_orders_max_fraud_score') >= 0 && get_option('ipq_prevent_fraudulent_orders_max_fraud_score') < $this->last_device_check['fraud_score']){
					$errors->add('device_error', 'Your order does not meet our criteria. Please contact us if this error persists.');
				}
				
				if(isset($_REQUEST['no_dt_submitted']) && !isset($this->last_device_check['fraud_score'])){
					$this->last_device_check = array(
						'success' => false,
						'timed_out' => true,
						'fraud_score' => 'N/A', 
						'message' => 'No device tracker data was collected for this order. Proceed with caution!'
					);

					if(get_option('ipq_prevent_timed_out_device_tracker_orders') === '1'){
						$errors->add('device_error', 'Your order does not meet our criteria. Please contact us if this error persists.');
					}
				}
			}
			
			if(get_option('ipq_email_check_orders') === '1'){
				$this->last_email_check = $this->FetchEmailCheck($data['billing_email']);
				if(isset($this->last_email_check['success']) && $this->last_email_check['success'] !== false){
					if(isset($this->last_email_check['valid'], $this->last_email_check['disposable'], $this->last_email_check['recent_abuse'], $this->last_email_check['fraud_score']) && ($this->last_email_check['valid'] !== true || $this->last_email_check['disposable'] === true || (int) $this->last_email_check['fraud_score'] === 100)){
						if(get_option('ipq_prevent_invalid_email_orders') === '1'){
							if(get_option('ipq_allow_timed_out_emails') === '1' && $this->last_email_check['timed_out'] === false || get_option('ipq_allow_timed_out_emails') !== '1' || $this->last_email_check['disposable'] === true || (int) $this->last_email_check['fraud_score'] === 100){
								$errors->add('email_validation_error', 'Please enter a valid email address.');
							}
						}
					}
				}
			}
		}
		
		/*
		* Caches our Fraud Score and other data for this order so we don't have to do an API call every time to retrieve it.
		*/
		public function StoreOrder($id, $data, $order){
			if($this->last_proxy_check !== null){
				$this->AddCache('proxy_order', $id, $this->last_proxy_check);
			}
			
			if($this->last_email_check !== null){
				$this->AddCache('email_order', $id, $this->last_email_check);
			}
			
			if($this->last_device_check !== null){
				$this->AddCache('device_order', $id, $this->last_device_check);
			}
		}
		
		/*
		* Appends the device tracker JS and a trigger to the page after the order is submitted if we've been asked to device tracker orders.
		*/
		public function OrderTrackerJSAfter($order_id){
			if(get_option('ipq_device_tracker_orders') == '2' || get_option('ipq_device_tracker_orders') == '4'){
				if(get_option('ipq_tracker_key') === false || get_option('ipq_tracker_domain') === false){
					if(!$this->PopulateTracker()){
						return;
					}
				}
				
				require_once('templates/OrderAfter.php');
			}
		}
		
		/*
		* Appends the device tracker JS and a trigger to the page before the order is submitted if we've been asked to device tracker orders.
		*/
		public function OrderTrackerJSBefore($order_id){
			if(in_array(get_option('ipq_device_tracker_orders'), ['1', '3', '5', '6'])){
				if(get_option('ipq_tracker_key') === false || get_option('ipq_tracker_domain') === false){
					if(!$this->PopulateTracker()){
						return;
					}
				}
				
				require_once('templates/OrderBefore.php');
			}
			
			require_once('templates/BeforeOrderAlways.php');
		}
		/*
		* Adds a column to the admin orders page for woocommerce so we can display fraud score.
		*/
		public function AddOrderColumn($columns){
			$new = array();
			foreach($columns as $name => $info){
				if($name === 'order_date'){
					$new['fraud_score'] = 'Risk Score';
				}
				
				$new[$name] = $info;
			}
			
			return $new;
		}
		
		/*
		* Returns the fraud score for the requested order row for the admin orders page.
		*/
		public function AddOrderColumnData($column){
			global $post;
			if($column === 'fraud_score'){
				if($this->FetchSimpleCache()){
					if(!isset($this->order_cache[$post->ID])){
						$this->ForcePopulateOrder($post->ID);
					}

					if(isset($this->order_cache[$post->ID])){
						wp_enqueue_style('ipq_settings_style_sheet', plugin_dir_url( __FILE__ ) .'assets/base.css');
						$cache = $this->order_cache[$post->ID];
						if($cache['timed_out'] === true){
							echo '<span class="ipq_fraudulent">Timed Out!</span>';
							return;
						}

						if($cache['risk_score'] !== null){
							if($cache['risk_score'] >= 90) {
								echo '<span class="ipq_fraudulent">'.$cache['risk_score'].' - Fraudulent</span>';
							} elseif($cache['risk_score'] >= 70) {
								echo '<span class="ipq_fraudulent">'.$cache['risk_score'].' - High Risk</span>';
							} elseif($cache['risk_score'] >= 40) {
								echo '<span class="ipq_suspicious">'.$cache['risk_score'].' - Suspicious</span>';
							} else {
								echo '<span class="ipq_clean">'.$cache['risk_score'].' - Clean</span>';
							}

							return;
						}
						
						if($cache['fraud_score'] !== null){
							if($cache['fraud_score'] >= 90) {
								echo '<span class="ipq_fraudulent">'.$cache['fraud_score'].' - Fraudulent</span>';
							} elseif($cache['fraud_score'] >= 70) {
								echo '<span class="ipq_fraudulent">'.$cache['fraud_score'].' - High Risk</span>';
							} elseif($cache['fraud_score'] >= 40) {
								echo '<span class="ipq_suspicious">'.$cache['fraud_score'].' - Suspicious</span>';
							} else {
								echo '<span class="ipq_clean">'.$cache['fraud_score'].' - Clean</span>';
							}

							return;
						}

						echo "N/A";
					} else {
						echo "N/A";
					}
				}
			}
		}
		
		/*
		* If we're viewing an order add a box to the right side for displaying IPQualityScore fraud score data.
		*/
		public function AddBoxes(){
			global $post;
			
			$order = wc_get_order( $post->ID );
			if(!empty($order)){
				add_meta_box('ipq-fraud-box', 'IPQualityScore Fraud Information', [$this, 'OrderStatusBox'], '', 'side');
			}
		}
		
		/*
		* Displays the results for this order in a box on the right hand side of the edit order page.
		*/
		public function OrderStatusBox(){
			global $post;
			$order = wc_get_order( $post->ID );
			if(!empty($order)){
				$results = $this->FetchCache($post->ID);
				wp_enqueue_style('ipq_settings_style_sheet', plugin_dir_url( __FILE__ ) .'assets/base.css');
				require_once('templates/AdminOrderPanel.php');
			}
		}
		
		/*
		* Submits a report that an order is fraudulent to IPQualityScore.
		*/
		const REPORT_ORDER_ROUTE = 'webhooks/Wordpress/report_order';
		public function ReportOrder(){
			$cache = $this->FetchCache($_REQUEST['id']);
			$ids = array();
			foreach($cache as $row){
				if(isset($row['Cache']['request_id'])){
					$ids[] = $row['Cache']['request_id'];
				}
			}
			
			$data = wp_remote_post($this->GetURL(static::REPORT_ORDER_ROUTE), [
				'method' => 'POST',
				'body' => array(
					'key' => urlencode(get_site_url()),
					'secret' => get_option('ipqualityscore_key'),
					'requests' => $ids
				)
			]);
			
			exit(print_r($data['body'], true));
		}
		
		public function ValidateEmail($email){
			if($email !== false && get_option('ipq_validate_email_fields') == '1'){
				$result = $this->FetchEmailCheck($email);
				if(is_array($result) && (isset($result['success']) && $result['success'] !== false)){
					if(($result['valid'] === true && $result['disposable'] === false && (int) $result['fraud_score'] < 100) || (get_option('ipq_allow_timed_out_emails') === '1' && $result['timed_out'] === true && $result['dns_valid'] === true && $result['disposable'] === false && (int) $result['fraud_score'] < 100)){
						return $email;
					}

					return false;
				} else {
					return $email;
				}
			}
			
			return $email;
		}
		/*
		* Setup menus for admins.
		*/
		public function AdminMenu(){
			add_menu_page(
				'IPQualityScore - Proactively Prevent Fraud',
				'IPQualityScore',
				'manage_options',
				'ipq_overview',
				[$this, 'AdminOverview'],
				plugin_dir_url( __FILE__ ) . static::ADMIN_ICON,
				90
			);
			
			add_submenu_page(
				'',
				'IPQualityScore - OAuth Failure',
				'',
				'manage_options',
				'ipq_oauth_failure',
				[$this, 'FailureOne']
			);

			$hook = add_submenu_page(
				'',
				'IPQualityScore - Setup Step 1',
				'',
				'manage_options',
				'ipq_oauth_success',
				function(){}
			);

			add_action('load-' . $hook, function() {
				exit(call_user_func_array([$this, 'SetupOne'], []));
			});
			
			if(get_option('ipqualityscore_key') !== 'null'){
				
				// If you remove this the menu will display IPQualityScore as the first option. 
				// I'd rather not have a first menu option but Wordpress doesn't allow that.
				add_submenu_page(
					'ipq_overview',
					'IPQualityScore - Dashboard',
					'Dashboard',
					'manage_options',
					'ipq_overview',
					[$this, 'AdminOverview']
				);

				add_submenu_page(
					'ipq_overview',
					'IPQualityScore - Proxy Statistics',
					'Proxy Statistics',
					'manage_options',
					'ipq_proxy_statistics',
					function(){ $this->WrapContent(['google' => 'https://www.gstatic.com/charts/loader.js', 'dt' => 'jquery.dataTables.min', 'hud' => 'HUD']); }
				);

				add_submenu_page(
					'ipq_overview',
					'IPQualityScore - Email Statistics',
					'Email Statistics',
					'manage_options',
					'ipq_email_statistics',
					function(){ $this->WrapContent(['google' => 'https://www.gstatic.com/charts/loader.js', 'dt' => 'jquery.dataTables.min', 'hud' => 'HUD']); }
				);

				add_submenu_page(
					'ipq_overview',
					'IPQualityScore - Device Tracker Statistics',
					'Device Statistics',
					'manage_options',
					'ipq_device_statistics',
					function(){ $this->WrapContent(['google' => 'https://www.gstatic.com/charts/loader.js', 'dt' => 'jquery.dataTables.min', 'hud' => 'HUD']); }
				);

				add_submenu_page(
					'ipq_overview',
					'IPQualityScore - Settings',
					'IPQS Settings',
					'manage_options',
					'ipq_settings',
					[$this, 'Settings']
				);

				/*
				* Setup hidden routes.
				*/

				add_submenu_page(
					'',
					'IPQualityScore - Login',
					'Login',
					'manage_options',
					'ipq_login',
					[$this, 'Login']
				);
				
				add_submenu_page(
					'',
					'IPQualityScore - Report',
					'report',
					'manage_options',
					'ipq_report_order',
					[$this, 'ReportOrder']
				);
				
				foreach(static::$destinations as $route => $path){
					add_submenu_page(
						'',
						'IPQualityScore - Login',
						'Login',
						'manage_options',
						$route,
						[$this, 'Login']
					);
				}
				
				/*
				* JSON routes.
				*/

				$json_routes = array(
					'dashboard_json',
					'hud_json',
					'gbu_json'
				);

				foreach($json_routes as $route){
					$hook = add_submenu_page(
						'',
						'JSON',
						'',
						'manage_options',
						'ipq_'.$route,
						[$this, 'RetrieveContent']
					);

					add_action('load-' . $hook, function() {
						call_user_func_array([$this, 'RetrieveContent'], []);
						exit;
					});
				}
			
				/*
				* Open in new window login link.
				*/
				global $submenu;
				$submenu['ipq_overview'][] = array(sprintf('<div id="manageWhitelists" onClick="window.open(\'%sadmin.php?page=ipq_whitelists\', \'_blank\')">Whitelists</div>', admin_url()), 'manage_options', '#whitelist');
				$submenu['ipq_overview'][] = array(sprintf('<div id="manageBlacklists" onClick="window.open(\'%sadmin.php?page=ipq_blacklists\', \'_blank\')">Blacklists</div>', admin_url()), 'manage_options', '#blacklist');
				$submenu['ipq_overview'][] = array(sprintf('<div id="loginToIPQ" onClick="window.open(\'%sadmin.php?page=ipq_login\', \'_blank\')">Login To IPQS</div>', admin_url()), 'manage_options', '#login');
				$submenu['ipq_overview'][] = array(sprintf('<div id="editOrderPages" onClick="window.location = \'%sedit.php?post_type=ipqualityscore\'">Edit Order Pages</div>', admin_url()), 'manage_options', '#editOrderPages');
			}
		}
		
		/*
		* Adds a settings link to the Plugins page.
		*/
		public function SettingsLink($links){
			if(get_option('ipqualityscore_key') !== 'null'){
				array_unshift($links, $settings_link = '<a href="admin.php?page=ipq_settings">' . __( 'Settings' ) . '</a>');
			}
			return $links;
		}
		
		/*
		* Processes custom URLS for IPQS.
		*/
		public function CustomRoutes(){
			if(isset($_REQUEST['ipqs']) && $_REQUEST['ipqs'] === 'dtpost') {
				exit($this->DTPost($_REQUEST));
			}
		}
		
		private function DTPost($request){
			if(get_option('ipq_device_tracker_orders') === '2' || get_option('ipq_device_tracker_orders') === '4'){
				if(isset($request['order_id'], $request['request_id'])){
					$order = wc_get_order($request['order_id']);
					if($order !== false){
						$this->last_device_check = $this->ValidateDeviceTracker($_REQUEST['request_id']);
						$this->StoreOrder($request['order_id'], $request, $order);
						
						if(isset($this->last_device_check['fraud_score']) && get_option('ipq_device_tracker_orders') === '4' && get_option('ipq_cancel_fraudulent_orders_max_fraud_score') > 0 && $this->last_device_check['fraud_score'] > get_option('ipq_cancel_fraudulent_orders_max_fraud_score')){
							$this->CancelOrder($order);
							return $this->JSONExit(false, 'EID: 1');
						}
						
						$this->JsonExit(true, 'Order successfully placed.');
					}
				}
			}
			
			return $this->JSONExit(false, 'EID: 3');
		}
		
		private function CancelOrder($order){
			if(method_exists($order, 'update_status')){
				$order->update_status('cancelled', 'Automatically cancelled by IPQualityScore Fraud Prevention.');
			} else {
				$order->cancel_order('Automatically cancelled by IPQualityScore Fraud Prevention.');
			}
			
			do_action('wco_after_order_cancel_action', $order);
		}
		
		private function JSONExit($success = true, $message = 'Success.', $variables = array()){
			$result = ['success' => $success, 'message' => $message];
			if(is_array($variables)){
				$result = array_merge($result, $variables);
			}
			
			exit(print_r(json_encode($result), true));
		}
		
		private function ExitValidation(){
			if(!empty(get_option('ipq_pages_redirect'))){
				exit(header(sprintf("Location: %s", get_option('ipq_pages_redirect'))));
			}
			
			exit(require_once('templates/FraudScoreFailure.php'));
		}
		
		private function GetPages(){
			$result = [];
			$result['-1'] = 'Home Page';
			$result['-2'] = 'Category Pages';
			$result['-3'] = 'Tag Pages';
			foreach(array_merge(get_pages(), get_posts()) as $page){
				$result[$page->ID] = $page->post_title;
			}
			
			return $result;
		}
		
		private static $run_proxy_test_if_true = [
			'ipq_proxy_all_pages',
			'ipq_vpn_all_pages',
			'ipq_tor_all_pages'
		];
		
		/*
		* Answers a "simple" question. Do we need to do a proxy check.
		*/
		private function AllowProxyCheck(){
			global $pagenow;
			
			if($pagenow === 'admin.php'){
				return false;
			}
			
			foreach(static::$run_proxy_test_if_true as $var){
				if(get_option($var) === '1'){
					return true;
				}
			}
			
			/*if(get_option('ipq_max_fraud_score') > 0 && get_option('proxy_check_all_pages') === '1'){
				return true;
			}*/
			
			if($this->PageIsProxyPrevented() === true){
				return true;
			}
			
			return false;
		}
		
		private function PageIsProxyPrevented(){
			global $pagenow;
			global $wp_query;
			
			$pages = $this->FetchProxyPageList();
			if(count($pages) > 0){
				if(is_object($wp_query) && $wp_query->post !== null){
					if($pagenow === 'index.php' && $wp_query->is_front_page() === false){
						if(in_array($wp_query->post->ID, $pages)){
							return true;
						}
					}
				}

				if(($pagenow === 'index.php' || $pagenow === 'home.php' || $pagenow === 'front-page.php') && in_array('-1', $pages) && $wp_query->is_front_page() === true){
					return true;
				}
				if($pagenow === 'category.php' && in_array('-2', $pages)){
					return true;
				}
				if(($pagenow === 'tag.php' || $pagenow === 'taxonomy.php' || $pagenow === 'author.php') && in_array('-3', $pages)){
					return true;
				}
			}
			
			return false;
		}
		
		private function FetchProxyPageList(){
			return json_decode(get_option('ipq_pages', '[]'), true);
		}
		
		const PROXY_API_URL = 'webhooks/Wordpress/proxy_check';
		private function FetchProxyCheck(array $data = []){
			$request = wp_remote_post($this->GetURL(static::PROXY_API_URL), [
				'method' => 'POST',
				'body' => array_merge($this->Convert($data), [
						'key' => urlencode(get_site_url()),
						'secret' => get_option('ipqualityscore_key'),
						'ip' => $this->GetIP(),
						'user_agent' => $_SERVER['HTTP_USER_AGENT'],
						'user_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
						'mobile' => wp_is_mobile() ? 'true' : 'false',
						'strictness' => get_option('ipq_strictness') !== false ? get_option('ipq_strictness') : 0,
						'allow_public_access_points' => (get_option('ipq_allow_public_access_points') === true || get_option('ipq_allow_public_access_points') === '1') ? 'true' : 'false'
					])
			]);
			
			if($request instanceof WP_Error){
				if(stripos($request->get_error_message(), 'curl') === false){
					throw new Exception($request->get_error_message());
				}
				
				return [];
			}
			
			$result = json_decode($request['body'], true);
			return is_array($result) ? $result : [];
		}
		
		private static $bridge = ['dsn342cawiw3A23' => 'charge_key_press', 'dsn342cawiw3A21' => 'total_key_press'];
		private function Convert($data){
			foreach($data as $k => $v){ if(isset(static::$bridge[$k])){ $data[static::$bridge[$k]] = $v; unset($data[$k]); }} return $data;
		}
		
		const EMAIL_API_URL = 'webhooks/Wordpress/email_check';
		private function FetchEmailCheck($email){
			$cache = $this->FetchEmailCache($email);
			if($cache !== null){
				return $cache;
			}
			
			$request = wp_remote_post($this->GetURL(static::EMAIL_API_URL), [
				'method' => 'POST',
				'body' => [
					'key' => urlencode(get_site_url()),
					'secret' => get_option('ipqualityscore_key'),
					'strictness' => get_option('ipq_strictness') !== false ? get_option('ipq_strictness') : 0,
					'email' => $email
				]
			]);
			
			if($request instanceof WP_Error){
				if(stripos($request->get_error_message(), 'curl') === false){
					throw new Exception($request->get_error_message());
				}
				
				return [];
			}
			
			$result = json_decode($request['body'], true);
			if($this->AddEmailCache($email, $request['body'])){
				return is_array($result) ? $result : [];
			}
			
			return [];
		}
		
		const TRACKER_API_URL = 'webhooks/Wordpress/generate_device_tracker';
		private function PopulateTracker(){
			$result = json_decode($this->FetchContent(static::TRACKER_API_URL), true);
			if($result['success'] === true){
				$this->CreateValue('ipq_tracker_key', $result['tracker']);
				$this->CreateValue('ipq_tracker_domain', $result['domain']);
				return true;
			}
			
			return false;
		}
		
		const TRACKER_VALIDATION_URL = 'webhooks/Wordpress/validate_device_tracker';
		private function ValidateDeviceTracker($id){
			$request = wp_remote_post($this->GetURL(static::TRACKER_VALIDATION_URL), [
				'method' => 'POST',
				'body' => array_merge($_REQUEST, array(
					'id' => $id,
					'key' => urlencode(get_site_url()),
					'secret' => get_option('ipqualityscore_key')
				))
			]);
			
			if($request instanceof WP_Error){
				if(stripos($request->get_error_message(), 'curl') === false){
					throw new Exception($request->get_error_message());
				}
				
				return [];
			}
			
			$result = json_decode($request['body'], true);
			return is_array($result) ? $result : [];
		}
		
		private function CreateValue($name, $value = 'null'){
			add_option($name, $value);
			update_option($name, $value);
		}
		
		private function FetchContent($route){
			$request = wp_remote_post($this->GetURL($route), [
				'method' => 'POST',
				'body' => array_merge($_REQUEST, array(
					'key' => urlencode(get_site_url()),
					'secret' => get_option('ipqualityscore_key')
				))
			]);
			
			if($request instanceof WP_Error){
				if(stripos($request->get_error_message(), 'curl') === false){
					throw new Exception($request->get_error_message());
				}
				
				return [];
			}
			
			return $request['body'];
		}
		
		const BASE_PATH = '%s/%s';
		private function GetURL($action){
			 return sprintf(static::BASE_PATH, static::BASE_URL, $action);
		}
		
		private function GetIP(){
			switch(true){
				case (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP']) && $_SERVER['HTTP_CF_CONNECTING_IP'] !== '0.0.0.0'): return $_SERVER['HTTP_CF_CONNECTING_IP'];
				case (isset($_SERVER['HTTP_X_REAL_IP']) && !empty($_SERVER['HTTP_X_REAL_IP']) && $_SERVER['HTTP_X_REAL_IP'] !== '0.0.0.0'): return $_SERVER['HTTP_X_REAL_IP'];
				case (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] !== '0.0.0.0'): return $_SERVER['REMOTE_ADDR'];
				case (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] !== '0.0.0.0'): return $_SERVER['HTTP_CLIENT_IP'];
				case (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] !== '0.0.0.0'): return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
			}
		}
		
		/*
		* Wraps some content in a Wordpress friendly feel.
		*/
		private function WrapContent(array $scripts = []){
			$content = $this->RetrieveContent();
			foreach($scripts as $name => $script){
				wp_enqueue_script('ipqualityscore_'.$name, substr($script, 0, 4) === 'http' ? $script : plugins_url( '/js/'.$script.'.js', __FILE__ ), array('jquery'));
			}
			
			require_once('templates/Wrapper.php');
		}
		
		/*
		* Retrieves a simple page from IPQ.
		*/
		private function RetrieveContent(){
			echo $this->FetchContent(sprintf(static::GENERIC_RETRIEVE_ACTION, substr($_REQUEST['page'], 4)));
		}
		
		/*
		* Returns a list of settings IPQ uses, their friendly name, their default, type and what conditions must be met for them to be displayed.
		*/
		private $additional_settings = array();
		private function GetSettings(){
			return array_merge([
				[
					'name' => 'ipq_proxy_all_pages', 
					'friendly_name' => 'Prevent Proxy/VPN/TOR Connections On ALL Pages',
					'type' => 'boolean',
					'default' => '0',
					'group' => 'site',
					'help_text' => 'Block proxies &amp; high risk connections for all parts of your site?',
					'conditions' => [
						'ipq_vpn_all_pages'  => false,
						'ipq_tor_all_pages' => false
					]
				],
				[
					'name' => 'ipq_vpn_all_pages',
					'friendly_name' => 'Prevent VPN Connections On ALL Pages',
					'type' => 'boolean',
					'default' => '0',
					'group' => 'site',
					'help_text' => 'Block VPN connections for all parts of your site?',
					'conditions' => [
						'ipq_proxy_all_pages' => false
					]
				],
				[
					'name' => 'ipq_tor_all_pages',
					'friendly_name' => 'Prevent TOR Connections On ALL Pages',
					'type' => 'boolean',
					'help_text' => 'Block TOR connections for all parts of your site?',
					'default' => '0',
					'group' => 'site',
					'conditions' => [
						'ipq_proxy_all_pages' => false
					]
				],
				[
					'name' => 'ipq_max_fraud_score',
					'friendly_name' => 'Max page fraud score:',
					'type' => 'number',
					'default' => '0',
					'group' => 'pages',
					'help_text' => 'Block requests with Fraud Scores greater than this threshold. This setting requires selecting your preferred pages or posts below to activate IP Reputation filtering. Setting this value to "0" disables this feature. 85 is a recommended starting threshold to avoid false-positives.',
					'conditions' => [
						'ipq_vpn_all_pages' => false,
						'ipq_tor_all_pages' => false,
						'ipq_proxy_all_pages' => false
					]
				],
				[
					'name' => 'ipq_allow_public_access_points',
					'friendly_name' => 'Bypass Some Checks for Public Access Points',
					'type' => 'boolean',
					'default' => '0',
					'group' => 'site',
					'help_text' => 'Skip stricter checks for IP addresses from educational institutions and businesses to better accommodate audiences that frequently use public connections (recommended).',
					'conditions' => [
						'ipq_proxy_all_pages' => true
					]
				],
				[
					'name' => 'ipq_allow_crawlers',
					'friendly_name' => 'Always Allow Trusted Search Crawlers (Google/Yahoo/etc...)',
					'type' => 'boolean',
					'default' => '0',
					'group' => 'site',
					'help_text' => 'Prevent proxy filters from detecting verified search engine crawlers as a bot (recommended).'
				],
				[
					'name' => 'ipq_strictness', 
					'friendly_name' => 'Global Strictness Setting',
					'type' => 'number',
					'group' => 'site',
					'default' => 0,
					'help_text' => 'Adjust the strictness of IPQS\' Fraud Scoring algorithms. Please specify a strictness level between 0 (low) and 3 (high). Level 0 - 1 is recommended.',
				],
				[
					'name' => 'ipq_validate_email_fields',
					'friendly_name' => 'Validate All Email Fields',
					'group' => 'email',
					'help_text' => 'Used anywhere the WordPress "is_email()" function is performed (recommended).',
					'type' => 'boolean',
					'default' => '0'
				],
				[
					'name' => 'ipq_allow_timed_out_emails',
					'friendly_name' => 'Allow Emails that Time Out During SMTP Verification',
					'group' => 'email',
					'help_text' => 'Some emails addresses can time out during SMTP verification. This setting will treat timed out emails as valid if all other validation checks are satisfied (recommended).',
					'type' => 'boolean',
					'default' => '0'
				],
				[
					'name' => 'ipq_pages',
					'friendly_name' => 'Prevent Proxies/VPN/TOR On Specific Pages &amp; Posts',
					'type' => 'multiselect',
					'options' => $this->GetPages(),
					'group' => 'pages',
					'help_text' => 'Choose which pages &amp; posts you would like to enable Proxy, VPN, TOR and max fraud score detection. If proxy check all pages is checked this setting is ignored.',
					'conditions' => [
						'ipq_proxy_all_pages' => false
					]
				],
				[
					'name' => 'ipq_pages_redirect',
					'group' => 'pages',
					'friendly_name' => 'Redirect Proxy Visitors To:',
					'help_text' => 'Choose which pages &amp; posts you would like to enable Proxy, VPN, &amp; TOR detection. This setting requires a minimum fraud score threshold in the section above. If "Prevent Proxies For All Pages" is checked above then this setting is ignored.',
					'type' => 'url'
				],
				[
					'name' => 'ipq_validate_account_emails',
					'friendly_name' => 'Validate New Account Emails',
					'group' => 'users',
					'help_text' => 'Confirm if new accounts have a valid email address and are not using a disposable email service or an email address associated with abusive behavior (recommended).',
					'type' => 'boolean',
					'default' => '0'
				],
				[
					'name' => 'ipq_prevent_proxy_registrations',
					'friendly_name' => 'Prevent Proxy Registrations',
					'group' => 'users',
					'help_text' => 'Block registrations from users behind a Proxy, VPN, or TOR connection.',
					'type' => 'boolean',
					'default' => '0'
				],
				[
					'name' => 'ipq_prevent_proxy_logins',
					'friendly_name' => 'Prevent Proxy Logins',
					'group' => 'users',
					'help_text' => 'Block logins from users behind a Proxy, VPN, or TOR connection.',
					'type' => 'boolean',
					'default' => '0'
				],
				[
					'name' => 'ipq_validate_comment_emails',
					'friendly_name' => 'Validate Comment Emails',
					'group' => 'users',
					'help_text' => 'Block comments from users with an invalid email address, disposable email address, or fraudulent email address (recommended).',
					'type' => 'boolean',
					'default' => '0'
				],
				[
					'name' => 'ipq_prevent_proxy_comments',
					'friendly_name' => 'Prevent Proxy Comments',
					'group' => 'users',
					'help_text' => 'Block comments from users behind a Proxy, VPN, or TOR connection (recommended).',
					'type' => 'boolean',
					'default' => '0'
				],
				[
					'name' => 'ipq_allow_admin_login',
					'friendly_name' => 'Allow Admin Login Even If Proxy',
					'group' => 'users',
					'help_text' => 'Permit "Admin" level accounts to bypass IP reputation checks (recommended).',
					'type' => 'boolean',
					'default' => '0'
				]
			], $this->additional_settings);
		}
		
		private function CacheTable(){
			global $wpdb;
			return $wpdb->prefix.'ipqs_cache';
		}
		
		public function EmailCacheTable(){
			global $wpdb;
			return $wpdb->prefix.'ipqs_email_cache';
		}
		
		private function AddCache($type, $id, array $data){
			global $wpdb;
			
			$wpdb->insert($this->CacheTable(), [
				'DataType' => $type,
				'DataID' => $id,
				'FraudScore' => isset($data['fraud_score']) ? $data['fraud_score'] : null,
				'Cache' => json_encode($data)
			]);
			
			return true;
		}
		
		private function FetchCache($id, $type = null){
			global $wpdb;
			
			if($type === null){
				$results = $wpdb->get_results($wpdb->prepare(str_replace('_table_name_', $this->CacheTable(), static::$fetch_cache), $id), ARRAY_A);
			} else {
				$results = $wpdb->get_results($wpdb->prepare(str_replace('_table_name_', $this->CacheTable(), static::$fetch_cache_type), $type, $id), ARRAY_A);
			}
			
			foreach($results as $id => $row){
				$results[$id]['Cache'] = json_decode($row['Cache'], true);
			}
			
			return $results;
		}
		
		private function FetchEmailCache($email){
			global $wpdb;
			$results = $wpdb->get_results($wpdb->prepare(str_replace('_table_name_', $this->EmailCacheTable(), static::$fetch_email_cache), $email), ARRAY_A);
			
			foreach($results as $id => $row){
				return json_decode($row['Cache'], true);
			}
		}
		
		private function AddEmailCache($email, $raw){
			global $wpdb;
			
			$wpdb->insert($this->EmailCacheTable(), [
				'Email' => $email,
				'Cache' => $raw
			]);
			
			return true;
		}
		
		private function SetupTables(){
			global $wpdb;
			
			if(file_exists(ABSPATH.'wp-admin/includes/upgrade.php')){
				require_once(ABSPATH.'wp-admin/includes/upgrade.php');
			} else {
				$admin_path = str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, get_admin_url() );
				if(substr($admin_path, -1, 1) === '/'){
					require_once($admin_path.'includes/upgrade.php');
				} else {
					require_once($admin_path.'/includes/upgrade.php');
				}
			}
			
			dbDelta(sprintf(static::$create_cache_table, $this->CacheTable(), $wpdb->get_charset_collate(), $this->CacheTable()));
			dbDelta(sprintf(static::$create_email_cache_table, $this->EmailCacheTable(), $wpdb->get_charset_collate(), $this->EmailCacheTable()));
			$this->CreateValue('ipq_database_version', '1.1');
		}
		
		private $order_cache;
		private $populated_order_cache = false;
		/*
		* Make bulk lookups more efficient.
		*/
		private function FetchSimpleCache(){
			if($this->populated_order_cache === false){
				$this->populated_order_cache = true;
				if($this->order_cache === null){
					$this->order_cache = array();
				}

				global $wpdb;
				foreach($wpdb->get_results(sprintf(static::$fetch_simple_cache, $this->CacheTable()), ARRAY_A) as $row){
					$this->order_cache[$row['DataID']] = $this->ConvertCacheObject(
						isset($this->order_cache[$row['DataID']]) ? $this->order_cache[$row['DataID']] : [],
						json_decode($row['Cache'], true)
					);				
				}
			}

			return true;
		}

		/*
		* Lookup a single order.
		*/
		private function ForcePopulateOrder($postid){
			if($this->order_cache === null){
				$this->order_cache = array();
			}

			foreach($this->FetchCache($postid) as $row){
				$this->order_cache[$row['DataID']] = $this->ConvertCacheObject(
					isset($this->order_cache[$row['DataID']]) ? $this->order_cache[$row['DataID']] : [],
					$row['Cache']
				);
			}

			return true;
		}

		private function ConvertCacheObject(array $data, array $raw){
			if(empty($data)){
				$data = ['fraud_score' => null, 'risk_score' => null, 'timed_out' => false];
			}

			if(isset($raw['timed_out'], $raw['success'])){
				if($raw['success'] === false){
					$data['timed_out'] = true;
				}
			}

			if(isset($raw['transaction_details']['risk_score'])){
				if($data['risk_score'] === null || $data['risk_score'] < $raw['transaction_details']['risk_score']){
					$data['risk_score'] = $raw['transaction_details']['risk_score'];
				}
			}

			if(isset($raw['fraud_score'])){
				if($data['fraud_score'] === null || $data['fraud_score'] < $raw['fraud_score']){
					$data['fraud_score'] = $raw['fraud_score'];
				}
			}

			if(isset($raw['risk_score'])){
				if($data['risk_score'] === null || $data['risk_score'] < $raw['risk_score']){
					$data['risk_score'] = $raw['risk_score'];
				}
			}

			return $data;
		}
		
		private static $fetch_simple_cache = <<<'SQL'
SELECT
	DataID,
	DataType,
	Cache,
	FraudScore
FROM
	%s 
ORDER BY
	DataID
DESC
LIMIT 3000
SQL;
		
	private static $fetch_email_cache = <<<'SQL'
SELECT
	Cache
FROM
	_table_name_
WHERE
	Email = %s
		AND
	Genesis >= DATE_SUB(NOW(), INTERVAL 12 HOUR) 
SQL;
		
		private static $fetch_cache = <<<'SQL'
SELECT
	ID,
	DataType,
	DataID,
	FraudScore,
	Cache,
	Genesis
FROM
	_table_name_
WHERE
	DataID = %s
ORDER BY
	FraudScore DESC
SQL;
		
		private static $fetch_cache_type = <<<'SQL'
SELECT
	ID,
	DataType,
	DataID,
	FraudScore,
	Cache,
	Genesis
FROM
	_table_name_
WHERE
	DataType = %s
		AND
	DataID = %s
SQL;
		
		private static $create_cache_table = <<<'SQL'
CREATE TABLE %s (
	ID int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	DataType varchar(64) NOT NULL,
	DataID int(11) NOT NULL,
	FraudScore int(11) NULL DEFAULT NULL,
	Cache text NOT NULL,
	Genesis timestamp NOT NULL DEFAULT current_timestamp()
) %s;
CREATE INDEX wp_ipq_cache_index ON %s (DataID, DataType);
SQL;
	
		private static $create_email_cache_table = <<<'SQL'
CREATE TABLE %s (
	ID int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	Email varchar(256) NOT NULL,
	Cache text NOT NULL,
	Genesis timestamp NOT NULL DEFAULT current_timestamp()
) %s;
CREATE INDEX wp_ipq_cache_index ON %s (Email);
SQL;
	}
	
	$IPQualityScore = new IPQualityScore();
	register_activation_hook( __FILE__, [$IPQualityScore, 'Install'] );
	register_deactivation_hook( __FILE__, [$IPQualityScore, 'Destruct'] );
	add_action('plugins_loaded', [$IPQualityScore, 'Upgrade']);
	add_filter('admin_footer_text', '__return_false', 11);
	add_filter('update_footer', '__return_false', 11);
	add_action('admin_menu', [$IPQualityScore, 'AdminMenu']);
	add_action('admin_init', [$IPQualityScore, 'Login']);
	add_action('get_header', [$IPQualityScore, 'DetectProxies']);
	add_action('comment_post', [$IPQualityScore, 'ValidateComment'], 10, 2);
	add_action('registration_errors', [$IPQualityScore, 'ValidateUser'], 10, 3);
	add_action('wp_authenticate_user', [$IPQualityScore, 'ValidateLogin'], 10, 2);
	add_action('register_post', [$IPQualityScore, 'ValidateUserEmail'], 10, 3);
	add_filter('http_request_timeout', function(){ return 10; });
	add_filter('is_email', [$IPQualityScore, 'ValidateEmail']);
	add_filter('parse_request', [$IPQualityScore, 'CustomRoutes']);
	add_filter(sprintf("plugin_action_links_%s", plugin_basename( __FILE__ )), [$IPQualityScore, 'SettingsLink']);
	add_action('plugins_loaded', [$IPQualityScore, 'CheckForWoo']);
	add_action('plugins_loaded', [$IPQualityScore, 'CheckForGravityForms']);
	add_action('init', [$IPQualityScore, 'SetupOrderDenied']);
}
?>
