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

if(is_numeric($router->vars['key'])) {
	try {
		$pubkey = $pubkey_dir->get_public_key_by_id($router->vars['key']);
	} catch(PublicKeyNotFoundException $e) {
		require('views/error404.php');
		die;
	}
} else {
	$pubkeys = $pubkey_dir->list_public_keys(array(), array('fingerprint' => $router->vars['key']));
	if(count($pubkeys) == 1) {
		redirect('/pubkeys/'.urlencode($pubkeys[0]->id));
	} elseif(count($pubkeys) > 1) {
		redirect('/pubkeys?fingerprint='.urlencode($router->vars['key']));
	} else {
		require('views/error404.php');
	}
	exit;
}
$dest_rules = $pubkey->list_destination_rules();
$signatures = $pubkey->list_signatures();
$user_is_owner = false;
switch(get_class($pubkey->owner)) {
case 'User':
	$title = 'Public key '.$pubkey->comment.' for '.$pubkey->owner->name;
	if($pubkey->owner->uid == $active_user->uid) {
		$user_is_owner = true;
	}
	break;
case 'ServerAccount':
	$title = 'Public key '.$pubkey->comment.' for '.$pubkey->owner->name.'@'.$pubkey->owner->server->hostname;
	if($active_user->admin_of($pubkey->owner) || $active_user->admin_of($pubkey->owner->server)) {
		$user_is_owner = true;
	}
	break;
default:
	require('views/error404.php');
	die;
}
if(isset($router->vars['format']) && $router->vars['format'] == 'txt') {
	$page = new PageSection('pubkey_txt');
	$page->set('pubkey', $pubkey);
	header('Content-type: text/plain; charset=utf-8');
	echo $page->generate();
} elseif(isset($router->vars['format']) && $router->vars['format'] == 'json') {
	$page = new PageSection('pubkey_json');
	$page->set('pubkey', $pubkey);
	header('Content-type: application/json; charset=utf-8');
	echo $page->generate();
} else {
	if(isset($_POST['add_signature']) && ($user_is_owner || $active_user->admin)) {
		$sig = new PublicKeySignature;
		$sig->signature = file_get_contents($_FILES['signature']['tmp_name']);
		$sig->public_key = $pubkey;
		try {
			$pubkey->add_signature($sig);
			redirect('#sig');
		} catch(InvalidArgumentException $e) {
			$content = new PageSection('signature_upload_fail');
			switch($e->getMessage()) {
			case "Signature doesn't validate against pubkey":
				$content->set('message', "The signature you submitted doesn't seem to validate against this public key.");
				break;
			default:
				$content->set('message', "The signature you submitted doesn't look valid.");
			}
		}
	} elseif(isset($_POST['delete_signature']) && ($user_is_owner || $active_user->admin)) {
		foreach($signatures as $sig) {
			if($sig->id == $_POST['delete_signature']) {
				$sig_to_delete = $sig;
			}
		}
		if(isset($sig_to_delete)) {
			$pubkey->delete_signature($sig_to_delete);
		}
		redirect('#sig');
	} elseif(isset($_POST['add_dest_rule']) && ($user_is_owner || $active_user->admin)) {
		$rule = new PublicKeyDestRule;
		$rule->account_name_filter = $_POST['account_name_filter'];
		$rule->hostname_filter = $_POST['hostname_filter'];
		$pubkey->add_destination_rule($rule);
		redirect('#dest');
	} elseif(isset($_POST['delete_dest_rule']) && ($user_is_owner || $active_user->admin)) {
		foreach($dest_rules as $rule) {
			if($rule->id == $_POST['delete_dest_rule']) {
				$rule_to_delete = $rule;
			}
		}
		if(isset($rule_to_delete)) {
			$pubkey->delete_destination_rule($rule_to_delete);
		}
		redirect('#dest');
	} else {
		$content = new PageSection('pubkey');
		$content->set('pubkey', $pubkey);
		$content->set('admin', $active_user->admin);
		$content->set('user_is_owner', $user_is_owner);
		$content->set('signatures', $signatures);
		$content->set('dest_rules', $dest_rules);
	}
	$head = '<link rel="alternate" type="application/json" href="'.urlencode($router->vars['key']).'.json" title="JSON for this page">'."\n";
	$head .= '<link rel="alternate" type="text/plain" href="'.urlencode($router->vars['key']).'.txt" title="TXT format for this page">'."\n";
	$page = new PageSection('base');
	$page->set('title', $title);
	$page->set('head', $head);
	$page->set('content', $content);
	$page->set('alerts', $active_user->pop_alerts());
	echo $page->generate();
}
