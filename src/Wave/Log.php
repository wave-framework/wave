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
    \Wave\Log\CliHandler,
	\Wave\Log\ExceptionIntrospectionProcessor,
	\Monolog\Logger,
	\Monolog\Handler\AbstractHandler,
	\Monolog\Handler\StreamHandler,
    \Monolog\Formatter\LineFormatter;
	

class Log extends Logger {
	
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
	 * Internal store for initialised channels
	 */
	protected static $channels = array();

	/**
	 *	Initialise the Log with a default channel for framework logs
	**/
	public static function init(){

	}

    /**
     * Set the default level for logging messages to the
     *
     * @param int $level The new default level
     * @throws Exception
     * @return void
     */
	public static function setDefaultLevel($level){
		if(!in_array($level, self::$all_levels))
			throw new \Wave\Exception("Invalid default log level of $level set");
        static::$default_level = $level;
	}

	/**
	 *	@return int The default log level
	**/
	public static function getDefaultLevel(){
		if(static::$default_level === null)
            static::$default_level = Config::get('wave')->logger->default_level;

		return static::$default_level;
	}

    /**
     * Create a new channel with the specified Handler
     *
     * If a `$handler` is not specified it will set a default StreamHandler
     * to the logfile specified in the `wave.php` configuration file.
     *
     * @param string $channel The name of the channel to return
     * @param \Monolog\Handler\AbstractHandler $handler The handler to attach to the channel [optional]
     *
     * @return \Monolog\Logger A new Logger instance
     */
	public static function createChannel($channel, AbstractHandler $handler = null){
        static::$channels[$channel] = new Logger($channel);
		if($handler === null){
			$log_path = Config::get('wave')->path->logs;
			$log_path .= Config::get('wave')->logger->default_file;
			$log_dir = realpath(dirname($log_path));

			if(!is_writable($log_dir)){
				@mkdir($log_dir, 0770, true);
			}
			$handler = new StreamHandler($log_path, Config::get('wave')->logger->default_level);
			$handler->pushProcessor(new ExceptionIntrospectionProcessor());
            static::$channels[$channel]->pushHandler($handler);

            if(PHP_SAPI === 'cli'){
                $cli_handler = new CliHandler();
                $cli_handler->setFormatter(new LineFormatter(CliHandler::LINE_FORMAT));

                static::$channels[$channel]->pushHandler($cli_handler);
            }
		}
		else{
            static::$channels[$channel]->pushHandler($handler);
		}
		
		return static::$channels[$channel];
	}

    /**
     * @param string $name
     * @param bool $create Create the channel if it does not exist (default=true)
     *
     * @return  \Monolog\Logger A Logger instance for the given channel or `null` if not found
     */
	public static function getChannel($name, $create = true){
		if(!isset(static::$channels[$name])){
			if($create === true) return static::createChannel($name);
			else return null;
		}
		return static::$channels[$name];
	}

    /**
     * Set a Logger instance for a channel
     *
     * @param string $name The channel name to set to
     * @param \Monolog\Logger $instance
     *
     * @return \Monolog\Logger
     */
	public static function setChannel($name, Logger $instance){
		return static::$channels[$name] = $instance;
	}

	/**
	 *	A shorthand for writing a message to a given channel
	 *
	 *	@param string $channel The channel to write to
	 *	@param string $message The message to write
	 *	@param int $level The level of the message (debug, info, notice, warning, error, critical)
	 *
	 *	@return Bool Whether the message has been written
	**/
	public static function write($channel, $message, $level = Logger::INFO){
		$channel = static::getChannel($channel);

		return $channel->addRecord($level, $message);
	}

}


?>