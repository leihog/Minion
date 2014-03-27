<?php

/**
 * Convert byte count to human readable format
 *
 * @param int $size
 * @return string
 */
function format_bytes($size)
{
	$unit = array('b','kb','mb','gb','tb','pb');
	return @round($size/pow(1024,($i=floor(log($size ,1024)))),2).' '.$unit[$i];
}

/**
 * Generic functions that didn't fit in a specific class
 *
 * Since functions can't be imported using 'Use'
 * we place these functions outside a namespace.
 */
function substrto($str, $to)
{
	if (($pos = strpos($str, $to)) === false) {
		return $str;
	}
	return substr($str, 0, $pos);
}

function array_rand_value(array $array)
{
	return $array[array_rand($array, 1)];
}

function array_path($arr, $path, $defaultValue = null) {
	if (strpos($path, '/') !== false) {
		$sections = explode('/', $path);
		while (($section = array_shift($sections))) {
			if (isset($arr[$section])) {
				$arr = $arr[$section];
			} else {
				return $defaultValue;
			}
		}
		return $arr;
	}

	return (isset($arr[$path]) ? $arr[$path] : $defaultValue);
}
