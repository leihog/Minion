<?php

namespace Bot\Plugin;

use Bot\Bot as Bot;
use Bot\Config as Config;
use Bot\User as User;

class Acl extends Plugin
{
	protected $accessControlList;
	protected $defaultCommandLevel;

	public function init()
	{
		$this->accessControlList = array();
		$this->defaultCommandLevel = Config::get('plugins/acl/default-level', 0);

		if (!\Bot\Schema::isInstalled($this->getName()) ) {
			Bot::log("Installing plugin ". $this->getName());
			$schema = new \Bot\Schema($this->getName(), __DIR__ . '/acl.schema');
			$schema->install();
		}

		$this->loadAcl();
		Bot::getCommandDaemon()->addAclHandler($this);
		Bot::log("Acl loaded with ". count($this->accessControlList). " commands.");
	}

	public function decorateCmdListItem($cmd, $decorated)
	{
		$level = (isset($this->accessControlList[$cmd]) ? $this->accessControlList[$cmd] : $this->defaultCommandLevel);
		return sprintf("[%3s] %s", $level, $decorated);
	}

	public function checkACL($cmdName, $event)
	{
		$currentLevel = 0;
		$hostmask = $event->getHostmask();
		if (User::isIdentified($hostmask)) {
			$user = User::fetch($hostmask);
			$currentLevel = $user->getLevel();
		}

		if (!isset($this->accessControlList[$cmdName])) {
			return true;
		}

		if ($this->accessControlList[$cmdName] <= $currentLevel) {
			return true;
		}

		return false;
	}

	public function cmdSetacl( \Bot\Event\Irc $event, $cmdName, $level = false )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();

		if (!$level) {
			$this->removeAcl($cmdName);
			$event->getServer()->doPrivmsg($nick, "Removed ACL for {$cmdName}.");
		} else {
			$this->setAcl($cmdName, $level);
			$event->getServer()->doPrivmsg($nick, "Updated ACL for {$cmdName}.");
		}
	}

	protected function loadAcl()
	{
		$db = Bot::getDatabase();
		$list = $db->fetchAll('SELECT cmd, level FROM acl');
		foreach($list as &$acl) {
			$this->accessControlList[ $acl['cmd'] ] = $acl['level'];
		}
	}

	protected function removeAcl($cmd)
	{
		if (isset($this->accessControlList[$cmd])) {
			$db = Bot::getDatabase();
			$db->execute('DELETE FROM acl WHERE cmd = :cmd', compact('cmd') );
			return true;
		}

		return false;
	}

	protected function setAcl($cmd, $level)
	{
		$db = Bot::getDatabase();
		if (isset($this->accessControlList[$cmd])) {
			if ($this->accessControlList[$cmd] != $level) {
				$db->execute('UPDATE acl SET level = :level WHERE cmd = :cmd', compact('cmd', 'level'));
			}
		} else {
			$db->execute('INSERT INTO acl (cmd, level) VALUES (:cmd, :level)', compact('cmd', 'level'));
		}
	}
}
