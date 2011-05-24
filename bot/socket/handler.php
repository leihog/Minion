<?php
namespace Bot\Socket;

class Handler
{
	protected $sockets = array();
	protected $resources = array();

	/**
	 *
	 * @todo add socket grouping somehow... maybe a new param $groupName?
	 *
	 * @param unknown_type $socket
	 */
	public function addSocket( $socket )
	{
		$resource = $socket->getResource();
		$id = (int)$resource;

		$this->sockets[$id] = $socket;
		$this->resources[$id] = $resource;
	}

	/**
	 * Returns an array of sockets
	 * @todo add some way to filter which type of socket to get.
	 * @return array
	 */
	public function getSockets()
	{
		return $this->sockets;
	}

	public function hasSockets()
	{
	    return (bool) count($this->sockets);
	}

	/**
	 * Checks for socket activity
	 *
	 * @todo when we support other socket types ie servers, can we still read on all sockets?
	 */
	public function select()
	{
		foreach($this->resources as $resource)
		{
		    if (feof($resource))
		    {
		        $socket = $this->sockets[(int)$resource];
		        $socket->disconnect();
		    }
		}

		if (empty($this->resources))
		{
			return;
		}

		$read = $this->resources;
		$write = $this->resources;
		if ( ($num = stream_select($read, $write, $except = null, $tv_sec = null)) === false )
		{
            echo "Error: stream_select returned false...\n";
			/** @todo do something real */
			return;
		}

		foreach( $read as $resourceId )
		{
		    if (isset($this->sockets[$resourceId]))
		    {
    			$socket = $this->sockets[$resourceId];
    			$socket->read();
		    }
		}

		foreach( $write as $resourceId )
		{
		    if (isset($this->sockets[$resourceId]))
		    {
    			$socket = $this->sockets[$resourceId];
    			$socket->processWriteQueue();
		    }
		}

	}

	public function removeSocket( $id )
	{
		if (is_object($id)) /** @todo this needs to be a bit more specific */
		{
			$id = (int)$id->getResource();
		}

		unset($this->resources[$id], $this->sockets[$id]); exit;
	}
}