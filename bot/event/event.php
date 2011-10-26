<?php
namespace Bot\Event;

abstract class Event
{
	protected $name;
	protected $params;

	public function __construct( $eventName, $params = array() )
	{
		$this->name = $eventName;

		if (is_array($params))
		{
            foreach( $params as $key => $value )
    		{
    		    if (!is_numeric($key))
    		    {
        			$method = "set{$key}";
        			if (method_exists($this, $method))
        			{
        				$this->$method($value);
        				unset($params[$key]); /** @todo unsetting keys inside foreach is bad. Don't do it. */
        			}
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

	public function getParams()
	{
		return $this->params;
	}

	public function getType()
	{
		return get_class($this); /** @todo remove namespace from classname */
	}
}