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

/**
* Class that represents a note associated with a server
*/
class ServerNote extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'server_note';

	public function __construct($id = null, $preload_data = array()) {
		parent::__construct($id, $preload_data);
		global $active_user;
		if(is_null($id)) $this->entity_id = $active_user->entity_id;
	}

	/**
	* Magic getter method - if server field requested, return Server object that note applies to;
	* if user field requested, return User object of the person who wrote the note.
	* @param string $field to retrieve
	* @return mixed data stored in field
	*/
	public function &__get($field) {
		global $user_dir;
		switch($field) {
		case 'user':
			$user = new User($this->entity_id);
			return $user;
		case 'server':
			$server = new Server($this->server_id);
			return $server;
		default:
			return parent::__get($field);
		}
	}
}
