<?php

require_once('./config.php');
require_once($CFG->libdir . '/Session.php');
require_once($CFG->libdir . '/Wrapper.php');

function d($d) {
	var_dump($d);
}

$locale=$CFG->locale;
putenv("LC_ALL",$locale);
setlocale(LC_ALL, $locale);
bindtextdomain('schulkonsole-settings-backup','/usr/share/locale');
textdomain('schulkonsole-settings-backup');

class File {
    const BACKUP_CONF_FILE = 1;
    const BACKUP_CONF_NAME = '/etc/linuxmuster/backup.conf';
    const WRAPPER_BACKUP = 'wrapper-backup';
    
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
        $wrapper = new Wrapper(File::WRAPPER_BACKUP, '91001', $session->id, $session->password);
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

require($CFG->shtmldir . '/' . $this_file . '.php');
