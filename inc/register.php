<?php

class ebk_register {
	private $options;

	function __construct() {
		$this->options = get_option('ebk_options');

		if ($this->options['ebk_email'] == 1) {
			// passwords fields
			wp_enqueue_script('user-profile'); 
			add_action( 'register_form', array( &$this, 'ebk_show_extra_register_fields') ,2);
			add_action( 'register_post', array( &$this, 'ebk_check_extra_register_fields'), 10, 3 );
			add_filter( 'gettext', array( &$this, 'ebk_edit_password_email_text' ));

			add_filter('authenticate', array( &$this, 'check_activation_status'),2,3);
			add_filter('login_messages', array( &$this, 'ebk_activation_message'));
			add_action('login_head', array( &$this, 'ebk_email'));
			add_action("wp_ajax_ebk_resend_activation", array( &$this, "ebk_resend_activation"));
			add_action("wp_ajax_nopriv_ebk_resend_activation", array( &$this, "ebk_resend_activation"));
			//add_action('login_init', 'ebk_email');
		}
		add_filter('registration_errors', array( &$this, 'ebk_validate_ban_info') ,10, 3);
		add_action('ebk_prune_unverifyed_users', array( &$this, 'ebk_prune_unverifyed_users'));
	}

	function ebk_resend_activation() {
		$field = filter_input(INPUT_POST, 'field',FILTER_VALIDATE_EMAIL);
		if (!$field) {
			echo __("This Email Address Is Not Valid.", 'wasp');
			die();
		}

		$user_info = get_user_by('email', $field);
		if (!$user_info) {
			echo __("There Is No Such Record In Our database", 'wasp');
			die();
		}

		if (get_user_meta( $user_info->ID, 'ebk_email_activation_status', true) == 1) {
			echo __("This email address all ready activated.");
			die();
		}

		$ebk_email_activation = get_user_meta( $user_info->ID, 'ebk_email_activation',true);
		$activation_url = get_bloginfo('url') . "/wp-login.php?ebk_email=" . $ebk_email_activation;
		wp_new_user_notification($user_info->ID, false, true);
		echo __("Activation Email Sent.", 'wasp');
		die();		
	}

	function ebk_email() {
		$key = filter_input(INPUT_GET,'ebk_email', FILTER_SANITIZE_STRING);
		if ($key) {
			global $wpdb;
			$user_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_value = '%s';", $key) );
			if (is_numeric($user_id)) {
				update_user_meta($user_id,'ebk_email_activation_status','1');
				add_filter('login_message', create_function('$message','return "<div class=\"message\">Your account is now activated, You can login.</div>";'));
			} else {
				add_filter('login_message', create_function('$message','return "<div id=\"login_error\"><strong>ERROR: </strong>There was a problem with the key OR no key found.</div>";'));
			}
		}
	}


	function ebk_show_extra_register_fields(){
	?>
		<p>
			<label for="pass1"><?php _e('Password') ?><br />
			<input type="password" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="off" /></label>
		</p>
		<p>
			<label for="pass2"><?php _e('Repeat password') ?><br />
			<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" /></label>
		</p>
		<div id="pass-strength-result" class="hide-if-no-js"><?php _e('Strength indicator'); ?></div>
		<p class="description indicator-hint"><?php __('Hint: The password should be at least seven characters long. To make it stronger, use upper and lower case letters, numbers and symbols like ! " ? $ % ^ &amp; ).'); ?></p>

		<br class="clear" />
	<?php
	}
	 
	
	function ebk_check_extra_register_fields($login, $email, $errors) {
		if ( $_POST['pass1'] !== $_POST['pass2'] ) {
			$errors->add( 'passwords_not_matched', __("<strong>ERROR</strong>: Passwords must match", 'wasp') );
		}
	}
	
	function ebk_edit_password_email_text ( $text ) {
		if ( $text == 'A password will be e-mailed to you.' ) {
			$text = __('If you leave password fields empty one will be generated for you. Password must be at least eight characters long.', 'wasp');
		}
		return $text;
	}

