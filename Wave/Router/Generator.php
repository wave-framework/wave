<?php

namespace Wave\Router;
use Wave;

class Generator {
	
	public static function generate(){
		$reflector = new Wave\Reflector(Wave\Config::get('wave')->path->controllers);				
		$reflected_options = $reflector->execute();
			
		$all_actions = self::buildRoutes($reflected_options);

		foreach($all_actions as $domain => $actions){					
			$route_node = new Node();
			foreach($actions as $action){
				foreach($action->getRoutes() as $route){
					$route_node->addChild($route, $action);
				}
			}
			
			Wave\Cache::store(Wave\Router::getCacheName($domain), $route_node);
		}
	}
	
	public static function buildRoutes($controllers){
		
		$compiled_routes = array();
		// iterate all the controllers and make a tree of all the possible path
		foreach($controllers as $controller){
			$base_route = new Action();
			// set the route defaults from the Controller annotations (if any)
			foreach($controller['class']['annotations'] as $annotation){
				$annotation->apply($base_route);
				//self::applyAnnotation($annotation, $base_route);
			}
			
			foreach($controller['methods'] as $method){
				$route = clone $base_route; // copy from the controller route
				
				if($method['visibility'] == Wave\Reflector::VISIBILITY_PUBLIC){
					foreach($method['annotations'] as $annotation)
						$annotation->apply($route);
						//self::applyAnnotation($annotation, $route);
				}
				
				$route->setAction($controller['class']['name'] . '.' . $method['name']);
				
				if($route->hasRoutes())
					$compiled_routes[$base_route->getBaseURL()][] = $route;
			}
		}
		return $compiled_routes;	
	}
	
	private static function applyAnnotation($annotation, &$route){
		
		$annotation_type = get_class($annotation);
		switch ($annotation_type){
			case 'Wave_Annotation_BaseRoute': 
				$basepath = $annotation->parameters[0];					
				return $route->setBaseRoute($basepath);
			
			case 'Wave_Annotation_Route': 
				return $route->addRoute($annotation->methods, $annotation->url);
			
			case 'Wave_Annotation_RequiresLevel':
				return $route->setRequiresLevel($annotation->methods, $annotation->inherit);
				
			case 'Wave_Annotation_RespondsWith':
				return $route->setRespondsWith($annotation->methods, $annotation->inherit);
			
			case 'Wave_Annotation_BaseURL':
				return $route->setBaseURL($annotation->parameters[0]);
				
			
		}
	}


}


?>