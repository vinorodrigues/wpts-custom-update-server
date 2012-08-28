<?php
/**
 * Options page
 *
 * @author Vino Rodrigues
 * @package TS-Automatic-Theme-Plugin-Update
 * @since TS-Automatic-Theme-Plugin-Update 0.1
 *
 * Code based on http://www.presscoders.com/2010/05/wordpress-settings-api-explained/
 */


// Small fix to work arround windows and virtual paths while in dev env.
if ( defined('WP_DEBUG') && WP_DEBUG )
	define( 'ATPU_PLUGIN_SLUG', basename(dirname(__FILE__)) . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.php' );
if ( ! defined('ATPU_PLUGIN_SLUG') )
	define( 'ATPU_PLUGIN_SLUG', plugin_basename(__FILE__) );


/**
 * Check if Settings API supported
 */
function ts_atpu_requires_wordpress_version() {
	global $wp_version;
	$plugin = ATPU_PLUGIN_SLUG;
	$plugin_data = get_plugin_data( __FILE__, false );

	if ( version_compare($wp_version, "2.7", "<" ) ) {
		if( is_plugin_active($plugin) ) {
			deactivate_plugins( $plugin );
			wp_die( "'".$plugin_data['Name']."' requires WordPress 2.7 or higher, and has been deactivated!" );
		}
	}
}
add_action( 'admin_init', 'ts_atpu_requires_wordpress_version' );


/**
 * Delete options table entries ONLY when plugin deactivated AND deleted
 */
function ts_atpu_atpu_uninstall_hook() {
	delete_option('ts_atpu_options');
}
register_uninstall_hook( ATPU_PLUGIN_SLUG, 'ts_atpu_register_uninstall_hook' );


/**
 * Define default option settings
 */
/* function ts_atpu_register_activation_hook() {
  // DO NOTHING
}
register_activation_hook( ATPU_PLUGIN_SLUG, 'ts_atpu_register_activation_hook' ); */


/**
 * Register settings page
 */
function ts_atpu_admin_init() {
	register_setting('ts_atpu_plugin_options', 'ts_atpu_options', 'ts_atpu_options_validate');

	add_settings_section('main', __('Main'), '__return_false', 'ts_atpu_options');

	add_settings_field( 'url', __('URL to update API'), 'ts_atpu_option_field_url', 'ts_atpu_options', 'main' );
}
add_action( 'admin_init', 'ts_atpu_admin_init' );


/**
 * Get options or defaults
 */
function ts_atpu_get_options() {
	$saved = (array) get_option( 'ts_atpu_options' );
	$defaults = array( 'url' => 'http://localhost/api' );  // fallback

	$options = wp_parse_args( $saved, $defaults );
	// $options = array_intersect_key( $options, $defaults );

	return $options;
}

function ts_atpu_option_field_url() {
	$options = ts_atpu_get_options();
	$name = 'url';
	?>
	<label for="<?php echo $name; ?>">
		<input type="url" name="ts_atpu_options[<?php echo $name; ?>]" id="<?php echo $name; ?>" value="<?php echo $options['url'] ?>" size="50" placeholder="http://..." />
	</label>
	<?php
}


/**
 * Render options page
 */
function ts_atpu_admin_options_page() {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
?>
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('TS Automatic Theme & Plugin Update Options'); ?></h2>
	<p></p>
	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php
			settings_fields( 'ts_atpu_plugin_options' );
			do_settings_sections( 'ts_atpu_options' );
			submit_button();
		?>
	</form>
</div>
<?php
}


if ( ! function_exists('is_valid_url') ) :
/**
 * Validate URL
 *
 * @param string $url
 * @return boolean type
 */
function is_valid_url( $url ) {
    $rx = "/^(http:\/\/|https:\/\/)?[a-z0-9_\-]+[a-z0-9_\-\.]+\.[a-z]{2,4}(\/+[a-z0-9_\.\-\/]*)?$/i";
    return (boolean) preg_match($rx, $url);
}
endif;


/**
 * Options validation
 */
function ts_atpu_options_validate( $input ) {
	$output = array();

	if ( isset( $input['url'] ) ) {
		if (is_valid_url($input['url']))
			$output['url'] = $input['url'];
		else
			add_settings_error ('URL', 'url', __('Invalid URL'));
	}
	return apply_filters( 'ts_atpu_options_validate', $output, $input );
}


/**
 * Add settings link to settings tab
 */
function ts_atpu_admin_menu() {
	add_plugins_page(
		__('TS Automatic Theme & Plugin Update Options'),
		__('TS Updates'),
		'manage_options',
		'ts-atpu',
		'ts_atpu_admin_options_page' );
}
add_action( 'admin_menu', 'ts_atpu_admin_menu' );


/**
 * Load options
 */
function ts_atpu_load_options() {
	global $_ts_atpu_urls;

	$options = ts_atpu_get_options();

	if (!empty($options['url'])) {
		if (!isset($_ts_atpu_urls) || !is_array($_ts_atpu_urls))
			$_ts_atpu_urls = array();
		$_ts_atpu_urls[] = $options['url'];
	}
}
add_action( 'admin_init', 'ts_atpu_load_options');


/* eof */
