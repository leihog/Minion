<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;
/**
 *
 * Input will be classified as one of these types:
 * - Targeted: msg directed at the bot either by prefixing with nick (Minion:)
 *             or as a private message.
 * - Mention:  Not directed at the bot but contains bot nick.
 * - Public:   these are messages sent to a channel.
 *
 * @todo Token replacement in replies
 * @todo when searching for botnick use both current nick and altnicks.
 * @todo after a reply has triggered flag it so that it's not used again for a while
 * @todo addresponse can't handle multiple types yet.
 * @todo add support for the duration parameter.
 * @todo subcmddelResponse should use transactions.
 *
 * @todo keep track how many times a response is triggered.
 * @todo we might want to use safer $id in del[match|except|reply] function
 * @todo optimize out preg_replace
 * @todo we should keep track of getResponse search times.
 * @todo could replace with a Response class
 */
class Respond extends Plugin
{
	const TYPE_TARGETED = 0x1;
	const TYPE_MENTION  = 0x2;
	const TYPE_PUBLIC   = 0x4;

	protected $responses;
	protected $groupsByType;
	protected $isHushed = false;

	public function __construct()
	{
		$this->responses = array();
		$this->groupsByType = array(
			self::TYPE_TARGETED => array(),
			self::TYPE_MENTION => array(),
			self::TYPE_PUBLIC => array(),
		);
	}

	public function init()
	{
		$db = Bot::getDatabase();
		if (!$db->isInstalled($this->getName())) {
			$db->install( $this->getName(), __DIR__ . '/respond.schema' );
		}

		// load responses - We might need to optimize this.
		$rows = $db->fetchAll("SELECT name, types, chance FROM respond_groups");
		foreach( $rows as $row ) {
			$response = $this->newResponse($row);
			$this->responses[$response->name] = $response;
		}
		$rows = null;

		$replies = $db->fetchAll("SELECT groupname, reply FROM respond_replies");
		foreach( $replies as $item ) {
			if ( isset($this->responses[$item['groupname']]) ) {
				$this->responses[$item['groupname']]->replies[] = $item['reply'];
			} else {
				Bot::log("Got reply for missing response: {$item['groupname']}.");
			}
		}
		$replies = null;

		$patterns = $db->fetchAll("SELECT groupname, pattern, type FROM respond_patterns");
		foreach( $patterns as $item ) {
			if ( !isset($this->responses[$item['groupname']]) ) {
				Bot::log("Got pattern for missing response: {$item['groupname']}.");
				continue;
			}

			if ( $item['type'] == 'match' ) {
				$this->responses[$item['groupname']]->match[] = $item['pattern'];
			} else {
				$this->responses[$item['groupname']]->except[] = $item['pattern'];
			}
		}
		$patterns = null;
	}

	public function onPrivmsg( \Bot\Event\Irc $event )
	{
		if ( $this->isHushed ) {
			return;
		}

		$msg = $event->getParam(1);
		$type = $this->getMessageType($event);
		
		$group = $this->getResponse($type, $msg);
		if ( !$group ) {
			return; // No match
		}

		$reply = $this->getReply($group);
		$this->respond($event->getServer(), $event->getSource(), $reply);
	}

	/**
	 * @todo add support for the duration parameter.
	 */
	public function cmdHush($event, $duration = 5)
	{
		$this->isHushed = true;
	}

