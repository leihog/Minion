<?php
namespace Bot\Plugin;

use Bot\Bot as Bot;

class Debug extends Plugin
{
	public function init()
	{
		\Bot\Event\Dispatcher::addListener($this);
	}

	public function unload()
	{
		\Bot\Event\Dispatcher::removeListener($this);
	}

	public function __call($method, $args)
	{
		if ( strpos($method, 'on') === 0 ) {
			if ( $args[0] instanceOf \Bot\Event\Irc ) {
				$this->handleIrcEvent( $args[0] );
			}
		}
	}

	protected function handleIrcEvent( \Bot\Event\Irc $event )
	{
		Bot::log($event->getRaw());

		$params = $event->getParams();
		if ( ($c = count($params)) ) {
			Bot::log("Params:");
			for( $i=0; $i<$c; $i++ ) {
				Bot::log(sprintf("  [%s] %s\n", 1+$i, $params[$i]));
			}
		}
	}

}
