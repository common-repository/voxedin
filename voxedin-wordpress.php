<?php
/*
Plugin Name: VoxedIn
Plugin URI: http://wordpress.org/extend/plugins/voxedin/
Description: Secure Login using Voice Biometrics for your WordPress site.
Author: Nick Wise
Version: 0.95
Author URI: http://www.voxedin.com/
Compatibility: WordPress 3.5
Text Domain: voxedin-wordpress
Domain Path: /lang

----------------------------------------------------------------------------

Copyright (c) 2013, Nick Wise  (email : nick.wise@outlook.com)
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, 
are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list 
of conditions and the following disclaimer.

Redistributions in binary form must reproduce the above copyright notice, this 
list of conditions and the following disclaimer in the documentation and/or 
other materials provided with the distribution.

Neither the names of VoxedIn nor the names of any contributors may 
be used to endorse or promote products derived from this software without 
specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND 
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED 
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE 
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE 
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL 
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR 
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER 
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, 
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE 
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

class VoxedInWordpress {

static $instance;

/**
 * Setup the static instance variable via which other plugins can interface,
 * and link the init action to wordpress. 
 */
function __construct() {
    self::$instance = $this;
    add_action( 'init', array( $this, 'init' ) );
}

/**
 * Set up the various action and filter hooks that we need, plus initialise
 * localisation. 
 */
function init() {
    // We hook login_form to add our hidden fields and iframe to it.
    add_action( 'login_form', array( $this, 'loginform' ) );
    // We hook authenticate in order to confirm the voice biometric succeeded
    // before letting the user log in. 
    add_filter( 'authenticate', array( $this, 'voxedin_login' ), 50, 3 );

    // If the plugin is being loaded because an ajax call has been received
    // by admin.php then we set up the handlers for each action.
    // Note we do "nopriv" actions in the login page and the usual
    // actions when in the profile page for enrollment.
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
      add_action( 'wp_ajax_getpayloadforuser', array( $this, 'ajax_getpayloadforuser' ) );
      add_action( 'wp_ajax_islogincomplete', array( $this, 'ajax_islogincomplete' ) );
      add_action( 'wp_ajax_loginresult', array( $this, 'ajax_loginresult' ) );
      add_action( 'wp_ajax_nopriv_getpayloadforuser', array( $this, 'ajax_getpayloadforuser' ) );
      add_action( 'wp_ajax_nopriv_islogincomplete', array( $this, 'ajax_islogincomplete' ) );
      add_action( 'wp_ajax_nopriv_loginresult', array( $this, 'ajax_loginresult' ) );
    }
    
    // We hook the user and admin options screens to add our own fields.
    add_action( 'personal_options_update', array( $this, 'personal_options_update' ) );
    add_action( 'profile_personal_options', array( $this, 'profile_personal_options' ) );
    add_action( 'edit_user_profile', array( $this, 'edit_user_profile' ) );
    add_action( 'edit_user_profile_update', array( $this, 'edit_user_profile_update' ) );
    if (is_admin()) {
      add_action( 'admin_menu', array( $this, 'voxedin_admin_options' ) );
      add_action( 'admin_init', array( $this, 'init_voxedin_admin_options' ) );
    }

    load_plugin_textdomain( 'voxedin-wordpress', false, basename( dirname( __FILE__ ) ) . '/lang' );
    
    add_action( 'login_enqueue_scripts', array( $this, 'voxedin_enqueue_scripts' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'voxedin_admin_enqueue_scripts' ) );
    
}

function voxedin_enqueue_scripts() {

    // Register our ajax javascript file
    wp_register_script( 'voxedin_script', plugins_url('voxedin.js', __FILE__), array('jquery') );
    // And set up variables using PHP here to pass into our ajax functions.
    wp_localize_script( 'voxedin_script', 'voxedInAjaxVars', array( 'ajaxurl' => admin_url( 'admin-ajax.php'),
                                                            'loginNonce' => wp_create_nonce('islogin_nonce'),
                                                            'payloadNonce' => wp_create_nonce('payload_nonce')));        

    // Specify that we're using jquery
    wp_enqueue_script( 'jquery' );
    // ..and our own script
    wp_enqueue_script( 'voxedin_script' );
}

function voxedin_admin_enqueue_scripts( $hook ) {

    if ( 'profile.php' == $hook ) {
        $this->voxedin_enqueue_scripts();
    }
}


