<?php
namespace Bot\Event;

class Event
{
	protected $name;
	protected $params;

	public function __construct($eventName, $params = array())
	{
		$this->name = $eventName;
		if (!is_array($params)) {
			return;
		}

		foreach( $params as $key => $value )
		{
			if (!is_numeric($key))
			{
				if (property_exists($this, $key))
				{
					$this->$key = $value;
					unset($params[$key]);
				}
			}
		}
		$this->params = $params;
	}

	public function getName()
	{
		return $this->name;
	}

	public function getParam($index, $default = false)
	{
		if ( isset($this->params[$index]) )
		{
			return $this->params[$index];
		}

		return $default;
	}

	public function setParam($index, $value)
	{
		$this->params[$index] = $value;
	}

	public function getParams()
	{
		return $this->params;
	}

	public function getType()
	{
		return get_class($this); /** @todo remove namespace from classname */
	}
}
