<?php
namespace Bot\Irc;

use Bot\Bot as Bot;
use Bot\Irc\Channel as Channel;
use Bot\Event\Dispatcher as Event;

class Server implements \Bot\Connection\IConnection
{
	protected $host;
	protected $port;
	protected $transport;

	protected $nick;
	protected $realname;
	protected $username;

	protected $serverUris;
	protected $channels;
	protected $iSupport = [];

	// used for I/O
	protected $adapter;
	protected $buffer = '';
	protected $writeQueue = array();

	// anti send-flood
	protected $rate = 5.0;
	protected $per = 8.0;
	protected $lastChecked;
	protected $allowance;

	// Reconnect settings
	protected $reconnect_enabled = true;

	public function __construct($options, $adapter)
	{
		$this->config = $options;

		foreach($options as $key => $value) {
			$method = "set{$key}";
			if (method_exists($this, $method)) {
				$this->$method($value);
			}
		}

		$this->adapter = $adapter;
		$this->channels = []; //new \Bot\Channels();
	}

	public function connect()
	{
		if (($uri = current($this->serverUris)) !== false) {
			if ($this->doConnect($uri)) {
				reset($this->serverUris);
				return true;
			} else {
				if (!next($this->serverUris)) {
					if (false) {
						Bot::log('Giving up reconnecting...');
						return false;
					}
					reset($this->serverUris);
				}
				// schedule new attempt in 1 min
				Bot::cron(60, false, [$this, 'connect']);
			}
		}
		return false;
	}

	public function disconnect($msg = null)
	{
		$this->doQuit($msg, true);
		if ($this->adapter->isConnected()) {
			$this->adapter->disconnect();
		}
	}

	protected function doConnect($uri)
	{
		@list($transport, $host, $port) = preg_split('@\://|\:@', $uri);
		if ($this->adapter->connect($transport, $host, $port)) {
			// This should probably be stored in the adapter
			$this->transport = $transport;
			$this->host = $host;
			$this->port = $port;

			$this->buffer = '';
			$this->writeQueue = [];
			$this->reconnect_enabled = true;

			// initializing anti-client-flood
			$this->lastChecked = time();
			$this->allowance = $this->rate;

			Bot::connections()->addConnection($this);

			$this->doNick($this->getNick());
			$this->doUser($this->getUsername(), $this->getRealname(), $this->getHost());

			return true;
		}

		return false;
	}

