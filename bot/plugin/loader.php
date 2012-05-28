<?php
namespace Bot\Plugin;
use Bot\Bot as Bot;

class Loader
{
	protected $blueprints = array();
	protected $blueprintFileResolver;

	public function setFileResolver( $resolver )
	{
		$this->blueprintFileResolver = $resolver;
	}

	/**
	 * Returns Current version of class
	 *
	 * @param string $class
	 */
	public function getBlueprint( $class, $namespace = null )
	{
		// Add namespace if it's missing, used in loadClass()
		if ($namespace && strpos($class, '\\') !== 0) {
			$class = $namespace . '\\' . $class;
		}

		// We only want to blueprint plugins
		if ( strpos($class, '\Bot\Plugin\\') === false ) {
			return $class;
		}

		if ( isset($this->blueprints[$class]) ) {
			return $this->blueprints[$class]['current'];
		}

		return $this->loadClass( $class );
	}

	protected function getBlueprintFile( $class )
	{
		if ( !isset($this->blueprintFileResolver) ) {
			throw new \Exception('No Blueprint file resolver');
		}

		return $this->blueprintFileResolver->resolve($class);
	}

	/**
	 * @todo Add support for 'implements'
	 *
	 * @param string $blueprint
	 * @throws \Exception
	 */
	public function loadClass( $blueprint )
	{
		if ($blueprint[0] != '\\') {
			$blueprint = '\\' . $blueprint;
		}

		$blueprintFile = $this->getBlueprintFile($blueprint);
		$contents = file_get_contents( $blueprintFile );

		$fingerprint = sha1($contents);
		$blueprintClass = substr($blueprint, strrpos($blueprint, '\\')+1) . '_' . $fingerprint;
		$qualifiedClass = $blueprint . '_' . $fingerprint;

		if ( class_exists($qualifiedClass, false) ) {
			Bot::log("Class '{$qualifiedClass}' already loaded...");
			return false;
		}

		$defaultNamespace = '';
		$tokens = token_get_all($contents);
		$contents = '';
		while ($token = next($tokens))
		{
			if (is_string($token)) {
				$contents .= $token;
			} else {
				if ( $token[0] == T_CLASS ) {
					$type = $token[0];

					while ($token && (!is_array($token) || $token[0] != T_STRING))
					{
						$contents .= (is_array($token) ? $token[1] : $token);
						$token = next($tokens);
					}

					$token[1] = $blueprintClass;
				} else if ($token[0] == T_EXTENDS) {
					$className = '';
					$token = next($tokens);
					while ( ($token = next($tokens)) &&
						$token[0] == T_STRING || $token[0] == T_NS_SEPARATOR
					) {
						$className .= $token[1];
					}

					$className = $this->getBlueprint($className, $defaultNamespace);
					$contents .= "extends {$className}";
				} else if ($token[0] == T_NAMESPACE) {
					$token = next($tokens);
					while( ($token = next($tokens)) && ($token[0] == T_STRING || $token[0] == T_NS_SEPARATOR) )
					{
						$defaultNamespace .= $token[1];
					}

					$contents .= 'namespace '. $defaultNamespace;
					$defaultNamespace = '\\' . $defaultNamespace;
				} else if ($token[0] == T_NEW) {
					$className = '';
					$token = next($tokens);
					while ( ($token = next($tokens)) && $token[0] == T_STRING || $token[0] == T_NS_SEPARATOR)
					{
						$className .= $token[1];
					}

					$className = $this->getBlueprint( $className, $defaultNamespace );
					$contents .= "new {$className}";
				} else if ($token[0] == T_FILE) {
					$token[1] = "'" . $blueprintFile . "'";
				} else if ($token[0] == T_DIR) {
					$token[1] = "'" . dirname($blueprintFile) . "'";
				}
				/* HALT_COMPILER doesn't work because we can't correctly get the
 				 * offset from __COMPILER_HALT_OFFSET since our buffer is
				 * different from the file on disk. It could work if we counted
				 * the bytes while we were rewriting the contents, but is it
				 * worth it?
 				  else if ( $token[0] == T_HALT_COMPILER ) {
					$contents .= '__halt_compiler();';
				}
				*/

				$contents .= (is_array($token) ? $token[1] : $token);
			}
		}

		//echo $contents, "\n----\n";
		Bot::log( "Parsing {$blueprintFile}..." );
		eval($contents);

		if (!class_exists($qualifiedClass, false)) {
			throw new \Exception("Failed to load {$qualifiedClass} using {$blueprint} from {$blueprintFile}.");
		}

		$this->addBlueprint($blueprint, $qualifiedClass);
		return $qualifiedClass;
	}

	protected function addBlueprint($blueprint, $blueprintClass)
	{
		if ( isset($this->blueprints[$blueprint]) ) {
			$this->blueprints[$blueprint]['current'] = $blueprintClass;
			$this->blueprints[$blueprint]['copies'][] =  $blueprintClass;
		} else {
			$this->blueprints[$blueprint] = array(
				'current' => $blueprintClass,
				'copies' => array($blueprintClass)
			);
		}
	}

	/**
	 * Creates an instance of $class using the stored blueprint (classname)
	 *
	 * @param string $class
	 */
	public function createInstance()
	{
		if ( !func_num_args() ) {
			throw new Exception('createInstance called without a class name');
		}

		$args = func_get_args();
		$class = array_shift($args);

		// @todo check if class is fully namespaced

		$class = $this->getBlueprint( $class );
		if ( empty($args) || !method_exists($class_name,  '__construct') ) {
			return new $class();
		}

		$refClass = new ReflectionClass($class);
		return $refClass->newInstanceArgs($args);
	}
}
