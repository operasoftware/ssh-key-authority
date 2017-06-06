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
?>
<h1>Users</h1>
<table class="table">
	<thead>
		<tr>
			<th>Username</th>
			<th>Full name</th>
			<th>Public keys</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($this->get('users') as $user) { ?>
		<tr<?php if(!$user->active) out(' class="text-muted"', ESC_NONE) ?>>
			<td><a href="/users/<?php out($user->uid, ESC_URL)?>" class="user<?php if(!$user->active) out(' text-muted') ?>"><?php out($user->uid)?></a></td>
			<td><?php out($user->name)?></td>
			<td><?php out(number_format(count($user->list_public_keys())))?></td>
		</tr>
		<?php } ?>
	</tbody>
</table>
