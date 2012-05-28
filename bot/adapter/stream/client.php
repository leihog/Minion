<?php
namespace Bot\Adapter\Stream;
use \Bot\Bot as Bot;

class Client
{
	protected $resource;
	protected $socketId;

	/**
	 * @todo add support for ipv6 host (ipv6 ips must be enclosed in [], ex: 'tcp://[df63::1]:80' )
	 * @todo test STREAM_CLIENT_ASYNC_CONNECT as a connect param
	 */
	public function connect( $transport, $host, $port )
	{
		$errorCode = $errorString = false;
		$host = "{$transport}://{$host}:{$port}";
		$this->resource = @stream_socket_client( $host, $errorCode, $errorString );
		if (!$this->resource)
		{
			Bot::log("Unable to connect to {$host}, reason: $errorString.");
			return false;
		}

		stream_set_blocking($this->resource, false);
		$this->socketId = stream_socket_get_name($this->resource, true);
		return true;
	}
	
	public function disconnect()
	{
		fclose($this->getResource());
	}

	/**
	 * Returns the network resource
	 * @return resource
	 */
	public function getResource()
	{
		return $this->resource;
	}

	public function getSocketId()
	{
		return $this->socketId;
	}

	public function isConnected()
	{
		$info = stream_get_meta_data($this->resource);
		if ( $info['eof'] || $info['timed_out'] )
		{
			return false;
		}
		return true;
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
	public function write( $string )
	{
		$bytesWritten = 0;
		$writeAttempts = 0;
		$stringLength = strlen($string);

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

			if ($writeAttempts == 3)
			{ /** @todo handle this better. maybe close/remove socket? */
				throw new \Exception('Unable to write to socket...');
			}
		}
	}


}
