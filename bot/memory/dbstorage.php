<?php
namespace Bot\Memory;

use Bot\Bot as Bot;

class DbStorage /*implements iStorage */
{
	protected $db;

	public function __construct($db)
	{
		$this->db = $db;
		// create table if it doesn't exist.
		// 'memory' => 'CREATE TABLE memory (key TEXT, value TEXT)',
	}

	public function save($data)
	{
		$update = $this->db->prepare("UPDATE memory SET value = :value WHERE key = :key");
		$insert = $this->db->prepare("INSERT INTO memory (key, value) VALUES(:key, :value)");

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
		$rows = $this->db->fetchAll('SELECT * FROM memory');

		$values = [];
		foreach($rows as $row) {
			$values[$row['key']] = json_decode($row['value'], true);
		}
		return $values;
	}
}
