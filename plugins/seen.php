<?php
namespace Bot\Plugin;
use Bot\Bot;

class Seen extends Plugin
{

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

    }

    public function onNick( \Bot\Event\Irc $event )
    {
    }

    public function onPart( \Bot\Event\Irc $event )
    {
    }

    public function onQuit( \Bot\Event\Irc $event )
    {
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

        if ( Bot::getPluginHandler()->getPlugin('channel')->isOn($user, $channel) ) // if user is online right now, requires a method of determining if a user is on a channel
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