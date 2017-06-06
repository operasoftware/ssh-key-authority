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
* Class that represents a grouping of users or server accounts
*/
class Group extends Entity {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'group';
	/**
	* Defines the field that is the primary key of the table
	*/
	protected $idfield = 'entity_id';

	public function __construct($id = null, $preload_data = array()) {
		parent::__construct($id, $preload_data);
		if(!isset($this->data['system'])) $this->data['system'] = 0;
	}

	/**
	* Write property changes to database and log the changes.
	* Triggers a resync if the group was activated/deactivated.
	*/
	public function update() {
		if($this->data['system']) $this->data['active'] = 1; // Cannot disable system groups
		$changes = parent::update();
		$resync = false;
		foreach($changes as $change) {
			$loglevel = LOG_INFO;
			switch($change->field) {
			case 'active':
				$resync = true;
				if($change->new_value == 1) $loglevel = LOG_WARNING;
				break;
			}
			$this->log(array('action' => 'Setting update', 'value' => $change->new_value, 'oldvalue' => $change->old_value, 'field' => ucfirst(str_replace('_', ' ', $change->field))), $loglevel);
		}
		if($resync) {
			$this->sync_access();
			$this->sync_remote_access();
		}
	}

	/**
	* List all log events for this group.
	* @return array of GroupEvent objects
	*/
	public function get_log() {
		if(is_null($this->id)) throw new BadMethodCallException('Group must be in directory before log entries can be listed');
		$stmt = $this->database->prepare("SELECT * FROM entity_event WHERE entity_id = ? ORDER BY id DESC");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$log = array();
		while($row = $result->fetch_assoc()) {
			$log[] = new GroupEvent($row['id'], $row);
		}
		$stmt->close();
		return $log;
	}

	/**
	* Add the specified user as an administrator of the group.
	* This action is logged with a warning level as it is increasing an access level.
	* @param User $user to add as administrator
	*/
	public function add_admin(User $user) {
		global $config;
		parent::add_admin($user);
		$url = $config['web']['baseurl'].'/groups/'.urlencode($this->name);
		$email = new Email;
		$email->subject = "Administrator for {$this->name} group";
		$email->add_cc($config['email']['report_address'], $config['email']['report_name']);
		$email->add_recipient($user->email, $user->name);
		$email->body = "{$this->active_user->name} ({$this->active_user->uid}) has added you as an administrator for the '{$this->name}' group.  You can administer this group from <$url>";
		$email->send();
		$this->log(array('action' => 'Administrator add', 'value' => "user:{$user->uid}"), LOG_WARNING);
	}

	/**
	* Remove the specified user as an administrator of the group.
	* This action is logged with a warning level as it means the removed user will no longer
	* receive notifications for any changes done to this group.
	* @param User $user to remove as administrator
	*/
	public function delete_admin(User $user) {
		parent::delete_admin($user);
		$this->log(array('action' => 'Administrator remove', 'value' => "user:{$user->uid}"), LOG_WARNING);
	}


