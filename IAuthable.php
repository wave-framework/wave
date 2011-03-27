<?php



interface Wave_IAuthable {

	public static function loadByIdentifier(array $params);
		
	public function hasAccess(array $level, $vars);
	
	public static function noAuthAction();
	
	public function getAuthKey();

}


?>