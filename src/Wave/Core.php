<?php

namespace Wave;

class Core {

    const MODE_TEST = 'test';
    const MODE_DEVELOPMENT = 'development';
    const MODE_PRODUCTION = 'production';

    static $_MODE = self::MODE_PRODUCTION;

    public static function setErrorReporting($display = false) {
        error_reporting($display ? E_ALL | E_STRICT : E_ALL & ~E_DEPRECATED);
        ini_set('display_errors', $display ? '1' : '0');
    }

    public static function bootstrap(array $config) {

        if(!isset($config['mode']))
            $config['mode'] = Config::get('deploy')->mode;

        Debug::init($config['debug']);

        self::$_MODE = $config['mode'];
        self::setErrorReporting($config['mode'] !== self::MODE_PRODUCTION);

        Cache::init();

        Debug::getInstance()->addCheckpoint('bootstrapped');
    }
}
