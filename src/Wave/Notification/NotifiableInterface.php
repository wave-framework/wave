<?php

namespace Wave\Notification;

interface NotifiableInterface {
	
	const TYPE_EMAIL = 'email';
	const TYPE_SMS = 'sms';
	
	public function getNotificationAddress($type);
	public function getNotificationName($type);
		
}


?>