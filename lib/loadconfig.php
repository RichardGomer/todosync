<?php

$cfn = dirname(__FILE__).'/../config.json';

// Load sources from sources.json
if(!file_exists($cfn)) {
    echo "$cfn doesn't exist (Hint: start with a copy of config.example.json)";
    exit;
}

$json = file_get_contents($cfn);
$conf = json_decode($json, true);

if(!$conf) {
    $err = json_last_error_msg();
    echo "Couldn't parse config.json\n$err\n$json";
    exit;
}

define('MAX_VERSION', '1.0');

// Check the config and create the syncer
if(!array_key_exists('version', $conf) || version_compare($conf['version'], MAX_VERSION) != 0) {
    echo "config.json version must be ".MAX_VERSION."\n";
    exit;
}

if(!array_key_exists('sources', $conf)) {
    echo "sources isn't defined in config.json\n";
    exit;
}

if(!array_key_exists('todofile', $conf)) {
    echo "todofile isn't defined in config.json\n";
    exit;
}

if(!array_key_exists('donefile', $conf)) {
    echo "donefile isn't defined in config.json\n";
    exit;
}

if(!array_key_exists('postdir', $conf)) {
    echo "postdir isn't defined in config.json\n";
    exit;
}
