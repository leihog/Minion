<?php
namespace Bot;

class Bot
{
	protected static $instance;

	/** @var string Path to bots working directory */
	protected $workingDirectory;
	protected $dataDirectory;
	protected $pluginDirectory;

    protected $config;
    protected $database;
    protected $engineOn;
    protected $serverConnection = null;

    // Daemons
    protected $eventHandler;
    protected $socketHandler;
    protected $channelDaemon;
    protected $commandDaemon;
    protected $pluginHandler;

	public function __construct()
	{
		set_time_limit(0);
		self::$instance = $this;

		spl_autoload_register( array($this, 'autoLoadClass') );

    	if ( $_SERVER['argc'] != 2 )
    	{
    		$this->showHelp();
    	}

    	$this->workingDirectory = rtrim($_SERVER['argv'][1], '/');
    	if ( !is_dir($this->workingDirectory) || !is_readable($this->workingDirectory) )
    	{
    	    echo "Error: Invalid bot path. \n";
    	    $this->showHelp();
    	}

        $this->dataDirectory = $this->workingDirectory . "/data";
    	if ( !is_dir($this->dataDirectory) || !is_writable($this->dataDirectory) )
    	{
    	    echo "Error: Invalid data directory. make sure that '{$this->dataDirectory}' exists and is writable by the bot. \n";
    	    $this->showHelp();
    	}

    	$this->pluginDirectory = $this->workingDirectory . "/plugins";
    	if ( !is_dir($this->pluginDirectory) )
    	{
    	    echo "Error: Invalid plugins directory. make sure that '{$this->pluginDirectory}' exists. \n";
    	    $this->showHelp();
    	}

        $this->init();
	}

    /**
     * Loads the php file corresponding to the given class name.
     */
    public function autoLoadClass( $class )
    {
		$classFile = strtolower( str_replace('\\', '/', $class ) ) . '.php';
		if ( file_exists( $classFile ) )
		{
    		include_once( $classFile );
    		if ( class_exists($class, false) || interface_exists($class, false) )
    		{
    			return true;
    		}
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
     * @return \Bot\Database
     */
    public static function getDatabase()
    {
        return self::getInstance()->database;
    }

    public static function getDir( $name = '' )
    {
        $obj = self::getInstance();

        if ( !empty($name) )
        {
            $prop = "{$name}Directory";
            if ( property_exists($obj, $prop) )
            {
                return $obj->$prop;
            }

            return $obj->workingDirectory . DIRECTORY_SEPARATOR . $name;
        }

        return $obj->workingDirectory;
    }

    // Daemons

    public static function getChannelDaemon()
    {
        return self::getInstance()->channelDaemon;
    }

    public static function getCommandDaemon()
    {
        return self::getInstance()->commandDaemon;
    }

    /**
     * @return \Bot\Event\Handler
     */
	public static function getEventHandler()
	{
		return self::getInstance()->eventHandler;
	}

	/**
	 * Returns the plugin handler
	 * @return \Bot\Plugin\Handler
	 */
	public static function getPluginHandler()
	{
		return self::getInstance()->pluginHandler;
	}

	/**
	 * @todo Does this really need to be public static
	 * return the socketHandler
	 * @return Bot\Socket\Handler
	 */
	public static function getSocketHandler()
	{
		return self::getInstance()->socketHandler;
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

	public static function getServer()
	{
	    if ( !self::getInstance()->serverConnection )
	    {
	        return false;
	    }

	    return self::getInstance()->serverConnection;
	}

    protected function init()
    {
    	try
    	{
	    	$configFile = $this->workingDirectory . "/config.php";
	    	$this->config = new Config\Native();
    		$this->config->load($configFile);

    		echo "Booting up... \n";

    		// init sub systems.
    		$this->database = new Database();
    		$this->eventHandler = new Event\Handler();
    		$this->socketHandler = new Socket\Handler();
    		$this->pluginHandler = new Plugin\Handler( $this->pluginDirectory );
            $this->commandDaemon = new Daemon\Command();
            $this->channelDaemon = new Daemon\Channel();

    		$this->eventHandler->addEventListener( $this ); // Bot is always first in stack
            $this->eventHandler->addEventListener( $this->commandDaemon ); // command handler must come before any plugins.
            $this->eventHandler->addEventListener( $this->channelDaemon );
    		$this->eventHandler->addEventListener( $this->pluginHandler );

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
            if ( !$this->serverConnection && (Bot::getConfig('server-cycle-wait', 60) + $lastRetry) <= time() )
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

    protected function serverConnect( $serverAddress )
    {
        echo "Connecting to $serverAddress.\n";

        @list($transport, $host, $port, $password) = preg_split('@\://|\:@', $serverAddress);
        $serverAddress = compact('transport', 'host', 'port', 'password');
        $settings = array_merge($serverAddress, Bot::getConfig('irc'));

        $this->serverConnection = new Connection\Server($settings);
        if ( $this->serverConnection->connect() )
        {
            echo "Connected to {$this->serverConnection->getHost()}:{$this->serverConnection->getPort()}\n";
            return true;
        }
        else
        {
            $this->serverConnection = null;
        }

        return false;
    }

    /**
     * Display Bot help and exit
     * @return void
     */
    protected function showHelp()
    {
        echo "Syntax: bot.php <bot path> \n";
        exit();
    }

    /**
     * Shutsdown the bot
     *
     * @todo disconnect all sockets...
     * @todo save everything that needs to be saved...
     */
    public function shutdown($msg = '')
    {
    	echo $msg, "Shutting down.\n";
        $this->engineOn = false;
    }

    public function onDisconnect( Event\Socket $event )
    {
        echo "Lost connection to {$event->getHost()}:{$event->getPort()}\n";
        if ( $event->getSocket() instanceOf \Bot\Connection\Server )
        {
            $this->serverConnection = null;
        }
    }

}
new Bot();