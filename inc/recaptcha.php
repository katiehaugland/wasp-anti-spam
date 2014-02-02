<?php
require_once('recaptchalib.php');


class ebk_recaptcha {
	private $options;
	private $publickey;
	private $privatekey;
	private $recaptcha_theme;
	private $roles;

	function __construct() {
		$this->options = get_option('ebk_options');
		$this->publickey = (isset($this->options['recaptcha_public_key'])) ? $this->options['recaptcha_public_key'] : null;
		$this->privatekey = (isset($this->options['recaptcha_private_key'])) ? $this->options['recaptcha_private_key'] : null;
		$this->recaptcha_theme = (isset($this->options['recaptcha_comments']['recaptcha_theme'])) ? $this->options['recaptcha_comments']['recaptcha_theme'] : 'red';
		$this->roles = (isset($this->options['recaptcha_comments']['recaptcha_comment_roles'])) ? $this->options['recaptcha_comments']['recaptcha_comment_roles'] : null;

		add_action('wp_head', array( &$this, 'recaptcha_custom_script'));

		if ($this->options['recaptcha_login'] == 1) {
			add_action('login_head', array( &$this, 'recaptcha_custom_script'));
			add_action('login_form', array( &$this,'ebk_add_recaptcha'));
			add_filter('authenticate', array( &$this,'validate_login_recaptcha'),2,3);			
		}

		if ($this->options['recaptcha_register'] == 1) {
			add_action('register_form', array( &$this, 'ebk_add_recaptcha'),100);
			add_filter('registration_errors',array( &$this,'validate_register_recaptcha'),10, 3);
		}

		if (isset($this->roles) && ebk_check_roles($this->roles) 
			&& isset($this->options['recaptcha_comments']['enabled'])) {
			add_action('pre_comment_on_post', array( &$this,'ebk_comments_recaptcha_validate'),3);
			add_action('comment_form' ,array( &$this,'ebk_comment_recaptcha'));
		}
	}

	function ebk_comment_recaptcha() {
		echo recaptcha_get_html($this->publickey);
	}

	function recaptcha_custom_script() {
		echo '<script type="text/javascript">';
		if ($GLOBALS['pagenow'] =='wp-login.php') {
			echo "var RecaptchaOptions = {
			    theme : 'custom',
			    custom_theme_widget: 'recaptcha_widget'
			};";
		} else {
			echo "var RecaptchaOptions = {
			    theme : '" . $this->recaptcha_theme . "'
			 };";
		}
		echo '</script>
		<style type="text/css">
		div#recaptcha_image > img {
			width:280px;
		}
		</style>';
	}

	function ebk_add_recaptcha() { ?>
		<div id="recaptcha_widget" style="display:none">

		   <div id="recaptcha_image"></div>
		   <div class="recaptcha_only_if_incorrect_sol" style="color:red"><?php echo __('Incorrect please try again', 'wasp'); ?></div>

		   <span class="recaptcha_only_if_image"><?php echo __('Enter the words above:', 'wasp'); ?></span>
		   <span class="recaptcha_only_if_audio"><?php echo __('Enter the numbers you hear:', 'wasp'); ?></span>

		   <input type="text" id="recaptcha_response_field" name="recaptcha_response_field" />

		   <div><a href="javascript:Recaptcha.reload()"><?php echo __('Get another CAPTCHA', 'wasp'); ?></a></div>
		   <div class="recaptcha_only_if_image"><a href="javascript:Recaptcha.switch_type('audio')"><?php echo __('Get an audio CAPTCHA', 'wasp'); ?></a></div>
		   <div class="recaptcha_only_if_audio"><a href="javascript:Recaptcha.switch_type('image')"><?php echo __('Get an image CAPTCHA', 'wasp'); ?></a></div>

		   <div><a href="javascript:Recaptcha.showhelp()"><?php echo __('Help', 'wasp'); ?></a></div>

		 </div>
		 <br><br>
		 <script type="text/javascript"
		    src="http://www.google.com/recaptcha/api/challenge?k=<?php echo $this->publickey; ?>">
		 </script>
		 <noscript>
		   <iframe src="http://www.google.com/recaptcha/api/noscript?k=<?php echo $this->publickey; ?>"
		        height="300" width="500" frameborder="0"></iframe><br>
		   <textarea name="recaptcha_challenge_field" rows="3" cols="40">
		   </textarea>
		   <input type="hidden" name="recaptcha_response_field"
		        value="manual_challenge">
		 </noscript>	 
		<?php
	}

	function validate_login_recaptcha($user, $username, $password) {
		if ( empty( $username ) || empty($password) ) {
			if ( empty($username) )
				$user = new WP_Error( 'empty_username', __( '<strong>ERROR</strong>: The username field is empty.', 'wasp' ) );
	
			if ( empty($password) )
				$user = new WP_Error( 'empty_password', __( '<strong>ERROR</strong>: The password field is empty.', 'wasp' ) );

			return $user;
		}

		if (isset($_POST["recaptcha_challenge_field"]) && isset($_POST["recaptcha_response_field"])) {
			$resp = recaptcha_check_answer ($this->privatekey,
			                            $_SERVER["REMOTE_ADDR"],
			                            $_POST["recaptcha_challenge_field"],
			                            $_POST["recaptcha_response_field"]);

			if (!$resp->is_valid) {
				$user = new WP_Error('empty_recaptcha', __("<strong>ERROR</strong>: The reCAPTCHA wasn't entered correctly. Go back and try it again." . $resp->error,'wasp'));
				remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
			} else {
				return $user;
			}
		}
		return $user;
	}

	function validate_register_recaptcha($errors, $login, $email) {
		if (isset($_POST["recaptcha_challenge_field"]) && isset($_POST["recaptcha_response_field"])) {
			$resp = recaptcha_check_answer ($this->privatekey,
			                            $_SERVER["REMOTE_ADDR"],
			                            $_POST["recaptcha_challenge_field"],
			                            $_POST["recaptcha_response_field"]);

			if (!$resp->is_valid) {
				$errors->add('empty_recaptcha', __("<strong>ERROR</strong>: The reCAPTCHA wasn't entered correctly. Go back and try it again." . $resp->error,'wasp'));
			} 
		}
		return $errors;
	}

	function ebk_comments_recaptcha_validate() {
		if (isset($_POST["recaptcha_challenge_field"]) && isset($_POST["recaptcha_response_field"])) {
			$resp = recaptcha_check_answer ($this->privatekey,
			                            $_SERVER["REMOTE_ADDR"],
			                            $_POST["recaptcha_challenge_field"],
			                            $_POST["recaptcha_response_field"]);

			if (!$resp->is_valid) {
				if ($resp->error == 'incorrect-captcha-sol') {
					wp_die(__("Incorrect Recaptcha Entered, Please Try Again", 'wasp') );
				} else {
					wp_die($resp->error);
				}
			}
		}
	}
}

?>