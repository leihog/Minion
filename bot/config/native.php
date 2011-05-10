<?php
namespace Bot\Config;

class Native implements IConfig
{
	protected $config;
	
	/**
	 * Load a php file containing an array
	 * 
	 *  @todo try to find file using various extensions?
	 *  
	 * @param string $file
	 */
	public function load( $file )
	{
		if ( !file_exists($file) )
		{
			throw new \Exception("Unable to load config from file '{$file}'");
		}
		
		$this->config = include($file);
	}
	
	public function get( $section, $defaultValue = false )
	{
		if ( strpos($section, '/') !== false )
		{
			$sections = explode('/', $section);
			
			$arr = $this->config;
			while( ($section = array_shift($sections)) )
			{
				if ( isset($arr[$section]) )
				{
					$arr = $arr[$section];					
				}
				else
				{
					return ($defaultValue ? $defaultValue : false);
				}
			}

			return $arr;
		}
		
		return ( isset($this->config[$section]) ? $this->config[$section] : $defaultValue );
	}
	
	public function save( $file )
	{
		throw new \Exception('Not implemented yet...');
	}
}