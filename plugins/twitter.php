<?php

namespace Bot\Plugin;

use Bot\Bot;
use Bot\Config as Config;
use Bot\Event\Irc as IrcEvent;

class Twitter extends Plugin
{
	protected $consumerKey;
	protected $consumerSecret;
	protected $timeoutPeriod = 3600;

	protected $token = null;
	protected $disable = false;
	protected $channelAccess;

	public function init()
	{
		$this->consumerKey = Config::get("plugins/twitter/key", false);
		$this->consumerSecret = Config::get("plugins/twitter/secret", false);
		$this->timeoutPeriod = Config::get("plugins/twitter/timeout-period", 3600);

		if (!$this->consumerKey || !$this->consumerSecret) {
			Bot::log("Twitter: config settings 'key' & 'secret' are both required.");
			return false;
		}

		$this->configureChannels();
	}

	protected function configureChannels()
	{
		$restrictions = Config::get("plugins/twitter/channel-access", false);
		if (!$restrictions || !preg_match("/^(DENY|ALLOW)\s(.+)$/i", $restrictions, $m)) {
			$this->channelAccess = null;
			return;
		}

		$type = strtoupper($m[1]);
		$channels = explode(",", str_replace(' ', '', $m[2]));
		$this->channelAccess = [$type, $channels];
	}

	public function onPrivmsg(IrcEvent $event)
	{
		if ($this->channelAccess && $event->isFromChannel()) {
			$match = in_array($event->getSource(), $this->channelAccess[1]);
			if (($match && $this->channelAccess[0] == 'DENY') ||
				(!$match && $this->channelAccess[0] == 'ALLOW')) {

				return false;
			}
		}

		$input = $event->getParam(1);
		$pattern = '/https?:\/\/(mobile\.)?twitter\.com\/.*?\/status\/([0-9]+)/i';
		if (preg_match($pattern, $input, $matches)) {
			$tweet = $this->getTweet($matches[2]);
			if ($tweet) {
				$event->respond("{$tweet['username']}: `{$tweet['text']}´");
			} else {
				$event->respond("Unable to find that tweet...");
			}
		}
	}

	public function getTweet($id)
	{
		$url = "https://api.twitter.com/1.1/statuses/show.json?id={$id}&include_entities=false";
		$response = $this->apiGet($url);
		if (!$response) {
			return null;
		}

		$tweet = array(
			'username' => '@'. $response['user']['screen_name'],
			'text' => $this->decode($response['text']),
		);
		return $tweet;
	}

	protected function decode($str)
	{
		return html_entity_decode($str, ENT_HTML5, 'UTF-8');
	}

	protected function apiGet($url)
	{
		if ($this->disable && time() < $this->disable) {
			Bot::log("Twitter plugin is temporarily disabled");
			return null;
		}

		if (!$this->token) {
			$this->token = $this->getBearerToken();
			if (!$this->token) {
				return null;
			}
		}

		$retries = 0;
		while($retries++ <= 0) {
			$headers = ["Authorization: Bearer {$this->token}"];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$response = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if (!empty($response)) {
				$response = json_decode($response, true);
			}

			if ($code == 200) {
				break;
			}

			if ($retries > 1) {
				$this->disable = time() + $this->timeoutPeriod;
				Bot::log("Disabling twitter for {$this->timeoutPeriod} seconds.");
				break;
			}

			if ($code == 401) {
				$this->token = $this->getBearerToken();
				Bot::log("Retrying with new token...");
				continue;
			}

			// on any other error we give up right away.

			break;
		} // end while

		if ($response === false || $code != 200) {
			Bot::log("Got no response from twitter. ({$code})");
			return null;
		}

		return $response;
	}

	protected function getBearerToken() {
		$encoded_consumer_key = urlencode($this->consumerKey);
		$encoded_consumer_secret = urlencode($this->consumerSecret);

		$bearer_token = "{$encoded_consumer_key}:{$encoded_consumer_secret}";
		$base64_encoded_bearer_token = base64_encode($bearer_token);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.twitter.com/oauth2/token");
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic {$base64_encoded_bearer_token}"]);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		curl_close($ch);

		if ($response === false) {
			echo "Error {$code}", curl_error($ch), "\n";
			return null;
		}

		$body = json_decode($response, true);
		return $body['access_token'];
	}
}
