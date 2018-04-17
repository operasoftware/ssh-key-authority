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

class PageSection {
	private $template;
	private $data;

	public function __construct($template) {
		global $relative_request_url;
		global $active_user;
		global $database;
		global $config;
		$this->template = $template;
		$this->data = new StdClass;
		$this->data->menu_items = array();
		$this->data->menu_items['/'] = 'Home';
		$this->data->menu_items['/servers'] = 'Servers';
		$this->data->menu_items['/users'] = 'Users';
		$this->data->menu_items['/groups'] = 'Groups';
		$this->data->menu_items['/pubkeys'] = 'Public keys';
		if($active_user && ($active_user->admin || count($active_user->list_admined_servers()) > 0)) {
			$this->data->menu_items['/activity'] = 'Activity';
		}
		if($active_user && $active_user->admin) {
			$this->data->menu_items['/tools'] = 'Tools';
		}
		$this->data->menu_items['/help'] = 'Help';
		$this->data->relative_request_url = $relative_request_url;
		$this->data->active_user = $active_user;
		$this->data->web_config = $config['web'];
		$this->data->email_config = $config['email'];
		if($active_user && $active_user->developer) {
			$this->data->database = $database;
		}
	}
	public function set_by_array($array, $prefix = '') {
		foreach($array as $item => $data) {
			$this->setData($prefix.$item, $data);
		}
	}
	public function set($item, $data) {
		$this->data->$item = $data;
	}
	public function get($item) {
		if(isset($this->data->$item)) {
			if(is_object($this->data->$item) && get_class($this->data->$item) == 'PageSection') {
				return $this->data->$item->generate();
			} else {
				return $this->data->$item;
			}
		} else {
			return null;
		}
	}
	public function generate() {
		ob_start();
		$data = $this->data;
		include_once(path_join('templates', 'functions.php'));
		include(path_join('templates', $this->template.'.php'));
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
}
