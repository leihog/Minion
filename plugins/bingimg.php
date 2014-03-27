<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;
use Bot\Config as Config;

class BingImg extends Plugin
{
	protected function imageSearch($query, $maxResults) {
		$key = Config::get("plugins/bingimg/key", false);
		if (!$key) {
			Bot::log("No Bing API key has been specified.");
			return [];
		}

		$url = 'https://api.datamarket.azure.com/Bing/Search/v1/Image?$format=json&$top='. $maxResults .'&Query=%27'. $query .'%27';
		$process = curl_init($url);
		curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($process, CURLOPT_USERPWD, "username:{$key}");
		curl_setopt($process, CURLOPT_TIMEOUT, 30);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($process);
		curl_close($process);

		return json_decode($response, true);
	}

	public function onPrivmsg($event) {
		$input = $event->getParam(1);
		if (preg_match("|#([a-z0-9]+)|i", $input, $m)) {
			Bot::log("[bingimg] Got a match on {$m[1]}");
			$query = $m[1];

			$result = $this->imageSearch($query, 50);
			$result = array_path($result, "d/results", []);
			if (($index = array_rand($result)) === null) {
				Bot::log("[bingimg] empty search result for query: '$query'.");
				return;
			}
			
			$result = $result[$index]['MediaUrl'];
			$event->respond($result);
		}
	}
}
