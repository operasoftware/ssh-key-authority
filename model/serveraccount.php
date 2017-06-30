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
* Class that represents an account on a server
*/
class ServerAccount extends Entity {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'server_account';
	/**
	* Defines the field that is the primary key of the table
	*/
	protected $idfield = 'entity_id';

	/**
	* Magic getter method - if server field requested, return Server object
	* @param string $field to retrieve
	* @return mixed data stored in field
	*/
	public function &__get($field) {
		global $user_dir;
		switch($field) {
		case 'server':
			$server = new Server($this->server_id);
			return $server;
		default:
			return parent::__get($field);
		}
	}

	/**
	* Write property changes to database and log the changes.
	* Triggers a resync of the server if account is activated/deactivated.
	*/
	public function update() {
		global $config;
		// Make it impossible to set default accounts to inactive
		if(is_array($config['defaults']['account_groups'])) {
			if(array_key_exists($this->data['name'], $config['defaults']['account_groups'])) {
				$this->data['active'] = true;
			}
		}
		$changes = parent::update();
		$resync = false;
		foreach($changes as $change) {
			$loglevel = LOG_INFO;
			switch($change->field) {
			case 'active':
				if($this->sync_status != 'proposed') {
					$resync = true;
				}
				if($change->new_value == 1) $loglevel = LOG_WARNING;
				break;
			}
			$this->log(array('action' => 'Setting update', 'value' => $change->new_value, 'oldvalue' => $change->old_value, 'field' => ucfirst(str_replace('_', ' ', $change->field))), $loglevel);
		}
		if($resync) {
			$this->server->sync_access();
			$this->sync_remote_access();
		}
	}

