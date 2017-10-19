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

$content = new PageSection('help');
if(file_exists('config/keys-sync.pub')) {
	$content->set('keys-sync-pubkey', file_get_contents('config/keys-sync.pub'));
} else {
	$content->set('keys-sync-pubkey', 'Error: keyfile missing');
}
$content->set('admin_mail', $config['email']['admin_address']);
$content->set('baseurl', $config['web']['baseurl']);
$content->set('security_config', isset($config['security']) ? $config['security'] : array());

$page = new PageSection('base');
$page->set('title', 'Help');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
