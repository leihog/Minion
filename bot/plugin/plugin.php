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

}