<?php

require_once('./config.php');

class Session {
        protected static $instance = null;
        protected $sessionID;
        protected $sessionFileContent;
        public $data;
        public $password;
        public $plain;
        public $key;
        public $iv;
        public $filename;

        public function __construct() {
                global $CFG;
                $this->sessionID = (isset($_COOKIE['CGISESSID'])) ? $_COOKIE['CGISESSID'] : false;
                $this->key = $_COOKIE['key'];
                if($this->sessionID) {
                        $this->filename = $CFG->SESSION_FILE . $this->sessionID;
                        $this->sessionFileContent = utf8_encode(file_get_contents($this->filename));
                        $this->data = str_replace(array('$D = ', ';;$D', '=>','\''), array('', '', ':','"'), $this->sessionFileContent);
                        
                        $this->data = json_decode($this->data, true);
                }
        }
        
        public function get_password() {
            if(isset($this->password)) {
                return $this->password;
            }
            if(!isset($this->data[password])) {
                return false;
            }
            $this->iv = substr( $this->data[password],0,32 );
            $this->password = Crypt::decrypt($this->key, $this->data[password]);
            
            return $this->password;
        }
        
        public function __get($key) {
                if(!isset($this->data[$key])) {
                        throw new Exception('session data for ' . $key . ' not found');
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