	protected function handleInput( $line )
	{
		if (empty($line)) {
			return;
		}

		$cmd = $args = $raw = $hostmask = null;
		extract( \Bot\Parser\Irc::parse( $line ), EXTR_IF_EXISTS);

		$event = new \Bot\Event\Irc($cmd, $args);
		$event->setRaw($raw);
		$event->setServer($this);

		if (!is_numeric($cmd) && strpos($hostmask, '@')) {
			$hostmask = new \Bot\Hostmask($hostmask);
			$event->setHostmask($hostmask);
		}

		switch($cmd) {
			case 'ping':
				$this->send($this->prepare('PONG', $args[0]), true);
				break;

			case '005':
				$this->parseISupportLine($args[0]);
				break;

			case '315':
				/* $tmp = explode(' ', $args[0]); */
				/* $channel = array_shift($tmp); */
				$channel = substrto($args[0], ' ');
				if (isset($this->channels[$channel])) {
					$this->channels[$channel]->setSyncing(false);
				}
				break;

			case '352':
				list($channel, $ident, $host, $server, $nick, $modes, $hopCount, $realname) = explode(' ', $args[0]);
				if (!isset($this->channels[$channel])) {
					break; // perhaps we should add the channel instead.
				}

				$channel = $this->channels[$channel];
				if (!$channel->isSyncing()) {
					$channel->setSyncing(true);
				}

				$channel->addUser(new \Bot\Hostmask( "{$nick}!{$ident}@{$host}"));
				break;

			case 'join':
				$channel = $args[0];
				$nick = $hostmask->getNick();

				if ($nick == $this->getNick()) {
					$chanObj = new Channel($channel);
					$this->channels[$channel] = $chanObj;
					$this->doRaw("WHO $channel");
				} else {
					$this->channels[$channel]->addUser($hostmask);
				}
				break;

			case 'kick':
				$chan = $args[0];
				$nick = $args[1];

				if (!isset($this->channels[$chan])) {
					break;
				}

				$channel = $this->channels[$chan];
				if (!$channel->hasUser($nick)) {
					break;
				}

				$hostmask = $channel->getUser($nick);
				$event->setParam('target', $hostmask);
				$channel->removeUser($hostmask);
				break;

			case 'nick':
				$newNick = $args[0];
				$newHostmask = clone $hostmask;
				$newHostmask->setNick($newNick);

				$channels = [];
				foreach($this->channels as $channel) {
					if (!$channel->hasUser($hostmask->getNick())) {
						continue;
					}

					$channels[] = $channel->getName();
					$channel->removeUser($hostmask);
					$channel->addUser($newHostmask);
				}

				$event->setChannels($channels);
				break;

			case 'part':
				$channel = $args[0];
				$nick = $hostmask->getNick();

				if ($nick == $this->getNick()) {
					unset($this->channels[$channel]);
					break;
				}

				$this->channels[$channel]->removeUser($hostmask);
				break;

			case 'quit':
				$nick = $hostmask->getNick();

				$channels = array();
				foreach($this->channels as $channel) {
					if ($channel->hasUser($nick)) {
						$channel->removeUser($hostmask);
						$channels[] = $channel->getName();
					}
				}

				$event->setChannels($channels);
				break;
		} // end switch

		Event::dispatch($event);
	}

	protected function parseISupportLine($line)
	{
		$line = trim(substr($line, 0, strrpos($line, ':')));
		$parts = explode(' ', $line);
		foreach($parts as $part) {
			@list($key, $value) = explode('=', $part);
			$this->iSupport[strtolower($key)] = $value;
		}
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
		if (empty($args)) {
			throw new \Exception('Prepare() called with no parameters...');
		}

		$buffer = array_shift($args);
		if (!empty($args)) {
			if (count($args) == 1 && is_array($args[0])) {
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
	public function close($msg = null)
	{
		$this->disconnect($msg);
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

	public function onClosed()
	{
		Bot::log("Lost connection to {$this->getHost()}:{$this->getPort()}");
		Event::dispatch(
			new \Bot\Event\Connection('Disconnect', array('connection' => $this))
		);

		if ($this->reconnect_enabled) {
			Bot::log('Should reconnect');
		// @todo if the server disconnected us then reconnect
		}
	}

	// getters / setters
	public function getConfig()
	{
		return $this->config;
	}
	public function getChannel($name)
	{
		if (isset($this->channels[$name])) {
			return $this->channels[$name];
		}
		return null;
	}

	public function getChannels()
	{
		return $this->channels;
	}
	public function getHost()
	{
		return $this->host;
	}

	public function getPort()
	{
		return $this->port;
	}
	public function getNetwork()
	{
		if (isset($this->iSupport['network'])) {
			return $this->iSupport['network'];
		}

		if (isset($this->config['network'])) {
			return $this->config['network'];
		}

		return null;
	}
	public function getNick()
	{
	    return $this->nick;
	}

	public function getRealname()
	{
		return $this->realname;
	}

	public function getUsername()
	{
		return $this->username;
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

	public function setServers(array $servers)
	{
		$this->serverUris = $servers;
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

	public function doQuit($reason = 'zZz', $skipQueue = false)
	{
		$this->reconnect_enabled = false;
		$this->send($this->prepare('QUIT', array($reason)), $skipQueue);
	}

	public function doRaw( $msg )
	{
		$this->send( $msg );
	}

}
