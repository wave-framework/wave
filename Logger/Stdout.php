<?php

class Wave_Logger_Stdout extends Wave_Logger {
	

	public function write($message){
		echo $this->getCaller() . "$message\n";
	}

}


?>