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
<?php if($this->get('admin')) { ?>
<ul class="nav nav-tabs">
	<li><a href="#list" data-toggle="tab">User list</a></li>
	<li><a href="#add" data-toggle="tab">Add user</a></li>
</ul>
<?php } ?>


<!-- Tab panes -->
<div class="tab-content">
	<div class="tab-pane fade<?php if(!$this->get('admin')) out(' in active') ?>" id="list">
		<h2 class="sr-only">User list</h2>
		<p><?php $total = count($this->get('users')); out(number_format($total).' user'.($total == 1 ? '' : 's').' found')?></p>
		<table class="table table-hover table-condensed">
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
					<td><a href="<?php outurl('/users/'.urlencode($user->uid))?>" class="user<?php if(!$user->active) out(' text-muted') ?>"><?php out($user->uid)?></a></td>
					<td><?php out($user->name)?></td>
					<td><?php out(number_format(count($user->list_public_keys())))?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

	<?php if($this->get('admin')) { ?>
	<div class="tab-pane fade" id="add">
		<h2 class="sr-only">Add user</h2>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="uid">Username</label>
				<input type="text" id="uid" name="uid" class="form-control" required>
			</div>
			<div class="form-group">
				<label for="name">Full Name</label>
				<input type="text" id="name" name="name" class="form-control" required>
			</div>
			<div class="form-group">
				<label for="email">Mail Address</label>
				<input type="email" id="email" name="email" class="form-control" required>
			</div>		
			<input type="checkbox" name="admin" value="admin">Administrator<br><br>
			<button type="submit" name="add_user" value="1" class="btn btn-primary">Add user</button>
		</form>
	</div>
	<?php } ?>
</div>
