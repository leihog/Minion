<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Chayism extends Plugin
{
	public function cmdChayism($event)
	{
		$chayism = $this->getUrl('http://phpdoc.info/chayism/');
		if ($chayism) {
			$event->getServer()->doPrivmsg($event->getSource(), $chayism);
		}
	}
}
