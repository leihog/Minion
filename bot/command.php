<?php
namespace Bot;

class Command
{
    protected static $aclHandlers = array();
    protected static $namePrefix = 'cmd';
    protected static $commands = array();

    protected $name;
    protected $parameters;
    protected $event;

    public function __construct( $event, $cmdName, $parameters )
    {
        $this->event = $event;
        $this->name = strtolower($cmdName);
        $this->parameters = $parameters;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function getEvent()
    {
        return $this->event;
    }

    // static //

    public static function checkAcl( $cmd )
    {
        foreach( self::$aclHandlers as &$handler )
        {
            if ( !$handler->checkAcl($cmd) )
            {
                return false;
            }
        }

        return true;
    }

    public static function execute( $event, $name, $args )
    {
        if ( !self::checkAcl( new self($event, $name, $args) ) )
        {
            return false;
        }

        $method = self::$commands[$name];
        $parameters = preg_split("/ (?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $args, $method['total']); // @todo handle single quotes         
        array_unshift($parameters, $event);

        try
        {
            call_user_func_array($method['pointer'], $parameters);
            return true;
        }
        catch( \Exception $e )
        {
            // @todo send to errorlog
            echo $e->getMessage(), "\n";
            return false;
        }
    }

    public static function addAclHandler( $handler )
    {
        if ( method_exists($handler, 'getName') )
        {
            $name = $handler->getName();
        }
        else
        {
            $name = get_class($handler);
        }

        self::$aclHandlers[$name] = $handler;
    }

    /**
     * Extracts command pointers from the given class or object
     * 
     * @param string|object $class
     */
    public static function extractCommandPointers($class)
    {
        $reflector = new \ReflectionClass($class);
        foreach ($reflector->getMethods() as $method)
        {
            $methodName = $method->getName();
            if ( strpos($methodName, self::$namePrefix) === 0 )
            {
                $cmdName = strtolower(substr($methodName, strlen(self::$namePrefix)));
                if ( isset(self::$commands[$cmdName]) )
                {
                    continue;
                }

                self::$commands[$cmdName] = array(
                    'pointer' => array($class, $methodName),
                    'total' => $method->getNumberOfParameters() -1,
                    'required' => $method->getNumberOfRequiredParameters() -1
                );
            }
        }
    }
    
    public static function getCommands()
    {
        return array_keys(self::$commands);
    }

    public static function has( $name )
    {
        if ( isset(self::$commands[$name]) )
        {
            return true;
        }

        return false;
    }
    
    public static function removeCommandPointers( $class )
    {
        $reflector = new \ReflectionClass($class);
        foreach ($reflector->getMethods() as $method)
        {
            $methodName = $method->getName();
            if ( strpos($methodName, self::$namePrefix) === 0 )
            {
                $cmdName = strtolower(substr($methodName, strlen(self::$namePrefix)));
                unset(self::$commands[$cmdName]);
            }
        }
    }
    
    public static function removeAclHandler( $handler )
    {
        if ( method_exists($handler, 'getName') )
        {
            $name = $handler->getName();
        }
        else
        {
            $name = get_class($handler);
        }

        if ( isset(self::$aclHandlers[$name]) )
        {
            unset(self::$aclHandlers[$name]);
        }
    }
}