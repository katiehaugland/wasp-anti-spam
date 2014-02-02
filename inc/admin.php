<?php
  require_once("admin-page-class/admin-page-class.php");


  $config = array(    
		'menu'=> 'settings',             
		'page_title' => __('WASP Anti-Spam','wasp'),       
		'capability' => 'manage_options',        
		'option_group' => 'ebk_options',      
		'id' => 'ebk_page',           
		'fields' => array(),           
		'local_images' => false,       
		'use_with_theme' => false          
  );  
  

  $options_panel = new BF_Admin_Page_Class($config);
  $options_panel->OpenTabs_container('');
  

  $options_panel->TabsListing(array(
    'links' => array(
    'options_1' =>  __('General Options','wasp'),
    'options_2' =>  __('Recaptcha Options','wasp'),
    'options_3' => __('Ban Options','wasp'),
    'options_4' => __('Customise Messages','wasp'),
    'options_5' =>  __('Import Export','wasp'),
    )
  ));
  

  $options_panel->OpenTab('options_1');
  $options_panel->Title(__("General Options","apc"));
  $options_panel->addCheckbox('ebk_email',array('name'=> __('Enable User Activation By Email <a href="#" tooltip="By default wordpress doesn\'t let the user choose password
            and random password is sent to the user email, By enable this option, you will enable each register user to pick his own password so to verify his email address
           he will get a unique url to his mailbox, and just after he will click it he can login to your site. This will help you ensure the unique identy and email of each new user 
           and take action in case for example of spammer by blocking is email address."><img src="'  . WASP_URL . '/images/help-icon.png" /></a> ','wasp'), 'std' => true));
  $options_panel->addRoles('ebk_change_email',array('type' => 'checkbox_list' ),array('name'=> __('Users <strong>NOT</strong> allowed to change there E-mail address','wasp')));
  if (defined('WPCF7_VERSION')) { 
    $options_panel->addCheckbox('ebk_cf7',array('name'=> __('Enable EBK Contact Form 7 Integration ','wasp'), 'std' => true)); 
  }

  $Conditinal_fields1[] = $options_panel->addRoles('ebk_comment_roles',array('type' => 'checkbox_list', 'logout' => true),array('name'=> __('To wich user roles apply this protection?','wasp')),true);  
  $options_panel->addCondition('ebk_comments',
    array(
      'name'=> __('Activate EBK Protection For Comments.<a href="#" tooltip="The idea beyond EBK his to prove you are real, so each
            comment or messaging attempt must be verify, after the user submit one of the two he will get a uniqe url to his inbox, after clicking
            the url the message is approved and get sent (Contact Form 7) OR publish (commenting system)."><img src="' . WASP_URL . '/images/help-icon.png" /></a> ','wasp'), 
      'fields' => $Conditinal_fields1,
      'std' => true
      ));

  $options_panel->addCheckbox('ebk_auto_learn',array('name'=> __('Enable Auto Learn Mode ','wasp'), 'desc' => __('Before auto delete of spam comment or unverifyed user the data will used by the plugin for blocking another registration or commenting.','wasp'),'std' => true));
  $options_panel->addText('users_grace_time', array('name'=> __('Time intervals To Delete Users From There Registration Date (in seconds). ','wasp')));
  $options_panel->addText('comments_grace_time', array('name'=> __('Time intervals To Delete Comments From Posting Date (in seconds). ','wasp')));
  $options_panel->CloseTab();
   
  $options_panel->OpenTab('options_2');
  $options_panel->Title(__('Recaptcha Options','wasp'));
  $options_panel->addText('recaptcha_public_key', array('name'=> __('Recaptcha Public Key ','wasp'), 'desc' => __('<b><i>You can get your key </i></b><a href="https://www.google.com/recaptcha/admin/create"> Here</a>','wasp')));
  $options_panel->addText('recaptcha_private_key', array('name'=> __('Recaptcha Private Key ','wasp'), 'desc' => __('<b><i>You can get your key </i></b><a href="https://www.google.com/recaptcha/admin/create"> Here</a>','wasp')));
  $options_panel->addCheckbox('recaptcha_register',array('name'=> __('Enable Recaptcha For Registration ','wasp'), 'std' => true));
  $options_panel->addCheckbox('recaptcha_login',array('name'=> __('Enable Recaptcha For Login ','wasp'), 'std' => true));
  $Conditinal_fields2[] = $options_panel->addRoles('recaptcha_comment_roles',array('type' => 'checkbox_list' , 'logout' => true),array('name'=> __('Wich Users Roles Will SEE IT?','wasp')),true);  
  $Conditinal_fields2[] = $options_panel->addSelect('recaptcha_theme',array('red'=>'red','white'=>'white','blackglass'=>'blackglass','clean'=>'clean'),array('name'=> __('Recaptcha Themes','wasp'), 'std'=> array('red'), 'desc' => __('Choose recaptcha theme, apply only for commnents','wasp')),true);
  $options_panel->addCondition('recaptcha_comments',
      array(
        'name'=> __('Enable Recaptcha For Comments ','wasp'),
        'fields' => $Conditinal_fields2,
        'std' => false
      ));
  $options_panel->CloseTab();


  $options_panel->OpenTab('options_3');
  $options_panel->addTextarea('ebk_ban_register',array('name'=> __('Ban users from register ','wasp')));
  $options_panel->addTextarea('ebk_ban_comment',array('name'=> __('Ban users from comment ','wasp')));
  $options_panel->CloseTab();


  $options_panel->OpenTab('options_4');
  $options_panel->addWysiwyg('ebk_email_message',array('name'=> __('Account Activation E-mail ','wasp'), 'style' =>'width: 300px; height: 800px'));
  $options_panel->addWysiwyg('ebk_comment_message',array('name'=> __('Comment Approve E-mail ','wasp')));
  $options_panel->addWysiwyg('ebk_cf7_message',array('name'=> __('Contact Form 7 Approve E-mail ','wasp')));
  $options_panel->CloseTab();
  

  $options_panel->OpenTab('options_5');
  $options_panel->Title(__("Import Export","wasp"));
  $options_panel->addImportExport();
  $options_panel->CloseTab();


  $options_panel->CloseTab();