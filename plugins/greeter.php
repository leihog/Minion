<?php
namespace Bot\Plugin;

use Bot\Event\Irc as IrcEvent;
use Bot\Bot as Bot;
use Bot\User as User;

class Greeter extends Plugin
{
	const MAX_LENGTH = 100;

	public function init()
	{
		if (!Bot::getPluginHandler()->getPlugin('respond')) {
			throw new \Exception('Respond plugin is required.');
		}

		$name = $this->getName();
		if (!\Bot\Schema::isInstalled($name)) {
			$schema = new \Bot\Schema($name, __DIR__ .'/greeter.schema');
			$schema->install();
		}
	}

	public function onJoin( IrcEvent $event )
	{
		if ( $event->getNick() == $event->getBotNick() ) {
			return;
		}

		$respond = Bot::getPluginHandler()->getPlugin('respond');
		if (!$respond) {
			return;
		}

		$response = null;
		$user = User::fetch($event->getHostmask());
		if ($user) {
			$db = Bot::getDatabase();
			$greetings = $db->fetchColumn(
				"SELECT greeting FROM greetings WHERE user_id = ?",
				array($user->getId())
			);
			if (!empty($greetings)) {
				$rcount = count($greetings);
				if ($rcount==1) {
					$response = $greetings[0];
				} else {
					$response = $greetings[ (mt_rand(1, $rcount) -1) ];
				}
				$respond->respond($event, $response);
			}
		}
	}

	public function cmdAddGreeting(IrcEvent $event, $line)
	{
		if ($event->isFromChannel()) {
			return;
		}

		$server = $event->getServer();

		if (!User::isIdentified($event->getHostmask())) {
			$server->doPrivmsg($event->getSource(), "Please identify yourself.");
			return;
		} else {
			$user = User::fetch($event->getHostmask());
		}

		$length = strlen($line);
		if ($length > self::MAX_LENGTH) {
			$diff = $length - self::MAX_LENGTH;
			$server->doPrivmsg(
				$event->getSource(),
				sprintf("Your greeting is to long by %d character%s", $diff, ($diff==1?'':'s') )
			);
			return;
		}

		if ( !strstr($line, '\\n') ) {
			$server->doPrivmsg(
				$event->getSource(),
				"You must have a \\n somewhere in your greeting, ".
				"it will be replaced with the nick you're using when you join a channel."
			);
			return;
		}

		$cmd = substrto($line, ' ');
		if ($cmd != 'say' && $cmd != 'emote') {
			$server->doPrivmsg(
				$event->getSource(),
				"Your greeting must start with either 'say' or 'emote'."
			);
			return;
		}

		$db = Bot::getDatabase();
		$gcount = $db->fetchScalar(
			"SELECT count(1) FROM greetings WHERE user_id = ?",
			array($user->getId())
		);
		if ($gcount == 10) {
			$server->doPrivmsg(
				$event->getSource(),
				"You have used all 10 of your greeting slots. ".
				"You will have to remove one to add another."
			);
			return;
		}

		$r = $db->execute(
			'INSERT INTO greetings (user_id, greeting) VALUES(?, ?)',
			array($user->getId(), $line)
		);
		if (!$r) {
			$reply = 'Failed to add greeting.';
		} else {
			$gcount++;
			$reply = "Greeting added. You are now using {$gcount} of your 10 slots.";
		}

		$server->doPrivmsg($event->getSource(), $reply);
	}

	public function cmdDelGreeting(IrcEvent $event, $index)
	{
		if ($event->isFromChannel()) {
			return;
		}

		$server = $event->getServer();
		if ( !is_numeric($index) || $index < 1 || $index > 10) {
			$server->doPrivmsg($event->getSource(), "Failed: invalid index.");
			return;
		}

		$server = $event->getServer();
		if (!User::isIdentified($event->getHostmask())) {
			$server->doPrivmsg($event->getSource(), "Please identify yourself.");
			return;
		}
		
		$user = User::fetch($event->getHostmask());
		$db = Bot::getDatabase();
		$greetings = $db->fetchAll(
			"SELECT id, greeting FROM greetings WHERE user_id = ? ORDER BY id ASC",
			array($user->getId())
		);

		--$index;
		$gcount = count($greetings);
		if (!empty($greetings) && isset($greetings[$index])) {
			$index = $greetings[$index]['id'];
			$r = $db->execute(
				"DELETE FROM greetings WHERE user_id = ? AND id = ?",
				array($user->getId(), $index)
			);
			if ($r) {
				--$gcount;
				$server->doPrivmsg(
					$event->getSource(),
					"Deleted greeting. You are now using {$gcount} of your 10 slots."
				);
				return;
			}
		}

		$server->doPrivmsg($event->getSource(), "Failed: unable to delete greeting.");
	}

	public function cmdShowGreetings(IrcEvent $event)
	{
		if ($event->isFromChannel()) {
			return;
		}

		$server = $event->getServer();
		if (!User::isIdentified($event->getHostmask())) {
			$server->doPrivmsg($event->getSource(), "Please identify yourself.");
			return;
		}
		
		$user = User::fetch($event->getHostmask());
		$db = Bot::getDatabase();
		$greetings = $db->fetchColumn(
			"SELECT greeting FROM greetings WHERE user_id = ? ORDER BY id ASC",
			array($user->getId())
		);
		
		$return = array();
		$count = count($greetings);
		if ($count > 0) {
			$return[] = "Greetings:";
			foreach($greetings as $index => $greeting) {
				$return[] = sprintf('[%2d] %s', $index+1, $greeting);
			}
		}

		$return[] = "You have used {$count} of your 10 slots.";
		$server->doPrivmsg($event->getSource(), $return);
	}
}
