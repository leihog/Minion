<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Irc extends Plugin 
{
    protected $altnicks;

    public function on001( \Bot\Event\Irc $event )
    {
    	$server = $event->getConnection();

    	$channels = Bot::getConfig("irc/channels", array());
    	if ( !empty($channels) )
    	{
			$server->doJoin($channels);
		}

        if (!empty($this->altnicks))
        {
            reset($this->altnicks);
        }
    }
    
    public function on433( \Bot\Event\Irc $event )
    {
        list($nick, $desc) = explode(' :', $event->getParams(), 2);
        
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

        $event->getConnection()->doNick( $newnick );
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

		$server = $event->getConnection();
		$server->send( $server->prepare('PONG', $args[0]) );
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

        $cmd = new \Bot\Command( $cmdName, $parameters );
        $cmd->setEvent($event);
        $cmd->execute();
    }
    
    public function cmdHello( \Bot\Command $cmd )
    {
        $cmd->getConnection()->doPrivmsg($cmd->getEvent()->getSource(), "Hello, how are you?");
    }

    public function cmdQuit( \Bot\Command $cmd, $msg = 'zZz' )
    {
        /** @todo output "by request of $nick." */
        $cmd->getConnection()->doQuit( $msg );
    }

}