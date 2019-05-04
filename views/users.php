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

if(isset($_POST['add_user']) && $active_user->admin) {
    $uid = trim($_POST['uid']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    $user = new User;
    $user->uid = $uid;
    $user->name = $name;
    $user->email = $email;
    
    $user->active = 1;
    if (isset($_POST['admin']) && $_POST['admin'] === 'admin') {
        $user->admin = 1;
    } else {
        $user->admin = 0;
    }
    $user->auth_realm = 'local';

    try {
        $user_dir->add_user($user);
        $alert = new UserAlert;
        $alert->content = 'User \'<a href="'.rrurl('/users/'.urlencode($user->uid)).'" class="alert-link">'.hesc($user->uid).'</a>\' successfully created.';
        $alert->escaping = ESC_NONE;
        $active_user->add_alert($alert);
    } catch(UserAlreadyExistsException $e) {
        $alert = new UserAlert;
        $alert->content = 'User \'<a href="'.rrurl('/users/'.urlencode($user->uid)).'" class="alert-link">'.hesc($user->uid).'</a>\' is already known by SSH Key Authority.';
        $alert->escaping = ESC_NONE;
        $alert->class = 'danger';
        $active_user->add_alert($alert);
    }
    redirect('#add');
} else {
    $content = new PageSection('users');
    $content->set('users', $user_dir->list_users());
    $content->set('admin', $active_user->admin);
}
    
$page = new PageSection('base');
$page->set('title', 'Users');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());
echo $page->generate();
