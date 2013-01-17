<?php

namespace Wave\Notification;

use \Wave\Config;

/**
 *	Extends the Swift Message class for sending emails within the application.
 *	Overloads certain Swift Mailer functions and provides references to Mailer and Transport instances
 *	
 *	@extends Swift_Message - See http://swiftmailer.org/docs/messages for function documentation
 *	@implements INotification - This class can be used as a dispatch method for the Notifier library
**/
class Email extends \Swift_Message {

	private static $_isbuilt = false;

	private static $transport;
	private static $mailer;
	
	private static $config;
	
	private static $instance;
		
	public static function create(){		
		
		if(!self::$_isbuilt){
			self::$config = Config::get('deploy')->email;

			if(self::$config->transport === 'SMTP'){
				self::$transport = \Swift_SmtpTransport::newInstance(self::$config->server, self::$config->port);
				if(self::$config->auth)
					self::$transport->setUsername(self::$config->username)
									->setPassword(self::$config->password);
			}
			else if(self::$config->transport = 'PHPMAIL')
				self::$transport = \Swift_MailTransport::newInstance();
			else if(self::$config->transport = 'SENDMAIL')
				self::$transport = \Swift_SendmailTransport::newInstance('/usr/sbin/postfix -bs');
			else
				new \Wave\Exception('No transport type defined in configuration.');

			self::$mailer = \Swift_Mailer::newInstance(self::$transport);
			
			self::$_isbuilt = true;
		}
		
		$instance = new self();
		$instance->setFrom(array(self::$config->fromaddr => self::$config->fromname));
		
		return $instance;
	}
	
	public function setContent($content, $mimetype = null){
		if($mimetype == null) $mimetype = self::$config->default_mimetype;
		return $this->setBody($content, $mimetype);
	}
	
	/**
	 *	Overloads the Swift Mailer setTo function to add object functionality
	 *
	 *	@param $addresses - This can be an array of objects, or an array of address 
	 *						name pairs. 
	 *						If it is an array of objects the property names must be 
	 *						defined in the configuration
	**/ 
	public function setTo($addresses, $name = null){
		$new_addresses = array();
		if(is_array($addresses)){
			foreach($addresses as $key => $value){
				// parsing an object
				if($value instanceof NotifiableInterface){
					$e = $value->getNotificationAddress(NotifiableInterface::TYPE_EMAIL);
					$n = $value->getNotificationName(NotifiableInterface::TYPE_EMAIL);
						
					$new_addresses[$e] = $n;
				}
				// parsing a key value pair of address => name
				else if(!is_numeric($key))
					$new_addresses[$key] = $value;
				// parsing a normal index that is just an address
				else if(is_string($value))
					$new_addresses[$value] = '';
			}
			$addresses = $new_addresses;
		} else if($addresses instanceof NotifiableInterface){
			$e = $addresses->getNotificationAddress(NotifiableInterface::TYPE_EMAIL);
			$n = $addresses->getNotificationName(NotifiableInterface::TYPE_EMAIL);
			
			
			return parent::setTo($e, $n);
		}
		
		return parent::setTo($addresses, $name);
	}
	
	/**
	 * 	Registers the Swift Decorator Plugin
	 * 	See http://swiftmailer.org/docs/decorator-plugin for $replacements format
	**/ 	
	public function decorate($replacements){
		$decorator = new Swift_Plugins_DecoratorPlugin($replacements);
		self::$mailer->registerPlugin($decorator);
		return $this;
	}

	
	/**
	 *	Alias the Swift Mailer send function to send this message
	**/
	public function send(&$result = 0, &$failures = array()){
		$result = self::$mailer->send($this, $failures);
		return $this;
	}	
	
	/**
	 *	Alias the Swift Mailer batchSend function to send this message
	**/
	public function batchSend(&$result, &$failures){
		$result = self::$mailer->batchSend($this, $failures);
		return $this;
	}	
	
}


?>