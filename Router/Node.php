<?php

class Wave_Router_Node {

	const VAR_INT = '<int>';
	const VAR_STRING = '<string>';

	private $destination = null;
	private $children = array();
	protected $sundry = null;
	
	public function setDestination(array $destination){
		if($this->destination === null)
			$this->destination = $destination;
		else {	
			throw new Wave_Exception($this->destination['action'] . ' shares a duplicate route with ' . $destination['action']);
		}
	}
	
	public function addChild($url, $route){
		$pos = strpos($url, '/');
		if($pos === false)
			$part = $url;
		else 
			$part = substr($url, 0, $pos);
		
		$sundry = null;
		if(strpos($part, self::VAR_INT) !== false){
			$sundry = substr($part, strlen(self::VAR_INT));
			$part = self::VAR_INT;
		}
		elseif(strpos($part, self::VAR_STRING) !== false){
			$sundry = substr($part, strlen(self::VAR_STRING));
			$part = self::VAR_STRING;
		}
			
		if(!isset($this->children[$part])){
			$this->children[$part] = new Wave_Router_Node();
			if($sundry !== null)
				$this->children[$part]->sundry = $sundry;
		}
		if($pos !== false){
			$remaining = substr($url, strlen($part . $sundry) + 1);
			$this->children[$part]->addChild($remaining, $route);
		}
		else $this->children[$part]->setDestination($route);
	}
	
	public function getChild($key){
		if(isset($this->children[$key]))
			return $this->children[$key];
		else if(is_numeric($key) && isset($this->children[self::VAR_INT]))
			return $this->children[self::VAR_INT];
		else if(isset($this->children[self::VAR_STRING]))
			return $this->children[self::VAR_STRING];
		else	
			return null;
	}
	
	public function getDestination(){
		return $this->destination;
	}
	
	public function getVarName(){
		return $this->sundry;
	}
	
	public function isLeaf(){
		return $this->destination !== null;
	}
	
	public function isVarNode(){
		return $this->sundry !== null;
	}
}

?>