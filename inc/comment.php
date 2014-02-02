<?php

class ebk_comment {
	private $options;
	private $enabled;
	private $roles;

	function __construct() {
		$this->options = get_option('ebk_options');
		$this->enabled = (isset($this->options['ebk_comments']['enabled'])) ? true : false;
		$this->roles = (isset($this->options['ebk_comments']['ebk_comment_roles'])) ?  true : false;
		$this->access = ($this->roles) ?  ebk_check_roles($this->options['ebk_comments']['ebk_comment_roles']) : false;

		add_action('comment_post', array(&$this, 'ebk_do_comment_process'),20, 2);
		add_action('ebk_prune_spam_comments_hook', array(&$this, 'ebk_prune_spam_comments'));
		add_action('pre_comment_on_post', array(&$this, 'ebk_email_validator'));
		add_action('pre_get_comments', array(&$this, 'check_comment_key'));
		add_filter('comment_row_actions', array(&$this, 'add_ban_link'), 10, 2);
		add_action('wp_ajax_bancomment', array(&$this, 'wp_ajax_bancomment'));
	}

	function wp_ajax_bancomment() {
		if (isset($_GET['action']) && $_GET['action'] == 'bancomment') {
			$comment_id = absint( $_REQUEST['c'] );
			check_admin_referer( 'ban-comment_' . $comment_id );
			$value = get_comment($comment_id, ARRAY_A);

			$ban = (isset($this->options['ebk_ban_comment'])) ? explode(",", $this->options['ebk_ban_comment']) : array();
			if (!in_array($value['comment_author_IP'], $ban))	
				$ban[] = $value['comment_author_IP'];

			if (!in_array($value['comment_author_email'], $ban))	
				$ban[] = $value['comment_author_email'];

			$this->options['ebk_ban_comment'] = implode(",", $ban);
			update_option('ebk_options', $this->options);
			$location = ( empty( $_POST['referredby'] ) ? "edit-comments.php?p=$comment_post_id" : $_POST['referredby'] ) . '#comment-' . $comment_id;
			$location = apply_filters( 'comment_edit_redirect', $location, $comment_id );
			wp_redirect( $location );
		}	
	}

	function add_ban_link($actions, $comment) {
		$ban_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "ban-comment_$comment->comment_ID" ) );
		$url = admin_url('admin-ajax.php') . "?c=$comment->comment_ID";
		$ban_url = esc_url( $url . "&action=bancomment&$ban_nonce" );


		$actions['ban'] = 
		"<a href='$ban_url' title='" . esc_attr__( 'Add user detailes to wasp ban system', 'wasp' ) . "'>" .
		 __( 'Ban', 'wasp' ) . '</a>';
		 return $actions;
	}

	function check_comment_key() {
		if (isset($_GET['ebk_comment'])) {
			$key = filter_input(INPUT_GET, 'ebk_comment');
			global $wpdb;
			$comment_id = $wpdb->get_var( $wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_value = '%s';", $key) );
			if ($comment_id != null) {
				if (!wp_unspam_comment($comment_id)) {
					wp_die(__("There Was An Error Approving Your Comment, Please Contact Site Admin.",'wasp'));
				}

				delete_comment_meta($comment_id,'ebk_comment_activation');

				$commentdata = get_comment($comment_id, ARRAY_A);
				wp_redirect(get_permalink($commentdata['comment_post_ID']) . '#comment-' . $comment_id);
				exit();
			} else {
				wp_die(__("There was a problem with the key OR no key found",'wasp'));
			}			
		}
	}


	function ebk_do_comment_process($comment_ID, $comment_status){
		$comment = get_comment($comment_ID, ARRAY_A);
		if (!$this->is_comment_author($comment) && $this->access) {
			wp_spam_comment($comment_ID);
			$key = md5(microtime());
			add_comment_meta($comment_ID, 'ebk_comment_activation', $key);

			$current_url = get_permalink( $comment['comment_post_ID']);
	      	$activation_url = add_query_arg('ebk_comment', $key , $current_url);
			$subject = __("Your comment is pending for approve in the website ", 'wasp') . get_bloginfo( 'name' );
			$message = ebk_replace_tag_holders(stripslashes(html_entity_decode($this->options['ebk_comment_message'])),$activation_url);
			if(wp_mail($comment['comment_author_email'], $subject, $message)) {	
				//wp_redirect(get_permalink($commentdata['comment_post_ID']);	
				wp_die(__("Your comment submitted successfully,<br> it will show ONLY after pressing the link in your email inbox.",'wasp') );
			} else {		
				wp_die(__("There was a problem sending approve email, check you wrote you email address correct.",'wasp') );
			}
		}
	}

	function is_comment_author($commentdata) {
		$userdata = get_userdata($commentdata['user_id']);
		$user = new WP_User($commentdata['user_id']);
		$post = get_post($commentdata['comment_post_ID']);
		if ( isset($userdata) && ( $post->post_author != $commentdata['user_id'])) {
			return false;
		} else {
			return true;
		}		
	}

	function ebk_prune_spam_comments() {
		global $wpdb;
		$result = $wpdb->get_results("SELECT comment_ID,comment_author_IP,comment_author_email,comment_date_gmt FROM  " . $wpdb->comments . " WHERE comment_approved = 'spam';",ARRAY_A);
		if (!empty($result)) {
			$ban = (isset($this->options['ebk_ban_comment'])) ? explode(",", $this->options['ebk_ban_comment']) : array();
			foreach ($result as $key => $value) {	
				if ($this->options['ebk_auto_learn'] == 1) {
					if (!in_array($value['comment_author_IP'], $ban))	
						$ban[] = $value['comment_author_IP'];

					if (!in_array($value['comment_author_email'], $ban))	
						$ban[] = $value['comment_author_email'];
				}
				if (time() - strtotime($value['comment_date_gmt']) > $this->options['comments_grace_time'])
					wp_delete_comment( $value['comment_ID'], true );
			}
			$this->options['ebk_ban_comment'] = implode(",", $ban);
			update_option('ebk_options', $this->options);
		}
	}

	function ebk_email_validator($comment_post_ID) {
		if (!empty($_POST['email'])) {
			$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
		    if (!is_user_logged_in() && !$email) {
		    	wp_die(__("Error: You must fill a valid email address to post a comment.",'wasp') );
		    }			
		} else {
			$user = wp_get_current_user();
			if ( $user->exists() ) {
				$email = wp_slash( $user->user_email );
			}
		} 

		if (empty($_POST['email']) && !is_user_logged_in()) {	
			wp_die(__("Error: You must fill a valid email address to post a comment.",'wasp') );
		}

		if (isset($this->options['ebk_ban_comment'])) {
			$ban = explode(",", $this->options['ebk_ban_comment']);
			$ip = ebk_getip();
			if (in_array($email, $ban) || in_array($ip, $ban)) {
				wp_die(__("Your Email OR IP Address is BAN.",'wasp') );
			}		
		}

	    if (empty($_POST['comment'])) {
	    	wp_die(__('Error: You didn\'t enter any comment.','wasp') );
		}
	}
}






