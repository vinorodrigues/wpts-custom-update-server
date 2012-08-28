<?php
/**
 * Core functionality of TS-Automatic-Theme-Plugin-Update API end-site.
 * Based on: http://konstruktors.com/blog/wordpress/2538-automatic-updates-for-plugins-and-themes-hosted-outside-wordpress-extend/
 * 
 * @author Vino Rodrigues
 * @package TS-Automatic-Theme-Plugin-Update
 * @since TS-Automatic-Theme-Plugin-Update 0.1
 *
 * WARNING: This code is not very forgiving on errors. Error handling is minimal and non-descript.
 */

define( 'LOADER', 'jsonloader' );

global $request_data;
global $packages;

require_once(LOADER.'.php');

/**
 * get var
 *
 * @global type $request_data
 * @param string $name
 * @return string or false
 */
function get_request($name) {
	global $request_data;
	if (is_a($request_data, 'StdClass') && isset($request_data->$name))
		return $request_data->$name;
	if (isset($_REQUEST[$name]))
		return $_REQUEST[$name];
	return false;  // no luck
}

if (!function_exists('http_response_code')) :
function http_response_code($code) {
	header(':', true, $code);
	header("HTTP/1.0 $code", true, $code);
	header("Status: $code", true, $code);
}
endif;

if (false && file_exists('whitelist.php'))  // TODO : remove false
	include_once('whitelist.php');

// Force refresh
header('CacheControl: no-cache, must-revalidate');
header('Expires: Tue, 1 Jan 1980 00:00:00 GMT');
header('Pragma: no-cache');
header('Content-Type: application/json');

// Get json raw data sent
$request_data = json_decode(file_get_contents("php://input"));

if (isset($whitelist)) :
	$api_key = get_request('api-key');
	if ( !$api_key || !in_array($api_key, $whitelist) ) :
		// http_response_code(403);
		print json_encode(array('error'=>'Access denied', 'reason'=>'Not in whitelist'));
		die();
	endif;
endif;

// get packages from loader
$packages = function_exists('get_packages') ? get_packages() : array();

// Get user agent
// $user_agent = $_SERVER['HTTP_USER_AGENT'];

// Get action
$action = strtolower( get_request('action') );

if ($action == 'list_all') :
	print json_encode($packages);
	die();
endif;

// Get data
$data = get_request('data');
if (is_a($data, 'StdClass'))
	$data = object2array($data);

// TODO : check data is array or string...

function get_latest_version($slug) {
	global $packages;

	$latest = -1;
	if ( ! isset($packages[$slug] ) ) return $latest;

	foreach ($packages[$slug]['versions'] as $ver => $data) :
		if (version_compare($latest, $ver, '<'))
			$latest = $ver;
	endforeach;
	return $latest;
}

function get_version_data($slug, $ver) {
	global $packages;
	if ( isset( $packages[$slug] ) )
		return $packages[$slug]['versions'][$ver];
	else
		return array();
}


switch ($action) :
	case 'check' :
		$checklist = array();
		if (isset($data['plugins']))
			foreach ( $data['plugins'] as $slug => $ver )
				$checklist[$slug] = $ver;
		if (isset($data['themes']))
			foreach ( $data['themes'] as $slug => $ver )
				$checklist[$slug] = $ver;
		
		$response = array();

		foreach ( $checklist as $slug => $v_sent ) :
			$v_stored = get_latest_version($slug);
			if ( version_compare($v_sent, $v_stored, '<') ) :
				$latest_package = get_version_data($slug, $v_stored);
				$response[$slug] = array(
					'new_version' => $v_stored,
					'slug' => $slug,
					'package' => isset($latest_package['download_link']) ? $latest_package['download_link'] : '',
				);
				if ( isset( $latest_package['url'] ) )
					$response[$slug]['url'] = $latest_package['url'];  // Info URL
			endif;
		endforeach;

		if ( count($response) > 0 )
			print json_encode( array( 'new' => $response ) );
		else
			print json_encode( array( 'ok' => 'no updates' ) );
		die();
		break;

	case 'plugin_information' :
		if (isset($data['get'])):
			$slug = $data['get'];
			$latest_vesion = get_latest_version($slug);
			$latest_package = get_version_data($slug, $latest_vesion);
			if (empty($latest_package)) :
				print json_encode( array( 
					'error' => 'not found',
					'reason' => 'no package for ' . $slug,
				) );
			else :
				print json_encode( array(
					'info' => $latest_package,
				) );
			endif;
			die();
		endif;
		// let it error out 'casue the request is invalid
		break;
endswitch;


/// go away!
http_response_code(403);
print json_encode(array(
    'error' => 'Access denied',
    'reason' => 'No direct access',
    ) );


/* eof */
