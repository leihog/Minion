<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;


class Trac extends Plugin
{
	public function init()
	{
		if ( !extension_loaded('SimpleXML') )
		{
			throw new \Exception('SimpleXML php extension is required');
		}
	}

	public function onPrivmsg(\Bot\Event\Irc $event)
	{
		if ( !$event->isFromChannel() || $event->getSource() !== Config::get('plugins/trac/channel', false) )
		{
			return;
		}

		if ( preg_match("/^[^!]+(?=.*\bticket\b|.*\bissue\b|.*\btrac\b)(?=.*#(\d+))/", $event->getParam(1), $match) )
		{
			$tid = $match[1];
			/** @todo add a method of adding new commands to the command queue of a user */
			Bot::getCommandDaemon()->execute( $event, 'ticket', $tid );
		}
	}

	public function cmdTicket( \Bot\Event\Irc $event, $ticket )
	{
		$hostmask = $event->getHostmask();
		$nick = $hostmask->getNick();
		$server = $event->getServer();

		$ticket = ltrim($ticket, '#');
		if (!is_numeric($ticket))
		{
			return;
		}

		try
		{
			$ticketUrl = Config::get('plugins/trac/trac.url') . "/ticket/{$ticket}";
			$dataUrl = $ticketUrl . "?format=csv";
			$pageContent = self::getURL( $dataUrl );
			if (empty($pageContent))
			{
				echo "Failed to load trac content from {$ticketUrl} \n";
				return;
			}

			$lines = preg_split("/\r\n|\n/", $pageContent, 2);
			$keys = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $lines[0]);
			$values = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $lines[1]);
			$ticket = array_combine($keys, $values);

			$server->doPrivmsg($event->getSource(), sprintf( '%s (%s) [%s]', trim($ticket['summary'],'"'), $ticket['status'], $ticketUrl ));
		}
		catch ( \Exception $e )
		{
			$server->doPrivmsg($event->getSource(), "Sorry, could not find ticket {$ticket}.");
		}
	}

	/**
	 * @todo perhaps this should accessible by all plugins.
	 *
	 * @param string $url
	 */
	protected function getUrl( $url )
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if ( ($login = Config::get('plugins/trac/login', false)) )
		{
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERPWD, $login);
		}

		if ( strstr($url, 'https') )
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		}

		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
}
