<?php
namespace Bot\Plugin;

use Bot\Event\Irc as IrcEvent;
use Bot\Bot as Bot;

/**
 *
 * Input will be classified as one of these types:
 * - Targeted: msg directed at the bot either by prefixing with nick (Minion:)
 *             or as a private message.
 * - Mention:  Not directed at the bot but contains bot nick.
 * - Public:   these are messages sent to a channel.
 *
 * @todo replace preg_match wich calls to ctype_alnum and check for
 *       a alphanumeric chars before and after $pattern.
 * @todo can't decide what I like better. Sub Cmds or many regular commands with
 *       cryptic/longer names.
 * @todo keep track how many times a response is triggered.
 * @todo we might want to use safer $id in del[match|except|reply] function
 * @todo we should keep track of getResponse search times.
 */
class Respond extends Plugin
{
	const TYPE_TARGETED = 0x1;
	const TYPE_MENTION  = 0x2;
	const TYPE_PUBLIC   = 0x4;

	protected $responses;
	protected $groupsByType;
	protected $isHushed;

	public function __construct()
	{
		$this->isHushed = array();
		$this->responses = array();
		$this->groupsByType = array(
			self::TYPE_TARGETED => array(),
			self::TYPE_MENTION => array(),
			self::TYPE_PUBLIC => array(),
		);
	}

	public function init()
	{
		$pluginName = $this->getName();
		if (!\Bot\Schema::isInstalled($pluginName)) {
			$schema = new \Bot\Schema($pluginName, __DIR__ . '/respond.schema' );
			$schema->install();
		}

		$db = Bot::getDatabase();
		// load responses - We might need to optimize this.
		$rows = $db->fetchAll("SELECT name, type, chance FROM respond_responses");
		foreach( $rows as $row ) {
			$this->initResponse($row);
		}
		$rows = null;

		$replies = $db->fetchAll("SELECT response, reply FROM respond_replies");
		foreach( $replies as $item ) {
			if ( isset($this->responses[$item['response']]) ) {
				$this->responses[$item['response']]->replies[] = $item['reply'];
			} else {
				Bot::log("Got reply for missing response: {$item['response']}.");
			}
		}
		$replies = null;

		$patterns = $db->fetchAll("SELECT response, pattern, type FROM respond_patterns");
		foreach( $patterns as $item ) {
			if ( !isset($this->responses[$item['response']]) ) {
				Bot::log("Got pattern for missing response: {$item['response']}.");
				continue;
			}

			if ( $item['type'] == 'match' ) {
				$this->responses[$item['response']]->match[] = $item['pattern'];
			} else {
				$this->responses[$item['response']]->except[] = $item['pattern'];
			}
		}
		$patterns = null;
	}

	public function onPrivmsg( \Bot\Event\Irc $event )
	{
		$channel = ( $event->isFromChannel() ? $event->getSource() : null );
		if ( $channel && !empty($this->isHushed) && isset($this->isHushed[$channel]) ) {
			if ( $this->isHushed[$channel] > time() ) {
				return;
			} else {
				unset($this->isHushed[$channel]);
			}
		}

		$msg = $event->getParam(1);
		$type = $this->getMessageType($event);
		$response = $this->getResponse($type, $msg);
		if ( !$response ) {
			return; // No match
		}

		$reply = $this->getReply($response);
		$this->respond($event, $reply);
	}

	/**
	 * Tokens:
	 * n - Nick of user we are responding to.
	 * c - Channel we are responding in or IRC if not on a channel
	 * b - Bot nick
	 * u - Uptime
	 */
	protected function translateToken($token, &$event)
	{
		switch($token)
		{
		case 'n': return $event->getNick(); break;
		case 'b': return $event->getBotNick(); break;
		case 'c': return ($event->isFromChannel() ? $event->getSource() : 'IRC' ); break;
		case 'u': return Bot::uptime(); break;
		default: return ''; break;
		}
	}

