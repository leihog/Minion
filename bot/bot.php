<?php
namespace Bot;

class Bot
{
	protected static $instance;

    protected $config;
    protected $database;
    protected $pluginHandler;

    protected $engineOn;
    protected $eventHandler;
    protected $socketHandler;
    
    protected $serverConnection = null;

	public function __construct()
	{
		set_time_limit(0);
		self::$instance = $this;

		spl_autoload_register( array($this, 'autoLoadClass') );       

        $this->init();
	}

    /**
     * Loads the php file corresponding to the given class name.
     */
    public function autoLoadClass( $class )
    {
    	try
    	{
    		$classFile = strtolower( str_replace('\\', '/', $class ) ) . '.php';
    		@include_once( $classFile );
    		if ( class_exists($class, false) || interface_exists($class, false) )
    		{
    			return true;
    		}
    	}
    	catch( \Exception $e )
    	{
    		echo $e->getMessage(), "\n";
    	}
    	
    	echo '(ERROR) Failed to load ', $class, ' from file ', $classFile, "\n";
    	return false;
    }

    public static function getConfig( $name = false, $defaultValue = false )
    {
        if(!$name)
        {
            return self::getInstance()->config;
        }

        return self::getInstance()->config->get($name, $defaultValue);
    }

    /**
     * 
     * @return \Bot\Database
     */
    public static function getDatabase()
    {
        return self::getInstance()->database;
    }

    /**
     * @return \Bot\Event\Handler
     */
	public static function getEventHandler()
	{
		return self::getInstance()->eventHandler;
	}
    
	/**
	 * For convenience, not a real singleton tho.
	 * @return Bot
	 */
	public static function getInstance()
	{
		if ( isset(self::$instance) )
		{
			return self::$instance;
		}
	}

	/**
	 * Returns the plugin handler
	 * @return \Bot\Plugin\Handler
	 */
	public static function getPluginHandler()
	{
		return self::getInstance()->pluginHandler;
	}
	
	public function getServer()
	{
	    if ( !$this->serverConnection )
	    {
	        return false;
	    }

	    return $this->serverConnection;
	}

	/**
	 * return the socketHandler
	 * @return Bot\Socket\Handler
	 */
	public static function getSocketHandler()
	{
		return self::getInstance()->socketHandler;
	}

    public function init()
    {
    	try
    	{
	    	if ( $_SERVER['argc'] != 2 )
	    	{
	    		throw new \Exception('Syntax: bot.php <config file>');
	    		return;
	    	}
	    	$configFile = $_SERVER['argv'][1];

	    	$this->config = new Config\Native();
    		$this->config->load($configFile);

    		//check if data directory exists and is read/writeable
    		$dataDirectory = $this->config->get('data-dir', 'data');
            if ( (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0655) ) || !is_writable($dataDirectory) || !is_readable($dataDirectory) )
    		{
    			throw new \Exception('Invalid data directory.');
    		}

    		echo "Booting up... \n";

    		// init sub systems.
    		$this->database = new Database();
    		$this->eventHandler = new Event\Handler();
    		$this->socketHandler = new Socket\Handler();
    		$this->pluginHandler = new Plugin\Handler();

    		$this->eventHandler->addEventListener( $this->pluginHandler );
    		$this->eventHandler->addEventListener( $this );
			

    		$plugins = $this->config->get('autoload');
    		foreach($plugins as $plugin)
    		{
    		    $this->pluginHandler->loadPlugin($plugin);
    		}

            $this->main();
        }
        catch( \Exception $e )
        {
        	echo $e->getMessage(), "\n";
        }
    }
    
    /**
     * The legendary main loop
     */
    protected function main()
    {
        $lastRetry = 0;
        $this->engineOn = true;
        $servers = Bot::getConfig('servers');

        while( $this->engineOn )
        {            
            if ( !$this->serverConnection && (time() - $lastRetry) >= Bot::getConfig('server-cycle-wait', 60) )
            {
                if ( ($server = current($servers)) !== false )
                {
                    $lastRetry = time();
                    if ( $this->serverConnect($server) )
                    {
                        reset($servers);
                        $lastRetry = 0;
                    }
                    else if ( !next($servers) )
                    {
                        if ( Bot::getConfig('never-give-up', false) )
                        {
                            reset($this->servers);
                        }
                        else if ( !$this->socketHandler->hasSockets() )
                        {
                            $this->shutdown("All connections closed...");
                        }
                    }
                }
                else if ( !$this->socketHandler->hasSockets() )
                {
                    $this->shutdown("All connections closed...");
                }
            }

            if ( $this->socketHandler->hasSockets() )
            {
                $this->socketHandler->select();
            }

            //$this->eventHandler->raise( new Event\Event('Tick') ); // Not sure this would do any good
            usleep(500000);// Allow the cpu to rest...
        }
    }

    protected function serverConnect( $server )
    {
        echo "Connecting to $server.\n";

        @list($transport, $host, $port, $password) = preg_split('@\://|\:@', $server);
        $server = compact('transport', 'host', 'port', 'password');
        $settings = array_merge($server, Bot::getConfig('irc'));

        $this->serverConnection = new Connection\Server($settings);
        if ( $this->serverConnection->connect() )
        {
            echo "Connected to {$this->serverConnection->getHost()}:{$this->serverConnection->getPort()}\n";
            return true;
        }
        
        return false;
    }

    /**
     * Shutsdown the bot
     */
    public function shutdown($msg = '')
    {
    	// todo:
    	// disconnect all sockets...
    	// save everything that needs to be saved...

    	echo $msg, "Shutting down.\n";
        $this->engineOn = false;
    }

    // Events - Move to plugins later... ?
        
    public function onDisconnect( Event\Socket $event )
    {
        echo "Lost connection to {$event->getHost()}:{$event->getPort()}\n";
        /** @todo attempt to reconnect x times */
    }

    public function onLoadPlugin( \Bot\Event\Plugin $event )
    {
        $plugin = $event->getPlugin();
        Command::extractCommandPointers($plugin);

        echo "Loaded plugin {$plugin->getName()}... \n";
    }

    public function onUnloadPlugin( \Bot\Event\Plugin $event )
    {
        $plugin = $event->getPlugin();
        Command::removeCommandPointers($plugin);
        Command::removeAclHandler($plugin); // an alternative would be to allow the plugin to unset itself. But then plugin authors could forget.

        echo "Unloaded plugin {$plugin->getName()}... \n";
    }

}
new Bot();