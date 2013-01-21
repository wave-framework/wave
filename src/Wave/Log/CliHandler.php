<?php


namespace Wave\Log;

use Monolog\Logger,
    Monolog\Handler\AbstractProcessingHandler;

class CliHandler extends AbstractProcessingHandler{

    const LINE_FORMAT = '[%level_name%] %message%';

	protected static $colors = array(
		Logger::DEBUG     => '1;34', // light_blue
		Logger::INFO      => '0;36', // cyan
		Logger::WARNING   => '1;33', // yellow
		Logger::ERROR     => '1;31', // light red
		Logger::CRITICAL  => '0;31', // red
		Logger::ALERT     => '0;35', // purple
    );

	/**
	 * Outputs a string to the cli.
	 *
	 * @param	string|array    $record	the text to output, or array of lines
	 */
	protected function write(array $record)
	{
		$stream = $record['level'] >= Logger::ERROR ? STDERR : STDOUT;
		$text = static::color($record['formatted'], $record['level']);

		$beep = '';
		if($record['level'] >= Logger::CRITICAL)
			$beep .= static::beep();
		if($record['level'] >= Logger::ALERT)
			$beep .= static::beep();

		fwrite($stream, $beep.$text.PHP_EOL);
	}

	/**
	 * Beeps a certain number of times.
	 *
	 * @param	int $num	the number of times to beep
	 */
	public static function beep($num = 1)
	{
		return str_repeat("\x07", $num);
	}


	/**
	 * Returns the given text with the correct color codes for the given level
	 *
	 * @param	string	$text		the text to color
	 * @param	int  	$level      the level of message
	 *
	 * @return	string	the color coded string
	 */
	public static function color($text, $level)
	{

		if(isset(static::$colors[$level])){
			$prefix = "\033[".static::$colors[$level]."m";
			$text = $prefix.$text."\033[0m";
		}

		return $text;
	}

}