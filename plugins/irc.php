<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Command;

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

    	$channels = Bot::getConfig("plugins/channel/autojoin", array());
    	if ( !empty($channels) )
    	{
			$this->doJoin($channels);
		}
    }

    public function on433( \Bot\Event\Irc $event )
    {
        list($nick, $desc) = explode(' :', $event->getParam(0), 2);

        $this->altnicks = Bot::getConfig('plugins/irc/altnicks', false);
        if ( !$this->altnicks || !current($this->altnicks) )
        {
            $newnick = $nick . date('s');
        }
        else
        {
            $newnick = current($this->altnicks);
            next($this->altnicks);
        }

        $this->doNick( $newnick );
    }

    public function onConnect( \Bot\Event\Socket $event )
    {
        $host = $event->getHost();
        $irc = Bot::getConfig('irc');

        $this->doNick( $irc['nick'] );
        $this->doUser( $irc['username'], $irc['realname'], $host );
    }

}