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
* Abstract class that represents one of several types of entities (users, server accounts, groups)
* which can have access rules created between them, administrators assigned, or be members of each other.
*/
abstract class Entity extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'entity';

	/**
	* Write event details to syslog and to entity_event table.
	* @param array $details event paramaters to be logged
	* @param int $level syslog priority as defined in http://php.net/manual/en/function.syslog.php
	* @param User $actor The user who performs the logged action. In case of null, $this->active_user is assumed.
	*/
	public function log($details, $level = LOG_INFO, User $actor = null) {
		if(is_null($this->id)) throw new BadMethodCallException('Entity must be in directory before log entries can be added');
		if ($actor === null) {
			$actor = $this->active_user;
		}
		switch(get_class($this)) {
		case 'User':
			$scope = "user:{$this->uid}";
			break;
		case 'ServerAccount':
			$scope = "account:{$this->name}@{$this->server->hostname}";
			break;
		case 'Group':
			$scope = "group:{$this->name}";
			break;
		default:
			throw new BadMethodCallException('Unsupported entity type: '.get_class($this));
		}
		$json = json_encode($details, JSON_UNESCAPED_UNICODE);
		$stmt = $this->database->prepare("INSERT INTO entity_event SET entity_id = ?, actor_id = ?, date = UTC_TIMESTAMP(), details = ?");
		$stmt->bind_param('dds', $this->id, $actor->entity_id, $json);
		$stmt->execute();
		$stmt->close();

		$text = "KeysScope=\"{$scope}\" KeysRequester=\"{$actor->uid}\"";
		foreach($details as $key => $value) {
			$text .= ' Keys'.ucfirst($key).'="'.str_replace('"', '', $value).'"';
		}
		openlog('keys', LOG_ODELAY, LOG_AUTH);
		syslog($level, $text);
		closelog();
	}

	/**
	* Add the specified user as an administrator of the entity.
	* Logging is performed by the inheriting classes.
	* @param User $user to add as administrator
	*/
	public function add_admin(User $user) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before admins can be added');
		if(is_null($user->entity_id)) throw new InvalidArgumentException('User must be in directory before it can be made admin');
		$entity_id = $user->entity_id;
		try {
			$stmt = $this->database->prepare("INSERT INTO entity_admin SET entity_id = ?, admin = ?");
			$stmt->bind_param('dd', $this->entity_id, $entity_id);
			$stmt->execute();
			$stmt->close();
		} catch(mysqli_sql_exception $e) {
			if($e->getCode() == 1062) {
				// Duplicate entry - ignore
			} else {
				throw $e;
			}
		}
	}

	/**
	* Remove the specified user as an administrator of the entity.
	* @param User $user to remove as administrator
	*/
	public function delete_admin(User $user) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before admins can be deleted');
		if(is_null($user->entity_id)) throw new InvalidArgumentException('User must be in directory before it can be removed as admin');
		$entity_id = $user->entity_id;
		$stmt = $this->database->prepare("DELETE FROM entity_admin WHERE entity_id = ? AND admin = ?");
		$stmt->bind_param('dd', $this->entity_id, $entity_id);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* List all administrators of this entity.
	* @return array of User objects
	*/
	public function list_admins() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before admins can be listed');
		$stmt = $this->database->prepare("SELECT admin FROM entity_admin WHERE entity_id = ?");
		$stmt->bind_param('d', $this->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$admins = array();
		while($row = $result->fetch_assoc()) {
			$admins[] = new User($row['admin']);
		}
		$stmt->close();
		return $admins;
	}

	/**
	* Add a public key to this entity for use with any outbound access rules that apply to it.
	* Emailing and logging is handled by the inheriting classes.
	* @param PublicKey $key to be added
	*/
	public function add_public_key(PublicKey $key) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before public keys can be added');
		$key->get_openssh_info();
		$key_type = $key->type;
		$key_keydata = $key->keydata;
		$key_comment = $key->comment;
		$key_size = $key->keysize;
		$key_fingerprint_md5 = $key->fingerprint_md5;
		$key_fingerprint_sha256 = $key->fingerprint_sha256;
		$key_randomart_md5 = $key->randomart_md5;
		$key_randomart_sha256 = $key->randomart_sha256;
		$stmt = $this->database->prepare("
			INSERT INTO public_key SET
			entity_id = ?,
			type = ?,
			keydata = ?,
			comment = ?,
			keysize = ?,
			fingerprint_md5 = ?,
			fingerprint_sha256 = ?,
			randomart_md5 = ?,
			randomart_sha256 = ?
		");
		$stmt->bind_param('dsssdssss', $this->entity_id, $key_type, $key_keydata, $key_comment, $key_size, $key_fingerprint_md5, $key_fingerprint_sha256, $key_randomart_md5, $key_randomart_sha256);
		$stmt->execute();
		$key->id = $stmt->insert_id;
		$stmt->close();
		$this->sync_remote_access();
	}

	/**
	* Delete the specified public key from this entity.
	* @param PublicKey $key to be removed
	*/
	public function delete_public_key(PublicKey $key) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before public keys can be deleted');
		$stmt = $this->database->prepare("DELETE FROM public_key WHERE entity_id = ? AND id = ?");
		$stmt->bind_param('dd', $this->entity_id, $key->id);
		$stmt->execute();
		$stmt->close();
		$this->sync_remote_access();
	}

	/**
	* Retrieve a specific public key for this entity by its ID.
	* @param int $id of public key to retrieve
	* @return PublicKey matching the ID
	* @throws PublicKeyNotFoundException if no public key exists with that ID
	*/
	public function get_public_key_by_id($id) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before public keys can be listed');
		$stmt = $this->database->prepare("SELECT * FROM public_key WHERE entity_id = ? AND id = ?");
		$stmt->bind_param('dd', $this->entity_id, $id);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$key = new PublicKey($row['id'], $row);
		} else {
			throw new PublicKeyNotFoundException('Public key does not exist.');
		}
		$stmt->close();
		return $key;
	}

	/**
	* List all public keys associated with this entity, optionally filtered by account name and hostname
	* for any of the keys that have destination rules applied.
	* @todo this is perhaps an unintuitive place to do this kind of filtering
	* @param string|null $account_name to filter for in the destination rules for each key
	* @param string|null $hostname to filter for in the destination rules for each key
	* @return array of PublicKey objects
	*/
	public function list_public_keys($account_name = null, $hostname = null) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before public keys can be listed');
		$stmt = $this->database->prepare("
			SELECT public_key.*, COUNT(public_key_dest_rule.id) AS dest_rule_count
			FROM public_key
			LEFT JOIN public_key_dest_rule ON public_key_dest_rule.public_key_id = public_key.id
			WHERE entity_id = ?
			GROUP BY public_key.id
		");
		$stmt->bind_param('d', $this->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$keys = array();
		while($row = $result->fetch_assoc()) {
			if((is_null($account_name) && is_null($hostname)) || $row['dest_rule_count'] == 0) {
				$include = true;
			} else {
				$include = false;
				$rulestmt = $this->database->prepare("SELECT * FROM public_key_dest_rule WHERE public_key_id = ?");
				$rulestmt->bind_param('d', $row['id']);
				$rulestmt->execute();
				$ruleresult = $rulestmt->get_result();
				if($ruleresult->num_rows == 0) {
					// Key has no destination rules defined, include it everywhere
					$include = true;
				} else {
					// Apply destination rules
					while($rule = $ruleresult->fetch_assoc()) {
						$filter1 = '/^'.str_replace('\*', '.*', preg_quote($rule['account_name_filter'], '/')).'$/i';
						$filter2 = '/^'.str_replace('\*', '.*', preg_quote($rule['hostname_filter'], '/')).'$/i';
						if(preg_match($filter1, $account_name) && preg_match($filter2, $hostname)) {
							$include = true;
							break;
						}
					}
				}
			}
			if($include) {
				$keys[] = new PublicKey($row['id'], $row);
			}
		}
		$stmt->close();
		return $keys;
	}

	/**
	* Retrieve a specific access rule towards this entity by its ID (inbound access).
	* @param int $id to retrieve
	* @return Access object
	* @throws AccessNotFoundException if no access rule exists with this ID
	*/
	public function get_access_by_id($id) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before access can be listed');
		$stmt = $this->database->prepare("
			SELECT access.*, entity.type
			FROM access
			INNER JOIN entity ON entity.id = access.source_entity_id
			WHERE access.dest_entity_id = ? AND access.id = ?
		");
		$stmt->bind_param('dd', $this->entity_id, $id);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			switch($row['type']) {
			case 'user': $source_entity = new User($row['source_entity_id']); break;
			case 'server account': $source_entity = new ServerAccount($row['source_entity_id']); break;
			case 'group': $source_entity = new Group($row['source_entity_id']); break;
			}
			$row['granted_by'] = new User($row['granted_by']);
			$row['source_entity'] = $source_entity;
			$row['dest_entity'] = $this;
			$access = new Access($row['id'], $row);
		} else {
			throw new AccessNotFoundException('Access rule does not exist.');
		}
		$stmt->close();
		return $access;
	}

	/**
	* List all access rules that grant access to this entity (inbound access).
	* @return array of Access objects
	*/
	public function list_access() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before access can be listed');
		$stmt = $this->database->prepare("
			SELECT access.*, entity.type
			FROM access
			INNER JOIN entity ON entity.id = access.source_entity_id
			LEFT JOIN user ON user.entity_id = entity.id
			LEFT JOIN server_account ON server_account.entity_id = entity.id
			LEFT JOIN server ON server.id = server_account.server_id
			LEFT JOIN `group` ON `group`.entity_id = entity.id
			WHERE dest_entity_id = ?
			ORDER BY entity.type, user.uid, server.hostname, server_account.name, `group`.name
		");
		$stmt->bind_param('d', $this->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$access_list = array();
		while($row = $result->fetch_assoc()) {
			switch($row['type']) {
			case 'user': $source_entity = new User($row['source_entity_id']); break;
			case 'server account': $source_entity = new ServerAccount($row['source_entity_id']); break;
			case 'group': $source_entity = new Group($row['source_entity_id']); break;
			}
			$row['granted_by'] = new User($row['granted_by']);
			$row['source_entity'] = $source_entity;
			$access_list[] = new Access($row['id'], $row);
		}
		$stmt->close();
		return $access_list;
	}

	/**
	* List all requests for access to this entity (inbound access).
	* @return array of AccessRequest objects
	*/
	public function list_access_requests() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before access can be listed');
		$stmt = $this->database->prepare("
			SELECT access_request.*, entity.type
			FROM access_request
			INNER JOIN entity ON entity.id = access_request.source_entity_id
			LEFT JOIN user ON user.entity_id = entity.id
			LEFT JOIN server_account ON server_account.entity_id = entity.id
			LEFT JOIN server ON server.id = server_account.server_id
			LEFT JOIN `group` ON `group`.entity_id = entity.id
			WHERE dest_entity_id = ?
			ORDER BY entity.type, user.uid, server.hostname, server_account.name, `group`.name
		");
		$stmt->bind_param('d', $this->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$access_requests = array();
		while($row = $result->fetch_assoc()) {
			switch($row['type']) {
			case 'user': $source_entity = new User($row['source_entity_id']); break;
			case 'server account': $source_entity = new ServerAccount($row['source_entity_id']); break;
			case 'group': $source_entity = new Group($row['source_entity_id']); break;
			}
			$row['requested_by'] = new User($row['requested_by']);
			$row['source_entity'] = $source_entity;
			$access_requests[] = new AccessRequest($row['id'], $row);
		}
		$stmt->close();
		return $access_requests;
	}

	/**
	* List all access rules that grant this entity access to other entities (outbound access).
	* @return array of Access objects
	*/
	public function list_remote_access() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Entity must be in directory before remote access can be listed');
		$stmt = $this->database->prepare("
			SELECT access.*, entity.type
			FROM access
			INNER JOIN entity ON access.dest_entity_id = entity.id
			LEFT JOIN user ON user.entity_id = entity.id
			LEFT JOIN server_account ON server_account.entity_id = entity.id
			LEFT JOIN server ON server.id = server_account.server_id
			LEFT JOIN `group` ON `group`.entity_id = entity.id
			WHERE access.source_entity_id = ?
			ORDER BY entity.type, user.uid, server.hostname, server_account.name, `group`.name
		");
		$stmt->bind_param('d', $this->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$access_list = array();
		while($row = $result->fetch_assoc()) {
			switch($row['type']) {
			case 'user': $dest_entity = new User($row['dest_entity_id']); break;
			case 'server account': $dest_entity = new ServerAccount($row['dest_entity_id']); break;
			case 'group': $dest_entity = new Group($row['dest_entity_id']); break;
			}
			$row['granted_by'] = new User($row['granted_by']);
			$row['dest_entity'] = $dest_entity;
			$access_list[] = new Access($row['id'], $row);
		}
		$stmt->close();
		return $access_list;
	}

	/**
	* Trigger a sync for this entity - must be implemented by inheriting class.
	*/
	abstract public function sync_access();

	/**
	* Trigger a sync for all entities that this entity has access to (and recurse to group members).
	* @param $seen used to prevent infinite recursion and double-syncing by tracking all entities seen so far
	*/
	public function sync_remote_access(&$seen = array()) {
		$seen[$this->entity_id] = true;
		// Sync whatever this entity has access to
		$access_list = $this->list_remote_access();
		foreach($access_list as $access) {
			$access->dest_entity->sync_access();
		}
		// Sync whatever groups this entity is a member of
		global $group_dir;
		$memberships = $group_dir->list_group_membership($this);
		foreach($memberships as $group) {
			if(!isset($seen[$group->entity_id])) {
				$group->sync_remote_access($seen);
			}
		}
		// If this is a user, also sync across LDAP-based servers
		global $server_dir;
		global $sync_request_dir;
		if(get_class($this) == 'User') {
			$servers = $server_dir->list_servers(array(), array('authorization' => array('manual LDAP', 'automatic LDAP')));
			foreach($servers as $server) {
				$sync_request = new SyncRequest;
				$sync_request->server_id = $server->id;
				$sync_request->account_name = $this->uid;
				$sync_request_dir->add_sync_request($sync_request);
			}
		}
	}
}