	function check_activation_status($user,$username,$password) {
		$user_info = get_user_by('login', $username);
		if ($user_info) {
			$activation_status = get_user_meta($user_info->ID, 'ebk_email_activation_status', true);
			
			if ($activation_status == 1 || $activation_status == null) {
				return $user;
				exit();
			} else {
				$user = new wp_error('ebk_email_not_activate', __("Your email address is not activated, please go to your inbox and look 
					for\n the activation code email",'wasp'));
				remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
			}
		}
	    return $user;
	}

	function ebk_activation_message($messages) {
		$action = (isset($_REQUEST['checkemail'])) ? $_REQUEST['checkemail'] : '';
		if( $action == 'registered' ) {
			$messages = __('Please check your email inbox for activation link.','wasp');	
		}
		return $messages;
	}


	function ebk_validate_ban_info($errors, $login, $email) {
		if (isset($this->options['ebk_ban_register'])) {
			$ban = explode(",", $this->options['ebk_ban_register']);
			$ip = ebk_getip();
			if (in_array($email, $ban) || in_array($ip, $ban)) {
				$errors->add('ebk_ban_register', __("<strong>ERROR</strong>: Your Email OR IP Address is BAN." ,'wasp'));
			}		
		}
		return $errors;	
	}

	function ebk_prune_unverifyed_users() {
		global $wpdb;
		$result = $wpdb->get_results("SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key = 'ebk_email_activation_status' AND meta_value = '0';",ARRAY_A);
		if (!empty($result)) {
			$ban = (isset($this->options['ebk_ban_register'])) ? explode(",", $this->options['ebk_ban_register']) : array();
			foreach ($result as $key => $value) {
				if ($this->options['ebk_auto_learn'] == 1) {

					$user = get_userdata($value['user_id']);
					if (!in_array($user->user_email , $ban))	
						$ban[] = $user->user_email;
				}
				$user = get_userdata($value['user_id']);
				$register_date = $user->user_registered;
				if (time() - strtotime($register_date) > $this->options['users_grace_time']) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user($value['user_id']);
				}
			}
			$this->options['ebk_ban_register'] = implode(",", $ban);
			update_option('ebk_options', $this->options);
		}
	}

}


if ( !function_exists('wp_new_user_notification') ) :
/**
 * Notify the blog admin of a new user, normally via email.
 *
 * @since 2.0
 *
 * @param int $user_id User ID
 * @param string $plaintext_pass Optional. The user's plaintext password
 */
function wp_new_user_notification($user_id, $plaintext_pass = '', $resend = false) {
	$user = get_userdata( $user_id );

	// The blogname option is escaped with esc_html on the way into the database in sanitize_option
	// we want to reverse this for the plain text arena of emails.
	$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

	$message  = sprintf(__('New user registration on your site %s:'), $blogname) . "\r\n\r\n";
	$message .= sprintf(__('Username: %s'), $user->user_login) . "\r\n\r\n";
	$message .= sprintf(__('E-mail: %s'), $user->user_email) . "\r\n";

	@wp_mail(get_option('admin_email'), sprintf(__('[%s] New User Registration'), $blogname), $message);
	
	$options = get_option('ebk_options');
	global $pagenow;
	if ($options['ebk_email'] == 1 && $pagenow != 'user-new.php') {
		if (empty($_POST['pass1'])) {
			$plaintext_pass = wp_generate_password( 12, false);
		} else {
			$plaintext_pass = filter_input(INPUT_POST, "pass1");
		}

		if (!$resend) {
			$userdata['ID'] = $user_id;
			$userdata['user_pass'] = $plaintext_pass;
			$new_user_id = wp_update_user( $userdata );
			update_user_meta( $user_id, 'ebk_email_activation_status', 0 );
		} else {
			$plaintext_pass = __('(Your password.)', 'wasp');
		}


		$key = md5(microtime());
		update_user_meta( $user_id, 'ebk_email_activation', $key);

		$activation_url = get_bloginfo('url') . "/wp-login.php?ebk_email=" . $key;
		$subject = "Account Activation from the website " . get_bloginfo( 'name' );
		$message = "Username: " . $user->user_login . "\r\nPassword: " . $plaintext_pass . "\r\n";
		$message .= html_entity_decode(ebk_replace_tag_holders(stripcslashes($options['ebk_email_message']),$activation_url));
		//$headers = 'Content-type: text/html';
		wp_mail($user->user_email,$subject,$message);
	} else {
		if ( empty($plaintext_pass) )
			return;

		$message  = sprintf(__('Username: %s'), $user->user_login) . "\r\n";
		$message .= sprintf(__('Password: %s'), $plaintext_pass) . "\r\n";
		$message .= wp_login_url() . "\r\n";
		wp_mail($user->user_email, sprintf(__('[%s] Your username and password'), $blogname), $message);
	}

}
endif;

