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

$public_keys = $active_user->list_public_keys();
$admined_servers = $active_user->list_admined_servers(array('pending_requests', 'admins'));

if(isset($_POST['add_public_key'])) {
	try {
		$public_key = new PublicKey;
		$public_key->import($_POST['add_public_key'], $active_user->uid);
		$active_user->add_public_key($public_key);
		redirect();
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
} elseif(isset($_POST['delete_public_key'])) {
	foreach($public_keys as $public_key) {
		if($public_key->id == $_POST['delete_public_key']) {
			$key_to_delete = $public_key;
		}
	}
	if(isset($key_to_delete)) {
		$active_user->delete_public_key($key_to_delete);
	}
	redirect();
} else {
	$content = new PageSection('home');
	$content->set('user_keys', $public_keys);
	$content->set('admined_servers', $admined_servers);
	$content->set('uid', $active_user->uid);
}

$page = new PageSection('base');
$page->set('title', 'Keys management');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
