<?php
namespace Bot\Plugin;

use Bot\Bot;

class Test extends Plugin 
{
    public function cmdTest( $cmd )
    {
        $cmd->respond('Testing 1, 2, 3...');
    }

    public function cmdVersion( $cmd )
    {
        $cmd->respond('Version: '. get_class($this));
    }

    public function cmdFormat( $cmd )
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
            $cmd->respond($row);
        }
    }
}