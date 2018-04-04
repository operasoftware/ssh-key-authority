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
} catch(ServerNotFoundException $e) {
	try {
		$server = $server_dir->get_server_by_uuid($router->vars['hostname']);
		redirect('/servers/'.urlencode($server->hostname));
	} catch(ServerNotFoundException $e) {
		require('views/error404.php');
		die;
	}
}
$all_users = $user_dir->list_users();
$server_admins = $server->list_admins();
$server_accounts = $server->list_accounts();
$admined_accounts = $server->list_accounts(array(), array('admin' => $active_user->entity_id));
$server_admin = $active_user->admin_of($server);
$all_groups = $group_dir->list_groups();
$all_servers = $active_user->list_admined_servers();
$all_accounts = $server->list_accounts();
$ldap_access_options = $server->list_ldap_access_options();
$server_admin_can_reset_host_key = (isset($config['security']) && isset($config['security']['host_key_reset_restriction']) && $config['security']['host_key_reset_restriction'] == 0);

if(isset($_POST['sync']) && ($server_admin || $active_user->admin)) {
	$server->sync_access();
	redirect();
} elseif(isset($_POST['add_admin']) && ($active_user->admin)) {
	try {
		$entity = $user_dir->get_user_by_uid($_POST['user_name']);
	} catch(UserNotFoundException $e) {
		try {
			$entity = $group_dir->get_group_by_name($_POST['user_name']);
		} catch(GroupNotFoundException $e) {
			$content = new PageSection('user_not_found');
		}
	}
	if(isset($entity)) {
		$server->add_admin($entity);
		redirect('#admins');
	}
} elseif(isset($_POST['delete_admin']) && ($active_user->admin)) {
	foreach($server_admins as $admin) {
		if($admin->id == $_POST['delete_admin']) {
			$admin_to_delete = $admin;
		}
	}
	if(isset($admin_to_delete)) {
		$server->delete_admin($admin_to_delete);
	}
	redirect('#admins');
} elseif(isset($_POST['add_account']) && ($server_admin || $active_user->admin)) {
	$account = new ServerAccount();
	$account->name = trim($_POST['account_name']);
	try {
		$server->add_account($account);
	} catch(AccountNameInvalid $e) {
		$alert = new UserAlert;
		$alert->content = $e->getMessage();
		$alert->class = 'danger';
		$active_user->add_alert($alert);
	}
	redirect('#accounts');
} elseif(isset($_POST['delete_account']) && ($server_admin || $active_user->admin)) {
	foreach($server_accounts as $account) {
		if($account->id == $_POST['delete_account']) {
			$account_to_delete = $account;
		}
	}
	if(isset($account_to_delete)) {
		$account_to_delete->active = 0;
		$account_to_delete->update();
	}
	redirect('#accounts');
} elseif(isset($_POST['edit_server']) && $active_user->admin) {
	$hostname = trim($_POST['hostname']);
	if(!preg_match('|.*\..*\..*|', $hostname)) {
		$content = new PageSection('invalid_hostname');
		$content->set('hostname', $hostname);
	} else {
		$options = array();
		if(isset($_POST['access_option'])) {
			foreach($_POST['access_option'] as $k => $v) {
				if($v['enabled']) {
					$option = new ServerLDAPAccessOption();
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
		$server->update_ldap_access_options($options);
		$server->hostname = $hostname;
		if($_POST['rsa_key_fingerprint'] == '') $server->rsa_key_fingerprint = null;
		$server->key_management = $_POST['key_management'];
		$server->authorization = $_POST['authorization'];
		try {
			$server->update();
			$alert = new UserAlert;
			$alert->content = "Settings saved.";
			$active_user->add_alert($alert);
			redirect('/servers/'.urlencode($hostname).'#settings'); // Must specify, since the hostname may have changed
		} catch(UniqueKeyViolationException $e) {
			$content = new PageSection('unique_key_violation');
			$content->set('exception', $e);
		}
	}
} elseif(isset($_POST['edit_server']) && $server_admin && $server_admin_can_reset_host_key) {
	if($_POST['rsa_key_fingerprint'] == '') $server->rsa_key_fingerprint = null;
	$server->update();
	redirect('#settings');
} elseif(isset($_POST['request_access'])) {
	// Where we are requesting access FROM
	switch($_POST['request_access']) {
	case 'user':
		$from = $active_user;
		$from_description = '';
		break;
	case 'server_account':
		try {
			$server_remote = $server_dir->get_server_by_hostname($_POST['hostname_remote']);
			$from = $server_remote->get_account_by_name($_POST['account_remote']);
			$from_description = " from {$from->name}@{$server_remote->hostname}";
		} catch(ServerNotFoundException $e) {
			$content = new PageSection('server_not_found');
		} catch(ServerAccountNotFoundException $e) {
			$content = new PageSection('server_account_not_found');
		}
		break;
	case 'group':
		try {
			$from = $group_dir->get_group_by_name($_POST['group_account']);
			$from_description = " from group: {$from->name}";
		} catch(GroupNotFoundException $e) {
			$content = new PageSection('group_not_found');
		}
		break;
	default:
		throw new Exception("Unrecognized access request type: {$_POST['request_access']}");
	}
	// Where we are requesting access TO
	$account_name = trim($_POST['account_name']);
	try {
		$account = $server->get_account_by_name($account_name);
	} catch(ServerAccountNotFoundException $e) {
		$account = new ServerAccount;
		$account->name = trim($account_name);
		$account->sync_status = 'proposed';
		try {
			$server->add_account($account);
		} catch(AccountNameInvalid $e) {
			$alert = new UserAlert;
			$alert->content = $e->getMessage();
			$alert->class = 'danger';
			$active_user->add_alert($alert);
			redirect();
		}
	}
	// Add access request if we found everything
	if(isset($from) && isset($account)) {
		$account->add_access_request($from);

		$alert = new UserAlert;
		$alert->content = "Access requested to {$account->name}@{$server->hostname}{$from_description}.";
		$active_user->add_alert($alert);
		redirect();
	}
} elseif(isset($_POST['add_note']) && $active_user->admin) {
	$note = new ServerNote();
	$note->note = $_POST['note'];
	$server->add_note($note);
	redirect('#notes');
} elseif(isset($_POST['delete_note']) && $active_user->admin) {
	$note = $server->get_note_by_id($_POST['delete_note']);
	$server->delete_note($note);
	redirect('#notes');
} elseif(isset($_POST['send_mail']) && !empty($_POST['subject']) && !empty($_POST['body']) && !empty($_POST['recipients'])) {
	$email = new Email;
	$email->subject = $_POST['subject'];
	$email->body = $_POST['body'];
	if($_POST['anonymous']) {
		$email->add_reply_to($config['email']['admin_address'], $config['email']['admin_name']);
	} else {
		$email->set_from($active_user->email, $active_user->name);
	}
	$hide_recipients = isset($_POST['hide_recipients']);
	if($hide_recipients) {
		$email->add_recipient('noreply', 'Undisclosed recipients');
	}
	$effective_server_admins = $server->list_effective_admins();
	switch($_POST['recipients']) {
	case 'admins':
		foreach($effective_server_admins as $user) {
			if($user->active) {
				if($hide_recipients) {
					$email->add_bcc($user->email, $user->name);
				} else {
					$email->add_recipient($user->email, $user->name);
				}
			}
		}
		break;
	case 'root_users':
		try {
			$account = $server->get_account_by_name('root');
		} catch(ServerAccountNotFoundException $e) {
			$alert = new UserAlert;
			$alert->content = "Could not send mail:  root account does not exist on this server.";
			$alert->class = 'danger';
			$active_user->add_alert($alert);
			redirect();
		}
		foreach($account->list_access() as $access) {
			$entity = $access->source_entity;
			if(get_class($entity) == 'User' && $entity->active) {
				if($hide_recipients) {
					$email->add_bcc($entity->email, $entity->name);
				} else {
					$email->add_recipient($entity->email, $entity->name);
				}
			}
		}
		break;
	case 'users':
		$users = array();
		foreach($server_accounts as $account) {
			foreach($account->list_access() as $access) {
				$entity = $access->source_entity;
				if(get_class($entity) == 'User' && $entity->active) {
					$users[$entity->id] = $entity;
				}
			}
		}
		foreach($users as $user) {
			if($hide_recipients) {
				$email->add_bcc($user->email, $user->name);
			} else {
				$email->add_recipient($user->email, $user->name);
			}
		}
		break;
	}
	$email->send();
	$alert = new UserAlert;
	$alert->content = "Mail sent!";
	$active_user->add_alert($alert);
	redirect('#contact');
} else {
	if(isset($router->vars['format']) && $router->vars['format'] == 'json') {
		$page = new PageSection('server_json');
		$page->set('server', $server);
		$page->set('last_sync_event', $server->get_last_sync_event());
		header('Content-type: application/json; charset=utf-8');
		echo $page->generate();
		exit;
	} else {
		$access_accounts = array();
		foreach($server_accounts as $account) {
			if($active_user->has_access($account)) $access_accounts[] = $account->name;
		}
		$content = new PageSection('server');
		$content->set('server', $server);
		$content->set('admin', $active_user->admin);
		$content->set('access_accounts', $access_accounts);
		$content->set('server_admin', $server_admin);
		$content->set('server_admins', $server_admins);
		$content->set('server_accounts', $server_accounts);
		$content->set('server_log', $server->get_log_including_accounts());
		$content->set('server_notes', $server->list_notes());
		$content->set('admined_accounts', $admined_accounts);
		$content->set('all_users', $all_users);
		$content->set('last_sync', $server->get_last_sync_event());
		$content->set('sync_requests', $server->list_sync_requests());
		$content->set('matching_servers_by_ip', $server_dir->list_servers(array(), array('ip_address' => $server->ip_address, 'key_management' => array('keys'))));
		$content->set('matching_servers_by_host_key', $server_dir->list_servers(array(), array('rsa_key_fingerprint' => $server->rsa_key_fingerprint, 'key_management' => array('keys'))));
		$content->set('all_groups', $all_groups);
		$content->set('all_servers', $all_servers);
		$content->set('all_accounts', $all_accounts);
		$content->set('ldap_access_options', $ldap_access_options);
		$content->set('output_formatter', $output_formatter);
		$content->set('email_config', $config['email']);
		$content->set('inventory_config', $config['inventory']);
		$content->set('default_accounts', isset($config['defaults']['account_groups']) ? $config['defaults']['account_groups'] : array());
		$content->set('server_admin_can_reset_host_key', $server_admin_can_reset_host_key);
		switch($server->sync_status) {
		case 'sync success': $content->set('sync_class', 'success'); break;
		case 'sync warning': $content->set('sync_class', 'warning'); break;
		case 'sync failure': $content->set('sync_class', 'danger'); break;
		}
	}
}

$page = new PageSection('base');
$page->set('title', $server->hostname);
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
