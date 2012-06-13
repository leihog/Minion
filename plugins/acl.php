<?php
namespace Bot\Plugin;

use Bot\Bot as Bot;
use Bot\Config as Config;
use Bot\User as User;

class Acl extends Plugin
{
	protected $accessControlList;
	protected $defaultCommandLevel;
	protected $restrictCmds; /** @todo try and remember what this is for? */

	public function init()
	{
		$this->accessControlList = array();
		$this->defaultCommandLevel = Config::get('plugins/acl/default-level', 0);
		$this->restrictCmds = Config::get('plugins/acl/restrict-cmds', false);

		$db = Bot::getDatabase();
		if ( !$db->isInstalled($this->getName()) ) {
			echo "Installing plugin ", $this->getName(), "\n";
			$db->install( $this->getName(), __DIR__ . '/acl.schema' );
		}

		$this->loadAcl();
		Bot::getCommandDaemon()->addAclHandler( $this );
		Bot::log("Acl loaded with ", count($this->accessControlList), " commands.\n");
	}

	public function checkACL( $cmdName, $event )
	{
		$currentLevel = 0;
		$hostmask = $event->getHostmask();
		if (User::isIdentified($hostmask)) {
			$user = User::fetch($hostmask);
			$currentLevel = $user->getLevel();
		}

		if ( !isset($this->accessControlList[ $cmdName ]) ) {
			return true;
		}

		if ( $this->accessControlList[ $cmdName ] <= $currentLevel ) {
			return true;
		}

		return false;
	}

	/**
	 * @todo this should be available even without the acl plugin.
	 *
	 * @param unknown_type $event
	 */
	public function cmdCmds( \Bot\Event\Irc $event )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$server = $event->getServer();

		if ($event->isFromChannel())
		{
			$server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' CMDS');
			return;
		}

		$level = 0;
		if (User::isIdentified($hostmask)) {
			$user = User::fetch($hostmask);
			$level = $user->getLevel();
		}

		$cmds = Bot::getCommandDaemon()->getCommands();
		$userCmds = array();
		foreach( $cmds as &$cmd ) {
			$cmdLevel = ( isset($this->accessControlList[$cmd]) ? $this->accessControlList[$cmd] : $this->defaultCommandLevel );
			if ( $cmdLevel > $level ) {
				continue;
			}

			$userCmds[] = array($cmdLevel, $cmd);
		}

		if ( ($cmdCount = count($userCmds)) ) {
			$server->doPrivmsg($nick, sprintf('%s available command%s', $cmdCount, ($cmdCount == 1 ? '':'s') ));
			$server->doPrivmsg($nick, $this->formatTableArray( $userCmds, "[%3s] %-14s", 4, 20 ));
		}
	}

	public function cmdSetacl( \Bot\Event\Irc $event, $cmdName, $level = false )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();

		if ( !$level )
		{
			$this->removeAcl($cmdName);
			$event->getServer()->doPrivmsg($nick, "Removed ACL for {$cmdName}.");
		}
		else
		{
			$this->setAcl($cmdName, $level);
			$event->getServer()->doPrivmsg($nick, "Updated ACL for {$cmdName}.");
		}
	}

	protected function loadAcl()
	{
		$db = Bot::getDatabase();
		$list = $db->fetchAll('SELECT cmd, level FROM acl');
		foreach($list as &$acl)
		{
			$this->accessControlList[ $acl['cmd'] ] = $acl['level'];
		}
	}

	protected function removeAcl($cmd)
	{
		if ( isset($this->accessControlList[$cmd]) )
		{
			$db = Bot::getDatabase();
			$db->execute('DELETE FROM acl WHERE cmd = :cmd', compact('cmd') );
			return true;
		}

		return false;
	}

	protected function setAcl($cmd, $level)
	{
		$db = Bot::getDatabase();

		if ( isset($this->accessControlList[$cmd]) )
		{
			if ( $this->accessControlList[$cmd] != $level )
			{
				$db->execute('UPDATE acl SET level = :level WHERE cmd = :cmd', compact('cmd', 'level') );
			}
		}
		else
		{
			$db->execute('INSERT INTO acl (cmd, level) VALUES (:cmd, :level)', compact('cmd', 'level') );
		}
	}
}
