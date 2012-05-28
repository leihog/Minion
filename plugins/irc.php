<?php
namespace Bot\Plugin;
use Bot\Config as Config;

/**
 *
 * This plugin is the bare minimum required for the bot to be able to connect to a server and join a channel.
 *
 */
class Irc extends Plugin
{
	protected $altnicks;

	public function on001( \Bot\Event\Irc $event )
	{
		if (!empty($this->altnicks))
		{
			reset($this->altnicks);
		}

		$channels = Config::get("plugins/channel/autojoin", array());
		if ( !empty($channels) )
		{
			$event->getServer()->doJoin($channels);
		}
	}

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

	public function onConnect( \Bot\Event\Irc $event )
	{
		$server = $event->getServer();
		$irc = Config::get('irc');

		$server->doNick( $irc['nick'] );
		$server->doUser( $irc['username'], $irc['realname'], $server->getHost() );
	}
}
