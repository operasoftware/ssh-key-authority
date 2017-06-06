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
* Class for reading/writing to the list of PublicKey objects in the database.
*/
class PublicKeyDirectory extends DBDirectory {
	/**
	* Retrieve a public key matching the specified ID.
	* @param int $id of public key to retrieve
	* @return PublicKey object with specified ID
	* @throws PublicKeyNotFoundException if no key with that ID exists
	*/
	public function get_public_key_by_id($id) {
		$stmt = $this->database->prepare("
			SELECT public_key.*, entity.type AS entity_type
			FROM public_key
			INNER JOIN entity ON entity.id = public_key.entity_id
			WHERE public_key.id = ?
		");
		$stmt->bind_param('d', $id);
		$stmt->execute();
		$result = $stmt->get_result();
		if($row = $result->fetch_assoc()) {
			switch($row['entity_type']) {
			case 'user': $row['owner'] = new User($row['entity_id']); break;
			case 'server account': $row['owner'] = new ServerAccount($row['entity_id']); break;
			}
			$key = new PublicKey($row['id'], $row);
		} else {
			throw new PublicKeyNotFoundException('Public key does not exist.');
		}
		$stmt->close();
		return $key;
	}

	/**
	* List stored public keys, optionally filtered by various parameters.
	* See also Entity::list_public_keys function for retrieving keys belonging to a specific entity.
	* @param array $include list of extra data to include in response - currently unused
	* @param array $filter list of field/value pairs to filter results on
	* @return array of PublicKey objects
	*/
	public function list_public_keys($include = array(), $filter = array()) {
		// WARNING: The search query is not parameterized - be sure to properly escape all input
		$fields = array("public_key.*, entity.type AS entity_type");
		$joins = array();
		$where = array();
		foreach($filter as $field => $value) {
			if($value) {
				switch($field) {
				case 'type':
					$where[] = "public_key.type = '".$this->database->escape_string($value)."'";
					break;
				case 'keysize-min':
					$where[] = "public_key.keysize >= ".intval($this->database->escape_string($value));
					break;
				case 'keysize-max':
					$where[] = "public_key.keysize <= ".intval($this->database->escape_string($value));
					break;
				case 'fingerprint':
					$where[] = "public_key.fingerprint_md5 = '".$this->database->escape_string($value)."' OR public_key.fingerprint_sha256 = '".$this->database->escape_string($value)."'";
					break;
				}
			}
		}
		$stmt = $this->database->prepare("
			SELECT ".implode(", ", $fields)."
			FROM public_key ".implode(" ", $joins)."
			INNER JOIN entity ON entity.id = public_key.entity_id
			LEFT JOIN user ON user.entity_id = entity.id
			LEFT JOIN server_account ON server_account.entity_id = entity.id
			LEFT JOIN server ON server.id = server_account.server_id
			".(count($where) == 0 ? "" : "WHERE (".implode(") AND (", $where).")")."
			ORDER BY entity.type, user.uid, server.hostname, server_account.name
		");
		$stmt->execute();
		$result = $stmt->get_result();
		$pubkeys = array();
		while($row = $result->fetch_assoc()) {
			switch($row['entity_type']) {
			case 'user': $row['owner'] = new User($row['entity_id']); break;
			case 'server account': $row['owner'] = new ServerAccount($row['entity_id']); break;
			}
			$pubkeys[] = new PublicKey($row['id'], $row);
		}
		return $pubkeys;
	}
}

class PublicKeyNotFoundException extends Exception {}
