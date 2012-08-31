<?php
/**
 * Plugin Name: TS Automatic Theme & Plugin Update
 * Plugin URI: http://tecsmith.com.au
 * Description: Custom updates plugin
 * Author: Vino Rodrigues
 * Version: 0.9.3
 * Author URI: http://vinorodrigues.com
 *
 * Based on: http://konstruktors.com/blog/wordpress/2538-automatic-updates-for-plugins-and-themes-hosted-outside-wordpress-extend/
 *
 * @author Vino Rodrigues
 * @package TS-Automatic-Theme-Plugin-Update
 * @since TS-Automatic-Theme-Plugin-Update 0.9.0
**/


/* Helpers */
include_once( 'aide.php' );

/* Options page */
include_once( 'atpu-opt.php' );


/**
 * In debug mode, check each time
 */
if ( defined('WP_DEBUG') && WP_DEBUG ) :
	set_site_transient('update_plugins', null);
	set_site_transient('update_themes', null);
endif;


// Uncomment code below to debug plugin_api_result
/* if ( defined('WP_DEBUG') && WP_DEBUG ) :
function ts_atpu_plugins_api_result($result, $action, $args) {
	echo "<pre><b>plugins_api_result</b>";
	echo "\n<u>\$result=</u>"; print_r($result);
	echo "\n<u>\$action=</u>"; print_r($action);
	echo "\n<u>\$args=</u>"; print_r($args);
	echo '</pre>';
	return $result;
}
add_filter( 'plugins_api_result', 'ts_atpu_plugins_api_result', 10, 3 );
endif;  /* */


// Uncomment code below to debug http_response  (will be a lot - all api calls included)
/* if ( defined('WP_DEBUG') && WP_DEBUG ) :
function ts_atpu_http_response( $response, $args, $url ) {
	if ($url != 'http://localhost/api') return $response;
	echo "<pre><b>http_response</b>";
	echo "\n<u>\$args=</u>"; print_r($args);
	echo "\n<u>\$url=</u>"; print_r($url);
	echo "\n<u>\$response=</u>"; print_r($response);
	echo '</pre>';
	return $response;
}
add_filter( 'http_response', 'ts_atpu_http_response', 10, 3);
endif;  /* */


/**
 * Internal http post preparation
 */
function _ts_atpu_prepare_request($action, $data) {
	global $wp_version;

	$send_data = json_encode( array(
		'action' => $action,
		'data' => $data,
		'api-key' => md5(get_bloginfo('url')),
	) );

	return array(
		'headers' => array(
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
			'Referer' => get_bloginfo('url'),
		),
		'body' => $send_data,
		'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo('url') ),
	);
}


/**
 * Internal error handling
 */
function ts_atpu_admin_notices() {
	global $_ts_atpu_errors;
	if ( !isset($_ts_atpu_errors) || !is_array($_ts_atpu_errors) )
		return;

	foreach ($_ts_atpu_errors as $error) :
		if ( is_wp_error($error) ) :
			echo '<div id="message" class="error">';
			echo '<b>' . $error->get_error_code() . '</b>: ' . $error->get_error_message();
			if ($error->get_error_data()) {
				echo ', data = ';
				print_r($error->get_error_data());
			}
			echo "</div>";
		endif;
	endforeach;
}
add_action('admin_notices', 'ts_atpu_admin_notices');


/**
 * Internal error handling
 */
function _ts_atpu_push_error( $error ) {
	global $_ts_atpu_errors;
	if ( !is_a($error, 'WP_Error') ) return;
	if ( !isset($_ts_atpu_errors) || !is_array($_ts_atpu_errors) )
		$_ts_atpu_errors = array();
	$_ts_atpu_errors[] = $error;
}


/**
 * Internal error handling
 */
function _ts_atpu_error( $message, $data=null, $file='', $function='', $line='' ) {
	if (empty($file)) $file = __FILE__;
	if (!empty($file)) $message .= ' in <b>' . $file . '</b>';
	if (!empty($line)) $message .= ' on line <i>' . $line . '</i>';
	if (!empty($function)) $message .= ' <i>[function ' . $function . ']</i>';
	$error = new WP_Error( 
		pathinfo($file, PATHINFO_FILENAME),
		$message,
		$data );
	_ts_atpu_push_error($error);
	return $error;
}


/**
 * Send and process Check requests
 */
