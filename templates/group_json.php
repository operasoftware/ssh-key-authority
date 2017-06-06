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
$json->users = array();
$json->server_accounts = array();
foreach($this->get('group_members') as $member) {
	$group_member = new StdClass;
	if(get_class($member) == 'User') {
		$group_member->uid = $member->uid;
		$group_member->email = $member->email;
		$json->users[] = $group_member;
	} elseif(get_class($member) == 'ServerAccount') {
		$group_member->name = $member->name;
		$group_member->hostname = $member->server->hostname;
		$json->server_accounts[] = $group_member;
	}
}
out(json_encode($json), ESC_NONE);
