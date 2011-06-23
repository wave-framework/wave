<?php 
/**
 *	Load third party sms handler. Must be loaded out here so the extends call works correctly
**/
include_once Wave_Config::get('deploy')->sms->loader;   
    
    
    
class Wave_Notification_SMS extends SMSGateway {

	const CHAR_LIMIT = 160;

	
	private static $conf;
	private static $_isbuilt = false;
	
	private $to = array();
	private $extra = array();
	private $replacements = null;
	
	public static function create(){
	
		if(!self::$_isbuilt){
			self::$conf = Wave_Config::get('deploy')->sms;
			self::$_isbuilt = true;
		}
		
		
		
		$instance = new self();
		$instance->setParameter('account_id', self::$conf->account_id)
				 ->setParameter('auth_key', self::$conf->auth_key);
	
		return $instance;
	}
	
	public function setTo($addresses, $name = null){
		
		if(!is_array($addresses)) $addresses = array($addresses);
		
		foreach($addresses as $key => $value){
			$dest = null;
			// parsing an object
			if(is_object($value) && $value instanceof Wave_INotifiable){
				$dest = $value->getNotificationAddress(Wave_INotifiable::TYPE_SMS);
			}
			
			// parsing a normal index that is just an address
			else if(is_string($value))
				$dest = $value;
			
			if($dest !== null && $dest !== ''){
				$this->to[] = $dest;
				
			}
		}
		return $this;
		
	}
	
	public function setContent($content, $mimetype = null){
		return $this->setParameter('message', $content);
	}
	
	public function decorate($replacements){
		$this->replacements = $replacements;
	}
	
	public function send(&$result, &$failures){
		
		$result = 0;
		$failures = array();
		$to = $this->to;
		$text = $this->getParameter('message');
				
		foreach($this->to as $number){
			$this->setParameter('number', $number);
			
			if($this->replacements !== null && isset($this->replacements[$number])){
				$this->setParameter('message', str_replace(array_keys($this->replacements[$number]), array_values($this->replacements[$number]), $text));
			}
			
			$res = parent::submit();
			
			if($res === 0){
				$failures[] = $number;
			}
			
			$result += $res;
		}
		
		$this->to = $to;
		$this->setParameter('message', $text);
		
		return $this;
	
	}
}

?>