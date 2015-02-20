<?php
define('SESSION_FILE', '/var/lib/schulkonsole/cgisess_');
xdebug_enable();

function d($d) {
	var_dump($d);
}

class Session {
	protected static $instance = null;
	protected $sessionID;
	protected $sessionFileContent;
	public $data;
	
	public function __construct() {
		$this->sessionID = (isset($_COOKIE['CGISESSID'])) ? $_COOKIE['CGISESSID'] : false;
		
		if($this->sessionID) {
			$this->sessionFileContent = utf8_encode(file_get_contents(SESSION_FILE . $this->sessionID));
			$this->data = json_decode(str_replace(array('$D = ', ';;$D', '=>', '\''), array('', '', ':', '"'), $this->sessionFileContent), true);
		}
		var_dump($this);
	}
	
	public function __get($key) {
		if(!isset($this->data[$key])) {
			//throw new Exception('session data for ' . $key . ' not found');
		}
		
		return $this->data[$key];
	}
	
	public static function get() {
		if(self::$instance == null) {
			self::$instance = new Session();
		}
		if(!isset(self::$instance->sessionID) || self::$instance->sessionID == false) {
			//throw new Exception('no active session');
			return false;
		}
		return self::$instance;
	}
}


class Wrapper {
	protected $in;
	protected $out;
	
	public function start() {
	
	}
}

$session = new Session();
