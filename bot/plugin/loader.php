<?php
namespace Bot\Plugin;

use Bot\Bot as Bot;

class Loader
{
	protected $blueprintPath;
	protected $blueprints = array();

	public function __construct($path)
	{
		$this->blueprintPath = rtrim($path, '/') . '/';
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
		$class = str_replace(array('\Bot\Plugin\\', '\\'), array('', '/'), $class);
		if ($class == 'Plugin' ) {
			return 'bot/plugin/plugin.php';
		}

		return $this->blueprintPath . strtolower($class) . '.php';
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

		$relativeFilename = $this->getBlueprintFile($blueprint);
		$blueprintFile = realpath($relativeFilename);
		if (!$blueprintFile) {
			throw new \Exception("Unable to load file '{$relativeFilename}'");
		}

		$fingerprint = sha1_file($blueprintFile);
		$qualifiedClass = $blueprint . '_' . $fingerprint;
		if ( class_exists($qualifiedClass, false) ) {
			Bot::log("Class '{$qualifiedClass}' already loaded...");
			return false;
		}

		$contents = file_get_contents( $blueprintFile );
		$blueprintClass = substr($blueprint, strrpos($blueprint, '\\')+1) . '_' . $fingerprint;

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

				$contents .= (is_array($token) ? $token[1] : $token);
			}
		}

		//echo $contents, "\n----\n";
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
	 * @todo check if class is fully namespaced
	 * @param string $class
	 */
	public function createInstance()
	{
		if ( !func_num_args() ) {
			throw new Exception('createInstance called without a class name');
		}

		$args = func_get_args();
		$class = array_shift($args);
		$class = $this->getBlueprint( $class );
		if ( empty($args) || !method_exists($class,  '__construct') ) {
			return new $class();
		}

		$refClass = new ReflectionClass($class);
		return $refClass->newInstanceArgs($args);
	}
}
