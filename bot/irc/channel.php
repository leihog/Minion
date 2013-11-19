<?php

namespace Bot\Irc;

class Channel
{
	protected $name = null;
	protected $users = [];
	protected $isSyncing = false;

	public function __construct($name)
	{
		$this->name = $name;
	}

	public function addUser($hostmask)
	{
		$this->users[$hostmask->getNick()] = $hostmask;
	}

	public function getName()
	{
		return $this->name;
	}

	public function getUser($nick)
	{
		if (isset($this->users[$nick])) {
			return $this->users[$nick];
		}
		return null;
	}
	public function getUsers()
	{
		return array_values($this->users);
	}
	public function hasUser($nick)
	{
		if (isset($this->users[$nick])) {
			return true;
		}
		return false;
	}

	public function isSyncing()
	{
		return $this->isSyncing;
	}

	public function removeUser($hostmask)
	{
		$nick = $hostmask->getNick();
		if (isset($this->users[$nick])) {
			unset($this->users[$nick]);
		}
	}

	public function setSyncing($syncing = true)
	{
		$this->isSyncing = $syncing;
		if ($syncing) {
			$this->users = [];
		}
	}
}
