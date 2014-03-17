<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Irc\Server as Server;
use Bot\Config as Config;

class Timed extends Plugin
{
	private $scheduled;
	private $compiled_schedule;
	private $compile_day;

	public function init()
	{
		$this->setup();
	}

	public function onConfigLoaded()
	{
		$this->setup();
	}

	public function onHeartbeat()
	{
		if (empty($this->scheduled)) {
			return;
		}

		list($now["time"], $now["dow"]) = explode(' ', strtolower(date("H:i D")), 2);

		if ($this->compile_day != $now["dow"]) {
			$this->compileSchedule($now);
		}

		if (empty($this->compiled_schedule[$now["time"]])) {
			return;
		}

		$servers = $this->getServers();
		foreach($this->compiled_schedule[$now["time"]] as $response) {
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
			return;
		}

		$this->compileSchedule();
	}

	protected function compileSchedule($now = null)
	{
		if (!$now) {
			list($now["time"], $now["dow"]) = explode(' ', strtolower(date("H:i D")), 2);
		}
		
		$this->compiled_schedule = [];
		foreach($this->scheduled as $row) {
			list($schedule["time"], $schedule["dow"], $schedule["action"]) = explode(' ', $row, 3);
			if ($this->shouldTriggerToday($now, $schedule)) {
				$this->compiled_schedule[$schedule["time"]][] = $schedule["action"];
			}
		}
		$this->compile_day = $now["dow"];
	}

	protected function shouldTriggerToday(&$now, &$schedule)
	{
		$dow = $schedule["dow"];
		if (stristr($dow, ",")) {
			$dow = explode(",", $dow);
		} else {
			$dow = [$dow];
		}

		$weekdays = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
		foreach($dow as $tmp_dow) {
			if (stristr($tmp_dow, "-")) { // handle day range
				list($day_from, $day_to) = explode('-', $tmp_dow, 2);

				$index_day_from = $weekdays[$day_from];
				$index_day_to   = $weekdays[$day_to];
				$dow_index      = $weekdays[$now["dow"]];

				if ($index_day_from > $index_day_to) {
					if (in_array($dow_index, range($index_day_to + 1, $index_day_from - 1))) {
						// match against the days we do not want to trigger on
						continue;
					}
				} else if (!in_array($dow_index, range($index_day_from, $index_day_to))) {
					// match against days we do want to trigger on
					continue;
				}
			} else if ($now["dow"] != $tmp_dow) {
				continue;
			}

			return true;
		}
		return false;
	}
}
