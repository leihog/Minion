<?php
namespace Bot;

class Log
{
	protected $writers = array();

	public function addWriter( $writer )
	{
		if ( is_callable($writer) || $writer instanceof Log\IWriter ) {
			$this->writers[] = $writer;
		}
	}

	public function put( $msg )
	{
		if ( !empty($this->writers) ) {
			// @todo Add filters and formatters to writers
			$msg = sprintf("[%s] %s \n", date('H:i:s d-m-Y'), $msg );
			foreach( $this->writers as $writer )
			{
				if ( is_callable($writer) ) {
					$writer($msg);
				} else {
					$writer->log( $msg );
				}
			}
		}
	}
}
