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
$json = new StdClass;
$json->servers = array();
foreach($this->get('servers') as $server) {
	$last_sync_event = $server->get_last_sync_event();
	$jsonserver = new StdClass;
	$jsonserver->uuid = $server->uuid;
	$jsonserver->hostname = $server->hostname;
	$jsonserver->key_management = $server->key_management;
	$jsonserver->sync_status = $server->sync_status;
	if($this->get('active_user')->admin ||
		(isset($config['general']['fake_viewonly_admin']) && is_array($config['general']['fake_viewonly_admin']) && in_array($this->get('active_user')->uid, $config['general']['fake_viewonly_admin'], true))
	)
		$jsonserver->admins = array();
		foreach($server->list_effective_admins() as $admin) {
			if($admin->active) {
				$jsonserver->admins[] = $admin->uid;
			}
		}
	}
	if($last_sync_event) {
		$jsonserver->last_sync_event = new StdClass;
		$jsonserver->last_sync_event->details = $last_sync_event->details;
		$jsonserver->last_sync_event->date = $last_sync_event->date;
	} else {
		$jsonserver->last_sync_event = null;
	}
	$json->servers[] = $jsonserver;
}
out(json_encode($json), ESC_NONE);
