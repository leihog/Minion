<?php

return array(

	'irc' => array(
        'nick' => 'Minion',
        'username' => 'Minion',
        'realname' => 'Minion Bot',

    	'channels' => array(
    		'#lair',
    	),

    	'msg-rate' => '5:8',
    	'never-give-up' => true,
    	'server-cycle-wait' => 60,
	),

    'servers' => array(
        'tcp://localhost:6667',
    ),

	'data-folder' => 'data',

	// Plugins to load at start up
	'autoload' => array(
	    'admin',
		'irc'
	),
	
	// Plugin settings
	'plugins' => array(
	    'acl' => array(
	        'default-level' => 0,
	        'restrict-cmds' => true,
	    ),

    	'irc' => array(
    	    'altnicks' => array( '[Minion]', 'Minion-' )
    	),

    	'trac' => array(
    		'trac.url' => 'https://trac.flattr.net',
    		'login' => 'status:statusfraggeln',
    	    'channel' => '#lair'
    	)
	),
);
