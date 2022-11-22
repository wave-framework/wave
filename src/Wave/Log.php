<?php

/**
 *    Provides singleton convenience methods for the monolog library
 *
 *    Internally tracks Monolog channels so they can be accessed through
 *    a singleton function. By default sets a StreamHandler to the file
 *    specified in the `wave.php` configuration file at the default level.
 *
 * @author Patrick patrick@hindmar.sh
 **/

namespace Wave;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Wave\Log\CliHandler;
use Wave\Log\ExceptionIntrospectionProcessor;


class Log extends Logger
{

    protected static $default_handlers = null;
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
     *    Initialise the Log with a default channel for framework logs
     **/
    public static function init()
    {

    }

    /**
     * Set the default level for logging messages to the
     *
     * @param int $level The new default level
     * @return void
     * @throws Exception
     */
    public static function setDefaultLevel($level)
    {
        if (!in_array($level, self::$all_levels))
            throw new \Wave\Exception("Invalid default log level of $level set");
        static::$default_level = $level;
    }

    /**
     * @return int The default log level
     **/
    public static function getDefaultLevel()
    {
        if (static::$default_level === null)
            static::$default_level = Config::get('wave')->logger->file->level;

        return static::$default_level;
    }

    public static function getDefaultHandlers()
    {
        if (static::$default_handlers === null) {

            static::$default_handlers = array();

            $log_path = Config::get('wave')->path->logs;
            $log_path .= Config::get('wave')->logger->file->file;
            $log_dir = dirname($log_path);


            if (!is_writable($log_dir)) {
                @mkdir($log_dir, 0770, true);
            }

            $stream_handler = new StreamHandler($log_path, static::getDefaultLevel());
            $stream_handler->pushProcessor(new ExceptionIntrospectionProcessor());

            static::pushDefaultHandler($stream_handler);

            if (PHP_SAPI === 'cli') {
                $cli_handler = new CliHandler(Config::get('wave')->logger->cli->level);
                $cli_handler->setFormatter(new LineFormatter(CliHandler::LINE_FORMAT));
                static::pushDefaultHandler($cli_handler);
            }

        }

        return static::$default_handlers;
    }

    public static function pushDefaultHandler(AbstractHandler $handler)
    {
        if (static::$default_handlers === null) {
            static::$default_handlers = self::getDefaultHandlers();
        }

        array_unshift(static::$default_handlers, $handler);
    }

    /**
     * Create a new channel with the specified Handler
     *
     * If a `$handler` is not specified it will set a default StreamHandler
     * to the logfile specified in the `wave.php` configuration file.
     *
     * @param string $channel The name of the channel to return
     * @param array $handlers Any handlers to attach to the channel [optional]
     * @param bool $use_default_handlers
     *
     * @return \Monolog\Logger A new Logger instance
     */
    public static function createChannel($channel, array $handlers = array(), $use_default_handlers = true)
    {
        if ($use_default_handlers)
            $handlers += static::getDefaultHandlers();

        static::$channels[$channel] = new Logger($channel, $handlers);
        return static::$channels[$channel];
    }

    /**
     * @param string $name
     * @param bool $create Create the channel if it does not exist (default=true)
     *
     * @return  \Monolog\Logger A Logger instance for the given channel or `null` if not found
     */
    public static function getChannel($name, $create = true)
    {
        if (!isset(static::$channels[$name])) {
            if ($create === true) return static::createChannel($name);
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
    public static function setChannel($name, Logger $instance)
    {
        return static::$channels[$name] = $instance;
    }

    /**
     *    A shorthand for writing a message to a given channel
     *
     * @param string $channel The channel to write to
     * @param string $message The message to write
     * @param int $level The level of the message (debug, info, notice, warning, error, critical)
     *
     * @return Bool Whether the message has been written
     **/
    public static function write($channel, $message, $level = Logger::INFO, $context = array())
    {
        $channel = static::getChannel($channel);

        return $channel->addRecord($level, $message, $context);
    }

}