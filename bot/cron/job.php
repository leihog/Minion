<?php

namespace Bot\Cron;

class Job
{
	protected $interval;
	protected $callback;
	protected $repeat;

	public function __construct(array $settings)
	{
		$this->callback = $settings['callback'];
		$this->repeat   = $settings['repeat'];
		$this->interval = $settings['interval'];
	}

	public function getInterval()
	{
		return $this->interval;
	}

	public function shouldRepeat()
	{
		return (bool) $this->repeat;
	}

	public function run()
	{
		$cb = $this->callback;
		$cb();

		if ($this->repeat) {
			if (is_numeric($this->repeat)) {
				$this->repeat--;
			}
		}
	}
}
