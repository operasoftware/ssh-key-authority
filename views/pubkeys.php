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

$defaults = array();
$defaults['fingerprint'] = '';
$defaults['type'] = '';
$defaults['keysize-min'] = '';
$defaults['keysize-max'] = '';
$filter = simplify_search($defaults, $_GET);
$pubkeys = $pubkey_dir->list_public_keys(array(), $filter);

if(isset($router->vars['format']) && $router->vars['format'] == 'json') {
	$page = new PageSection('pubkeys_json');
	$page->set('pubkeys', $pubkeys);
	header('Content-type: text/plain; charset=utf-8');
	echo $page->generate();
} else {
	$content = new PageSection('pubkeys');
	$content->set('filter', $filter);
	$content->set('pubkeys', $pubkeys);
	$content->set('admin', $active_user->admin);

	$page = new PageSection('base');
	$page->set('title', 'Public keys');
	$page->set('content', $content);
	$page->set('alerts', $active_user->pop_alerts());
	echo $page->generate();
}
