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
switch($this->get('account')->sync_status) {
case 'proposed': $sync_class = 'info'; $sync_message = 'Requested'; break;
case 'sync success': $sync_class = 'success'; $sync_message = 'Synced'; break;
case 'sync failure': $sync_class = 'danger'; $sync_message = 'Failed'; break;
case 'sync warning':
default: $sync_class = 'warning'; $sync_message = 'Not synced'; break;
}
?>
<h1><span class="glyphicon glyphicon-log-in" title="Server account"></span> <?php out($this->get('account')->name)?>@<a href="<?php outurl('/servers/'.urlencode($this->get('server')->hostname))?>"><?php out($this->get('server')->hostname)?></a><?php if($this->get('account')->active == 0) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></h1>
<?php if($this->get('server')->key_management == 'keys') { ?>
<?php if($this->get('account')->name != 'root' && $this->get('server')->sync_status == 'sync warning') { ?>
<div class="alert alert-danger">
	Non-root accounts are not being synchronized on this server yet.  See <a href="<?php outurl('/help#sync_setup')?>">the help pages</a> for details of what is required to activate syncing for all accounts.</p>
</div>
<?php } else { ?>
<dl class="oneline">
	<dt>Sync status:</dt>
	<dd id="server_account_sync_status"
	<?php if(!$this->get('account')->sync_is_pending()) { ?>
	data-class="<?php out($sync_class)?>" data-message="<?php out($sync_message)?>"
	<?php } ?>
	>
		<span></span>
		<div class="spinner"></div>
	</dd>
</dl>
<?php } ?>
<?php } ?>
<?php if($this->get('account')->sync_status == 'proposed') { ?>
<div class="alert alert-info">
	The account name <i><?php out($this->get('account')->name) ?></i> is a requested account.  If you reject the access request<?php out(count($this->get('access_requests')) == 1 ? '' : 's')?> below then the account will be removed from the keys system.
</div>
<?php } ?>
<ul class="nav nav-tabs">
	<?php if($this->get('server')->key_management == 'keys') { ?>
	<li><a href="#access" data-toggle="tab">Access</a></li>
	<?php } ?>
	<li><a href="#pubkeys" data-toggle="tab">Public keys</a></li>
	<li><a href="#outbound" data-toggle="tab">Outbound access</a></li>
	<li><a href="#admins" data-toggle="tab">Administrators</a></li>
	<li><a href="#log" data-toggle="tab">Log</a></li>
</ul>

