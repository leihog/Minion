<?php
namespace Bot\Config;

Interface IConfig
{
	/**
	 * Returns the config parameter specified by $name
	 * @param string $name
	 * @param mixed $defaultValue
	 * @return mixed config option
	 */
	public function get( $name, $defaultValue = false );
	
	/**
	 * Load a config file
	 * @param string $file
	 */
	public function load( $file );
	
	/**
	 * Save config to file
	 * @param string $file
	 */
	public function save( $file );
}
