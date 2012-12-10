<?php

namespace Wave\Logger;
use Wave;

class File extends Wave\Logger {
	
	private $filename;
	private $dirname;
	private $fullpath;
	private $segment = true;
	private $echo = false;	

	public function __construct($name, $dir = null, $segment = null, $echo = false){
		if($dir === null){
			$dir = Wave\Config::get('wave')->path->logs;
		}
		if($segment === null || !is_bool($segment)){
			$segment = Wave\Config::get('wave')->logger->segment;
		}
		$segment_size = Wave\Config::get('wave')->logger->segment_size;
		
		$this->filename = $name;
		$this->dirname = $dir;
		$this->segment = $segment;
		$this->echo = $echo;
		
		if(!file_exists($this->dirname)){
			@mkdir($this->dirname, 0770, true);
		}		
		$fullpath = $dir . $name;
		
		if(file_exists($fullpath) && filesize($fullpath) > $segment_size){
			// archive the current log file.
			$datestr = date('YmdHis');
			rename($fullpath, $fullpath.'_'.$datestr);
		}
		
		$this->fullpath = $fullpath;
	}

	
	public function write($message, $script_name = null){
	
		$log_when = date('Y/m/d H:i:s');
		
		if($script_name === null){
			$trace = debug_backtrace(false);
			$caller = $trace[1];
			$script_name =& $caller['class'];
		}
		
		$str = "[$log_when]\t$script_name\t$message\n";
		if($this->echo)
			echo $str;
		
		if(error_log($str, 3, $this->fullpath) === false){
			error_log('Unable to write to log file. ('.$this->fullpath.')');
		}
	}

}


?>