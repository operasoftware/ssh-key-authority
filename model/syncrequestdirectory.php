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
* Class for reading/writing to the list of SyncRequest objects in the database.
*/
class SyncRequestDirectory extends DBDirectory {
	/**
	* Store query as a prepared statement.
	*/
	private $sync_list_stmt;

	/**
	* Create the new sync request in the database.
	* @param SyncRequest $req object to add
	*/
	public function add_sync_request(SyncRequest $req) {
		$stmt = $this->database->prepare("INSERT IGNORE INTO sync_request SET server_id = ?, account_name = ?");
		$stmt->bind_param('ds', $req->server_id, $req->account_name);
		$stmt->execute();
		$req->id = $stmt->insert_id;
		$stmt->close();
	}

	/**
	* Delete the sync request from the database.
	* @param SyncRequest $req object to delete
	*/
	public function delete_sync_request(SyncRequest $req) {
		$stmt = $this->database->prepare("DELETE FROM sync_request WHERE id = ?");
		$stmt->bind_param('s', $req->id);
		$stmt->execute();
		$stmt->close();
	}

	/**
	* List the sync requests stored in the database that are not being processed yet.
	* @return array of SyncRequest objects
	*/
	public function list_pending_sync_requests() {
		if(!isset($this->sync_list_stmt)) {
			$this->sync_list_stmt = $this->database->prepare("SELECT * FROM sync_request WHERE processing = 0 ORDER BY id");
		}
		$this->sync_list_stmt->execute();
		$result = $this->sync_list_stmt->get_result();
		$reqs = array();
		while($row = $result->fetch_assoc()) {
			$reqs[] = new SyncRequest($row['id'], $row);
		}
		return $reqs;
	}
}

class SyncRequestNotFoundException extends Exception {}
