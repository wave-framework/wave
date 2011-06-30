<?php

class Wave_Logger {
	
	protected function getCaller(){
	
		$log_when = date('Y/m/d H:i:s');
		
		$trace = debug_backtrace(false);
		$caller = $trace[1];
		
		$script_name =& $caller['class'];
		
		$str = "[$log_when]\t$script_name\t";
		
		return $str;
	}
	
	public function write($message) { }

}


?>