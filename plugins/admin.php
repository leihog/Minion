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
		Bot::log("Warning: in the wrong hands this plugin can wreak havoc!");
	}

	public function cmdLoad( \Bot\Event\Irc $event, $plugin )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$server = $event->getServer();

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

	public function cmdMemstat( \Bot\Event\Irc $event )
	{
		$source = $event->getSource();
		$server = $event->getServer();

		$size = memory_get_usage();
		$unit = array('b','kb','mb','gb','tb','pb');
		$memusage = @round($size/pow(1024,($i=floor(log($size ,1024)))),2).' '.$unit[$i];
		$server->doPrivmsg($source, "Mem usage: {$memusage}");

		$size = memory_get_usage(true);
		$unit = array('b','kb','mb','gb','tb','pb');
		$memusage = @round($size/pow(1024,($i=floor(log($size ,1024)))),2).' '.$unit[$i];
		$server->doPrivmsg($source, "Mem usage(true): {$memusage}");
	}

	public function cmdPlugins( \Bot\Event\Irc $event )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$server = $event->getServer();

		if ($event->isFromChannel())
		{
			$server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' PLUGINS');
			return;
		}

		$plugins = Bot::getPluginHandler()->getPlugins();
		if ( ($pluginCount = count($plugins)) )
		{
			$server->doPrivmsg($nick, sprintf('%s loaded plugin%s', $pluginCount, ($pluginCount == 1 ? '':'s') ));
			$server->doPrivmsg($nick, $this->formatTableArray( $plugins, "%-10s", 4, 15 ));
		}
	}

	public function cmdReload( \Bot\Event\Irc $event, $plugin, $force = false )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$server = $event->getServer();

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

	public function cmdShutdown( \Bot\Event\Irc $event, $msg = 'Matane' )
	{
		Bot::shutdown($msg);
	}

	public function cmdUnload( \Bot\Event\Irc $event, $plugin )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();

		if ($event->isFromChannel())
		{
			$event->getServer()->doPrivmsg($nick, 'Syntax: /msg '. $event->getServer()->getNick() . ' UNLOAD plugin');
			return;
		}

		Bot::getPluginHandler()->unloadPlugin($plugin);
		$event->getServer()->doPrivmsg($nick, "Unloaded plugin '{$plugin}'.");
	}

	public function cmdUptime( \Bot\Event\Irc $event )
	{
		$uptime = "My uptime is: ". Bot::uptime();
		$event->getServer()->doPrivmsg($event->getSource(), $uptime);
	}
}
