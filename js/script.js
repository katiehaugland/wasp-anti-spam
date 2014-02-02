jQuery('document').ready(function($) {
	jQuery('#nav').append('<br><a id="resend_activation" href="javascript:void(0);">Resend Activation Email</a>');
	jQuery('#backtoblog').append('<div id="ebk_resend_activiation_form" class="login form" style="margin-top:20px;display:none;"><p><label for="user_login_resend">E-mail:<br><input type="text" name="user_login_resend" id="user_login_resend" class="input" value="" size="20"></label></p><p class="submit"><input type="submit" name="wp-submit-resend" id="wp-submit-resend" class="button button-primary button-large" value="Resend Activation Code"></p></div>');

	jQuery('#resend_activation').click(function() {
		jQuery('#ebk_resend_activiation_form').toggle();
		var h = jQuery(document).height();
	    $('html, body').animate({ scrollTop: h },'50');
	});

	jQuery('#wp-submit-resend').click(function() {
		var field = jQuery('#user_login_resend').val();
		if (field.length == 0) {
			alert(ebkL10n.empty_submitting_form);
			return false;
		}

		jQuery.post(ebkajax.ajaxurl ,{action: "ebk_resend_activation", field: field}, function(data) { 
			alert(data);
		});
	});
});