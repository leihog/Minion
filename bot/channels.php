<?php
namespace Bot;
use Bot\Bot as Bot;

class Channels
{
    protected $channels;

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

		if ( $nick == $event->getServer()->getNick() )
		{
			$this->channels[$channel] = array(
				'resync' => true,
				'users' => array(),
			);

			$event->getServer()->doRaw("WHO $channel");
		}
		else
		{
			$this->channels[$channel]['users'][$hostmask->getNick()] = $hostmask;
		}
	}

    /**
     * @param unknown_type $event
     */
    public function onKick( \Bot\Event\Irc $event )
	{
		$chan = $event->getParam(0);
		$target = $event->getParam(1);
		if ( !isset($this->channels[$chan]['users'][$target]) )
		{
			return;
		}

		$target = $this->channels[$chan]['users'][$target];
		$event->setParam('target', $target);
		unset($this->channels[$chan]['users'][$target]);
    }

    public function onNick( \Bot\Event\Irc $event )
    {
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
        $newNick = $event->getParam(0);

        $channels = array();
        foreach( array_keys($this->channels) as $chan )
        {
            if ( isset($this->channels[$chan]['users'][$nick]) )
            {
                $channels[] = $chan;
                $this->channels[$chan]['users'][$newNick] = $this->channels[$chan]['users'][$nick];
                unset($this->channels[$chan]['users'][$nick]);
            }
        }

        $event->setChannels( $channels );
    }

    public function onPart( \Bot\Event\Irc $event )
    {
        $channel = $event->getParam(0);
        $nick = $event->getHostmask()->getNick();

        if ( $nick == $event->getServer()->getNick() )
        {
            unset($this->channels[$channel]);
        }
        else if ( isset($this->channels[$channel]['users'][$nick]) )
        {
            unset($this->channels[$channel]['users'][$nick]);
        }
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
        foreach( array_keys($this->channels) as $chan )
        {
            if ( isset($this->channels[$chan]['users'][$nick]) )
            {
                $channels[] = $chan;
                unset($this->channels[$chan]['users'][$nick]);
            }
        }

        $event->setChannels( $channels );
    }

    /**
     * Returns a list of channels that $nick is on.
     *
     * @param string $nick
     */
    public function getChannels($nick)
    {
        $channels = array();
        foreach( array_keys($this->channels) as $chan )
        {
            if ( isset($this->channels[$chan]['users'][$nick]) )
            {
                $channels[] = $chan;
            }
        }

        return $channels;
    }

    /**
     * If bot is not on $channel false is returned.
     * Returns true if $nick is set and if $nick is on $channel
     *
     * @param string $channel
     * @param string $nick
     */
    public function isOn($channel, $nick = false )
    {
        if ( !isset($this->channels[$channel]) )
        {
            return false;
        }

        if ( $nick && !isset($this->channels[$channel]['users'][$nick]) )
        {
            return false;
        }

        return true;
    }

    public function isSyncing( $chan )
    {
        if ( isset($this->channels[$chan]['resync']) && $this->channels[$chan]['resync'] )
        {
            return true;
        }

        return false;
    }

    public function getUsers( $channel )
    {
        if ( isset($this->channels[$channel]) )
        {
            return $this->channels[$channel]['users'];
        }

        return array();
    }
}
