<?php
namespace Bot\Plugin;
use Bot\Bot;

class Seen extends Plugin
{
    const ACTION_JOIN = 1;
    const ACTION_PART = 2;
    const ACTION_QUIT = 3;
    const ACTION_KICK = 4;
    const ACTION_NICK = 5;

    public function init()
    {
        if ( !Bot::getDatabase()->isInstalled($this->getName()) )
        {
            echo "Installing plugin ", $this->getName(), "\n";
            $db = Bot::getDatabase();
            $db->install( $this->getName(), __DIR__ . '/seen.schema' );
        }
    }

    public function onJoin( \Bot\Event\Irc $event )
    {
        $hostmask = $event->getHostmask();
        if ( $hostmask->getNick() != $this->getNick() )
        {
            $this->registerAction( $hostmask, $event->getParam(0), self::ACTION_JOIN );
        }
    }

    public function onKick( \Bot\Event\Event $event )
    {

    }

    public function onNick( \Bot\Event\Irc $event )
    {
        $hostmask = $event->getHostmask();
        $newNick = $event->getParam(1); /** @todo make sure that this is still correct */
        $channels = $event->getChannels();

        $newHostmask = new \Bot\Hostmask("{$newNick}!{$hostmask->getUsername()}@{$hostmask->getHost()}");

        foreach( $channels as $chan )
        {
            $this->registerAction( $hostmask, $chan, self::ACTION_PART );
            $this->registerAction( $newHostmask, $chan, self::ACTION_JOIN );
        }
    }

    public function onPart( \Bot\Event\Irc $event )
    {
        $hostmask = $event->getHostmask();
        if ( $hostmask->getNick() != $this->getNick() )
        {
            $this->registerAction( $hostmask, $event->getParam(0), self::ACTION_PART );
        }
    }

    public function onQuit( \Bot\Event\Irc $event )
    {
        $hostmask = $event->getHostmask();
        $channels = $event->getChannels();

        if ( $hostmask->getNick() != $this->getNick() )
        {
            foreach( $channels as &$channel )
            {
                $this->registerAction( $hostmask, $channel, self::ACTION_QUIT );
            }
        }
    }

    public function cmdSeen( \Bot\Event\Irc $event, $user )
    {
        $cmpuser = strtolower($user);
        $source = $event->getSource();
        $nick = $event->getHostmask()->getNick();
        $channel = ( $event->isFromChannel() ? $source : false);
        $prefix = ( $channel ? "{$nick}: " : "" );

        if ( $cmpuser == strtolower($this->getNick()) )
        {
            $this->doPrivmsg($source, $prefix . "I'm over here!");
            return;
        }

        if ( $cmpuser == strtolower($nick) )
        {
            $msgs = array(
                "I'm looking right at you.",
                'I see you.',
                'Lost yourself again?',
            );

            $this->doPrivmsg($source, $prefix . $msgs[array_rand($msgs)]);
            return;
        }

        if ( Bot::getPluginHandler()->getPlugin('channel')->isOn($user, $channel) )
        {
            $this->doPrivmsg($source, "I see {$user} right now.");
            return;
        }

        $db = Bot::getDatabase();

        if ( $channel )
        {
            $r = $db->fetch("SELECT channel, added FROM seen WHERE channel = :channel AND nick = :user ORDER BY id DESC LIMIT 1", compact("channel", "cmpuser"));
        }
        else
        {
            $r = $db->fetch("SELECT channel, added FROM seen WHERE nick = :user ORDER BY id DESC LIMIT 1", compact("channel", "cmpuser"));
        }

        if (!$r)
        {
            $this->doPrivmsg($source, $prefix . "I don't remember seeing {$user}.");
            return;
        }

        $msg = "{$user} was last seen ";
        if (!$channel)
        {
            $msg .= "on {$r['channel']} ";
        }
        $msg .= $this->formatTimestamp($r['added']) . ' ago.';

        $this->doPrivmsg($source, $prefix . $msg);
    }

    protected function registerAction( \Bot\Hostmask $hostmask, $channel, $action )
    {
        $params = array(
            'nick' => $hostmask->getNick(),
            'hostmask' => $hostmask->toString(),
            'channel' => $channel,
            'action' => $action,
            'added' => time()
        );

        $db = Bot::getDatabase();
        $r = $db->execute("INSERT INTO seen (nick, hostmask, channel, action, added) VALUES (:nick, :hostmask, :channel, :action, :added)", $params);
        if (!$r)
        {
            echo "registerAction failed...\n";
        }
    }

    public function formatTimestamp($timestamp)
    {
        $time = (time() - $timestamp);
        $return = array();

        $days = floor($time / 86400);
        if ($days > 0)
        {
            $return[] = $days . 'd';
            $time %= 86400;
        }

        $hours = floor($time / 3600);
        if ($hours > 0)
        {
            $return[] = $hours . 'h';
            $time %= 3600;
        }

        $minutes = floor($time / 60);
        if ($minutes > 0)
        {
            $return[] = $minutes . 'm';
            $time %= 60;
        }

        if ($time > 0 || count($return) <= 0)
        {
            $return[] = ($time > 0 ? $time : '0') . 's';
        }

        return implode(' ', $return);
    }

}