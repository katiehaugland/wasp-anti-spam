<?php
class ebk_cf7 {
  private $table;
  private $key;

  function __construct() {
    global $wpdb;

    $this->table = $wpdb->prefix . 'wasp_cf7_tbl';
    if (isset($_GET['ebk_cf7'])) {
      $this->key = filter_input(INPUT_GET, 'ebk_cf7');
    }

    //on submit hooks
    if (!isset($_GET['ebk_cf7'])) {
      add_filter('wpcf7_mail_components', array( &$this,'save_form_components'));
      add_action('wpcf7_before_send_mail', array( &$this,'change_form_messages'));
    }

    //after user press key
    if ($this->key && $this->verifyKey($this->key)) {
      add_action('wp_head', array( &$this,'cf7_submit'));
      add_filter('wpcf7_mail_components', array( &$this,'get_form_com'));
      add_filter('wpcf7_validate' , array( &$this,'cf7_val_bypass'));
      add_action('wpcf7_mail_sent', array( &$this, 'mailSent'));
      //add_filter('wpcf7_form_action_url', array( &$this,'cf7_url'));
    }
  }

  /**
  on submit
  **/
  function save_form_components($components) {
    global $wpdb;
    $options = get_option('ebk_options');
    $components['ebk_key'] = md5(microtime());
    $sql = build_sql_insert($this->table, $components);
    if ($wpdb->query($sql)) {
      $current_url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
      $activation_url = add_query_arg('ebk_cf7', $components['ebk_key'], $current_url);
      $to = $components['sender'];
      $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
      $subject = sprintf(__('A message from the website %s:'), $blogname);
      $message = ebk_replace_tag_holders(stripslashes(html_entity_decode($options['ebk_cf7_message'])),$activation_url);
      wp_mail($to,$subject,$message);
    } else {
      //debug
    }

      // will hold the form data from sent by set bulk email address
      $components['recipient'] = 'hold-this-please@no-domain.com';
      return $components;
  }

  function change_form_messages(&$wpcf7) {
    $wpcf7->messages['mail_sent_ok'] = __('This message will be send ONLY by pressing the link in your email inbox','wasp');
  }

  /**
  after user press key
  **/
  function cf7_submit() {
    ?>
      <script>
      jQuery(window).load(function() {
        jQuery('.wpcf7-form').submit(); 
        jQuery('html, body').animate({
          scrollTop: jQuery(".wpcf7-submit").offset().top
              }, 2000);   
      });
      </script>
    <?php
  }

  function cf7_val_bypass() {
    $result = array();
    $result['mail_sent'] = true;
    $result['valid'] = true;
    return $result;
  }


  function mailSent() {
    global $wpdb;
    $wpdb->get_var( $wpdb->prepare( "DELETE FROM " . $this->table . " where ebk_key = '%s'", $this->key) );
  }

  function verifyKey($key) {
    global $wpdb;
    $result = $wpdb->get_var( $wpdb->prepare( "SELECT count(ebk_key) FROM " . $this->table . " WHERE 
      ebk_key = '%s'", $key) );
    return (bool)$result;
  }

  function get_form_com($components) {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare( "select * from " . $this->table . " where
    ebk_key = '%s'" , $this->key) ,ARRAY_A); 
    if ($rows == null || count($rows) == 0) {
      return $components;
    }
    return $rows[0];
  }
}
