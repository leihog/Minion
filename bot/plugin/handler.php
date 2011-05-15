<?php
namespace Bot\Plugin;

class Handler extends \Bot\Loader
{
    protected $plugins = array();

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
    				    echo $e->getMessage(), "\n";
    				}
    			}

				$iterator->next();
    		}
    	}
    }

    public function loadPlugin( $name )
    {
        if ( !$this->hasPlugin($name) )
        {
            try
            {
                $plugin = $this->cloneObject( '\Bot\Plugin\\' . $name );
                $this->plugins[ $name ] = $plugin;
    
                if ( method_exists($plugin, 'init') )
                {
                    $plugin->init();
                }
    
                \Bot\Bot::getEventHandler()->raise( new \Bot\Event\Plugin('loadplugin', array('plugin' => $plugin)) );
                return true;
            }
            catch( \Exception $e )
            {
                echo "Failed to load plugin '{$name}', Reason: {$e->getMessage()}. \n";
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
    	if ( !$this->hasPlugin( $name ) )
    	{
    		return false;
    	}

    	if ( $this->loadClass( '\Bot\Plugin\\' . $name, $force ) )
    	{
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
    	    \Bot\Bot::getEventHandler()->raise( new \Bot\Event\Plugin('unloadplugin', array( 'plugin' => $this->plugins[$name] )) );
    		unset( $this->plugins[$name] ); /** @todo maybe we should call an unload function on the plugin frist. */
    	}
    }
}