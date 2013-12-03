<?php
namespace Bot\Connection;

Interface IConnection
{
	public function close($msg = null);

	/**
	 * Returns a Resource
	 *
	 * @link http://php.net/resource
	 * @return Resource
	 */
	public function getResource();
	/**
	 * Called when a read on the connection will not block.
	 */
	public function onCanRead();
	/**
	 * Called when a write on the connection will not block.
	 */
	public function onCanWrite();

	public function onClosed();
}
