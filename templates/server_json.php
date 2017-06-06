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
$server = $this->get('server');
$last_sync_event = $this->get('last_sync_event');
$json = new StdClass;
$json->uuid = $server->uuid;
$json->hostname = $server->hostname;
$json->key_management = $server->key_management;
$json->sync_status = $server->sync_status;
if($last_sync_event) {
	$json->last_sync_event = new StdClass;
	$json->last_sync_event->details = $last_sync_event->details;
	$json->last_sync_event->date = $last_sync_event->date;
} else {
	$json->last_sync_event = null;
}
out(json_encode($json), ESC_NONE);
