<?php
namespace Bot\Config;

Interface IStore
{
	public function __construct( $options );
	public function load();
}
