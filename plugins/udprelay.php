<?php
namespace Bot\Plugin;

use Bot\Bot as Bot;
use Bot\Config as Config;

class udprelay extends Plugin implements \Bot\Connection\IConnection
{
	protected $resource;
	protected $keys = [];
	protected $requireKey;

	// IConnection
	public function close($msg = null)
	{
		fclose($this->resource);
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function onCanRead()
	{
		$this->read();
	}
	
	public function onCanWrite()
	{}
	public function onClosed()
	{
	}
	// end IConnection

	public function init()
	{
		$host = Config::get('plugins/udprelay/host', "127.0.0.1");
		$port = Config::get('plugins/udprelay/port', 9999);
		$this->requireKey = Config::get('plugins/udprelay/require-key', true);
		foreach(Config::get("plugins/udprelay/keys", []) as $key => $channel) {
			$this->keys[$key] = $channel;
		}

		if (!$this->listen("udp://{$host}:{$port}")) {
			return false;
		}

		Bot::connections()->addConnection($this);
		return true;
	}

	public function unload()
	{
		Bot::log("shutting down udp-relay");
		$this->close();
	}

	protected function getServerByNetworkName($network)
	{
		$connections = Bot::connections()->getConnections();
		foreach($connections as &$con) {
			if ($con instanceof \Bot\Irc\Server) {
				if ($network == strtolower($con->getNetwork())) {
					return $con;
				}
			}
		}
	}

	protected function relay($key, $target, $msg)
	{
		if (stristr($target, '/') === false) {
			return;
		}

		if ($this->requireKey) {
			if (empty($key) || !isset($this->keys[$key]) || $this->keys[$key] != $target) {
				return;
			}
		}

		list($network, $channel) = explode('/', $target, 2);
		$server = $this->getServerByNetworkName(strtolower($network));
		if (!$server) {
			return;
		}

		if ($server->getChannel($channel)) {
			$server->doPrivmsg($channel, $msg);
		}
	}

	protected function listen($uri)
	{
		$errorCode = $errorString = false;
		$this->resource = stream_socket_server($uri, $errorCode, $errorString, STREAM_SERVER_BIND);
		if (!$this->resource) {
			return false;
		}
		stream_set_blocking($this->resource, 0);
		return true;
	}

	protected function read($bytes = 1024)
	{
		$buffers = [];
		do {
			$data = stream_socket_recvfrom($this->resource, $bytes, 0, $peer);
			if ($data) {
				if (!isset($buffers[$peer])) {
					$buffers[$peer] = "";
				}
				$buffers[$peer] .= $data;
			}
		} while($data !== false);

		if (!empty($buffers)) {
			$this->handleInput($buffers);
		}
	}

	protected function handleInput($data)
	{
		foreach($data as $peer => $line) {
			// Match: abc123 freequest/#world :Hello World
			if (preg_match("@^(?:([^\s]+)\s)?([^:\s]+/[^:\s]+) :(.+)$@", $line, $matches)) {
				$this->relay($matches[1], $matches[2], $matches[3]);
			}
		}
	}
}
