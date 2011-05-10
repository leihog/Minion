<?php
namespace Bot;

abstract class Loader
{
	protected static $blueprints = array();

	/**
	 * Returns Current version of class
	 * 
	 * @param string $class
	 */
	public function getBlueprint( $class )
	{
	    if ( isset(self::$blueprints[$class]) )
	    {
	        return self::$blueprints[$class]['current'];
	    }

	    return $this->loadClass( $class );
	}

	protected function getClass( $blueprint )
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
	protected function getNamespaced( $class, $namespace )
	{
	    if (strpos($class, '\\') === false)
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
	public function loadClass( $blueprint, $forceReload = false )
	{
	    if ($blueprint[0] != '\\')
	    {
	        $blueprint = '\\' . $blueprint;
	    }

	    $blueprintFile = strtolower( str_replace('\\', '/', ltrim($blueprint, '\\') ) ) . '.php';
	    $contents = file_get_contents( $blueprintFile );

	    $fingerprint = sha1($contents);
	    $blueprintClass = substr($blueprint, strrpos($blueprint, '\\')+1) . '_' . $fingerprint;
	    $qualifiedClass = $blueprint . '_' . $fingerprint;

	    if ( class_exists($qualifiedClass, false) )
	    {
	        if ( !$forceReload || !($parentClass = get_parent_class($qualifiedClass)) || !$this->loadClass( $this->getClass($parentClass), $forceReload ) )
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
                if ($token[0] == T_CLASS || $token[0] == T_EXTENDS)
                {
                    $type = $token[0];
                    
                    while ($token && (!is_array($token) || $token[0] != T_STRING))
                    {
                        $contents .= (is_array($token) ? $token[1] : $token);
                        $token = next($tokens);
                    }

                    if ($type == T_CLASS)
                    {
                        $token[1] = $blueprintClass;
                    }
                    else // Extends
                    {
                        $token[1] = $this->getBlueprint( $this->getNamespaced( $token[1], $defaultNamespace ) );
                    }
                }
                else if ($token[0] == T_NAMESPACE)
                {
                    $token = next($tokens);
                    while( ($token = next($tokens)) && ($token[0] == 307 || $token[0] == 380) )
                    {
                        $defaultNamespace .= $token[1];
                    }

                    $contents .= 'namespace '. $defaultNamespace;
                    $defaultNamespace = '\\' . $defaultNamespace;
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

        eval($contents); /** @todo would be nice to use the stream include function instead. */

        if (!class_exists($qualifiedClass, false))
        {
            throw new \Exception("Failed to load {$qualifiedClass} using {$blueprint} from {$blueprintFile}.");
        }
        
        $this->addBlueprint($blueprint, $qualifiedClass);
        
        return $qualifiedClass;
	}

	protected function addBlueprint($blueprint, $blueprintClass)
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
	 * 
	 * @param string $class
	 */
	public function cloneObject( $class )
	{
	    $class = $this->getBlueprint( $class );
	    return new $class();
	}
			 
}