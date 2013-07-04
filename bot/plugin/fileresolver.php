<?php
namespace Bot\Plugin;
use \Bot\Bot as Bot;

class FileResolver
	{
	protected $pluginPath;

	public function __construct( $path )
	{
		$this->pluginPath = $path;
	}

	public function resolve( $class )
	{
		$class = str_replace(array('\Bot\Plugin\\', '\\'), array('', '/'), $class);
		if ($class == 'Plugin' ) {
			return 'bot/plugin/plugin.php';
		}

		return $this->pluginPath . strtolower($class) . '.php';
	}
}
