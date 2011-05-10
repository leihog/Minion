<?php
namespace Bot\Event;

class Socket extends Event
{
	protected $socket;
	
	public function getHost()
	{	    
	    if (isset($this->socket))
	    {
	        return $this->socket->getHost();
	    }
	    
	    return false;
	}
	
	public function getPort()
	{
	    if (isset($this->socket))
	    {
	        return $this->socket->getPort();
	    }
	    
	    return false;
	}
	
	/**
	 * @return the $socket
	 */
	public function getSocket()
	{
		return $this->socket;
	}

	/**
	 * @todo more specific type
	 * @param Object $socket
	 */
	public function setSocket($socket)
	{
		$this->socket = $socket;
	}	
}