	/**
	* Add the specified entity (User/ServerAccount/Group†) as a member of the group.
	* †Adding a Group as a member of a group (nested groups) is no longer allowed by the UI.
	* This action is logged with a warning level as it is potentially granting access.
	* @todo remove nested group functionality
	* @param Entity $entity to add as a group member
	*/
	public function add_member(Entity $entity) {
		global $config;
		if(is_null($this->entity_id)) throw new BadMethodCallException('Group must be in directory before members can be added');
		if(is_null($entity->entity_id)) throw new InvalidArgumentException('Entity must be in directory before it can be added to a group');
		$entity_id = $entity->entity_id;
		switch(get_class($entity)) {
		case 'User':
			$name = "user {$entity->uid}";
			$mailsubject = "{$entity->uid} added to {$this->name} group by {$this->active_user->uid}";
			$mailbody = "{$entity->name} ({$entity->uid}) has been added to the {$this->name} group by {$this->active_user->name} ({$this->active_user->uid}).";
			$logmsg = array('action' => 'Member add', 'value' => "user:{$entity->uid}");
			break;
		case 'ServerAccount':
			// We should not allow adding server accounts to a group if the active user is not an admin of that server or server account
			if(!$this->active_user->admin && !$this->active_user->admin_of($entity->server) && !$this->active_user->admin_of($entity)) {
				throw new InvalidArgumentException('Active user is not an administrator of the specified server account');
			}
			$name = "account {$entity->name}@{$entity->server->hostname}";
			$mailsubject = "{$entity->name}@{$entity->server->hostname} added to {$this->name} group by {$this->active_user->uid}";
			$mailbody = "{$entity->name}@{$entity->server->hostname} has been added to the {$this->name} group by {$this->active_user->name} ({$this->active_user->uid}).";
			$logmsg = array('action' => 'Member add', 'value' => "account:{$entity->name}@{$entity->server->hostname}");
			break;
		case 'Group':
			// We should not allow adding groups to a group if the active user is not an admin of that group
			if(!$this->active_user->admin && !$this->active_user->admin_of($entity)) {
				throw new InvalidArgumentException('Active user is not an administrator of the specified group');
			}
			$name = "group {$entity->name}";
			$mailsubject = "{$entity->name} group added to {$this->name} group by {$this->active_user->uid}";
			$mailbody = "The {$entity->name} group has been added to the {$this->name} group by {$this->active_user->name} ({$this->active_user->uid}).";
			$logmsg = array('action' => 'Member add', 'value' => "group:{$entity->name}");
			break;
		}
		try {
			$stmt = $this->database->prepare("INSERT INTO group_member SET `group` = ?, entity_id = ?, add_date = UTC_TIMESTAMP(), added_by = ?");
			$stmt->bind_param('ddd', $this->entity_id, $entity_id, $this->active_user->entity_id);
			$stmt->execute();
			$stmt->close();
			$this->log($logmsg, LOG_WARNING);
			if($this->active_user->uid != 'import-script') {
				$email = new Email;
				foreach($this->list_admins() as $admin) {
					$email->add_recipient($admin->email, $admin->name);
				}
				$email->add_cc($config['email']['report_address'], $config['email']['report_name']);
				$email->subject = $mailsubject;
				$email->body = $mailbody;
				$email->send();
			}
		} catch(mysqli_sql_exception $e) {
			if($e->getCode() == 1062) {
				// Duplicate entry - ignore
			} else {
				throw $e;
			}
		}
		$entity->sync_access(); // This entity is now a member of the group, so any access rules that apply to the group now apply to the entity
		$this->sync_remote_access(); // If this group has access to anything, this entity now also has access to it
	}

