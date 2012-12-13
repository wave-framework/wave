<?php

define ('VERBOSE', in_array('-v', $argv));

if(!file_exists('./bootstrap.php')){
    error("Could not load application './bootstrap.php', aborting...", false);
    error("This script must be run from the application root directory");
}

require_once './bootstrap.php';

if(!function_exists('writeout')){
	function writeout($msg){ write_stdout($msg); }
}

function write_stdout($msg){
	echo date('Y-m-d h:i:s') . ' ' . $msg . "\n";
}

function readin($prompt = null, $delim = ':'){
	if($prompt !== null){
		echo "{$prompt}{$delim}\n";
	}
	if(function_exists('readline')) return readline();
	else {
		$fr = fopen("php://stdin","r");
		$input = fgets($fr,128);
		$input = rtrim($input);
		fclose ($fr);
		return $input;
	}
}

function info($message){
	echo "\033[1;37m$message\033[0m\n";
}
function warn($message){
	echo "\033[1;34m$message\033[0m\n";
}
function error($message, $terminiate = true){
	echo "\033[0;31m$message\033[0m\n";
	if($terminiate) exit(1);
}
