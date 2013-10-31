<?php

namespace Bot\Cron;

class Queue extends \SplPriorityQueue
{
	public function __construct()
	{
		$this->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
	}

	public function compare($val1, $val2)
	{
		if ($val1 < $val2) {
			return 1;
		} else if ($val1 > $val2) {
			return -1;
		} else {
			return 0;
		}
	}

	public function canDequeue($timestamp)
	{
		if ($this->isEmpty()) {
			return false;
		}

		$next = $this->top();
		if ($next['priority'] <= $timestamp) {
			return true;
		}
		return false;
	}

	public function dequeue()
	{
		$job = $this->extract();
		return $job['data'];
	}
}
