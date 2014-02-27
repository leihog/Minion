<?php
namespace Bot;

use Bot\Event\Dispatcher as Event;
use Bot\Event\Event as ConfigEvent;

class Config
{
	protected static $store = null;
	protected static $settings = [];

	public static function get( $section, $defaultValue = false )
	{
		if ( strpos($section, '/') !== false ) {
			$sections = explode('/', $section);
			
			$arr = self::$settings;
			while( ($section = array_shift($sections)) ) {
				if ( isset($arr[$section]) ) {
					$arr = $arr[$section];
				} else {
					return $defaultValue;
				}
			}

			return $arr;
		}

		return ( isset(self::$settings[$section]) ? self::$settings[$section] : $defaultValue );
	}

	public static function init( $dataStore )
	{
		if (!self::$store) {
			self::$store = $dataStore;
		}
	}

	public static function load()
	{
		if (!self::$store) {
			throw new \Exception("Unable to load config.");
		}

		self::$settings = self::$store->load();
		Event::dispatch(new ConfigEvent("configloaded"));
	}
}
