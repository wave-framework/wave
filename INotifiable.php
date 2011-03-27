<?php


interface Wave_INotifiable {
	
	const TYPE_EMAIL = 'email';
	
	public function getNotificationAddress($type);
	public function getNotificationName($type);
		
}


?>