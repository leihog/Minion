<?php

namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Event\Irc as IrcEvent;

class Puppet extends Plugin
{
	protected $forwards = [];

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


	// Forwarding should only be active over DCC
	// but we'll have to make do with this for now.

	public function cmdForward(IrcEvent $event, $source = null)
	{
		$nick = $event->getHostmask()->getNick();

		if (empty($source)) {
			$forwards = [];
			foreach(array_keys($this->forwards) as $key) {
				if (isset($this->forwards[$key][$nick])) {
					$forwards[] = $key;
				}
			}

			$sourceCount = count($forwards);
			if ($sourceCount) {
				$rows[] = implode(', ', $forwards);
			}
			$rows[] = $sourceCount . ($sourceCount==1 ? ' source is' : ' sources are') .' being forwarded to you.';
			$event->respond($rows);

			return;
		}

		if ($source != 'msgs') {
			$channel = $event->getServer()->getChannel($source);
			if (!$channel) {
				$event->respond("'{$source}' is not a valid source.");
				return;
			}
		}

		if (isset($this->forwards[$source][$nick])) {
			$event->respond("You are already forwarding from {$source}.");
			return;
		}

		$this->forwards[$source][$nick] = true;
		$event->respond("Forwarding {$source} to you. Use 'unforward {$source}' to stop the flood.");
	}

	public function cmdUnForward(IrcEvent $event, $source)
	{
		$nick = $event->getHostmask()->getNick();
		if (isset($this->forwards[$source][$nick])) {
			unset($this->forwards[$source][$nick]);
			$event->respond("Stopped forwarding from {$source}.");
		} else {
			$event->respond("You are not forwarding from {$source}.");
		}
	}

	public function onPrivmsg(IrcEvent $event)
	{
		$public = $event->isFromChannel();
		$source = ($public ? $event->getSource() : 'msgs');
		$nick = $event->getHostmask()->getNick();

		if (empty($this->forwards[$source])) {
			return;
		}

		if ($public) {
			$msg = '['. $source .'/'. $nick .'] '. $event->getParam(1);
		} else {
			$msg = '['. $nick .'] '. $event->getParam(1);
		}

		foreach($this->forwards[$source] as $target => $junk) {
			if (!$public && $target == $nick) {
				continue;
			}

			$event->getServer()->doPrivmsg($target, $msg);
		}
	}

	public function onNotice(IrcEvent $event)
	{
		if (empty($this->forwards['msgs'])) {
			return;
		}

		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$msg = '['. $hostmask->toString() .'] '. $event->getParam(1);
		foreach($this->forwards['msgs'] as $target => $junk) {
			if ($target == $nick) {
				continue;
			}

			$event->getServer()->doPrivmsg($target, $msg);
		}
	}
}
