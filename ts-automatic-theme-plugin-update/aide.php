<?php
/**
 * Helper functions
 *
 * @author Vino Rodrigues
 * @package TS-Automatic-Theme-Plugin-Update
 * @since TS-Automatic-Theme-Plugin-Update 0.1
 */

if ( ! function_exists('object2array') ) :
/**
 * converts StdClass objects to arrays
 *
 * @param mixed $object
 * @return array
 */
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
endif;

/* eof */
