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

if(isset($router->vars['hostname'])) {
	try {
		$server = $server_dir->get_server_by_hostname($router->vars['hostname']);
		$server_admin = $active_user->admin_of($server);
		$account_admin = false;
		if(!$server_admin && !$active_user->admin) {
			try {
				$account = $server->get_account_by_name($router->vars['account']);
				$account_admin = $active_user->admin_of($account);
			} catch(ServerAccountNotFoundException $e) {
			}
			if(!$account_admin) {
				require('views/error403.php');
				die;
			}
		} else {
			$account = $server->get_account_by_name($router->vars['account']);
		}
		$access = $account->get_access_by_id($router->vars['access']);
		$entity = $account;
	} catch(ServerNotFoundException $e) {
		require('views/error404.php');
		die;
	} catch(ServerAccountNotFoundException $e) {
		require('views/error404.php');
		die;
	} catch(AccessNotFoundException $e) {
		require('views/error404.php');
		die;
	}
} elseif(isset($router->vars['group'])) {
	try {
		$group = $group_dir->get_group_by_name($router->vars['group']);
		$group_admin = $active_user->admin_of($group);
		$access = $group->get_access_by_id($router->vars['access']);
		$entity = $group;
	} catch(GroupNotFoundException $e) {
		require('views/error404.php');
		die;
	} catch(AccessNotFoundException $e) {
		require('views/error404.php');
		die;
	}
} else {
	require('views/error404.php');
	die;
}
if(isset($_POST['update_access'])) {
	$options = array();
	if(isset($_POST['access_option'])) {
		foreach($_POST['access_option'] as $k => $v) {
			if($v['enabled']) {
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
	$access->update_options($options);
	if(isset($server)) {
		redirect('/servers/'.urlencode($router->vars['hostname']).'/accounts/'.urlencode($router->vars['account']).'#access');
	} elseif(isset($group)) {
		redirect('/groups/'.urlencode($router->vars['group']).'#access');
	}
} else {
	$content = new PageSection('access_options');
	$content->set('entity', $entity);
	$content->set('options', $access->list_options());
	$content->set('admin', $active_user->admin);
	$content->set('remote_entity', $access->source_entity);
	$content->set('mode', 'edit');
}

$page = new PageSection('base');
if(isset($server)) {
	$page->set('title', $account->name.'@'.$server->hostname);
} elseif(isset($group)) {
	$page->set('title', $group->name);
}
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
