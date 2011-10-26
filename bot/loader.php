<?php
namespace Bot;

class Loader
{
	protected static $blueprints = array();

	protected static $blueprintFileResolver;

	public static function setFileResolver( $resolver )
	{
	    self::$blueprintFileResolver = $resolver;
	}

	/**
	 * Returns Current version of class
	 *
	 * @param string $class
	 */
	public static function getBlueprint( $class )
	{
	    if ( strpos($class, '\Bot\Plugin\\') === false ) /** @todo make this configurable */
	    {
	        return $class;
	    }

	    if ( isset(self::$blueprints[$class]) )
	    {
	        return self::$blueprints[$class]['current'];
	    }

	    return self::loadClass( $class );
	}

    protected static function getBlueprintFile( $class )
    {
        if ( !isset(self::$blueprintFileResolver) )
        {
            throw new \Exception('No Blueprint file resolver');
        }

        return self::$blueprintFileResolver->resolve($class);
    }

	protected static function getClass( $blueprint )
	{
	    return substr($blueprint, 0, strrpos($blueprint, '_'));
	}

	/**
	 * returns a qualified class name (class name including namespace)
	 * If $class is namespaced it's returned as is,
	 * if not then it's prepended with $namespace.
	 *
	 * @param string $class
	 * @param string $namespace
	 */
	protected static function getNamespaced( $class, $namespace )
	{
	    if (strpos($class, '\\') !== 0)
	    {
	        $class = $namespace . '\\' . $class;
	    }

	    return $class;
	}

	/**
	 *
	 * @todo Add support for 'implements'
	 * @todo not sure i like forceReload, perhaps it should be replaced by something else.
	 *
	 * @param string $blueprint
	 * @throws \Exception
	 */
	public static function loadClass( $blueprint, $forceReload = false )
	{
	    if ($blueprint[0] != '\\')
	    {
	        $blueprint = '\\' . $blueprint;
	    }

	    $blueprintFile = self::getBlueprintFile($blueprint);
	    $contents = file_get_contents( $blueprintFile );

	    $fingerprint = sha1($contents);
	    $blueprintClass = substr($blueprint, strrpos($blueprint, '\\')+1) . '_' . $fingerprint;
	    $qualifiedClass = $blueprint . '_' . $fingerprint;

	    if ( class_exists($qualifiedClass, false) ) /** @todo handle differently, perhaps we remove force reload... */
	    {
	        if ( !$forceReload || !($parentClass = get_parent_class($qualifiedClass)) || !self::loadClass( self::getClass($parentClass), $forceReload ) )
	        {
	            echo "class '{$qualifiedClass}' already loaded... \n"; /** @todo handle better */
	            return false;
	        }

	        echo "Class '{$qualifiedClass}' already loaded... force reloading.\n";

            $fingerprint = time();
    	    $blueprintClass = substr($blueprint, strrpos($blueprint, '\\')+1) . '_' . $fingerprint;
    	    $qualifiedClass = $blueprint . '_' . $fingerprint;
	    }

	    $defaultNamespace = '';
        $tokens = token_get_all($contents);
        $contents = '';
        while ($token = next($tokens))
        {
            if (is_string($token))
            {
                $contents .= $token;
            }
            else
            {
                if ( $token[0] == T_CLASS )
                {
                    $type = $token[0];

                    while ($token && (!is_array($token) || $token[0] != T_STRING))
                    {
                        $contents .= (is_array($token) ? $token[1] : $token);
                        $token = next($tokens);
                    }

                    $token[1] = $blueprintClass;
                }
                else if ($token[0] == T_EXTENDS)
                {
                    $className = '';
                    $token = next($tokens);
                    while ( ($token = next($tokens)) && $token[0] == T_STRING || $token[0] == T_NS_SEPARATOR)
                    {
                        $className .= $token[1];
                    }

                    $className = self::getBlueprint( self::getNamespaced( $className, $defaultNamespace ) );
                    $contents .= "extends {$className}";
                }
                else if ($token[0] == T_NAMESPACE)
                {
                    $token = next($tokens);
                    while( ($token = next($tokens)) && ($token[0] == T_STRING || $token[0] == T_NS_SEPARATOR) )
                    {
                        $defaultNamespace .= $token[1];
                    }

                    $contents .= 'namespace '. $defaultNamespace;
                    $defaultNamespace = '\\' . $defaultNamespace;
                }
                else if ($token[0] == T_NEW)
                {
                    $className = '';
                    $token = next($tokens);
                    while ( ($token = next($tokens)) && $token[0] == T_STRING || $token[0] == T_NS_SEPARATOR)
                    {
                        $className .= $token[1];
                    }

                    $className = self::getBlueprint( self::getNamespaced( $className, $defaultNamespace ) );
                    $contents .= "new {$className}";
                }
                else if ($token[0] == T_FILE)
                {
                    $token[1] = "'" . $blueprintFile . "'";
                }
                else if ($token[0] == T_DIR)
                {
                    $token[1] = "'" . dirname($blueprintFile) . "'";
                }

                $contents .= (is_array($token) ? $token[1] : $token);
            }
        }


        //echo $contents, "\n----\n";
        error_log( "Parsing {$blueprintFile}..." ); /** @todo replace with proper logging */

        eval($contents);

        if (!class_exists($qualifiedClass, false))
        {
            throw new \Exception("Failed to load {$qualifiedClass} using {$blueprint} from {$blueprintFile}.");
        }

        self::addBlueprint($blueprint, $qualifiedClass);

        return $qualifiedClass;
	}

	protected static function addBlueprint($blueprint, $blueprintClass)
	{
	    if ( isset(self::$blueprints[$blueprint]) )
	    {
	        self::$blueprints[$blueprint]['current'] = $blueprintClass;
	        self::$blueprints[$blueprint]['copies'][] =  $blueprintClass;
	    }
	    else
	    {
	        self::$blueprints[$blueprint] = array(
	            'current' => $blueprintClass,
	            'copies' => array($blueprintClass)
	        );
	    }
	}

	public static function isInstanceOf( $object, $class )
	{
	    if ( $object instanceOf $class )
	    {
	        return true;
	    }

	    if ( isset(self::$blueprints[$class]) )
	    {
	        foreach( array_reverse(self::$blueprints[$class]['copies']) as $carbon )
	        {
	            if ( $object instanceOf $carbon )
	            {
	                return true;
	            }
	        }
	    }

	    return false;
	}

	/**
	 * Creates an instance of $class using the stored blueprint (classname)
	 *
	 * @param string $class
	 */
	public static function createInstance()
	{
	    if ( !func_num_args() )
	    {
	        throw new Exception('createInstance called without a class name');
	    }

	    $args = func_get_args();
        $class = array_shift($args);

        // @todo class must be fully namespaced...

        $class = self::getBlueprint( $class );

        if ( empty($args) || !method_exists($class_name,  '__construct') )
        {
            return new $class();
        }

        $refClass = new ReflectionClass($class);
        return $refClass->newInstanceArgs($args);
	}

}