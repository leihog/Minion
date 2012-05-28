<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Debug extends Plugin
{
	/** @todo add  preUnload hook ( unload(), destroy() ) */
	public function init()
	{
		\Bot\Event\Dispatcher::addListener($this);
	}

	public function __call($method, $args)
	{
		if ( strpos($method, 'on') === 0 ) {
			if ( $args[0] instanceOf \Bot\Event\Irc ) {
				$this->handleIrcEvent( $args[0] );
			}
		}
	}

	public function cmdGcStatus($event)
	{
		$msg = "Garbage collector status: ". (gc_enabled() ? 'ON' : 'OFF');
		$event->getServer()->doPrivmsg($event->getSource(), $msg);
	}

	public function cmdToggleGc($event)
	{
		if ( gc_enabled() ) {
			gc_disable();
			$msg = 'Disabled the garbage collector.';
		} else {
			gc_enable();
			$msg = 'Enabled the garbage collector.';
		}

		$event->getServer()->doPrivmsg($event->getSource(), $msg);
	}

	protected function handleIrcEvent( \Bot\Event\Irc $event )
	{
		printf("\n=== Event: %s === \n", $event->getName());
		echo  $event->getRaw(), "\n";

		$params = $event->getParams();
		if ( ($c = count($params)) ) {
			echo "Params: \n";
			for( $i=0; $i<$c; $i++ ) {
				printf("  [%s] %s\n", 1+$i, $params[$i]);
			}
		}

		echo "=== End ===\n";
	}

}
