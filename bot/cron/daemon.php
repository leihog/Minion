<?php

namespace Bot\Cron;

class Daemon
{
	protected $queue;
	protected $ticks = 0;
	protected $lastTick = 0;
	protected $ticksPerSec = 0;
	protected $ticksPerPulse;
	protected $pulsePrecision;

	public function __construct($precision = 3)
	{
		// The precision we strive for, yet can't guarantee
		$this->pulsePrecision = $precision;
		// This is just a default value to start with.
		// Will be auto adjusted.
		$this->ticksPerPulse = 3; //round($precision * 1.5);
		$this->queue = new Queue();
	}

	/**
	 * @param $schedule examples: after 1 hour: 3600, after 10 minutes: "10i"
	 */
	public function schedule($schedule, $repeat, $callback)
	{
		if (!is_callable($callback)) {
			return false;
		}

		if (is_numeric($schedule)) {
			$delay = $schedule;
		} else {
			$num = $unit = null;
			$units = array(
				's' => 1,
				'i' => 60,
				'h' => 3600,
			);
			if (preg_match("/([0-9]+)([sihdwmy]{1})/i", $schedule, $match)) {
				$num = $match[1];
				$unit = $match[2];
			}

			if (!isset($num, $unit)) {
				return false;
			}

			$delay = ($num * $units[$unit]);
		}

		$job = new Job(array(
			'interval' => $delay,
			'callback' => $callback,
			'repeat' => $repeat,
		));

		$this->queue->insert($job, time() + $delay);
	}

	/**
	 * Used by the bot to tell us that a new tick has occoured,
	 * a tick being an iteration of the bots event loop.
	 */
	public function tick()
	{
		if (!$this->lastTick) {
			$this->lastTick = time();
		}

		if ($this->ticks < $this->ticksPerPulse) {
			$this->ticks++;
			return;
		}

		$now = time();
		$elapsed = ($now - $this->lastTick);
		if ($elapsed == 0) {
			return;
		}

		if ($elapsed == 1) {
			$this->ticksPerSec = $this->ticks;
		} else if ($elapsed > 1) {
			$this->ticksPerSec = ($this->ticks / $elapsed);
		}

		if ($elapsed < $this->pulsePrecision) {
			$timeLeft = $this->pulsePrecision - $elapsed;
			$this->ticksPerPulse = $timeLeft * $this->ticksPerSec;
			$this->ticks = 0;
			return;
		}

		if ($elapsed > $this->pulsePrecision && $this->ticksPerPulse > 0) {
			$overTime = $elapsed - $this->pulsePrecision;
			$this->ticksPerPulse -= ($overTime * $this->ticksPerSec);
		}

		$this->ticks = 0;
		$this->lastTick = $now; // lastTickRound

		if ($this->queue->isEmpty()) {
			return;
		}

		while($this->queue->canDequeue($now)) {
			$job = $this->queue->dequeue();
			$job->run();

			if ($job->shouldRepeat()) {
				$this->queue->insert($job, time() + $job->getInterval());
			}
		}
	}
}