/**
 * Add login_id hidden field to login form.
 * Add a label for error text and our iframe for posting to the voxedin sdk.
 */
function loginform() {
    echo "\n<input type='hidden' id='login_id' name='login_id' value='" . uniqid(mt_rand(), true) . "'/>\n";
    echo "<iframe id='voxedinsdk' height='1px' scrolling='no' frameBorder='0' src='" . plugins_url('voxedinsdkform.html', __FILE__) . "' ></iframe>\n";
}


/**
 * Check to see if the voice biometric verification succeeded before letting
 * the user proceed.  We never log the user in here, we only vito login if
 * voice login has failed.  
 */
function voxedin_login( $user, $username = '', $password = '' ) {

  $user_data = get_user_by('login', $username);
  
  if ( isset($user_data) && trim( get_user_option('voxedinwordpress_enabled', $user_data->ID) ) == 'enabled' &&
                            trim( get_user_option('voxedinwordpress_enrolled', $user_data->ID) ) == 'enrolled') {

    $login_id = trim( $_POST[ 'login_id' ] );
    $login_result = get_site_transient( $login_id );
  
    if ( isset($login_result) ) {
      delete_site_transient( $login_id );
    
      $parts = explode( ',', $login_result );
      
      if ( count($parts) == 5 ) {
        $status = $parts[1];
      
        if ($status == "Succeeded") {
          return $user;
        } else if ($status == "Failed" || $status == "Aborted" || $status == "Abandoned") {
          return new WP_Error( 'voxedin_login_failed', __( '<strong>ERROR</strong>: Your voice could not be verified.', 'voxedin-wordpress' ) );
        } else {
          return new WP_Error( 'voxedin_login_failed', __( '<strong>ERROR</strong>: Voice service unavailable.', 'voxedin-wordpress' ) );
        }
      }
    }

    return new WP_Error( 'voxedin_login_failed', __( '<strong>ERROR</strong>: Voice result unavailable.', 'voxedin-wordpress' ) );
  }
  
  return $user;
}


/**
 * Add our own settings to the user's profile page.
 */
