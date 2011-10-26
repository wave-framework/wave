<?php



interface Wave_IAuthable {

	public static function loadByIdentifier(array $params);
		
	public function hasAccess(array $level, $vars);
	
	public static function noAuthAction(array $data);
	
	public function getAuthKey();
	
	public function getCSRFKey();
	
	public function confirmCSRFKey($key);
	
}


?>