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
$owner = $this->get('pubkey')->owner;
?>
<h1>
	Public key '<?php out($this->get('pubkey')->comment)?>' for
	<?php
	switch(get_class($owner)) {
	case 'User':
		$name = $owner->name;
	?>
	<a href="<?php outurl('/users/'.urlencode($owner->uid))?>" class="user"><?php out($name)?></a>
	<?php
		break;
	case 'ServerAccount':
		$name = $owner->name.'@'.$owner->server->hostname;
	?>
	<a href="<?php outurl('/servers/'.urlencode($owner->server->hostname).'/accounts/'.urlencode($owner->name))?>" class="serveraccount"><?php out($name)?></a>
	<?php
		break;
	}
	?>
</h1>
<?php if($this->get('user_is_owner') || $this->get('admin')) { ?>
<ul class="nav nav-tabs">
	<li><a href="#info" data-toggle="tab">Information</a></li>
	<li><a href="#sig" data-toggle="tab">Key signing</a></li>
	<li><a href="#dest" data-toggle="tab">Destination restrictions</a></li>
</ul>
<?php } ?>
<div class="tab-content">
	<div class="tab-pane <?php if(!$this->get('user_is_owner') || $this->get('admin')) out(' active') ?>" id="info">
		<h2 class="sr-only">Information</h2>
		<dl>
			<dt>Key data</dt>
			<dd><pre><?php out($this->get('pubkey')->export())?></pre></dd>
			<dt>Key size</dt>
			<dd><?php out($this->get('pubkey')->keysize)?></dd>
			<dt>Fingerprint (MD5)</dt>
			<dd><?php out($this->get('pubkey')->fingerprint_md5)?></dd>
			<dt>Randomart (MD5)</dt>
			<dd><pre class="ascii-art"><?php out($this->get('pubkey')->randomart_md5)?></pre></dd>
			<dt>Fingerprint (SHA256)</dt>
			<dd><?php out($this->get('pubkey')->fingerprint_sha256)?></dd>
			<dt>Randomart (SHA256)</dt>
			<dd><pre class="ascii-art"><?php out($this->get('pubkey')->randomart_sha256)?></pre></dd>
			<dt>Upload Date</dt>
			<dd><?php out($this->get('pubkey')->upload_date) ?></dd>
			<?php if($this->config()['general']['key_expiration_enabled'] == 1) { ?>
			<dt>Expiration Date</dt>
			<dd>
			<?php 
			$date = $this->get('pubkey')->upload_date;
			$expiration_days = $this->config()['general']['key_expiration_days'];
			$expiration_date = strtotime($date . ' + ' . $expiration_days . ' days');
			$expiration_time_in_days = round(($expiration_date - time()) / (60 * 60 * 24));
			out(date('Y-m-d H:i:s', $expiration_date) . ' (' . $expiration_time_in_days . ' days left)');
			?>
			</dd>
			<?php } ?>
		</dl>
	</div>
	<?php if($this->get('user_is_owner') || $this->get('admin')) { ?>
	<div class="tab-pane" id="sig">
		<h2 class="sr-only">Key signing</h2>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>" enctype="multipart/form-data">
			<?php if(count($this->get('signatures')) == 0) { ?>
			<p>No signatures have been uploaded for this key yet.</p>
			<?php } else { ?>
			<table class="table table-bordered table-striped">
				<thead>
					<tr>
						<th>Signing key</th>
						<th>Signed on</th>
						<th>Uploaded on</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->get('signatures') as $sig) { ?>
					<tr>
						<td><?php out($sig->fingerprint)?></td>
						<td><?php out($sig->sign_date)?></td>
						<td><?php out($sig->upload_date)?></td>
						<td><button type="submit" name="delete_signature" value="<?php out($sig->id)?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Delete signature</button></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
			<?php } ?>
			<h3>Add signature</h3>
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label>
					Signature file
					<input type="file" name="signature" class="form-control">
				</label>
			</div>
			<div class="form-group">
				<button type="submit" name="add_signature" value="1" class="btn btn-primary">Upload signature</button>
			</div>
		</form>
	</div>
	<div class="tab-pane" id="dest">
		<h2 class="sr-only">Destination restrictions</h2>
		<?php if(count($this->get('dest_rules')) == 0) { ?>
		<p>This key will currently be synced to all accounts and servers that <?php out($name)?> is granted access to.  To restrict this key to a subset of that list, add rules below.</p>
		<?php } else { ?>
		<p>This key will only be synced to accounts and servers that <?php out($name)?> is granted access to that also match the following rules:</p>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table table-bordered table-striped">
				<thead>
					<tr>
						<th>Account name</th>
						<th>Hostname</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->get('dest_rules') as $rule) { ?>
					<tr>
						<td><?php out($rule->account_name_filter)?></td>
						<td><?php out($rule->hostname_filter)?></td>
						<td><button type="submit" name="delete_dest_rule" value="<?php out($rule->id)?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Delete rule</button></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</form>
		<?php } ?>
		<form method="post" action="<?php outurl($this->data->relative_request_url)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<h3>Add new rule</h3>
			<p>You can make use of wildcards (<kbd>*</kbd>) in each field below.</p>
			<div class="form-group">
				<label for="account_name_filter">Account name</label>
				<input type="text" id="account_name_filter" name="account_name_filter" class="form-control" value="*" required>
			</div>
			<div class="form-group">
				<label for="hostname_filter">Hostname</label>
				<input type="text" id="hostname_filter" name="hostname_filter" class="form-control" value="*" required>
			</div>
			<div class="form-group">
				<button type="submit" name="add_dest_rule" value="1" class="btn btn-primary btn-block">Add rule</button>
			</div>
		</form>
	</div>
	<?php } ?>
</div>
