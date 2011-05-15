<?php
namespace Bot\Plugin;
use Bot\Bot;

class Users extends Plugin 
{
	/**
	 * @todo if no password is given then send the syntax.
	 * 
	 * @param \Bot\Command $cmd
	 * @param unknown_type $password
	 * @param unknown_type $username
	 */
	public function cmdIdentify( \Bot\Event\Irc $event, $password, $username = false )
	{
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
	    
	    if ($event->isFromChannel())
	    {
	        $this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() . ' IDENTIFY password [username]');
	        return;
	    }

        if ( \Bot\User::isIdentified( $hostmask ) )
        {
            $this->doPrivmsg($nick, 'Yes yes, I know who you are...');
            return;
        }

        $username = ($username ? $username : $nick);
		if ( \Bot\User::authenticate( $username, $password, $hostmask ) )
        {
            $this->doPrivmsg($nick, "Ah it's you again...");

            $user = \Bot\User::fetch( $username );
            if (!$user->hasHostmask($hostmask))
            {
                $user->addHostmask($hostmask);

                $this->doPrivmsg($nick, "Added '{$hostmask}' to your list of hostmasks.");
                $this->doPrivmsg($nick, sprintf('You now have %s hostmasks.', count($user->getHostmasks()) ));
            }
        }
        else
        {
            $this->doPrivmsg($nick, 'Authentication failed!');
        }
	}

	public function cmdRegister( \Bot\Event\Irc $event, $password = false )
	{
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();

	    if ($event->isFromChannel())
	    {
            $this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() . ' REGISTER password');
            return;
	    }

        if ( \Bot\User::isIdentified( $hostmask ) )
        {
            $this->doPrivmsg($nick, 'You are already registered!');
            return;
        }
        
        if ( empty($password) )
        {
    		$this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() . ' REGISTER password');
    		return;
    	}

    	if ( \Bot\User::isUser($nick) )
    	{
    		$this->doPrivmsg($nick, "A user with the name '{$nick}' is already registered.");
    		$this->doPrivmsg($nick, 'If this is you then identify yourself.');
    		$this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() .' IDENTIFY password');
			return;
    	}

    	if ( \Bot\User::create($hostmask, $password) )
    	{
    	    $this->doPrivmsg($nick, "You have now been registered as '{$nick}' using the hostmask '{$hostmask}'.");
    	}
	    else
    	{
    		$this->doPrivmsg($nick, 'Something went wrong when trying to register you. Please try again later.');
    		return;
    	}
    	/** @todo send welcome message, and explain features and stuff. */
	}

	public function cmdUsers( \Bot\Event\Irc $event )
	{
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();

        if ( $event->isFromChannel() )
        {
            $this->doPrivmsg($nick, 'Syntax: /msg '. $this->getNick() . ' USERS');
    		return;
    	}

    	$users = \Bot\User::fetchAll();
    	$userCount = count($users);
    	if (!$userCount)
    	{
    		$this->doPrivmsg($nick, 'No users found.');
    		return;
    	}

    	$this->doPrivmsg($nick, 'Users:');
    	
    	foreach( $users as &$user )
    	{
    	     $user = array( $user->getLevel(), $user->getName() );
    	}
    	unset($user);    	
    	$this->doPrivmsg($nick, $this->formatTableArray($users, "[%3s] %-14s", 4, 20));
	}

}