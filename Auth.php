<?php


class Wave_Auth {

	const SUCCESS = 'success';
	const FAILURE_NO_IDENTITY = 'no-identity';
	const FAILURE_BAD_CREDENTIAL = 'bad-credential';
	
	/**
	 * 	Reference to a static instance of the Auth Handler class
	**/
	private static $_handler;
	private static $_auth_problems;
	
	private static $_valid_auth;
	
	public static $_is_loaded = false;
	
	public static function registerHandler($class, $autoload = true){
		if(!class_implements($class))
			throw new Wave_Exception('Auth Handler class ('.$class.') must implement Wave_IAuthable');
		
		if($autoload) $class::loadPersistentAuth();
		
		self::$_handler = $class;
		
		self::$_is_loaded = true;
		
		
	}
	
	public static function checkIdentity($primary, $secondary){
		$class = self::$_handler;
		$auth_object = $class::loadByIdentifier($primary);
							
		if($auth_object instanceof Wave_IAuthable){

			$_is_valid = true;
			// check the secondary credentials
			foreach($secondary as $key => $value){
				if((is_callable($value) && $value($auth_object->$key))
				  || (isset($auth_object->$key) && $auth_object->$key == $value))
					continue;

				self::$_auth_problems['secondary'][$key] = array(
					'value' => $auth_object->$key, 
					'reason' => self::FAILURE_BAD_CREDENTIAL, 
					'match' => $value);
				$_is_valid = false;
			}
			
			if($_is_valid){
				self::$_valid_auth = $auth_object;
				self::registerIdentity($auth_object);
				return self::SUCCESS;
			}
			
			else return self::FAILURE_BAD_CREDENTIAL;
			
		}
		else {
			self::$_auth_problems['primary'] = $primary;
			
			return self::FAILURE_NO_IDENTITY;
		}
	}
	
	public static function registerIdentity($identity){
		return Wave_Registry::store('__wave_identity', $identity);
	}
	
	public static function deregisterIdentity(){
		return Wave_Registry::destroy('__wave_identity');
	}
		
	public static function persistIdentity($identity, $type = null, $expires = null){
		$config = Wave_Config::get('deploy')->auth;
		if($type === null)
			$type = $config->persist_type;
		if($expires === null)
			$expires = $config->$type->expires;
			
		if($type == 'cookie'){
				
			Wave_Storage_Cookie::store(
				$config->cookie->name,
				$identity,
				strtotime($expires),
				$config->cookie->path,
				$config->cookie->domain
			);
		}
				
		
	}
	
	public static function ceaseIdentity($type = null){		
		$config = Wave_Config::get('deploy')->auth;
		if($type === null)
			$type = $config->persist_type;
				
		if($type == 'cookie'){
				
			Wave_Storage_Cookie::store(
				$config->cookie->name,
				'',
				time()-86400,
				$config->cookie->path,
				$config->cookie->domain
			);
		}
				
		
	}
	
	public static function getAuthProblems() { return self::$_auth_problems; }
	
	public static function getIdentity() { 
		return Wave_Registry::fetch('__wave_identity'); 
	}
	
	public static function getHandlerClass() { return self::$_handler; }

}


?>