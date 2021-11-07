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
* Class that represents a user of this system
*/
class User extends Entity {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'user';
	/**
	* Defines the field that is the primary key of the table
	*/
	protected $idfield = 'entity_id';
	/**
	* LDAP connection object
	*/
	private $ldap;

	public function __construct($id = null, $preload_data = array()) {
		parent::__construct($id, $preload_data);
		global $ldap;
		$this->ldap = $ldap;
	}

	/**
	* Write property changes to database and log the changes.
	* Triggers a resync if the user was activated/deactivated.
	*/
	public function update() {
		$changes = parent::update();
		$resync = false;
		foreach($changes as $change) {
			$loglevel = LOG_INFO;
			switch($change->field) {
			case 'active':
				$resync = true;
				if($change->new_value == 1) $loglevel = LOG_WARNING;
				break;
			case 'admin':
				if($change->new_value == 1) $loglevel = LOG_WARNING;
				break;
			case 'csrf_token':
			case 'superior_entity_id':
				return;
			}
			$this->log(array('action' => 'Setting update', 'value' => $change->new_value, 'oldvalue' => $change->old_value, 'field' => ucfirst(str_replace('_', ' ', $change->field))), $loglevel);
		}
		if($resync) {
			$this->sync_remote_access();
		}
	}

	/**
	* Magic getter method - if superior field requested, return User object of user's superior
	* @param string $field to retrieve
	* @return mixed data stored in field
	*/
	public function &__get($field) {
		global $user_dir;
		switch($field) {
		case 'superior':
			if(is_null($this->superior_entity_id)) $superior = null;
			else $superior = new User($this->superior_entity_id);
			return $superior;
		default:
			return parent::__get($field);
		}
	}

	/**
	* List all events on entities and servers that this user has administrator access to
	* @param array $include list of extra data to include in response
	* @return array of *Event objects
	*/
	public function list_events($include = array()) {
		global $event_dir;
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before events can be listed');
		return $event_dir->list_events($include, array('admin' => $this->entity_id));
	}

	/**
	* List all servers that are administrated by this user
	* @param array $include list of extra data to include in response
	* @return array of Server objects
	*/
	public function list_admined_servers($include = array()) {
		global $server_dir;
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before admined servers can be listed');
		return $server_dir->list_servers($include, array('admin' => $this->entity_id, 'key_management' => array('none', 'keys', 'other')));
	}

	/**
	* List all groups that are administrated by this user
	* @param array $include list of extra data to include in response
	* @return array of Group objects
	*/
	public function list_admined_groups($include = array()) {
		global $group_dir;
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before admined group can be listed');
		$groups = $group_dir->list_groups($include, array('admin' => $this->entity_id));
		return $groups;
	}

	/**
	* List all groups that this user is a member of
	* @param array $include list of extra data to include in response
	* @return array of Group objects
	*/
	public function list_group_memberships($include = array()) {
		global $group_dir;
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before group memberships can be listed');
		$groups = $group_dir->list_groups($include, array('member' => $this->entity_id));
		return $groups;
	}

