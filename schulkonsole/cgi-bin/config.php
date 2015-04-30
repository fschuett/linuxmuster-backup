<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->locale       = 'de_DE.UTF-8';
$CFG->cgibase      = '/usr/lib/schulkonsole';
$CFG->libbase      = '/usr/share/schulkonsole';
$CFG->varbase      = '/var/lib/schulkonsole';
$CFG->cgidir       = $CFG->cgibase . '/cgi-bin';
$CFG->wrapperdir   = $CFG->cgibase . '/bin';
$CFG->libdir       = $CFG->libbase . '/Schulkonsole';
$CFG->shtmldir     = $CFG->libbase . '/shtml';
$CFG->SESSION_FILE = $CFG->varbase . '/cgisess_';
