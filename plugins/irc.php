<?php
namespace Bot\Plugin;

use Bot\Config as Config;
use Bot\Bot as Bot;
use Bot\Event\Event as Event;

/**
 *
 * This plugin is the bare minimum required for the bot to be able to connect to a server and join a channel.
 *
 */
class Irc extends Plugin
{
	protected $altnicks;

	public function onStarted(Event $event)
	{
		// connect to irc networks
		$networks = Config::get('plugins/irc/networks');
		foreach($networks as $network) {
			$adapter = new \Bot\adapter\Stream\Client();
			$server = new \Bot\Connection\Server($network, $adapter);

			if ($server->connect()) {
				Bot::log("Connected to {$server->getHost()}:{$server->getPort()}");
			}
		}
	}

	public function on001( \Bot\Event\Irc $event )
	{
		if (!empty($this->altnicks)) {
			reset($this->altnicks);
		}

		$server = $event->getServer();
		$config = $server->getConfig();
		$channels = $config['channels'];
		if (!empty($channels)) {
			$server->doJoin($channels);
		}
	}

	/** @todo altnicks should be part of the network config */
	public function on433( \Bot\Event\Irc $event )
	{
		list($nick, $desc) = explode(' :', $event->getParam(0), 2);

		if ( !isset($this->altnicks) ) {
			$this->altnicks = Config::get('plugins/irc/altnicks', false);
		}

		if ( !$this->altnicks || current($this->altnicks) === false ) {
			$newnick = $nick . date('s');
		} else {
			$newnick = current($this->altnicks);
			next($this->altnicks);
		}

		$event->getServer()->doNick( $newnick );
	}

	public function onConnect(\Bot\Event\Irc $event)
	{
		$server = $event->getServer();

		$server->doNick($server->getNick());
		$server->doUser($server->getUsername(), $server->getRealname(), $server->getHost() );
	}
}
