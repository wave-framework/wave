<?php

/**
 *	Provides singleton convenience methods for the monolog library 
 *	
 *	Internally tracks Monolog channels so they can be accessed through 
 *	a singleton function. By default sets a StreamHandler to the file
 *	specified in the `wave.php` configuration file at the default level.
 *
 *	@author Patrick patrick@hindmar.sh
**/

namespace Wave;
use \Wave\Config,
	\Monolog\Logger,
	\Monolog\Handlers\AbstractHandler;

class Log {
	
	private static $default_level = null;
	private static $all_levels = array(
		Logger::DEBUG,
		Logger::INFO,
		Logger::WARNING,
		Logger::ERROR,
		Logger::CRITICAL,
		Logger::ALERT
	);

	/**
	 *	Internal store for initialised channels 
	**/
	private static $channels = array();

	/**
	 *	Set the default level for logging messages to the 
	 *
	 * 	@param $level The new default level
	**/
	public static function setDefaultLevel($level){
		if(!in_array($level, self::$all_levels))
			throw new \Wave\Exception("Invalid default log level of $level set");
		self::$default_level = $level;
	}

	/**
	 *	@return int The default log level
	**/
	public static function getDefaultLevel(){
		if(self::$default_level === null)
			self::$default_level = Config::get('wave')->logger->default_level;

		return self::$default_level;
	}

	/**
	 *	Create a new channel with the specified Handler
	 *
	 *	If a `$handler` is not specified it will set a default StreamHandler
	 *	to the logfile specified in the `wave.php` configuration file.
	 *
	 *	@param String $channel The name of the channel to return
	 *	@param \Monolog\Handlers\AbstractHandler $handler The handler to attach to the channel [optional]
	 *
	 *	@return \Monolog\Logger A new Logger instance
	**/
	public static function createChannel($channel, AbstractHandler $handler = null){
		self::$channels[$channel] = new Logger($channel);
		if($handler === null){
			$log_path = Config::get('wave')->path->logs;
			$log_path .= Config::get('wave')->logger->default_file;
			$log_dir = realpath(dirname($log_path));

			if(!is_writable($log_dir)){
				@mkdir($log_dir, 0770, true);
			}

			self::$channels[$channel]->pushHandler(new StreamHandler($log_path, self::getDefaultLevel()));
		}
		else{
			self::$channels[$channel]->pushHandler($handler);
		}
		
		return self::$channels[$channel];
	}

	/**
	 *	@param $name 
	 *	@param bool $create_if_empty Create the channel if it does not exist (default=true)
	 *
	 *	@return  \Monolog\Logger A Logger instance for the given channel or `null` if not found
	**/
	public static function getChannel($name, $create = true){
		if(!isset(self::$channels[$name])){
			if($create === true) return self::createChannel($name);
			else return null;
		}
		return self::$channels[$name];
	}


	/**
	 *	A shorthand for writing a message to a given channel
	 *
	 *	@param $channel The channel to write to
	 *	@param $message The message to write
	 *	@param $level The level of the message (debug, info, notice, warning, error, critical)
	 *
	 *	@return Bool Whether the message has been written
	**/
	public static function write($channel, $message, $level = Logger::INFO){
		$channel = self::getChannel($channel);

		return $channel->addRecord($message, $level);
	}


}


?>