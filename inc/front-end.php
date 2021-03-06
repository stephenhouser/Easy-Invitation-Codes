<?php
defined('ABSPATH') or die('Cheatin&#8217; uh?');

add_action('init', 'baweic_l10n');
function baweic_l10n() {
	load_plugin_textdomain('baweic', 'false', dirname(plugin_basename(BAWEIC__FILE__)) . '/lang/');
}

add_action('register_form', 'baweic_register_form_add_field');
function baweic_register_form_add_field() {
	global $allowedposttags;
	$baweic_fields = get_option('baweic_fields');

	?>
		<label>
			<?php _e('Invitation Code', 'baweic'); ?> (<?php _e('required)', 'buddypress'); ?>
		</label>
		<?php do_action('bp_invitation_code_errors'); ?>
		<input name="invitation_code" tabindex="0" type="text" 
				class="input" id="invitation_code" 
				style="text-transform: uppercase" />
		<?php if (!empty($baweic_fields['link']) && $baweic_fields['link'] == 'on') { ?>
			<p id="baweic_fields_text_link" class="field-visibility-settings-notoggle">
				<?php echo ! empty($baweic_fields['text_link']) ? wp_kses_post($baweic_fields['text_link'], $allowedposttags) : ''; ?>
			</p>
		<?php } ?>
 	<?php
}

add_filter('registration_errors', 'baweic_registration_errors', 20, 3);
function baweic_registration_errors($errors, $sanitized_user_login, $user_email) {
	if (count($errors->errors)) {
		return $errors;
	}

	$baweic_options = get_option('baweic_options');
	$invitation_code = isset($_POST['invitation_code']) ? strtoupper($_POST['invitation_code']) : '';

	if (!array_key_exists($invitation_code, $baweic_options['codes'])) {
		add_action('login_head', 'wp_shake_js', 12);
		return new WP_Error('authentication_failed', __('<strong>ERROR</strong>: Wrong Invitation Code.', 'baweic'));
	} elseif (isset($baweic_options['codes'][ $invitation_code ]) && ! $baweic_options['codes'][ $invitation_code ]['leftcount']){
		add_action('login_head', 'wp_shake_js', 12);
		return new WP_Error('authentication_failed', __('<strong>ERROR</strong>: This Invitation Code is over.', 'baweic'));
	} else {
		$baweic_options['codes'][ $invitation_code ]['leftcount']--;
		$baweic_options['codes'][ $invitation_code ]['users'][] = $sanitized_user_login;
		update_option('baweic_options', $baweic_options);
	}

	return $errors;
}

add_action('login_footer', 'baweic_login_footer');
function baweic_login_footer() {
	$baweic_options = get_option('baweic_options');

	$invitation_code = isset($_POST['invitation_code']) ? strtoupper($_POST['invitation_code']) : '';
	if ($invitation_code && !array_key_exists($invitation_code, $baweic_options['codes'])):
		?>
		<script type="text/javascript">
			try{document.getElementById('invitation_code').focus();}catch(e){}
		</script>
		<?php
	endif;
}

/* BuddyPress */

/* Add invitation code to registration screen */
add_action('bp_after_account_details_fields', 'registration_add_code_invite', 20);
function registration_add_code_invite() {
	?>
		<div class="register-section" id="profile-details-section">
			<h2><?php _e( 'Invitation Code', 'buddypress' ); ?></h2>
			<?php do_action('register_form'); ?>
		</div>
	<?php
}

/* Validate invitation code during registration validation */
add_action('bp_signup_validate', 'registration_validate');
function registration_validate() {
	$bp = buddypress();
	$baweic_options = get_option('baweic_options');
	$invitation_code = isset($_POST['invitation_code']) ? strtoupper($_POST['invitation_code']) : '';

	if (!array_key_exists($invitation_code, $baweic_options['codes'])) {
		add_action('login_head', 'wp_shake_js', 12);
		$bp->signup->errors['invitation_code'] = __('Invalid invitation code.', 'baweic');
	} elseif (isset($baweic_options['codes'][ $invitation_code ]) && ! $baweic_options['codes'][ $invitation_code ]['leftcount']){
		add_action('login_head', 'wp_shake_js', 12);
		$bp->signup->errors['invitation_code'] = __('Invitation code expired.', 'baweic');
	}
}

/* Save invitation code in bbpress metadata.
 * (pending account activation?) 
 */
add_filter('bp_signup_usermeta', 'invite_code_update');
function invite_code_update($usermeta = array()) {
	if (isset($_POST['invitation_code'])) {
		$invitation_code = strtoupper($_POST['invitation_code']);
		$usermeta['invitation_code'] = $invitation_code;
	}

	return $usermeta;
}

/* (Upon activation?) 
 * Save invitation code in user's metadata and deduct from use count 
 */
add_action( 'bp_core_signup_user', 'invite_code_sync', 10, 5);
function invite_code_sync($user_id, $user_login, $user_password, $user_email, $usermeta) {
	if (isset($_POST['invitation_code'])) {
		$baweic_options = get_option('baweic_options');
		$invitation_code = strtoupper($_POST['invitation_code']);
		bp_update_user_meta( $user_id, 'invitation_code',  $invitation_code  );	

		$baweic_options['codes'][ $invitation_code ]['leftcount']--;
		$baweic_options['codes'][ $invitation_code ]['users'][] = $sanitized_user_login;
		update_option('baweic_options', $baweic_options);
	}
}
?>