<?php
namespace Bot\Plugin;

abstract class Plugin
{
	/**
	 * Called when the plugin is first loaded.
	 * Do any initializations and such here.
	 */
	public function init()
	{
	}

	/**
	 * This will break if the class doesn't have the added fingerprint AND has an _ in the name.
	 * This should never happen since the pluginHandler will always use blueprints.
	 */
	public function getName()
	{
		$className = get_class($this);
		if (preg_match("/([^\\\]+)_[^_]+$/", $className, $m))
		{
			return $m[1];
		}
		return $className;
	}

	/**
	* Returns an array of formated rows
	*
	* @todo make it handle utf-8 strings, right now padding + utf-8 = fail
	*
	* @param unknown_type $data
	* @param unknown_type $format
	* @param unknown_type $columns
	* @param unknown_type $columnWidth
	*/
	protected function formatTableArray($data, $format, $columns = 3, $columnWidth = 20)
	{
		$buffer = array();
		$rows = array();
		$i = 0;
		foreach( $data as &$item )
		{
			++$i;
			$buffer[] = vsprintf( $format, $item );
			if (count($buffer) == $columns || $i >= count($data)) {
				$lineFormat = str_repeat("%-{$columnWidth}s ", count($buffer));
				$rows[] = vsprintf( $lineFormat, $buffer );
				$buffer = array();
			}
		}

		return $rows;
	}

	/**
	 * Called when the plugin is unloaded.
	 * Do any uninitializations and such here.
	 */
	public function unload()
	{
	}

	/**
	 * Fetch the contents of an url.
	 *
	 * @todo Make the trac plugin use this function
	 * @todo should handle authentication.
	 */
	protected function getUrl( $url )
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
/*
		if ( $login ) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERPWD, $login);
		}
*/
		if ( strstr($url, 'https') ) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		}

		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
}
