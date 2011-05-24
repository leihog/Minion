<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Puppet extends Plugin
{
    public function cmdJoin( \Bot\Event\Irc $event, $channel, $key = '' )
    {
        $this->doJoin($channel, $key);
    }

    public function cmdPart( \Bot\Event\Irc $event, $channel )
    {
        $this->doPart($channel);
    }

    public function cmdQuit( \Bot\Event\Irc $event, $msg = 'zZz' )
    {
        $this->doQuit( $msg );
    }

    public function cmdRaw( \Bot\Event\Irc $event, $raw )
    {
        //echo "sending: ", $raw, "\n";
        $this->doRaw( $raw );
    }
}