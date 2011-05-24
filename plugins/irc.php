<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Command;

class Irc extends Plugin
{
    protected $altnicks;

    public function on001( \Bot\Event\Irc $event )
    {
        if (!empty($this->altnicks))
        {
            reset($this->altnicks);
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
        $this->getServer()->send( $this->prepare('USER', $irc['username'], $host, $host, $irc['realname']) );
    }

    /**
     * @todo this should probably be handled in \Bot\Server since it's so essential
     * @param \Bot\Event\Irc $event
     */
    public function onPing( \Bot\Event\Irc $event )
    {
    	$args = $event->getParams();
    	if (empty($args))
    	{
    		return;
    	}

		$this->getServer()->send( $this->prepare('PONG', $args[0]) );
    }

    public function onPrivmsg( \Bot\Event\Irc $event )
    {
        list($source, $input) = $event->getParams();
        if ($event->isFromChannel() && $input[0] != '!')
        {
            return;
        }

        list($cmdName, $parameters) = array_pad( explode(' ', $input, 2), 2, null );
        if ( $cmdName[0] == '!' )
        {
            $cmdName = substr($cmdName, 1);
        }

        if ( !Command::has($cmdName) )
        {
            if ( !$event->isFromChannel() )
            {
                $this->doPrivmsg($event->getSource(), 'What?');
            }

            return;
        }

        Command::execute( $event, $cmdName, $parameters );
    }

    public function cmdHello( \Bot\Event\Irc $event )
    {
        $this->doPrivmsg($event->getSource(), "Hello, how are you?");
    }

}