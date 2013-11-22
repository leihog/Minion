<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Chuck extends Plugin
{
	public function cmdChuck($event)
	{
		$result = $this->getUrl("http://api.icndb.com/jokes/random");
		if ($result) {
			$result = json_decode($result, true);
			if (isset($result['joke'])) {
				$event->respond($result['joke']);
			}
		}
	}
}
