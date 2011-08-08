<?php


class Wave_Router_Generator {
	
	public static function generate(){
		$reflector = new Wave_Reflector(Wave_Config::get('wave')->path->controllers);				
		$reflected_options = $reflector->execute();
			
		$all_routes = self::buildRoutes($reflected_options);

		foreach($all_routes as $d => $routes){					
			$r = new Wave_Router_Node();
			foreach($routes as $route)
				$r->addChild($route['url'], $route['action']);
			
			Wave_Cache::store(Wave_Router::getCacheName($d), $r);
		}
	}
	
	public static function buildRoutes($controllers){
	
		$config_baseurl = Wave_Config::get('deploy')->baseurl;
		if(strpos($config_baseurl, 'http://') !== false)
			$config_baseurl = substr($config_baseurl, 7);
		else if(strpos($config_baseurl, 'https://') !== false)
			$config_baseurl = substr($config_baseurl, 8);
		// Loop each controller 
		// 		if the controller inherits from Wave_IResource then declare the default CRUD routes
		//		if the controller or any of it's methods have annotations, process them
		//		if there are any public methods that dont fall into either previous category process them as verbs
		$output_routes = array();
 		foreach($controllers as $cname => $controller){
			// holds the base url if the controller specifies it.
			$c_baseurl = null;
			// holds the response methods if the controller specifies it
			$c_respondswith = null;
			// holds the base route prefix for each of the methods if specified
			$basepath = null;
			// holds the minimium required level for this page
			$c_requireslevel = null;
			
			// if the controller inherits from Wave_IResource then more default routes are added to the routing table
			$is_resource = in_array('Wave_IResource', $controller['class']['implements']);
			$resource_name = strtolower(substr($cname, 0, strpos($cname, 'Controller')));
			
			// Loop the controllers annotations first setting any default values
			foreach($controller['class']['annotations'] as $cannotation){
				if($cannotation instanceOf Wave_Annotation_BaseRoute){
					$basepath = $cannotation->parameters[0];
					if($basepath[0] == '/') $basepath = substr($basepath, 1);
				}
				else if($cannotation instanceof Wave_Annotation_BaseURL){
					$c_baseurl = $cannotation->parameters[0];
				}
				else if($cannotation instanceof Wave_Annotation_RespondsWith){
					$c_respondswith = $cannotation;
				}
				else if($cannotation instanceof Wave_Annotation_RequiresLevel){
					$c_requireslevel = $cannotation;
				}
			}

			if($basepath === null && $is_resource) {
				$basepath = $resource_name;
			}
			if($c_baseurl === null)
				$c_baseurl = $config_baseurl;


			// if the controller did not specify any response methods, or it specified it would inherit from the configuration
			// then merge the response methods with the config
			if($c_respondswith == null || $c_respondswith->inherit){
				if($c_respondswith == null)
					$c_respondswith = new Wave_Annotation_RespondsWith();
					
				$c_respondswith->addMethods(Wave_Config::get('wave')->router->base->methods);
			}
					
			foreach($controller['methods'] as $method){
				
				// only want to operate on methods that are defined on the actual class (not inherited ones)
				if($method['declaring_class'] !== $cname)
					continue;
					
				$baseurl = $c_baseurl;		
				$routes = array();
				$auth = null;
				$respondswith = $c_respondswith;
				$requires_level = $c_requireslevel;
				// method annotations first
				foreach($method['annotations'] as $annotation){
					
					if($annotation instanceof Wave_Annotation_Route){
						foreach($annotation->methods as $req_method){
							$routes[] = array('method' => $req_method, 'url' => $annotation->url);
						}
					}
					else if ($annotation instanceof Wave_Annotation_Auth){
						$auth = $annotation->parameters[0];
					}
					else if($annotation instanceof Wave_Annotation_BaseURL){
						$baseurl = $annotation->parameters[0];
						if($baseurl == Wave_Annotation_BaseURL::DEFAULT_KEYWORD){
							$baseurl = $config_baseurl;
						}
					}
					else if($annotation instanceof Wave_Annotation_RespondsWith){
						if($annotation->inherit)
							$respondswith->addMethods($annotation->methods);
						
						else 
							$respondswith = $annotation;
					}
					else if($annotation instanceof Wave_Annotation_RequiresLevel){
						if(isset($annotation->inherit) && $annotation->inherit)
							$requires_level->addMethods($annotation->methods);
						else 
							$requires_level = $annotation;
					}
				}
				// no annotations, check default resource routes
				if(empty($routes) && $is_resource){
					if($method['name'] == Wave_IResource::RESOURCE_SHOW){
						$routes[] = array('method' => Wave_Method::GET, 'url' => '');	# resource/
					}
					elseif($method['name'] == Wave_IResource::RESOURCE_CREATE){
						$routes[] = array('method' => Wave_Method::POST, 'url' => '');	# resource/	
						$routes[] = array('method' => Wave_Method::CREATE, 'url' => '');	# resource/	
					}
					elseif($method['name'] == Wave_IResource::RESOURCE_GET){
						$routes[] = array('method' => Wave_Method::GET, 'url' => '<string>'.$resource_name.'_id');	# resource/100
					}
					elseif($method['name'] == Wave_IResource::RESOURCE_UPDATE){
						$routes[] = array('method' => Wave_Method::POST, 'url' => '<string>'.$resource_name.'_id');	# resource/100
						$routes[] = array('method' => Wave_Method::UPDATE, 'url' => '<string>'.$resource_name.'_id');	# resource/100
					}
					elseif($method['name'] == Wave_IResource::RESOURCE_DELETE){
						$routes[] = array('method' => Wave_Method::POST, 'url' => '<string>'.$resource_name.'_id/delete');	# resource/100/delete
						$routes[] = array('method' => Wave_Method::DELETE, 'url' => '<string>'.$resource_name.'_id');	# resource/100
					}
				}
				// no resource routes either, if public turn into verb
				if(empty($routes) && $is_resource && $method['visibility'] == Wave_Reflector::VISIBILITY_PUBLIC){
					$verb = strtolower($method['name']);
					$routes[] = array('method' => Wave_Method::ANY, 'url' => '<string>'.$resource_name.'_id/'.$verb);	# resource/100/delete
				}
				
				$m = array('action' => $cname.'.'.$method['name'],
						   'auth' => $auth,
						   'baseurl' => $baseurl,
						   'respondswith' => $respondswith->methods,
						   'requireslevel' => $requires_level == null ? null : $requires_level->methods);
				
				foreach($routes as $route){
					// relative URL, prepend base to it
					if((!isset($route['url'][0]) || $route['url'][0] !== '/') && $basepath !== null){
						$route['url'] = '/'.$basepath . '/' . $route['url'];
					}
					if(substr($route['url'], -1, 1) == '/'){
						$route['url'] = substr($route['url'], 0, -1);
					}
					if($route['method'] == Wave_Method::ANY)
						$http_methods = Wave_Method::$ALL;
					else
						$http_methods = array($route['method']);
						
					foreach($http_methods as $http_method){
						if(!isset($output_routes[$baseurl])) $output_routes[$baseurl] = array();
						
						$output_routes[$baseurl][] = array('url' => $http_method . $route['url'], 'action' => $m);
						//$routes_trie->addChild($http_method . $route['url'], $m);
					}
				}
			}
		}	
		return($output_routes);
	}


}


?>