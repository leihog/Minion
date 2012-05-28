<?php

return array(

	'irc' => array(
		'nick' => 'ExBot',
		'username' => 'exbot',
		'realname' => 'Example Minion Bot',

		'msg-rate' => '5:8',
		'never-give-up' => false,
		'server-cycle-wait' => 60,
	),

	'servers' => array(
		'tcp://localhost:6667',
		'tcp://localhost:7000',
	),

    // Plugins to load at start up
	'autoload' => array(
		'irc',
		'admin',
		'respond',
//		'users',
//        'debug',
//		'puppet',
//		'seen'
	),

	// Plugin settings
	'plugins' => array(
		'acl' => array(
			'default-level' => 0, /* Required user level for commands that aren't explicitly restricted. */
			'restrict-cmds' => true,
		),

		'channel' => array(
			'autojoin' => array(
				'#lair', '#secret:hemlis',
			),
		),

		'irc' => array(
			'altnicks' => array( '[ExBot]', 'ExBot-' )
		),

		'trac' => array(
			'trac.url' => 'https://www.trac.net',
			'login' => 'username:passwd',
			'channel' => '#lair'
		)
	),
);
