<?php
namespace Bot\Plugin;
use Bot\Bot;

class Acl extends Plugin 
{
    protected $accessControlList;
    protected $defaultCommandLevel;
    protected $restrictCmds;

    public function init()
    {
        $this->accessControlList = array();
        $this->defaultCommandLevel = Bot::getConfig('plugins/acl/default-level', 0);
        $this->restrictCmds = Bot::getConfig('plugins/acl/restrict-cmds', false);

        if ( !Bot::getDatabase()->isInstalled($this->getName()) )
        {
            echo "Installing plugin ", $this->getName(), "\n";
            $db = Bot::getDatabase();
            $db->install( $this->getName(), 'acl.schema' );
        }
        
        $this->loadAcl();
        \Bot\Command::addAclHandler( $this );
        
        echo "Acl loaded with ", count($this->accessControlList), " commands.\n";
    }

    public function checkACL( $cmd )
    {
        $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();

        $currentLevel = 0;
        if ( \Bot\User::isIdentified( $hostmask ) )
        {
            $user = \Bot\User::getIdentifiedUser( $hostmask );
            $currentLevel = $user->getLevel();
        }

        if ( !isset($this->accessControlList[ $cmd->getName() ]) )
        {
            return true;
        }

        if ( $this->accessControlList[ $cmd->getName() ] <= $currentLevel )
        {
            return true;
        }

        return false;
    }

	public function cmdCmds( \Bot\Command $cmd )
	{
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
	    $server = $cmd->getConnection();

        if ($event->isFromChannel())
	    {
	        $server->doPrivmsg($nick, 'Syntax: /msg '. $server->getNick() . ' CMDS');
	        return;
	    }

	    $user = \Bot\User::getIdentifiedUser($hostmask);
	    $level = ( $user ? $user->getLevel() : 0);
		$cmds = \Bot\Command::getCommands();

		$userCmds = array();
		foreach( $cmds as &$cmd )
		{
		    $cmdLevel = ( isset($this->accessControlList[$cmd]) ? $this->accessControlList[$cmd] : $this->defaultCommandLevel );
		    if ( $cmdLevel > $level )
		    {
		        continue;
		    }

		    $userCmds[] = array($cmdLevel, $cmd);
		}

		if ( ($cmdCount = count($userCmds)) )
		{
		    $server->doPrivmsg($nick, sprintf('%s available command%s', $cmdCount, ($cmdCount == 1 ? '':'s') ));
		    $server->doPrivmsg($nick, $this->formatTableArray( $userCmds, "[%3s] %-14s", 4, 20 ));
		}
	}

    public function cmdSetacl( \Bot\Command $cmd, $cmdName, $level = false )
    {
	    $event = $cmd->getEvent();
        $hostmask = $event->getHostmask();
        $nick = $hostmask->getNick();
	    $server = $cmd->getConnection();

    	if ( !$level )
    	{
    		$this->removeAcl($cmdName);
    		$server->doPrivmsg($nick, "Removed ACL for {$cmdName}.");
    	}
    	else
    	{
    		$this->setAcl($cmdName, $level);
    		$server->doPrivmsg($nick, "Updated ACL for {$cmdName}.");
    	}
    }

    protected function loadAcl()
    {
        $db = Bot::getDatabase();
    	$list = $db->fetchAll('SELECT cmd, level FROM acl');
    	foreach($list as &$acl)
    	{
    	    $this->accessControlList[ $acl['cmd'] ] = $acl['level'];
    	}
    }
    
    protected function removeAcl($cmd)
    {
    	if ( isset($this->accessControlList[$cmd]) )
    	{
    		$db = Bot::getDatabase();
    		$db->execute('DELETE FROM acl WHERE cmd = :cmd', compact('cmd') );
    		return true;
    	}

    	return false;
    }
    
    protected function setAcl($cmd, $level)
    {
    	$db = Bot::getDatabase();
    	
    	if ( isset($this->accessControlList[$cmd]) )
    	{
    		if ( $this->accessControlList[$cmd] != $level )
    		{
    			$db->execute('UPDATE acl SET level = :level WHERE cmd = :cmd', compact('cmd', 'level') );
    		}
    	}
    	else
    	{
    		$db->execute('INSERT INTO acl (cmd, level) VALUES (:cmd, :level)', compact('cmd', 'level') );
    	}
    }
}