function profile_personal_options($user) {
	global $is_profile_page;
  
  $viw_enabled = trim( get_user_option( 'voxedinwordpress_enabled', $user->ID ) );
  $viw_enrolled = trim( get_user_option( 'voxedinwordpress_enrolled', $user->ID ) );
	

	if ( $is_profile_page || IS_PROFILE_PAGE ) {
  	echo "<h3>".__( 'VoxedIn Settings', 'voxedin-wordpress' )."</h3>\n";
    
    _e( '<div style="max-width:500px"><p>Enabling the VoxedIn plugin means that you will need to perform a VoxedIn voice login as well as type your password when logging on to WordPress. You will not be able to use voice login until you have enrolled your voice using the button below.</p><p><span style="font-weight:bold">Your voice will be enrolled with the VoxedIn service</span>, which is separate to this WordPress site. No personal information will be sent to VoxedIn, however a unique identifier representing you will be. This identifier will be used to fetch an image of a QR code from VoxedIn that you will scan using the VoxedIn app in order to record samples of your voice to log in.</p></div>', 'voxedin-wordpress' );
  
  	echo "<table class=\"form-table\">\n";
  	echo "<tbody>\n";
  	echo "<tr>\n";
  	echo "<th scope=\"row\">".__( 'Enabled?', 'voxedin-wordpress' )."</th>\n";
  	echo "<td>\n";
  	echo "<input name=\"viw_enabled\" id=\"viw_enabled\" class=\"tog\" type=\"checkbox\"" . checked( $viw_enabled, 'enabled', false ) . "/><span id=\"viw_enabled_help\" style=\"display:none; color:red;\" class=\"description\">".__(' You must save your changes before you can enroll.','voxedin-wordpress')."</span>\n";
  	echo "</td>\n";
  	echo "</tr>\n";

    // Set a transient to re-use ajax methods for login for enrolment, but
    // know which is which
    $login_id = uniqid( mt_rand(), true );
    set_site_transient( $login_id . 'enr', 'true', 600 );

		echo "<tr>\n";
		echo "<th scope=\"row\">".__( 'Enrolled?', 'voxedin-wordpress' )."</th>\n";
		echo "<td>\n";
		echo "<input name=\"viw_enrolled\" id=\"viw_enrolled\" class=\"tog\" type=\"checkbox\" disabled=\"disabled\"" . checked( $viw_enrolled, 'enrolled', false ) . "/>";
    if ($viw_enrolled != 'enrolled') {
      echo "<span class=\"description\">".__(' You need to enroll before you can use voxedin. Press the enroll button and scan the code with the voxedin app.','voxedin-wordpress')."</span>";
    }
		echo "\n</td>\n";
		echo "</tr>\n";

    // this is the inverse of the disabled(..) behaviour...
    if ($viw_enabled == 'enabled') {
      $but_dis = '';
    } else {
      $but_dis = "disabled=\"disabled\"";
    }

		echo "<tr>\n";
    if ($viw_enrolled == 'enrolled') {
		  $enr_str = __( 'Re-enroll now', 'voxedin-wordpress' );
    } else {
      $enr_str = __( 'Enroll now', 'voxedin-wordpress' );
    }
	  echo "<th scope=\"row\">" . $enr_str . "</th>\n";

		echo "<td>\n";
		echo "<input name=\"viw_enrol\" id=\"viw_enrol\" value=\"". $enr_str ."\" " . $but_dis . " type=\"button\" class=\"button\" />";
		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "<th></th>\n";
		echo "<td>";
    echo "\n<input type='hidden' id='login_id' name='login_id' value='" . $login_id . "'/>\n";
    echo "<iframe id='voxedinsdk' height='1px' scrolling='no' frameBorder='0' src='" . plugins_url('voxedinsdkregisterform.html', __FILE__) . "' ></iframe>\n";
		echo "</td>\n";
		echo "</tr>\n";

	echo "</tbody></table>\n";
  echo "<script type=\"text/javascript\">\n";
  echo "var isVlwEnabled=('" . $viw_enabled . "' === 'enabled');\n";
 	echo <<<ENROLSCRIPT
    
function primeFormSubmission() {
    if (isVlwEnabled) {
      jQuery('#viw_enrol').removeAttr('disabled');
    }
    
    jQuery('#viw_enrol').bind('click', function() {
        if (jQuery('#viw_enrol').attr('disabled')) {
            return true;
        } else {
            jQuery('#viw_enrol').attr('disabled', 'disabled');
            jQuery('#viw_enrolled').prop('checked', false);

            // Translate the provided username into a payload and begin the voice login process
            getPayloadForUser(true);

            return false;
        }
    });
}
  
primeFormSubmission();

jQuery('#viw_enabled').bind('click', function() {
    if (jQuery('#viw_enabled').is(':checked')) {
      jQuery('#viw_enabled_help').show();
    } else {
      jQuery('#viw_enabled_help').hide();
    }
});
  
</script>
ENROLSCRIPT;
		
	}
}

/**
 * Handle the saving of the options added to the user's own profile page
 * in the previous function.  
 */
function personal_options_update($user_id) {

	$viw_enabled	= ! empty( $_POST['viw_enabled'] );
	
	if ( ! $viw_enabled ) {
		$viw_enabled = 'disabled';
	} else {
		$viw_enabled = 'enabled';
	}
	
	update_user_option( $user_id, 'voxedinwordpress_enabled', $viw_enabled );
}

/**
 * Add the ability to enable or disable VoxedIn to the user's profile page
 * for the situation when it's viewed by the administrator. 
 */
function edit_user_profile() {
	global $user_id;
  
  $viw_enabled = trim( get_user_option( 'voxedinwordpress_enabled', $user_id ) );
	
	echo "<h3>".__( 'VoxedIn Settings', 'voxedin-wordpress' )."</h3>\n";

	echo "<table class=\"form-table\">\n";
	echo "<tbody>\n";
	echo "<tr>\n";
	echo "<th scope=\"row\">".__( 'Enabled?', 'voxedin-wordpress' )."</th>\n";
	echo "<td>\n";
	echo "<input name=\"viw_enabled\" id=\"viw_enabled\" class=\"tog\" type=\"checkbox\"" . checked( $viw_enabled, 'enabled', false ) . "/><span id=\"viw_enabled_help\" style=\"display:none; color:red;\" class=\"description\">".__(' You must save your changes before you can enroll.','voxedin-wordpress')."</span>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</tbody>\n";
	echo "</table>\n";
}

/**
 * Handle the saving of the options added to the user's profile page by an admin
 * in the previous function.  
 */
