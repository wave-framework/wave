<?php

namespace Wave;

class Reflector {

    const SCAN_DIRECTORY = 1;
    const SCAN_FILE = 2;

    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PROTECTED = 'protected';
    const VISIBILITY_PRIVATE = 'private';

    const DEFAULT_FILE_FILTER = '.php';

    private $_classes = null;

    public function __construct($handle, $type = self::SCAN_DIRECTORY, $filter = self::DEFAULT_FILE_FILTER, $recursive = true) {
        if(!file_exists($handle))
            throw new \Wave\Exception('Directory ' . $handle . ' cannot be resolved in \Wave\Refector', 0);

        $this->_classes = array();
        if($type == self::SCAN_FILE && is_file($handle)) {
            $class = self::findClass($handle);
            if($class !== false)
                $this->_classes[] = $class;
        } else if($type == self::SCAN_DIRECTORY && is_dir($handle)) {

            if($recursive === true) {
                $dir_iterator = new \RecursiveDirectoryIterator($handle);
                $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
            } else {
                $dir_iterator = new \FilesystemIterator($handle);
                $iterator = new \IteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
            }
            foreach($iterator as $file) {
                $class = self::findClass($file);
                if($class !== false)
                    $this->_classes[] = $class;
            }
        }

    }

    public function execute($include_inherited_members = false) {
        if(!isset($this->_classes[0]))
            throw new \Wave\Exception('No files to reflect on');

        $classes = array();

        foreach($this->_classes as $class) {
            $reflector = new \ReflectionClass($class);

            $class_annotations = Annotation::parse($reflector->getDocComment(), $class);
            $parent_class = $reflector->getParentClass();

            $c = array(
                'name' => $class,
                'subclasses' => $parent_class instanceof \ReflectionClass ? $parent_class->getName() : '',
                'implements' => $reflector->getInterfaceNames(),
                'annotations' => $class_annotations
            );

            $methods = array();
            foreach($reflector->getMethods() as $method) {
                $declaring_class = $method->getDeclaringClass()->getName();
                // don't put inherited methods on here plz
                if($declaring_class != $class && !$include_inherited_members)
                    continue;

                $annotations = Annotation::parse($method->getDocComment(), $class);
                $method_annotations = array();
                foreach($annotations as $annotation) {
                    $method_annotations[] = $annotation;
                }

                $method_name = $method->getName();
                $visibility = $method->isPublic() ? self::VISIBILITY_PUBLIC : ($method->isPrivate() ? self::VISIBILITY_PRIVATE : self::VISIBILITY_PROTECTED);
                $methods[$method_name] = array(
                    'name' => $method_name,
                    'visibility' => $visibility,
                    'static' => $method->isStatic(),
                    'parameters' => $method->getParameters(),
                    'annotations' => $method_annotations,
                    'declaring_class' => $method->getDeclaringClass()->getName()
                );
            }

            $properties = array();
            foreach($reflector->getProperties() as $property) {
                $declaring_class = $property->getDeclaringClass()->getName();
                // don't put inherited methods on here plz
                if($declaring_class != $class && !$include_inherited_members)
                    continue;

                $annotations = Annotation::parse($property->getDocComment(), $class);
                $property_annotations = array();
                foreach($annotations as $annotation) {
                    $property_annotations[] = $annotation;
                }

                $property_name = $property->getName();
                $visibility = $property->isPublic() ? self::VISIBILITY_PUBLIC : ($property->isPrivate() ? self::VISIBILITY_PRIVATE : self::VISIBILITY_PROTECTED);
                $properties[$property_name] = array(
                    'name' => $property_name,
                    'visibility' => $visibility,
                    'static' => $property->isStatic(),
                    'annotations' => $property_annotations,
                    'declaring_class' => $property->getDeclaringClass()->getName()
                );
            }

            $classes[$class] = array('class' => $c, 'methods' => $methods, 'properties' => $properties);

        }
        return $classes;

    }

    /**
     * Returns the full class name for the first class in the file.
     *
     * @param string $file A PHP file path
     *
     * @return string|false Full class name if found, false otherwise
     */
    protected function findClass($file) {
        // skip anything that's not a regular file
        if (!is_file($file))
            return false;
        
        $class = false;
        $namespace = false;
        $tokens = token_get_all(file_get_contents($file));
        for($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if(!is_array($token)) {
                continue;
            }

            if(true === $class && T_STRING === $token[0]) {
                return $namespace . '\\' . $token[1];
            }

            if(true === $namespace && T_STRING === $token[0]) {
                $namespace = '';
                do {
                    $namespace .= $token[1];
                    $token = $tokens[++$i];
                } while($i < $count && is_array($token) && in_array($token[0], array(T_NS_SEPARATOR, T_STRING)));
            }

            if(T_CLASS === $token[0]) {
                $class = true;
            }

            if(T_NAMESPACE === $token[0]) {
                $namespace = true;
            }
        }

        return false;
    }

}

?>