	/**
	* Remove the specified entity (User/ServerAccount/Group) as a member of the group.
	* @todo remove nested group functionality
	* @param Entity $entity to remove as a group member
	*/
	public function delete_member(Entity $entity) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Group must be in directory before members can be deleted');
		switch(get_class($entity)) {
		case 'User':
			$this->log(array('action' => 'Member remove', 'value' => "user:{$entity->uid}"));
			break;
		case 'ServerAccount':
			$this->log(array('action' => 'Member remove', 'value' => "account:{$entity->name}@{$entity->server->hostname}"));
			break;
		case 'Group':
			$this->log(array('action' => 'Member remove', 'value' => "group:{$entity->name}"));
			break;
		}
		$stmt = $this->database->prepare("DELETE FROM group_member WHERE `group` = ? AND entity_id = ?");
		$stmt->bind_param('ds', $this->entity_id, $entity->entity_id);
		$stmt->execute();
		$stmt->close();
		// Resync both the entity being removed and the group itself
		$entity->sync_access();
		$this->sync_remote_access();
	}

	/**
	* List all members of the group.
	* @todo remove nested group functionality
	* @return array of User/ServerAccount/Group objects
	*/
	public function list_members() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Group must be in directory before members can be listed');
		$stmt = $this->database->prepare("
			SELECT entity.id, entity.type, add_date, added_by
			FROM group_member
			INNER JOIN entity ON group_member.entity_id = entity.id
			LEFT JOIN user ON user.entity_id = entity.id
			LEFT JOIN server_account ON server_account.entity_id = entity.id
			LEFT JOIN server ON server.id = server_account.server_id
			LEFT JOIN `group` ON `group`.entity_id = entity.id
			WHERE group_member.group = ?
			ORDER BY entity.type, user.uid, server.hostname, server_account.name, `group`.name
		");
		$stmt->bind_param('d', $this->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$members = array();
		while($row = $result->fetch_assoc()) {
			$row['added_by'] = new User($row['added_by']);
			switch($row['type']) {
			case 'user': $members[] = new User($row['id'], $row); break;
			case 'server account': $members[] = new ServerAccount($row['id'], $row); break;
			case 'group': $members[] = new Group($row['id'], $row); break;
			}
		}
		$stmt->close();
		return $members;
	}

	/**
	* Grant the specified entity (User/ServerAccount/Group) access to members of this group.
	* An email is sent to the group admins and sec-ops to inform them of the change.
	* This action is logged with a warning level as it is granting access.
	* @param Entity $entity to add as a group member
	* @param array $access_options array of AccessOption rules to apply to the granted access
	*/
	public function add_access(Entity $entity, array $access_options) {
		global $config;
		if(is_null($this->entity_id)) throw new BadMethodCallException('Group must be in directory before access can be added');
		if(is_null($entity->entity_id)) throw new InvalidArgumentException('Entity must be in directory before it can be granted access to a group');
		$access = new Access;
		$access->dest_entity_id = $this->entity_id;
		$access->source_entity_id = $entity->entity_id;
		$access->granted_by = $this->active_user->entity_id;
		try {
			$stmt = $this->database->prepare("INSERT INTO access SET dest_entity_id = ?, source_entity_id = ?, grant_date = UTC_TIMESTAMP(), granted_by = ?");
			$stmt->bind_param('ddd', $access->dest_entity_id, $access->source_entity_id, $access->granted_by);
			$stmt->execute();
			$access->id = $stmt->insert_id;
			$stmt->close();
			switch(get_class($entity)) {
			case 'User':
				$this->log(array('action' => 'Access add', 'value' => "user:{$entity->uid}"), LOG_WARNING);
				$mailsubject = "{$entity->uid} granted access to {$this->name} group resources by {$this->active_user->uid}";
				$mailbody = "{$entity->name} ({$entity->uid}) has been granted access to resources in the {$this->name} group by {$this->active_user->name} ({$this->active_user->uid}).";
				break;
			case 'ServerAccount':
				$this->log(array('action' => 'Access add', 'value' => "account:{$entity->name}@{$entity->server->hostname}"), LOG_WARNING);
				$mailsubject = "{$entity->name}@{$entity->server->hostname} granted access to {$this->name} group resources by {$this->active_user->uid}";
				$mailbody = "{$entity->name}@{$entity->server->hostname} has been granted access to resources in the {$this->name} group by {$this->active_user->name} ({$this->active_user->uid}).";
				break;
			case 'Group':
				$this->log(array('action' => 'Access add', 'value' => "group:{$entity->name}"), LOG_WARNING);
				$mailsubject = "{$entity->name} group granted access to {$this->name} group resources by {$this->active_user->uid}";
				$mailbody = "The {$entity->name} group has been granted access to resources in the {$this->name} group by {$this->active_user->name} ({$this->active_user->uid}).";
				break;
			}
			if($this->active_user->uid != 'import-script') {
				$email = new Email;
				foreach($this->list_admins() as $admin) {
					$email->add_recipient($admin->email, $admin->name);
				}
				$email->add_cc($config['email']['report_address'], $config['email']['report_name']);
				$email->subject = $mailsubject;
				$email->body = $mailbody;
				$email->send();
			}
			foreach($access_options as $access_option) {
				$access->add_option($access_option);
			}
		} catch(mysqli_sql_exception $e) {
			if($e->getCode() == 1062) {
				// Duplicate entry - ignore
			} else {
				throw $e;
			}
		}
		$this->sync_access();
	}

	/**
	* Revoke the specified access rule to members of this group.
	* @param Access $access rule to be removed
	*/
	public function delete_access(Access $access) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Group must be in directory before access can be deleted');
		$entity = $access->source_entity;
		switch(get_class($entity)) {
		case 'User':
			$this->log(array('action' => 'Access remove', 'value' => "user:{$entity->uid}"));
			break;
		case 'ServerAccount':
			$this->log(array('action' => 'Access remove', 'value' => "account:{$entity->name}@{$entity->server->hostname}"));
			break;
		case 'Group':
			$this->log(array('action' => 'Access remove', 'value' => "group:{$entity->name}"));
			break;
		}
		$stmt = $this->database->prepare("DELETE FROM access WHERE dest_entity_id = ? AND id = ?");
		$stmt->bind_param('ds', $this->entity_id, $access->id);
		$stmt->execute();
		$stmt->close();
		$this->sync_access();
	}

	/**
	* List all groups that *this* group is a member of, searched recursively.
	* Note: nested groups are no longer allowed by the UI.
	* @todo remove nested group functionality
	* @return array of Group objects
	*/
	public function list_group_membership() {
		global $group_dir;
		return $group_dir->list_group_membership($this);
	}

	/**
	* Trigger a resync for all members of this group, searched recursively†.
	* †Nested groups are no longer allowed by the UI.
	* @todo remove nested group functionality
	* @param array $seen keep track of entities we've already processed to prevent infinite recursion
	*/
	public function sync_access(&$seen = array()) {
		$seen[$this->entity_id] = true;
		$members = $this->list_members();
		foreach($members as $entity) {
			if(!isset($seen[$entity->entity_id])) {
				$entity->sync_access($seen);
			}
		}
	}
}