function edit_user_profile_update() {
	global $user_id;
	
	$viw_enabled	= ! empty( $_POST['viw_enabled'] );
	
	if ( ! $viw_enabled ) {
		$viw_enabled = 'disabled';
	} else {
		$viw_enabled = 'enabled';
	}
	
	update_user_option( $user_id, 'voxedinwordpress_enabled', $viw_enabled );
}


function voxedin_admin_options() {
	add_options_page( 'VoxedIn Settings', 'VoxedIn Settings', 'manage_options', 'voxedin-wordpress', array($this, 'voxedin_options_form') );
}

function voxedin_options_form() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'voxedin-wordpress' ) );
	}
  
  echo "<div class=\"wrap\">\n";
  echo "<div id=\"icon-options-general\" class=\"icon32\"></div>\n";
  echo "<h2>".__( 'VoxedIn Settings', 'voxedin-wordpress' )."</h2>\n";
  
  echo "<form action=\"options.php\" method=\"post\">\n";
  echo settings_fields('voxedin_option_group');
  echo do_settings_sections('voxedin-wordpress');
 
  echo submit_button(__( 'Update Settings', 'voxedin-wordpress' ), 'primary', 'viw_submit');
  echo "</form></div>\n";
}

public function init_voxedin_admin_options() {
		
	register_setting('voxedin_option_group', 'voxedin_options', array($this, 'validate_options'));
		
  add_settings_section(
    'voxedin_section_id',
    __( 'Credentials', 'voxedin-wordpress' ),
    array($this, 'print_section_info'),
    'voxedin-wordpress'
	);	
		
	add_settings_field(
	    'access_key', 
	    __( 'Access Key', 'voxedin-wordpress' ), 
	    array($this, 'create_access_key_field'), 
	    'voxedin-wordpress',
	    'voxedin_section_id'			
	);		
		
	add_settings_field(
	    'secret_key', 
	    __( 'Secret Key', 'voxedin-wordpress' ), 
	    array($this, 'create_secret_key_field'), 
	    'voxedin-wordpress',
	    'voxedin_section_id'			
	);		
		
	add_settings_field(
	    'config_id', 
	    __( 'Configuration ID', 'voxedin-wordpress' ), 
	    array($this, 'create_config_id_field'), 
	    'voxedin-wordpress',
	    'voxedin_section_id'			
	);		
}
	
function print_section_info() {
  _e( '<p>This is where you input the access_key, secret_key and configuration_id that you obtained from the VoxedIn SDK when you registered. These credentials are used to secure the voice biometric login attempts made by your users to the VoxedIn SDK.</p>', 'voxedin-wordpress');
}
	
function create_access_key_field() {
  $options = get_option('voxedin_options');
  echo "<input id=\"access_key\" name=\"voxedin_options[access_key]\" size=\"100\" type=\"text\" value=\"{$options['access_key']}\" />";
}
	
function create_secret_key_field() {
  $options = get_option('voxedin_options');
  echo "<input id=\"secret_key\" name=\"voxedin_options[secret_key]\" size=\"100\" type=\"text\" value=\"{$options['secret_key']}\" />";
}
	
function create_config_id_field() {
  $options = get_option('voxedin_options');
  echo "<input id=\"config_id\" name=\"voxedin_options[config_id]\" size=\"100\" type=\"text\" value=\"{$options['config_id']}\" />";
}
	
function validate_options($input) {
  
  $newinput['access_key'] = trim($input['access_key']);
  
  if ( !preg_match('/^[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}$/i', $newinput['access_key']) ) {
    $newinput['access_key'] = '';
  }
  
  $newinput['config_id'] = trim($input['config_id']);
  
  if ( !preg_match('/^[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}$/i', $newinput['config_id']) ) {
    $newinput['config_id'] = '';
  }
  
  $newinput['secret_key'] = trim($input['secret_key']);
  
  if ( !preg_match('/^[A-Z0-9\+\/=]{64}$/i', $newinput['secret_key']) ) {
    $newinput['secret_key'] = '';
  }
  
  return $newinput;
}



