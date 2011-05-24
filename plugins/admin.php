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

	public function cmdLoad( \Bot\Event\Irc $event, $plugin )
	{
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();

	    if ($event->isFromChannel())
	    {
	        $this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() . ' LOAD plugin');
	        return;
	    }

	    if ( Bot::getPluginHandler()->hasPlugin($plugin) )
	    {
	        $this->doPrivmsg($nick, "Plugin '{$plugin}' is already loaded.");
	        return;
	    }

	    if ( Bot::getPluginHandler()->loadPlugin($plugin) )
	    {
	        $this->doPrivmsg($nick, "Loaded plugin '{$plugin}'.");
	    }
	    else
	    {
	        $this->doPrivmsg($nick, "Unable to load plugin '{$plugin}'.");
	    }
	}

	public function cmdMemstat( \Bot\Event\Irc $event )
	{
	    $source = $event->getSource();

        $size = memory_get_usage();
        $unit = array('b','kb','mb','gb','tb','pb');
        $memusage = @round($size/pow(1024,($i=floor(log($size ,1024)))),2).' '.$unit[$i];
        $this->doPrivmsg($source, "Mem usage: {$memusage}");

        $size = memory_get_usage(true);
        $unit = array('b','kb','mb','gb','tb','pb');
        $memusage = @round($size/pow(1024,($i=floor(log($size ,1024)))),2).' '.$unit[$i];
        $this->doPrivmsg($source, "Mem usage(true): {$memusage}");
	}

	public function cmdPlugins( \Bot\Event\Irc $event )
	{
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();

	    if ($event->isFromChannel())
	    {
	        $this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() . ' PLUGINS');
	        return;
	    }

	    $plugins = Bot::getPluginHandler()->getPlugins();
	    if ( ($pluginCount = count($plugins)) )
		{
		    $this->doPrivmsg($nick, sprintf('%s loaded plugin%s', $pluginCount, ($pluginCount == 1 ? '':'s') ));
		    $this->doPrivmsg($nick, $this->formatTableArray( $plugins, "%-10s", 4, 15 ));
		}
	}

	public function cmdReload( \Bot\Event\Irc $event, $plugin, $force = false )
	{
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();

	    if ($event->isFromChannel())
	    {
	        $this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() . ' RELOAD plugin');
	        return;
	    }

	    $handler = Bot::getPluginHandler();
	    if ( $handler->hasPlugin($plugin) )
	    {
            $pluginClassName = get_class($handler->getPlugin($plugin));
    	    if ( Bot::getPluginHandler()->reloadPlugin($plugin, $force) )
    	    {
    	        $this->doPrivmsg($nick, "Reloaded plugin '{$plugin}'.");
    	    }
    	    else
    	    {
    	        if ( !$force && ($pluginClassName == get_class($handler->getPlugin($plugin))) )
    	        {
    	            $this->doPrivmsg($nick, "Plugin '{$plugin}' has not changed since it was loaded.");
    	        }
    	        else
    	        {
    	            $this->doPrivmsg($nick, "Failed to reload '{$plugin}'.");
    	        }
    	    }
	    }
	}

	public function cmdShutdown()
	{
	    $this->doQuit('Shutting down...');
	    Bot::getInstance()->shutdown();
	}

	public function cmdUnload( \Bot\Event\Irc $event, $plugin )
	{
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();

	    if ($event->isFromChannel())
	    {
	        $this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() . ' UNLOAD plugin');
	        return;
	    }

	    Bot::getPluginHandler()->unloadPlugin($plugin);
	    $this->doPrivmsg($nick, "Unloaded plugin '{$plugin}'.");
	}

}