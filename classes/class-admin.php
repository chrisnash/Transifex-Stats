<?php

/**
 * Codepress Transifex Admin
 *
 * @since 0.1
 */
class Codepress_Transifex_Admin {

	/**
	 * Notices
	 *
	 * @since 0.1
	 */
	private $notices = array();

	/**
	 * Constructor
	 *
	 * @since 0.1
	 */
	function __construct() {

		// Admin UI
		add_action( 'admin_menu', array( $this, 'settings_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links',  array( $this, 'add_settings_link' ), 1, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Admin Menu.
	 *
	 * Create the admin menu link for the settings page.
	 *
	 * @since 0.1
	 */
	public function settings_menu() {

		$page = add_options_page( __( 'Transifex Stats', 'transifex-stats' ), __( 'Transifex Stats', 'transifex-stats' ), 'manage_options', CPTI_SLUG, array( $this, 'plugin_settings_page') );
		add_action( "admin_print_styles-{$page}", array( $this, 'admin_styles') );

		// verify credentials
		add_action( "load-{$page}", array( $this, 'verify_credentials' ) );
	}

	/**
	 * Verify Credentials on storing credentials
	 *
	 * @since 0.1
	 */
	function verify_credentials() {

		if ( false !== get_option('cptw_options') && isset( $_REQUEST['settings-updated'] ) && 'true' == $_REQUEST['settings-updated'] ) {

			$api = new Codepress_Transifex_API();
			$result = $api->verify_credentials();

			if ( ! $result ) {
				$this->notices[] = (object) array(
					'message' 	=> __( 'Your transifex credentials are incorrect.', 'transifex-stats' ),
					'class'		=> 'error'
				);
			}
		}
	}

	/**
	 * Register admin css
	 *
	 * @since 0.1
	 */
	public function admin_styles() {

		wp_enqueue_style( 'cpti-admin', CPTI_URL.'/assets/css/admin_settings.css', array(), CPTI_VERSION, 'all' );
	}

	/**
	 * Add Settings link to plugin page
	 *
	 * @since 0.1
	 */
	public function add_settings_link( $links, $file ) {

		if ( $file != CPTI_SLUG . '/' . CPTI_SLUG . '.php' ) {
			return $links;
		}

		array_unshift( $links, '<a href="' .  admin_url("admin.php") . '?page=' . CPTI_SLUG . '">' . __( 'Settings', 'transifex-stats' ) . '</a>' );

		return $links;
	}

	public function sanitize_text( $options, $key ) {
		$value = $options[$key];
		$value = sanitize_text_field( $value );
		$value = trim( $value );
		return $value;
	}
	
	public function sanitize_password( $options, $key ) {
		// TODO: this was how the original plugin sanitized a password, but it
		// doesn't behave correctly if your password contains <, for instance.
		return $this->sanitize_text( $options, $key );
	}
	
	public function sanitize_boolean( $options, $key ) {
		return isset( $options[$key] );
	}
	
	/**
	 * Sanitize options
	 *
	 * @since 0.1
	 */
	public function sanitize_options( $options ) {
		$new_options = array();
		foreach( self::$settings_table as $key =>$data ) {
			$sanitize_func = $data['sanitizer'];
			$new_options[ $key ] = $this->$sanitize_func( $options, $key );
		}
		return $new_options;
	}

	/**
	 * Register plugin options
	 *
	 * @since 0.1
	 */
	public function register_settings() {
		$settings = get_option( 'cpti_options' );
		$this->enforce_defaults( $settings );
		register_setting( 'cpti-settings-group', 'cpti_options', array( $this, 'sanitize_options' ) );
	}

	public function enforce_defaults( &$settings ) {
		$changed = false;
		if ( $settings === false ) {
			$settings = array();
		}
		$defaults = $this->get_default_values();
		foreach( $defaults as $key=>$value ) {
			if ( !isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $value;
				$changed = true;
			}
		}
		if ( $changed ) {
			add_option( 'cpti_options', $settings );
		}
	}

	/**
	 * New settings handler implementation for WPT version of the plugin.
	 */
	private static $settings_table = array(
			'username'	=>	array( 'default'=>'', 'html_type'=>'text', 'html_class'=>'regular-text code', 'sanitizer'=>'sanitize_text' ),
			'password'	=>	array( 'default'=>'', 'html_type'=>'password', 'html_class'=>'regular-text code', 'sanitizer'=>'sanitize_password' ),
			'showlang'	=>	array( 'default'=>true, 'html_type'=>'checkbox', 'html_class'=>'', 'sanitizer'=>'sanitize_boolean' ),
			'showcode'	=>	array( 'default'=>false, 'html_type'=>'checkbox', 'html_class'=>'', 'sanitizer'=>'sanitize_boolean' ),
			'shownative'	=>	array( 'default'=>false, 'html_type'=>'checkbox', 'html_class'=>'', 'sanitizer'=>'sanitize_boolean' ),
			);
	  
	/**
	 * HTML input text element driver.
	 */
	private function input_text( $key, $value, $clazz ) {
		echo "<input type='text' class='$clazz' id='$key' name='cpti_options[$key]' value='" . esc_attr( $value ) . "'>";
	}
	
	/**
	 * HTML input password element driver.
	 */
	private function input_password( $key, $value, $clazz ) {
		echo "<input type='password' class='$clazz' id='$key' name='cpti_options[$key]' value='" . esc_attr( $value ) . "'>";
	}
	
	/**
	 * HTML input checkbox element driver.
	 */
	private function input_checkbox( $key, $value, $clazz ) {
		echo "<input type='checkbox' class='$clazz' id='$key' name='cpti_options[$key]' value='true' " . ( $value ? 'checked' : '') . ">";
	}

	/**
  	 * HTML field generator.
	 */
	private function field_generate( $options, $key, $text ) {
		$setting = self::$settings_table[$key];
		$type = $setting['html_type'];
		$clazz = $setting['html_class'];
		$value = $options[$key];
		$driver = 'input_' . $type;
		?>
			<tr valign="top">
				<th scope="row">
					<label for="<?php echo $key; ?>"><?php echo $text; ?></label>
				</th>
				<td>
					<label for="<?php echo $key; ?>"><?php $this->$driver( $key, $value, $clazz ); ?></label>
				</td>
			</tr>
		<?php
	}
		
	/**
	 * Returns the default plugin options.
	 *
	 * @since 0.1
	 */
	public function get_default_values() {
		$defaults = array();
		foreach ( self::$settings_table as $key=>$data ) {
			$defaults[ $key ] = $data['default'];
		}
		return apply_filters( 'cpti-defaults', $defaults );
	}

	/**
	 * Admin Notices
	 *
	 * @since 0.1
	 */
	function admin_notices() {

		if ( ! $this->notices ) {
			return;
		}

		foreach ( $this->notices as $notice ) { ?>
		    <div class="<?php echo $notice->class; ?>">
		        <p><?php echo $notice->message; ?></p>
		    </div>
		    <?php
		}
	}

	/**
	 * Settings Page Template.
	 *
	 * This function in conjunction with others usei the WordPress
	 * Settings API to create a settings page where users can adjust
	 * the behaviour of this plugin.
	 *
	 * @since 0.1
	 */
	public function plugin_settings_page() {

		$options = get_option( 'cpti_options' );
		$this->enforce_defaults( $options );
		echo '<!--'; var_export($options); echo '-->';
	?>
	<div id="cpti" class="wrap">
		<?php screen_icon( CPTI_SLUG ); ?>
		<h2><?php _e('Transifex Stats Settings', 'transifex-stats'); ?></h2>

		<form method="post" action="options.php">

			<?php settings_fields( 'cpti-settings-group' ); ?>

			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row" colspan="2">
							<p><?php _e( 'Fill in your Transifex credentials below to make a connection with the Transifex API.', 'transifex-stats' ); ?></p>
							<p><?php _e( 'Your credentials will remain private and will only be used to connect with Transifex API.', 'transifex-stats' ); ?></p>
						</th>
					</tr>
					<?php $this->field_generate( $options, 'username', __( 'Username', 'transifex-stats' ) ); ?>
					<?php $this->field_generate( $options, 'password', __( 'Password', 'transifex-stats' ) ); ?>
					<?php $this->field_generate( $options, 'showlang', __( 'Show language name (English)?', 'transifex-stats' ) ); ?>
					<?php $this->field_generate( $options, 'showcode', __( 'Show language code?', 'transifex-stats' ) ); ?>
					<?php $this->field_generate( $options, 'shownative', __( 'Show language name (native)?', 'transifex-stats' ) ); ?>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" />
			</p>
		</form>
		<p>
			<?php printf( __('This plugin is made by %s', 'transifex-stats' ), '<a target="_blank" href="http://www.codepresshq.com">Codepresshq.com</a>' ); ?>
		</p>
	</div>
	<?php
	}
}

new Codepress_Transifex_Admin();
