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
* Class for reading from the list of all *Event objects in the database.
*/
class EventDirectory extends DBDirectory {
	/**
	* List events of all types stored in the database ordered from most recent.
	* @param array $include list of extra data to include in response - currently unused
	* @param array $filter list of field/value pairs to filter results on
	* @param int|null $limit max results to return
	* @return array of *Event objects
	*/
	public function list_events($include = array(), $filter = array(), $limit = 100) {
		// WARNING: The search query is not parameterized - be sure to properly escape all input
		$fields = array(
			'server' => array("se.id", "se.server_id", "NULL as `entity_id`", "se.actor_id", "se.date", "se.details"),
			'group' => array("ee.id", "NULL AS server_id", "ee.entity_id", "ee.actor_id", "ee.date", "ee.details")
		);
		$joins = array('server' => array(), 'group' => array());
		$where = array('server' => array(), 'group' => array());
		foreach($filter as $field => $value) {
			if($value) {
				switch($field) {
				case 'admin':
					// Filter for events from servers that the user is an admin of
					$joins['server']['adminsearch'] = "INNER JOIN server_admin AS admin_search ON admin_search.server_id = se.server_id";
					$where['server'][] = "admin_search.entity_id = ".intval($value);
					// Filter for events from server accounts or groups that the user is an admin of
					// (possibly indirectly for the former as a result of being server admin)
					$joins['group']['adminsearch'] = "LEFT JOIN entity_admin AS admin_search ON admin_search.entity_id = ee.entity_id";
					$joins['group']['account'] = "LEFT JOIN server_account AS sa ON sa.entity_id = ee.entity_id";
					$joins['group']['server'] = "LEFT JOIN server AS s ON s.id = sa.server_id";
					$joins['group']['parentadminsearch'] = "LEFT JOIN server_admin AS parent_admin_search ON parent_admin_search.server_id = s.id";
					$where['group'][] = "admin_search.admin = ".intval($value)." OR parent_admin_search.entity_id = ".intval($value);
					break;
				}
			}
		}
		$stmt = $this->database->prepare("
			(SELECT ".implode(", ", $fields['server']).", 'server' AS event_type
			FROM server_event se ".implode(" ", $joins['server'])."
			".(count($where['server']) == 0 ? "" : "WHERE (".implode(") AND (", $where['server']).")")."
			GROUP BY se.id
			ORDER BY se.id DESC)
			UNION
			(SELECT ".implode(", ", $fields['group']).", e.type AS event_type
			FROM entity_event ee ".implode(" ", $joins['group'])."
			INNER JOIN entity e ON e.id = ee.entity_id
			".(count($where['group']) == 0 ? "" : "WHERE (".implode(") AND (", $where['group']).")")."
			GROUP BY ee.id
			ORDER BY ee.id DESC)
			ORDER BY `date` DESC, id DESC
			".(is_null($limit) ? '' : 'LIMIT '.intval($limit))."
		");
		$stmt->execute();
		$result = $stmt->get_result();
		$events = array();
		while($row = $result->fetch_assoc()) {
			if($row['event_type'] == 'server') {
				$events[] = new ServerEvent($row['id'], $row);
			} elseif($row['event_type'] == 'user') {
				$events[] = new UserEvent($row['id'], $row);
			} elseif($row['event_type'] == 'server account') {
				$events[] = new ServerAccountEvent($row['id'], $row);
			} elseif($row['event_type'] == 'group') {
				$events[] = new GroupEvent($row['id'], $row);
			}
		}
		$stmt->close();
		return $events;
	}
}

class EventNotFoundException extends Exception {}
