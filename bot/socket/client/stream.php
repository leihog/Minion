<?php
namespace Bot\Socket\Client;

/** @todo should events be handled in SocketHandler instead? */
abstract class Stream
{
	protected $resource;
	protected $socketId;
	
	protected $host;
	protected $port;
	protected $transport;
	
	public function __construct( $options )
	{
		foreach( $options as $key => $value )
		{
			$method = "set{$key}";
			if (method_exists($this, $method))
			{
				$this->$method($value);
			}
		}
	}
	
	/**
	 * 
	 * @todo add support for ipv6 host (ipv6 ips must be enclosed in [], ex: 'tcp://[df63::1]:80' )
	 * @todo test STREAM_CLIENT_ASYNC_CONNECT as a connect param
	 */
	public function connect()
	{
		$errorCode = $errorString = false;
		$host = "{$this->transport}://{$this->host}:{$this->port}";
		$this->resource = @stream_socket_client( $host, $errorCode, $errorString );
		if (!$this->resource)
		{
			echo "Unable to connect to {$host}, reason: $errorString.\n";
			return false;
		}

		stream_set_blocking($this->resource, false);
		$this->socketId = stream_socket_get_name($this->resource, true);
		\Bot\Bot::getSocketHandler()->addSocket($this);
		\Bot\Bot::getEventHandler()->raise( new \Bot\Event\Socket( 'Connect', array( 'socket' => $this ) ));

		return true;
	}
	
	public function disconnect()
	{
	    fclose($this->getResource());
		\Bot\Bot::getSocketHandler()->removeSocket($this);
		\Bot\Bot::getEventHandler()->raise( new \Bot\Event\Socket( 'Disconnect', array('socket' => $this) ));
		
		/** @todo destroy object */
	}
	
	public function getHost()
	{
		return $this->host;
	}
	
	public function getPort()
	{
		return $this->port;
	}
	
	/**
	 * Returns the actual socket resource
	 * @return resource socket
	 */
	public function getResource()
	{
		return $this->resource;
	}
	
	public function getSocketId()
	{
		return $this->socketId;
	}
	
	/**
	 * Reads up to $length bytes from the socket.
	 * 
	 * @param int $length
	 */
	public function read( $length = 2048 )
	{
		$buffer = '';
		while( ($data = fread($this->resource, $length)) !== false )
		{
			if ( ($bytesRead = strlen($data)) === 0 )
			{
				break;
			}
			
			$buffer .= $data;
			$length = ($length - $bytesRead);
			
			if ( $length <= 0 )
			{
				break;
			}
		}

		return $buffer;
	}
	
	/**
	 * @todo support utf8 (mb_strlen($string, '8bit');)
	 * 
	 * @param string $string
	 */
	protected function write( $string )
	{
		$stringLength = strlen($string);
		$bytesWritten = 0;
		$writeAttempts = 0;
		
		while ($bytesWritten < $stringLength)
		{
			$b = fwrite( $this->resource, substr($string, $bytesWritten) );
			if ($b === 0)
			{
				$writeAttempts++;
			}
			else
			{
				$bytesWritten += $b;
			}
			
			if ($writeAttempts == 3) {
				throw new \Exception('Unable to write to socket...'); /** @todo handle this better. maybe close/remove socket? */
			}
		}
	}
	
	public function setHost( $host )
	{
		$this->host = $host;
	}
	
	public function setPort( $port )
	{
		$this->port = $port;
	}
	
	public function setTransport( $transport )
	{
		$this->transport = $transport;
	}
}