<?php
namespace Bot;

require_once('functions.php');

class Bot
{
	protected static $instance;

	protected $startTime;
	/** @var string Path to bots working directory */
	protected $workingDirectory;
	protected $dataDirectory;
	protected $pluginDirectory;
	protected $database;
	protected $engineOn;
	protected $serverConnection = null;
	protected $connectionHandler;
	protected $commandDaemon;
	protected $cron;
	protected $pluginHandler;
	protected $log;

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
		$classFile = strtolower( str_replace('\\', '/', $class ) ) . '.php';
		if ( file_exists( $classFile ) ) {
			include_once( $classFile );
			if ( class_exists($class, false) || interface_exists($class, false) ) {
				return true;
			}
		}

		Bot::log("[autoload] Failed to load {$class} from file {$classFile}");
		return false;
	}

	/**
	 * Shutsdown the bot
	 */
	protected function doShutdown($msg)
	{
		Bot::log('Saving bot memory...');
		$this->memory->save();

		Bot::log("Shutting down ($msg).");
		$this->engineOn = false;
		if ( $this->serverConnection ) {
			$this->serverConnection->doQuit($msg);
		}
	}

	protected function init()
	{
		if ( $_SERVER['argc'] != 2 ) {
			$this->showHelp();
		}

		$this->workingDirectory = rtrim($_SERVER['argv'][1], '/');
		if ( !is_dir($this->workingDirectory) || !is_readable($this->workingDirectory) ) {
			echo "Error: Invalid bot path. \n";
			$this->showHelp();
		}

		$this->dataDirectory = $this->workingDirectory . "/data";
		if ( !is_dir($this->dataDirectory) || !is_writable($this->dataDirectory) ) {
			echo "Error: Invalid data directory. make sure that '{$this->dataDirectory}' exists and is writable by the bot. \n";
			$this->showHelp();
		}

		$this->pluginDirectory = $this->workingDirectory . "/plugins";
		if ( !is_dir($this->pluginDirectory) ) {
			echo "Error: Invalid plugins directory. make sure that '{$this->pluginDirectory}' exists. \n";
			$this->showHelp();
		}

		try
		{
			$configFile = $this->workingDirectory . "/config.php";
			$fileExt = pathinfo($configFile, PATHINFO_EXTENSION);
			switch($fileExt)
			{
			case "php":
				Config::init( new Config\ArrayStore($configFile) );
				break;
			default:
				Bot::log("Unsupported config format.");
				exit;
			}
			Config::load();

			// setup logging
			$log = new Log();
			$log->addWriter(
				new Log\File( "{$this->workingDirectory}/logs/bot.log" )
			);
			$log->addWriter(function($str) {
				echo $str;
			});
			$this->log = $log;

			Bot::log("Booting up...");
			$this->cron = new Cron\Daemon(5);
			$this->database = new Database();

			// Give the bot a memory, with long term storage
			$memStore = new \Bot\Memory\DbStorage($this->database);
			$this->memory = new \Bot\Memory\Memory($memStore);
			$this->cron->schedule('5i', true, [$this->memory, 'save']);

			// @todo should tell it to use streams here.
			// perhaps by adding a \Adapter\Stream\Selector
			$this->connectionHandler = new Connection\Handler();

			$this->pluginHandler = new Plugin\Handler( $this->pluginDirectory );
			$this->commandDaemon = new Command();

			Event\Dispatcher::addListener( $this ); // Bot is always first in stack
			Event\Dispatcher::addListener( $this->commandDaemon );
			Event\Dispatcher::addListener( $this->pluginHandler );

			$plugins = Config::get('autoload');
			foreach($plugins as $plugin)
			{
				$this->pluginHandler->loadPlugin($plugin);
			}

			$this->startTime = time();
			$this->main();
		}
		catch( \Exception $e )
		{
			echo $e->getMessage(), "\n";
		}
	}

	/**
	 * Main loop, keeps us running.
	 *
	 * this is built for single server usage, but I haven't actually 
	 * decided if I want to go multiserver or not yet.
	 */
	protected function main()
	{
		$lastRetry = 0;
		$this->engineOn = true;
		$servers = Config::get('servers');

		while($this->engineOn) {

			if ( !$this->serverConnection && (Config::get('server-cycle-wait', 60) + $lastRetry) <= time() )
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
						if ( Config::get('never-give-up', false) )
						{
							reset($this->servers);
						}
						else if ( !$this->connectionHandler->hasConnections() )
						{
							Bot::shutdown("All connections closed...");
						}
					}
				}
				else if ( !$this->connectionHandler->hasConnections() )
				{
					Bot::shutdown("All connections closed...");
				}
			}

			if ($this->connectionHandler->hasConnections()) {
				$this->connectionHandler->select();
			}

			$this->cron->tick();

			usleep(500000);// Allow the cpu to rest...
		} // end while
	}

	protected function serverConnect( $serverAddress )
	{
		Bot::log("Connecting to $serverAddress.");

		/* @todo The adapter should be configurable */
		$adapter = new \Bot\adapter\Stream\Client();
		$server = new Connection\Server( Config::get('irc'), $adapter );
		if ( $server->connect($serverAddress) )
		{
			Bot::log("Connected to {$server->getHost()}:{$server->getPort()}");
			$this->serverConnection = $server; // @todo We shouldn't need this.
			$this->connectionHandler->addConnection($server);
			return true;
		}
		return false;
	}

	/**
	 * Display Bot help and exit
	 * @return void
	 */
	protected function showHelp()
	{
		echo "Syntax: php bot.php <bot path> \n";
		exit();
	}

	// Static methods
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

	public static function getCommandDaemon()
	{
		return self::getInstance()->commandDaemon;
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
		* return the connectionHandler
		* @return Bot\Connection\Handler
		*/
	public static function getConnectionHandler()
	{
		return self::getInstance()->connectionHandler;
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

	public static function cron($schedule, $repeat, $callback)
	{
		$this->cron->schedule($schedule, $repeat, $callback);
	}

	public static function memory()
	{
		return self::getInstance()->memory;
	}

	public static function log( $msg )
	{
		if ( self::$instance->log ) {
			self::$instance->log->put( $msg );
		} else {
			echo $msg, "\n";
		}
	}

	public static function shutdown($msg)
	{
		self::$instance->doShutdown($msg);
	}

	public static function uptime()
	{
		$now = new \DateTime();
		$uptime= $now->diff(new \DateTime('@'.self::$instance->startTime));

		$pluralize = function($str) {
			if (substrto($str, ' ') > 1) return $str . 's';
			return $str;
		};

		$ret = array();
		if ( $uptime->d > 0 ) $ret[] = $pluralize("{$uptime->d} day");
		if ( $uptime->h > 0 ) $ret[] = $pluralize("{$uptime->h} hour");
		if ( $uptime->i > 0 ) $ret[] = $pluralize("{$uptime->i} minute");
		if ( $uptime->s > 0 ) $ret[] = $pluralize("{$uptime->s} second");

		return implode(' ', $ret);
	}

	// Event Handlers
	public function onDisconnect( Event\Connection $event )
	{
		Bot::log("Lost connection to {$event->getHost()}:{$event->getPort()}");
		if ( $event->getConnection() instanceOf \Bot\Connection\Server )
		{
			$this->connectionHandler->removeConnection( $event->getConnection() );
			$this->serverConnection = null;
		}
	}

}
new Bot();