function _ts_atpu_do_check( $transient, $url, $what='plugins' ) {
	if (empty($transient->checked))
		return $transient;

	if ( !function_exists('curl_init') )
		return _ts_atpu_error(__('cURL not installed'), null, __FILE__, __FUNCTION__, __LINE__-1);

	if (!isset($transient->checked)) return $transient;

	// use the 'ts_atpu_check_list' filter to add or remove items from the list
	$checklist = apply_filters( 'ts_atpu_check_list', $transient->checked, $what );
	$checklist = array( $what => $checklist );

	$request = _ts_atpu_prepare_request( 'check', $checklist );
	$response = wp_remote_post($url, $request);

	if (is_wp_error($response)) {
		_ts_atpu_push_error($response);
		return $response;
	}

	if ($response['response']['code'] != 200)
		return _ts_atpu_error(
			__('Something went wrong!'),
			$response['response']['code'],
			__FILE__,
			__FUNCTION__,
			__LINE__-6 );

	$response = json_decode($response['body']);

	if (is_null($response)) :
		_ts_atpu_error(
			 __('Response not valid JSON'),
			null,
			__FILE__,
			__FUNCTION__,
			__LINE__-6 );
	elseif (isset($response->error)) :
		_ts_atpu_error(
			 __('Response error'),
			$response->error . ' : ' . $response->reason,
			__FILE__,
			__FUNCTION__,
			__LINE__-6 );
	elseif (isset($response->new)) :
		$response = object2array( $response->new );
		// work arround inconsistent WP response
		if ($what == 'plugins') :
			foreach ($response as $slug => $data)
				$transient->response[$slug] = (object) $data;
		elseif ($what == 'themes') :
			foreach ($response as $slug => $data)
				$transient->response[$slug] = $data;
		endif;
	endif;

	return $transient;
}

/**
 * Update check for Plugins
 */
function ts_atpu_psstup( $transient ) {
	global $_ts_atpu_urls;

	if (!isset($transient->checked)) return $transient;

	if (isset($_ts_atpu_urls) && is_array($_ts_atpu_urls))
		foreach ($_ts_atpu_urls as $url) :
			$res = _ts_atpu_do_check( $transient, $url );
			if (!is_wp_error($res))
				$transient = $res;
		endforeach;

	return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'ts_atpu_psstup');


/**
 * Update check for Themes
 */
function ts_atpu_psstut( $transient ) {
	global $_ts_atpu_urls;

	if (!isset($transient->checked)) return $transient;

	if (isset($_ts_atpu_urls) && is_array($_ts_atpu_urls))
		foreach ($_ts_atpu_urls as $url) :
			$res = _ts_atpu_do_check( $transient, $url, 'themes' );
			if (!is_wp_error($res))
				$transient = $res;
		endforeach;

	return $transient;
}
add_filter('pre_set_site_transient_update_themes', 'ts_atpu_psstut');


/**
 * Plugin info screen
 *
 * @return false or StdClass containing info
 */
function ts_atpu_plugins_api($data, $action, $args) {
	global $_ts_atpu_urls;

	// not plugin information, get out
	if ($action != 'plugin_information') return false;

	if ( ! isset( $args->slug ) ) return false;

	// no cURL, get out
	if ( !function_exists('curl_init') )
		return _ts_atpu_error(__('cURL not installed'), null, __FILE__, __FUNCTION__, __LINE__-1);

	if (!isset($_ts_atpu_urls) || !is_array($_ts_atpu_urls))
		return false;

	foreach ($_ts_atpu_urls as $url) :
		$request = _ts_atpu_prepare_request( $action, array( 'get' => $args->slug ) );
		$response = wp_remote_post($url, $request);

		if (is_wp_error($response)) {
			_ts_atpu_push_error($response);
			continue;
		}

		if ($response['response']['code'] != 200) :
			_ts_atpu_push_error( _ts_atpu_error(
				__('Something went wrong!'),
				$response['response']['code'],
				__FILE__,
				__FUNCTION__,
				__LINE__-6 ) );
			continue;
		endif;

		$response = json_decode($response['body']);

		if (is_null($response)) :
			_ts_atpu_error(
				 __('Response not valid JSON'),
				null,
				__FILE__,
				__FUNCTION__,
				__LINE__-6 );
		elseif (isset($response->error)) :
			if ($response->error != 'not found') :  // ignore not-found
				_ts_atpu_push_error( _ts_atpu_error(
					__('Response error'),
					$response->error . ' : ' . $response->reason,
					__FILE__,
					__FUNCTION__,
					__LINE__-7 ) );
			endif;
		elseif (isset($response->info)) :
			$response = object2array($response->info);  // need array's for child elements
			$response = (object) $response;  // cast base array back to object, leaving child's as array

			return apply_filters('plugins_api_result', $response, $action, $args);
		// else
			// continue;
		endif;
	endforeach;

	return false;  // not found
}
add_filter('plugins_api', 'ts_atpu_plugins_api', 10, 3);


/* eof */