	/**
	 * @todo add support for the duration parameter.
	 * @param int $duration The amount of minutes to disable responses.
	 */
	public function cmdHush($event, $duration = 5)
	{
		if ( !is_numeric($duration) ) {
			$duration = 5;
		}

		if ( !$event->isFromChannel() ) {
			return;
		}

		$channel = $event->getSource();
		$this->isHushed[$channel] = (time() + (60 * $duration));
	}

	public function cmdUnhush($event)
	{
		if ( !$event->isFromChannel() ) {
			return;
		}

		$channel = $event->getSource();
		unset($this->isHushed[$channel]);
	}

	/**
	 * This is the response management tool
	 * Will evaluate and execute sub-command requests.
	 */
	public function cmdRespond($event, $arg = '')
	{
		$server = $event->getServer();

		try
		{
			list($cmd, $parameters) = array_pad( explode(' ', $arg, 2), 2, null);
			switch($cmd)
			{
			case 'showresponse':
			case 'showmatches':
			case 'showexcepts':
			case 'showreplies':
			case 'delresponse':
			case 'search':
				// Only have one parameter
				$parameters = array($parameters);
				break;

			case 'addresponse':
			case 'addmatch':
			case 'delmatch':
			case 'addreply':
			case 'delreply':
			case 'addexcept':
			case 'delexcept':
			case 'setchance':
			case 'settype':
				// Expects $responseName, $value
				$parameters = array_pad( explode(' ', $parameters, 2), 2, null);
				break;

			default:
				// unknown command
				$cmds = array(
					'addresponse',
					'addmatch',
					'addexcept',
					'addreply',
					'delresponse',
					'delmatch',
					'delreply',
					'delexcept',
					'setchance',
					'setype',
					'search',
					'showresponse',
					'showmatches',
					'showexcepts',
					'showreplies',
				);

				$server->doPrivmsg($event->getSource(), array(
					"Syntax: respond <subcmd> [parameters]",
					"Available sub commands:"
				));
				$server->doPrivmsg(
					$event->getSource(),
					$this->formatTableArray($cmds, "%-14s", 4, 20)
				);
				return;
			}

			$return = call_user_func_array(array($this, "subcmd{$cmd}"), $parameters);
			if ( !$return ) {
				$return = "Failed.";
			} else if ( !is_array($return) ) {
				$return = "Ok.";
			}
		}
		catch (\Exception $e)
		{
			if ( $e->getCode() == 666 ) {
				$return = $e->getMessage();
			} else {
				Bot::log($e->getMessage());
				$return = "Failed.";
			}
		}

		$server->doPrivmsg($event->getSource(), $return);
	}

	protected function subcmdAddResponse($responseName, $type)
	{
		if ( ($type = $this->parseType($type)) === false ) {
			throw new \Exception('Invalid type', 666);
		}

		if ( isset($this->responses[$responseName]) ) {
			throw new \Exception("{$responseName} exists", 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"INSERT INTO respond_responses (name, type, chance) VALUES(?,?,?)",
			array($responseName, $type, 100)
		);
		if ($r) {
			$this->initResponse(array(
				'name' => $responseName,
				'type' => $type,
				'chance' => 100,
			));
			return true;
		}
	}

	protected function subcmdDelResponse($responseName)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		$db = Bot::getDatabase();
		$sql = array(
			"DELETE FROM respond_replies WHERE response = ?",
			"DELETE FROM respond_patterns WHERE response = ?",
			"DELETE FROM respond_responses WHERE name = ?",
		);

		$r = $db->execute($sql, $responseName);
		if (!$r) {
			throw new \Exception('Failed to delete response', 666);
		}

		$this->ungroupResponse($this->responses[$responseName]);
		unset( $this->responses[$responseName] );
		return true;
	}

	protected function subcmdSetChance($responseName, $chance)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		if ( $chance >= 1 && $chance <= 100 ) {
			$db = Bot::getDatabase();
			$r = $db->execute(
				"UPDATE respond_responses SET chance = ? WHERE name = ?",
				array($chance, $responseName)
			);
			if ($r) {
				$this->responses[$responseName]->chance = $chance;
				return true;
			}
		}

