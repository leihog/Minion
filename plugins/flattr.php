<?php

namespace Bot\Plugin;

use Bot\Bot as Bot;
use Bot\Event\Irc as IrcEvent;

class Flattr extends Plugin
{
	public function onPrivmsg(IrcEvent $event)
	{
		$input = $event->getParam(1);
		$pattern = '@https?://flattr\.com/(?:t|thing)/([0-9]+)(?:/.*)?@i';
		if (!preg_match($pattern, $input, $matches)) {
			return;
		}

		$result = $this->getUrl("https://api.flattr.com/rest/v2/things/{$matches[1]}");
		if ($result) {
			$result = json_decode($result, true);
			if (isset($result['title'])) {
				$title = html_entity_decode($result['title']);
				$event->respond("[{$result['flattrs']}/{$result['flattrs_user_count']}] {$title} ({$result['url']})");
			}
		}
	}

}
