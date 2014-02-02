<?php
/*
Plugin Name: WASP - Anti Spam
Plugin URI: http://wpdevplus.com/wasp-anti-spam
Description: All In One Spam Solution.
Version: 1.0
Author: Yehuda Hassine
Author URI: http://wpdevplus.com
Text Domain: wasp
*/
global $wpdb;
define('WASP_URL', plugins_url( '', __FILE__ ) );
define('WASP_PATH', plugin_dir_path(__FILE__) );
define('wasp', $wpdb->prefix . "wasp_cf7_tbl");
$options = get_option('ebk_options');

foreach ( glob( dirname( __FILE__ ) . '/inc/*.php' ) as $file ) {
    require_once $file;
}

add_action('plugins_loaded', 'ebk_init');
function ebk_init() {
	load_plugin_textdomain('wasp', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	$ebk_comment = new ebk_comment;
	$ebk_cf7 = new ebk_cf7;
	$ebk_register = new ebk_register;
	$recaptcha = new ebk_recaptcha;
}


register_activation_hook( __FILE__, 'ebk_activate' );
function ebk_activate() {
	$table_name = wasp;
      
	$sql = "CREATE TABLE $table_name (
		id int(11) NOT NULL AUTO_INCREMENT,
	  	subject VARCHAR(55) NOT NULL,
	  	sender VARCHAR(55) NOT NULL,
	  	body text NOT NULL,
	  	recipient VARCHAR(55) NOT NULL,
	  	additional_headers VARCHAR(55) NOT NULL,
	  	attachments VARCHAR(55) NOT NULL,
		ebk_key VARCHAR(33) NOT NULL,
	  	UNIQUE KEY id (id)
	  	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	$args = array (
		'recaptcha_public_key' => '',
		'recaptcha_private_key' => '',
		'ebk_email' => 1,
		'ebk_change_email' => array('Subscriber'),
		'ebk_cf7' => 1,
		'ebk_comments' => array( 'enabled' => 'on', 'ebk_comment_roles' => array('Subscriber', 'logout')),
		'ebk_auto_learn' => 1,
		'recaptcha_login' => 1,
		'recaptcha_register' => 1,
		'comments_grace_time' => 86400,
		'users_grace_time' => 86400,
		'ebk_ban_comment' => '',
		'ebk_ban_register' => '',
		'ebk_email_message' => "Hello {current_user_login},\n you getting this message because your email used to register in this site:
  		{site_name}\n to activate your account and get the access to the site, please press the
	    folowing link:\n{activation_url}\n\n Thanks you\n{site_name}",
	    'ebk_comment_message' => "Hello {current_user_login},\n you getting this message because you commented in this site:
  		{site_name}\n to approve your comment and publish it, please press the
  		folowing link:\n{activation_url}\n\n Thanks you\n{site_name}",
  		'ebk_cf7_message' => "Hello {current_user_login},\n you getting this message because you used the form in this site:
  		{site_name}\n to approve your form post and send it, please press the
  		folowing link:\n{activation_url}\n\n Thanks you\n{site_name}",
		);
	update_option('ebk_options',$args);

	if (!wp_next_scheduled('ebk_prune_spam_comments_hook')) {
		wp_schedule_event(time(), 'twicedaily', 'ebk_prune_spam_comments_hook');
	}

	if (!wp_next_scheduled('ebk_prune_unverifyed_users')) {
		wp_schedule_event(time(), 'twicedaily', 'ebk_prune_unverifyed_users');
	}
}

register_deactivation_hook( __FILE__, 'ebk_deactivate' );
function ebk_deactivate() {
	global $wpdb;
	$table_name = wasp;
	$sql = "DROP TABLE $table_name";
	$result = $wpdb->query($sql);
	delete_option('ebk_options');
	remove_action('ebk_prune_spam_comments_hook','ebk_prune_spam_comments');
	wp_clear_scheduled_hook( 'ebk_prune_spam_comments_hook' );
	remove_action('ebk_prune_unverifyed_users','ebk_prune_unverifyed_users');
	wp_clear_scheduled_hook( 'ebk_prune_unverifyed_users' );
}

/*
add_filter('cron_schedules', 'ebk_add_scheduled_interval');
function ebk_add_scheduled_interval($schedules) {
	$options = get_option('ebk_options');
	$ebk_interval = 180;
	$schedules['wasp_interval'] = array('interval'=> $ebk_interval, 'display'=>'WASP Custom interval');
	return $schedules;
}
*/

//add links and meta to plugin info
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ebk_plugin_action_links', 10, 2 );
function ebk_plugin_action_links( $links, $file ) {
	return array_merge(
		array('settings' => '<a href="' . admin_url("options-general.php?page=ebk_anti-spam") . '">Settings</a>'),$links);
}

add_filter( 'plugin_row_meta', 'ebk_plugin_meta_links', 10, 2 );
function ebk_plugin_meta_links( $links, $file ) {
	$plugin = plugin_basename(__FILE__);
	if ( $file == $plugin ) {
		return array_merge(
			$links,
			array( '<a target="_blank" href="http://wpdevplus.com/forums">Support</a>' )
		);
	}
	return $links;

}

add_action('login_enqueue_scripts','ebk_front_scripts');
add_action('wp_enqueue_scripts', 'ebk_front_scripts');
function ebk_front_scripts(){
	$options = get_option('ebk_options');
	if ($options['ebk_email'] == 1) {
		wp_enqueue_script('ebk-script-js', plugins_url('/js/script.js', __FILE__),array('jquery'));
	}
	wp_enqueue_style('ebk-style', plugins_url('/css/style.css', __FILE__));
	wp_localize_script( 'ebk-script-js', 'ebkajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
	wp_localize_script('ebk-script-js', 'ebkL10n', ebk_js_l10n());
}

/* JavaScript localisation
 */
function ebk_js_l10n()
{
    return array(
        'empty_submitting_form' => __('The field is empty, please fill it.', 'wasp')
    );
}

add_action( 'admin_enqueue_scripts', 'ebk_admin_scripts');
function ebk_admin_scripts() {
	wp_enqueue_script('ebk-qtip-js', plugins_url('/js/jquery.qtip.min.js', __FILE__),array('jquery'));
	wp_enqueue_script('ebk-admin-tooltip-js', plugins_url('/js/admin.tooltip.js', __FILE__));
	wp_enqueue_style('ebk-qtip-style', plugins_url('/css/jquery.qtip.min.css', __FILE__));
	wp_enqueue_style('admin-style', plugins_url('/css/admin.style.css', __FILE__));
}



function build_sql_insert($table, $data) {
	if (isset($data['attachments']))
		unset($data['attachments']);

    $key = array_keys($data);
    $val = array_values($data);

    $sql = "INSERT INTO $table (" . implode(', ', $key) . ") "
         . "VALUES ('" . implode("', '", $val) . "')";
 
    return($sql);
}

function ebk_replace_tag_holders($message,$activation_url) {
    $current_user = wp_get_current_user();
    return str_replace(array('{activation_url}', '{site_name}', '{current_user_display}', '{current_user_email}', '{current_user_login}'), 
    	array($activation_url, get_bloginfo('name'), $current_user->display_name, $current_user->user_email ,$current_user->user_login), $message);
}

function ebk_check_roles($options_roles){
	$current_user = wp_get_current_user();
	if ($current_user->ID == 0) {
		if (in_array('logout', $options_roles)) {
			return true;
		} else {
			return false;
		}
	}

	$user_roles = $current_user->roles;
	foreach ($user_roles as $key => $value) {
		if (in_array(ucfirst($value), $options_roles))
			return true;
	}
	return false;
}

function ebk_getip() {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

	return $ip; 
}
?>