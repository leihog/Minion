<?php
namespace Bot;

class User
{
    protected static $_identifiedUsers = array();

    protected $hostmasks = array();
    protected $id;
    protected $level;
    protected $username;

    protected $authenticated = false;

    protected function __construct( $data )
    {
    	foreach( $data as $key => $value )
		{
			if (property_exists($this, $key))
			{
				$this->$key = $value;
			}
		}
    }

    public function addHostmask( \Bot\Hostmask $hostmask )
    {
        $userId = $this->id;
        if (!$this->hasHostmask($hostmask))
        {
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

    public function getLevel()
    {
        return $this->level;
    }

    public function getName()
    {
    	return $this->username;
    }

    public function getNick()
    {
        return $this->username; /** @todo FIX THIS */
    }

    public function getHostmasks()
    {
    	return $this->hostmasks;
    }

    public function hasHostmask( \Bot\Hostmask $hostmask )
    {
        if ( in_array($hostmask->toString(), $this->hostmasks) )
        {
            return true;
        }

        return false;
    }

    public function isAuthenticated()
    {
        return $this->authenticated;
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
        $db = Bot::getDatabase();
        return $db->fetchScalar( 'SELECT count(*) FROM users' );
    }

    public static function create( \Bot\Hostmask $hostmask, $password )
    {
        $username = $hostmask->getNick();
        $salt = md5(uniqid(time(), true));
        $password = self::hashPassword($password, $salt);
        $added = time(); /** @todo created/modified columns should be automatically updated in the database. */
        $level = ( !self::count() ? 100 : 1 );

    	$db = Bot::getDatabase();

    	if ( $db->execute( 'INSERT INTO users (username, password, salt, level, added) VALUES (:username, :password, :salt, :level, :added)', compact('username', 'password', 'salt', 'level', 'added') ) )
    	{
    	    $user = self::fetch( $username );
    	    $user->addHostmask( $hostmask );

    	    self::$_identifiedUsers[$hostmask->toString()] = $user;
    	    return $db->lastInsertId();
    	}

    	return false;
    }

    public static function fetch( $username )
    {
        $db = Bot::getDatabase();

        $user = $db->fetch( 'SELECT * FROM users WHERE username = :username', compact('username') );
        if ($user)
    	{
        	$hostmasks = $db->fetchColumn( 'SELECT mask FROM hostmasks WHERE user_id = ?', array($user['id']) );
        	$user['hostmasks'] = ( is_array($hostmasks) ? $hostmasks : array() );

        	return new self($user);
    	}

    	return false;
    }

    public static function fetchAll()
    {
        $db = Bot::getDatabase();
        $users = $db->fetchAll('SELECT * FROM users');

        foreach($users as &$user)
        {
            $user = new self($user);
        }

        return $users;
    }

    /**
     *
     * @param \Bot\Hostmask $hostmask
     */
    public static function getIdentifiedUser( \Bot\Hostmask $hostmask )
    {
        $hostmask = $hostmask->toString();
        if (isset(self::$_identifiedUsers[$hostmask]))
        {
            return self::$_identifiedUsers[$hostmask];
        }

        return false;
    }

    protected static function hashPassword( $password, $salt )
    {
        return md5( $password . $salt );
    }

    public static function isIdentified( \Bot\Hostmask $hostmask )
    {
        if ( isset(self::$_identifiedUsers[$hostmask->toString()]) )
        {
            return true;
        }

        return false;
    }

    public static function isUser( $username )
    {
        $db = Bot::getDatabase();
        $userId = $db->fetchScalar('SELECT id FROM users WHERE username = :username', compact($username));

        if ( $userId )
        {
            return true;
        }

        return false;
    }
}