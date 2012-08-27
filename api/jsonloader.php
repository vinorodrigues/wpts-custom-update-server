<?php
/**
 * @author Vino Rodrigues
 * @package TS-Automatic-Theme-Plugin-Update
 * @since TS-Automatic-Theme-Plugin-Update 0.1
 *
 * WARNING: Make sure your JSON files are valid, or else the objects will fail to load.
 */

define( 'ASSET_FOLDER', 'assets' );

function object2array($object) {
	if (is_object($object)) {
		$array = array();
		foreach ($object as $key => $value) {
			if (is_object($value))
				$array[$key] = object2array ($value);
			else
				$array[$key] = $value;
		}
	} elseif (is_array($object)) {
		$array = $object;
	} else {
		$array = array($object);
	}
	return $array;
}

function array_merge_unique($array1, $array2, $overwrite = true) {
	if ( !is_array($array1) || !is_array($array2)) return false;

	foreach ($array2 as $k=>$v) :
		if (is_array($v)) :
			if (isset($array1[$k]) && is_array($array1[$k])) :
				$array1[$k] = array_merge_unique($array1[$k], $v, $overwrite);
			else :
				if ($overwrite) $array1[$k] = $v;
			endif;
		else :
			if (isset($array1[$k])) :
				if ($overwrite) $array1[$k] = $v;
			else
				$array1[$k] = $v;
			endif;
		endif;
	endforeach;

	return $array1;
}

function get_packages() {
	$files = array();
	$handler = opendir(ASSET_FOLDER);
	while ($file = readdir($handler)) {
		if ($file != "." && $file != ".." && (pathinfo($file, PATHINFO_EXTENSION) == 'json')) {
			$files[] = ASSET_FOLDER . '/' . $file;
		}
	}
	closedir($handler);

	ksort($files);

	$packages = array();
	foreach ($files as $file) :
		$data = file_get_contents($file);
		$data = json_decode($data);
		if (is_a($data, 'StdClass')) :
			$package = object2array($data);
			$packages = array_merge_unique($packages, $package);
		endif;
	endforeach;

	return $packages;
}

/* eof */
