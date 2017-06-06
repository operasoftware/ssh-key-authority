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
	$user = $user_dir->get_user_by_uid($router->vars['username']);
} catch(UserNotFoundException $e) {
	require('views/error404.php');
	die;
}
$pubkeys = $user->list_public_keys();
if(isset($router->vars['format']) && $router->vars['format'] == 'txt') {
	$page = new PageSection('user_pubkeys_txt');
	$page->set('pubkeys', $pubkeys);
	header('Content-type: text/plain; charset=utf-8');
	echo $page->generate();
} elseif(isset($router->vars['format']) && $router->vars['format'] == 'json') {
	$page = new PageSection('user_pubkeys_json');
	$page->set('pubkeys', $pubkeys);
	header('Content-type: application/json; charset=utf-8');
	echo $page->generate();
} else {
	$content = new PageSection('user_pubkeys');
	$content->set('user', $user);
	$content->set('pubkeys', $pubkeys);
	$content->set('admin', $active_user->admin);

	$head = '<link rel="alternate" type="application/json" href="pubkeys.json" title="JSON for this page">'."\n";
	$head .= '<link rel="alternate" type="text/plain" href="pubkeys.txt" title="TXT format for this page">'."\n";

	$page = new PageSection('base');
	$page->set('title', 'Public keys for '.$user->name);
	$page->set('head', $head);
	$page->set('content', $content);
	$page->set('alerts', $active_user->pop_alerts());
	echo $page->generate();
}
