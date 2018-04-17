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
* Class for reading/writing to the list of Server objects in the database.
*/
class ServerDirectory extends DBDirectory {
	/**
	* Create the new server in the database.
	* @param Server $server object to add
	* @throws ServerAlreadyExistsException if a server with that hostname already exists
	*/
	public function add_server(Server $server) {
		$hostname = $server->hostname;
		$port = $server->port;
		try {
			$stmt = $this->database->prepare("INSERT INTO server SET hostname = ?, port = ?");
			$stmt->bind_param('sd', $hostname, $port);
			$stmt->execute();
			$server->id = $stmt->insert_id;
			$stmt->close();
			$server->log(array('action' => 'Server add'));
			$server->add_standard_accounts();
			$server->sync_access();
		} catch(mysqli_sql_exception $e) {
			if($e->getCode() == 1062) {
				// Duplicate entry
				throw new ServerAlreadyExistsException("Server {$server->hostname} already exists");
			} else {
				throw $e;
			}
		}
	}

	/**
	* Get a server from the database by its ID.
	* @param int $id of server
	* @return Server with specified ID
	* @throws ServerNotFoundException if no server with that ID exists
	*/
	public function get_server_by_id($server_id) {
		$stmt = $this->database->prepare("SELECT * FROM server WHERE id = ?");
		$stmt->bind_param('d', $server_id);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$server = new Server($row['id'], $row);
		} else {
			throw new ServerNotFoundException('Server does not exist.');
		}
		$stmt->close();
		return $server;
	}

	/**
	* Get a server from the database by its hostname.
	* @param string $hostname of server
	* @return Server with specified hostname
	* @throws ServerNotFoundException if no server with that hostname exists
	*/
	public function get_server_by_hostname($hostname) {
		$stmt = $this->database->prepare("SELECT * FROM server WHERE hostname = ?");
		$stmt->bind_param('s', $hostname);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$server = new Server($row['id'], $row);
		} else {
			throw new ServerNotFoundException('Server does not exist');
		}
		$stmt->close();
		return $server;
	}

	/**
	* Get a server from the database by its uuid.
	* @param string $uuid of server
	* @return Server with specified uuid
	* @throws ServerNotFoundException if no server with that uuid exists
	*/
	public function get_server_by_uuid($uuid) {
		$stmt = $this->database->prepare("SELECT * FROM server WHERE uuid = ?");
		$stmt->bind_param('s', $uuid);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			$server = new Server($row['id'], $row);
		} else {
			throw new ServerNotFoundException('Server does not exist');
		}
		$stmt->close();
		return $server;
	}

	/**
	* List all servers in the database.
	* @param array $include list of extra data to include in response
	* @param array $filter list of field/value pairs to filter results on
	* @return array of Server objects
	*/
	public function list_servers($include = array(), $filter = array()) {
		// WARNING: The search query is not parameterized - be sure to properly escape all input
		$fields = array("server.*");
		$joins = array();
		$where = array('!server.deleted');
		foreach($filter as $field => $value) {
			if($value) {
				switch($field) {
				case 'hostname':
					$where[] = "hostname REGEXP '".$this->database->escape_string($value)."'";
					break;
				case 'ip_address':
				case 'rsa_key_fingerprint':
					$where[] = "server.$field = '".$this->database->escape_string($value)."'";
					break;
				case 'admin':
					$where[] = "admin_search.entity_id = ".intval($value)." OR admin_search_members.entity_id = ".intval($value);
					$joins['adminsearch'] = "LEFT JOIN server_admin AS admin_search ON admin_search.server_id = server.id";
					$joins['adminsearchmembers'] = "LEFT JOIN group_member AS admin_search_members ON admin_search_members.group = admin_search.entity_id";
					break;
				case 'authorization':
				case 'key_management':
				case 'sync_status':
					$where[] = "server.$field IN ('".implode("', '", array_map(array($this->database, 'escape_string'), $value))."')";
					break;
				}
			}
		}
		foreach($include as $inc) {
			switch($inc) {
			case 'pending_requests':
				$fields[] = "COUNT(DISTINCT access_request.source_entity_id) AS pending_requests";
				$joins['accounts'] = "LEFT JOIN server_account ON server_account.server_id = server.id";
				$joins['requests'] = "LEFT JOIN access_request ON access_request.dest_entity_id = server_account.entity_id";
				break;
			case 'admins':
				$fields[] = "GROUP_CONCAT(DISTINCT IF(user.uid IS NULL, CONCAT('G:', group.name), CONCAT('U:', user.uid)) SEPARATOR ',') AS admins";
				$joins['admins'] = "LEFT JOIN server_admin ON server_admin.server_id = server.id";
				$joins['adminusers'] = "LEFT JOIN user ON user.entity_id = server_admin.entity_id AND user.active";
				$joins['admingroups'] = "LEFT JOIN `group` ON group.entity_id = server_admin.entity_id";
				break;
			}
		}
		try {
			$stmt = $this->database->prepare("
				SELECT ".implode(", ", $fields)."
				FROM server ".implode(" ", $joins)."
				WHERE (".implode(") AND (", $where).")
				GROUP BY server.id
				ORDER BY server.hostname
			");
		} catch(mysqli_sql_exception $e) {
			if($e->getCode() == 1139) {
				throw new ServerSearchInvalidRegexpException;
			} else {
				throw $e;
			}
		}

		$stmt->execute();
		$result = $stmt->get_result();
		$servers = array();
		while($row = $result->fetch_assoc()) {
			$servers[] = new Server($row['id'], $row);
		}
		$stmt->close();
		usort($servers, function($a, $b) {return strnatcasecmp($a->hostname, $b->hostname);});
		# Reverse domain level sort
		#usort($servers, function($a, $b) {return strnatcasecmp(implode('.', array_reverse(explode('.', $a->hostname))), implode('.', array_reverse(explode('.', $b->hostname))));});
		return $servers;
	}
}

class ServerNotFoundException extends Exception {}
class ServerAlreadyExistsException extends Exception {}
class ServerSearchInvalidRegexpException extends Exception {}
