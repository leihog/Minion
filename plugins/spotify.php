<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Spotify extends Plugin
{
	public function onPrivmsg($event)
	{
		if (!preg_match("@spotify[^\s]+(track|album|artist)[:/]{1}([a-z0-9]{22})@i", $event->getParam(1), $match)) {
			return;
		}

		$uri = "http://ws.spotify.com/lookup/1/.json?uri=spotify:{$match[1]}:{$match[2]}";
		$result = $this->getUrl($uri);
		if (!$result) {
			return;
		}

		$response = $this->parseResponse(json_decode($result, true));
		if (!$response) {
			return;
		}
		$event->respond($response);
	}

	public function parseResponse($response)
	{
		if (!isset($response['info']['type'])) {
			return;
		}

		switch($response['info']['type']) {
		case 'artist':

			break;

		case 'album':
			break;

		case 'track':
			$track = $response['track']['name'];
			$artist = array_shift($response['track']['artists']);
			return "{$track} by {$artist['name']}";
		}
	}
}
