<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->locale = 'de_DE.UTF-8';
$CFG->libdir = '/usr/lib/schulkonsole/cgi-bin';
$CFG->shtmldir = '/usr/share/schulkonsole/shtml';
$CFG->SESSION_FILE ='/var/lib/schulkonsole/cgisess_';
