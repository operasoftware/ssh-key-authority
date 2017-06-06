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
	$group = $group_dir->get_group_by_name($router->vars['group']);
} catch(GroupNotFoundException $e) {
	require('views/error404.php');
	die;
}
$all_users = $user_dir->list_users();
$all_groups = $group_dir->list_groups();
$all_servers = $server_dir->list_servers();
$admined_servers = $active_user->list_admined_servers();
$group_members = $group->list_members();
$group_access = $group->list_access();
$group_remote_access = $group->list_remote_access();
$group_admins = $group->list_admins();
$group_admin = $active_user->admin_of($group);

if(isset($_POST['add_admin']) && ($active_user->admin)) {
	try {
		$user = $user_dir->get_user_by_uid($_POST['user_name']);
	} catch(UserNotFoundException $e) {
		$content = new PageSection('user_not_found');
	}
	if(isset($user)) {
		$group->add_admin($user);
		redirect('#admins');
	}
} elseif(isset($_POST['delete_admin']) && ($active_user->admin)) {
	foreach($group_admins as $admin) {
		if($admin->id == $_POST['delete_admin']) {
			$admin_to_delete = $admin;
		}
	}
	if(isset($admin_to_delete)) {
		$group->delete_admin($admin_to_delete);
	}
	redirect('#admins');
} elseif(isset($_POST['add_member']) && ($group_admin || $active_user->admin)) {
	if(isset($_POST['username'])) {
		try {
			$entity = $user_dir->get_user_by_uid(trim($_POST['username']));
		} catch(UserNotFoundException $e) {
			$content = new PageSection('user_not_found');
		}
	} elseif(isset($_POST['account'])) {
		try {
			$server = $server_dir->get_server_by_hostname(trim($_POST['hostname']));
			$entity = $server->get_account_by_name(trim($_POST['account']));
		} catch(ServerNotFoundException $e) {
			$content = new PageSection('server_not_found');
		} catch(ServerAccountNotFoundException $e) {
			$content = new PageSection('server_account_not_found');
		}
	}
	if(isset($entity) && !$group->system) {
		try {
			$group->add_member($entity);
			redirect('#members');
		} catch(InvalidArgumentException $e) {
			$content = new PageSection('not_admin');
		}
	}
} elseif(isset($_POST['delete_member']) && ($group_admin || $active_user->admin)) {
	foreach($group_members as $member) {
		if($member->entity_id == $_POST['delete_member']) {
			$member_to_delete = $member;
		}
	}
	if(isset($member_to_delete) && !$group->system) {
		$group->delete_member($member_to_delete);
	}
	redirect('#members');
} elseif(isset($_POST['add_access']) && ($group_admin || $active_user->admin)) {
	if(isset($_POST['username'])) {
		try {
			$entity = $user_dir->get_user_by_uid(trim($_POST['username']));
		} catch(UserNotFoundException $e) {
			$content = new PageSection('user_not_found');
		}
	} elseif(isset($_POST['account'])) {
		try {
			$server = $server_dir->get_server_by_hostname(trim($_POST['hostname']));
			$entity = $server->get_account_by_name(trim($_POST['account']));
		} catch(ServerNotFoundException $e) {
			$content = new PageSection('server_not_found');
		} catch(ServerAccountNotFoundException $e) {
			$content = new PageSection('server_account_not_found');
		}
	} elseif(isset($_POST['group'])) {
		try {
			$entity = $group_dir->get_group_by_name(trim($_POST['group']));
		} catch(GroupNotFoundException $e) {
			$content = new PageSection('group_not_found');
		}
	}
	if(isset($entity)) {
		if($_POST['add_access'] == '2') {
			$options = array();
			if(isset($_POST['access_option'])) {
				foreach($_POST['access_option'] as $k => $v) {
					if(isset($v['enabled'])) {
						$option = new AccessOption();
						$option->option = $k;
						if(isset($v['value'])) {
							$option->value = $v['value'];
						} else {
							$option->value = null;
						}
						$options[] = $option;
					}
				}
			}
			$group->add_access($entity, $options);
			redirect('#access');
		} else {
			$content = new PageSection('access_options');
			$content->set('entity', $group);
			$content->set('remote_entity', $entity);
			$content->set('mode', 'create');
		}
	}
} elseif(isset($_POST['delete_access']) && ($group_admin || $active_user->admin)) {
	foreach($group_access as $access) {
		if($access->id == $_POST['delete_access']) {
			$access_to_delete = $access;
		}
	}
	if(isset($access_to_delete)) {
		$group->delete_access($access_to_delete);
	}
	redirect('#access');
} elseif(isset($_POST['edit_group']) && ($active_user->admin)) {
	$name = trim($_POST['name']);
	$group->name = $name;
	$group->active = $_POST['active'];
	try {
		$group->update();
		$alert = new UserAlert;
		$alert->content = "Settings saved.";
		$active_user->add_alert($alert);
		redirect('/groups/'.urlencode($name).'#settings'); // Must specify, since the name may have changed
	} catch(UniqueKeyViolationException $e) {
		$content = new PageSection('unique_key_violation');
		$content->set('exception', $e);
	}
} else {
	if(isset($router->vars['format']) && $router->vars['format'] == 'json') {
		$page = new PageSection('group_json');
		$page->set('group_members', $group_members);
		header('Content-type: application/json; charset=utf-8');
		echo $page->generate();
		exit;
	} else {
		$content = new PageSection('group');
		$content->set('group', $group);
		$content->set('admin', $active_user->admin);
		$content->set('group_admin', $group_admin);
		$content->set('group_admins', $group_admins);
		$content->set('group_members', $group_members);
		$content->set('group_access', $group_access);
		$content->set('group_remote_access', $group_remote_access);
		$content->set('group_log', $group->get_log());
		$content->set('all_users', $all_users);
		$content->set('all_groups', $all_groups);
		$content->set('all_servers', $all_servers);
		$content->set('admined_servers', $admined_servers);
	}
}

$page = new PageSection('base');
$page->set('title', $group->name);
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
