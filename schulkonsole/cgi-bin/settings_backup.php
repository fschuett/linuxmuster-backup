<?php

require_once('./config.php');

function d($d) {
	var_dump($d);
}

class Crypt {
    public static function decrypt($key, $string) {
        # --- DECRYPTION ---
        # Grab the hex-encoded key
        $key = pack( 'H*', $key );
        # Grab the hex-encoded cipherblock & convert it to binary
        $cipher_block = unpack( 'a16iv/a*ciphertext', pack( 'H*', $string ) );

        # Set up cipher
        $cipher = mcrypt_module_open( MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');

        mcrypt_generic_init( $cipher, $key, $cipher_block['iv'] );

        # Do the decryption
        $cleartext = mdecrypt_generic( $cipher, $cipher_block['ciphertext'] );
        $cleartext = rtrim( $cleartext );
        
        # Clean up
        mcrypt_generic_deinit( $cipher );
        mcrypt_module_close( $cipher );
        return $cleartext;
    }

    public static function encrypt($key, $iv, $string) {
        # --- ENCRYPTION ---
        $string = utf8_encode($string);
        # Set up cipher
        $cipher = mcrypt_module_open( MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
        mcrypt_generic_init( $cipher, $key, $iv );

        # Do the encryption
        $ciphertext = mcrypt_generic( $cipher, $string );

        # Convert to HEX for print/storage
        $cipher_block = implode( unpack( 'H*', $iv . $ciphertext ) );

        # Clean up
        mcrypt_generic_deinit( $cipher );
        mcrypt_module_close( $cipher );
        return $cipher_block;
    }
}

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

// proc_open()
// proc_get_status()
// proc_close()
class Wrapper {
        const WRAPPER_FILES = 'wrapper-files';
        
        protected $wrapperFile;
	protected $appID;
	protected $id;
	protected $password;
	protected $noAuth;
	public $out;
	public $in;
	public $err;
        public $process;
        
        /**
         * Erstellt eine neue Wrapperinstanz.
         *
         * @param	string		$wrapperFile	Ausführbare Wrapperfa
         * @param	integer		$appID
         * @param	integer		$id		Benutzer ID.
         * @param	string		$password	Benutzerkennwort.
         * @param	boolean		$noAuth		Autorisierung soll beim Start durchgeführt werden.
         */
        public function __construct($wrapperFile, $appID, $id, $password, $noAuth = false) {
            $this->wrapperFile = $wrapperFile;
            $this->appID = $appID;
            $this->id = $id;
            $this->password = $password;
            $this->noAuth = (bool) $noAuth;
        }
        
        public function __destruct() {
            $this->stop();
        }
        
	public function start() {
            $wrapperDir = '/usr/lib/schulkonsole/bin/';
            
            $descriptorspec = array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            );
            $pipes = array();
            
            $this->process = proc_open($wrapperDir . $this->wrapperFile, $descriptorspec, $pipes, $wrapperDir);
            
            if(!is_resource($this->process)) {
                throw new Exception('Wrapperaufruf fehlgeschlagen: ' . $wrapperDir . $this->wrapperFile . '!');
            }
            
            list($this->in, $this->out, $this->err) = $pipes;
            
            if($this->noAuth !== false) {
		$this->write($this->id . "\n" . $this->password . "\n" . $this->appID . "\n");
            }
            d($this);
	}
        
        public function stop() {
            if(is_resource($this->process)) {
                fclose($this->in);
                fclose($this->out);
                fclose($this->err);
                $state = proc_close($this->process);
                d("beendet mit code " . $state);
            }
        }
        
        public function write($string) {
            fwrite($this->in, $string);
        }
        
        public function read() {
            return stream_get_contents($this->out);
        }
        
}

class File {
    const BACKUP_CONF_FILE = 4;
    #const backup_conf_file = '/etc/linuxmuster/backup.conf';
    
    public static function read_backup_conf() {
        $content = utf8_encode(file_get_contents($backup_conf_file));
        $data = str_replace(array('$'), array(''), $content);
        return parse_ini_string($data, false);
    }
    
    public static function write($fileID, array $lines, Session $session) {
        $wrapper = new Wrapper(Wrapper::WRAPPER_FILES, '11001', $session->id, $session->password);
        $wrapper->start();
        
        $wrapper->write($fileID . "\n" . implode('', $lines));
        
        $wrapper->stop();
    }
}

$sk_session = new Session();

$this_file = 'settings_backup';

if ( ! $sk_session->get_password()) {
#        my $q = new CGI;
#        my $url = $q->url( -full => 1 );
#
#        # we send cookies over secure connections only
#        if ($url =~ s/^http:/https:/g) {
#                $sk_session->redirect($url);
#        } else {
#                $sk_session->exit_with_login_page($this_file);
#        }
}

$id = $sk_session->userdata('id');
$password = $sk_session->get_password();
$backup_conf = File::read_backup_conf();

// ----- Wrapper aufrufen, starten, schreiben, lesen -----

/*$wrapper = new Wrapper(Wrapper::WRAPPER_FILES, "appID", "id", "password");
$wrapper->start();

$wrapper->write("test");
$wrapper->read();*/

// ----- Konfigurationsdatei über bereitgestellte Klasse schreiben -----

//File::write(File::BACKUP_CONF_FILE, array('zeile1', 'zeile2'), $session);

# --------------------------------------------------------------------------
# ANZEIGE
# --------------------------------------------------------------------------
