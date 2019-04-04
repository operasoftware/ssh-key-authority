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

if(isset($_POST['add_server']) && ($active_user->admin)) {
	$hostname = trim($_POST['hostname']);
	if(!preg_match('|.*\..*\..*|', $hostname)) {
		$content = new PageSection('invalid_hostname');
		$content->set('hostname', $hostname);
	} else {
		$admin_names = preg_split('/[\s,]+/', $_POST['admins'], null, PREG_SPLIT_NO_EMPTY);
		$admins = array();
		foreach($admin_names as $admin_name) {
			$admin_name = trim($admin_name);
			try {
				$new_admin = null;
				$new_admin = $user_dir->get_user_by_uid($admin_name);
				if(isset($new_admin)) {
					$admins[] = $new_admin;
				}
			} catch(UserNotFoundException $e) {
				try {
					$new_admin = $group_dir->get_group_by_name($admin_name);
					if(isset($new_admin)) {
						$admins[] = $new_admin;
					}
				} catch(GroupNotFoundException $e) {
					$content = new PageSection('user_not_found');
				}
			}
		}
		if(count($admins) == count($admin_names)) {
			$server = new Server;
			$server->hostname = $hostname;
			$server->port = $_POST['port'];
			try {
				$server_dir->add_server($server);
				foreach($admins as $admin) {
					$server->add_admin($admin);
				}
				$alert = new UserAlert;
				$alert->content = 'Server \'<a href="'.rrurl('/servers/'.urlencode($hostname)).'" class="alert-link">'.hesc($hostname).'</a>\' successfully created.';
				$alert->escaping = ESC_NONE;
				$active_user->add_alert($alert);
			} catch(ServerAlreadyExistsException $e) {
				$alert = new UserAlert;
				$alert->content = 'Server \'<a href="'.rrurl('/servers/'.urlencode($hostname)).'" class="alert-link">'.hesc($hostname).'</a>\' is already known by SSH Key Authority.';
				$alert->escaping = ESC_NONE;
				$alert->class = 'danger';
				$active_user->add_alert($alert);
			}
			redirect('#add');
		}
	}
} else {
	$defaults = array();
	$defaults['key_management'] = array('keys');
	$defaults['sync_status'] = array('sync success', 'sync warning', 'sync failure', 'not synced yet');
	$defaults['hostname'] = '';
	$defaults['ip_address'] = '';
	$filter = simplify_search($defaults, $_GET);
	try {
		$servers = $server_dir->list_servers(array('pending_requests', 'admins'), $filter);
	} catch(ServerSearchInvalidRegexpException $e) {
		$servers = array();
		$alert = new UserAlert;
		$alert->content = "The hostname search pattern '".$filter['hostname']."' is invalid.";
		$alert->class = 'danger';
		$active_user->add_alert($alert);
	}
	if(isset($router->vars['format']) && $router->vars['format'] == 'json') {
		$page = new PageSection('servers_json');
		$page->set('servers', $servers);
		header('Content-type: application/json; charset=utf-8');
		echo $page->generate();
		exit;
	} else {
		$content = new PageSection('servers');
		$content->set('filter', $filter);
		$content->set('admin', $active_user->admin);
		$content->set('servers', $servers);
		$content->set('all_users', $user_dir->list_users());
		$content->set('all_groups', $group_dir->list_groups());
		if(file_exists('config/keys-sync.pub')) {
			$content->set('keys-sync-pubkey', file_get_contents('config/keys-sync.pub'));
		} else {
			$content->set('keys-sync-pubkey', 'Error: keyfile missing');
		}
	}
}

$page = new PageSection('base');
$page->set('title', 'Servers');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
