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
			if (isset($result['value']['joke'])) {
				$event->respond(html_entity_decode($result['value']['joke']));
			}
		}
	}
}
