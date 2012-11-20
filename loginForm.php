<?php
/*
Plugin Name: Front End Login Form
Plugin URI: http://fasamfast.com/
Description: A custom wordpress plugin for displaying login form thrugh shortcode. Just use <strong>[login_form]</strong> in your post to get the login form.
Version: 0.2
Author: Jigs Patel
Author URI: http://fasamfast.com/
License: GPLv2 or later
*/


  
  
add_action( 'wp_enqueue_scripts', 'addLoginStyle' ); 
function addLoginStyle() {
    wp_register_style( 'lform-style', plugins_url('style.css', __FILE__) );
    wp_enqueue_style( 'lform-style' );
}


	
  
function getLostLink($c){
	$params = array( 'action' => "lostpassword" );
	$url = add_query_arg( $params, get_permalink() );
	return $url;
}
add_filter('lostpassword_url','getLostLink');
 
 

function logout_redirect($c)
{
	$params = array( 'redirect_to' => get_permalink() );
	$url = add_query_arg( $params ,$c);
    return $url;
}
add_filter('logout_url','logout_redirect');

 


function retrieve_my_password() {
	global $wpdb, $current_site;

	$errors = new WP_Error();

	if ( empty( $_POST['user_login'] ) ) {
		$errors->add('empty_username', __('<strong>ERROR</strong>: Enter a username or e-mail address.'));
	} else if ( strpos( $_POST['user_login'], '@' ) ) {
		$user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );
		if ( empty( $user_data ) )
			$errors->add('invalid_email', __('<strong>ERROR</strong>: There is no user registered with that email address.'));
	} else {
		$login = trim($_POST['user_login']);
		$user_data = get_user_by('login', $login);
	}

	do_action('lostpassword_post');

	if ( $errors->get_error_code() )
		return $errors;

	if ( !$user_data ) {
		$errors->add('invalidcombo', __('<strong>ERROR</strong>: Invalid username or e-mail.'));
		return $errors;
	}

	// redefining user_login ensures we return the right case in the email
	$user_login = $user_data->user_login;
	$user_email = $user_data->user_email;
	
	do_action('retreive_password', $user_login);  // Misspelled and deprecated
	do_action('retrieve_password', $user_login);

	$allow = apply_filters('allow_password_reset', true, $user_data->ID);

	if ( ! $allow )
		return new WP_Error('no_password_reset', __('Password reset is not allowed for this user'));
	else if ( is_wp_error($allow) )
		return $allow;

	$key = $wpdb->get_var($wpdb->prepare("SELECT user_activation_key FROM $wpdb->users WHERE user_login = %s", $user_login));
	if ( empty($key) ) {
		// Generate something random for a key...
		$key = wp_generate_password(20, false);
		do_action('retrieve_password_key', $user_login, $key);
		// Now insert the new md5 key into the db
		$wpdb->update($wpdb->users, array('user_activation_key' => $key), array('user_login' => $user_login));
	}
	$message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
	$message .= network_site_url() . "\r\n\r\n";
	$message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
	$message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
	$message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
	$message .= '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . ">\r\n";

	if ( is_multisite() )
		$blogname = $GLOBALS['current_site']->site_name;
	else
		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

	$title = sprintf( __('[%s] Password Reset'), $blogname );

	$title = apply_filters('retrieve_password_title', $title);
	$message = apply_filters('retrieve_password_message', $message, $key);

	if ( $message && !wp_mail($user_email, $title, $message) ){
	
		$errors->add('invalidcombo', __('<strong>ERROR</strong>: The e-mail could not be sent. <br /> Possible reason: your host may have disabled the mail() function...'));
		return $errors;
		}
	return true;
}



 
  function displayForm($atts) {
	
	if(isset($_POST['submit'])){
		if(isset($_POST['username']) && isset($_POST['password'])){
			$creds = array();
			$creds['user_login'] = $_POST['username'];
			$creds['user_password'] = $_POST['password'];
			$creds['remember'] = true;
			
			$user = wp_signon( $creds, false );
			if ( is_wp_error($user) )
			   echo '<div class="jerror">'.$user->get_error_message().'</div>';
			else
				wp_redirect(get_permalink());
		}
		else{
			echo '<div class="jerror">Enter Username and password.</div>';
		}
	}
	//Password retrive section
	if(isset($_POST['user_login'])){
	$result = retrieve_my_password();
		if ( is_wp_error($result) )
			echo '<div class="jerror">'.$result->get_error_message().'</div>';
		}
	
	if(isset($_GET['action']) && !is_user_logged_in()){
	

	?>
	<section id="contentForm">
		<!--form name="lostpasswordform" id="lostpasswordform" action="<?php echo home_url('/'); ?>/wp-login.php?action=lostpassword" method="post"-->
		<form name="lostpasswordform" id="lostpasswordform" action="" method="post">
			<h1>Reset</h1>
	<div>
		<input type="text" name="user_login" id="user_login" placeholder="Username or E-mail" value="" required="" size="20" tabindex="10"></label>
	</div>
	<div>
	<input type="hidden" name="redirect_to" value="<?php echo get_permalink(); ?>">
	<input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="Get Password" tabindex="100">
					<a href="<?php echo get_permalink();  ?>">Login Now</a>
	</div>
</form>
</section><!-- content -->
	<?
	}
	else if(!is_user_logged_in()){
    ?>
	
	<section id="contentForm">
		<form action="" method="post">
			<h1>Login Form</h1>
			<div>
				<input type="text" placeholder="Username" required="" id="username" name="username" />
			</div>
			<div>
				<input type="password" placeholder="Password" required="" id="password" name="password" />
			</div>
			<div>
				<input type="submit" value="Log in" name="submit" />
				<a href="<?php echo wp_lostpassword_url(); ?>">Lost your password?</a>
				
				
			</div>
		</form><!-- form -->
		
	</section><!-- content -->
	
	<?php
	}
	else if(is_user_logged_in()){
		 $current_user=wp_get_current_user();
		 
	echo '<section id="contentForm">';
	echo '<form method="post"><h1>Profile</h1>';
	echo '<div>' .get_avatar( $current_user->user_email , 200 );'</div> <br />';
    echo '<div>Username: ' . $current_user->user_login . '</div><br />';
    echo '<div>User email: ' . $current_user->user_email . '</div><br />';
    echo '<div>User first name: ' . $current_user->user_firstname . '</div><br />';
    echo '<div>User last name: ' . $current_user->user_lastname . '</div><br />';
    echo '<div>User display name: ' . $current_user->display_name . '</div><br />';
	echo '<div><a href="'.wp_logout_url().'" title="Logout">Logout</a><br /></div></form>';
	echo '</section>';
	}
}
add_shortcode('login_form', 'displayForm');


?>

