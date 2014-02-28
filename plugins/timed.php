<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Irc\Server as Server;
use Bot\Config as Config;

class Timed extends Plugin
{
	private $scheduled;

	/**
	 * @todo only listen to ticks if we have a schedule
	 */
	public function init()
	{
		$this->setup();
		Bot::cron('1i', true, [$this, "onTick"]);
	}

	public function onConfigLoaded()
	{
		$this->setup();
	}

	public function onTick()
	{
		if (empty($this->scheduled)) {
			return;
		}

		$now = date("H:i");
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
	protected function setup()
	{
		$this->scheduled = Config::get("plugins/timed/schedule", []);
		if (empty($this->scheduled)) {
			Bot::log("[timed] No scheduled responses in config.");
		}
	}
}
