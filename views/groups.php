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

if(isset($_POST['add_group']) && ($active_user->admin)) {
	// create a local group
	$name = trim($_POST['name']);
	if(preg_match('|/|', $name)) {
		$content = new PageSection('invalid_group_name');
		$content->set('group_name', $name);
	} else {
		try {
			$new_admin = $user_dir->get_user_by_uid(trim($_POST['admin_uid']));
		} catch(UserNotFoundException $e) {
			$content = new PageSection('user_not_found');
		}
		if(isset($new_admin)) {
			$group = new Group;
			$group->name = $name;
			try {
				$group_dir->add_group($group);
				$group->add_admin($new_admin);
				$alert = new UserAlert;
				$alert->content = 'Group \'<a href="'.rrurl('/groups/'.urlencode($name)).'" class="alert-link">'.hesc($name).'</a>\' successfully created.';
				$alert->escaping = ESC_NONE;
				$active_user->add_alert($alert);
			} catch(GroupAlreadyExistsException $e) {
				$alert = new UserAlert;
				$alert->content = 'Group \'<a href="'.rrurl('/groups/'.urlencode($name)).'" class="alert-link">'.hesc($name).'</a>\' already exists.';
				$alert->escaping = ESC_NONE;
				$alert->class = 'danger';
				$active_user->add_alert($alert);
			}
			redirect('#add');
		}
	}
} else if (isset($_POST['add_ldap_group']) && $active_user->admin) {
	$group_guid = $_POST['ldap_group'];
	$result = $ldap->search($config['ldap']['dn_group'], 'objectguid='.LDAP::query_encode_guid($group_guid), ['cn']);
	$alert = new UserAlert;
	if (!empty($result)) {
		try {
			$group = new Group;
			$group->name = $result[0]['cn'];
			$group->system = 1;
			$group->ldap_guid = $group_guid;
			$group_dir->add_group($group);

			$alert->content = 'Group \'<a href="'.rrurl('/groups/'.urlencode($group->name)).'" class="alert-link">'.hesc($group->name).'</a>\' successfully connected.';
			$alert->escaping = ESC_NONE;
		} catch (GroupAlreadyExistsException $e) {
			$alert->content = 'Group \'<a href="'.rrurl('/groups/'.urlencode($group->name)).'" class="alert-link">'.hesc($group->name).'</a>\' already exists.';
			$alert->escaping = ESC_NONE;
			$alert->class = 'danger';
		}
	} else {
		$alert->content = 'Could not find this group on the ldap server';
		$alert->class = 'danger';
	}
	$active_user->add_alert($alert);
	redirect('#add');
} else {
	$defaults = array();
	$defaults['active'] = array('1');
	$defaults['name'] = '';
	$filter = simplify_search($defaults, $_GET);
	try {
		$groups = $group_dir->list_groups(array('admins', 'members'), $filter);
	} catch(GroupSearchInvalidRegexpException $e) {
		$groups = array();
		$alert = new UserAlert;
		$alert->content = "The group name search pattern '".$filter['hostname']."' is invalid.";
		$alert->class = 'danger';
		$active_user->add_alert($alert);
	}
	$content = new PageSection('groups');
	$content->set('filter', $filter);
	$content->set('admin', $active_user->admin);
	$content->set('groups', $groups);
	$content->set('all_users', $user_dir->list_users());
	$content->set('all_ldap_groups', $ldap->search($config['ldap']['dn_group'], 'objectclass=group', [$config['ldap']['user_id'], 'objectguid']));
}

$page = new PageSection('base');
$page->set('title', 'Groups');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
