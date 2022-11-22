<?php

/**
 *    DB Generation class. Creates models.
 *
 * @author Michael michael@calcin.ai
 **/

namespace Wave\DB;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Wave;

class Generator
{

    /** @var Environment $twig */
    private static $twig;

    private static $baseModelClass = '\\Wave\\DB\\Model';

    /**
     * Generate the base models (and any model stubs if they don't exist) based on the schema
     * from the database
     */
    public static function generate(&$orphans = null)
    {

        self::initTwig();
        $databases = Wave\DB::getAll();

        foreach ($databases as $database) {

            self::createModelDirectory($database);
            $tables = $database->getTables($database);

            $existing_files = glob(self::getModelPath($database) . '*.php');

            foreach ($tables as $table) {
                $base_file = self::getBaseModelPath($database) . $table->getClassName() . '.php';
                $base_rendered = self::renderTemplate('base-model', array('table' => $table));
                self::writeTemplateIfChanged($table, $base_file, $base_rendered);

                $stub_file = self::getModelPath($database) . $table->getClassName() . '.php';
                if (!file_exists($stub_file))
                    file_put_contents($stub_file, self::renderTemplate('stub-model', array('table' => $table)));

                $current_files[] = $stub_file;
            }

            $orphans = array_diff($existing_files, $current_files);

        }
    }

    /**
     * @param string $filepath
     * @param string $content
     *
     * @return bool
     */
    private static function writeTemplateIfChanged($table, $filepath, $contents)
    {

        if (file_exists($filepath)) {
            $rendered_fingerprint = $table->getSchemaFingerprint();
            $current_contents = file_get_contents($filepath);
            preg_match('/@fingerprint: ([0-9a-f]{32})/', $current_contents, $matches);
            if (!isset($matches[1]) || $rendered_fingerprint !== $matches[1]) {
                Wave\Log::write('generator', sprintf('Table [%s] has changed, updating base model file...', $table->getName()), Wave\Log::DEBUG);
                file_put_contents($filepath, $contents);
            }
        } else {
            Wave\Log::write('generator', sprintf('Base Model for table [%s] doesn\'t exist, creating...', $table->getName()), Wave\Log::DEBUG);
            file_put_contents($filepath, $contents);
        }

    }

    /**
     * @param string $template
     * @param array $data
     * @param string $template_ext
     *
     * @return string
     */
    private static function renderTemplate($template, $data, $template_ext = '.phpt')
    {

        $loaded_template = self::$twig->load($template . $template_ext);
        return $loaded_template->render($data);

    }

    private static function initTwig()
    {

        $loader = new FilesystemLoader(__DIR__ . DS . 'Generator' . DS . 'Templates');
        self::$twig = new Environment($loader, array('autoescape' => false));
        self::$twig->addFilter(new TwigFilter('addslashes'));
        self::$twig->addFilter(
            new TwigFilter('export',
                function ($var) {
                    return var_export($var, true);
                }
            )
        );
        self::$twig->addFilter(new TwigFilter('implode'));
        self::$twig->addFilter(new TwigFilter('singularize', '\\Wave\\Inflector::singularize'));
        self::$twig->addFilter(new TwigFilter('formatType', '\\Wave\\DB\\Generator::formatTypeForSource'));
        self::$twig->addGlobal('baseModelClass', static::$baseModelClass);
    }

    public static function formatTypeForSource($type)
    {

        if (null === $type)
            return "null";
        else if (is_int($type) || is_float($type))
            return "$type";
        else if (is_bool($type))
            return $type ? "true" : "false";
        else
            return "'$type'";

    }

    /**
     * @param Wave\DB $database
     */
    private static function createModelDirectory(Wave\DB $database)
    {

        $basedir = self::getBaseModelPath($database);

        if (!file_exists($basedir))
            mkdir($basedir, 0775, true);
    }

    /**
     * @param \Wave\DB $database
     *
     * @return string
     */
    private static function getBaseModelPath(Wave\DB $database)
    {
        return self::getModelPath($database) . 'Base' . DS;
    }

    /**
     * @param \Wave\DB $database
     *
     * @return string
     */
    private static function getModelPath(Wave\DB $database)
    {

        $namespace = $database->getNamespace(false);
        $model_directory = Wave\Config::get('wave')->path->models;

        return $model_directory . DS . $namespace . DS;

    }

    public static function setBaseModelClass($baseModelClass)
    {
        self::$baseModelClass = $baseModelClass;
    }

}
