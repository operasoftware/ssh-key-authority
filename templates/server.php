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
<h1><span class="glyphicon glyphicon-hdd" title="Server"></span> <?php out($this->get('server')->hostname)?><?php if($this->get('server')->key_management == 'decommissioned') out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></h1>
<?php if($this->get('admin') || $this->get('server_admin')) { ?>
<?php if($this->get('server')->key_management == 'keys') { ?>
<form method="post" action="<?php out($this->data->relative_request_url)?>">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<dl class="oneline">
		<?php if(isset($this->get('inventory_config')['url']) && $this->get('server')->uuid) { ?>
		<dt>Inventory UUID:</dt>
		<dd><a href="<?php out(printf($this->get('inventory_config')['url'], $this->get('server')->uuid), ESC_URL)?>/"><?php out($this->get('server')->uuid)?></a></dd>
		<?php } ?>
		<dt>Sync status:</dt>
		<dd id="server_sync_status"
		<?php if(count($this->get('sync_requests')) == 0) { ?>
		<?php if(is_null($this->get('last_sync'))) { ?>
		data-class="warning" data-message="Not synced yet"
		<?php } else { ?>
		data-class="<?php out($this->get('sync_class'))?>" data-message="<?php out(json_decode($this->get('last_sync')->details)->value) ?>"
		<?php } ?>
		<?php } ?>
		>
			<span></span>
			<div class="spinner"></div>
			<a href="/help" class="btn btn-info btn-xs hidden">Explain</a>
			<button name="sync" value="1" type="submit" class="btn btn-default btn-xs invisible">Sync now</button>
		</dd>
	</dl>
</form>
<?php if($this->get('server')->ip_address && count($this->get('matching_servers_by_ip')) > 1) { ?>
<div class="alert alert-danger">
	<p>The hostname <?php out($this->get('server')->hostname)?> resolves to the same IP address as the following:</p>
	<ul>
		<?php foreach($this->get('matching_servers_by_ip') as $matched_server) { ?>
		<?php if($matched_server->hostname != $this->get('server')->hostname) { ?>
		<li><a href="/servers/<?php out($matched_server->hostname, ESC_URL)?>" class="server alert-link"><?php out($matched_server->hostname)?></a></li>
		<?php } ?>
		<?php } ?>
	</ul>
</div>
<?php } ?>
<?php if($this->get('server')->rsa_key_fingerprint && count($this->get('matching_servers_by_host_key')) > 1) { ?>
<div class="alert alert-danger">
	<p>The server has the same SSH host key as the following:</p>
	<ul>
		<?php foreach($this->get('matching_servers_by_host_key') as $matched_server) { ?>
		<?php if($matched_server->hostname != $this->get('server')->hostname) { ?>
		<li><a href="/servers/<?php out($matched_server->hostname, ESC_URL)?>" class="server alert-link"><?php out($matched_server->hostname)?></a></li>
		<?php } ?>
		<?php } ?>
	</ul>
</div>
<?php } ?>
<?php } ?>
<ul class="nav nav-tabs">
	<li><a href="#accounts" data-toggle="tab">Accounts</a></li>
	<li><a href="#admins" data-toggle="tab">Administrators</a></li>
	<li><a href="#settings" data-toggle="tab">Settings</a></li>
	<li><a href="#log" data-toggle="tab">Log</a></li>
	<?php if($this->get('admin')) { ?>
	<li><a href="#notes" data-toggle="tab">Notes<?php if(count($this->get('server_notes')) > 0) out(' <span class="badge">'.count($this->get('server_notes')).'</span>', ESC_NONE)?></a></li>
	<li><a href="#contact" data-toggle="tab">Contact</a></li>
	<?php } ?>
</ul>

<!-- Tab panes -->
<div class="tab-content">
	<div class="tab-pane fade" id="accounts">
		<h2 class="sr-only">
			<?php if($this->get('server')->authorization == 'manual') { ?>
				Accounts
			<?php } else { ?>
				Non-LDAP accounts
			<?php } ?>
		</h2>
		<?php if(count($this->get('server_accounts')) == 0) { ?>
		<p>No accounts have been created yet.</p>
		<?php } else { ?>
		<form method="post" action="<?php out($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table table-bordered">
				<thead>
					<tr>
						<th>Account</th>
						<?php if($this->get('server')->key_management == 'keys') { ?>
						<th>Sync status</th>
						<?php } ?>
						<th>Account actions</th>
						<th colspan="2">Access granted for</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->get('server_accounts') as $account) { ?>
					<?php
					$access_list = $account->list_access();
					switch($account->sync_status) {
					case 'proposed': $sync_class = 'info'; $sync_message = 'Requested'; break;
					case 'sync success': $sync_class = 'success'; $sync_message = 'Synced'; break;
					case 'sync failure': $sync_class = 'danger'; $sync_message = 'Failed'; break;
					case 'sync warning':
					default: $sync_class = 'warning'; $sync_message = 'Not synced'; break;
					}
					?>
					<tr>
						<th rowspan="<?php out(max(1, count($access_list)))?>">
							<a href="<?php out($this->data->relative_request_url.'/accounts/'.urlencode($account->name))?>" class="serveraccount"><?php out($account->name) ?></a>
							<?php if($account->pending_requests > 0) { ?>
							<a href="<?php out($this->data->relative_request_url.'/accounts/'.urlencode($account->name))?>"><span class="badge" title="Pending requests"><?php out(number_format($account->pending_requests))?></span></a>
							<?php } ?>
						</th>
						<?php if($this->get('server')->key_management == 'keys') { ?>
						<td rowspan="<?php out(max(1, count($access_list)))?>">
							<span id="server_account_sync_status_<?php out($account->name)?>" class="server_account_sync_status"
							<?php if(!$account->sync_is_pending()) { ?>
							data-class="<?php out($sync_class)?>" data-message="<?php out($sync_message)?>"
							<?php } ?>
							></span>
						</td>
						<?php } ?>
						<td rowspan="<?php out(max(1, count($access_list)))?>">
							<a href="<?php out($this->data->relative_request_url.'/accounts/'.urlencode($account->name))?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-cog"></span> Manage account</a>
							<?php if(!array_key_exists($account->name, $this->get('default_accounts'))) { ?>
							<button type="submit" name="delete_account" value="<?php out($account->id) ?>" class="btn btn-default btn-xs" data-confirm="Are you sure you want to delete this account?"><span class="glyphicon glyphicon-trash"></span> Delete account</button>
							<?php } ?>
							<!--<button class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span> Grant user access</button>-->
						</td>
						<?php if(empty($access_list)) { ?>
						<td colspan="3"><em>No-one</em></td>
						<?php } else { ?>
						<?php
						$count = 0;
						foreach($access_list as $access) {
							$entity = $access->source_entity;
							$count++;
							if($count > 1) out('</tr><tr>', ESC_NONE);
							switch(get_class($entity)) {
							case 'User':
						?>
						<td><a href="/users/<?php out($entity->uid, ESC_URL)?>" class="user"><?php out($entity->uid) ?></a></td>
						<td><?php out($entity->name); if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE)?></td>
						<?php
								break;
							case 'ServerAccount':
						?>
						<td><a href="/servers/<?php out($entity->server->hostname, ESC_URL)?>/accounts/<?php out($entity->name, ESC_URL)?>" class="serveraccount"><?php out($entity->name.'@'.$entity->server->hostname) ?></a></td>
						<td><em>Server-to-server access</em><?php if($entity->server->key_management == 'decommissioned') out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
						<?php
								break;
							case 'Group':
						?>
						<td><a href="/groups/<?php out($entity->name, ESC_URL)?>" class="group"><?php out($entity->name) ?></a></td>
						<td><em>Group access</em><?php if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE)?></td>
						<?php
								break;
							}
						}
						?>
						<?php } ?>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</form>
		<?php } ?>
		<form method="post" action="<?php out($this->data->relative_request_url)?>" class="form-inline">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<h3>Create<?php if($this->get('server')->authorization != 'manual') out(' non-LDAP'); ?> account</h3>
			<div class="form-group">
				<label for="account_name" class="sr-only">Account name</label>
				<input type="text" id="account_name" name="account_name" class="form-control" placeholder="Account name" required pattern=".*[^\s].*">
			</div>
			<button type="submit" name="add_account" value="1" class="btn btn-primary">Manage this account with SSH Key Authority</button>
		</form>
	</div>
	<div class="tab-pane fade" id="admins">
		<h2 class="sr-only">Server administrators</h2>
		<?php if(count($this->get('server_admins')) == 0) { ?>
		<p class="alert alert-danger">This server does not have any administrators assigned.</p>
		<?php } else { ?>
		<form method="post" action="<?php out($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table table-bordered table-striped">
				<thead>
					<tr>
						<th>Entity</th>
						<th>Name</th>
						<?php if($this->get('admin')) { ?>
						<th>Actions</th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->get('server_admins') as $admin) { ?>
						<?php if(strtolower(get_class($admin)) == "user"){?>
							<tr>
								<td><a href="/users/<?php out($admin->uid, ESC_URL)?>" class="user"><?php out($admin->uid) ?></a></td>
								<td><?php out($admin->name); if(!$admin->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
								<?php if($this->get('admin')) {?>
								<td>
									<button type="submit" name="delete_admin" value="<?php out($admin->id) ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Remove admin</button>
								</td>
								<?php } ?>
							</tr>
						<?php } elseif(strtolower(get_class($admin)) == "group"){ ?>
							<tr>
								<td><a href="/groups/<?php out($admin->name, ESC_URL)?>" class="group"><?php out($admin->name) ?></a></td>
								<td><?php out($admin->name); if(!$admin->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?></td>
								<?php if($this->get('admin')) { ?>
								<td>
									<button type="submit" name="delete_admin" value="<?php out($admin->id) ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Remove admin</button>
								</td>
								<?php } ?>
							</tr>
						<?php }} ?>
				</tbody>
			</table>
		</form>
		<?php } ?>
		<?php if($this->get('admin')) { ?>
		<form method="post" action="<?php out($this->data->relative_request_url)?>" class="form-inline">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<h3>Add administrator</h3>
			<div class="form-group">
				<label for="user_name" class="sr-only">User or group name</label>
				<input type="text" id="user_name" name="user_name" class="form-control" placeholder="User or group name" required list="userlist">
				<datalist id="userlist">
					<?php foreach($this->get('all_users') as $user) { ?>
					<option value="<?php out($user->uid)?>" label="<?php out($user->name)?>">
					<?php } ?>
					<?php foreach($this->get('all_groups') as $group) { ?>
					<option value="<?php out($group->name)?>" label="<?php out($group->name)?>">
					<?php } ?>
				</datalist>
			</div>
			<button type="submit" name="add_admin" value="1" class="btn btn-primary">Add administrator to server</button>
		</form>
		<?php } ?>
	</div>
	<div class="tab-pane fade" id="settings">
		<h2 class="sr-only">Settings</h2>
		<form id="server_settings" method="post" action="<?php out($this->data->relative_request_url)?>" class="form-horizontal">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<?php if($this->get('admin')) { ?>
			<div class="form-group">
				<label for="hostname" class="col-sm-2 control-label">Hostname</label>
				<div class="col-sm-10">
					<input type="text" id="hostname" name="hostname" value="<?php out($this->get('server')->hostname)?>" required class="form-control">
				</div>
			</div>
			<div class="form-group">
				<label for="port" class="col-sm-2 control-label">SSH port number</label>
				<div class="col-sm-2">
					<input type="number" id="port" name="port" value="<?php out($this->get('server')->port)?>" required class="form-control">
				</div>
			</div>
			<div class="form-group">
				<label for="rsa_key_fingerprint" class="col-sm-2 control-label">Host key fingerprint</label>
				<div class="col-sm-4">
					<input type="text" id="rsa_key_fingerprint" name="rsa_key_fingerprint" value="<?php out($this->get('server')->rsa_key_fingerprint)?>" readonly class="form-control">
				</div>
				<div class="col-sm-6">
					<button type="button" class="btn btn-default" data-clear="rsa_key_fingerprint">Clear</button>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">Key management</label>
				<div class="col-sm-10">
					<div class="radio">
						<label class="text-success">
							<input type="radio" name="key_management" value="keys"<?php if($this->get('server')->key_management == 'keys') out(' checked') ?>>
							SSH keys managed and synced by SSH Key Authority
						</label>
					</div>
					<div class="radio">
						<label class="text-danger">
							<input type="radio" name="key_management" value="none"<?php if($this->get('server')->key_management == 'none') out(' checked') ?>>
							Disabled - server has no key management
						</label>
					</div>
					<div class="radio">
						<label>
							<input type="radio" name="key_management" value="other"<?php if($this->get('server')->key_management == 'other') out(' checked') ?>>
							Disabled - SSH keys managed by another system
					</div>
					<div class="radio">
						<label>
							<input type="radio" name="key_management" value="decommissioned"<?php if($this->get('server')->key_management == 'decommissioned') out(' checked') ?>>
							Disabled - server has been decommissioned
					</div>
				</div>
			</div>
			<div class="form-group<?php if($this->get('server')->key_management != 'keys') out(' hide') ?>" id="authorization">
				<label class="col-sm-2 control-label">Accounts</label>
				<div class="col-sm-10">
					<div class="radio">
						<label>
							<input type="radio" name="authorization" value="manual"<?php if($this->get('server')->authorization == 'manual') out(' checked') ?>>
							All accounts on the server are manually created
						</label>
					</div>
					<div class="radio">
						<label>
							<input type="radio" name="authorization" value="automatic LDAP"<?php if($this->get('server')->authorization == 'automatic LDAP') out(' checked') ?>>
							Accounts will be linked to LDAP and created automatically on the server
						</label>
					</div>
					<div class="radio">
						<label>
							<input type="radio" name="authorization" value="manual LDAP"<?php if($this->get('server')->authorization == 'manual LDAP') out(' checked') ?>>
							Accounts will be based on LDAP usernames but created manually on the server
						</label>
					</div>
				</div>
			</div>
			<?php $options = $this->get('ldap_access_options'); ?>
			<div class="form-group<?php if($this->get('server')->key_management != 'keys' || $this->get('server')->authorization == 'manual') out(' hide') ?>" id="ldap_access_options">
				<label class="col-sm-2 control-label">LDAP access options</label>
				<div class="col-sm-10">
					<div class="checkbox">
						<label><input type="checkbox" name="access_option[command][enabled]"<?php if(isset($options['command'])) out(' checked'); ?>> Specify command (<code>command=&quot;command&quot;</code>)</label>
					</div>
					<input type="text" id="command_value" name="access_option[command][value]" value="<?php if(isset($options['command'])) out($options['command']->value); ?>" class="form-control">
					<div class="checkbox">
						<label><input type="checkbox" name="access_option[from][enabled]"<?php if(isset($options['from'])) out(' checked'); ?>> Restrict source address (<code>from=&quot;<abbr title="A pattern-list is a comma-separated list of patterns.  Each pattern can be either a hostname or an IP address, with wildcards (* and ?) allowed.">pattern-list</abbr>&quot;</code>)</label>
					</div>
					<input type="text" id="from_value" name="access_option[from][value]" value="<?php if(isset($options['from'])) out($options['from']->value); ?>" class="form-control">
					<div class="checkbox">
						<label><input type="checkbox" name="access_option[no-port-forwarding][enabled]"<?php if(isset($options['no-port-forwarding'])) out(' checked'); ?>> Disallow port forwarding (<code>no-port-forwarding</code>)</label>
					</div>
					<div class="checkbox">
						<label><input type="checkbox" name="access_option[no-X11-forwarding][enabled]"<?php if(isset($options['no-X11-forwarding'])) out(' checked'); ?>> Disallow X11 forwarding (<code>no-X11-forwarding</code>)</label>
					</div>
					<div class="checkbox">
						<label><input type="checkbox" name="access_option[no-pty][enabled]"<?php if(isset($options['no-pty'])) out(' checked'); ?>> Disable terminal (<code>no-pty</code>)</label>
					</div>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" name="edit_server" value="1" class="btn btn-primary">Change settings</button>
				</div>
			</div>
			<?php } else { ?>
			<dl>
				<dt>SSH port number</dt>
				<dd><?php out($this->get('server')->port)?></dd>
				<dt>Key management</dt>
				<dd>
					<?php
					switch($this->get('server')->key_management) {
					case 'keys': out('SSH keys managed and synced by SSH Key Authority'); break;
					case 'none': out('Disabled - server has no key management'); break;
					case 'other': out('Disabled - SSH keys managed by another system'); break;
					case 'decommissioned': out('Disabled - server has been decommissioned'); break;
					}
					?>
				</dd>
				<dt>Accounts</dt>
				<dd>
					<?php
					switch($this->get('server')->authorization) {
					case 'manual': out('All accounts on the server are manually created'); break;
					case 'automatic LDAP': out('Accounts will be linked to LDAP and created automatically on the server'); break;
					case 'manual LDAP': out('Accounts will be based on LDAP usernames but created manually on the server'); break;
					}
					?>
				</dd>
				<?php if($this->get('server')->key_management == 'keys' && $this->get('server')->authorization != 'manual') { ?>
				<dt>LDAP access options</dt>
				<dd>
					<?php
					$optiontext = array();
					foreach($this->get('ldap_access_options') as $option) {
						$optiontext[] = $option->option.(is_null($option->value) ? '' : '="'.str_replace('"', '\\"', $option->value).'"');
					}
					if(count($optiontext) == 0) {
						out('No options set');
					} else {
						?>
						<code><?php out(implode(' ', $optiontext)) ?></code>
						<?php
					}
					?>
				</dd>
				<?php } ?>
			</dl>
			<?php if($this->get('server_admin_can_reset_host_key')) { ?>
			<div class="form-group">
				<label for="rsa_key_fingerprint" class="col-sm-2 control-label">Host key fingerprint</label>
				<div class="col-sm-4">
					<input type="text" id="rsa_key_fingerprint" name="rsa_key_fingerprint" value="<?php out($this->get('server')->rsa_key_fingerprint)?>" readonly class="form-control">
				</div>
				<div class="col-sm-6">
					<button type="button" class="btn btn-default" data-clear="rsa_key_fingerprint">Clear</button>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" name="edit_server" value="1" class="btn btn-primary">Change settings</button>
				</div>
			</div>
			<?php } ?>
			<?php } ?>
		</form>
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
				foreach($this->get('server_log') as $event) {
					show_event($event);
				}
				?>
			</tbody>
		</table>
	</div>
	<?php if($this->get('admin')) { ?>
	<div class="tab-pane fade" id="notes">
		<h2 class="sr-only">Notes</h2>
		<form method="post" action="<?php out($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<?php foreach($this->get('server_notes') as $note) { ?>
			<div class="panel panel-default">
				<div class="panel-body pre-formatted"><?php out($this->get('output_formatter')->comment_format($note->note), ESC_NONE)?></div>
				<div class="panel-footer">
					Added <?php out($note->date)?> by <?php out($note->user->name)?>
					<button name="delete_note" value="<?php out($note->id)?>" class="pull-right btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Delete</button>
				</div>
			</div>
			<?php } ?>
		</form>
		<form method="post" action="<?php out($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="note">Note</label>
				<textarea class="form-control" rows="4" id="note" name="note" required></textarea>
			</div>
			<div class="form-group">
				<button type="submit" name="add_note" value="1" class="btn btn-primary btn-lg btn-block">Add note</button>
			</div>
		</form>
	</div>
	<div class="tab-pane fade" id="contact">
		<h2 class="sr-only">Contact</h2>
		<form method="post" action="<?php out($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="anonymous">From</label>
				<select class="form-control" id="anonymous" name="anonymous">
					<option value="0"><?php out("{$this->get('active_user')->name} <{$this->get('active_user')->email}>");?></option>
					<option value="1"><?php out($this->get('email_config')['from_name'])?> &lt;<?php out($this->get('email_config')['from_address'])?>&gt; (Reply-to: <?php out($this->get('email_config')['admin_address']) ?>)</option>
				</select>
			</div>
			<div class="form-group">
				<label>Recipients</label>
				<div class="radio">
					<label>
						<input type="radio" name="recipients" value="admins" checked>
						Server admins of <?php out($this->get('server')->hostname) ?>
					</label>
				</div>
				<div class="radio">
					<label>
						<input type="radio" name="recipients" value="root_users">
						All users with access to root@<?php out($this->get('server')->hostname) ?>
					</label>
				</div>
				<div class="radio">
					<label>
						<input type="radio" name="recipients" value="users">
						All users with access to accounts on <?php out($this->get('server')->hostname) ?>
					</label>
				</div>
			</div>
			<div class="form-group">
				<div class="checkbox">
					<label>
						<input type="checkbox" id="hide_recipients" name="hide_recipients">
						Hide recipient list
					</label>
				</div>
			</div>
			<div class="form-group">
				<label for="subject">Subject</label>
				<input type="text" class="form-control" id="subject" name="subject" required value="Server <?php out('"'.$this->get('server')->hostname.'"') ?>">
			</div>
			<div class="form-group">
				<label for="body">Body</label>
				<textarea class="form-control" rows="20" id="body" name="body" required></textarea>
			</div>
			<div class="form-group"><button type="submit" name="send_mail" value="1" data-confirm="Send mail? Are you sure?" class="btn btn-primary btn-lg btn-block">Send mail</button></div>
		</form>
	</div>
	<?php } ?>
</div>
<?php } else { ?>
<?php if($this->get('server')->authorization == 'manual') { ?>
<?php if(count($this->get('access_accounts')) == 1) { ?>
<?php $accounts = $this->get('access_accounts'); $account = reset($accounts) ?>
<p>You have access to the <i><?php out($account) ?></i> account on this server.</p>
<?php } elseif(count($this->get('access_accounts')) > 1) { ?>
<p>You have access to the following accounts on this server: <?php out(implode(', ', $this->get('access_accounts'))) ?>
</p>
<?php } ?>
<form method="post" action="<?php out($this->data->relative_request_url)?>">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<h4>Request access to account</h4>
	<div class="row">
		<div class="col-sm-5">
			<label for="account_name" class="sr-only">Account name</label>
			<div class="input-group">
				<span class="input-group-addon"><span class="glyphicon glyphicon-log-in" title="Server account"></span></span>
				<input type="text" id="account_name" name="account_name" class="form-control" placeholder="Account name" list="accountlist" required pattern=".*[^\s].*">
				<span class="input-group-addon">@<?php out($this->get('server')->hostname)?></span>
				<datalist id="accountlist">
					<?php foreach($this->get('all_accounts') as $accounts) { ?>
					<option value="<?php out($accounts->name)?>">
					<?php } ?>
				</datalist>
			</div>
		</div>
		<div class="col-sm-7">
			<button type="submit" name="request_access" value="user" class="btn btn-primary">Request access</button>
			<a href="/help#getting_access" class="btn btn-info">Help</a>
		</div>
	</div>
</form>
<form method="post" action="<?php out($this->data->relative_request_url)?>">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<h4>Request server-to-server access</h4>
	<div class="row">
		<div class="form-group col-sm-3">
			<div class="input-group">
				<span class="input-group-addon">From: </span>
				<span class="input-group-addon"><label for="account"><span class="glyphicon glyphicon-log-in" title="Server account"></span><span class="sr-only">Account name</span></label></span>
				<input type="text" id="account_remote" name="account_remote" class="form-control" placeholder="Account name" required pattern=".*[^\s].*">
			</div>
		</div>
		<div class="form-group col-sm-3">
			<div class="input-group">
				<span class="input-group-addon"><label for="hostname">@</label></span>
				<input type="text" id="hostname_remote" name="hostname_remote" class="form-control" placeholder="Hostname" list="serverlist" required>
				<datalist id="serverlist">
					<?php foreach($this->get('all_servers') as $server) { ?>
					<option value="<?php out($server->hostname)?>">
					<?php } ?>
				</datalist>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-5">
			<label for="account_name_s2s" class="sr-only">Account name</label>
			<div class="input-group">
				<span class="input-group-addon">To: </span>
				<span class="input-group-addon"><span class="glyphicon glyphicon-log-in" title="Server account"></span></span>
				<input type="text" id="account_name_s2s" name="account_name" class="form-control" placeholder="Account name" list="accountlist" required pattern=".*[^\s].*">
				<span class="input-group-addon">@<?php out($this->get('server')->hostname)?></span>
			</div>
		</div>
		<div class="col-sm-3">
			<button type="submit" name="request_access" value="server_account" class="btn btn-primary">Request access</button>
			<a href="/help#getting_access" class="btn btn-info">Help</a>
		</div>
	</div>
</form>
<form method="post" action="<?php out($this->data->relative_request_url)?>">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<h4>Request group access</h4>
	<div class="row">
		<div class="form-group col-sm-5">
			 <div class="input-group">
				<span class="input-group-addon"><label for="account"><span class="glyphicon glyphicon-list-alt" title="Group account"></span><span class="sr-only">Group name</span></label></span>
				<input type="text" id="group_account" name="group_account" class="form-control" placeholder="Group name" list="grouplist" required>
				<datalist id="grouplist">
					<?php foreach($this->get('all_groups') as $group) { ?>
					<option value="<?php out($group->name)?>">
					<?php } ?>
				</datalist>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-5">
			<label for="account_name_group" class="sr-only">Account name</label>
			<div class="input-group">
				<span class="input-group-addon">To: </span>
				<span class="input-group-addon"><span class="glyphicon glyphicon-log-in" title="Server account"></span></span>
				<input type="text" id="account_name_group" name="account_name" class="form-control" placeholder="Account name" list="accountlist" required pattern=".*[^\s].*">
				<span class="input-group-addon">@<?php out($this->get('server')->hostname)?></span>
			</div>
		</div>
		<div class="col-sm-3">
			<button type="submit" name="request_access" value="group" class="btn btn-primary">Request access</button>
			<a href="/help#getting_access" class="btn btn-info">Help</a>
		</div>
	</div>
</form>
<?php } elseif($this->get('server')->authorization == 'automatic LDAP') { ?>
<p>Access to this server is based on LDAP accounts.</p>
<?php } elseif($this->get('server')->authorization == 'manual LDAP') { ?>
<p>Access to this server is based on LDAP accounts.  Contact the server administrators to get access.</p>
<?php } ?>
<?php if(count($this->get('admined_accounts')) > 0) { ?>
<h2>Administrated accounts</h2>
<p>You are an administrator for the following accounts on this server:</p>
<table class="table table-bordered table-striped">
	<thead>
		<tr>
			<th>Account</th>
			<th>Sync status</th>
			<th>Account actions</th>
			<th colspan="2">Access granted for</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($this->get('admined_accounts') as $account) { ?>
		<?php
		$access_list = $account->list_access();
		?>
		<tr>
			<th rowspan="<?php out(max(1, count($access_list)))?>">
				<a href="<?php out($this->data->relative_request_url.'/'.urlencode($account->name))?>" class="serveraccount"><?php out($account->name) ?></a>
				<?php if($account->pending_requests > 0) { ?>
				<a href="<?php out($this->data->relative_request_url.'/'.urlencode($account->name))?>"><span class="badge" title="Pending requests"><?php out(number_format($account->pending_requests))?></span></a>
				<?php } ?>
			</th>
			<td rowspan="<?php out(max(1, count($access_list)))?>">
				<?php if($account->sync_is_pending()) { ?>
				<span class="text-warning">Pending</span>
				<?php } elseif($this->get('server')->sync_status == 'sync success' || ($account->name == 'root' && $this->get('server')->sync_status == 'sync warning')) { ?>
				<span class="text-success">Synced</span>
				<?php } elseif($this->get('server')->sync_status == 'sync warning') { ?>
				<span class="text-warning">Not synced</span>
				<?php } elseif($this->get('server')->sync_status == 'sync failure') { ?>
				<span class="text-danger">Failed</span>
				<?php } ?>
			</td>
			<td rowspan="<?php out(max(1, count($access_list)))?>">
				<a href="<?php out($this->data->relative_request_url.'/accounts/'.urlencode($account->name))?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-cog"></span> Manage account</a>
			</td>
			<?php if(empty($access_list)) { ?>
			<td colspan="3"><em>No-one</em></td>
			<?php } else { ?>
			<?php
			$count = 0;
			foreach($access_list as $access) {
				$entity = $access->source_entity;
				$count++;
				if($count > 1) out('</tr><tr>', ESC_NONE);
				switch(get_class($entity)) {
				case 'User':
			?>
			<td><a href="/users/<?php out($entity->uid, ESC_URL)?>" class="user"><?php out($entity->uid) ?></a></td>
			<td><?php out($entity->name); if(!$entity->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE)?></td>
			<?php
					break;
				case 'ServerAccount':
			?>
			<td><a href="/servers/<?php out($entity->server->hostname, ESC_URL)?>/accounts/<?php out($entity->name, ESC_URL)?>" class="serveraccount"><?php out($entity->name.'@'.$entity->server->hostname) ?></a></td>
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
			}
			?>
			<?php } ?>
		</tr>
		<?php } ?>
	</tbody>
</table>
<?php } ?>
<?php } ?>
