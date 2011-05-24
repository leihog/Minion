<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Channel extends Plugin
{
    protected $channels;

    public function on001( \Bot\Event\Irc $event )
    {
    	$channels = Bot::getConfig("plugins/channel/autojoin", array());
    	if ( !empty($channels) )
    	{
			$this->doJoin($channels);
		}
    }

    public function on315( \Bot\Event\Irc $event )
    {
        $channel = array_shift(explode(' ', $event->getParam(0)));
        $this->channels[$channel]['resync'] = false;
    }

    public function on352( \Bot\Event\Irc $event )
    {
        list($channel, $ident, $host, $server, $nick, $modes, $hopCount, $realname) = explode(' ', $event->getParam(0));

        if ( !$this->channels[$channel]['resync'] )
        {
            $this->channels[$channel]['resync'] = true;
            $this->channels[$channel]['users'] = array();
        }

        $this->channels[$channel]['users'][$nick] = new \Bot\Hostmask( "{$nick}!{$ident}@{$host}" );
    }

    public function onJoin( \Bot\Event\Irc $event )
    {
        $channel = $event->getParam(0);
        $hostmask = $event->getHostmask();
        $nick = $event->getHostmask()->getNick();

        if ( $nick == $this->getNick() )
        {
            $this->channels[$channel] = array(
            	'resync' => true,
                'users' => array(),
            );

            $this->doRaw("WHO $channel");
        }
        else
        {
            $this->channels[$channel]['users'][$hostmask->getNick()] = $hostmask;
        }
    }

    public function onNick( \Bot\Event\Irc $event )
    {
        $nick = $event->getHostmask()->getNick();
        $newNick = $event->getParam(0);

        foreach( array_keys($this->channels) as $chan )
        {
            if ( isset($this->channels[$chan]['users'][$nick]) )
            {
                $this->channels[$chan]['users'][$newNick] = $this->channels[$chan]['users'][$nick];
                unset($this->channels[$chan]['users'][$nick]);
            }
        }
    }

    public function onPart( \Bot\Event\Irc $event )
    {
        $channel = $event->getParam(0);
        $nick = $event->getHostmask()->getNick();

        if ( $nick == $this->getNick() )
        {
            unset($this->channels[$channel]);
        }
        else if ( isset($this->channels[$channel]['users'][$nick]) )
        {
            unset($this->channels[$channel]['users'][$nick]);
        }
    }

    public function onQuit( \Bot\Event\Irc $event )
    {
        $nick = $event->getHostmask()->getNick();

        foreach( array_keys($this->channels) as $chan )
        {
            if ( isset($this->channels[$chan]['users'][$nick]) )
            {
                unset($this->channels[$chan]['users'][$nick]);
            }
        }
    }

	/**
	 * @todo don't show users on channels with modes +k +s unless user is on channel or user is bot admin
	 *
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

        if ( !isset($this->channels[$chan]) )
        {
            $this->doPrivmsg($nick, "I'm not watching that channel.");
            return;
        }

        if ( $this->channels[$chan]['resync'] )
        {
            $this->doPrivmsg($nick, "Channel is resynchronizing, try again in a little while...");
            return;
        }

        $usersEnabled = ( Bot::getPluginHandler()->hasPlugin('users') ? true : false );

        $users = $this->channels[$chan]['users'];
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

    public function isOn($nick, $channel)
    {
        if ( isset($this->channels[$channel]) && isset($this->channels[$channel]['users'][$nick]) )
        {
            return true;
        }

        return false;
    }
}