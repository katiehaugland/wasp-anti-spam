<?php
$ebkprofile = new ebkProfile;



class ebkProfile {

    private $options;

    function __construct() {
        $this->options = get_option('ebk_options');

        global $pagenow;
        if (isset($this->options['ebk_change_email']) && $pagenow != 'user-new.php') {
            add_action( 'user_profile_update_errors', array( &$this, 'ebk_prevent_email_change') ,10, 3);   
            add_action( 'all_admin_notices', array( &$this, 'ebk_new_user_email_admin_notice' ));
        }

        if ($this->options['ebk_email'] == 1) {
            add_action('manage_users_custom_column',  array( &$this, 'ebk_show_user_activation_column'), 10, 3);  
            add_filter( 'manage_users_sortable_columns', array( &$this, 'user_sortable_columns' ));
            // custom filter result
            add_filter( 'request', array( &$this, 'activation_status_column_orderby'));
            add_filter( 'manage_users_columns', array( &$this, 'ebk_add_user_activation_column'));
            add_action( 'show_user_profile', array( &$this, 'ebk_add_custom_user_profile_fields' ));
            add_action( 'edit_user_profile', array( &$this, 'ebk_add_custom_user_profile_fields' ));
            add_action( 'personal_options_update', array( &$this, 'ebk_save_custom_user_profile_fields' ));
            add_action( 'edit_user_profile_update', array( &$this, 'ebk_save_custom_user_profile_fields' ));    
        }
    }


    function ebk_prevent_email_change(&$errors, $update, &$user ) {
        if ( $user->ID != $_POST['user_id'] )
            return false;

        $old = get_user_by('id', $user->ID);
        if( $user->user_email != $old->user_email) {
            if (ebk_check_roles($this->options['ebk_change_email'])) {
                $errors->add('email_change_restricted',__('You are not allowed to change your email address.','wasp'));
                return $errors;
            } 
        }
    }

    function ebk_add_custom_user_profile_fields( $user ) {
        $status = get_user_meta( $user->ID, 'ebk_email_activation_status',true);
        if (current_user_can('manage_options')) {
    ?>
        <h3><?php _e('User Activation','wasp'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="ebk_user_activated"><?php __('User Activation Status.','wasp'); ?>
                </label></th>
                <td>
                    <input type="checkbox" name="ebk_user_activated" value="1" <?php checked( $status, 1 ); ?> /><br />
                    <span class="description"><?php __('User Activated.','wasp'); ?></span>
                </td>
            </tr>
        </table>
    <?php }
    }

    function ebk_new_user_email_admin_notice() {
        if ( strpos( $_SERVER['PHP_SELF'], 'profile.php' ) && isset( $_GET['updated'] ) && $email = get_option( get_current_user_id() . '_new_email' ) )
            echo "<div class='update-nag'>" . sprintf( __( "Your email address has not been updated yet. Please check your inbox at %s for a confirmation email." ), $email['newemail'] ) . "</div>";
    }

    function ebk_save_custom_user_profile_fields( $user_id ) {
        if ( !current_user_can( 'edit_user', $user_id ) )
            return FALSE;
        if (isset($_POST['ebk_user_activated']) && is_numeric( $_POST['ebk_user_activated']))
            update_user_meta( $user_id, 'ebk_email_activation_status', $_POST['ebk_user_activated'] );
    }


    function ebk_add_user_activation_column($columns) {
        $columns['ebk_activation_status'] = __('Active','wasp');
        return $columns;
    }

    //make the new column sortable
    function user_sortable_columns( $columns ) {
        $columns['ebk_activation_status'] = 'ebk_email_activation_status';
        return $columns;
    }


    function ebk_show_user_activation_column($value, $column_name, $user_id) {
        $status = get_user_meta( $user_id, 'ebk_email_activation_status',true);
        if ( 'ebk_activation_status' == $column_name )
            return ($status == 1) ? "True": "False";
        return $value;
    }


    function activation_status_column_orderby( $vars ) {
        if ( isset( $vars['orderby'] ) && 'ebk_email_activation_status' == $vars['orderby'] ) {
                $vars = array_merge( $vars, array(
                        'meta_key' => 'ebk_email_activation_status',
                        'orderby' => 'meta_value'
                ) );
        }
        return $vars;
    }
}