		return false;
	}

	protected function subcmdSetType($responseName, $type)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		if ( ($type = $this->parseType($type)) === false ) {
			throw new \Exception('Invalid type', 666);
		}

		$response = $this->responses[$responseName];
		$this->ungroupResponse($response);
		$response->type = $type;
		$this->groupResponse($response);

		return true;
	}

	protected function subcmdAddExcept($responseName, $pattern)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"INSERT INTO respond_patterns (response, pattern, type) VALUES(?, ?, 'except')",
			array($responseName, $pattern)
		);
		if ($r) {
			//$this->patterns[$responseName]['except'][] = $pattern;
			$this->responses[$responseName]->except[] = $pattern;
			return true;
		}
	}

	protected function subcmdDelExcept($responseName, $id)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$responseName];
		if ( !isset($response->except[$id]) ) {
			throw new \Exception("Unable to find except", 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"DELETE FROM respond_patterns WHERE response = ? AND ".
			"type = 'except' AND pattern = ?",
			array($responseName, $response->except[$id])
		);
		if (!$r) {
			throw new \Exception('Unable to delete except', 666);
		}
		unset( $response->except[$id] );
		return true;
	}

	protected function subcmdAddMatch($responseName, $pattern)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"INSERT INTO respond_patterns (response, pattern, type) VALUES(?, ?, 'match')",
			array($responseName, $pattern)
		);
		if ($r) {
			//$this->patterns[$responseName]['match'][] = $pattern;
			$this->responses[$responseName]->match[] = $pattern;
			return true;
		}
	}

	protected function subcmdDelMatch($responseName, $id)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}
		
		$response = $this->responses[$responseName];
		if ( !isset($response->match[$id]) ) {
			throw new \Exception("Unable to find match", 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"DELETE FROM respond_patterns WHERE response = ? AND ".
			"type = 'match' AND pattern = ?",
			array($responseName, $response->match[$id] )
		);
		if (!$r) {
			throw new \Exception('Unable to delete match', 666);
		}
		unset( $response->match[$id] );
		return true;
	}

	protected function subcmdAddReply($responseName, $reply)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		list($cmd, ) = explode(' ', $reply, 2);
		if ( !in_array($cmd, array('say', 'emote')) ) {
			throw new \Exception('Invalid reply syntax.', 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"INSERT INTO respond_replies (response, reply) VALUES(?, ?)",
			array($responseName, $reply)
		);
		if (!$r) {
			throw new \Exception("Failed to add reply", 666);
		}
		$this->responses[$responseName]->replies[] = $reply;
		return true;
	}

	protected function subcmdDelReply($responseName, $id)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$responseName];
		if ( !isset($response->replies[$id]) ) {
			throw new \Exception("Unable to find reply", 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"DELETE FROM respond_replies WHERE response = ? AND reply = ?",
			array($responseName, $response->replies[$id])
		);
		if (!$r) {
			throw new \Exception('Unable to delete reply', 666);
		}
		unset( $response->replies[$id] );
		return true;
	}

	protected function subcmdShowResponse($responseName)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$responseName];
		$return = array();
		$return[] = "Response: {$responseName}";

		$type = array();
		if ( ($response->type & self::TYPE_TARGETED) == self::TYPE_TARGETED) {
			$type[] = 'targeted';
		}

		if ( ($response->type & self::TYPE_MENTION) == self::TYPE_MENTION) {
			$type[] = 'mention';
		}

		if ( ($response->type & self::TYPE_PUBLIC) == self::TYPE_PUBLIC) {
			$type[] = 'public';
		}

		$type = implode(', ', $type);
		$return[] = "Type: {$type}";

		$row = array();
		$row[] = "Chance: 100";
		$patternTypes = array('match'=>'Matches', 'except'=>'Excepts');
		foreach ( $patternTypes as $type => $header ) {
			$row[] = $header .": ". count($response->$type);
		}
		$row[] = 'Replies: '. count($response->replies);
		$return[] = implode("    ", $row);

		return $return;
	}

	protected function subcmdShowExcepts($responseName)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$responseName];
		if ( empty($response->except) ) {
			return array(
				"No excepts registered for response {$responseName}."
			);
		}

		$return = array();
		$return[] = "Excepts registered for response {$responseName}:";
		foreach ( $response->except as $id => $pattern ) {
			$return[] = "[{$id}] {$pattern}";
		}
		return $return;
	}

	protected function subcmdShowMatches($responseName)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$responseName];
		if ( empty($response->match) ) {
			return array("No matches registered for response {$responseName}.");
		}

		$return = array();
		$return[] = "Matches registered for response {$responseName}:";
		foreach ( $response->match as $id => $pattern ) {
			$return[] = "[{$id}] {$pattern}";
		}
		return $return;
	}

	protected function subcmdShowReplies($responseName)
	{
		if (!isset($this->responses[$responseName])) {
			throw new \Exception('No such response.', 666);
		}
		
		$response = $this->responses[$responseName];
		$return = array();
		if ( !empty($response->replies) ) {
			$return[] = "Replies for response {$responseName}:";
			foreach( $response->replies as $id => $response ) {
				$return[] = "[{$id}] {$response}";
			}
		}
		return $return;
	}

	protected function subcmdSearch($needle)
	{
		$matches = array();
		foreach ( $this->responses as $r ) {
			foreach ( $r->replies as $s ) {
				if ( stristr($s, $needle) !== false ) {
					$matches[] = $r->name .':    '. $s;
				}
			}
		}

		if ( empty($matches) ) {
			return array("Found no matching responses.");
		}

		return $matches;
	}

	public function respond(IrcEvent $event, $response)
	{
		$server = $event->getServer();
		$source = $event->getSource();

		//might want to extract this into it's own function/plugin/class
		$offset = 0;
		while ( ($pos = strpos($response, '\\', $offset)) !== false ) {
			$token = $this->translateToken($response[$pos+1], $event);
			$response = substr_replace($response, $token, $pos, 2);
			$offset += strlen($token);
		}

		list($cmd, $response) = explode(" ", $response, 2);
		switch(strtolower($cmd))
		{
		case 'emote':
			$server->doEmote($source, $response);
			break;
		case 'say':
			$server->doPrivmsg($source, $response);
			break;

		default: // Perhaps we could add KICK or other actions?
			Bot::log("Recieved unknown respond cmd '{$cmd} {$response}'.");
		}
	}

	protected function getReply($group)
	{
		$replies =& $this->responses[$group]->replies;
		if ( sizeof($replies) == 1 ) {
			return $replies[0];
		}

		$lottery = array();
		if ( !empty($this->responses[$group]->rhits) ) {
			foreach ( array_keys($replies) as $key ) {
				if ( !isset($this->responses[$group]->rhits[$key]) ) {
					$lottery[] = $key;
				}
			}
		}

		$count = count($lottery);
		if ( $count == 0 ) {
			$lottery = array_keys($replies);
			$count = count($lottery);
			$this->responses[$group]->rhits = array();
		}
		
		if ( $count == 1 ) {
			$winner = $lottery[0];
		} else {
			$winner = $lottery[ (mt_rand(1, $count) -1) ];
		}

		$this->responses[$group]->rhits[$winner] = true;
		return $replies[ $winner ];
	}

	/**
	 * Returns the first response that matches message and message type.
	 *
	 * @param int $type The message type.
	 * @param string $msg The message we want to respond to.
	 * @return string response name.
	 */
	protected function getResponse($type, $msg)
	{
		foreach ( $this->groupsByType[$type] as $response ) {
			if ( !$this->evalMatch($msg, $response->match) ) {
				continue; // if match fails then continue search
			}

			if ( $this->evalMatch($msg, $response->except) ) {
				continue; // if except matches then continue search
			}

			// If chance is larger than $response->chance then we skip.
			$chance = mt_rand(1, 100);
			if ( $chance  > $response->chance ) {
				continue;
			}
			return $response->name;
		}

		return false;
	}

	/**
	 * Will test a string against an array of patterns.
	 *
	 * @param string @msg
	 * @param array $patterns
	 * @return bool true if pattern matches
	 */
	protected function evalMatch(&$msg, &$patterns)
	{
		if ( !is_array($patterns) || empty($patterns) ) {
			return false;
		}

		foreach( $patterns as $pattern ) {
			if ( stristr($msg, $pattern) === false ) {
				continue;
			}

			if ( preg_match('/\b'.$pattern.'\b/ui', $msg) ) {
				return true;
			}
		}

		return false;
	}

	protected function getMessageType(&$event)
	{
		if ( !$event->isFromChannel() ) {
			return self::TYPE_TARGETED;
		}

		$msg = trim($event->getParam(1));
		$botnick = $event->getServer()->getNick();

		if ( !$this->isNickMentioned($botnick, $msg) ) {
			$preferedNick = \Bot\Config::get('irc/nick');
			if ( $preferedNick != $botnick && $this->isNickMentioned($preferedNick, $msg) ) {
				$botnick = $preferedNick;
			} else {
				return self::TYPE_PUBLIC;
			}
		}

		if ( stripos($msg, $botnick) === 0 ) {
			return self::TYPE_TARGETED;
		}

		return self::TYPE_MENTION;
	}

	/**
	 * Takes a type string and returns a bitflag with the types set.
	 * @return false|int
	 */
	protected function parseType($typeString)
	{
		$type = false;
		$types = explode(',', strtolower($typeString));
		foreach ($types as $t) {
			switch($t)
			{
			case 'public': $type |= self::TYPE_PUBLIC; break;
			case 'mention': $type |= self::TYPE_MENTION; break;
			case 'targeted': $type |= self::TYPE_TARGETED; break;
			}
		}
		return $type;
	}

	/**
	 * Check if $nick is mentioned in $msg.
	 * The character in front of $nick may only be a space or a ','
	 * while the character directly following $nick may be one of ',.!?:>'
	 * or a space.
	 *
	 * @param string $nick
	 * @param string $msg
	 * @return bool
	 */
	protected function isNickMentioned($nick, $msg)
	{
		if ( ($pos = stripos($msg, $nick)) === false ) {
			return false;
		}
		
		if ( $pos != 0 && !strpbrk($msg[$pos-1], ', ') ) {
			return false;
		}

		$after = $pos + strlen($nick);
		if ( isset($msg[$after]) && !strpbrk($msg[$after], ',.!?:> ') ) {
			return false;
		}

		return true;
	}

	protected function initResponse($data)
	{
		$response = new \StdClass();
		$response->name    = $data['name'];
		$response->type    = $data['type'];
		$response->chance  = $data['chance'];
		$response->replies = array();
		$response->match   = array();
		$response->except  = array();
		$response->rhits   = array();

		$this->responses[$response->name] = $response;
		$this->groupResponse($response);
	}

	protected function groupResponse($response)
	{
		if ( ($response->type & self::TYPE_PUBLIC) ) {
			$this->groupsByType[self::TYPE_PUBLIC][$response->name] = $response;
		}

		if ( ($response->type & self::TYPE_TARGETED) ) {
			$this->groupsByType[self::TYPE_TARGETED][$response->name] = $response;
		}

		if ( ($response->type & self::TYPE_MENTION) ) {
			$this->groupsByType[self::TYPE_MENTION][$response->name] = $response;
		}
	}

	protected function ungroupResponse($response)
	{
		if ( ($response->type & self::TYPE_PUBLIC) ) {
			unset($this->groupsByType[self::TYPE_PUBLIC][$response->name]);
		}

		if ( ($response->type & self::TYPE_TARGETED) ) {
			unset($this->groupsByType[self::TYPE_TARGETED][$response->name]);
		}

		if ( ($response->type & self::TYPE_MENTION) ) {
			unset($this->groupsByType[self::TYPE_MENTION][$response->name]);
		}
	}
}
