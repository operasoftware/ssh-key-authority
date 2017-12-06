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

try {
	$user = $user_dir->get_user_by_uid($router->vars['username']);
} catch(UserNotFoundException $e) {
	require('views/error404.php');
	die;
}
$access = $user->list_remote_access();
$admined_servers = $user->list_admined_servers(array('pending_requests'));
$admined_groups = $user->list_admined_groups(array('members', 'admins'));
$groups = $user->list_group_memberships(array('members', 'admins'));
usort($admined_servers, function($a, $b) {return strnatcasecmp($a->hostname, $b->hostname);});

if(isset($_POST['reassign_servers']) && is_array($_POST['servers']) && $active_user->admin) {
	try {
		$new_admin = $user_dir->get_user_by_uid($_POST['reassign_to']);
	} catch(UserNotFoundException $e) {
		try {
			$new_admin = $group_dir->get_group_by_name($_POST['reassign_to']);
		} catch(GroupNotFoundException $e) {
			$content = new PageSection('user_not_found');
		}
	}
	if(isset($new_admin)) {
		foreach($admined_servers as $server) {
			if(in_array($server->hostname, $_POST['servers'])) {
				$server->add_admin($new_admin);
				$server->delete_admin($user);
			}
		}
		redirect('#details');
	}
} elseif(isset($_POST['edit_user']) && $active_user->admin) {
	$user->force_disable = $_POST['force_disable'];
	$user->get_details_from_ldap();
	$user->update();
	redirect('#settings');
} else {
	$content = new PageSection('user');
	$content->set('user', $user);
	$content->set('user_access', $access);
	$content->set('user_admined_servers', $admined_servers);
	$content->set('user_admined_groups', $admined_groups);
	$content->set('user_groups', $groups);
	$content->set('user_keys', $user->list_public_keys());
	$content->set('admin', $active_user->admin);
}

$page = new PageSection('base');
$page->set('title', $user->name);
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
