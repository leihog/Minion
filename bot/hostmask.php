<?php
namespace Bot;

class Hostmask
{
	protected $nick;
	protected $username;
	protected $host;

	public function __construct( $hostmask )
	{
		if (preg_match('/^([^!@]+)!(?:[ni]=)?([^@]+)@([^ ]+)/', $hostmask, $match)) {
			list(, $this->nick, $this->username, $this->host) = $match;
			return;
		}

		throw new \Exception('Invalid hostmask');
	}

	public function getNick()
	{
		return $this->nick;
	}

	public function setNick($nick)
	{
		$this->nick = $nick;
	}
	public function getUsername()
	{
		return $this->username;
	}

	public function getHost()
	{
		return $this->host;
	}

	public function toString()
	{
		return $this->nick . '!' . $this->username . '@' . $this->host;
	}

	public function __toString()
	{
		return $this->toString();
	}

	public function toArray()
	{
		return array(
			'nick'     => $this->nick,
			'username' => $this->username,
			'host'     => $this->host
		);
	}

}
