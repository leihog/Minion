<?php
namespace Bot\parser;

class Irc
{
	protected static function parseArguments($args, $count = -1)
	{
		return preg_split('/ :?/S', $args, $count);
	}

	protected static function parseMsg( &$cmd, &$args )
	{
		$args = self::parseArguments($args, 2);

		list($source, $ctcp) = $args;
		if (substr($ctcp, 0, 1) === "\001" && substr($ctcp, -1) === "\001")
		{
			$ctcp = substr($ctcp, 1, -1);
			$reply = ($cmd == 'notice');
			list($cmd, $args) = array_pad(explode(' ', $ctcp, 2), 2, array());
			$cmd = strtolower($cmd);

			switch ($cmd)
			{
				case 'action':
					$args = array($source, $args);
					break;

				case 'finger':
				case 'ping':
				case 'time':
				case 'version':
					if ($reply)
					{
						$args = array($args);
					}
					break;
			}
		}
	}

	public static function parse( $line )
	{
		$raw = $line;
		$hostmask = '';
		if ( $line[0] == ':' )
		{
			list($hostmask, $line) = explode(' ', $line, 2);
			$hostmask = substr($hostmask, 1);
		}

		list($cmd, $args) = array_pad(explode(' ', $line, 2), 2, null); // not sure the array_pad is needed.
		$cmd = strtolower($cmd);

		switch( $cmd )
		{
		case 'error':
		case 'join':
		case 'names':
		case 'nick':
		case 'part':
		case 'ping':
		case 'pong':
		case 'quit':
			$args = array_filter(array(ltrim($args, ':')));
			break;

		case 'privmsg':
		case 'notice':
			self::parseMsg( $cmd, $args );
			break;

		case 'topic':
		case 'invite':
			$args = self::parseArguments($args, 2);
			break;

		case 'kick':
		case 'mode':
			$args = self::parseArguments($args, 3);
			break;

		default: // Numeric response
			if ( $args[0] == "*" ) {
				$args = array(substr($args, 2));
			} else {
				$args = array( ltrim( substr($args, strpos($args, ' ')), ' :=') );
			}

			break;
		} //end switch

		return compact('cmd', 'args', 'raw', 'hostmask');
	}
}
