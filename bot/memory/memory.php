<?php
namespace Bot\Memory;

use Bot\Bot as Bot;

/**
 * Keys:
 * In it's basic form a key is just a string containing a-z0-9 or a dot(.).
 *
 * For keys that contain arrays, the array values can be accessed using
 * the array[key] syntax. An example array key could be: 'foo[bar]' which
 * would return the value of the element indexed by 'bar'.
 * To append a value to an array one would write put('foo[]', 'myval') or
 * put('foo[bar]', 'myval') for an associative array.
 *
 * The get() method also supports the wildcard(*) syntax for fetching multiple
 * values matching the wildcard. This comes in handy when keys are grouped
 * or namespaced with the same string. Example: 'foobar.*' would match
 * 'foobar.abc' and 'foobar.zxy'.
 *
 * Persistance:
 * By default the memory is non persistent, meaning that on a restart everything 
 * held in memory will be lost. Memory can be configured with a storage module
 *
 * @todo implement the wildcard syntax or remove it.
 */
class Memory
{
	protected $storage = null;
	protected $values;
	protected $nullPointer = null;

	public function __construct($storage = null)
	{
		$values = [];
		if ($storage) {
			$this->storage = $storage;
			$values = $this->storage->load();
		}

		$this->values = $values;
	}

	/**
	 * Increment the value of $key
	 * This only works on numeric values.
	 * An exception will be thrown if value of $key is not numeric.
	 * If $key isn't set, $key will be created with value 1.
	 *
	 * @param string $key
	 * @return void
	 * @throw Exception
	 */
	public function inc($key)
	{
		$storedValue = &$this->doGet($key);
		if ($storedValue === null) {
			$this->put($key, 1);
			return 1;
		} else if (!is_numeric($storedValue)) {
			throw new \Exception('Trying to increment a non numeric value.');
		}

		return ++$storedValue;
	}

	/**
	 * Decrement the value of $key
	 * This only works on numeric values.
	 * An exception will be thrown if value of $key is not numeric.
	 * If $key isn't set, $key will be created with value -1.
	 *
	 * @param string $key
	 * @return void
	 * @throw Exception
	 */
	public function dec($key)
	{
		$storedValue = &$this->doGet($key);
		if ($storedValue === null) {
			$this->put($key, -1);
			return -1;
		} else if (!is_numeric($storedValue)) {
			throw new \Exception('Trying to increment a non numeric value.');
		}

		return --$storedValue;
	}

	public function get($key)
	{
		return $this->doGet($key);
	}

	protected function &getArray($key)
	{
		$baseKey = substrto($key, '[');
		$storedValue = &$this->doGet($baseKey);
		$index = $this->getArrayIndex($key);

		if (!$storedValue && !is_array($storedValue)) {
			return $this->nullPointer;
		}

		if ($index) {
			if (!isset($storedValue[$index])) {
				return $this->nullPointer;
			}
			return $storedValue[$index];
		} else {
			return $storedValue;
		}
	}

	/**
	 * Will fetch the value of $key.
	 * If $key contains '[]' it will be assumed to contain an array and the
	 * value will be handled as such.
	 *
	 * @param string $key
	 * @return mixed
	 */
	protected function &doGet($key)
	{
		if (strstr($key, '[')) { // expecting array
			return $this->getArray($key);
		}

		if (isset($this->values[$key])) {
			return $this->values[$key];
		}
		return $this->nullPointer;
	}

	/**
	 * Will store a value with the specified key.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function put($key, $value)
	{
		$this->doPut($key, $value);
	}

	/**
	 * Will store $value in $key
	 * If $key is unset it will be created.
	 * if value of $key is not an array an exception will be thrown.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 * @throws \Exception
	 */
	protected function &putArray($key, $value)
	{
		$baseKey = substrto($key, '[');
		$storedValue = &$this->doGet($baseKey);
		$index = $this->getArrayIndex($key);

		if ($storedValue === null) {
			$storedValue = &$this->doPut($baseKey, array());
		}

		if (!is_array($storedValue)) {
			throw new \Exception('Key is not an array');
		}

		if ($index) {
			$storedValue[$index] = $value;
			return $storedValue[$index];
		} else {
			$storedValue[] = $value;
			return $storedValue;
		}
	}

	protected function &doPut($key, $value)
	{
		if (strstr($key, '[')) { // expecting array
			return $this->putArray($key, $value);
		}

		$this->values[$key] = $value;
		return $this->values[$key];
	}

	/**
	 * Will remove the key and it's value from memory.
	 *
	 * @param string $key
	 * @return void
	 */
	public function forget($key)
	{
		// @todo implement
	}
	/**
	 * returns the key array index or false if one wasn't provided.
	 */
	protected function getArrayIndex($key)
	{
		if (preg_match('/[^\[]+\[(?P<index>[^\]]+)\]/i', $key, $m)) {
			return $m['index'];
		}
		return false;
	}
	/**
	 * @todo only save modified elements
	 */
	public function save()
	{
		if (!$this->storage) {
			return;
		}
		Bot::log('Storing memories');
		$this->storage->save($this->values);
	}
}