	public function cmdUnhush($event)
	{
		$this->isHushed = false;
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
				// Expects $groupName, $value
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

	protected function subcmdAddResponse($groupName, $type)
	{
		switch(strtolower($type))
		{
		case 'public': $type = self::TYPE_PUBLIC; break;
		case 'mention': $type = self::TYPE_MENTION; break;
		case 'targeted': $type = self::TYPE_TARGETED; break;
		default:
			throw new \Exception('Invalid type', 666);
		}

		if ( isset($this->responses[$groupName]) ) {
			throw new \Exception("{$groupName} exists", 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"INSERT INTO respond_groups (name, types) VALUES(?,?)",
			array($groupName, $type)
		);
		if ($r) {
			$response = $this->newResponse(array(
				'name' => $groupName,
				'type' => $type,
				'chance' => 100,
			));
			return true;
		}
	}

	protected function subcmdDelResponse($groupName)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		$db = Bot::getDatabase();
		$sql = array(
			"DELETE FROM respond_replies WHERE groupname = ?",
			"DELETE FROM respond_patterns WHERE groupname = ?",
			"DELETE FROM respond_groups WHERE name = ?",
		);
		foreach ( $sql as $query ) {
			/** @todo add transaction support */
			$r = $db->execute($query, array($groupName));
			if (!$r) {
				throw new \Exception('Failed to delete response', 666);
			}
		}

		foreach(array_keys($this->groupsByType) as $type) {
			unset( $this->groupsByType[$type][$groupName] );
		}
		unset( $this->responses[$groupName] );
		return true;
	}

	protected function subcmdSetChance($groupName, $chance)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		if ( $chance >= 1 && $chance <= 100 ) {
			$this->responses[$groupName]->chance = $chance;
			return true;
		}

		return false;
	}

