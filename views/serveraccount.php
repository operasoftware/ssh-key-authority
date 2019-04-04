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
} catch(ServerNotFoundException $e) {
	require('views/error404.php');
	die;
} catch(ServerAccountNotFoundException $e) {
	require('views/error404.php');
	die;
}
$account_access = $account->list_access();
$account_access_requests = $account->list_access_requests();
$account_remote_access = $account->list_remote_access();
$account_groups = $account->list_group_membership();
$account_admins = $account->list_admins();
$pubkeys = $account->list_public_keys();
if(isset($_POST['add_access']) && ($server_admin || $account_admin || $active_user->admin)) {
	if(isset($_POST['username'])) {
		try {
			$entity = $user_dir->get_user_by_uid(trim($_POST['username']));
		} catch(UserNotFoundException $e) {
			$content = new PageSection('user_not_found');
		}
	} elseif(isset($_POST['account'])) {
		try {
			$remoteserver = $server_dir->get_server_by_hostname(trim($_POST['hostname']));
			$entity = $remoteserver->get_account_by_name(trim($_POST['account']));
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
			$account->add_access($entity, $options);
			redirect('#access');
		} else {
			$content = new PageSection('access_options');
			$content->set('entity', $account);
			$content->set('remote_entity', $entity);
			$content->set('mode', 'create');
		}
	}
} elseif(isset($_POST['delete_access']) && ($server_admin || $account_admin || $active_user->admin)) {
	foreach($account_access as $access) {
		if($access->id == $_POST['delete_access']) {
			$access_to_delete = $access;
		}
	}
	if(isset($access_to_delete)) {
		$account->delete_access($access_to_delete);
	}
	redirect('#access');
} elseif(isset($_POST['approve_access']) && ($server_admin || $account_admin || $active_user->admin)) {
	foreach($account_access_requests as $request) {
		if($request->id == $_POST['approve_access']) {
			$request_to_approve = $request;
		}
	}
	if(isset($request_to_approve)) {
		$account->approve_access_request($request_to_approve);
		redirect('#access');
	}
} elseif(isset($_POST['reject_access']) && ($server_admin || $account_admin || $active_user->admin)) {
	foreach($account_access_requests as $request) {
		if($request->id == $_POST['reject_access']) {
			$request_to_reject = $request;
		}
	}
	if(isset($request_to_reject)) {
		$sync_status = $account->sync_status;
		$account->reject_access_request($request_to_reject);
		// Check to see if account still exists
		try {
			$account = $server->get_account_by_name($router->vars['account']);
			redirect('#access');
		} catch(ServerAccountNotFoundException $e) {
			redirect('/servers/'.urlencode($server->hostname).'#accounts');
		}
	}
} elseif(isset($_POST['add_public_key']) && ($server_admin || $account_admin || $active_user->admin)) {
	try {
		$public_key = new PublicKey;
		$public_key->import($_POST['add_public_key'], null, isset($_POST['force']) && $active_user->admin);
		$account->add_public_key($public_key);
		redirect('#pubkeys');
	} catch(InvalidArgumentException $e) {
		$content = new PageSection('key_upload_fail');
		switch($e->getMessage()) {
		case 'Insufficient bits in public key':
			$content->set('message', "The public key you submitted is of insufficient strength; it must be at least 4096 bits.");
			break;
		default:
			$content->set('message', "The public key you submitted doesn't look valid.");
		}
	} catch(PublicKeyAlreadyKnownException $e) {
		$content = new PageSection('key_upload_fail');
		$content->set('message', "The public key you submitted is already in use. Please create a new one.");
	}
} elseif(isset($_POST['delete_public_key']) && ($server_admin || $account_admin || $active_user->admin)) {
	foreach($pubkeys as $pubkey) {
		if($pubkey->id == $_POST['delete_public_key']) {
			$key_to_delete = $pubkey;
		}
	}
	if(isset($key_to_delete)) {
		$account->delete_public_key($key_to_delete);
	}
	redirect('#pubkeys');
} elseif(isset($_POST['add_admin']) && ($server_admin || $active_user->admin)) {
	try {
		$user = $user_dir->get_user_by_uid($_POST['user_name']);
	} catch(UserNotFoundException $e) {
		$content = new PageSection('user_not_found');
	}
	if(isset($user)) {
		$account->add_admin($user);
		redirect('#admins');
	}
} elseif(isset($_POST['delete_admin']) && ($server_admin || $active_user->admin)) {
	foreach($account_admins as $admin) {
		if($admin->id == $_POST['delete_admin']) {
			$admin_to_delete = $admin;
		}
	}
	if(isset($admin_to_delete)) {
		$account->delete_admin($admin_to_delete);
	}
	redirect('#admins');
} else {
	$content = new PageSection('serveraccount');
	$content->set('server', $server);
	$content->set('account', $account);
	$content->set('access', $account_access);
	$content->set('access_requests', $account_access_requests);
	$content->set('pubkeys', $pubkeys);
	$content->set('remote_access', $account_remote_access);
	$content->set('group_membership', $account_groups);
	$content->set('admins', $account_admins);
	$content->set('admin', $active_user->admin);
	$content->set('log', $account->get_log());
	$content->set('server_admin', $server_admin);
	$content->set('all_users', $user_dir->list_users());
	$content->set('all_servers', $server_dir->list_servers());
	$content->set('all_groups', $group_dir->list_groups());
}

$page = new PageSection('base');
$page->set('title', $account->name.'@'.$server->hostname);
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
