<?php
namespace Bot\Event;

class Dispatcher
{
	const HALT_EXECUTION = 'HALT';
	protected static $listeners = array();

	public static function addListener( $listener )
	{
		self::$listeners[] = $listener;
	}

	/**
	 * Alert all event listeners of the event
	 *
	 * @param Abstract $event
	 *
	 * @todo filter on event type
	 */
	public static function dispatch( $event )
	{
		if (empty(self::$listeners)) {
			return;
		}

		foreach( self::$listeners as &$listener ) {
			$eventName = 'on' . $event->getName();
			$method = array($listener, $eventName);

			if (is_callable($method)) {
				$status = call_user_func_array($method, array(&$event));
				// @todo Do we want to limit what listeners
				// that can halt the execution? Do we want this at all?
				if ( $status == self::HALT_EXECUTION ) {
					break;
				}
			}
		}
	}

	/**
	 * Remove an event listener
	 *
	 * @param object $listener
	 */
	public static function removeListener($listener)
	{
		foreach(self::$listeners as $i=>$l) {
			if ($l == $listener) {
				unset(self::$listeners[$i]);
			}
		}
	}
}
