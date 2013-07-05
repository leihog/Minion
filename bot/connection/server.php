<?php
namespace Bot\Connection;

use Bot\Event\Dispatcher as Event;

class Server implements IConnection
{
	protected $host;
	protected $port;
	protected $transport;

	protected $nick;
	protected $realname;
	protected $username;

	// used for I/O
	protected $buffer = '';
	protected $writeQueue = array();

	// anti send-flood
	protected $rate = 5.0;
	protected $per = 8.0;
	protected $lastChecked;
	protected $allowance;

	protected $channels;

	public function __construct( $options, $adapter )
	{
		foreach( $options as $key => $value )
		{
			$method = "set{$key}";
			if (method_exists($this, $method))
			{
				$this->$method($value);
			}
		}

		$this->adapter = $adapter;
		$this->channels = new \Bot\Channels();
	}

	protected function handleInput( $line )
	{
		if (empty($line)) {
			return;
		}

		$cmd = $args = $raw = $hostmask = null;
		extract( \Bot\Parser\Irc::parse( $line ), EXTR_IF_EXISTS);

		if ( $cmd == 'ping' ) {
			$this->send( $this->prepare('PONG', $args[0]), true );
		}

		$event = new \Bot\Event\Irc($cmd, $args);
		$event->setRaw($raw);
		$event->setServer($this);
		if ( !is_numeric($cmd) && strpos($hostmask, '@') ) {
			$event->setHostmask( new \Bot\Hostmask( $hostmask ) );
		}

		$method = "on{$cmd}";
		if ( method_exists($this->channels, $method) ) {
			$this->channels->$method( $event );
		}

		Event::dispatch( $event );
	}

	/**
	 * Builds a server command with parameters from the argument list
	 *
	 * @todo perhaps we should abandon this.
	 *
	 * @param string $cmd
	 * @param string $arg1, $arg2, $arg3
	 * @throws \Exception
	 */
	protected function prepare()
	{
		$args = func_get_args();
		if ( empty($args) )
		{
			throw new \Exception('Prepare() called with no parameters...');
		}

		$buffer = array_shift($args);
		if ( !empty($args) )
		{
			if ( count($args) == 1 && is_array($args[0]) )
			{
				$args = $args[0];
				$args[] = ':'. array_pop($args);
			}

			$buffer .= ' ' . preg_replace('/\v+/', ' ', implode(' ', $args));
		}

		return trim($buffer);
	}

	/**
	 * Adds $string to the send queue
	 * If $skipQueue is true then $string is sent right away.
	 *
	 * @param string $string
	 * @param boolean $skipQueue
	 */
	protected function send( $string, $skipQueue = false )
	{
	    $string .= "\r\n";
		if ( $skipQueue )
		{
			try
			{
				$this->adapter->write($string);
			}
			catch( \Exception $e )
			{
				Bot::log("Failed to write\n". $e->getMessage());
			}
			return;
		}

		$this->writeQueue[] = $string;
	}

	// IConnection methods
	public function connect( $uri )
	{
		@list($transport, $host, $port) = preg_split('@\://|\:@', $uri);

		if ($this->adapter->connect($transport, $host, $port))
		{
			// This should probably be stored in the adapter
			$this->transport = $transport;
			$this->host = $host;
			$this->port = $port;

			// initializing anti-client-flood
			$this->lastChecked = time();
			$this->allowance = $this->rate;

			Event::dispatch(
				new \Bot\Event\Irc( 'Connect', array( 'server' => $this ) )
			);

			return true;
		}

		return false;
	}

	public function disconnect()
	{
		if ( $this->adapter->isConnected() )
		{
			// @todo Make sure that we finish writing the queue.. or is that not important?
			$this->adapter->disconnect();
		}

		Event::dispatch(
			new \Bot\Event\Connection( 'Disconnect', array('connection' => $this) )
		);
	}

	public function getResource()
	{
		return $this->adapter->getResource();
	}

	/**
	 * reads from the adapter.
	 */
	public function onCanRead()
	{
		$buffer = $this->buffer . $this->adapter->read(512);
		$this->buffer = '';
		if (empty($buffer)) {
			return;
		}

		$incompleteRead = false;
		if ( !preg_match('/\v+$/', $buffer) ) {
			$incompleteRead = true;
		}

		$buffer = preg_split('/\v+/', $buffer);
		if ( $incompleteRead ) {
			$this->buffer = array_pop($buffer);
		}

		foreach($buffer as &$line) {
			$this->handleInput( trim($line) );
		}
	}

	public function onCanWrite()
	{
		if (empty($this->writeQueue))
		{
			return;
		}

		$now = time();
		$timePassed = ($now - $this->lastChecked);
		$this->lastChecked = $now;
		$this->allowance += ( $timePassed * ($this->rate / $this->per) );
		if ($this->allowance > $this->rate)
		{
		    $this->allowance = $this->rate;
		}

		/** @todo when we fail to write we need to re-add the buffer to the queue. */
		while( $this->allowance > 1 && ($buffer = array_shift($this->writeQueue)) )
		{
			try
			{
				--$this->allowance;
				$this->adapter->write( $buffer );
			}
			catch(\Exception $e)
			{
				Bot::log("Failed to write\n". $e->getMessage());
				break;
			}
		}
	}


	// getters / setters
	public function getHost()
	{
		return $this->host;
	}

	public function getPort()
	{
		return $this->port;
	}
	public function getNick()
	{
	    return $this->nick;
	}

	public function setNick( $nick )
	{
		$this->nick = $nick;
	}

	public function setRealname( $realname )
	{
		$this->realname = $realname;
	}

	public function setUsername( $username )
	{
		$this->username = $username;
	}


	// IRC commands
	public function doEmote( $target, $msg )
	{
		$this->doPrivmsg($target, "\001ACTION {$msg}");
	}
	public function doJoin( $channel, $key = '' )
	{
		if ( is_array($channel) )
		{
			$channels = $keys = array();
			foreach( $channel as &$chan )
			{
				list($c, $k) = array_pad(explode(':', $chan, 2), 2, ' ');
				$channels[] = $c;
				$keys[] = $k;
			}

			$channel = implode(',', $channels);
			$key = implode(',', $keys);
		}

        $this->send( $this->prepare('JOIN', $channel, $key) );
	}

	public function doNick( $nick )
	{
	    $this->setNick($nick);
	    $this->send( $this->prepare('NICK', $nick) );
	}

	public function doUser( $username, $realname, $host )
	{
        $this->send( $this->prepare('USER', $username, $host, $host, $realname) );
	}

	public function doPart( $channel )
	{
		if ( is_array($channel) )
		{
			$channel = implode(',', $channel);
		}

		$this->send( $this->prepare('PART', $channel) );
	}

	/**
	 * Send a message to a channel or user
	 *
	 * @param string $target
	 * @param string|array $msg
	 */
	public function doPrivmsg( $target, $msg )
	{
		if ( !is_array($msg) ) {
			$msg = array($msg);
		}

		foreach( $msg as &$str ) {
			$this->send( $this->prepare('PRIVMSG', array($target, $str)) );
		}
	}

	public function doTopic( $channel, $topic = false )
	{
		$args = array($channel);
		if ($topic)
		{
			$args[] = $topic;
		}

		$this->send( $this->prepare('TOPIC', $args) );
	}

	public function doQuit( $reason = 'zZz' )
	{
		$this->send( $this->prepare('QUIT', array($reason)), true );
	}

	public function doRaw( $msg )
	{
	    $this->send( $msg );
	}

}
