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

class LDAP {
	private $conn;
	private $host;
	private $starttls;
	private $bind_dn;
	private $bind_password;
	private $options;

	public function __construct($host, $starttls, $bind_dn, $bind_password, $options) {
		$this->conn = null;
		$this->host = $host;
		$this->starttls = $starttls;
		$this->bind_dn = $bind_dn;
		$this->bind_password = $bind_password;
		$this->options = $options;
	}

	private function connect() {
		$this->conn = ldap_connect($this->host);
		if($this->conn === false) throw new LDAPConnectionFailureException('Invalid LDAP connection settings');
		if($this->starttls) {
			if(!ldap_start_tls($this->conn)) throw new LDAPConnectionFailureException('Could not initiate TLS connection to LDAP server');
		}
		foreach($this->options as $option => $value) {
			ldap_set_option($this->conn, $option, $value);
		}
		if(!empty($this->bind_dn)) {
			if(!ldap_bind($this->conn, $this->bind_dn, $this->bind_password)) throw new LDAPConnectionFailureException('Could not bind to LDAP server');
		}
	}

	/**
	 * Decode a raw binary 16-byte guid (as given by ldap) to a readable
	 * guid string like 0d187752-3d90-4c9c-8db2-eb7003163a82
	 *
	 * @param string $encoded
	 * @return string
	 */
	private static function decode_guid(string $encoded) {
		return bin2hex(strrev(substr($encoded, 0, 4))) . '-' .
				bin2hex(strrev(substr($encoded, 4, 2))) . '-' .
				bin2hex(strrev(substr($encoded, 6, 2))) . '-' .
				bin2hex(substr($encoded, 8, 2)) . '-' .
				bin2hex(substr($encoded, 10, 6));
	}

	/**
	 * Example:
	 * Input: 0d187752-3d90-4c9c-8db2-eb7003163a82
	 * Output: \52\77\18\0d\90\3d\9c\4c\8d\b2\eb\70\03\16\3a\82
	 * This can be used for a search query, for example: ObjectGUID=\52\77...
	 *
	 * @param string $guid The input guid
	 * @return string The escaped guid usable for search queries
	 */
	public static function query_encode_guid($guid) {
		if (preg_match('/^([a-f0-9]{8})-([a-f0-9]{4})-([a-f0-9]{4})-([a-f0-9]{4})-([a-f0-9]{12})$/', $guid, $matches)) {
			$reverse_part = $matches[3] . $matches[2] . $matches[1];
			$forward_part = $matches[4] . $matches[5];
			$output_left = '';
			$output_right = '';
			for ($i=0; $i<8; $i++) {
				$output_left .= '\\' . substr($reverse_part, 14 - 2 * $i, 2);
				$output_right .= '\\' . substr($forward_part, 2 * $i, 2);
			}
			return $output_left . $output_right;
		}
		return '';
	}

	public function search($basedn, $filter, $fields = array(), $sort = array()) {
		if(is_null($this->conn)) $this->connect();
		if(empty($fields)) $r = @ldap_search($this->conn, $basedn, $filter);
		else $r = @ldap_search($this->conn, $basedn, $filter, $fields);
		$sort = array_reverse($sort);
		foreach($sort as $field) {
			@ldap_sort($this->conn, $r, $field);
		}
		if($r) {
			// Fetch entries
			$result = @ldap_get_entries($this->conn, $r);
			unset($result['count']);
			$items = array();
			foreach($result as $item) {
				unset($item['count']);
				$itemResult = array();
				foreach($item as $key => $values) {
					if(!is_int($key)) {
						if(is_array($values)) {
							unset($values['count']);
							if(count($values) == 1) $values = $values[0];
						}
						if (strtolower($key) === 'objectguid') {
							$values = self::decode_guid($values);
						}
						$itemResult[$key] = $values;
					}
				}
				$items[] = $itemResult;
			}
			return $items;
		}
		return false;
	}

	public static function escape($str = '') {
		$metaChars = array("\\00", "\\", "(", ")", "*");
		$quotedMetaChars = array();
		foreach($metaChars as $key => $value) {
			$quotedMetaChars[$key] = '\\'. dechex(ord($value));
		}
		$str = str_replace($metaChars, $quotedMetaChars, $str);
		return $str;
	}
}

class LDAPConnectionFailureException extends RuntimeException {}
