<?php
namespace Bot\Plugin;

abstract class Plugin
{
    /**
     * This will break if the class doesn't have the added fingerprint AND has an _ in the name.
     * This should never happen since the pluginHandler will always use blueprints.
     */
    public function getName()
    {
        $className = get_class($this);
        if (preg_match("/([^\\\]+)_[^_]+$/", $className, $m))
        {
            return $m[1];
        }

        return $className;
    }

    public function getNick()
    {
        return $this->getServer()->getNick();
    }

    /**
     * Returns the server object.
     * @return \Bot\Connection\Server
     */
    public function getServer()
    {
        return \Bot\Bot::getServer();
    }

    /**
     * Returns an array of formated rows
     *
     * @todo make it handle utf-8 strings, right now padding + utf-8 = fail
     *
     * @param unknown_type $data
     * @param unknown_type $format
     * @param unknown_type $columns
     * @param unknown_type $columnWidth
     */
    protected function formatTableArray( $data, $format, $columns = 3, $columnWidth = 20 )
    {
        $buffer = array();
        $rows = array();
        $i = 0;
        foreach( $data as &$item )
        {
            ++$i;
            $buffer[] = vsprintf( $format, $item );

            if (count($buffer) == $columns || $i >= count($data))
            {
                $lineFormat = str_repeat("%-{$columnWidth}s ", count($buffer));
                $rows[] = vsprintf( $lineFormat, $buffer );
                $buffer = array();
            }
        }

        return $rows;
    }

    /**
     * Will try to execute the method on the server object.
     *
     * @param string $method
     * @param array $params
     */
    public function __call( $method, $params )
    {
        if ( substr($method, 0, 2) == 'do' )
        {
            $server = $this->getServer();
            if ( method_exists($server, $method) )
            {
                call_user_func_array(array($server, $method), $params);
            }
        }
    }
}