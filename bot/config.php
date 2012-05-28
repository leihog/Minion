<?php
namespace Bot;

class Config
{
	protected static $store = null;
	protected static $settings = array();

	public static function get( $section, $defaultValue = false )
	{
		if ( strpos($section, '/') !== false )
		{
			$sections = explode('/', $section);
			
			$arr = self::$settings;
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

		return ( isset(self::$settings[$section]) ? self::$settings[$section] : $defaultValue );
	}

	public static function init( $dataStore )
	{
		if ( !self::$store )
		{
			self::$store = $dataStore;
		}
	}

	public static function load()
	{
		if ( !self::$store )
		{
			throw new \Exception("Unable to load config.");
		}

		self::$settings = self::$store->load();
	}
}
