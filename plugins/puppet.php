<?php

namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Event\Irc as IrcEvent;

class Puppet extends Plugin
{
	public function cmdJoin(IrcEvent $event, $channel, $key = '')
	{
		$event->getServer()->doJoin($channel, $key);
	}

	public function cmdPart(IrcEvent $event, $channel)
	{
		$event->getServer()->doPart($channel);
	}

	public function cmdQuit(IrcEvent $event, $msg = 'zZz')
	{
		$event->getServer()->doQuit($msg);
	}

	public function cmdRaw(IrcEvent $event, $raw)
	{
		$event->getServer()->doRaw($raw);
	}

	public function cmdSay(IrcEvent $event, $target, $msg)
	{
		$event->getServer()->doPrivmsg($target, $msg);
	}

	public function cmdWho(IrcEvent $event, $chan)
	{
		if ($event->isFromChannel()) {
			return;
		}

		$server = $event->getServer();
		$nick = $event->getHostmask()->getNick();

		if (!in_array($chan[0], array('#', '&', '!', '~', '+'))) {
			$chan = "#{$chan}";
		}

		$channel = $server->getChannel($chan);
		if (!$channel) {
			$server->doPrivmsg($nick, "I'm not watching that channel.");
			return;
		}

		if ($channel->isSyncing()) {
			$server->doPrivmsg($nick, "Channel is resynchronizing, try again in a little while...");
			return;
		}

		$users = $channel->getUsers();
		$userCount = count($users);

		$list = [];
		foreach($users as $userHostmask) {
			$userNick = $userHostmask->getNick();
			if (\Bot\User::isIdentified($userHostmask)) {
				$userNick .= '*';
			}

			$list[] = $userNick;
		}

		$server->doPrivmsg($nick, sprintf('Showing %s user%s on %s', $userCount, ($userCount == 1 ? '':'s'), $chan ));
		$server->doPrivmsg($nick, $this->formatTableArray($list, "%-10s", 4, 15));
	}

					$list[] = $userNick;
				}

				$server->doPrivmsg($nick, $this->formatTableArray( $list, "%-10s", 4, 15 ));
		}

	}
}
