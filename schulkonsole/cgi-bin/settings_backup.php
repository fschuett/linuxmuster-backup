<?php

require_once('./config.php');

function d($d) {
	var_dump($d);
}

$locale=$CFG->locale;
putenv("LC_ALL",$locale);
setlocale(LC_ALL, $locale);
bindtextdomain('schulkonsole-settings-backup','/usr/share/locale');
textdomain('schulkonsole-settings-backup');

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
        public $input_errors;
        public $is_error;
        public $status;

        public function __construct() {
                global $CFG;
                $this->sessionID = (isset($_COOKIE['CGISESSID'])) ? $_COOKIE['CGISESSID'] : false;
                $this->key = $_COOKIE['key'];
                if($this->sessionID) {
                        $this->filename = $CFG->SESSION_FILE . $this->sessionID;
                        $this->sessionFileContent = utf8_encode(file_get_contents($this->filename));
                        $this->data = str_replace(array('$D = ', ';;$D', '=>','\''), array('', '', ':','"'), $this->sessionFileContent);
                        
                        $this->data = json_decode($this->data, true);
                        $this->is_error = false;
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
        
        public function set_status($status, $is_error) {
            $this->is_error = $error;
            $this->status = $status;
        }
        
        public function redirect() {
            
        }
        
        public function exit_with_login_page($page) {
            
        }
        
        public function mark_input_error($error) {
            $input_id = $error;

            # TODO String manipulation - $input_id =~ s/[^A-Za-z0-9\-_:.]//g;
            # $input_id =~ s/^([^A-Za-z])/x$1/;

            $this->input_errors[$input_id] = 1;
        }
}

// proc_open()
// proc_get_status()
// proc_close()
class Wrapper {
        const WRAPPER_BACKUP = 'wrapper-backup';
        
        protected $wrapperFile;
	protected $appID;
	protected $id;
	protected $password;
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
         */
        public function __construct($wrapperFile, $appID, $id, $password) {
            $this->wrapperFile = $wrapperFile;
            $this->appID = $appID;
            $this->id = $id;
            $this->password = $password;
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
            
            $this->write($this->id . "\n" . $this->password . "\n" . $this->appID . "\n");
	}
        
        public function stop() {
            if(is_resource($this->process)) {
                fclose($this->in);
                fclose($this->out);
                fclose($this->err);
                $state = proc_close($this->process);
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
    const BACKUP_CONF_NAME = '/etc/linuxmuster/backup.conf';
    private static $bools = array('firewall','verify','unmount','cronbackup');
    
    public static function isBool($var) {
        if(in_array($var, File::$bools))
            return true;
        else
            return false;
    }
    
    public static function toBool($var) {
        if (!is_string($var)) { 
            return (bool) $var;
        }
        switch (strtolower($var)) {
          case '1':
          case 'true':
          case 'on':
          case 'yes':
          case 'y':
            return true;
          default:
            return false;
        }
    }
    
    public static function read_backup_conf() {
        $bc = parse_ini_file(File::BACKUP_CONF_NAME);
        foreach($bc as $key => $value) {
            if(File::isBool($key)) {
                $bc[$key] = File::toBool($value);
            }
        }
        return $bc;
    }
    
    public static function read_backup_conf_lines() {
        return file(File::BACKUP_CONF_NAME,FILE_IGNORE_NEW_LINES);
    }
    
    public static function write($fileID, array $lines, Session $session) {
        $wrapper = new Wrapper(Wrapper::WRAPPER_BACKUP, '11001', $session->id, $session->password);
        $wrapper->start();
        
        $wrapper->write($fileID . "\n" . implode("\n", $lines));
        
        $wrapper->stop();
    }

    public static function new_backup_lines($values_new) {
	$lines = array();
	if ($bclines = File::read_backup_conf_lines()) {
            foreach($bclines as $line) {
                if( preg_match('/^\s*([\w]+)\s*=/', $line, $matches) ) {
                    $key = $matches[1];
                    if(!isset($values_new[$key])) {
                        continue;
                    }
                    if (File::isBool($key)) {
                            $value = (File::toBool($values_new[$key]) ? "yes" : "no");
                    } else {
                            $value = $values_new[$key];
                    }
                    $line = "$key=$value";
                    unset( $values_new[$key] );
                }
                array_push($lines, $line);
            }
	}

	if (isset($values_new) && count($values_new) > 0) {
            array_push($lines, "# schulkonsole");
            foreach($values_new as $key => $value) {
                if (isBool($key)) {
                    $value = (toBool($value) ? "yes" : "no");
                }
                array_push($lines, "$key=$value");
            }
	}
	return $lines;
    }
}

# --------------------------------------------------------------------------
# VERARBEITUNG
# --------------------------------------------------------------------------
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

$id = $sk_session->id;
$password = $sk_session->get_password();
$backup_conf = File::read_backup_conf();

if(isset($_POST['accept'])) {
    $errors;
    $is_changed = 0;
    foreach($backup_conf as $key => $value) {
        if(isset($_POST[$key])) {
            $value_new = $_POST[$key];
            if(File::isBool($key)) {
                $value_new = File::toBool($value_new);
                if ($value != $value_new) {
                    $backup_conf[$key] = $value_new;
                    $is_changed = 1;
                }
            } else {
                $value_new = trim($value_new);
                if ($backup_conf[$key] != $value_new) {
                    switch($key) {
                        default:
                            break;
                        case 'restoremethod':
                            if($value_new != 'hd' && $value_new != 'nfs') {
                                $sk_session->mark_input_error('restoremethod');
                                        array_push($errors, _(
                                                'Restoremethode muss hd oder nfs sein'));
                            }
                            break;
                        case 'includedirs':
                        case 'excludedirs':
                            $dirs = explode(',', $value_new);
                            foreach($dirs as $dir) {
                                trim($dir);
                            }
                            $value_new = implode(',', $dirs);
                            break;
                        case 'compression':
                            if (!is_int($value_new)) {
                                $sk_session->mark_input_error('compression');
                                array_push($errors, _(
                                            'Erlaubte Werte f&uuml;r Kompressionsgrad sind 0 bis 9'));
                            }
                            break;
                        case 'keepfull':
                            if (!is_int($value_new) || $value_new < 1 || $value_new > 9) {
                                $sk_session->mark_input_error('keepfull');
                                array_push($errors, _(
                                            'Erwarte Zahl zwischen 1 und 9 bei Anzahl der vorgehaltenen Vollbackups'));
                            }
                            break;
                        case 'keepdiff':
                            if (!is_int($value_new) || $value_new < 1 || $value_new > 9) {
                                $sk_session->mark_input_error('keepdiff');
                                array_push($errors, _(
                                            'Erwarte Zahl gr&ouml;&szlig;er gleich 1 bei Anzahl der vorgehaltenen differentiellen Backups'));
                            }
                            break;
                        case 'keepinc':
                            if (!is_int($value_new) || $value_new < 1 || $value_new > 9) {
                                $sk_session->mark_input_error('keepfull');
                                array_push($errors, _(
                                            'Erwarte Zahl gr&ouml;&szlig;er gleich 1 bei Anzahl der vorgehaltenen inkrementellen Backups'));
                            }
                            break;
                    }
                    $backup_conf[$key] = $value_new;
                    $is_changed = 1;
                }
            }
        }
    }

    # commit changes
    if (isset($errors)) {
        $sk_session->set_status(implode(', ', $errors), 1);
    } else {
        if (isset($backup_conf)) {
                $lines = File::new_backup_lines($backup_conf);
                File::write(File::BACKUP_CONF_FILE, $lines, $sk_session);
        }

        if ($is_changed) {
                $sk_session->set_status(
                        _('&Auml;nderungen &uuml;bernommen'), 0);
        }
    }

# TODO Error handling    if ($@) {
#            $sk_session->standard_error_handling($this_file, $@);
#    }

}

// ----- Wrapper aufrufen, starten, schreiben, lesen -----

/*$wrapper = new Wrapper(Wrapper::WRAPPER_BACKUP, "appID", "id", "password");
$wrapper->start();

$wrapper->write("test");
$wrapper->read();*/

// ----- Konfigurationsdatei über bereitgestellte Klasse schreiben -----

//File::write(File::BACKUP_CONF_FILE, array('zeile1', 'zeile2'), $session);

# --------------------------------------------------------------------------
# ANZEIGE
# --------------------------------------------------------------------------
foreach( $backup_conf as $key => $value) {
    if(!isset($key)) {
        $backup_conf[$key] = $key;
    }
}

function write_checked($val1,$val2) {
    if(isset($val1) && isset($val2) && $val1 == $val2) {
        echo "checked";
    } else {
        echo "";
    }
}

$status = $sk_session->status;
$is_error = $sk_session->is_error;

?>

<html lang="de">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Content-Language" content="de">
<title><?php echo _("Schulkonsole") ?> - <?php echo _("Einstellungen") ?> - <?php echo _("Backup") ?></title>
<?php include($CFG->shtmldir . '/css.shtml.inc') ?>
<script type="text/javascript" src="schulkonsole.js"></script>
</head>
<body>
<div id="container">
<div id="header">
<h1><?php echo _("Schulkonsole f&uuml;r Netzwerkberater/innen") ?></h1>
<?php echo _("Version") ?> <?php echo $version; ?>
<?php echo $action; ?>
</div>
</div>

<div id="menu">
   <a href="start"><?php echo _("Mein Konto") ?></a>
   <a href="settings"><?php echo _("Einstellungen") ?></a>
   <a href="user"><?php echo _("Benutzer") ?></a>
   <a href="quotas"><?php echo _("Quota") ?></a>
   <a href="rooms"><?php echo _("R&auml;ume") ?></a>
   <a href="printers"><?php echo _("Drucker") ?></a>
   <a href="hosts"><?php echo _("Hosts") ?></a>
   <a href="linbo"><?php echo _("LINBO") ?></a>
   <a href="logout"><?php echo _("Abmelden") ?></a>
</div>

<div id="submenu">
	<a class="separator" href="settings"><?php echo _("Start") ?></a>
	<a href="settings_program"><?php echo _("Schulkonsole") ?></a>
	<a href="settings_users"><?php echo _("Benutzerverwaltung") ?></a>
	<a href="settings_classs"><?php echo _("Klassen") ?></a>
        <a href="settings_projects"><?php echo _("Projekte") ?></a>
	<a class="end" href="settings_backup"><?php echo _("Backup") ?></a>
	<a class="end" href="settings_backup.php"><?php echo _("Backup") ?></a>
</div>

<div id="content">

<h2><?php echo _("Einstellungen") ?> :: <?php echo _("Backup") ?></h2>

<form method="post" accept-charset="UTF-8">

<h3><?php echo _("Backup") ?></h3>

<table class="settings">
<colgroup span="2" width="50%">
</colgroup>
<tr class="even">
<td><label for="backupdevice"><?php echo _("Backupger&auml;t (z.B. /dev/sdb1 oder NFS-Share)") ?></label></td>
<td><input type="text" name="backupdevice" id="backupdevice" value="<?php echo $backup_conf[backupdevice] ?>" ></td>
</tr>
<tr class="odd">
<td><label for="mountpoint"><?php echo _("Mountpunkt (Verzeichnis, in das das Backupger&auml;t ins Serverdateisystem eingeh&auml;ngt ist)") ?></label></td>
<td><input type="text" name="mountpoint" id="mountpoint" value="<?php echo $backup_conf[mountpoint] ?>" ></td>
</tr>
<tr class="even">
<td><?php echo _("Restoremethode, hd: von Fest-/Wechselplatte, nfs: von NFS-Share") ?></td>
<td>
    <input type="radio" name="restoremethod" id="restoremethodhd" value="hd" 
        <?php write_checked($backup_conf[restoremethod], "hd") ?> >
<label for="restoremethodhd"><?php echo _("hd") ?></label><br>
<input type="radio" name="restoremethod" id="restoremethodnfs" value="nfs"
               <?php write_checked($backup_conf[restoremethod], "nfs") ?> >
<label for="restoremethodnfs"><?php echo _("nfs") ?></label><br>
</td>
</tr>
<tr class="odd">
<td><?php echo _("Firewall-Einstellungen sichern?") ?></td>
<td>
<input type="radio" name="firewall" id="firewalltrue" value="1"
       <?php write_checked($backup_conf[firewall],"1") ?> >
<label for="firewalltrue"><?php echo _("Ja") ?></label><br>
<input type="radio" name="firewall" id="firewallfalse" value="0"
       <?php write_checked($backup_conf[firewall],"0") ?> >
<label for="firewallfalse"><?php echo _("Nein") ?></label>
</td>
<tr class="even">
<td><?php echo _("Backup verifizieren?") ?></td>
<td>
<input type="radio" name="verify" id="verifytrue" value="1"
       <?php write_checked($backup_conf[verify],"1") ?> >
<label for="verifytrue"><?php echo _("Ja") ?></label><br>
<input type="radio" name="verify" id="verifyfalse" value="0"
       <?php write_checked($backup_conf[verify],"0") ?> >
<label for="verifyfalse"><?php echo _("Nein") ?></label>
</td>
</tr>
<tr class="odd">
<td><label for="isoprefix"><?php echo _("Pr&auml;fix f&uuml;r ISO-Dateien") ?></label></td>
<td><input type="text" name="isoprefix" id="isoprefix" value="<?php echo $backup_conf[isoprefix] ?>"></td>
</tr>
<tr class="even">
<td><label for="mediasize"><?php echo _("Gr&ouml;&szlig;e der ISO-Dateien in MB") ?></label></td>
<td><input size="5" maxlength="4" type="text" name="mediasize" id="mediasize" value="<?php echo $backup_conf[mediasize] ?>"></td>
</tr>
<tr class="odd">
<td><label for="includedirs"><?php echo _("Einzuschlie&szlig;ende Verzeichnisse, leeres Feld bedeutet alle") ?></label></td>
<td><input type="text" size="40" name="includedirs" id="includedirs" value="<?php echo $backup_conf[includedirs] ?>"></td>
</tr>
<tr class="even">
<td><label for="excludedirs"><?php echo _("Vom Backup auszuschlie&szlig;ende Verzeichnisse") ?></label></td>
<td><input type="text" size="40" name="excludedirs" id="excludedirs" value="<?php echo $backup_conf[excludedirs] ?>"></td>
</tr>
<tr class="odd">
<td><label for="services"><?php echo _("W&auml;hrend des Backups herunterzufahrende  Dienste") ?></label></td>
<td><input type="text" size="40" name="services" id="services" value="<?php echo $backup_conf[services] ?>"></td>
</tr>
<tr class="even">
<td><label for="compression"><?php echo _("Kompressionsgrad") ?></label></td>
<td><input size="2" maxlength="1" type="text" name="compression" id="compression" value="<?php echo $backup_conf[compression] ?>"></td>
</tr>
<tr class="odd">
<td><?php echo _("Backupger&auml;t nach Backup aush&auml;ngen?") ?></td>
<td>
<input type="radio" name="unmount" id="unmounttrue" value="1"
       <?php write_checked($backup_conf[unmount],"1") ?> >
<label for="unmounttrue"><?php echo _("Ja") ?></label><br>
<input type="radio" name="unmount" id="unmountfalse" value="0"
       <?php write_checked($backup_conf[unmount],"0") ?> >
<label for="unmountfalse"><?php echo _("Nein") ?></label>
</td>
</tr>
<tr class="even">
<td><label for="keepfull"><?php echo _("Anzahl der vorgehaltenen Vollbackups") ?></label></td>
<td><input type="text" size="2" maxlength="1" name="keepfull" id="keepfull" value="<?php echo $backup_conf[keepfull] ?>"></td>
</tr>
<tr class="odd">
<td><label for="keepdiff"><?php echo _("Anzahl der vorgehaltenen differentiellen Backups") ?></label></td>
<td><input type="text" size="3" maxlength="2" name="keepdiff" id="keepdiff" value="<?php echo $backup_conf[keepdiff] ?>"></td>
</tr>
<tr class="even">
<td><label for="keepinc"><?php echo _("Anzahl der vorgehaltenen inkrementellen Backups") ?></label></td>
<td><input type="text" size="3" maxlength="2" name="keepinc" id="keepinc" value="<?php echo $backup_conf[keepinc] ?>"></td>
</tr>
<tr class="odd">
<td><label for="cronbackup"><?php echo _("Vollautomatische Backups durchf&uuml;hren?") ?></label></td>
<td>
<input type="radio" name="cronbackup" id="cronbackuptrue" value="1"
       <?php write_checked($backup_conf[cronbackup],"1") ?> >
<label for="cronbackuptrue"><?php echo _("Ja") ?></label><br>
<input type="radio" name="cronbackup" id="cronbackupfalse" value="0"
       <?php write_checked($backup_conf[cronbackup],"0") ?> >
<label for="cronbackupfalse"><?php echo _("Nein") ?></label>
</td>
</tr>
</table>

<p>
<input type="submit" name="accept" value="&Auml;nderungen &uuml;bernehmen">
</p>

</form>


</div>

<div id="info" style="display: block;">
<a class="hideinfo" onclick="toggle_visibility('info');" href="#">^</a>

<?php
echo '<div id=' . ($is_error?"status.error":"status.ok") . '>';
echo '<div></div>';
if(isset($status)) {
    echo '<p>' . $status . '</p>';
}
if(isset($_SERVER['REMOTE_ADDR'])) {
    echo '<p class="info">';
    echo _("Sitzungsdauer:") . $session_time . '<br>';
    echo _("verbleibend:") .  '<span id="timer">' . $max_idle_hh_mm_ss . '</span></p>';
    echo '<p class="info">' . _("Benutzer:");
    echo '<strong>' . $firstname . ' ' . $surname . '</strong><br>';
    echo _("Raum:");
    if(isset($remote_room)) {
        echo '<strong>' . $remote_room . '</strong><br>';
    } else {
        echo '<strong><?php echo _("unbekannt") ?></strong><br>';
    }
    echo _("Workstation:");
    echo '<strong>' . $remote_workstation . '</strong><br>';
    echo _("IP:") . '<strong>' . $_SERVER['REMOTE_ADDR'] . '</strong><br>';
    if(isset($class)) {
        echo '<p class="info">' . _("aktive Klasse:");
        echo '<strong>' . $class . '</strong></p>';
    }
}
?>
</div>

<h2><?php echo _("Info") ?></h2>

<p><?php echo _("Bearbeiten Sie hier die globalen Einstellungen f&uuml;r
das Serverbackup.") ?></p>
<p><?php echo _("F&uuml;r eine detaillierte Beschreibung der einzelnen Punkte
konsultieren Sie bitte die Dokumentation.") ?></p>

<div id="authors">
</div>

</body>
</html>
