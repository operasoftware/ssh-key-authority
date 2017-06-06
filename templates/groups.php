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
<h1>Groups</h1>
<?php if($this->get('admin')) { ?>
<ul class="nav nav-tabs">
	<li><a href="#list" data-toggle="tab">Group list</a></li>
	<li><a href="#add" data-toggle="tab">Add group</a></li>
</ul>
<?php } ?>

<!-- Tab panes -->
<div class="tab-content">
	<div class="tab-pane fade<?php if(!$this->get('admin')) out(' in active') ?>" id="list">
		<h2 class="sr-only">Group list</h2>
		<div class="panel-group">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">
						Filter options
					</h3>
				</div>
					<div class="panel-body">
					<form>
						<div class="row">
							<div class="col-sm-4">
								<div class="form-group">
									<label for="name-search">Name (<a href="https://mariadb.com/kb/en/mariadb/regular-expressions-overview/">regexp</a>)</label>
									<input type="text" id="name-search" name="name" class="form-control" value="<?php out($this->get('filter')['name'])?>" autofocus>
								</div>
							</div>
							<div class="col-sm-3">
								<h4>Status</h4>
								<?php
								$options = array();
								$options['1'] = 'Active';
								$options['0'] = 'Inactive';
								foreach($options as $value => $label) {
									$checked = in_array($value, $this->get('filter')['active']) ? ' checked' : '';
								?>
								<div class="checkbox"><label><input type="checkbox" name="active[]" value="<?php out($value)?>"<?php out($checked) ?>> <?php out($label) ?></label></div>
								<?php } ?>
							</div>
						</div>
						<button type="submit" class="btn btn-primary">Display results</button>
					</form>
				</div>
			</div>
		</div>
		<?php if(count($this->get('groups')) == 0) { ?>
		<p>No groups found.</p>
		<?php } else { ?>
		<form method="post" action="<?php out($this->data->relative_request_url)?>" class="form-inline">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table table-striped">
				<thead>
					<tr>
						<th>Group</th>
						<th>Members</th>
						<th>Admins</th>
						<?php if($this->get('admin')) { ?>
						<th>Actions</th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->get('groups') as $group) { ?>
					<tr<?php if(!$group->active) out(' class="text-muted"', ESC_NONE) ?>>
						<td><a href="/groups/<?php out($group->name, ESC_URL) ?>" class="group<?php if(!$group->active) out(' text-muted') ?>"><?php out($group->name) ?></a></td>
						<td><?php out(number_format($group->member_count))?></td>
						<td><?php out($group->admins)?></td>
						<?php if($this->get('admin')) { ?>
						<td>
							<a href="<?php out($this->data->relative_request_url.'/'.urlencode($group->name))?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-cog"></span> Manage group</a>
						</td>
						<?php } ?>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</form>
		<?php } ?>
	</div>
	<?php if($this->get('admin')) { ?>
	<div class="tab-pane fade" id="add">
		<h2 class="sr-only">Add group</h2>
		<form method="post" action="<?php out($this->data->relative_request_url)?>" class="form-inline">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="name" class="sr-only">Group name</label>
				<input type="text" id="name" name="name" class="form-control" placeholder="Group name" required>
			</div>
			<div class="form-group">
				<label for="admin_uid" class="sr-only">Administrator</label>
				<input type="text" size="40" id="admin_uid" name="admin_uid" class="form-control" placeholder="Administrator" required list="userlist">
				<datalist id="userlist">
					<?php foreach($this->get('all_users') as $user) { ?>
					<option value="<?php out($user->uid)?>" label="<?php out($user->name)?>">
					<?php } ?>
				</datalist>
			</div>
			<button type="submit" name="add_group" value="1" class="btn btn-primary">Create group</button>
		</form>
	</div>
	<?php } ?>
</div>
