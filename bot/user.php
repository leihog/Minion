<?php
namespace Bot;

use Bot\Hostmask as Hostmask;

class User
{
	protected static $_identifiedUsers = array();

	protected $id        = null;
	protected $level     = 0;
	protected $username  = null;
	protected $hostmasks = array();

	protected $authenticated = false;

	protected function __construct( $data )
	{
		foreach( $data as $key => $value ) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}

		if ( $this->id ) {
			$db = Bot::getDatabase();
			$hostmasks = $db->fetchColumn(
				'SELECT mask FROM hostmasks WHERE user_id = ?',
				array($this->id)
			);
			if ( is_array($hostmasks) ) {
				$this->hostmasks = $hostmasks;
			}
		}
	}

	public function addHostmask( \Bot\Hostmask $hostmask )
	{
		$userId = $this->id;
		if (!$this->hasHostmask($hostmask)) {
			$db = Bot::getDatabase();
			$hostmask = $hostmask->toString();
			if ( !$db->execute('INSERT INTO hostmasks (user_id, mask) VALUES (:userId, :hostmask)', compact('userId', 'hostmask')) )
			{
				return false;
			}

			$this->hostmasks[] = $hostmask;
		}

		return true;
	}

	public function getId()
	{
		return $this->id;
	}

	public function getLevel()
	{
		return $this->level;
	}

	public function getName()
	{
		return $this->username;
	}

	public function getHostmasks()
	{
		return $this->hostmasks;
	}

	public function hasHostmask( \Bot\Hostmask $hostmask )
	{
		if ( in_array($hostmask->toString(), $this->hostmasks) ) {
			return true;
		}

		return false;
	}

	public function identified()
	{
		foreach( $hostmasks as $mask ) {
			if ( isset(self::$_identifiedUsers[$mask]) ) {
				return true;
			}
		}

		return false;
	}

// STATIC
    public static function authenticate( $username, $password, \Bot\Hostmask $hostmask )
    {
        $db = Bot::getDatabase();

        $credentials = $db->fetch('SELECT password, salt FROM users WHERE username = :username', compact('username'));
        if ( $credentials && self::hashPassword( $password, $credentials['salt'] ) == $credentials['password'] )
        {
            self::$_identifiedUsers[$hostmask->toString()] = self::fetch( $username );
            return true;
        }

        return false;
    }

	public static function count()
	{
		return Bot::getDatabase()->fetchScalar( 'SELECT count(*) FROM users' );
	}

	public static function create( Hostmask $hostmask, $password )
	{
		$username = $hostmask->getNick();
		$salt = md5(uniqid(time(), true));
		$password = self::hashPassword($password, $salt);
		$added = time();
		$level = ( !self::count() ? 100 : 1 );
		$db = Bot::getDatabase();

		$db->execute(
			'INSERT INTO users (username, password, salt, level, added) '.
			'VALUES (:username, :password, :salt, :level, :added)',
			compact('username', 'password', 'salt', 'level', 'added')
		);
		if (($uid = $db->lastInsertId())) {
			$user = self::fetch($username);
			$user->addHostmask($hostmask);
			self::$_identifiedUsers[$hostmask->toString()] = $user;
			return $uid;
		}

		return false;
	}

	public static function exists( $id )
	{
		$db = Bot::getDatabase();
		if ( $id instanceOf Hostmask ) {
			$match = $db->fetchScalar(
				'SELECT 1 FROM hostmasks WHERE mask = ? limit 1',
				array($id->toString())
			);
		} else {
			$match = $db->fetchScalar(
				'SELECT 1 FROM users WHERE username = ? limit 1', array($id)
			);
		}

		if ($match) {
			return true;
		}
		return false;
	}

	/**
	 * returns a user
	 * @param mixed $id username|hostmask
	 */
	public static function fetch( $id )
	{
		$db = Bot::getDatabase();
		if ( $id instanceOf Hostmask ) {
			$hostmask = $id->toString();
			if (isset(self::$_identifiedUsers[$hostmask])) {
				return self::$_identifiedUsers[$hostmask];
			}

			$data = $db->fetch(
				'SELECT u.* FROM users as u join hostmasks as h '.
				'on(h.user_id=u.id) WHERE h.mask = ? limit 1',
				array($hostmask)
			);
		} else {
			$data = $db->fetch(
				'SELECT * FROM users WHERE username = ? limit 1', array($id)
			);
		}

		if ($data) {
			return new self($data);
		}

		return false;
	}

	public static function fetchAll()
	{
		$db = Bot::getDatabase();
		$users = $db->fetchAll('SELECT * FROM users');
		foreach($users as &$user) {
			$user = new self($user);
		}

		return $users;
	}

	public static function userList()
	{
		$db = Bot::getDatabase();
		return $db->fetchAll('SELECT username, level FROM users');
	}

    protected static function hashPassword( $password, $salt )
    {
        return md5( $password . $salt );
    }

	public static function isIdentified(Hostmask $hostmask)
	{
		if ( isset(self::$_identifiedUsers[$hostmask->toString()]) ) {
			return true;
		}

		return false;
	}

}
