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
* Class that represents an access rule granting access from one entity to another
*/
class Access extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'access';

	/**
	* Add an SSH access option to the access rule
	* Access options include "command", "from", "no-port-forwarding" etc.
	* @param AccessOption $option to be added
	*/
	public function add_option(AccessOption $option) {
		if(is_null($this->id)) throw new BadMethodCallException('Access rule must be in directory before options can be added');
		$stmt = $this->database->prepare("INSERT INTO access_option SET access_id = ?, `option` = ?, value = ?");
		$stmt->bind_param('dss', $this->id, $option->option, $option->value);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* Remove an SSH option from the access rule
	* @param AccessOption $option to be removed
	*/
	public function delete_option(AccessOption $option) {
		if(is_null($this->id)) throw new BadMethodCallException('Access rule must be in directory before options can be deleted');
		$stmt = $this->database->prepare("DELETE FROM access_option WHERE access_id = ? AND `option` = ?");
		$stmt->bind_param('ds', $this->id, $option->option);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* Replace the current list of SSH access options with the provided array of options.
	* This is a crude implementation - just deletes all existing options and adds new ones, with
	* table locking for a small measure of safety.
	* @param array $options array of AccessOption objects
	*/
	public function update_options(array $options) {
		$stmt = $this->database->query("LOCK TABLES access_option WRITE");
		$oldoptions = $this->list_options();
		foreach($oldoptions as $oldoption) {
			$this->delete_option($oldoption);
		}
		foreach($options as $option) {
			$this->add_option($option);
		}
		$stmt = $this->database->query("UNLOCK TABLES");
		$this->dest_entity->sync_access();
	}

	/**
	* List all current SSH access options applied to the access rule.
	* @return array of AccessOption objects
	*/
	public function list_options() {
		if(is_null($this->id)) throw new BadMethodCallException('Access rule must be in directory before options can be listed');
		$stmt = $this->database->prepare("
			SELECT *
			FROM access_option
			WHERE access_id = ?
			ORDER BY `option`
		");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$options = array();
		while($row = $result->fetch_assoc()) {
			$options[$row['option']] = new AccessOption($row['option'], $row);
		}
		$stmt->close();
		return $options;
	}
}

class AccessNotFoundException extends Exception {}
