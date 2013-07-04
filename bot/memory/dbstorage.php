<?php
namespace Bot\Memory;

use Bot\Bot as Bot;

class DbStorage /*implements iStorage */
{
	public function __construct()
	{
		// create table if it doesn't exist.
		// 'memory' => 'CREATE TABLE memory (key TEXT, value TEXT)',
	}

	public function save($data)
	{
		$db = Bot::GetDatabase();
		$update = $db->prepare("UPDATE memory SET value = :value WHERE key = :key");
		$insert = $db->prepare("INSERT INTO memory (key, value) VALUES(:key, :value)");

		foreach($data as $key => $value) {

			$params = array(
				':key' => $key,
				':value' => json_encode($value)
			);

			$update->execute($params);
			if ($update->rowCount() === 0) {
				$insert->execute($params);
			}
		}
	}

	public function load()
	{
		$data = null; // load data
		return $data;
	}
}
