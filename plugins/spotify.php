<?php
/*
 * Lookup spotify urls using the open lookup API 
 * @see https://developer.spotify.com/technologies/web-api/lookup/
 *
 * Config options:
 *   opinionated: value from 0-100, represents the chance of responding with an opinion.
 *
 * Example:
 *  Dude:   have you heard http://open.spotify.com/track/6iHHu7yp9R9Tey6cb8hP4K
 *  Minion: track Cardiac Rhythm by Snowgoons (spotify)
 *  Minion: One of my favorite tracks // If configured to have an opinion
 */
namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Config as Config;

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

	protected function extractTrack($data)
	{
		$track = [];
		if (isset($data['name'])) {
			if (!preg_match("@spotify:track:([a-z0-9]{22})@i", $data['href'], $match)) {
				return null;
			}

			$track['id']  = $match[1];
			$track['url'] = "http://open.spotify.com/track/". $match[1];
			$track['name'] = $data['name'];

			$artists = [];
			foreach($data['artists'] as $artist) {
				$artists[] = $artist['name'];
			}
			$track['artist'] = implode(", ", $artists);
		}

		return $track;
	}

	public function cmdFindsong($event, $query)
	{
		if (!$query) {
			return;
		}

		$url = "http://ws.spotify.com/search/1/track.json?q={$query}";
		$result = $this->getUrl($url);
		$result = json_decode($result, true);
		$index = array_rand($result["tracks"]);

		$track = $this->extractTrack($result["tracks"][$index]);
		$response = "{$track['name']} by {$track['artist']} ({$track['url']})";
		$event->respond($response);
	}

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

			$opinionated = Config::get('plugins/spotify/opinionated', 50);
			if ($opinionated) {
				if (mt_rand(1, 100) <= $opinionated) {
					$response[] = $this->popularityResponse($data['track']['popularity']);
				}
			}

			return $response;
		default:
			return null;
		}
	}

	public function popularityResponse($popularity)
	{
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
