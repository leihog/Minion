<?php
namespace Bot;

class Database
{
	protected $db;
	protected $internalTables = array(
		'users' => 'CREATE TABLE users (id INTEGER, username TEXT, password TEXT, salt TEXT, level INTEGER, added INTEGER, PRIMARY KEY(id ASC))',
		'hostmasks' => 'CREATE TABLE hostmasks (id INTEGER, user_id INTEGER, mask TEXT, PRIMARY KEY(id ASC))',
		'plugins' => 'CREATE TABLE plugins (id INTEGER, plugin TEXT, PRIMARY KEY(id ASC))',
		'plugin_tables' => 'CREATE TABLE plugin_tables (plugin INTEGER, tablename TEXT)',
	);

	public function __construct()
	{
		if (!extension_loaded('PDO') || !extension_loaded('pdo_sqlite')) {
			throw new \Exception('PDO and pdo_sqlite extensions must be installed');
		}

		$dbFile = Bot::getDir('data') . '/bot.db';
		$this->db = new \PDO('sqlite:' . $dbFile);
		
		$this->initialize(); // @todo this should move out of here.
	}

	protected function initialize()
	{
		foreach(array_keys($this->internalTables) as $table) {
			if (!$this->hasTable($table)) {
				$this->createTable($table);
			}
		}

	}
	protected function doExecute($query, $params)
	{
		try {
			if ( !is_array($params) || empty($params) ) {
				$r = $this->db->exec($query);
				if (false !== $r) {
					return $r;
				}
				$error =$this->db->errorInfo();
				$errorMsg = $error[2];
			} else {
				$q = $this->prepare($query);
				$q->execute($params);
				if ($q->errorCode() == '00000') {
					return true;
				}
			}
		} catch( \PDOException $e ) {
			$errorMsg = $e->getMessage();
		}

		Bot::log($errorMsg);
		return false;
	}

	/**
	 * Executes a db statement
	 *
	 * if $query is an array of statements then they will be wrapped
	 * in a transaction. All statements will be sent the same parameter list.
	 *
	 * @param string|array $query
	 * @param mixed|array $params
	 * @return bool
	 */
	public function execute($query, $params = array())
	{
		if ( is_array($query) ) {
			$this->db->beginTransaction();
			foreach($query as $q) { // @todo Do not allow transactions that result in commit
				if (!$this->doExecute($q, $params)) {
					$this->db->rollback();
					return false;
				}
			}
			$this->db->commit();
			return true;
		} else {
			return $this->doExecute($query, $params);
		}
	}

	public function prepare($query)
	{
		$q = $this->db->prepare($query);
		if (!$q) {
			throw new \Exception('Bad query: '. $query); // @todo get error from PDO?
		}
		return $q;
	}

	public function fetch($query, $params = array())
	{
		try {
			$q = $this->prepare($query);
			$q->execute($params);
			return $q->fetch(\PDO::FETCH_ASSOC);
		} catch( \PDOException $e ) {
			/** @todo log or report errors... */
		}
		return null;
	}

	public function fetchAll($query, $params = array())
	{
		try {
			$q = $this->prepare($query);
			$q->execute($params);
			return $q->fetchAll(\PDO::FETCH_ASSOC);
		} catch( \PDOException $e ) {
			/** @todo log or report errors... */
		}
		return array();
	}

	public function fetchColumn( $query, $params )
	{
		try {
			$q = $this->prepare($query);
			$q->execute($params);
			return $q->fetchAll( \PDO::FETCH_COLUMN );
		} catch( \PDOException $e ) {
			/** @todo log or report errors... */
		}
		return array();
	}

	public function fetchScalar( $query, $params = array() )
	{
		try {
			$q = $this->prepare($query);
			$q->execute($params);
			return $q->fetchColumn(0);
		} catch( \PDOException $e ) {
			/** @todo log or report errors... */
		}
		return null;
	}

	/**
	 * Determines if a table exists
	 *
	 * @param string $name Table name
	 *
	 * @return bool
	 */
	public function hasTable($name)
	{
		$sql = 'SELECT COUNT(*) FROM sqlite_master WHERE name = ' . $this->db->quote($name);
		return (bool) $this->db->query($sql)->fetchColumn();
	}

	protected function createTable($name)
	{
		$sql = $this->internalTables[$name];
		$r = $this->db->exec($sql);
		if (!$r) {
			/**
			 * If the error code is 0000 and the array empty then it's not
			 * an error.. $r is just 0 after a create table it seems.
			 */
			$error = $this->db->errorInfo();
			if ($error[0] == '00000' && empty($error[1]) && empty($error[2]) ) {
				return;
			}
			print_r( $error ); /** @todo handle this better... */
		}
	}

    public function lastInsertId()
    {
        return $this->db->lastInsertId();
    }

}