	/**
	* List all log events for this server account.
	* @return array of ServerAccountEvent objects
	*/
	public function get_log() {
		if(is_null($this->id)) throw new BadMethodCallException('Server account must be in directory before log entries can be listed');
		$stmt = $this->database->prepare("
			SELECT *
			FROM entity_event
			WHERE entity_id = ?
			ORDER BY id DESC
		");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$log = array();
		while($row = $result->fetch_assoc()) {
			$log[] = new ServerAccountEvent($row['id'], $row);
		}
		$stmt->close();
		return $log;
	}

	/**
	* Add the specified user as an administrator of the account.
	* This action is logged with a warning level as it is increasing an access level.
	* @param User $user to add as administrator
	*/
	public function add_admin(User $user) {
		global $config;
		parent::add_admin($user);
		$url = $config['web']['baseurl'].'/servers/'.urlencode($this->server->hostname).'/accounts/'.urlencode($this->name);
		$email = new Email;
		$email->subject = "Administrator for {$this->name}@{$this->server->hostname}";
		$email->add_cc($config['email']['report_address'], $config['email']['report_name']);
		$email->add_recipient($user->email, $user->name);
		$email->body = "{$this->active_user->name} ({$this->active_user->uid}) has added you as an administrator for the '{$this->name}' account on {$this->server->hostname}.  You can administer access to this account from <$url>";
		$email->send();
		$this->log(array('action' => 'Administrator add', 'value' => "user:{$user->uid}"), LOG_WARNING);
	}

	/**
	* Remove the specified user as an administrator of the account.
	* This action is logged with a warning level as it means the removed user will no longer
	* receive notifications for any changes done to this account.
	* @param User $user to remove as administrator
	*/
	public function delete_admin(User $user) {
		parent::delete_admin($user);
		$this->log(array('action' => 'Administrator remove', 'value' => "user:{$user->uid}"), LOG_WARNING);
	}

	/**
	* Add a public key to this account for use with any outbound access rules that apply to it.
	* An email is sent to the server admins and sec-ops to inform them of the change.
	* This action is logged with a warning level as it is potentially granting SSH access with the key.
	* @param PublicKey $key to be added
	*/
	public function add_public_key(PublicKey $key) {
		global $config;
		parent::add_public_key($key);
		if($this->active_user->uid != 'import-script') {
			$url = $config['web']['baseurl'].'/pubkeys/'.urlencode($key->id);
			$email = new Email;
			$email->add_reply_to($config['email']['admin_address'], $config['email']['admin_name']);
			foreach($this->server->list_effective_admins() as $admin) {
				$email->add_recipient($admin->email, $admin->name);
			}
			$email->add_cc($config['email']['report_address'], $config['email']['report_name']);
			$email->subject = "A new SSH public key has been added to the account {$this->name}@{$this->server->hostname} by {$this->active_user->uid}";
			$email->body = "A new SSH public key has been added to the account {$this->name}@{$this->server->hostname} on SSH Key Authority. The key was added by {$this->active_user->name} ({$this->active_user->uid}).\n\nIf this key was added without your knowledge, please contact {$config['email']['admin_address']} immediately.\n\n".$key->summarize_key_information();
			$email->send();
		}
		$this->log(array('action' => 'Pubkey add', 'value' => $key->fingerprint_md5), LOG_WARNING);
	}

	/**
	* Delete the specified public key from this account.
	* @param PublicKey $key to be removed
	*/
	public function delete_public_key(PublicKey $key) {
		parent::delete_public_key($key);
		$this->log(array('action' => 'Pubkey remove', 'value' => $key->fingerprint_md5));
	}

	/**
	* Request access for the specified entity (User/ServerAccount/Group) to this account.
	* Stores the request and sends an email to the account admins and server admins notifying them of it.
	* @param Entity $entity to request access for
	*/
	public function add_access_request(Entity $entity) {
		global $config;
		if(is_null($this->entity_id)) throw new BadMethodCallException('Server account must be added to server before access can be requested');
		try {
			$request = new AccessRequest;
			$request->dest_entity_id = $this->entity_id;
			$request->source_entity_id = $entity->entity_id;
			$request->requested_by = $this->active_user->entity_id;
			$stmt = $this->database->prepare("INSERT INTO access_request SET dest_entity_id = ?, source_entity_id = ?, request_date = UTC_TIMESTAMP(), requested_by = ?");
			$stmt->bind_param('ddd', $request->dest_entity_id, $request->source_entity_id, $request->requested_by);
			$stmt->execute();
			$request->id = $stmt->insert_id;
			$stmt->close();
			switch(get_class($entity)) {
			case 'User':
				$this->log(array('action' => 'Access request', 'value' => "user:{$entity->uid}"));
				break;
			case 'ServerAccount':
				$this->log(array('action' => 'Access request', 'value' => "account:{$entity->name}@{$entity->server->hostname}"));
				break;
			case 'Group':
				$this->log(array('action' => 'Access request', 'value' => "group:{$entity->name}"));
				break;
			}
			$account_admins = $this->list_admins();
			$server_admins = $this->server->list_effective_admins();
			if($this->active_user->uid != 'import-script') {
				$email = new Email;
				$email->add_reply_to($this->active_user->email, $this->active_user->name);
				if(count($account_admins) == 0) {
					foreach($server_admins as $admin) {
						$email->add_recipient($admin->email, $admin->name);
					}
				} else {
					foreach($account_admins as $admin) {
						$email->add_recipient($admin->email, $admin->name);
					}
					foreach($server_admins as $admin) {
						$email->add_cc($admin->email, $admin->name);
					}
				}
				$url = $config['web']['baseurl'].'/servers/'.urlencode($this->server->hostname).'/accounts/'.urlencode($this->name);
				switch(get_class($entity)) {
				case 'User':
					$email->subject = "{$entity->uid} requests access to {$this->name}@{$this->server->hostname}";
					$email->body = "{$entity->name} ({$entity->uid}) has requested access to {$this->name}@{$this->server->hostname}. View this request at <$url>";
					break;
				case 'ServerAccount':
					$email->subject = "{$this->active_user->uid} requests {$entity->name}@{$entity->server->hostname} access to {$this->name}@{$this->server->hostname}";
					$email->body = "{$this->active_user->name} ({$this->active_user->uid}) has requested that {$entity->name}@{$entity->server->hostname} have server-to-server access to {$this->name}@{$this->server->hostname}. View this request at <$url>";
					break;
				case 'Group':
					$email->subject = "{$this->active_user->uid} requests {$entity->name} group access to {$this->name}@{$this->server->hostname}";
					$email->body = "{$this->active_user->name} ({$this->active_user->uid}) has requested that the {$entity->name} group have access to {$this->name}@{$this->server->hostname}. View this request at <$url>";
					break;
				}
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
	* Approve a request for access to this account.
	* For user access, sends an email to the requester informing them of the approval.
	* Triggers add_access() and deletes the request from the DB.
	* @todo send emails for all access types
	* @param AccessRequest $request details
	*/
	public function approve_access_request(AccessRequest $request) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Server account must be added to server before access can be approved');
		$entity = $request->source_entity;
		switch(get_class($entity)) {
		case 'User':
			$this->log(array('action' => 'Access approve', 'value' => "user:{$entity->uid}"));
			$email = new Email;
			$email->add_recipient($entity->email, $entity->name);
			$email->subject = "Your request for access to {$this->name}@{$this->server->hostname} has been approved";
			$email->body = "You requested access to {$this->name}@{$this->server->hostname}, and this request has now been approved by {$this->active_user->name} ({$this->active_user->uid}).";
			$email->send();
			break;
		case 'ServerAccount':
			$this->log(array('action' => 'Access approve', 'value' => "account:{$entity->name}@{$entity->server->hostname}"));
			break;
		case 'Group':
			$this->log(array('action' => 'Access approve', 'value' => "group:{$entity->name}"));
			break;
		}
		$options = array();
		$this->add_access($entity, $options);
		$stmt = $this->database->prepare("DELETE FROM access_request WHERE dest_entity_id = ? AND id = ?");
		$stmt->bind_param('dd', $this->entity_id, $request->id);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* Reject a request for access to this account.
	* For user access, sends an email to the requester informing them of the rejection.
	* Deletes the request from the DB. If the account was created as the result of a request and
	* there are no other pending access requests for the account, deactivate the account.
	* @todo send emails for all access types
	* @param AccessRequest $request details
	*/
	public function reject_access_request(AccessRequest $request) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Server account must be added to server before access can be rejected');
		$entity = $request->source_entity;
		switch(get_class($entity)) {
		case 'User':
			$this->log(array('action' => 'Access reject', 'value' => "user:{$entity->uid}"));
			$email = new Email;
			$email->add_recipient($entity->email, $entity->name);
			$email->subject = "Your request for access to {$this->name}@{$this->server->hostname} has been rejected";
			$email->body = "You requested access to {$this->name}@{$this->server->hostname}, but this request has been rejected by {$this->active_user->name} ({$this->active_user->uid}).";
			$email->send();
			break;
		case 'ServerAccount':
			$this->log(array('action' => 'Access reject', 'value' => "account:{$entity->name}@{$entity->server->hostname}"));
			break;
		case 'Group':
			$this->log(array('action' => 'Access reject', 'value' => "group:{$entity->name}"));
			break;
		}
		$stmt = $this->database->prepare("DELETE FROM access_request WHERE dest_entity_id = ? AND id = ?");
		$stmt->bind_param('dd', $this->entity_id, $request->id);
		$stmt->execute();
		$stmt->close();
		if($this->sync_status == 'proposed') {
			if(count($this->list_access_requests()) == 0) {
				$this->active = 0;
				$this->update();
			}
		}
	}

	/**
	* Grant the specified entity (User/ServerAccount/Group) access to this server account.
	* An email is sent to the account admins, server admins and sec-ops to inform them of the change.
	* This action is logged with a warning level as it is granting access.
	* @param Entity $entity to add as a group member
	* @param array $access_options array of AccessOption rules to apply to the granted access
	*/
	public function add_access(Entity $entity, array $access_options) {
		global $config;
		if(is_null($this->entity_id)) throw new BadMethodCallException('Server account must be added to server before access can be added');
		if($this->sync_status == 'proposed') {
			$this->sync_status = 'not synced yet';
			$this->update();
		}
		try {
			$access = new Access;
			$access->dest_entity_id = $this->entity_id;
			$access->source_entity_id = $entity->entity_id;
			$access->granted_by = $this->active_user->entity_id;
			$stmt = $this->database->prepare("INSERT INTO access SET dest_entity_id = ?, source_entity_id = ?, grant_date = UTC_TIMESTAMP(), granted_by = ?");
			$stmt->bind_param('ddd', $access->dest_entity_id, $access->source_entity_id, $access->granted_by);
			$stmt->execute();
			$access->id = $stmt->insert_id;
			$stmt->close();
			switch(get_class($entity)) {
			case 'User':
				$this->log(array('action' => 'Access add', 'value' => "user:{$entity->uid}"), LOG_WARNING);
				$mailsubject = "Access granted for {$entity->uid} to {$this->name}@{$this->server->hostname} by {$this->active_user->uid}";
				$mailbody = "{$entity->name} ({$entity->uid}) has been granted access to {$this->name}@{$this->server->hostname} by {$this->active_user->name} ({$this->active_user->uid}). The changes will be synced to the server within a few seconds.";
				break;
			case 'ServerAccount':
				$this->log(array('action' => 'Access add', 'value' => "account:{$entity->name}@{$entity->server->hostname}"), LOG_WARNING);
				$mailsubject = "Access granted for {$entity->name}@{$entity->server->hostname} to {$this->name}@{$this->server->hostname} by {$this->active_user->uid}";
				$mailbody = "{$entity->name}@{$entity->server->hostname} has been granted server-to-server access to {$this->name}@{$this->server->hostname} by {$this->active_user->name} ({$this->active_user->uid}). The changes will be synced to the server within a few seconds.";
				break;
			case 'Group':
				$this->log(array('action' => 'Access add', 'value' => "group:{$entity->name}"), LOG_WARNING);
				$mailsubject = "Access granted for {$entity->name} group to {$this->name}@{$this->server->hostname} by {$this->active_user->uid}";
				$mailbody = "The {$entity->name} group has been granted access to {$this->name}@{$this->server->hostname} by {$this->active_user->name} ({$this->active_user->uid}). The changes will be synced to the server within a few seconds.";
				break;
			}
			if($this->active_user->uid != 'import-script') {
				$account_admins = $this->list_admins();
				$server_admins = $this->server->list_effective_admins();
				$email = new Email;
				if(count($account_admins) == 0) {
					foreach($server_admins as $admin) {
						$email->add_recipient($admin->email, $admin->name);
					}
				} else {
					foreach($account_admins as $admin) {
						$email->add_recipient($admin->email, $admin->name);
					}
					foreach($server_admins as $admin) {
						$email->add_cc($admin->email, $admin->name);
					}
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
	* Revoke the specified access rule for this account.
	* @param Access $access rule to be removed
	*/
	public function delete_access(Access $access) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Server account must be added to server before access can be deleted');
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
		$stmt->bind_param('dd', $this->entity_id, $access->id);
		$stmt->execute();
		$stmt->close();
		$this->sync_access();
	}

	/**
	* List all groups that this account is a member of.
	* @return array of Group objects
	*/
	public function list_group_membership() {
		global $group_dir;
		return $group_dir->list_group_membership($this);
	}

	/**
	* Trigger a sync for this account.
	*/
	public function sync_access() {
		global $sync_request_dir;
		$sync_request = new SyncRequest;
		$sync_request->server_id = $this->server_id;
		$sync_request->account_name = $this->name;
		$sync_request_dir->add_sync_request($sync_request);
	}

	/**
	* Determine if a sync is currently pending for this account.
	* @return boolean true if a sync is pending
	*/
	public function sync_is_pending() {
		$stmt = $this->database->prepare("SELECT * FROM sync_request WHERE server_id = ? AND (account_name = ? OR account_name IS NULL) ORDER BY account_name");
		$stmt->bind_param('ds', $this->server_id, $this->name);
		$stmt->execute();
		$result = $stmt->get_result();
		return $result->num_rows > 0;
	}

	/**
	* Update the sync status for the account.
	* @param string $status "sync success", "sync failure" or "sync warning"
	*/
	public function sync_report($status) {
		if(is_null($this->id)) throw new BadMethodCallException('Server account must be in directory before sync reporting can be done');
		if($this->sync_status != 'proposed') {
			$this->sync_status = $status;
			$this->update();
		}
	}
}
