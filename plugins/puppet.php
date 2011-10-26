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

    public function cmdHello( \Bot\Event\Irc $event )
    {
        $this->doPrivmsg($event->getSource(), "Hello, how are you?");
    }

    public function cmdRaw( \Bot\Event\Irc $event, $raw )
    {
        $this->doRaw( $raw );
    }

	/**
	 * @todo don't show users on channels with modes +k +s unless user is on channel or user is bot admin
	 */
    public function cmdWho( \Bot\Event\Irc $event, $chan, $mode = 'compact' )
    {
        if ( $event->isFromChannel() )
        {
            return;
        }

        $nick = $event->getHostmask()->getNick();
        if ( !in_array($chan[0], array('#', '&', '!', '~', '+')) )
        {
            $chan = "#{$chan}";
        }

        $channelDaemon = Bot::getChannelDaemon();

        if ( !$channelDaemon->isOn($chan) )
        {
            $this->doPrivmsg($nick, "I'm not watching that channel.");
            return;
        }

        if ( $channelDaemon->isSyncing($chan) )
        {
            $this->doPrivmsg($nick, "Channel is resynchronizing, try again in a little while...");
            return;
        }

        $usersEnabled = ( Bot::getPluginHandler()->hasPlugin('users') ? true : false );

        $users = $channelDaemon->getUsers($chan);
        $userCount = count($users);

        $this->doPrivmsg($nick, sprintf('Showing %s user%s on %s', $userCount, ($userCount == 1 ? '':'s'), $chan ));

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

    		    $this->doPrivmsg($nick, $this->formatTableArray( $list, "%-10s", 4, 15 ));
        }

    }
}