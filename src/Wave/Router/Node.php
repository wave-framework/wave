<?php

namespace Wave\Router;


use Wave\Exception;
use Wave\Http\Request;

class Node {

	const VAR_INT = '<int>';
	const VAR_STRING = '<string>';
	
	const URL_SEGMENT_DELIMITER = '/(?<!<)\/(?!>)/';
	
	private $children = array();
	private $action = null;
	
	private function setAction(Action $action){
		if($this->action === null)
			$this->action = $action;
		else {
			throw new Exception($this->action->getAction() . ' shares a duplicate route with ' . $action->getAction());
		}
	}
	
	public function addChild($route, Action $action){
		
		// need to check if this part of the segment is a regex
		// and extend the segment to contain the whole expression
		// if it contains a `/`
		if(substr_compare($route, '</', 0, 2) === 0){
			preg_match('/<\/.*?\/>[^\/]*/', $route, $matches);
			$segment = $matches[0];
			$segment_length = strlen($segment);
			if($segment_length < strlen($route))
				$remaining = substr($route, $segment_length + 1);
		}
		else {
			$segment = preg_split(self::URL_SEGMENT_DELIMITER, $route, 2);
			if(isset($segment[1])) $remaining = $segment[1];
			$segment = $segment[0];
		}
		
		
		
		if(!isset($this->children[$segment])){
			$this->children[$segment] = new Node();
		}
		
		if(isset($remaining) && $remaining !== null){
			$this->children[$segment]->addChild($remaining, $action);
		}
		else
			$this->children[$segment]->setAction($action);
		
	}
	
	public function findChild($url, Request &$request){
		
		if($url == null) return $this;
				
		$segment = preg_split(self::URL_SEGMENT_DELIMITER, $url, 2);
		if(isset($segment[1])) $remaining = $segment[1];
		else $remaining = null;
		$segment = $segment[0];
		
		$node = null;
		// first check the segment is a directly keyed child
		if(isset($this->children[$segment])){
			$node = $this->children[$segment];
			// if there is more to go, recurse with the rest
			return $node !== null ? $node->findChild($remaining, $request) : $node;
		}
		else {
			// otherwise, start searching through all the child paths
			// matching each one and recursing if a match is found
			$matching_node = null;
			foreach($this->children as $path => $node){
				// start with the regex matches			
				if(substr_compare($path, '</', 0, 2) === 0){
					$expression_end = strpos($path, '/>');
					$pattern = '#^'.substr($path, 2, $expression_end - 2).'#';
					if(preg_match($pattern, $url, $matches) == 1){
						$segment = $matches[0];
						$remaining = substr($url, strlen($segment) + 1) ?: null;
						$matching_node = $node->findChild($remaining, $request);
						if($matching_node !== null && $matching_node->hasValidAction())
                            $request->attributes->set(substr($path, $expression_end + 2), $segment);
					}
				}
				elseif((is_numeric($segment) && strpos($path, self::VAR_INT) !== false)
						|| strpos($path, self::VAR_STRING) !== false) {
						
					$matching_node = $node->findChild($remaining, $request);
					if($matching_node !== null && $matching_node->hasValidAction())
                        $request->attributes->set(substr($path, strpos($path, '>') + 1), $segment);
				}
				
				if($matching_node !== null)
					break;
			}
			
			return $matching_node;
		}
	}
	
	public function getAction(){
		return $this->action;
	}
	
	public function hasValidAction(){
		return $this->action instanceof Action;
	}
	
}

?>