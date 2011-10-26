<?php
namespace Bot\Event;

class Handler
{
    const HALT_EXECUTION = 'HALT';

	protected $eventListeners = array();

	public function addEventListener( $listener )
	{
		$this->eventListeners[] = $listener;
	}

	/**
	 * Alert all  event listeners of the event
	 *
	 * @param Abstract $event
	 *
	 * @todo filter on event type
	 */
	public function raise( $event )
	{
		if (empty($this->eventListeners))
		{
			return;
		}

		foreach( $this->eventListeners as &$listener )
		{
			$eventName = 'on' . $event->getName(); // Do we need this? ucfirst($event->getName());
			$method = array($listener, $eventName);

			if (is_callable($method))
			{
				$status = call_user_func_array($method, array(&$event));
				if ( $status == self::HALT_EXECUTION ) /** @todo Do we want to limit what listeners that can halt the execution? */
				{
				    break;
				}
			}
		}
	}

	/**
	 * Remove an event listener
	 *
	 * @param unknown_type $listener
	 */
	public function removeEventListener( $listener )
	{
		throw new \Exception('Not implemented yet (removeEventListener)');
	}
}