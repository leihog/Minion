<?php
namespace Bot\Connection;

class Handler
{
	protected $connections = array();
	protected $resources = array();

	/**
	 * @param iConnection $connection
	 */
	public function addConnection( IConnection $connection )
	{
		$resource = $connection->getResource();
		$id = (int)$resource;
		$this->connections[$id] = $connection;
		$this->resources[$id] = $resource;
	}

	/**
	 * Returns an array of connections
	 * @return IConnection[]
	 */
	public function getConnections()
	{
		return array_values($this->connections);
	}

	/**
	 * @return boolean
	 */
	public function hasConnections()
	{
	    return (bool) count($this->connections);
	}

	/**
	 * Checks for connection activity
	 */
	public function select()
	{
		foreach($this->resources as $resource)
		{
			if (feof($resource))
			{
				$connection = $this->connections[(int)$resource];
				$connection->disconnect();
			}
		}

		if (empty($this->resources))
		{
			return;
		}

		$read = $this->resources;
		$write = $this->resources;
		$except = null;
		$tv_usec = null;
		if ( ($num = stream_select($read, $write, $except, 0, $tv_usec)) === false )
		{
			Bot::log("Error: stream_select returned false...");
			/** @todo do something real */
			return;
		}

		foreach( $read as $resourceId )
		{
			if (isset($this->connections[(int)$resourceId]))
			{
				$connection = $this->connections[(int)$resourceId];
				$connection->onCanRead();
			}
		}

		foreach( $write as $resourceId )
		{
			if (isset($this->connections[(int)$resourceId]))
			{
				$connection = $this->connections[(int)$resourceId];
				$connection->onCanWrite();
			}
		}

	}

	/**
	 * @param IConnection $connection
	 */
	public function removeConnection( IConnection $connection )
	{
		$id = (int)$connection->getResource();
		unset($this->resources[$id], $this->connections[$id]);
	}
}
