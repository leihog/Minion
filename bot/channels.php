<?php
namespace Bot;
use Bot\Bot as Bot;
use Bot\Irc\Channel as Channel;

class Channels
{
	protected $channels;

	public function on315( \Bot\Event\Irc $event )
	{
		$tmp = explode(' ', $event->getParam(0));
		$channel = array_shift($tmp);

		if (isset($this->channels[$channel])) {
			$this->channels[$channel]->setSyncing(false);
		}
	}

	public function on352(\Bot\Event\Irc $event)
	{
		list($channel, $ident, $host, $server, $nick, $modes, $hopCount, $realname) = explode(' ', $event->getParam(0));
		if (!isset($this->channels[$channel])) {
			return; // perhaps we should add the channel instead.
		}

		$channel = $this->channels[$channel];
		if (!$channel->isSyncing()) {
			$channel->setSyncing(true);
		}

		$channel->addUser(new \Bot\Hostmask( "{$nick}!{$ident}@{$host}"));
	}

	public function onJoin( \Bot\Event\Irc $event )
	{
		$channel = $event->getParam(0);
		$hostmask = $event->getHostmask();
		$nick = $event->getHostmask()->getNick();

		if ($nick == $event->getServer()->getNick()) {
			$chanObj = new Channel($channel);
			$this->channels[$channel] = $chanObj;
			/* $this->channels[$channel] = array( */
			/* 	'resync' => true, */
			/* 	'users' => array(), */
			/* ); */

			$event->getServer()->doRaw("WHO $channel");
		} else {
			/* $this->channels[$channel]['users'][$hostmask->getNick()] = $hostmask; */
			$this->channels[$channel]->addUser($hostmask);
		}
	}

    /**
     * @param unknown_type $event
     */
    public function onKick( \Bot\Event\Irc &$event )
	{
		$chan = $event->getParam(0);
		$nick = $event->getParam(1);

		if (!isset($this->channels[$chan])) {
			return;
		}

		$channel = $this->channels[$chan];
		if (!$channel->hasUser($nick)) {
			return;
		}

		$hostmask = $channel->getUser($nick);
		$event->setParam('target', $hostmask);
		$channel->removeUser($hostmask);
	}

    public function onNick( \Bot\Event\Irc $event )
    {
        $hostmask = $event->getHostmask();
        $newNick = $event->getParam(0);
		$newHostmask = clone $hostmask;
		$newHostmask->setNick($newNick);

        $channels = array();
        foreach($this->channels as $channel) {
			if (!$channel->hasUser($hostmask->getNick())) {
				continue;
			}

			$channels[] = $channel->getName();
			$channel->removeUser($hostmask);
			$channel->addUser($newHostmask);
            /* if ( isset($this->channels[$chan]['users'][$nick]) ) */
            /* { */
            /*     $channels[] = $chan; */
            /*     $this->channels[$chan]['users'][$newNick] = $this->channels[$chan]['users'][$nick]; */
            /*     unset($this->channels[$chan]['users'][$nick]); */
            /* } */
        }

        $event->setChannels($channels);
    }

	public function onPart( \Bot\Event\Irc $event )
	{
		$channel = $event->getParam(0);
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();

		if ($nick == $event->getServer()->getNick()) {
			unset($this->channels[$channel]);
			return;
		}

		$this->channels[$channel]->removeUser($hostmask);
		/* else if ( isset($this->channels[$channel]['users'][$nick]) ) { */
            /* unset($this->channels[$channel]['users'][$nick]); */
        /* } */
    }

	/**
	 * Remove the records for $nick and adds the affected channels to the event.
	 *
	 * @param \Bot\Event\Irc $event
	 */
	public function onQuit( \Bot\Event\Irc &$event )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();

		$channels = array();
		foreach($this->channels as $channel) {
			if ($channel->hasUser($nick)) {
				$channel->removeUser($hostmask);
				$channels[] = $channel->getName();
			}
		}

		$event->setChannels($channels);
	}

	public function get($channel)
	{
		if (isset($this->channels[$channel])) {
			return $this->channels[$channel];
		}
		return null;
	}
}