function ajax_getpayloadforuser() {

  check_ajax_referer( 'payload_nonce', 'nonce' );
   
  $login_id = trim( $_POST[ 'login_id' ] );
  $username = trim( $_POST[ 'username' ] );
  
  $registration = get_site_transient( $login_id . 'enr' );

  $user_data = get_user_by('login', $username);
  
  if ( isset($user_data) && trim( get_user_option('voxedinwordpress_enabled', $user_data->ID) ) == 'enabled' &&
      (trim( get_user_option('voxedinwordpress_enrolled', $user_data->ID) ) == 'enrolled' || $registration === 'true')) {

    $options = get_option('voxedin_options');
    $access_key = $options['access_key']; 
    $secret_key = $options['secret_key'];
    $configuration_id = $options['config_id']; 
    $operation = (($registration === 'true') ? 'enrol' : 'verify');
    $return_path = admin_url( 'admin-ajax.php?nonce=' . wp_create_nonce('loginresult_nonce') . '&action=loginresult');
    
    $user_hash = wp_hash($username . $access_key);
    
    $plaintext = $configuration_id . ',' . $user_hash . ',' . $login_id . ',' . $return_path . ',' . $operation;
    
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND );
    
    $blocksize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);

    // PKCS7 Padding
    $pad = $blocksize - (strlen($plaintext) % $blocksize);
    $plaintext .= str_repeat(chr($pad), $pad);

    $ciphertext = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, md5($secret_key), $plaintext, MCRYPT_MODE_CBC , $iv );

    $payload = base64_encode($ciphertext);
    $base64iv = base64_encode($iv);
    
    // Put a fake payload in a transient in anticipation of the return, for islogincomplete to poll on
    set_site_transient( $login_id, $login_id, 300 );
    set_site_transient( $login_id . 'started', time(), 300 );

    if ($registration === 'true' && get_current_user_id( ) != 0) {
      update_user_option( get_current_user_id( ), 'voxedinwordpress_enrolled', 'notenrolled' );
    }
    
    $result = array( 'use_voxedin' => true, 'access_key' => $access_key, 
                     'iv' => $base64iv, 'payload' => $payload );
  	header( 'Content-Type: application/json' );
  	echo json_encode( $result );
  } else {
    $result = array( 'use_voxedin' => false );
  	header( 'Content-Type: application/json' );
  	echo json_encode( $result );
  }
  
  die();
}

function ajax_islogincomplete() {

  check_ajax_referer( 'islogin_nonce', 'nonce' );

  $login_id = trim( $_POST[ 'login_id' ] );
  $login_results = get_site_transient( $login_id );

 	header( 'Content-Type: application/json' );
  $result = array( 'poll' => true );

  if ( isset($login_results) ) {
    $parts = explode( ',', $login_results );
    if ( count($parts) > 1 ) {
      $status = $parts[1];
      
      if ($status == "Succeeded") {
        $result = array( 'error' => '' );
        $is_enrol = get_site_transient( $login_id . 'enr' );
        if ( isset($is_enrol) && $is_enrol === 'true' && get_current_user_id( ) != 0 ) {
          delete_site_transient( $login_id . 'enr' );
          update_user_option( get_current_user_id( ), 'voxedinwordpress_enrolled', 'enrolled' );
        }
      } else {
        $result = array( 'error' => 'voxedin ' . $status );
      }
    } else {
      $starttime = get_site_transient( $login_id . 'started' );
      if ( !isset($starttime) || (time() - $starttime) > 60 )
      {
        $result = array( 'error' => 'Session timed out' );
      }
    }
  } else {
    $result = array( 'error' => 'No such login session' );
  }
  
  echo json_encode( $result );

  die();
}

function ajax_loginresult() {

  check_ajax_referer( 'loginresult_nonce', 'nonce' );

  $options = get_option('voxedin_options');
  $secret_key = $options['secret_key'];

  $ciphertext = base64_decode( trim( $_POST[ 'payload' ] ) );
  $iv         = base64_decode( trim( $_POST[ 'iv' ] ) );
  
  $plaintext = mcrypt_decrypt( MCRYPT_RIJNDAEL_256, md5($secret_key), $ciphertext, MCRYPT_MODE_CBC , $iv );
  // remove pkcs7 padding
  $dec_s = strlen($plaintext); 
  $padding = ord($plaintext[$dec_s-1]); 
  $plaintext = substr($plaintext, 0, -$padding); 
    
  $parts = explode(',', $plaintext);
  if ( count($parts) == 5 ) {
    set_site_transient( $parts[0], $plaintext, 300 );
  }
  
  die();
}

} // end class

$voxedin_wordpress = new VoxedInWordpress;

