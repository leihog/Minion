<?php
namespace Bot;
use Bot\Bot as Bot;

class Command
{
	protected $aclHandlers = array();
	protected $commands = array();
	protected $namePrefix = 'cmd';

	protected function checkAcl( $cmdName, $event )
	{
		foreach( $this->aclHandlers as &$handler )
		{
			if ( !$handler->checkAcl($cmdName, $event) )
			{
				return false;
			}
		}

		return true;
	}

	public function execute( $event, $name, $args )
	{
		if ( !$this->checkAcl( $name, $event ) )
		{
			return false;
		}

		$method = $this->commands[$name];
		$parameters = preg_split("/ (?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $args, $method['total'], PREG_SPLIT_NO_EMPTY); // @todo handle single quotes
		array_unshift($parameters, $event);

		try
		{
			call_user_func_array($method['pointer'], $parameters);
			return true;
		}
		catch( \Exception $e )
		{
			Bot::log($e->getMessage());
			return false;
		}
	}

	public function addAclHandler( $handler )
	{
		if ( method_exists($handler, 'getName') )
		{
			$name = $handler->getName();
		}
		else
		{
			$name = get_class($handler);
		}

		$this->aclHandlers[$name] = $handler;
	}

	/**
		* Extracts command pointers from the given class or object
		*
		* @param string|object $class
		*/
	public function extractCommandPointers($class)
	{
		$reflector = new \ReflectionClass($class);
		foreach ($reflector->getMethods() as $method)
		{
			$methodName = $method->getName();
			if ( strpos($methodName, $this->namePrefix) === 0 )
			{
				$cmdName = strtolower(substr($methodName, strlen($this->namePrefix)));
				if ( isset($this->commands[$cmdName]) )
				{
					continue;
				}

				$this->commands[$cmdName] = array(
					'pointer' => array($class, $methodName),
					'total' => $method->getNumberOfParameters() -1,
					'required' => $method->getNumberOfRequiredParameters() -1
				);
			}
		}
	}

	public function getCommands()
	{
		return array_keys($this->commands);
	}

	public function has( $name )
	{
		if ( isset($this->commands[$name]) )
		{
			return true;
		}

		return false;
	}

	public function onLoadPlugin( \Bot\Event\Plugin $event )
	{
		$plugin = Bot::getPluginHandler()->getPlugin($event->getPlugin());
		$this->extractCommandPointers($plugin);
	}

	public function onUnloadPlugin( \Bot\Event\Plugin $event )
	{
		$plugin = Bot::getPluginHandler()->getPlugin($event->getPlugin());
		$this->removeCommandPointers($plugin);
		$this->removeAclHandler($plugin);
	}

	public function onPrivmsg( \Bot\Event\Irc $event )
	{
		$publicCommandPrefix = "!";
		list($source, $input) = $event->getParams();
		$input = trim($input);
		if ($event->isFromChannel() && $input[0] != $publicCommandPrefix) {
			return false;
		}

		list($cmdName, $parameters) = array_pad( explode(' ', $input, 2), 2, null );
		if ( $cmdName[0] == $publicCommandPrefix ) {
			$cmdName = substr($cmdName, 1);
		}

		if ( !$this->has($cmdName) ) {
			return false;
		}

		$this->execute( $event, $cmdName, $parameters );
		return \Bot\Event\Dispatcher::HALT_EXECUTION;
	}

	public function removeCommandPointers( $class )
	{
		$reflector = new \ReflectionClass($class);
		foreach ($reflector->getMethods() as $method)
		{
			$methodName = $method->getName();
			if ( strpos($methodName, $this->namePrefix) === 0 )
			{
				$cmdName = strtolower(substr($methodName, strlen($this->namePrefix)));
				unset($this->commands[$cmdName]);
			}
		}
	}

	public function removeAclHandler( $handler )
	{
		if ( method_exists($handler, 'getName') )
		{
			$name = $handler->getName();
		}
		else
		{
			$name = get_class($handler);
		}

		if ( isset($this->aclHandlers[$name]) )
		{
			unset($this->aclHandlers[$name]);
		}
	}
}
