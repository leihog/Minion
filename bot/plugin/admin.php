<?php
namespace Bot\Plugin;
use Bot\Bot;
/**
 * Administrator commands.
 * 
 * Warning: You might not want to run this plugin in a live environment!
 */
class Admin extends Plugin
{
    public function init()
    {
        echo "Warning: in the wrong hands this plugin can wreak havoc!\n";
    }

	public function cmdLoad( \Bot\Command $cmd, $plugin )
	{
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
	    $server = $cmd->getConnection();

	    if ($event->isFromChannel())
	    {
	        $server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' LOAD plugin');
	        return;
	    }

	    if ( Bot::getPluginHandler()->hasPlugin($plugin) )
	    {
	        $server->doPrivmsg($nick, "Plugin '{$plugin}' is already loaded.");
	        return;
	    }

	    if ( Bot::getPluginHandler()->loadPlugin($plugin) )
	    {
	        $server->doPrivmsg($nick, "Loaded plugin '{$plugin}'.");
	    }
	    else
	    {
	        $server->doPrivmsg($nick, "Unable to load plugin '{$plugin}'.");
	    }
	}

	public function cmdMemstat( \Bot\Command $cmd )
	{
        $size = memory_get_usage();
        $unit = array('b','kb','mb','gb','tb','pb');
        $memusage = @round($size/pow(1024,($i=floor(log($size ,1024)))),2).' '.$unit[$i];        
        $cmd->respond("Mem usage: {$memusage}");

        $size = memory_get_usage(true);
        $unit = array('b','kb','mb','gb','tb','pb');
        $memusage = @round($size/pow(1024,($i=floor(log($size ,1024)))),2).' '.$unit[$i];
        $cmd->respond("Mem usage(true): {$memusage}");
	}

	public function cmdReload( \Bot\Command $cmd, $plugin, $force = false )
	{
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
	    $server = $cmd->getConnection();

	    if ($event->isFromChannel())
	    {
	        $server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' RELOAD plugin');
	        return;
	    }

	    $handler = Bot::getPluginHandler();
	    if ( $handler->hasPlugin($plugin) )
	    {
            $pluginClassName = get_class($handler->getPlugin($plugin));
    	    if ( Bot::getPluginHandler()->reloadPlugin($plugin, $force) )
    	    {
    	        $server->doPrivmsg($nick, "Reloaded plugin '{$plugin}'.");
    	    }
    	    else
    	    {
    	        if ( !$force && ($pluginClassName == get_class($handler->getPlugin($plugin))) )
    	        {
    	            $server->doPrivmsg($nick, "Plugin '{$plugin}' has not changed since it was loaded.");
    	        }
    	        else
    	        {
    	            $server->doPrivmsg($nick, "Failed to reload '{$plugin}'.");
    	        }
    	    }
	    }
	}

	public function cmdUnload( \Bot\Command $cmd, $plugin )
	{
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
	    $server = $cmd->getConnection();

	    if ($event->isFromChannel())
	    {
	        $server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' UNLOAD plugin');
	        return;
	    }

	    Bot::getPluginHandler()->unloadPlugin($plugin);
	    $server->doPrivmsg($nick, "Unloaded plugin '{$plugin}'.");
	}
    
}