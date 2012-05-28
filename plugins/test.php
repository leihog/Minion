<?php
namespace Bot\Plugin;

use Bot\Bot;

class Test extends Plugin
{
	public function cmdTest( \Bot\Event\Irc $event )
	{
		$event->getServer()->doPrivmsg('#lair', 'hej');
	}

	public function cmdVersion( \Bot\Event\Irc $event )
	{
		$event->getServer()->doPrivmsg($event->getSource(), 'Version: '. get_class($this));
	}

	public function cmdFormat( \Bot\Event\Irc $event )
	{
		$data = array(
			array( 'level' => 5, 'name' => 'godis' ),
			array( 'level' => 10, 'name' => 'kallaspuff' ),
			array( 'level' => 2, 'name' => 'lala' ),
			array( 'level' => 1, 'name' => 'ahlgrens bilar' ),
			array( 'level' => 6, 'name' => 'paron' ),
			array( 'level' => 100, 'name' => 'reload' ),
			array( 'level' => 3, 'name' => 'load' ),
			array( 'level' => 0, 'name' => 'users' ),
			array( 'level' => 12, 'name' => 'cmds' ),
		);

		$rows = $this->formatTableArray( $data, "[%3s] %-14s", 4, 20 );
		foreach($rows as $row)
		{
			$event->getServer()->doPrivmsg($event->getSource(), $row);
		}
	}
}
