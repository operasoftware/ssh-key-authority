#!/usr/bin/php
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

chdir(__DIR__);
require('../core.php');

$users = $user_dir->list_users();
$servers = $server_dir->list_servers();

// Use 'keys-sync' user as the active user (create if it does not yet exist)
try {
	$active_user = $user_dir->get_user_by_uid('keys-sync');
} catch(UserNotFoundException $e) {
	$active_user = new User;
	$active_user->uid = 'keys-sync';
	$active_user->name = 'Synchronization script';
	$active_user->email = '';
	$active_user->active = 1;
	$active_user->admin = 1;
	$active_user->developer = 0;
	$user_dir->add_user($active_user);
}

try {
	$sysgrp = $group_dir->get_group_by_name($config['ldap']['admin_group_cn']);
} catch(GroupNotFoundException $e) {
	$sysgrp = new Group;
	$sysgrp->name = $config['ldap']['admin_group_cn'];
	$sysgrp->system = 1;
	$group_dir->add_group($sysgrp);
}
foreach($servers as $server) {
	foreach($server->list_accounts() as $account) {
		deprecate_public_keys($config, $account->list_public_keys(), $account->name . "@" . $server->hostname, $config['email']['admin_address'], $config['email']['admin_name'], $account);
	}
}
foreach($users as $user) {
	if($user->auth_realm == 'LDAP') {
		$active = $user->active;
		try {
			$user->get_details_from_ldap();
			$user->update();
			if(isset($config['ldap']['user_superior'])) {
				$user->get_superior_from_ldap();
			}
		} catch(UserNotFoundException $e) {
			$user->active = 0;
		}
		if($active && !$user->active) {
			// Check for servers that will now be admin-less
			$servers = $user->list_admined_servers();
			foreach($servers as $server) {
				$server_admins = $server->list_effective_admins();
				$total_server_admins = 0;
				foreach($server_admins as $server_admin) {
					if($server_admin->active) $total_server_admins++;
				}
				if($total_server_admins == 0) {
					if(isset($config['ldap']['user_superior'])) {
						$rcpt = $user->superior;
						while(!is_null($rcpt) && !$rcpt->active) {
							$rcpt = $rcpt->superior;
						}
					}
					$email = new Email;
					$email->subject = "Server {$server->hostname} has been orphaned";
					$email->body = "{$user->name} ({$user->uid}) was an administrator for {$server->hostname}, but they have now been marked as a former employee and there are no active administrators remaining for this server.\n\n";
					$email->body .= "Please find a replacement owner for this server and inform {$config['email']['admin_address']} ASAP, otherwise the server will be registered for decommissioning.";
					$email->add_reply_to($config['email']['admin_address'], $config['email']['admin_name']);
					if(is_null($rcpt)) {
						$email->subject .= " - NO SUPERIOR EMPLOYEE FOUND";
						$email->body .= "\n\nWARNING: No suitable superior employee could be found!";
						$email->add_recipient($config['email']['report_address'], $config['email']['report_name']);
					} else {
						$email->add_recipient($rcpt->email, $rcpt->name);
						$email->add_cc($config['email']['report_address'], $config['email']['report_name']);
					}
					$email->send();
				}
			}
		}
		if($user->admin && $user->active && !$user->member_of($sysgrp)) {
			$sysgrp->add_member($user);
		}
		if(!($user->admin && $user->active) && $user->member_of($sysgrp)) {
			$sysgrp->delete_member($user);
		}
	}
	deprecate_public_keys($config, $user->list_public_keys(), $user->uid, $user->email, $user->name, $user);
	$user->update();
}

function deprecate_public_keys($config, $public_keys, $entity_identifier, $recipient_mail, $recipient_name, $entity) {
	if($config['general']['key_expiration_enabled'] == 1) {
		foreach($public_keys as $public_key) {
			$date = $public_key->upload_date;
			$expiration_days = $config['general']['key_expiration_days'];
			$expiration_date = strtotime($date . ' + ' . $expiration_days . ' days');
			$expiration_time_in_days = round(($expiration_date - time()) / (60 * 60 * 24));
				
			if($expiration_time_in_days == 21 || $expiration_time_in_days == 14 || $expiration_time_in_days <= 7) {
				$email = new Email;
				$email->add_reply_to($config['email']['admin_address'], $config['email']['admin_name']);
				$email->add_recipient($recipient_mail, $recipient_name);
				if($expiration_time_in_days <= 0) {
					$email->subject = $entity_identifier . ": Public key removed.";
					$email->body = "Public key expired and was removed from the system.";
					$entity->delete_public_key($public_key);
				} else {
					$email->subject = $entity_identifier . ": Public key expires in " . $expiration_time_in_days . " days";
					$email->body = "Public key will expire soon. Expired keys are removed immediately and will not be usable anymore.";
				}
				$email->send();
			}
		}
	}
}
