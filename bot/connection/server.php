<?php
namespace Bot\Connection;

class Server extends \Bot\Socket\Client\Stream
{
	protected $nick;
	protected $realname;
	protected $username;
	
	protected $buffer = ''; // used when reading input
	protected $writeQueue = array();

	// anti send-flood
	protected $rate = 5.0;
	protected $per = 8.0;
	protected $lastChecked;
	protected $allowance;

	public function connect()
	{
		if (parent::connect())
		{
		    $this->lastChecked = time();
		    $this->allowance = $this->rate;

            \Bot\Bot::getEventHandler()->raise( new \Bot\Event\Socket( 'Connect', array( 'socket' => $this ) ));

			return true;
		}

		return false;
	}

	public function disconnect()
	{
	    parent::disconnect();
	    \Bot\Bot::getEventHandler()->raise( new \Bot\Event\Socket( 'Disconnect', array('socket' => $this) ));
	}
	
	// input
	
	protected function parseArguments($args, $count = -1)
    {
        return preg_split('/ :?/S', $args, $count);
    }
	
    protected function parseLine( $line )
    {
    	if (empty($line))
    	{
    		return;
    	}
    	
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
	        case 'names':
	        case 'nick':
	        case 'quit':
	        case 'ping':
	        case 'pong':
	        case 'error':
	            $args = array_filter(array(ltrim($args, ':')));
	            break;
	
	        case 'privmsg':
	        case 'notice':
	            $this->parseMsg( $cmd, $args );
	        	break;
	
	        case 'topic':
	        case 'part':
	        case 'invite':
	        case 'join':
	            $args = $this->parseArguments($args, 2);
	            break;
	
	        case 'kick':
	        case 'mode':
	            $args = $this->parseArguments($args, 3);
	            break;

	        default: // Numeric response
	            if ( $args[0] == '*')
	            {
	                $args = substr($args, 2);
	            }
	            else
	            {
	                $args = ltrim( substr($args, strpos($args, ' ')), ' :=');
	            }

	            break;
		} //end switch

		//echo $raw, "\n";
		
		$event = new \Bot\Event\Irc($cmd, $args);
		$event->setRaw($raw);
		$event->setSocket($this);

		if ( !is_numeric($cmd) && strpos($hostmask, '@') )
		{
			$event->setHostmask( new \Bot\Hostmask( $hostmask ) );
		}

		\Bot\Bot::getEventHandler()->raise( $event );
    }
    
	protected function parseMsg( &$cmd, &$args )
	{
		$args = $this->parseArguments($args, 2);

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
	
	/**
	 * Handles socket input and raises events.
	 * 
	 * @see Bot\Socket\Client.Stream::read()
	 */
	public function read()
	{
		$buffer = $this->buffer . parent::read(512);
		$this->buffer = '';
		if (empty($buffer))
		{
			return;
		}

		$incompleteRead = false;
		if ( !preg_match('/\v+$/', $buffer) )
		{
			$incompleteRead = true;
		}
		
		$buffer = preg_split('/\v+/', $buffer);
		if ( $incompleteRead )
		{
			$this->buffer = array_pop($buffer);
		}

		foreach($buffer as &$line)
		{
			$this->parseLine(trim($line));
		}
	}

	// output
	
	public function processWriteQueue()
	{
		if (empty($this->writeQueue))
		{
			return;
		}

		$now = time();
		$timePassed = ($now - $this->lastChecked);
		$this->lastChecked = $now; 
		$this->allowance += ( $timePassed * ($this->rate / $this->per) );
		if ($this->allowance > $this->rate)
		{
		    $this->allowance = $this->rate;
		}

		/** @todo when we fail to write we need to re-add the buffer to the queue. */
		while( $this->allowance > 1 && ($buffer = array_shift($this->writeQueue)) )
		{
			try
			{
			    --$this->allowance;
				$this->write( $buffer );
			}
			catch(\Exception $e)
			{
				echo "Failed to write\n", $e->getMessage(), "\n";
				break;
			}
		}
	}
	
	/**
	 * Adds $string to the send queue
	 * If $skipQueue is true then $string is sent right away.
	 * 
	 * @param string $string
	 * @param boolean $skipQueue
	 * 
	 * @see Bot\Socket\Client.Stream::write()
	 */
	public function send( $string, $skipQueue = false )
	{
        if ( $skipQueue )
        {
            try
            {
                $this->write($string);
            }
            catch( \Exception $e )
            {
                echo "Failed to write\n", $e->getMessage(), "\n";
            }
            
            return;
        }

		$this->writeQueue[] = $string;
	}

	// getters / setters
	
	public function getNick()
	{
	    return $this->nick;
	}
	
	public function setNick( $nick )
	{
		$this->nick = $nick;
	}
	
	public function setRealname( $realname )
	{
		$this->realname = $realname;
	}
	
	public function setUsername( $username )
	{
		$this->username = $username; 
	}

}