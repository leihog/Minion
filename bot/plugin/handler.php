<?php
namespace Bot\Plugin;

use Bot\Event\Dispatcher as Event;
use Bot\Bot as Bot;

class Handler
{
	protected $plugins = array();
	protected $loader;

	public function __construct($path)
	{
		$this->loader = new Loader($path);
	}

	public function __call($name, $params)
	{
		if ( strpos($name, 'on') === 0 )
		{
			$obj = new \ArrayObject($this->plugins);
			$iterator = $obj->getIterator();
			while ( $iterator->valid() )
			{
				$plugin = $iterator->current();
				if (method_exists($plugin, $name))
				{
					try
					{
						call_user_func_array(array($plugin, $name), $params);
					}
					catch( \Exception $e )
					{
						Bot::log($e->getMessage());
					}
				}
				$iterator->next();
			}
		}
	}

	/**
	 * @todo make sure plugin extends \Bot\Plugin\Plugin
	 */
	public function loadPlugin( $name )
	{
		if ( !$this->hasPlugin($name) ) {
			try {
				$plugin = $this->loader->createInstance( '\Bot\Plugin\\' . $name );
				$this->plugins[ $name ] = $plugin;
				$plugin->init();
				Bot::log("Loaded plugin {$plugin->getName()}...");
				
				Event::dispatch(
					new \Bot\Event\Plugin('loadplugin', array('plugin' => $name))
				);
				return true;
			} catch(\Exception $e) {
				Bot::log("Failed to load plugin '{$name}', Reason: {$e->getMessage()}.");
			}
		}

		return false;
	}

	public function getPlugin( $name )
	{
		if ( isset($this->plugins[$name]) )
		{
			return $this->plugins[$name];
		}

		return false;
	}

	/**
	 * Returns a list of the loaded plugins
	 * @return array
	 */
	public function getPlugins()
	{
		return array_keys($this->plugins);
	}

	public function hasPlugin( $name )
	{
		if ( !isset($this->plugins[$name]) )
		{
			return false;
		}

		return true;
	}

	public function reloadPlugin( $name, $force = false )
	{
		if ( !$this->hasPlugin( $name ) ) {
			return false;
		}

		if ( $this->loader->loadClass( '\Bot\Plugin\\' . $name ) ) {
			$this->unloadPlugin($name);
			$this->loadPlugin($name);
			return true;
		}
		return false;
	}

	public function unloadPlugin( $name )
	{
		if ($this->hasPlugin($name))
		{
			$plugin = $this->plugins[$name];

			Event::dispatch(
				new \Bot\Event\Plugin('unloadplugin', array('plugin' => $name))
			);

			$plugin->unload();
			unset( $this->plugins[$name] );
			$plugin = null;
			Bot::log("Unloaded plugin {$name}...");
		}
	}
}
