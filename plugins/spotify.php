<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Spotify extends Plugin
{
	protected $popularityResponses = array(
		"high" => array(
			"that track is da bomb!",
			"One of my favorite tracks",
			"bit too mainstream if you ask me",
			"most bands start to suck when they get popular.",
		),
		"low" => array(
			"ego driven drivel *yawn*",
			"you know, there is no shortage of good music these days.",
			"we all want to believe that our judgment of music is pure, but music is more than just a bunch of sounds.",
		),
	);

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

	public function parseResponse($data)
	{
		if (!isset($data['info']['type'])) {
			return;
		}

		switch($data['info']['type']) {
		case 'artist':
		case 'album':
			return null;

		case 'track':
			$track = $data['track']['name'];
			$artist = array_shift($data['track']['artists']);

			$response = ["track {$track} by {$artist['name']} (spotify)"];
			$popularity = $this->popularityResponse($data['track']['popularity']);
			if ($popularity) {
				$response[] = $popularity;
			}

			return $response;
		default:
			return null;
		}
	}

	public function popularityResponse($popularity)
	{
		if (mt_rand(1, 100) >= 33) {
			return; // don't respond to often
		}

		$popularity = 100 * $popularity;
		switch($popularity) {
		case $popularity >= 85:
			$index = array_rand($this->popularityResponses['high']);
			$response = $this->popularityResponses['high'][$index];
			break;
		case $popularity <= 25:
			$index = array_rand($this->popularityResponses['low']);
			$response = $this->popularityResponses['low'][$index];
			break;
		default:
			$response = "";
		}

		return $response;
	}
}
