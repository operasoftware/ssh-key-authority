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

$content = new PageSection('users');
$content->set('users', $user_dir->list_users());
$content->set('admin', $active_user->admin);

$page = new PageSection('base');
$page->set('title', 'Users');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