	/**
	* Determine if this user is an administrator of the specified entity or server.
	* @param Record $record object to check for administration privileges
	* @return bool true if user is an administrator of the object
	* @throws InvalidArgumentException if a non-administratable Record is provided
	*/
	public function admin_of(Record $record) {
		switch(get_class($record)) {
		case 'Server':
			$stmt = $this->database->prepare("
				SELECT entity_id
				FROM group_member
				WHERE  (`group` IN (
						SELECT entity_id
						FROM server_admin
						WHERE server_id = ?)
					AND entity_id = ?)
				UNION  (SELECT entity_id
					FROM server_admin
					WHERE server_id = ?
					AND entity_id = ?)");
			$stmt->bind_param('dddd', $record->id, $this->entity_id, $record->id, $this->entity_id);
			$stmt->execute();
			$result = $stmt->get_result();
			return $result->num_rows >= 1;
			break;
		case 'Group':
		case 'ServerAccount':
			$stmt = $this->database->prepare("SELECT * FROM entity_admin WHERE admin = ? AND entity_id = ?");
			$stmt->bind_param('dd', $this->entity_id, $record->entity_id);
			$stmt->execute();
			$result = $stmt->get_result();
			return $result->num_rows >= 1;
			break;
		default:
			throw new InvalidArgumentException('Records of type '.get_class($record).' cannot be administered');
		}
	}

	/**
	* Determine if this user is a member of the specified group
	* @param Group $group to check membership of
	* @return bool true if user is an member of the group
	*/
	public function member_of(Group $group) {
		$stmt = $this->database->prepare("SELECT * FROM group_member WHERE entity_id = ? AND `group` = ?");
		$stmt->bind_param('dd', $this->entity_id, $group->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		return $result->num_rows >= 1;
	}

	/**
	* Add a public key to this user for use with any outbound access rules that apply to them.
	* An email is sent to the user and sec-ops to inform them of the change.
	* This action is logged with a warning level as it is potentially granting SSH access with the key.
	* @param PublicKey $key to be added
	*/
	public function add_public_key(PublicKey $key) {
		global $active_user, $config;
		parent::add_public_key($key);
		if($active_user->uid != 'import-script') {
			$url = $config['web']['baseurl'].'/pubkeys/'.urlencode($key->id);
			$email = new Email;
			$email->add_reply_to($config['email']['admin_address'], $config['email']['admin_name']);
			$email->add_recipient($this->email, $this->name);
			$email->add_cc($config['email']['report_address'], $config['email']['report_name']);
			$email->subject = "A new SSH public key has been added to your account ({$this->uid})";
			$email->body = "A new SSH public key has been added to your account on SSH Key Authority.\n\nIf you added this key then all is well. If you do not recall adding this key, please contact {$config['email']['admin_address']} immediately.\n\n".$key->summarize_key_information();
			$email->send();
		}
		$this->log(array('action' => 'Pubkey add', 'value' => $key->fingerprint_md5), LOG_WARNING);
	}

	/**
	* Delete the specified public key from this user.
	* @param PublicKey $key to be removed
	*/
	public function delete_public_key(PublicKey $key) {
		global $active_user;
		parent::delete_public_key($key);
		$this->log(array('action' => 'Pubkey remove', 'value' => $key->fingerprint_md5));
	}

	/**
	* Add an alert to be displayed to this user on their next normal page load.
	* @param UserAlert $alert to be displayed
	*/
	public function add_alert(UserAlert $alert) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before alerts can be added');
		$stmt = $this->database->prepare("INSERT INTO user_alert SET entity_id = ?, class = ?, content = ?, escaping = ?");
		$stmt->bind_param('dssd', $this->entity_id, $alert->class, $alert->content, $alert->escaping);
		$stmt->execute();
		$alert->id = $stmt->insert_id;
		$stmt->close();
	}

	/**
	* List all alerts for this user *and* delete them.
	* @return array of UserAlert objects
	*/
	public function pop_alerts() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before alerts can be listed');
		$stmt = $this->database->prepare("SELECT * FROM user_alert WHERE entity_id = ?");
		$stmt->bind_param('d', $this->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$alerts = array();
		$alert_ids = array();
		while($row = $result->fetch_assoc()) {
			$alerts[] = new UserAlert($row['id'], $row);
			$alert_ids[] = $row['id'];
		}
		$stmt->close();
		if(count($alert_ids) > 0) {
			$this->database->query("DELETE FROM user_alert WHERE id IN (".implode(", ", $alert_ids).")");
		}
		return $alerts;
	}

	/**
	* Determine if this user has been granted access to the specified account.
	* @param ServerAccount $account to check for access
	* @return bool true if user has access to the account
	*/
	public function has_access(ServerAccount $account) {
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before access can be checked');
		$stmt = $this->database->prepare("SELECT * FROM access WHERE source_entity_id = ? AND dest_entity_id = ?");
		$stmt->bind_param('dd', $this->entity_id, $account->entity_id);
		$stmt->execute();
		$result = $stmt->get_result();
		return (bool)$result->fetch_assoc();
	}

	/**
	* Return HTML containing the user's CSRF token for inclusion in a POST form.
	* Also includes a random string of the same length to help guard against http://breachattack.com/
	* @return string HTML
	*/
	public function get_csrf_field() {
		return '<input type="hidden" name="csrf_token" value="'.hesc($this->get_csrf_token()).'"><!-- '.hash("sha512", mt_rand(0, mt_getrandmax())).' -->'."\n";
	}

	/**
	* Return the user's CSRF token. Generate one if they do not yet have one.
	* @return string CSRF token
	*/
	public function get_csrf_token() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before CSRF token can be generated');
		if(!isset($this->data['csrf_token'])) {
			$this->data['csrf_token'] = hash("sha512", mt_rand(0, mt_getrandmax()));
			$this->update();
		}
		return $this->data['csrf_token'];
	}

	/**
	* Check the given string against the user's CSRF token.
	* @return bool true on string match
	*/
	public function check_csrf_token($token) {
		return $token === $this->get_csrf_token();
	}

