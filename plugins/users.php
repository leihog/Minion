<?php
namespace Bot\Plugin;

use Bot\Bot;
use Bot\User as User;
use Bot\Event\Irc as IrcEvent;

class Users extends Plugin
{
	/**
	 * @todo if no password is given then send the syntax.
	 * 
	 * @param \Bot\Command $cmd
	 * @param unknown_type $password
	 * @param unknown_type $username
	 */
	public function cmdIdentify( \Bot\Event\Irc $event, $password, $username = false )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$server = $event->getServer();

		if ($event->isFromChannel()) {
			return;
		}

		if (User::isIdentified($hostmask)) {
			$server->doPrivmsg($nick, 'Yes yes, I know who you are...');
			return;
		}

		$username = ($username ? $username : $nick);
		if (User::authenticate($username, $password, $hostmask)) {
			$server->doPrivmsg($nick, "Ah it's you again...");

			$user = \Bot\User::fetch($username);
			if (!$user->hasHostmask($hostmask)) {
				$user->addHostmask($hostmask);

				$server->doPrivmsg($nick, "Added '{$hostmask}' to your list of hostmasks.");
				$server->doPrivmsg($nick, sprintf('You now have %s hostmasks.', count($user->getHostmasks()) ));
			}
		} else {
			$server->doPrivmsg($nick, 'Authentication failed!');
		}
	}

	public function cmdRegister( IrcEvent $event, $password = false )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$server = $event->getServer();

		if ($event->isFromChannel() || empty($password)) {
			$server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' REGISTER <password>');
			return;
		}

		if (User::exists($nick)) {
			$server->doPrivmsg($nick, "A user with the name '{$nick}' is already registered.");
			$server->doPrivmsg($nick, 'If this is you then identify yourself.');
			$server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() .' IDENTIFY <password>');
			return;
		}

		if (User::create($hostmask, $password)) {
			$server->doPrivmsg(
				$nick,
				"You have now been registered as '{$nick}' using the hostmask '{$hostmask}'."
			);
		} else {
			$server->doPrivmsg(
				$nick,
				"Something went wrong when trying to register you. Please try again later."
			);
		}
	}

	public function cmdUsers( \Bot\Event\Irc $event )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$server = $event->getServer();

		if ( $event->isFromChannel() ) {
			$server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' USERS');
			return;
		}

		$users = User::userList();
		$userCount = count($users);
		if (!$userCount) {
			$server->doPrivmsg($nick, 'No users found.');
			return;
		}

		foreach( $users as &$user ) {
			$user = array( $user['level'], $user['username'] );
		}

		$server->doPrivmsg($nick, 'Users:');
		$server->doPrivmsg($nick, $this->formatTableArray($users, "[%3s] %-14s", 4, 20));
	}
	public function cmdUptime( \Bot\Event\Irc $event )
	{
		$uptime = "My uptime is: ". Bot::uptime();
		$event->getServer()->doPrivmsg($event->getSource(), $uptime);
	}
}
