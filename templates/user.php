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
<h1><span class="glyphicon glyphicon-user" title="User"></span> <?php out($this->get('user')->name)?> <small>(<?php out($this->get('user')->uid)?>)</small><?php if(!$this->get('user')->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE)?></h1>
<dl>
	<dt>Account type</dt>
	<dd><?php out($this->get('user')->auth_realm)?></dd>
</dl>
<ul class="nav nav-tabs">
	<li><a href="#details" data-toggle="tab">Details</a></li>
	<?php if($this->get('user')->auth_realm == 'LDAP') { ?>
	<li><a href="#settings" data-toggle="tab">Settings</a></li>
	<?php } ?>
</ul>
<!-- Tab panes -->
<div class="tab-content">
	<div class="tab-pane fade" id="details">
		<h2 class="sr-only">Details</h2>
		<h3><a href="<?php outurl('/users/'.urlencode($this->get('user')->uid).'/pubkeys')?>">Public keys</a></h3>
		<?php if(count($this->get('user_keys')) == 0) { ?>
		<p><?php out($this->get('user')->name)?> has no public keys uploaded.</p>
		<?php } else { ?>
		<table class="table">
			<thead>
				<tr>
					<th>Type</th>
					<th class="fingerprint">Fingerprint</th>
					<th></th>
					<th>Size</th>
					<th>Comment</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($this->get('user_keys') as $key) { ?>
				<tr>
					<td><?php out($key->type) ?></td>
					<td>
						<a href="<?php outurl('/pubkeys/'.urlencode($key->id).'#info')?>">
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
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<?php if($this->get('admin')) { ?>
		<h3>Groups</h3>
		<?php if(count($this->get('user_groups')) == 0 && count($this->get('user_admined_groups')) == 0) { ?>
		<p><?php out($this->get('user')->name)?> is not a member or administrator of any groups.</p>
		<?php } ?>
		<?php if(count($this->get('user_groups')) > 0) { ?>
		<p><?php out($this->get('user')->name)?> is a member of the following groups:</p>
		<table class="table">
			<thead>
				<tr>
					<th>Group</th>
					<th>Members</th>
					<th>Admins</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($this->get('user_groups') as $group) {?>
				<tr>
					<td><a href="<?php outurl('/groups/'.urlencode($group->name)) ?>" class="group"><?php out($group->name) ?></a></td>
					<td><?php out(number_format($group->member_count))?></td>
					<td><?php out($group->admins)?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<?php if(count($this->get('user_admined_groups')) > 0) { ?>
		<p><?php out($this->get('user')->name)?> is an administrator of the following groups:</p>
		<table class="table">
			<thead>
				<tr>
					<th>Group</th>
					<th>Members</th>
					<th>Admins</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($this->get('user_admined_groups') as $group) {?>
				<tr>
					<td><a href="<?php outurl('/groups/'.urlencode($group->name)) ?>" class="group"><?php out($group->name) ?></a></td>
					<td><?php out(number_format($group->member_count))?></td>
					<td><?php out($group->admins)?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<h3>Access</h3>
		<?php if(count($this->get('user_access')) == 0) { ?>
		<p><?php out($this->get('user')->name)?> has not been explicitly granted access to any entities.</p>
		<?php } else { ?>
		<p><?php out($this->get('user')->name)?> has been explicitly granted access to the following entities:</p>
		<table class="table">
			<thead>
				<tr>
					<th>Entity</th>
					<th>Granted by</th>
					<th>Granted on</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($this->get('user_access') as $access) { ?>
				<tr>
					<td>
						<?php
						switch(get_class($access->dest_entity)) {
						case 'ServerAccount':
						?>
						<a href="<?php outurl('/servers/'.urlencode($access->dest_entity->server->hostname).'/accounts/'.urlencode($access->dest_entity->name))?>" class="serveraccount"><?php out($access->dest_entity->name.'@'.$access->dest_entity->server->hostname)?></a>
						<?php
							break;
						case 'Group':
						?>
						<a href="<?php outurl('/groups/'.urlencode($access->dest_entity->name))?>" class="group"><?php out($access->dest_entity->name)?></a>
						<?php
							break;
						}
						?>
					</td>
					<td><a href="<?php outurl('/users/'.urlencode($access->granted_by->uid))?>" class="user"><?php out($access->granted_by->uid)?></a></td>
					<td><?php out($access->grant_date) ?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<h3>Server administration</h3>
		<?php if(count($this->get('user_admined_servers')) == 0) { ?>
		<p><?php out($this->get('user')->name)?> is not an administrator for any servers.</p>
		<?php } else { ?>
		<p><?php out($this->get('user')->name)?> is an administrator for the following servers:</p>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table" id="admined_servers">
				<thead>
					<tr>
						<th>Hostname</th>
						<th>Config</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach($this->get('user_admined_servers') as $server) {
						if($server->key_management != 'keys') {
							$class = '';
						} else {
							switch($server->sync_status) {
							case 'not synced yet': $class = 'warning'; break;
							case 'sync failure':   $class = 'danger';  break;
							case 'sync success':   $class = 'success'; break;
							case 'sync warning':   $class = 'warning'; break;
							}
						}
						if($last_sync = $server->get_last_sync_event()) {
							$sync_details = json_decode($last_sync->details)->value;
						} else {
							$sync_details = ucfirst($server->sync_status);
						}
					?>
					<tr>
						<td>
							<a href="<?php outurl('/servers/'.urlencode($server->hostname)) ?>" class="server"><?php out($server->hostname) ?></a>
							<?php if($server->pending_requests > 0) { ?>
							<a href="<?php outurl('/servers/'.urlencode($server->hostname).'#requests') ?>"><span class="badge" title="Pending requests"><?php out(number_format($server->pending_requests)) ?></span></a>
							<?php } ?>
						</td>
						<td>
							<?php
							switch($server->key_management) {
							case 'keys':
								switch($server->authorization) {
								case 'manual': out('Manual account management'); break;
								case 'automatic LDAP': out('LDAP accounts - automatic'); break;
								case 'manual LDAP': out('LDAP accounts - manual'); break;
								}
								break;
							case 'other': out('Managed by another system'); break;
							case 'none': out('Unmanaged'); break;
							case 'decommissioned': out('Decommissioned'); break;
							}
							?>
						</td>
						<td class="<?php out($class)?>"><?php out($sync_details) ?></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
			<p><button type="button" class="btn btn-default" data-reassign="admined_servers">Reassign servers</button></p>
		</form>
		<?php } ?>
		<?php } ?>
	</div>
	<?php if($this->get('user')->auth_realm == 'LDAP') { ?>
	<div class="tab-pane fade" id="settings">
		<h2 class="sr-only">Settings</h2>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>" class="form-horizontal">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label class="col-sm-2 control-label">User status</label>
				<div class="col-sm-10">
					<div class="radio">
						<label>
							<input type="radio" name="force_disable" value="0"<?php if(!$this->get('user')->force_disable) out(' checked') ?>>
							Use status from LDAP
						</label>
					</div>
					<div class="radio">
						<label class="text-danger">
							<input type="radio" name="force_disable" value="1"<?php if($this->get('user')->force_disable) out(' checked') ?>>
							Disable account (override LDAP)
						</label>
					</div>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" name="edit_user" value="1" class="btn btn-primary">Change settings</button>
				</div>
			</div>
		</form>
	</div>
	<?php } ?>
</div>
