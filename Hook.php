<?php


class Wave_Hook {
	
	private static $handlers = array();
	
	/**
	 *	Register a handler to fire when the given action is fired.
	 *	The optional $priority sets when in the chain the handler is fired, lower is earlier, defaults to 10
	 *	The optional $name can be used to deregister the handler at a later time if necessary
	**/
	public static function registerHandler($action, $callback, $priority = 10, $name = null){
		
		if(!isset(self::$handlers[$action]))
			self::$handlers[$action] = array();
		
		if(!isset(self::$handlers[$action][$priority]))
			self::$handlers[$action][$priority] = array();
		
		self::$handlers[$action][$priority] = $callback;
		
	}
	
	/**
	 *	Fire the specified action, calling all the registered handlers for that action.
	**/
	public static function triggerAction($action, $data){
		
		if(isset(self::$handlers[$action])){
			foreach(self::$handlers as $priority => $handlers){
				foreach($handlers as $handler){
					if(is_callable($handler)){
						call_user_func_array($handler, $data);
					}
				}
			}
		}
	}
	
}