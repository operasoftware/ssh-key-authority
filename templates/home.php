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
<h1>Keys management</h1>
<p>Welcome to the SSH Key Authority server.</p>
<?php if(count($this->get('user_keys')) == 0) { ?>
<h2>Getting started</h2>
<p>To start using the key management system, you must first generate a "key pair".  The instructions for doing this vary based on your computer's Operating System (OS).</p>
<?php keygen_help('below') ?>
<form method="post" action="/">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<div class="form-group">
		<label for="public_key">Public key</label>
		<textarea class="form-control" rows="4" id="add_public_key" name="add_public_key" required></textarea>
	</div>
	<div class="form-group"><button class="btn btn-primary btn-lg btn-block">Add public key</button></div>
</form>
<?php } else { ?>
<h2>Your public keys</h2>
<form method="post" action="<?php out($this->data->relative_request_url)?>">
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
			<?php foreach($this->get('user_keys') as $key) { ?>
			<tr>
				<td><?php out($key->type) ?></td>
				<td>
					<a href="/users/<?php out($this->get('uid'), ESC_URL)?>/pubkeys/<?php out($key->id, ESC_URL)?>#info">
						<span class="fingerprint_md5"><?php out($key->fingerprint_md5) ?></span>
						<span class="fingerprint_sha256"><?php out($key->fingerprint_sha256) ?></span>
					</a>
				</td>
				<td>
					<?php if(count($key->list_signatures()) > 0) { ?><a href="/users/<?php out($this->get('uid'), ESC_URL)?>/pubkeys/<?php out($key->id, ESC_URL)?>#sig"><span class="glyphicon glyphicon-pencil" title="Signed key"></span></a><?php } ?>
					<?php if(count($key->list_destination_rules()) > 0) { ?><a href="/users/<?php out($this->get('uid'), ESC_URL)?>/pubkeys/<?php out($key->id, ESC_URL)?>#dest"><span class="glyphicon glyphicon-pushpin" title="Destination-restricted"></span></a><?php } ?>
				</td>
				<td><?php out($key->keysize) ?></td>
				<td><?php out($key->comment) ?></td>
				<td>
					<a href="/users/<?php out($this->get('uid'), ESC_URL)?>/pubkeys/<?php out($key->id, ESC_URL)?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-cog"></span> Manage public key</a>
					<button type="submit" name="delete_public_key" value="<?php out($key->id) ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Delete public key</button>
				</td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
</form>
<p><button id="add_key_button" class="btn btn-default">Add another public key</button></p>
<form method="post" action="/" class="hidden" id="add_key_form">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<div class="form-group">
		<label for="add_public_key">Public key</label>
		<textarea class="form-control" rows="4" id="add_public_key" name="add_public_key" required></textarea>
	</div>
	<div class="form-group row">
		<div class="col-md-8">
			<button type="submit" class="btn btn-primary btn-lg btn-block">Add public key</button>
		</div>
		<div class="col-md-2">
			<button type="button" class="btn btn-info btn-lg btn-block">Help</button>
		</div>
		<div class="col-md-2">
			<button type="button" class="btn btn-default btn-lg btn-block">Cancel</button>
		</div>
	</div>
	<div id="help" class="hidden">
		<?php keygen_help('above') ?>
	</div>
</form>
<?php if(count($this->get('admined_servers')) > 0) { ?>
<h2>Your servers</h2>
<p>You are listed as an administrator for the following servers:</p>
<table class="table">
	<thead>
		<tr>
			<th>Hostname</th>
			<th>Config</th>
			<th>Admins</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach($this->get('admined_servers') as $server) {
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
			<td rowspan="2">
				<a href="/servers/<?php out($server->hostname, ESC_URL) ?>" class="server"><?php out($server->hostname) ?></a>
				<?php if($server->pending_requests > 0) { ?>
				<a href="/servers/<?php out($server->hostname, ESC_URL) ?>#requests"><span class="badge" title="Pending requests"><?php out(number_format($server->pending_requests)) ?></span></a>
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
			<td rowspan="2" class="<?php out($class)?>"><?php out($sync_details) ?></td>
		</tr>
		<tr>
			<td colspan="2" class="indented">
				<dl class="oneline">
					<?php foreach($server->list_accounts() as $server_account) { ?>
					<dt><a href="/servers/<?php out($server->hostname, ESC_URL)?>/accounts/<?php out($server_account->name, ESC_URL)?>" class="serveraccount"><?php out($server_account->name) ?></a>:</dt>
					<?php
					$list = array();
					foreach($server_account->list_access() as $access) {
						$entity = $access->source_entity;
						switch(get_class($entity)) {
						case 'User':
							$list[] = hesc($entity->uid);
							break;
						case 'ServerAccount':
							$list[] = hesc($entity->name.'@'.$entity->server->hostname);
							break;
						case 'Group':
							$list[] = '<span class="glyphicon glyphicon-list-alt"></span> '.hesc($entity->name);
							break;
						}
					}
					?>
					<dd><?php out(implode(', ', $list), ESC_NONE)?></dd>
					<?php } ?>
				</dl>
			</td>
		</tr>
		<?php } ?>
	</tbody>
</table>
<?php } ?>
<?php } ?>
