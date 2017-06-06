<?php
##
## Copyright 2013-2017 Opera Software AS
##
## Licensed under the Apache License, Version 2.0 (the "License");
## you may not use this file except in compliance with the License.
## You may obtain a copy of the License at
##
## http://www.apache.org/licenses/LICENSE-2.0
##
## Unless required by applicable law or agreed to in writing, software
## distributed under the License is distributed on an "AS IS" BASIS,
## WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
## See the License for the specific language governing permissions and
## limitations under the License.
##

class Router {
	private $routes = array();
	private $route_vars;
	public $view = null;
	public $public = null;
	public $vars = array();

	public function add_route($path, $view, $public) {
		$this->route_vars = array();
		$path = preg_replace_callback('|\\\{([a-z]+)\\\}|', array($this, 'parse_route_variable'), preg_quote($path, '|'));
		$route = new StdClass;
		$route->view = $view;
		$route->vars = $this->route_vars;
		$route->public = $public;
		$this->routes[$path] = $route;
	}

	private function parse_route_variable($matches) {
		$this->route_vars[] = $matches[1];
		return '([^/]*)';
	}

	public function handle_request($request_path) {
		$request_path = preg_replace('|\?.*$|', '', $request_path);
		foreach($this->routes as $path => $route) {
			if(preg_match('|^'.$path.'$|', $request_path, $matches)) {
				$this->view = $route->view;
				$this->public = $route->public;
				$i = 0;
				foreach($route->vars as $var) {
					$i++;
					if(isset($matches[$i])) {
						$this->vars[$var] = urldecode($matches[$i]);
					}
				}
			}
		}
		if(is_null($this->view)) {
			$this->view = 'error404';
		}
	}
}