<!-- Tab panes -->
<div class="tab-content">
	<?php if($this->get('server')->key_management == 'keys') { ?>
	<div class="tab-pane fade" id="access">
		<h2 class="sr-only">Access</h2>
		<?php if(count($this->get('access')) == 0 && count($this->get('access_requests')) == 0) { ?>
		<p>No-one has been granted access to this account.</p>
		<?php } else { ?>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table table-bordered table-hover">
				<thead>
					<tr>
						<th>Access for</th>
						<th>Status</th>
						<th>Options</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->get('access') as $access) { ?>
					<?php $entity = $access->source_entity; ?>
					<tr>
						<?php
						$options = $access->list_options();
						switch(get_class($entity)) {
						case 'User':
							?>
						<td>
							<a href="<?php outurl('/users/'.urlencode($entity->uid))?>" class="user"><?php out($entity->uid) ?></a>
							<?php if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?>
						</td>
							<?php
							break;
						case 'ServerAccount':
							?>
						<td>
							<a href="<?php outurl('/servers/'.urlencode($entity->server->hostname).'/accounts/'.urlencode($entity->name))?>" class="serveraccount"><?php out($entity->name.'@'.$entity->server->hostname) ?></a>
							<?php if($entity->server->key_management == 'decommissioned') out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?>
						</td>
							<?php
							break;
						case 'Group':
							?>
						<td>
							<a href="<?php outurl('/groups/'.urlencode($entity->name))?>" class="group"><?php out($entity->name) ?></a>
							<?php if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?>
						</td>
							<?php
							break;
						}
						?>
						<td>
							Access granted on <span class="date"><?php out($access->grant_date) ?> by <a href="<?php outurl('/users/'.urlencode($access->granted_by->uid))?>" class="user"><?php out($access->granted_by->uid) ?></a></span>
						</td>
						<td>
							<?php if(count($options) > 0) { ?>
							<ul class="compact">
								<?php foreach($options as $option) { ?>
								<li>
									<code>
										<?php
										out($option->option);
										if(!is_null($option->value)) {
											?>=&quot;<abbr title="<?php out($option->value)?>">…</abbr>&quot;<?php
										}
										?>
									</code>
								</li>
								<?php } ?>
							</ul>
							<?php } ?>
						</td>
						<td class="nowrap">
							<a href="<?php outurl('/servers/'.urlencode($this->get('server')->hostname).'/accounts/'.urlencode($this->get('account')->name))?>/access_rules/<?php out($access->id, ESC_URL)?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-cog"></span> Configure access</a>
							<button type="submit" name="delete_access" value="<?php out($access->id)?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-ban-circle"></span> Remove access</button>
						</td>
					</tr>
					<?php } ?>
					<?php foreach($this->get('access_requests') as $access) { ?>
					<?php $entity = $access->source_entity; ?>
					<tr>
						<?php
						switch(get_class($entity)) {
						case 'User':
							?>
						<td>
							<a href="<?php outurl('/users/'.urlencode($entity->uid))?>" class="user"><?php out($entity->uid) ?></a>
							<?php if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?>
						</td>
							<?php
							break;
						case 'ServerAccount':
							?>
						<td>
							<a href="<?php outurl('/servers/'.urlencode($entity->server->hostname).'/accounts/'.urlencode($entity->name))?>" class="serveraccount"><?php out($entity->name.'@'.$entity->server->hostname) ?></a>
							<?php if($entity->server->key_management == 'decommissioned') out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?>
						</td>
							<?php
							break;
						case 'Group':
							?>
						<td><a href="<?php outurl('/groups/'.urlencode($entity->name))?>" class="group"><?php out($entity->name) ?></a></td>
							<?php
							break;
						}
						?>
						<td>Access requested on <span class="date"><?php out($access->request_date) ?></span> by <a href="<?php outurl('/users/'.urlencode($access->requested_by->uid))?>" class="user"><?php out($access->requested_by->uid) ?></a></td>
						<td></td>
						<td class="nowrap">
							<button type="submit" name="approve_access" value="<?php out($access->id)?>" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-ok"></span> Approve</button>
							<button type="submit" name="reject_access" value="<?php out($access->id)?>" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-remove"></span> Reject</button>
						</td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</form>
		<?php } ?>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<h4>Add user to account</h4>
			<div class="row">
				<div class="form-group col-md-9">
					<div class="input-group">
						<span class="input-group-addon"><label for="username"><span class="glyphicon glyphicon-user" title="User"></span><span class="sr-only">User name</span></label></span>
						<input type="text" id="username" name="username" class="form-control" placeholder="User name" required list="userlist">
						<datalist id="userlist">
							<?php foreach($this->get('all_users') as $user) { ?>
							<option value="<?php out($user->uid)?>" label="<?php out($user->name)?>">
							<?php } ?>
						</datalist>
					</div>
				</div>
				<div class="form-group col-md-3">
					<button type="submit" name="add_access" value="1" class="btn btn-primary btn-block">Add user to account</button>
				</div>
			</div>
		</form>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<h4>Add server-to-server access to account</h4>
			<div class="row">
				<div class="form-group col-md-2">
					<div class="input-group">
						<span class="input-group-addon"><label for="account"<span class="glyphicon glyphicon-log-in" title="Server account"></span><span class="sr-only">Account name</span></label></span>
						<input type="text" id="account" name="account" class="form-control" placeholder="Account name" required>
					</div>
				</div>
				<div class="form-group col-md-7">
					<div class="input-group">
						<span class="input-group-addon"><label for="hostname">@</label></span>
						<input type="text" id="hostname" name="hostname" class="form-control" placeholder="Hostname" required list="serverlist">
						<datalist id="serverlist">
							<?php foreach($this->get('all_servers') as $server) { ?>
							<option value="<?php out($server->hostname)?>">
							<?php } ?>
						</datalist>
					</div>
				</div>
				<div class="form-group col-md-3">
					<button type="submit" name="add_access" value="1" class="btn btn-primary btn-block">Add remote account to this account</button>
				</div>
			</div>
		</form>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<h4>Add group access to account</h4>
			<div class="row">
				<div class="form-group col-md-9">
					<div class="input-group">
						<span class="input-group-addon"><label for="group"><span class="glyphicon glyphicon-list-alt" title="Group"></span><span class="sr-only">Group name</span></label></span>
						<input type="text" id="group" name="group" class="form-control" placeholder="Group name" required list="grouplist">
						<datalist id="grouplist">
							<?php foreach($this->get('all_groups') as $group) { ?>
							<option value="<?php out($group->name)?>">
							<?php } ?>
						</datalist>
					</div>
				</div>
				<div class="form-group col-md-3">
					<button type="submit" name="add_access" value="1" class="btn btn-primary btn-block">Add group to this account</button>
				</div>
			</div>
		</form>
		<?php if(count($this->get('group_membership')) > 0) { ?>
		<hr>
		<h3>Group access rules</h3>
		<p>
			As this account is a member of the
			<?php
			$grouplist = array();
			foreach($this->get('group_membership') as $group) {
				$grouplist[] = '<a href="'.rrurl('/groups/'.urlencode($group->name)).'" class="group">'.hesc($group->name).'</a>';
			}
			$grouplisttext = english_list($grouplist).' group'.(count($this->get('group_membership') == 1) ? '' : 's');
			?>
			<?php out($grouplisttext, ESC_NONE)?>, the following access rules automatically apply to it:
		</p>
		<?php
		foreach($this->get('group_membership') as $group) {
			$group_access_rules = $group->list_access();
			if(count($group_access_rules) > 0) {
			?>
		<h4><a href="<?php outurl('/groups/'.urlencode($group->name))?>" class="group"><?php out($group->name)?></a></h4>
		<table class="table table-bordered table-striped">
			<thead>
				<tr>
					<th colspan="2">Access for</th>
					<th>Status</th>
					<th>Options</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($group_access_rules as $access) { ?>
				<?php $entity = $access->source_entity; ?>
				<tr>
					<?php
					$options = $access->list_options();
					switch(get_class($entity)) {
					case 'User':
						?>
					<td><a href="<?php outurl('/users/'.urlencode($entity->uid))?>" class="user"><?php out($entity->uid) ?></a></td>
					<td><?php out($entity->name); if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
						<?php
						break;
					case 'ServerAccount':
						?>
					<td><a href="<?php outurl('/servers/'.urlencode($entity->server->hostname).'/accounts/'.urlencode($entity->name))?>" class="serveraccount"><?php out($entity->name.'@'.$entity->server->hostname) ?></a></td>
					<td><em>Server-to-server access</em><?php if($entity->server->key_management == 'decommissioned') out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
						<?php
						break;
					case 'Group':
						?>
					<td><a href="<?php outurl('/groups/'.urlencode($entity->name))?>" class="group"><?php out($entity->name) ?></a></td>
					<td><em>Group access</em></td>
						<?php
						break;
					}
					?>
					<td>Access granted on <?php out($access->grant_date) ?> by <a href="<?php outurl('/users/'.urlencode($access->granted_by->uid))?>" class="user"><?php out($access->granted_by->uid) ?></a></td>
					<td>
						<?php if(count($options) > 0) { ?>
						<ul class="compact">
							<?php foreach($options as $option) { ?>
							<li>
								<code>
									<?php
									out($option->option);
									if(!is_null($option->value)) {
										?>=&quot;<abbr title="<?php out($option->value)?>">…</abbr>&quot;<?php
									}
									?>
								</code>
							</li>
							<?php } ?>
						</ul>
						<?php } ?>
					</td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<?php } ?>
		<?php } ?>
	</div>
	<?php } ?>
	<div class="tab-pane fade" id="pubkeys">
		<h2 class="sr-only">Public keys</h2>
		<p class="alert alert-info">Keys added here will be used for <strong>outgoing</strong> connections <em>from</em> this account to any account that it has been granted remote access to.</p>
		<p>Public keys can be added to an account to facilitate server-to-server access from it to other accounts.</p>
		<?php if(count($this->get('pubkeys')) == 0) { ?>
		<p>This account does not have any public keys associated with it.</p>
		<?php } else { ?>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table">
				<thead>
					<tr>
						<th>Type</th>
						<th class="fingerprint">Fingerprint</th>
						<th></th>
						<th>Size</th>
						<th>Comment</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->get('pubkeys') as $key) { ?>
					<tr>
						<td><?php out($key->type) ?></td>
						<td>
							<a href="<?php outurl('/pubkeys/'.urlencode($key->id))?>">
								<span class="fingerprint_md5"><?php out($key->fingerprint_md5) ?></span>
								<span class="fingerprint_sha256"><?php out($key->fingerprint_sha256) ?></span>
							</a>
						</td>
						<td>
							<?php if(count($key->list_signatures()) > 0) { ?><a href="<?php outurl('/pubkeys/'.urlencode($key->id).'#sig')?>"><span class="glyphicon glyphicon-pencil" title="Signed key"></span></a><?php } ?>
							<?php if(count($key->list_destination_rules()) > 0) { ?><a href="<?php outurl('/pubkeys/'.urlencode($key->id).'#dest')?>"><span class="glyphicon glyphicon-pushpin" title="Destination-restricted"></span></a><?php } ?>
						</td>
						<td><?php out($key->keysize) ?></td>
						<td><?php out($key->comment) ?></td>
						<td><button type="submit" name="delete_public_key" value="<?php out($key->id) ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Delete public key</button></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</form>
		<?php } ?>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="add_public_key">Public key</label>
				<textarea class="form-control" rows="4" id="add_public_key" name="add_public_key" required></textarea>
			</div>
			<?php if($this->get('active_user')->admin) { ?>
			<div class="checkbox">
				<label><input type="checkbox" name="force"> Allow weak (< 4096 bits) key</label>
			</div>
			<?php } ?>
			<div class="form-group"><button class="btn btn-primary btn-lg btn-block">Add public key to account</button></div>
		</form>
	</div>
	<div class="tab-pane fade" id="outbound">
		<h2 class="sr-only">Outbound access</h2>
		<?php if(count($this->get('remote_access')) == 0) { ?>
		<p>This account has not been granted access to any other resources.</p>
		<?php } else { ?>
		<p>This account has access to the following resources:</p>
		<table class="table table-bordered table-striped">
			<thead>
				<tr>
					<th colspan="2">Access to</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($this->get('remote_access') as $access) { ?>
				<?php $entity = $access->dest_entity; ?>
				<tr>
					<?php
					switch(get_class($entity)) {
					case 'User':
						?>
					<td><a href="<?php outurl('/users/'.urlencode($entity->uid))?>" class="user"><?php out($entity->uid) ?></a></td>
					<td><?php out($entity->name); if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
						<?php
						break;
					case 'ServerAccount':
						?>
					<td><a href="<?php outurl('/servers/'.urlencode($entity->server->hostname).'/accounts/'.urlencode($entity->name))?>" class="serveraccount"><?php out($entity->name.'@'.$entity->server->hostname) ?></a></td>
					<td><em>Server-to-server access</em><?php if($entity->server->key_management == 'decommissioned') out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
						<?php
						break;
					case 'Group':
						?>
					<td><a href="<?php outurl('/groups/'.urlencode($entity->name))?>" class="group"><?php out($entity->name) ?></a></td>
					<td><em>Group access</em></td>
						<?php
						break;
					}
					?>
					<td>Access granted on <?php out($access->grant_date) ?> by <?php out($access->granted_by->uid) ?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<?php if(count($this->get('group_membership')) > 0) { ?>
		<hr>
		<h3>Group access rules</h3>
		<p>
			As this account is a member of the
			<?php
			$grouplist = array();
			foreach($this->get('group_membership') as $group) {
				$grouplist[] = '<a href="'.rrurl('/groups/'.urlencode($group->name)).'" class="group">'.hesc($group->name).'</a>';
			}
			$grouplisttext = english_list($grouplist).' group'.(count($this->get('group_membership') == 1) ? '' : 's');
			?>
			<?php out($grouplisttext, ESC_NONE)?>, the following outbound access rules automatically apply to it:
		</p>
		<?php
		foreach($this->get('group_membership') as $group) {
			$group_access_rules = $group->list_remote_access();
			if(count($group_access_rules) > 0) {
			?>
		<h4><a href="<?php outurl('/groups/'.urlencode($group->name))?>" class="group"><?php out($group->name)?></a></h4>
		<table class="table table-bordered table-striped">
			<thead>
				<tr>
					<th colspan="2">Group has access to</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($group_access_rules as $access) { ?>
				<?php $entity = $access->dest_entity; ?>
				<tr>
					<?php
					switch(get_class($entity)) {
					case 'User':
						?>
					<td><a href="<?php outurl('/users/'.urlencode($entity->uid))?>" class="user"><?php out($entity->uid) ?></a></td>
					<td><?php out($entity->name); if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
						<?php
						break;
					case 'ServerAccount':
						?>
					<td><a href="<?php outurl('/servers/'.urlencode($entity->server->hostname).'/accounts/'.urlencode($entity->name))?>" class="serveraccount"><?php out($entity->name.'@'.$entity->server->hostname) ?></a></td>
					<td><em>Server-to-server access</em><?php if($entity->server->key_management == 'decommissioned') out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
						<?php
						break;
					case 'Group':
						?>
					<td><a href="/groups/<?php out($entity->name, ESC_URL)?>" class="group"><?php out($entity->name) ?></a></td>
					<td><em>Group access</em></td>
						<?php
						break;
					}
					?>
					<td>Access granted on <?php out($access->grant_date) ?> by <a href="<?php outurl('/users/'.urlencode($access->granted_by->uid))?>" class="user"><?php out($access->granted_by->uid) ?></a></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<?php } ?>
		<?php } ?>
	</div>
	<div class="tab-pane fade" id="admins">
		<h2 class="sr-only">Account administrators</h2>
		<?php if(count($this->get('admins')) == 0) { ?>
		<p>This account does not have any administrators assigned.</p>
		<?php } else { ?>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table table-bordered table-striped">
				<thead>
					<tr>
						<th>User ID</th>
						<th>Name</th>
						<?php if($this->get('admin') || $this->get('server_admin')) { ?>
						<th>Actions</th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->get('admins') as $admin) { ?>
					<tr>
						<td><a href="<?php outurl('/users/'.urlencode($admin->uid))?>" class="user"><?php out($admin->uid) ?></a></td>
						<td><?php out($admin->name); if(!$admin->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
						<?php if($this->get('admin') || $this->get('server_admin')) { ?>
						<td>
							<button type="submit" name="delete_admin" value="<?php out($admin->id) ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Remove admin</button>
						</td>
						<?php } ?>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</form>
		<?php } ?>
		<?php if($this->get('admin') || $this->get('server_admin')) { ?>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>" class="form-inline">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<h3>Add administrator</h3>
			<div class="form-group">
				<label for="user_name" class="sr-only">Account name</label>
				<input type="text" id="user_name" name="user_name" class="form-control" placeholder="User name" required list="userlist">
				<datalist id="userlist">
					<?php foreach($this->get('all_users') as $user) { ?>
					<option value="<?php out($user->uid)?>" label="<?php out($user->name)?>">
					<?php } ?>
				</datalist>
			</div>
			<button type="submit" name="add_admin" value="1" class="btn btn-primary">Add administrator to account</button>
		</form>
		<?php } ?>
	</div>
	<div class="tab-pane fade" id="log">
		<h2 class="sr-only">Log</h2>
		<table class="table">
			<col></col>
			<col></col>
			<col></col>
			<col class="date"></col>
			<thead>
				<tr>
					<th>Entity</th>
					<th>User</th>
					<th>Activity</th>
					<th>Date (<abbr title="Coordinated Universal Time">UTC</abbr>)</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach($this->get('log') as $event) {
					show_event($event);
				}
				?>
			</tbody>
		</table>
	</div>
</div>
