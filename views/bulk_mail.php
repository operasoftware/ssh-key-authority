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

if(!$active_user->admin) {
	require('views/error403.php');
	die;
}

if(!empty($_POST['subject']) && !empty($_POST['body']) && !empty($router->vars['recipients'])) {
	$email = new Email;
	$email->subject = $_POST['subject'];
	$email->body = $_POST['body'];
	$email->add_reply_to($config['email']['admin_address'], $config['email']['admin_name']);
	$email->add_recipient('noreply', 'Undisclosed recipients');
	$filters = array();
	if($router->vars['recipients'] == 'server_admins') {
		$filters['admins_servers'] = 1;
	}
	foreach($user_dir->list_users(array(), $filters) as $user) {
		if($user->active) {
			$email->add_bcc($user->email, $user->name);
		}
	}
	$email->send();
	$alert = new UserAlert;
	$alert->content = "Mail sent!";
	$active_user->add_alert($alert);
	redirect();
} elseif(empty($router->vars['recipients'])) {
	$content = new PageSection('bulk_mail_choose');
} else {
	switch($router->vars['recipients']) {
	case 'all_users':
		$rcpt_desc = 'users of';
		$rcpt_role = 'user of';
		break;
	case 'server_admins':
		$rcpt_desc = 'server admins on';
		$rcpt_role = 'server admin on';
		break;
	default:
		require('views/error404.php');
		die;
	}
	$content = new PageSection('bulk_mail');
	$content->set('recipients', $router->vars['recipients']);
	$content->set('rcpt_desc', $rcpt_desc);
	$content->set('rcpt_role', $rcpt_role);
}

$page = new PageSection('base');
$page->set('title', 'Bulk mail');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
