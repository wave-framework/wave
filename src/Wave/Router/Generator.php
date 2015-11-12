<?php

namespace Wave\Router;

use Wave;
use Wave\Router;

class Generator {

    public static function generate() {
        $reflector = new Wave\Reflector(Wave\Config::get('wave')->path->controllers);
        $reflected_options = $reflector->execute();

        $all_actions = self::buildRoutes($reflected_options);
        foreach($all_actions as $profile => $actions) {
            $route_node = new Node();
            foreach($actions as $action) {
                foreach($action->getRoutes() as $route) {
                    $route_node->addChild($route, $action);
                }
            }
            Wave\Cache::store(Router::getCacheName($profile, 'table'), $actions);
            Wave\Cache::store(Router::getCacheName($profile, 'tree'), $route_node);
        }
    }

    public static function buildRoutes($controllers) {

        $compiled_routes = array();
        // iterate all the controllers and make a tree of all the possible path
        foreach($controllers as $controller) {
            $base_route = new Action();
            // set the route defaults from the Controller annotations (if any)
            foreach($controller['class']['annotations'] as $annotation) {
                $base_route->addAnnotation($annotation);
            }

            foreach($controller['methods'] as $method) {
                $route = clone $base_route; // copy from the controller route

                if($method['visibility'] == Wave\Reflector::VISIBILITY_PUBLIC) {
                    foreach($method['annotations'] as $annotation)
                        $route->addAnnotation($annotation);

                    foreach($method['parameters'] as $parameter) {
                        /** @var \ReflectionParameter $parameter */
                        $type = $parameter->getClass() !== null ? $parameter->getClass()->getName() : null;
                        $route->addMethodParameter($parameter->getName(), $type);
                    }

                }

                $route->setAction($controller['class']['name'] . '.' . $method['name']);

                if($route->hasRoutes()) {
                    if(isset($compiled_routes[$base_route->getProfile()][$route->getAction()])) {
                        throw new \LogicException(sprintf("Action %s is declared twice", $route->getAction()));
                    }
                    $compiled_routes[$base_route->getProfile()][$route->getAction()] = $route;
                }
            }
        }

        return $compiled_routes;
    }

}


?>