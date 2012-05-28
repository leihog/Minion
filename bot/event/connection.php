<?php
namespace Bot\Event;

class Connection extends Event
{
	protected $connection;

	public function getHost()
	{
		if (isset($this->connection))
		{
			return $this->connection->getHost();
		}
	    return false;
	}

	public function getPort()
	{
		if (isset($this->connection))
		{
			return $this->connection->getPort();
		}
		return false;
	}

	public function getConnection()
	{
		return $this->connection;
	}

}
