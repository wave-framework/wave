<?php

namespace Wave;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Twig\Loader\FilesystemLoader;

class View {

    private $twig;

    private static $_filters = array();
    private static $_globals = array();
    private static $_timezone;
    private static $_date_format;

    private static $instance = null;


    private function __construct() {

        $loader = new FilesystemLoader(Config::get('wave')->path->views);

        $conf = array('cache' => Config::get('wave')->view->cache);
        if(Core::$_MODE !== Core::MODE_PRODUCTION) {
            $conf['auto_reload'] = true;
            $conf['debug'] = true;
        }
        $this->twig = new View\TwigEnvironment($loader, $conf);
        $this->twig->addExtension(new View\TwigExtension());
        foreach(self::$_filters as $name => $action)
            $this->twig->addFilter($name, $action);
        $this->twig->registerUndefinedFilterCallback(
            function ($name) {
                if(function_exists($name)) {
                    return new \Twig_Filter_Function($name);
                }

                return false;
            }
        );
        $this->twig->addFilter(new \Twig_SimpleFilter('last','\\Wave\\Utils::array_peek'));
        $this->twig->addFilter(new \Twig_SimpleFilter('short',
                '\\Wave\\Utils::shorten', array(
                    'pre_escape' => 'html',
                    'is_safe' => array('html')
                )
            )
        );

        // global variables
        $this->twig->addGlobal('_assets', Config::get('deploy')->assets);
        $this->twig->addGlobal('_host', Config::get('deploy')->profiles->default->baseurl);
        $this->twig->addGlobal('_mode', Core::$_MODE);

        if(self::$_timezone !== null)
            $this->twig->getExtension('core')->setTimezone(self::$_timezone);

        if(self::$_date_format !== null)
            $this->twig->getExtension('core')->setDateFormat(self::$_date_format);

        if(Config::get('deploy')->mode == Core::MODE_DEVELOPMENT || isset($_REQUEST['_wave_show_debugger']))
            $this->twig->addGlobal('_debugger', Debug::getInstance());

        foreach(self::$_globals as $key => $value)
            $this->twig->addGlobal($key, $value);

    }

    public static function getInstance() {

        if(self::$instance === null)
            self::$instance = new self();

        return self::$instance;
    }


    public function render($template, $data = array()) {

        // locate the template file
        $template .= Config::get('wave')->view->extension;
        Hook::triggerAction('view.before_load_template', array(&$this, &$template));
        $loaded_template = $this->twig->loadTemplate($template);
        Hook::triggerAction('view.before_render', array(&$this, &$data));
        $html = $loaded_template->render($data);
        Hook::triggerAction('view.after_render', array(&$this, &$html));
        return $html;

    }

    public static function registerFilter($filter, $action) {
        if(self::$instance == null) self::$_filters[$filter] = $action;
        else self::$instance->twig->addFilter($filter, $action);
    }

    public static function registerGlobal($name, $value) {
        if(self::$instance == null) self::$_globals[$name] = $value;
        else self::$instance->twig->addGlobal($name, $value);

    }

    public static function setTimezone($timezone) {
        if(self::$instance == null) self::$_timezone = $timezone;
        else self::$instance->twig->getExtension('core')->setTimezone($timezone);
    }

    public static function setDefaultDateFormat($format) {
        if(self::$instance == null) self::$_date_format = $format;
        else self::$instance->twig->getExtension('core')->setDateFormat($format);
    }

    public static function generate() {

        $cache_dir = Config::get('wave')->view->cache;
        if(!file_exists($cache_dir))
            @mkdir($cache_dir, 0770, true);

        if(!file_exists($cache_dir))
            throw new Exception('Could not generate views, the cache directory does not exist or is not writable');

        // delete caches
        $dir_iterator = new RecursiveDirectoryIterator($cache_dir);
        $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file) {
            if($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            $func = $file->isDir() ? 'rmdir' : 'unlink';
            $func($file->getRealPath());
        }
        $self = self::getInstance();

        $source_path = Config::get('wave')->path->views;
        $dir_iterator = new RecursiveDirectoryIterator($source_path);
        $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::CHILD_FIRST);
        $l = strlen($source_path);
        foreach($iterator as $template) {
            $filename = $template->getFilename();
            if(pathinfo($filename, PATHINFO_EXTENSION) != 'phtml') continue;
            $self->twig->loadTemplate(substr($template, $l));
        }

    }

}


?>