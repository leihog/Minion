<?php
namespace Bot\Config;

class ArrayStore implements IStore
{
	protected $configFile;

	public function __construct( $filename )
	{
		$this->configFile = $filename;
	}

	public function load()
	{
		if ( !file_exists($this->configFile) )
		{
			throw new \Exception(
				"Unable to load config from file '{$this->configFile}'"
			);
		}
		
		return include($this->configFile);
	}

}
