<?php 
 
    
class SMSGateway {

	const API_URL = 'http://sms-gateway.binarydesign.co.nz/send.json';
	//const API_URL = 'http://dev.pricemaker.co.nz/api/notification/test/';
	
	private $handle = null;
	private $params = array();
	private $static_args = '';
		
	private static $static = array(
		'account_id', 'auth_key'
	);
	
	public function __construct($server = null){
			
		if($server == null)
			$server = self::API_URL;
				
		$this->handle = curl_init($server); 
        curl_setopt($this->handle, CURLOPT_POST, true); 
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true); 
		
	}

    public function __destruct(){
   		curl_close ($this->handle);
    }

	
	public function setParameter($key, $value){
		$this->params[$key] = $value;
		
		return $this;
	}
	
	public function getParameter($key){
		return isset($this->params[$key]) ? $this->params[$key] : null;
	}
	
	public function submit(){
		if($this->static_args == ''){
			$args = array();
			foreach(self::$static as $parameter){
				if(!isset($this->params[$parameter]))
					new ApplicationException('Missing parameter '.$parameter.' when attempting to send SMS');
				else {
					$args[] = $parameter . '=' . rawurlencode($this->params[$parameter]);
					unset($this->params[$parameter]);
				}
			}
			$this->static_args = implode('&', $args);		
		}
		
		if(!isset($this->params['number']) || !isset($this->params['message']))
			new ApplicationException('Missing parameter "to" or "text" when attempting to send SMS');

		$session_args = $this->static_args;

		foreach($this->params as $key => $value)
			$session_args .= '&' . $key . '=' . rawurlencode($value);
				
		curl_setopt($this->handle, CURLOPT_POSTFIELDS, $session_args); 
	
		$output = curl_exec($this->handle); 
                
    	if(stripos($output, 'K') !== false)
    		return 1;
    	else return 0;
    	
    }
    


}    
    
?>