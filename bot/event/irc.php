<?php
namespace Bot\Event;

class Irc extends Event
{
	protected $hostmask = false;
	protected $raw = false;
	protected $channels = array();
	protected $server = null;

	/**
	 *
	 * @return \Bot\Hostmask
	 */
	public function getHostmask()
	{
		return $this->hostmask;
	}

	public function getRaw()
	{
		return $this->raw;
	}

	public function setHostmask( \Bot\Hostmask $hostmask )
	{
		$this->hostmask = $hostmask;
	}

	public function setRaw($raw)
	{
		$this->raw = $raw;
	}

    public function isFromChannel()
    {
        // Per the 2000 RFCs 2811 and 2812, channels may begin with &, #, +, or !
        if ( !empty($this->params[0]) && strspn($this->params[0], '#&+!', 0, 1) >= 1)
        {
            return true;
        }

        return false;
    }

	public function getSource()
	{
		if ($this->isFromChannel())
		{
			return $this->params[0];
		}

		return $this->hostmask->getNick();
	}

	public function getBotNick()
	{
		return $this->server->getNick();
	}

	public function getChannels()
	{
	    return $this->channels;
	}

	public function setChannels( $channels )
	{
	    $this->channels = $channels;
	}
	/**
	 * Returns the nick of the user that caused the event
	 * or null if no user was involved.
	 */
	public function getNick()
	{
		if ( !$this->hostmask ) {
			return null;
		}
		return $this->hostmask->getNick();
	}

	public function getServer()
	{
		return $this->server;
	}

	public function setServer( $server )
	{
		$this->server = $server;
	}
}
