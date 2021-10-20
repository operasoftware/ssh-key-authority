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
* Class for reading/writing to the list of Group objects in the database.
*/
class GroupDirectory extends DBDirectory {
	/**
	* Create the new group in the database.
	* @param Group $group object to add
	* @throws GroupAlreadyExistsException if a group with that name already exists
	*/
	public function add_group(Group $group) {
		$name = $group->name;
		$system = $group->system;
		$this->database->begin_transaction();
		$stmt = $this->database->prepare("INSERT INTO entity SET type = 'group'");
		$stmt->execute();
		$group->entity_id = $stmt->insert_id;
		$stmt->close();
		$stmt = $this->database->prepare("INSERT INTO `group` SET entity_id = ?, name = ?, `system` = ?");
		$stmt->bind_param('dsd', $group->entity_id, $name, $system);
		try {
			$stmt->execute();
			$stmt->close();
			$this->database->commit();
			$group->log(array('action' => 'Group add'));
		} catch(mysqli_sql_exception $e) {
			$this->database->rollback();
			if($e->getCode() == 1062) {
				// Duplicate entry
				throw new GroupAlreadyExistsException("Group {$group->name} already exists");
			} else {
				throw $e;
			}
		}
	}

	/**
	* Get a group from the database by its entity ID.
	* @param int $entity_id of group
	* @return Group with specified entity ID
	* @throws GroupNotFoundException if no group with that entity ID exists
	*/
	public function get_group_by_id($entity_id) {
		$stmt = $this->database->prepare("SELECT * FROM `group` WHERE entity_id = ?");
		$stmt->bind_param('d', $entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$group = new Group($row['entity_id'], $row);
		} else {
			throw new GroupNotFoundException('Group does not exist.');
		}
		$stmt->close();
		return $group;
	}

	/**
	* Get a group from the database by its name.
	* @param string $name of group
	* @return Group with specified name
	* @throws GroupNotFoundException if no group with that name exists
	*/
	public function get_group_by_name($name) {
		$stmt = $this->database->prepare("SELECT * FROM `group` WHERE name = ?");
		$stmt->bind_param('s', $name);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$group = new Group($row['entity_id'], $row);
		} else {
			throw new GroupNotFoundException('Group does not exist');
		}
		$stmt->close();
		return $group;
	}

	/**
	* List all groups in the database.
	* @param array $include list of extra data to include in response
	* @param array $filter list of field/value pairs to filter results on
	* @return array of Group objects
	*/
	public function list_groups($include = array(), $filter = array()) {
		// WARNING: The search query is not parameterized - be sure to properly escape all input
		$fields = array("`group`.*");
		$joins = array();
		$where = array();
		foreach($filter as $field => $value) {
			if($value) {
				switch($field) {
				case 'name':
					$where[] = "`group`.name REGEXP '".$this->database->escape_string($value)."'";
					break;
				case 'active':
					$where[] = "`group`.active IN (".implode(", ", array_map('intval', $value)).")";
					break;
				case 'admin':
					$where[] = "admin_filter.admin = ".intval($value);
					$joins['adminfilter'] = "INNER JOIN entity_admin admin_filter ON admin_filter.entity_id = `group`.entity_id";
					break;
				case 'member':
					$where[] = "member_filter.entity_id = ".intval($value);
					$joins['memberfilter'] = "INNER JOIN group_member member_filter ON member_filter.group = `group`.entity_id";
					break;
				}
			}
		}
		foreach($include as $inc) {
			switch($inc) {
			case 'admins':
				$fields[] = "GROUP_CONCAT(DISTINCT user.uid SEPARATOR ', ') AS admins";
				$joins['admins'] = "LEFT JOIN entity_admin ON entity_admin.entity_id = `group`.entity_id";
				$joins['adminusers'] = "LEFT JOIN user ON user.entity_id = entity_admin.admin AND user.active";
				break;
			case 'members':
				$fields[] = "COUNT(DISTINCT group_member.entity_id) AS member_count";
				$joins['members'] = "LEFT JOIN group_member ON group_member.group = `group`.entity_id";
				break;
			}
		}
		try {
			$stmt = $this->database->prepare("
				SELECT ".implode(", ", $fields)."
				FROM `group` ".implode(" ", $joins)."
				".(count($where) == 0 ? "" : "WHERE (".implode(") AND (", $where).")")."
				GROUP BY group.entity_id
				ORDER BY `group`.name
			");
		} catch(mysqli_sql_exception $e) {
			if($e->getCode() == 1139) {
				throw new GroupSearchInvalidRegexpException;
			} else {
				throw $e;
			}
		}
		$stmt->execute();
		$result = $stmt->get_result();
		$groups = array();
		while($row = $result->fetch_assoc()) {
			$groups[] = new Group($row['entity_id'], $row);
		}
		$stmt->close();
		return $groups;
	}

	/**
	* List all groups that the given entity (User/ServerAccount/Group†) is a member of (searched recursively†).
	* †Nested groups are no longer allowed by the UI.
	* @todo remove nested group functionality
	* @param Entity $entity to find in group memberships
	* @param array $via keep track of groups we have already searched through to prevent infinite recursion†
	* @param array $groups to allow the function to add to the list of groups when recursing†
	* @return array of Group objects
	*/
	public function list_group_membership(Entity $entity, $via = array(), &$groups = array()) {
		$stmt = $this->database->prepare("
			SELECT `group`.*, add_date, added_by
			FROM group_member
			INNER JOIN `group` ON `group`.entity_id = group_member.group
			WHERE group_member.entity_id = ?
			ORDER BY `group`.name
		");
		$stmt->bind_param('d', $entity->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		while($row = $result->fetch_assoc()) {
			$row['added_by'] = new User($row['added_by']);
			$group = new Group($row['entity_id'], $row);
			$groups[] = $group;
			$skip = false;
			foreach($via as $check) {
				if($group->id == $check->id) $skip = true;
			}
			if(!$skip) {
				$thisvia = $via;
				$thisvia[] = $group;
				$this->list_group_membership($group, $thisvia, $groups);
			}
		}
		$stmt->close();
		return $groups;
	}
}

class GroupNotFoundException extends Exception {}
class GroupAlreadyExistsException extends Exception {}
class GroupNotDeletableException extends Exception {}
class GroupSearchInvalidRegexpException extends Exception {}
