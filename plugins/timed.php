<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Irc\Server as Server;
use Bot\Config as Config;

class Timed extends Plugin
{
	private $scheduled;

	public function init()
	{
		$this->scheduled = Config::get("plugins/timed/schedule", []);
		if (empty($this->scheduled)) {
			Bot::log("[timed] No scheduled responses in config.");
			return;
		}
		Bot::cron('1i', true, [$this, "onTick"]);
	}

	public function onTick()
	{
		if (empty($this->scheduled)) {
			return;
		}

		$now = date("h:i");
		if (!isset($this->scheduled[$now])) {
			return;
		}

		$servers = $this->getServers();
		foreach($this->scheduled[$now] as $response) {
			list($network, $target, $response) = explode(" ", $response, 3);
			if (isset($servers[$network])) {
				$servers[$network]->doPrivmsg($target, $response);
			}
		}
	}

	protected function getServers()
	{
		$servers = [];
		$cons = Bot::connections()->getConnections();
		foreach($cons as $con) {
			if ($con instanceOf Server) {
				$network = $con->getNetwork();
				if ($network) {
					$servers[$network] = $con;
				}
			}
		}
		return $servers;
	}
}
