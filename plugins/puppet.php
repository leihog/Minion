<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Puppet extends Plugin
{
	public function cmdJoin( \Bot\Event\Irc $event, $channel, $key = '' )
	{
		$event->getServer()->doJoin($channel, $key);
	}

	public function cmdPart( \Bot\Event\Irc $event, $channel )
	{
		$event->getServer()->doPart($channel);
	}

	public function cmdQuit( \Bot\Event\Irc $event, $msg = 'zZz' )
	{
		$event->getServer()->doQuit( $msg );
	}

	public function cmdHello( \Bot\Event\Irc $event )
	{
		$event->getServer()->doPrivmsg($event->getSource(), "Hello, how are you?");
	}

	public function cmdRaw( \Bot\Event\Irc $event, $raw )
	{
		$event->getServer()->doRaw( $raw );
	}

	public function cmdSay( \Bot\Event\Irc $event, $target, $msg )
	{
		$server = $event->getServer();
		$server->doPrivmsg($target, $msg);
	}

	public function cmdWho( \Bot\Event\Irc $event, $chan, $mode = 'compact' )
	{
		if ( $event->isFromChannel() )
		{
			return;
		}

		$server = $event->getServer();

		$nick = $event->getHostmask()->getNick();
		if ( !in_array($chan[0], array('#', '&', '!', '~', '+')) )
		{
			$chan = "#{$chan}";
		}

		$channelDaemon = Bot::getChannelDaemon();

		if ( !$channelDaemon->isOn($chan) )
		{
			$server->doPrivmsg($nick, "I'm not watching that channel.");
			return;
		}

		if ( $channelDaemon->isSyncing($chan) )
		{
			$server->doPrivmsg($nick, "Channel is resynchronizing, try again in a little while...");
			return;
		}

		$usersEnabled = ( Bot::getPluginHandler()->hasPlugin('users') ? true : false );

		$users = $channelDaemon->getUsers($chan);
		$userCount = count($users);

		$server->doPrivmsg($nick, sprintf('Showing %s user%s on %s', $userCount, ($userCount == 1 ? '':'s'), $chan ));

		switch($mode)
		{
			case 'detailed':
				break;

			default:

				$list = array();
				foreach( $users as $userNick => $userHostmask )
				{
					if ( $usersEnabled && \Bot\User::isIdentified( $userHostmask ) )
					{
						$userNick .= '*';
					}

					$list[] = $userNick;
				}

				$server->doPrivmsg($nick, $this->formatTableArray( $list, "%-10s", 4, 15 ));
		}

	}
}
