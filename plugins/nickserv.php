<?php

namespace Bot\Plugin;

use Bot\Config as Config;
use Bot\Bot as Bot;
use Bot\Event\Irc as IrcEvent;

class NickServ extends Plugin
{
	protected $config;
	protected $defaultConfig = array(
		'freequest' => array(
			'hostmask' => 'service@FreeQuest.net',
			'trigger' => 'This nickname is registered. Please choose a different nickname',
		),
	);

	public function init()
	{
		$this->config = array_merge_recursive($this->defaultConfig, Config::get('plugins/nickserv', []));
	}

	public function onNotice(IrcEvent $event)
	{
		$server = $event->getServer();
		$network = strtolower($server->getNetwork());

		if (!isset($this->config[$network])) {
			return;
		}

		$hostmask = $event->getHostmask();
		if (!$hostmask) {
			return;
		}

		$cfg = $this->config[$network];
		if (empty($cfg['password']) || $cfg['hostmask'] != $hostmask->toString()) {
			return;
		}

		$msg = $event->getParam(1);
		if (stristr($msg, $cfg['trigger']) !== false) {
			$server->doPrivmsg($hostmask->getNick(), "IDENTIFY {$cfg['password']}");
		}
	}
}
