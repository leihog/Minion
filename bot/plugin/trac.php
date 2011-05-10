<?php
namespace Bot\Plugin;
use Bot\Bot;

class Trac extends Plugin 
{
	public function init()
	{
		if ( !extension_loaded('SimpleXML') )
		{
			/** @todo handle dependency checking in a better way. A plugin with a failed dependency should be disabled without crashing the bot. */
			throw new \Exception('SimpleXML php extension is required');
		}
	}

	public function onPrivmsg( \Bot\Event\Irc $event )
	{
	    if ( !$event->isFromChannel() || $event->getSource() !== Bot::getConfig('plugins/trac/channel', false) )
	    {
	        return;
	    }

        if ( preg_match("/^[^!]+(?=.*\bticket\b|.*\bissue\b|.*\btrac\b)(?=.*#(\d+))/", $event->getParam(1), $match) )
        {
            $tid = $match[1];

            $cmd = new \Bot\Command( 'ticket', $tid );
            $cmd->setEvent($event);
            $cmd->execute();
        }
	}

	public function cmdTicket( \Bot\Command $cmd, $ticket )
	{
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
	    $server = $cmd->getConnection();
		
	    $ticket = ltrim($ticket, '#');
	    if (!is_numeric($ticket))
	    {
	    	return;
	    }

		try
		{
		    $ticketUrl = Bot::getConfig('plugins/trac/trac.url') . "/ticket/{$ticket}";
			$lines = preg_split("/\r\n|\n/", self::getURL( $ticketUrl . "?format=csv" ), 2);
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

	    if ( ($login = Bot::getConfig('plugins/trac/login', false)) )
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