	/**
	* Retrieve the user's details from LDAP.
	* @throws UserNotFoundException if the user is not found in LDAP
	*/
	public function get_details_from_ldap() {
		global $config, $group_dir, $user_dir;
		$attributes = array();
		$attributes[] = 'dn';
		$attributes[] = $config['ldap']['user_id'];
		$attributes[] = $config['ldap']['user_name'];
		$attributes[] = $config['ldap']['user_email'];
		$attributes[] = $config['ldap']['group_member_value'];
		if(isset($config['ldap']['user_active'])) {
			$attributes[] = $config['ldap']['user_active'];
		}
		if(isset($config['ldap']['user_filter'])) {
			$user_filter = $config['ldap']['user_filter'];
		} else {
			$user_filter = '';
		}
		if(isset($config['ldap']['group_filter'])) {
			$group_filter = $config['ldap']['group_filter'];
		} else {
			$group_filter = '';
		}
		$ldapusers = $this->ldap->search($config['ldap']['dn_user'], '(&('.LDAP::escape($config['ldap']['user_id']).'='.LDAP::escape($this->uid).')'.$user_filter.')', array_keys(array_flip($attributes)));
		if($ldapuser = reset($ldapusers)) {
			$this->auth_realm = 'LDAP';
			$this->uid = $ldapuser[strtolower($config['ldap']['user_id'])];
			$this->name = $ldapuser[strtolower($config['ldap']['user_name'])];
			$this->email = $ldapuser[strtolower($config['ldap']['user_email'])];
			if(isset($config['ldap']['user_active'])) {
				$this->active = 0;
				if(isset($config['ldap']['user_active_true'])) {
					$this->active = intval($ldapuser[strtolower($config['ldap']['user_active'])] == $config['ldap']['user_active_true']);
				} elseif(isset($config['ldap']['user_active_false'])) {
					$this->active = intval($ldapuser[strtolower($config['ldap']['user_active'])] != $config['ldap']['user_active_false']);
				}
			} else {
				$this->active = 1;
			}
			$group_member = $ldapuser[strtolower($config['ldap']['group_member_value'])];
			$ldapgroups = $this->ldap->search($config['ldap']['dn_group'], '(&('.LDAP::escape($config['ldap']['group_member']).'='.LDAP::escape($group_member).')'.$group_filter.')', array('cn'));
			$memberships = array();
			foreach($ldapgroups as $ldapgroup) {
				$memberships[$ldapgroup['cn']] = true;
			}
			$this->admin = isset($memberships[$config['ldap']['admin_group_cn']]);
			if(isset($this->id)) {
				$this->update();
			} else {
				$user_dir->add_user($this);
			}
			if(isset($config['ldap']['sync_groups']) && is_array($config['ldap']['sync_groups'])) {
				$syncgroups = $config['ldap']['sync_groups'];
			} else {
				$syncgroups = array();
			}
			$syncgroups[] = $config['ldap']['admin_group_cn'];
			foreach($syncgroups as $syncgroup) {
				try {
					$group = $group_dir->get_group_by_name($syncgroup);
				} catch(GroupNotFoundException $e) {
					$group = new Group;
					$group->name = $syncgroup;
					$group->system = 1;
					$group_dir->add_group($group);
				}
				if(isset($memberships[$syncgroup])) {
					if(!$this->member_of($group)) {
						$group->add_member($this);
					}
				} else {
					if($this->member_of($group)) {
						$group->delete_member($this);
					}
				}
			}
		} else {
			throw new UserNotFoundException('User does not exist.');
		}
	}

	/**
	* Retrieve the user's superior from LDAP.
	* @throws UserNotFoundException if the user is not found in LDAP
	*/
	public function get_superior_from_ldap() {
		global $user_dir, $config;
		if(is_null($this->entity_id)) throw new BadMethodCallException('User must be in directory before superior employee can be looked up');
		if(!isset($config['ldap']['user_superior'])) {
			throw new BadMethodCallException("Cannot retrieve user's superior if user_superior is not configured");
		}
		$ldapusers = $this->ldap->search($config['ldap']['dn_user'], LDAP::escape($config['ldap']['user_id']).'='.LDAP::escape($this->uid), array($config['ldap']['user_superior']));
		if($ldapuser = reset($ldapusers)) {
			$superior = null;
			if(isset($ldapuser[strtolower($config['ldap']['user_superior'])]) && $ldapuser[strtolower($config['ldap']['user_superior'])] != $this->uid) {
				$superior_uid = $ldapuser[strtolower($config['ldap']['user_superior'])];
				try {
					$superior = $user_dir->get_user_by_uid($superior_uid);
				} catch(UserNotFoundException $e) {
				}
			}
			if(is_null($superior)) {
				$this->superior_entity_id = null;
			} else {
				$this->superior_entity_id = $superior->entity_id;
			}
			$this->update();
		} else {
			throw new UserNotFoundException('User does not exist.');
		}
	}

	/**
	* Implements the Entity::sync_access as a no-op as it makes no sense to grant access TO a user.
	*/
	public function sync_access() {
	}
}
