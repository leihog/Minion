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
    
    protected $valid = true;
    
    public function __construct( $cmdName, $parameters )
    {
        $this->name = strtolower($cmdName);
        $this->parameters = $parameters;
    }

    public function checkAcl()
    {
        foreach( self::$aclHandlers as &$handler )
        {
            if ( !$handler->checkAcl($this) )
            {
                return false;
            }
        }

        return true;
    }

    public function execute()
    {
        if ( !$this->checkAcl() )
        {
            return;
        }

        if ( !isset(self::$commands[$this->name]) )
        {
            if ( method_exists($this->event, 'isFromChannel') && $this->event->isFromChannel() )
            {
                return;
            }

            $this->respond('what?');
            return;
        }

        $method = self::$commands[$this->name];
        $parameters = preg_split("/ (?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $this->getParameters(), $method['total']); /** @todo handle single quotes */         
        array_unshift($parameters, $this);

        try
        {
            call_user_func_array($method['pointer'], $parameters);
        }
        catch( \Exception $e )
        {
            /** @todo send to errorlog */
            echo $e->getMessage(), "\n";
        }
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

    public function getConnection()
    {
        return $this->event->getConnection();
    }
    
    public function respond($string)
    {
        if ( $this->getConnection() instanceOf Connection\Server )
        {
            $this->getConnection()->doPrivmsg($this->event->getSource(), $string);
        }
    }

    public function setEvent( $event )
    {
        $this->event = $event;
    }
        
    // static //

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