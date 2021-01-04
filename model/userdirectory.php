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
* Class for reading/writing to the list of User objects in the database.
*/
class UserDirectory extends DBDirectory {
	/**
	* LDAP connection object
	*/
	private $ldap;
	/**
	* Avoid making multiple LDAP lookups on the same person by caching their details here
	*/
	private $cache_uid;

	public function __construct() {
		parent::__construct();
		global $ldap;
		$this->ldap = $ldap;
		$this->cache_uid = array();
	}

	/**
	* Create the new user in the database.
	* @param User $user object to add
	*/
	public function add_user(User $user) {
		$user_id = $user->uid;
		$user_name = $user->name;
		$user_active = $user->active;
		$user_admin = $user->admin;
		$user_email = $user->email;
		$stmt = $this->database->prepare("INSERT INTO entity SET type = 'user'");
		$stmt->execute();
		$user->entity_id = $stmt->insert_id;
		$stmt = $this->database->prepare("INSERT INTO user SET entity_id = ?, uid = ?, name = ?, email = ?, active = ?, admin = ?");
		$stmt->bind_param('dsssdd', $user->entity_id, $user_id, $user_name, $user_email, $user_active, $user_admin);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* Get a user from the database by its entity ID.
	* @param int $entity_id of user
	* @return User with specified entity ID
	* @throws UserNotFoundException if no user with that entity ID exists
	*/
	public function get_user_by_id($id) {
		$stmt = $this->database->prepare("SELECT * FROM user WHERE entity_id = ?");
		$stmt->bind_param('d', $id);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$user = new User($row['entity_id'], $row);
		} else {
			throw new UserNotFoundException('User does not exist.');
		}
		$stmt->close();
		return $user;
	}

	/**
	* Get a user from the database by its uid. If it does not exist in the database, retrieve it
	* from LDAP and store in the database.
	* @param string $uid of user
	* @return User with specified entity uid
	* @throws UserNotFoundException if no user with that uid exists
	*/
	public function get_user_by_uid($uid) {
		if(isset($this->cache_uid[$uid])) {
			return $this->cache_uid[$uid];
		}
		$stmt = $this->database->prepare("SELECT * FROM user WHERE uid = ?");
		$stmt->bind_param('s', $uid);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$user = new User($row['entity_id'], $row);
			$this->cache_uid[$uid] = $user;
		} else {
			$user = new User;
			$user->uid = $uid;
			$this->cache_uid[$uid] = $user;
			$user->get_details_from_ldap();
			$this->add_user($user);
			$user->update_group_memberships();
		}
		$stmt->close();
		return $user;
	}

	/**
	* List all users in the database.
	* @param array $include list of extra data to include in response - currently unused
	* @param array $filter list of field/value pairs to filter results on
	* @return array of User objects
	*/
	public function list_users($include = array(), $filter = array()) {
		// WARNING: The search query is not parameterized - be sure to properly escape all input
		$fields = array("user.*");
		$joins = array();
		$where = array();
		foreach($filter as $field => $value) {
			if($value) {
				switch($field) {
				case 'uid':
					$where[] = "uid = '".$this->database->escape_string($value)."'";
					break;
				case 'name':
					$where[] = "name = '".$this->database->escape_string($value)."'";
					break;
				case 'admins_servers':
					$joins[] = "INNER JOIN server_admin ON server_admin.entity_id = user.entity_id";
					$joins[] = "INNER JOIN server ON server.id = server_admin.server_id AND server.key_management <> 'decommissioned'";
					break;
				}
			}
		}
		$stmt = $this->database->prepare("
			SELECT ".implode(", ", $fields)."
			FROM user ".implode(" ", $joins)."
			".(count($where) == 0 ? "" : "WHERE (".implode(") AND (", $where).")")."
			GROUP BY user.entity_id
			ORDER BY user.uid
		");
		$stmt->execute();
		$result = $stmt->get_result();
		$users = array();
		while($row = $result->fetch_assoc()) {
			$users[] = new User($row['entity_id'], $row);
		}
		return $users;
	}
}

class UserNotFoundException extends Exception {}
