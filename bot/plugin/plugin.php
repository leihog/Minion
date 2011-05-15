<?php
namespace Bot\Plugin;

abstract class Plugin
{
    
	public function doJoin( $channel, $key = '' )
	{
		if ( is_array($channel) )
		{
			$channels = $keys = array();
			foreach( $channel as &$chan )
			{
				list($c, $k) = array_pad(explode(':', $chan, 2), 2, ' ');
				$channels[] = $c;
				$keys[] = $k;
			}

			$channel = implode(',', $channels);
			$key = implode(',', $keys);
		}

        $this->getServer()->send( $this->prepare('JOIN', $channel, $key) );
	}

	public function doNick( $nick )
	{
	    $this->getServer()->setNick($nick);
	    $this->getServer()->send( $this->prepare('NICK', $nick) );
	}

	public function doPart( $channel )
	{
		if ( is_array($channel) )
		{
			$channel = implode(',', $channel);
		}
		
		$this->getServer()->send( $this->prepare('PART', $channel) );
	}
	
	/**
	 * Send a message to a channel or user
	 * 
	 * @param string $target
	 * @param string|array $msg
	 */
	public function doPrivmsg( $target, $msg )
	{
	    if ( !is_array($msg) )
	    {
	        $msg = array($msg);
	    }

	    foreach( $msg as &$str )
	    {
	        $this->getServer()->send( $this->prepare('PRIVMSG', array($target, $str)) );
	    }
	}
    
	public function doTopic( $channel, $topic = false )
	{
		$args = array($channel);
		if ($topic)
		{
			$args[] = $topic;
		}

		$this->getServer()->send( $this->prepare('TOPIC', $args) );
	}
    
	public function doQuit( $reason = 'zZz' )
	{
		$this->getServer()->send( $this->prepare('QUIT', array($reason)), true );
	}
    
    /**
     * This will break if the class doesn't have the added fingerprint AND has an _ in the name.
     * This should never happen since the pluginHandler will always use blueprints.
     */
    public function getName()
    {
        $className = get_class($this);
        if (preg_match("/([^\\\]+)_[^_]+$/", $className, $m))
        {
            return $m[1];
        }

        return $className;
    }

    public function getNick()
    {
        return $this->getServer()->getNick();
    }
    
    /**
     * Returns the server object.
     * @return \Bot\Connection\Server
     */
    public function getServer()
    {
        return \Bot\Bot::getInstance()->getServer();
    }
    
    /**
     * Returns an array of formated rows
     * 
     * @todo make it handle utf-8 strings, right now padding + utf-8 = fail
     * 
     * @param unknown_type $data
     * @param unknown_type $format
     * @param unknown_type $columns
     * @param unknown_type $columnWidth
     */
    protected function formatTableArray( $data, $format, $columns = 3, $columnWidth = 20 )
    {
        $buffer = array();
        $rows = array();
        $i = 0;
        foreach( $data as &$item )
        {
            ++$i;
            $buffer[] = vsprintf( $format, $item );
            
            if (count($buffer) == $columns || $i >= count($data))
            {
                $lineFormat = str_repeat("%-{$columnWidth}s ", count($buffer));
                $rows[] = vsprintf( $lineFormat, $buffer );
                $buffer = array();
            }
        }

        return $rows;
    }

	/**
	 * Builds a command with parameters from the argument list
	 * 
	 * @todo perhaps we should abandon this.
	 * 
	 * @param string $cmd
	 * @param string $arg1, $arg2, $arg3
	 * @throws \Exception
	 */
	protected function prepare()
	{
		$args = func_get_args();
		if ( empty($args) )
		{
			throw new \Exception('Prepare() called with no parameters...');
		}

		$buffer = array_shift($args);
        if ( !empty($args) )
        {
        	if ( count($args) == 1 && is_array($args[0]) )
        	{
        		$args = $args[0];
        		$args[] = ':'. array_pop($args);
        	}

            $buffer .= ' ' . preg_replace('/\v+/', ' ', implode(' ', $args));
        }

	    return trim($buffer) . "\r\n";
	}
    
}