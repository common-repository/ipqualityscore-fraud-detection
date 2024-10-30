<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

delete_option('ipqualityscore_key');
delete_option('ipq_proxy_check_orders');
delete_option('ipq_prevent_invalid_proxy_orders');
delete_option('ipq_email_check_orders');
delete_option('ipq_prevent_invalid_email_orders');
delete_option('ipq_device_tracker_orders');
delete_option('ipq_prevent_fraudulent_device_orders');
delete_option('ipq_cancel_fraudulent_orders_max_fraud_score');
delete_option('ipq_proxy_all_pages');
delete_option('ipq_vpn_all_pages');
delete_option('ipq_tor_all_pages');
delete_option('ipq_max_fraud_score');
delete_option('ipq_pages');
delete_option('ipq_pages_redirect');
delete_option('ipq_validate_account_emails');
delete_option('ipq_prevent_proxy_registrations');
delete_option('ipq_prevent_proxy_logins');
delete_option('ipq_validate_comment_emails');
delete_option('ipq_prevent_proxy_comments');
delete_option('ipq_allow_admin_login');
delete_option('ipq_tracker_key');
delete_option('ipq_tracker_domain');

global $wpdb;
$table = $wpdb->prefix.'ipqs_cache';
$sql = "DROP TABLE IF EXISTS $table";
$wpdb->query($sql);

delete_option('ipq_database_version');
?>