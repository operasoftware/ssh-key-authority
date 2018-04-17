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
<h1>Servers</h1>
<?php if($this->get('admin')) { ?>
<ul class="nav nav-tabs">
	<li><a href="#list" data-toggle="tab">Server list</a></li>
	<li><a href="#add" data-toggle="tab">Add server</a></li>
</ul>
<?php } ?>

<!-- Tab panes -->
<div class="tab-content">
	<div class="tab-pane fade<?php if(!$this->get('admin')) out(' in active') ?>" id="list">
		<h2 class="sr-only">Server list</h2>
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
									<label for="hostname-search">Hostname (<a href="https://mariadb.com/kb/en/mariadb/regular-expressions-overview/">regexp</a>)</label>
									<input type="text" id="hostname-search" name="hostname" class="form-control" value="<?php out($this->get('filter')['hostname'])?>" autofocus>
								</div>
								<div class="form-group">
									<label for="ipaddress-search">IP address</label>
									<input type="text" id="ipaddress-search" name="ip_address" class="form-control" value="<?php out($this->get('filter')['ip_address'])?>">
								</div>
							</div>
							<div class="col-sm-3">
								<h4>Key management</h4>
								<?php
								$options = array();
								$options['keys'] = 'Managed by SSH Key Authority';
								$options['other'] = 'Managed by another system';
								$options['none'] = 'Unmanaged';
								$options['decommissioned'] = 'Decommissioned';
								foreach($options as $value => $label) {
									$checked = in_array($value, $this->get('filter')['key_management']) ? ' checked' : '';
								?>
								<div class="checkbox"><label><input type="checkbox" name="key_management[]" value="<?php out($value)?>"<?php out($checked) ?>> <?php out($label) ?></label></div>
								<?php } ?>
							</div>
							<div class="col-sm-2">
								<h4>Sync status</h4>
								<?php
								$options = array();
								$options['sync success'] = 'Sync success';
								$options['sync warning'] = 'Sync warning';
								$options['sync failure'] = 'Sync failure';
								$options['not synced yet'] = 'Not synced yet';
								foreach($options as $value => $label) {
									$checked = in_array($value, $this->get('filter')['sync_status']) ? ' checked' : '';
								?>
								<div class="checkbox"><label><input type="checkbox" name="sync_status[]" value="<?php out($value)?>"<?php out($checked) ?>> <?php out($label) ?></label></div>
								<?php } ?>
							</div>
						</div>
						<button type="submit" class="btn btn-primary">Display results</button>
					</form>
				</div>
			</div>
		</div>
		<p><?php $total = count($this->get('servers')); out(number_format($total).' server'.($total == 1 ? '' : 's').' found')?></p>
		<table class="table table-hover table-condensed">
			<thead>
				<tr>
					<th>Hostname</th>
					<th>Config</th>
					<?php if($this->get('admin')) { ?>
					<th>Admins</th>
					<?php } ?>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach($this->get('servers') as $server) {
					if($server->key_management != 'keys') {
						$syncclass = '';
					} else {
						switch($server->sync_status) {
						case 'not synced yet': $syncclass = 'warning'; break;
						case 'sync failure':   $syncclass = 'danger';  break;
						case 'sync success':   $syncclass = 'success'; break;
						case 'sync warning':   $syncclass = 'warning'; break;
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
						<a href="/servers/<?php out($server->hostname, ESC_URL) ?>" class="server"><?php out($server->hostname) ?></a>
						<?php if($server->pending_requests > 0 && $this->get('admin')) { ?>
						<a href="/servers/<?php out($server->hostname, ESC_URL) ?>"><span class="badge" title="Pending requests"><?php out(number_format($server->pending_requests)) ?></span></a>
						<?php } ?>
					</td>
					<td class="nowrap">
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
					<?php if($this->get('admin')) { ?>
					<?php if(is_null($server->admins)) { ?>
					<td<?php if($server->key_management == 'keys') out(' class="danger"', ESC_NONE)?>>Server has no administrators</td>
					<?php } else { ?>
					<td>
						<?php
						$admins = explode(',', $server->admins);
						$admin_list = '';
						foreach($admins as $admin) {
							$type = substr($admin, 0, 1);
							$name = substr($admin, 2);
							if($type == 'G') {
								$admin_list .= '<span class="glyphicon glyphicon-list-alt"></span> ';
							}
							$admin_list .= hesc($name).', ';
						}
						$admin_list = substr($admin_list, 0, -2);
						out($admin_list, ESC_NONE);
						?>
					</td>
					<?php } ?>
					<?php } ?>
					<td class="<?php out($syncclass)?> nowrap"><?php if($server->key_management != 'none') out($sync_details) ?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
	<?php if($this->get('admin')) { ?>
	<div class="tab-pane fade" id="add">
		<h2 class="sr-only">Add server</h2>
		<div class="alert alert-info">
			See <a href="/help#sync_setup" class="alert-link">the sync setup instructions</a> for how to set up the server for key synchronization.
		</div>
		<form method="post" action="<?php out($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="hostname">Server hostname</label>
				<input type="text" id="hostname" name="hostname" class="form-control" required>
			</div>
			<div class="form-group">
				<label for="port">SSH port number</label>
				<input type="number" id="port" name="port" class="form-control" value="22" required>
			</div>
			<div class="form-group">
				<label for="server_admin">Administrators</label>
				<input type="text" id="server_admins" name="admins" class="form-control hidden" required>
				<input type="text" id="server_admin" name="admin" class="form-control" placeholder="Type user/group name and press 'Enter' key" list="adminlist">
				<datalist id="adminlist">
					<?php foreach($this->get('all_users') as $user) { ?>
					<option value="<?php out($user->uid)?>" label="<?php out($user->name)?>">
					<?php } ?>
					<?php foreach($this->get('all_groups') as $group) { ?>
					<option value="<?php out($group->name)?>" label="<?php out($group->name)?>">
					<?php } ?>
				</datalist>
			</div>
			<button type="submit" name="add_server" value="1" class="btn btn-primary">Add server to key management</button>
		</form>
	</div>
	<?php } ?>
</div>
