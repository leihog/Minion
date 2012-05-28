<?php
namespace Bot\Log;

interface IWriter
{
	/**
	 * @param string $msg
	 */
	public function log($msg);
}
