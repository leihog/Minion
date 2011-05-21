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
        if (!extension_loaded('PDO') || !extension_loaded('pdo_sqlite'))
        {
            throw new \Exception('PDO and pdo_sqlite extensions must be installed');
        }

        $this->initialize();
    }

    public function execute( $query, $params = array() )
    {
    	try
    	{
    		$q = $this->db->prepare($query);
    		$q->execute($params);
    		if ($q->errorCode() == '00000')
    		{
    		    return true;
    		}
    	}
    	catch( \PDOException $e )
    	{
    	    /** @todo log or report errors... */
    	}

    	return false;
    }

    public function fetch( $query, $params = array() )
    {
        try
        {
        	$q = $this->db->prepare($query);
        	$q->execute($params);
        	return $q->fetch(\PDO::FETCH_ASSOC);
        }
        catch( \PDOException $e )
        {
            /** @todo log or report errors... */
        }

        return false;
    }

    public function fetchAll( $query, $params = array() )
    {
        try
        {
        	$q = $this->db->prepare($query);
        	$q->execute($params);
        	return $q->fetchAll(\PDO::FETCH_ASSOC);
        }
        catch( \PDOException $e )
        {
            /** @todo log or report errors... */
        }

        return false;
    }

    public function fetchColumn( $query, $params )
    {
        try
        {
    	    $q = $this->db->prepare($query);
    	    $q->execute($params);
    	    return $q->fetchAll( \PDO::FETCH_COLUMN );
        }
        catch( \PDOException $e )
        {
            /** @todo log or report errors... */
        }

        return false;
    }

    public function fetchScalar( $query, $params = array() )
    {
        try
        {
    	    $q = $this->db->prepare($query);
    	    $q->execute($params);
    	    return $q->fetchColumn(0);
        }
        catch( \PDOException $e )
        {
            /** @todo log or report errors... */
        }

    }

    /**
     * Enter description here ...
     * @param dbFile
     * @param
     */
	protected function initialize()
	{
		$dbFile = Bot::getDir('data') . '/bot.db';
		$this->db = new \PDO('sqlite:' . $dbFile);

		foreach(array_keys($this->internalTables) as $table)
		{
			if (!$this->hasTable($table))
			{
				$this->createTable($table);
			}
		}
	}

	public function install( $name, $schema )
	{
	    $fp = null;
	    $tables = array();

	    if ( !file_exists($schema) || ($fp = fopen($schema, 'r')) === false )
	    {
	        throw new \Exception('Unable to open schema for reading');
	    }

	    $i = 0;
	    while ( ($line = fgets($fp)) !== false)
	    {
	        $i++;
	        $line = strtolower($line);

	        if ( preg_match('/^create\stable\s([a-z0-9_]+)\s.*;$/', $line, $m) )
	        {
	            if ( $this->hasTable($m[1]) )
	            {
	                throw new \Exception('Table exists');
	            }

	            if ( !$this->execute(rtrim($line, ';')) )
	            {
	                throw new \Exception("Unable to create table '{$m[1]}'.");
	            }

	            $tables[] = $m[1];
	        }
            else if ( preg_match('/^insert\sinto\s([a-z0-9_]+)\s.*;$/', $line, $m) )
            {
                if ( !in_array($m[1], $tables) )
                {
                    throw new \Exception('Trying to insert in to restricted table.');
                }

	            if ( !$this->execute(rtrim($line, ';')) )
	            {
	                throw new \Exception("Unable to insert line '$i' from schema '{$schema}'.");
	            }
            }

            unset($m);
	    }

	    if (!empty($tables))
	    {
    	    $this->execute('INSERT INTO plugins (plugin) VALUES(?)', array($name));
    	    $pluginId = $this->lastInsertId();

    	    foreach($tables as $table)
    	    {
    	        $this->execute('INSERT INTO plugin_tables (plugin, tablename) VALUES (?, ?)', array($pluginId, $table));
	        }
	    }
	}

	public function isInstalled( $name )
	{
	    if ( !$this->fetchScalar('SELECT 1 FROM plugins WHERE plugin = :name', array('name'=>$name)) )
	    {
	        return false;
	    }

	    return true;
	}

	/**
	 * Returns the plugin that created the table
	 */
	public function createdBy( $tableName )
	{
	    return $this->fetchScalar('SELECT a.plugin FROM plugins as a JOIN plugin_tables as b ON a.id=b.plugin WHERE b.tablename = ? LIMIT 1', array($tableName));
	}

    /**
     * Determines if a table exists
     *
     * @param string $name Table name
     *
     * @return bool
     */
    protected function hasTable($name)
    {
        $sql = 'SELECT COUNT(*) FROM sqlite_master WHERE name = ' . $this->db->quote($name);
        return (bool) $this->db->query($sql)->fetchColumn();
    }

    protected function createTable($name)
    {
        $sql = $this->internalTables[$name];
		$r = $this->db->exec($sql);
		if (!$r)
		{
		    /** If the error code is 0000 and the array empty then it's not an error.. $r is just 0 after a create table it seems. */
		    $error = $this->db->errorInfo();
		    if ($error[0] == '00000' && empty($error[1]) && empty($error[2]) )
		    {
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