	protected function subcmdAddExcept($groupName, $pattern)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"INSERT INTO respond_patterns (groupname, pattern, type) VALUES(?, ?, 'except')",
			array($groupName, $pattern)
		);
		if ($r) {
			//$this->patterns[$groupName]['except'][] = $pattern;
			$this->responses[$groupName]->except[] = $pattern;
			return true;
		}
	}

	protected function subcmdDelExcept($groupName, $id)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$groupName];

		//if ( !isset($this->patterns[$groupName]['except'][$id]) ) {
		if ( !isset($response->except[$id]) ) {
			throw new \Exception("Unable to find except", 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"DELETE FROM respond_patterns WHERE groupname = ? AND ".
			"type = 'except' AND pattern = ?",
			//array($groupName, $this->patterns[$groupName]['except'][$id])
			array($groupName, $response->except[$id])
		);
		if (!$r) {
			throw new \Exception('Unable to delete except', 666);
		}
		//unset( $this->patterns[$groupName]['except'][$id] );
		unset( $response->except[$id] );
		return true;
	}

	protected function subcmdAddMatch($groupName, $pattern)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"INSERT INTO respond_patterns (groupname, pattern, type) VALUES(?, ?, 'match')",
			array($groupName, $pattern)
		);
		if ($r) {
			//$this->patterns[$groupName]['match'][] = $pattern;
			$this->responses[$groupName]->match[] = $pattern;
			return true;
		}
	}

	protected function subcmdDelMatch($groupName, $id)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}
		
		$response = $this->responses[$groupName];

		//if ( !isset($this->patterns[$groupName]['match'][$id]) ) {
		if ( !isset($response->match[$id]) ) {
			throw new \Exception("Unable to find match", 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"DELETE FROM respond_patterns WHERE groupname = ? AND ".
			"type = 'match' AND pattern = ?",
			//array($groupName, $this->patterns[$groupName]['match'][$id])
			array($groupName, $response->match[$id] )
		);
		if (!$r) {
			throw new \Exception('Unable to delete match', 666);
		}
		//unset( $this->patterns[$groupName]['match'][$id] );
		unset( $response->match[$id] );
		return true;
	}

	protected function subcmdAddReply($groupName, $reply)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		list($cmd, ) = explode(' ', $reply, 2);
		if ( !in_array($cmd, array('say', 'emote')) ) {
			throw new \Exception("Invalid reply syntax.");
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"INSERT INTO respond_replies (groupname, reply) VALUES(?, ?)",
			array($groupName, $reply)
		);
		if (!$r) {
			throw new \Exception("Failed to add reply", 666);
		}
		$this->responses[$groupName]->replies[] = $reply;
		return true;
	}

	protected function subcmdDelReply($groupName, $id)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$groupName];
		if ( !isset($response->replies[$id]) ) {
			throw new \Exception("Unable to find reply", 666);
		}

		$db = Bot::getDatabase();
		$r = $db->execute(
			"DELETE FROM respond_replies WHERE groupname = ? AND reply = ?",
			array($groupName, $response->replies[$id])
		);
		if (!$r) {
			throw new \Exception('Unable to delete reply', 666);
		}
		unset( $response->replies[$id] );
		return true;
	}

	protected function subcmdShowResponse($groupName)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$groupName];
		$reply = array();
		$reply[] = "Response: {$groupName}";

		$row = array();
		$row[] = "Chance: 100";
		$patternTypes = array('match'=>'Matches', 'except'=>'Excepts');
		foreach ( $patternTypes as $type => $header ) {
			$row[] = $header .": ". count($response->$type);
		}
		$row[] = 'Replies: '. count($response->replies);
		$reply[] = implode("    ", $row);

		return $reply;
	}

	protected function subcmdShowExcepts($groupName)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$groupName];
		if ( empty($response->except) ) {
			return array(
				"No excepts registered for response {$groupName}."
			);
		}

		$return = array();
		$return[] = "Excepts registered for response {$groupName}:";
		foreach ( $response->except as $id => $pattern ) {
			$return[] = "[{$id}] {$pattern}";
		}
		return $return;
	}

	protected function subcmdShowMatches($groupName)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}

		$response = $this->responses[$groupName];
		if ( empty($response->match) ) {
			return array("No matches registered for response {$groupName}.");
		}

		$return = array();
		$return[] = "Matches registered for response {$groupName}:";
		foreach ( $response->match as $id => $pattern ) {
			$return[] = "[{$id}] {$pattern}";
		}
		return $return;
	}

	protected function subcmdShowReplies($groupName)
	{
		if (!isset($this->responses[$groupName])) {
			throw new \Exception('No such response.', 666);
		}
		
		$response = $this->responses[$groupName];
		$return = array();
		if ( !empty($response->replies) ) {
			$return[] = "Replies for response {$groupName}:";
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

	protected function respond(&$server, $source, $response)
	{
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

		$count = sizeof($replies);
		if ( $count == 1 ) {
			return $replies[0];
		}

		return $replies[ (mt_rand(1, $count) -1) ];
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

			//if ( !$this->evalMatch($msg, $this->patterns[$response->name]['match']) ) {
			if ( !$this->evalMatch($msg, $response->match) ) {
				continue; // if match fails then continue search
			}

			//if ( $this->evalMatch($msg, $this->patterns[$response->name]['except']) ) {
			if ( $this->evalMatch($msg, $response->except) ) {
				continue; // if except matches then continue search
			}

			// If chance is larger than $response->chance then we skip.
			$chance = mt_rand(1, 100);
			if ( $chance  > $response->chance ) {
				continue; // allow fate to take it's course
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

			// @todo replace preg_match wich calls to ctype_alnum and check for
			// a alphanumeric chars before and after $pattern.
			if ( preg_match('/\b'.$pattern.'\b/ui', $msg) ) {
				return true;
			}
		}

		return false;
	}

	protected function getMessageType(&$event)
	{
		$msg = $event->getParam(1);
		$botnick = $event->getServer()->getNick();

		if ( !$event->isFromChannel() ) {
			return self::TYPE_TARGETED;
		}

		if ( ($pos = strpos($msg, $botnick)) === false ) {
			return self::TYPE_PUBLIC;
		}

		$bl = strlen($botnick);
		if ( $pos == 0 && strpbrk($msg[$bl], ':,> ') ) {
			return self::TYPE_TARGETED;
		} else {
			return self::TYPE_MENTION;
		}
	}
	protected function newResponse(&$data)
	{
		$response = new \StdClass();
		$response->name    = $data['name'];
		$response->type    = $data['types'];
		$response->chance  = $data['chance'];
		$response->replies = array();
		$response->matches = array();
		$response->excepts = array();

		// Not sure we want to allow a response to be of several types.
		if ( ($response->type & self::TYPE_PUBLIC) ) {
			$this->groupsByType[self::TYPE_PUBLIC][$response->name] = $response;
		}

		if ( ($response->type & self::TYPE_TARGETED) ) {
			$this->groupsByType[self::TYPE_TARGETED][$response->name] = $response;
		}

		if ( ($response->type & self::TYPE_MENTION) ) {
			$this->groupsByType[self::TYPE_MENTION][$response->name] = $response;
		}

		return $response;
	}
}

