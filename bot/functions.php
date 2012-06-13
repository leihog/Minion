<?php

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

