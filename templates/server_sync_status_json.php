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
$sync_status = $this->get('sync_status');
$last_sync = $this->get('last_sync');
$pending = $this->get('pending');
$accounts = $this->get('accounts');
$json = new StdClass;
$json->sync_status = $sync_status;
if(is_null($last_sync)) {
	$json->last_sync = null;
} else {
	$json->last_sync = new StdClass;
	$json->last_sync->date = $last_sync->date;
	$json->last_sync->details = json_decode($last_sync->details)->value;
}
$json->accounts = array();
foreach($accounts as $account) {
	$jsa = new StdClass;
	$jsa->name = $account->name;
	$jsa->sync_status = $account->sync_status;
	$jsa->pending = $account->sync_is_pending();
	$json->accounts[] = $jsa;
}
$json->pending = $pending;
out(json_encode($json), ESC_NONE);
