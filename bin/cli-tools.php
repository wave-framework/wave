<?php

define ('VERBOSE', in_array('-v', $argv));

include_once dirname(__FILE__) . '/../bootstrap.php';

if(!function_exists('write')){
	function write($msg){ write_stdout($msg); }
}

function write_stdout($msg){
	echo date('Y-m-d h:i:s') . ' ' . $msg . "\n";
}

function read($prompt = null, $delim = ':'){
	if($prompt !== null){
		echo "{$prompt}{$delim} ";
	}
	if(function_exists('readline')) return readline();
	else return read_stdin();
}

function read_stdin() {
    $fr = fopen("php://stdin","r");
    $input = fgets($fr,128);
    $input = rtrim($input);
    fclose ($fr);
    return $input;
}

