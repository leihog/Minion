<?php
namespace Bot\Log;

class File implements IWriter
{
	protected $filepath;
	protected $pointer;

	public function __construct( $filepath )
	{
		// @todo validate that we can write the file.
		$this->filepath = $filepath;
		$this->pointer = fopen($filepath, 'w');
	}

	public function log( $msg )
	{
		fwrite($this->pointer, $msg);
	}

	public function __destruct()
	{
		fclose($this->pointer);
	}
}
