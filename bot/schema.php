<?php
namespace Bot;

/**
 * @todo should handle different versions somehow.
 */
class Schema
{
	protected $name;
	protected $file;
	protected $tables;

	public function __construct($name, $filePath)
	{
		$this->name = $name;
		if (!file_exists($filePath) ||
			($this->file = fopen($filePath, 'r')) === false)
		{
			throw new \Exception('Unable to open schema for reading');
		}

		$this->extractCreateStatements();
	}

	public function install()
	{
		$db = Bot::getDatabase();
		$created = array();

		try {
			foreach ($this->tables as $t => &$stmt) {
				if ($db->hasTable($t)) {
					throw new \Exception("Table '{$t}' exists");
				}
				if ($db->execute($stmt) === false) {
					throw new \Exception('Unable to create tables.');
				} else {
					$created[] = $t;
				}
			}

			$i = 0;
			$pattern = '/^insert\sinto\s([a-z0-9_]+)\s.*;$/';
			while ( ($line = fgets($this->file)) !== false && ++$i) {
				if (preg_match($pattern, strtolower($line), $m) ) {
					if (!in_array($m[1], $this->tables)) {
						throw new \Exception('Trying to insert in to restricted table.');
					}

					if (!$db->execute(rtrim($line, ';'))) {
						throw new \Exception(
							"Unable to insert line '$i' from schema '{$this->name}'."
						);
					}
				}
			}

			$db->execute('INSERT INTO plugins (plugin) VALUES(?)', array($this->name));
			$pluginId = $db->lastInsertId();

			foreach(array_keys($this->tables) as $table) {
				$db->execute(
					'INSERT INTO plugin_tables (plugin, tablename) VALUES (?, ?)',
					array($pluginId, $table)
				);
			}

		} catch (\Exception $e) {
			if (!empty($created)) {
				foreach($created as $t) {
					$db->execute("DROP TABLE {$t}");
				}
			}
			throw $e;
		}
	}

	public function uninstall()
	{
		$db = Bot::getDatabase();
		foreach (array_keys($this->tables) as $t) {
			$db->execute("DROP TABLE {$t}");
		}
	}

	protected function extractCreateStatements()
	{
		$this->tables = array();
		$pattern = '/^create\stable\s([a-z0-9_]+)\s.*;$/';
		while (($line = fgets($this->file)) !== false) {
			if (preg_match($pattern, strtolower($line), $m)) {
				$this->tables[$m[1]] = rtrim($line, ';');
			}
			unset($m);
		}
	}

	/**
	 * @todo not sure that this should be here.
	 */
	public static function isInstalled($name)
	{
		$db = Bot::getDatabase();
		$isInstalled = $db->fetchScalar(
			'SELECT 1 FROM plugins WHERE plugin = :name',
			array('name'=>$name)
		);

		return (bool)$isInstalled;
	}
}
