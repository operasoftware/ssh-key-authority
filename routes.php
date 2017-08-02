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

$routes = array(
	'/' => 'home',
	'/activity' => 'activity',
	'/bulk_mail' => 'bulk_mail',
	'/bulk_mail/{recipients}' => 'bulk_mail',
	'/groups' => 'groups',
	'/groups/{group}' => 'group',
	'/groups/{group}/members.{format}' => 'group',
	'/groups/{group}/access_rules/{access}' => 'access_options',
	'/help' => 'help',
	'/pubkeys' => 'pubkeys',
	'/pubkeys.{format}' => 'pubkeys',
	'/pubkeys/{key}' => 'pubkey',
	'/pubkeys/{key}.{format}' => 'pubkey',
	'/servers' => 'servers',
	'/servers.{format}' => 'servers',
	'/servers/{hostname}' => 'server',
	'/servers/{hostname}/accounts/{account}' => 'serveraccount',
	'/servers/{hostname}/accounts/{account}/access_rules/{access}' => 'access_options',
	'/servers/{hostname}/accounts/{account}/pubkeys.{format}' => 'serveraccount_pubkeys',
	'/servers/{hostname}/accounts/{account}/sync_status' => 'serveraccount_sync_status',
	'/servers/{hostname}/status.{format}' => 'server',
	'/servers/{hostname}/sync_status' => 'server_sync_status',
	'/tools' => 'tools',
	'/users' => 'users',
	'/users/{username}' => 'user',
	'/users/{username}/pubkeys' => 'user_pubkeys',
	'/users/{username}/pubkeys.{format}' => 'user_pubkeys',
	'/users/{username}/pubkeys/{key}' => 'pubkey',
	'/users/{username}/pubkeys/{key}.{format}' => 'pubkey',
);

$public_routes = array(
	'/groups/{group}/members.{format}' => true,
	'/pubkeys/{key}.{format}' => true,
	'/users/{username}' => true,
	'/users/{username}/pubkeys.{format}' => true,
	'/users/{username}/pubkeys/{key}.{format}' => true,
);
