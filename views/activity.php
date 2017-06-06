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

if(!$active_user->admin && count($active_user->list_admined_servers()) == 0 && count($active_user->list_admined_groups()) == 0) {
	require('views/error403.php');
	die;
}

$content = new PageSection('activity');
if($active_user->admin) {
	$content->set('events', $event_dir->list_events());
} else {
	$content->set('events', $active_user->list_events());
}

$page = new PageSection('base');
$page->set('title', 'Activity');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
