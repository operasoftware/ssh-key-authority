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
* Class that represents a server
*/
class Server extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'server';

	/**
	* Write event details to syslog and to server_event table.
	* @param array $details event paramaters to be logged
	* @param int $level syslog priority as defined in http://php.net/manual/en/function.syslog.php
	*/
	public function log($details, $level = LOG_INFO) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before log entries can be added');
		$json = json_encode($details, JSON_UNESCAPED_UNICODE);
		$stmt = $this->database->prepare("INSERT INTO server_event SET server_id = ?, actor_id = ?, date = UTC_TIMESTAMP(), details = ?");
		$stmt->bind_param('dds', $this->id, $this->active_user->entity_id, $json);
		$stmt->execute();
		$stmt->close();

		$text = "KeysScope=\"server:{$this->hostname}\" KeysRequester=\"{$this->active_user->uid}\"";
		foreach($details as $key => $value) {
			$text .= ' Keys'.ucfirst($key).'="'.str_replace('"', '', $value).'"';
		}
		openlog('keys', LOG_ODELAY, LOG_AUTH);
		syslog($level, $text);
		closelog();
	}

	/**
	* Write property changes to database and log the changes.
	* Triggers a resync if certain settings are changed.
	*/
	public function update() {
		$changes = parent::update();
		$resync = false;
		foreach($changes as $change) {
			switch($change->field) {
			case 'hostname':
			case 'key_management':
			case 'authorization':
			case 'custom_keys':
				$resync = true;
				break;
			case 'rsa_key_fingerprint':
				if(empty($change->new_value)) $resync = true;
				break;
			}
			$this->log(array('action' => 'Setting update', 'value' => $change->new_value, 'oldvalue' => $change->old_value, 'field' => ucfirst(str_replace('_', ' ', $change->field))));
		}
		if($resync) {
			$this->sync_access();
		}
	}

	/**
	* List all log events for this server.
	* @return array of ServerEvent objects
	*/
	public function get_log() {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before log entries can be listed');
		$stmt = $this->database->prepare("
			SELECT *
			FROM server_event
			WHERE server_id = ?
			ORDER BY id DESC
		");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$log = array();
		while($row = $result->fetch_assoc()) {
			$log[] = new ServerEvent($row['id'], $row);
		}
		$stmt->close();
		return $log;
	}

	/**
	* List all log events for this server and any accounts on the server.
	* @return array of ServerEvent/ServerAccountEvent objects
	*/
	public function get_log_including_accounts() {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before log entries can be listed');
		$stmt = $this->database->prepare("
			(SELECT se.id, se.actor_id, se.date, se.details, se.server_id, NULL as entity_id, 'server' as type
			FROM server_event se
			WHERE se.server_id = ?
			ORDER BY id DESC)
			UNION
			(SELECT ee.id, ee.actor_id, ee.date, ee.details, NULL as server_id, ee.entity_id, 'server account' as type
			FROM server_account sa
			INNER JOIN entity_event ee ON ee.entity_id = sa.entity_id
			WHERE sa.server_id = ?
			ORDER BY id DESC)
			ORDER BY date DESC, id DESC
		");
		$stmt->bind_param('dd', $this->id, $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$log = array();
		while($row = $result->fetch_assoc()) {
			if($row['type'] == 'server') {
				$log[] = new ServerEvent($row['id'], $row);
			} elseif($row['type'] == 'server account') {
				$log[] = new ServerAccountEvent($row['id'], $row);
			}
		}
		$stmt->close();
		return $log;
	}

	/**
	* Get the more recent log event that recorded a change in sync status.
	* @todo In a future change we may want to move the 'action' parameter into its own database field.
	* @return ServerEvent last sync status change event
	*/
	public function get_last_sync_event() {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before log entries can be listed');
		$stmt = $this->database->prepare("SELECT * FROM server_event WHERE server_id = ? AND details LIKE '{\"action\":\"Sync status change\"%' ORDER BY id DESC LIMIT 1");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$event = new ServerEvent($row['id'], $row);
		} else {
			$event = null;
		}
		$stmt->close();
		return $event;
	}

	/**
	* Add the specified user or group as an administrator of the server.
	* This action is logged with a warning level as it is increasing an access level.
	* @param Entity $entity user or group to add as administrator
	*/
	public function add_admin(Entity $entity) {
		global $config;
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before admins can be added');
		if(is_null($entity->entity_id)) throw new InvalidArgumentException('User or group must be in directory before it can be made admin');
		$entity_id = $entity->entity_id;
		try {
			$url = $config['web']['baseurl'].'/servers/'.urlencode($this->hostname);
			$email = new Email;
			$email->subject = "Administrator for {$this->hostname}";
			$email->add_cc($config['email']['report_address'], $config['email']['report_name']);
			switch(get_class($entity)) {
			case 'User':
				$email->add_recipient($entity->email, $entity->name);
				$email->body = "{$this->active_user->name} ({$this->active_user->uid}) has added you as a server administrator for {$this->hostname}.  You can administer access to this server from <$url>";
				$logmsg = array('action' => 'Administrator add', 'value' => "user:{$entity->uid}");
				break;
			case 'Group':
				foreach($entity->list_members() as $member) {
					if(get_class($member) == 'User') {
						$email->add_recipient($member->email, $member->name);
					}
				}
				$email->body = "{$this->active_user->name} ({$this->active_user->uid}) has added the {$entity->name} group as server administrator for {$this->hostname}.  You are a member of the {$entity->name} group, so you can administer access to this server from <$url>";
				$logmsg = array('action' => 'Administrator add', 'value' => "group:{$entity->name}");
				break;
			default:
				throw new InvalidArgumentException('Entities of type '.get_class($entity).' cannot be added as server admins');
			}
			$stmt = $this->database->prepare("INSERT INTO server_admin SET server_id = ?, entity_id = ?");
			$stmt->bind_param('dd', $this->id, $entity_id);
			$stmt->execute();
			$stmt->close();
			if($this->active_user->uid != 'import-script') {
				$this->log($logmsg, LOG_WARNING);
				$email->send();
			}
		} catch(mysqli_sql_exception $e) {
			if($e->getCode() == 1062) {
				// Duplicate entry - ignore
			} else {
				throw $e;
			}
		}
	}

	/**
	* Remove the specified user or group as an administrator of the server.
	* This action is logged with a warning level as it means the removed user/group will no longer
	* receive notifications for any changes done to this server.
	* @param Entity $entity user or group to remove as administrator
	*/
	public function delete_admin(Entity $entity) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before admins can be deleted');
		if(is_null($entity->entity_id)) throw new InvalidArgumentException('User or group must be in directory before it can be removed as admin');
		$entity_id = $entity->entity_id;
		switch(get_class($entity)) {
		case 'User':
			$this->log(array('action' => 'Administrator remove', 'value' => "user:{$entity->uid}"), LOG_WARNING);
			break;
		case 'Group':
			$this->log(array('action' => 'Administrator remove', 'value' => "group:{$entity->name}"), LOG_WARNING);
			break;
		default:
			throw new InvalidArgumentException('Entities of type '.get_class($entity).' should not exist as server admins');
		}
		$stmt = $this->database->prepare("DELETE FROM server_admin WHERE server_id = ? AND entity_id = ?");
		$stmt->bind_param('dd', $this->id, $entity_id);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* List all administrators of this server.
	* @return array of User/Group objects
	*/
	public function list_admins() {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before admins can be listed');
		$stmt = $this->database->prepare("SELECT entity_id, type FROM server_admin INNER JOIN entity ON entity.id = server_admin.entity_id WHERE server_id = ?");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$admins = array();
		while($row = $result->fetch_assoc()) {
			if(strtolower($row['type']) == "user") {
				$admins[] = new User($row['entity_id']);
			} elseif(strtolower($row['type']) == "group") {
				$admins[] = new Group($row['entity_id']);
			}
		}
		$stmt->close();
		return $admins;
	}

	/**
	* Return the list of all users who can administrate this server, including
	* via group membership of a group that has been made administrator.
	* @return array of User objects
	*/
	public function list_effective_admins() {
		$admins = $this->list_admins();
		$e_admins = array();
		foreach($admins as $admin) {
			switch(get_class($admin)) {
			case 'Group':
				if($admin->active) {
					$members = $admin->list_members();
					foreach($members as $member) {
						if(get_class($member) == 'User') {
							$e_admins[] = $member;
						}
					}
				}
				break;
			case 'User':
				$e_admins[] = $admin;
				break;
			}
		}
		return $e_admins;
	}

	/**
	* Create any standard accounts that should exist on every server, and add them to the related
	* groups.
	*/
	public function add_standard_accounts() {
		global $group_dir, $config;
		if(!isset($config['defaults']['account_groups'])) return;
		foreach($config['defaults']['account_groups'] as $account_name => $group_name) {
			$account = new ServerAccount;
			$account->name = $account_name;
			$this->add_account($account);
			try {
				$group = $group_dir->get_group_by_name($group_name);
			} catch(GroupNotFoundException $e) {
				$group = new Group;
				$group->name = $group_name;
				$group->system = 1;
				$group_dir->add_group($group);
			}
			$group->add_member($account);
		}
	}

	/**
	* Create a new account on the server.
	* Reactivates an existing account if one exists with the same name.
	* @param ServerAccount $account to be added
	* @throws AccountNameInvalid if account name is empty
	*/
	public function add_account(ServerAccount &$account) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before accounts can be added');
		$account_name = $account->name;
		if($account_name === '') throw new AccountNameInvalid('Account name cannot be empty');
		if(substr($account_name, 0, 1) === '.') throw new AccountNameInvalid('Account name cannot begin with .');
		$sync_status = is_null($account->sync_status) ? 'not synced yet' : $account->sync_status;
		$this->database->begin_transaction();
		$stmt = $this->database->prepare("INSERT INTO entity SET type = 'server account'");
		$stmt->execute();
		$account->entity_id = $stmt->insert_id;
		$stmt->close();
		$stmt = $this->database->prepare("INSERT INTO server_account SET entity_id = ?, server_id = ?, name = ?, sync_status = ?");
		$stmt->bind_param('ddss', $account->entity_id, $this->id, $account_name, $sync_status);
		try {
			$stmt->execute();
			$stmt->close();
			$this->database->commit();
			$this->log(array('action' => 'Account add', 'value' => $account_name));
		} catch(mysqli_sql_exception $e) {
			$this->database->rollback();
			if($e->getCode() == 1062) {
				// Duplicate entry
				$account = $this->get_account_by_name($account_name);
				$account->active = 1;
				$account->update();
			} else {
				throw $e;
			}
		}
	}

	/**
	* Get a server account from the database by its name.
	* @param string $name of account
	* @return ServerAccount with specified name
	* @throws ServerAccountNotFoundException if no account with that name exists
	*/
	public function get_account_by_name($name) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before accounts can be listed');
		$stmt = $this->database->prepare("SELECT entity_id, name FROM server_account WHERE server_id = ? AND name = ?");
		$stmt->bind_param('ds', $this->id, $name);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$account = new ServerAccount($row['entity_id'], $row);
		} else {
			throw new ServerAccountNotFoundException('Account does not exist.');
		}
		$stmt->close();
		return $account;
	}

	/**
	* List accounts stored for this server.
	* @param array $include list of extra data to include in response - currently unused
	* @param array $filter list of field/value pairs to filter results on
	* @return array of ServerAccount objects
	*/
	public function list_accounts($include = array(), $filter = array()) {
		// WARNING: The search query is not parameterized - be sure to properly escape all input
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before accounts can be listed');
		$where = array('server_id = '.intval($this->id), 'active = 1');
		$joins = array("LEFT JOIN access_request ON access_request.dest_entity_id = server_account.entity_id");
		foreach($filter as $field => $value) {
			if($value) {
				switch($field) {
				case 'admin':
					$where[] = "admin_filter.admin = ".intval($value);
					$joins['adminfilter'] = "INNER JOIN entity_admin admin_filter ON admin_filter.entity_id = server_account.entity_id";
					break;
				}
			}
		}
		$stmt = $this->database->prepare("
			SELECT server_account.entity_id, name,
			COUNT(DISTINCT access_request.source_entity_id) AS pending_requests
			FROM server_account
			".implode("\n", $joins)."
			WHERE (".implode(") AND (", $where).")
			GROUP BY server_account.entity_id
			ORDER BY name
		");
		$stmt->execute();
		$result = $stmt->get_result();
		$accounts = array();
		while($row = $result->fetch_assoc()) {
			$accounts[] = new ServerAccount($row['entity_id'], $row);
		}
		$stmt->close();
		return $accounts;
	}

	/**
	* Add an access option that should be applied to all LDAP accounts on the server.
	* Access options include "command", "from", "no-port-forwarding" etc.
	* @param ServerLDAPAccessOption $option to be added
	*/
	public function add_ldap_access_option(ServerLDAPAccessOption $option) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before LDAP access options can be added');
		$stmt = $this->database->prepare("INSERT INTO server_ldap_access_option SET server_id = ?, `option` = ?, value = ?");
		$stmt->bind_param('dss', $this->id, $option->option, $option->value);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* Remove an access option from all LDAP accounts on the server.
	* Access options include "command", "from", "no-port-forwarding" etc.
	* @param ServerLDAPAccessOption $option to be removed
	*/
	public function delete_ldap_access_option(ServerLDAPAccessOption $option) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before LDAP access options can be deleted');
		$stmt = $this->database->prepare("DELETE FROM server_ldap_access_option WHERE server_id = ? AND `option` = ?");
		$stmt->bind_param('ds', $this->id, $option->option);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* Replace the current list of LDAP access options with the provided array of options.
	* This is a crude implementation - just deletes all existing options and adds new ones, with
	* table locking for a small measure of safety.
	* @param array $options array of ServerLDAPAccessOption objects
	*/
	public function update_ldap_access_options(array $options) {
		$stmt = $this->database->query("LOCK TABLES server_ldap_access_option WRITE");
		$oldoptions = $this->list_ldap_access_options();
		foreach($oldoptions as $oldoption) {
			$this->delete_ldap_access_option($oldoption);
		}
		foreach($options as $option) {
			$this->add_ldap_access_option($option);
		}
		$stmt = $this->database->query("UNLOCK TABLES");
		$this->sync_access();
	}

	/**
	* List all current LDAP access options applied to the server.
	* @return array of ServerLDAPAccessOption objects
	*/
	public function list_ldap_access_options() {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before LDAP access options can be listed');
		$stmt = $this->database->prepare("
			SELECT *
			FROM server_ldap_access_option
			WHERE server_id = ?
			ORDER BY `option`
		");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$options = array();
		while($row = $result->fetch_assoc()) {
			$options[$row['option']] = new ServerLDAPAccessOption($row['option'], $row);
		}
		$stmt->close();
		return $options;
	}

	/**
	* Update the sync status for the server and write a log message if the status details have changed.
	* @param string $status "sync success", "sync failure" or "sync warning"
	* @param string $logmsg details of the sync attempt's success or failure
	*/
	public function sync_report($status, $logmsg) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before sync reporting can be done');
		$prevlogmsg = $this->get_last_sync_event();
		if(is_null($prevlogmsg) || $logmsg != json_decode($prevlogmsg->details)->value) {
			$logmsg = array('action' => 'Sync status change', 'value' => $logmsg);
			$this->log($logmsg);
		}
		$this->sync_status = $status;
		$this->update();
	}

	/**
	* Add a note to the server. The note is a piece of text with metadata (who added it and when).
	* @param ServerNote $note to be added
	*/
	public function add_note(ServerNote $note) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before notes can be added');
		$entity_id = $note->user->entity_id;
		$stmt = $this->database->prepare("INSERT INTO server_note SET server_id = ?, entity_id = ?, date = UTC_TIMESTAMP(), note = ?");
		$stmt->bind_param('dds', $this->id, $entity_id, $note->note);
		$stmt->execute();
		$stmt->close();
	}


	/**
	* Delete the specified note from the server.
	* @param ServerNote $note to be deleted
	*/
	public function delete_note(ServerNote $note) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before notes can be deleted');
		$stmt = $this->database->prepare("DELETE FROM server_note WHERE server_id = ? AND id = ?");
		$stmt->bind_param('dd', $this->id, $note->id);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* Retrieve a specific note for this server by its ID.
	* @param int $id of note to retrieve
	* @return ServerNote matching the ID
	* @throws ServerNoteNotFoundException if no note exists with that ID
	*/
	public function get_note_by_id($id) {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before notes can be listed');
		$stmt = $this->database->prepare("SELECT * FROM server_note WHERE server_id = ? AND id = ? ORDER BY id");
		$stmt->bind_param('dd', $this->id, $id);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$note = new ServerNote($row['id'], $row);
		} else {
			throw new ServerNoteNotFoundException('Note does not exist.');
		}
		$stmt->close();
		return $note;
	}

	/**
	* List all notes associated with this server.
	* @return array of ServerNote objects
	*/
	public function list_notes() {
		if(is_null($this->id)) throw new BadMethodCallException('Server must be in directory before notes can be listed');
		$stmt = $this->database->prepare("SELECT * FROM server_note WHERE server_id = ? ORDER BY id");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$notes = array();
		while($row = $result->fetch_assoc()) {
			$notes[] = new ServerNote($row['id'], $row);
		}
		$stmt->close();
		return $notes;
	}

	/**
	* Trigger a sync for all accounts on this server.
	*/
	public function sync_access() {
		global $sync_request_dir;
		$sync_request = new SyncRequest;
		$sync_request->server_id = $this->id;
		$sync_request->account_name = null;
		$sync_request_dir->add_sync_request($sync_request);
	}

	/**
	* List all pending sync requests for this server.
	* @return array of SyncRequest objects
	*/
	public function list_sync_requests() {
		$stmt = $this->database->prepare("SELECT * FROM sync_request WHERE server_id = ? ORDER BY account_name");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$reqs = array();
		while($row = $result->fetch_assoc()) {
			$reqs[] = new SyncRequest($row['id'], $row);
		}
		return $reqs;
	}

	/**
	* Delete all pending sync requests for this server.
	*/
	public function delete_all_sync_requests() {
		$stmt = $this->database->prepare("DELETE FROM sync_request WHERE server_id = ?");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
	}
}

class ServerNoteNotFoundException extends Exception {}
class AccountNameInvalid extends InvalidArgumentException {}