<?php
namespace Bot\Plugin;
use Bot\Bot;

class Core extends Plugin 
{
	/**
	 * @todo if no password is given then send the syntax.
	 * 
	 * @param \Bot\Command $cmd
	 * @param unknown_type $password
	 * @param unknown_type $username
	 */
	public function cmdIdentify( \Bot\Command $cmd, $password, $username = false )
	{
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
	    
	    $server = $cmd->getConnection();
	    
	    if ($event->isFromChannel())
	    {
	        $server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' IDENTIFY password [username]');
	        return;
	    }

        if ( \Bot\User::isIdentified( $hostmask ) )
        {
            $server->doPrivmsg($nick, 'Yes yes, I know who you are...');
            return;
        }

        $username = ($username ? $username : $nick);
		if ( \Bot\User::authenticate( $username, $password, $hostmask ) )
        {
            $server->doPrivmsg($nick, "Ah it's you again...");

            $user = \Bot\User::fetch( $username );
            if (!$user->hasHostmask($hostmask))
            {
                $user->addHostmask($hostmask);

                $server->doPrivmsg($nick, "Added '{$hostmask}' to your list of hostmasks.");
                $server->doPrivmsg($nick, sprintf('You now have %s hostmasks.', count($user->getHostmasks()) ));
            }
        }
        else
        {
            $server->doPrivmsg($nick, 'Authentication failed!');
        }
	}

	public function cmdRegister( \Bot\Command $cmd, $password = false )
	{
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();

        $server = $cmd->getConnection();

	    if ($event->isFromChannel())
	    {
            $server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' REGISTER password');
            return;
	    }

        if ( \Bot\User::isIdentified( $hostmask ) )
        {
            $server->doPrivmsg($nick, 'You are already registered!');
            return;
        }
        
        if ( empty($password) )
        {
    		$server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' REGISTER password');
    		return;
    	}

    	if ( \Bot\User::isUser($nick) )
    	{
    		$server->doPrivmsg($nick, "A user with the name '{$nick}' is already registered.");
    		$server->doPrivmsg($nick, 'If this is you then identify yourself.');
    		$server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() .' IDENTIFY password');
			return;
    	}

    	if ( \Bot\User::create($hostmask, $password) )
    	{
    	    $server->doPrivmsg($nick, "You have now been registered as '{$nick}' using the hostmask '{$hostmask}'.");
    	}
	    else
    	{
    		$server->doPrivmsg($nick, 'Something went wrong when trying to register you. Please try again later.');
    		return;
    	}
    	/** @todo send welcome message, and explain features and stuff. */
	}

	public function cmdUsers( \Bot\Command $cmd )
	{
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
        $server = $cmd->getConnection();

        if ( $event->isFromChannel() )
        {
            $server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' USERS');
    		return;
    	}

    	$users = \Bot\User::fetchAll();
    	$userCount = count($users);
    	if (!$userCount)
    	{
    		$server->doPrivmsg($nick, 'No users found.');
    		return;
    	}

    	$server->doPrivmsg($nick, 'Users:');
    	
    	foreach( $users as &$user )
    	{
    	     $user = array( $user->getLevel(), $user->getName() );
    	}
    	unset($user);    	
    	$server->doPrivmsg($nick, $this->formatTableArray($users, "[%3s] %-14s", 4, 20));
	}

}