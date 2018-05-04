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
$admin_mail = $this->get('admin_mail');
$baseurl = $this->get('baseurl');
$security_config = $this->get('security_config');
?>
<div class="panel-group" id="help">
	<h1>Help</h1>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#getting_started">
					Getting started
				</a>
			</h2>
		</div>
		<div id="getting_started" class="panel-collapse collapse">
			<div class="panel-body">
				<h3>Generating an SSH keypair</h3>
				<?php keygen_help(null) ?>
				<h3>Uploading a public key</h3>
				<p>You can upload a new public key to your account from the <a href="<?php outurl('/')?>">home</a> page.</p>
			</div>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#concepts">
					Concepts
				</a>
			</h2>
		</div>
		<div id="concepts" class="panel-collapse collapse">
			<div class="panel-body">
				<h3>Iconography</h3>
				<p>Most objects that are known by SSH Key Authority are represented by icons:</p>
				<h4><span class="glyphicon glyphicon-hdd"></span> Servers</h4>
				<p>Physical or virtual servers.</p>
				<h4><span class="glyphicon glyphicon-log-in"></span> Server accounts</h4>
				<p>Accounts on servers (eg. root@myserver is a server account).</p>
				<h4><span class="glyphicon glyphicon-user"></span> Users</h4>
				<p>Users of SSH Key Authority.</p>
				<h4><span class="glyphicon glyphicon-list-alt"></span> Groups</h4>
				<p>Collections of users or server accounts.</p>
			</div>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#getting_access">
					Getting access to a server
				</a>
			</h4>
		</div>
		<div id="getting_access" class="panel-collapse collapse">
			<div class="panel-body">
				<p>Begin by browsing the <a href="<?php outurl('/servers')?>">server list</a>.  Click on the server that you need access to.</p>
				<p>You should see a "request access" form, in which you will need to enter the name of the account on the server that you are requesting access for.  For example, if you need access to the <i>root</i> account, then that is what you should enter in this field.</p>
				<p>Once you have successfully requested access, the designated server administators will be sent a mail informing them of your request and you will need to wait for one of them to grant your access.</p>
				<p class="alert alert-info">You will need to have a public key uploaded for your access to work.  See the <a data-toggle="collapse" data-parent="#help" href="#getting_started" class="alert-link">getting started guide</a>.</p>
			</div>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#add_server">
					Adding a server to SSH Key Authority
				</a>
			</h2>
		</div>
		<div id="add_server" class="panel-collapse collapse">
			<div class="panel-body">
				<p>Contact <a href="mailto:<?php out($admin_mail)?>"><?php out($admin_mail)?></a> to have your server(s) added to SSH Key Authority.</p>
			</div>
		</div>
	</div>
	<h2>Frequently asked questions</h2>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#sync_error">
					What does this sync error for my server mean?
				</a>
			</h3>
		</div>
		<div id="sync_error" class="panel-collapse collapse">
			<div class="panel-body">
				<dl class="spaced">
					<dt>SSH connection failed</dt>
					<dd>SSH key authority was unable to establish an SSH connection to your server.  This could indicate that the server is offline or otherwise unreachable, or that the SSH server is not running.</dd>
					<dt>SSH host key verification failed</dt>
					<dd>SSH key authority was able to open an SSH connection to your server, but the host key no longer matches the one that is on record for your server.  If this is expected (eg. your server has been migrated to a new host), you can reset the host key on the "Settings" page of your server. Press the "Clear" button for the host key fingerprint and then "Save changes".</dd>
					<?php if(!isset($security_config['host_key_collision_protection']) || $security_config['host_key_collision_protection'] == 1) { ?>
					<dt>SSH host key collision</dt>
					<dd>Your server has the same SSH host key as another server. This should be corrected by regenerating the SSH host keys on one or both of the affected servers.</dd>
					<?php } ?>
					<dt>SSH authentication failed</dt>
					<dd>Although SSH key authority was able to connect to your server via SSH, it failed to log in.  See the guides for setting up <a data-toggle="collapse" data-parent="#help" href="#sync_setup">full account syncing</a> or <a data-toggle="collapse" data-parent="#help" href="#legacy_sync_setup">legacy root account syncing</a>.</dd>
					<dt>SFTP subsystem failed</dt>
					<dd>SSH key authority currently relies on SFTP in order to determine if an account's key file needs updating or not.  We are hoping to remove this dependency at some point, but for now your server needs to support SFTP (which openssh does by default) for key synchronization to work.</dd>
					<dt><em>x</em> account(s) failed to sync</dt>
					<dt>Failed to clean up <em>x</em> file(s)</dt>
					<dd>
						SSH key authority could not write to at least one of the files in <code>/var/local/keys-sync</code> (or <code>/root/.ssh/authorized_keys2</code> for legacy sync).  This is typically caused by one of 3 possibilities:
						<ul>
							<li>Issues with file ownership - this directory and all files in it must be owned by the keys-sync user</li>
							<li>Read-only filesystem</li>
							<li>Disk full</li>
						</ul>
					</dd>
					<dt>Multiple hosts with same IP address</dt>
					<dd>At least one other host managed by SSH Key Authority resolves to the same IP address as your server.  SSH Key Authority will refuse to sync to either server until this is resolved.</dd>
					<?php if(isset($security_config['hostname_verification']) && $security_config['hostname_verification'] >= 3) { ?>
					<dt>Hostnames file missing</dt>
					<dd>The <code>/var/local/keys-sync/.hostnames</code> file does not exist on the server. SSH Key Authority uses the contents of this file to verify that it is allowed to sync to your server.</dd>
					<dt>Hostname check failed</dt>
					<dd>The server name was not found in <code>/var/local/keys-sync/.hostnames</code> when SSH Key Authority tried to sync to your server.</dd>
					<?php } ?>
				</dl>
			</div>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#sync_warning">
					What does this sync warning for my server mean?
				</a>
			</h3>
		</div>
		<div id="sync_warning" class="panel-collapse collapse">
			<div class="panel-body">
				<dl class="spaced">
					<dt>Key directory does not exist</dt>
					<dd>Your server has not been set up for <a data-toggle="collapse" data-parent="#help" href="#sync_setup">full account syncing</a>. The <i>root</i> account <strong>is</strong> being synced, but other accounts are not.</dd>
					<dt>Using legacy sync method</dt>
					<dd>Your server <strong>has</strong> been set up for <a data-toggle="collapse" data-parent="#help" href="#sync_setup">full account syncing</a> (stage 1), but the authentication on your server has not been switched over to keys control (stage 2). Legacy syncing is still being used, so only the <i>root</i> account sync is taking effect.</dd>
				</dl>
			</div>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#sync_setup">
					How do I set up my server to sync access for all accounts?
				</a>
			</h3>
		</div>
		<div id="sync_setup" class="panel-collapse collapse">
			<div class="panel-body">
				<h5>Stage 1</h5>
				<p>If SSH Key Authority is reporting "Key directory does not exist" for your server, then Stage 1 is required.</p>
				<ol>
					<li>Create keys-sync account: <code>adduser --system --disabled-password --home /var/local/keys-sync --shell /bin/sh keys-sync</code>
					<li>Change the permissions of <code>/var/local/keys-sync</code> to 711: <code>chmod 0711 /var/local/keys-sync</code>
					<li>Create <code>/var/local/keys-sync/keys-sync</code> file (owned by keys-sync, permissions 0644) with the following SSH key in it:
						<pre><?php out($this->get('keys-sync-pubkey'))?></pre>
					</li>
					<?php if(isset($security_config['hostname_verification']) && $security_config['hostname_verification'] >= 3) { ?>
					<li>Create <code>/var/local/keys-sync/.hostnames</code> text file (owned by keys-sync, permissions 0644) with the server's hostname in it</li>
					<?php } ?>
				</ol>
				<h5>Verify Stage 1 success</h5>
				<p>Once Stage 1 has been deployed to your server, trigger a resync from SSH Key Authority. The server should no longer have the "Key directory does not exist" warning after syncing (the "Using legacy sync method" warning is expected at this point instead). You can check the contents of the <code>/var/local/keys-sync</code> directory to make sure that the access looks right.</p>
				<h5>Stage 2</h5>
				<ol>
					<li>
						Reconfigure SSH (<code>/etc/ssh/sshd_config</code>) to use:
						<ul>
							<li>"<code>AuthorizedKeysFile /var/local/keys-sync/%u</code>"
							<li>"<code>StrictModes no</code>"
						</ul>
					<li>Restart SSH server
				</ol>
				<p>This stage stops any .ssh/authorized_keys* files from having any effect and transfers login authentication authority over to the /var/local/keys-sync directory.</p>
				<p>After triggering a resync from SSH Key Authority, your server should be listed as "Synced successfully".</p>
			</div>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#legacy_sync_setup">
					How do I set up my server for legacy (root-only) sync?
				</a>
			</h3>
		</div>
		<div id="legacy_sync_setup" class="panel-collapse collapse">
			<div class="panel-body">
				<p class="alert alert-warning">While this sync method is simpler to set up, we recommend setting up <a data-toggle="collapse" data-parent="#help" href="#sync_setup">full account syncing</a> where possible.</p>
				<p>Add the following to the <code>/root/.ssh/authorized_keys</code> file (create it if it does not exist):</p>
				<pre><?php out($this->get('keys-sync-pubkey'))?></pre>
				<p>The <code>/root</code> and <code>/root/.ssh</code> directories must be accessible <em>only by root</em>.</p>
			</div>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">
				<a data-toggle="collapse" data-parent="#help" href="#grant_access">
					How do I grant access to an account on my server?
				</a>
			</h3>
		</div>
		<div id="grant_access" class="panel-collapse collapse">
			<div class="panel-body">
				<p>For access to accounts by employees:</p>
				<ol>
					<li>Go to your server's page (ie. <code><?php out($baseurl)?>/servers/&lt;hostname&gt;</code>).</li>
					<li>If the account is not listed yet, add it with the "Create account" form.</li>
					<li>Click "Manage account" for the relevant account.</li>
					<li>In the "Add user to account" form, enter the user's intranet account name and submit.</li>
				</ol>
				<p>For server-to-server access, assuming that both of the servers involved are managed by SSH Key Authority:</p>
				<p>Example: <code>foo@source.example.com</code> needs SSH access to <code>bar@destination.example.com</code></p>
				<ol>
					<li>Go to the admin page for source.example.com (ie. <code><?php out($baseurl)?>/servers/source.example.com</code>).</li>
					<li>Add the "foo" account to keys ("Manage this account with SSH Key Authority") if it is not already listed.</li>
					<li>Go to the manage account page for "foo".</li>
					<li>On the Public keys tab, add the SSH public key for the foo@source.example.com account.</li>
					<li>Go to the admin page for destination.example.com (ie. <code><?php out($baseurl)?>/servers/destination.example.com</code>).</li>
					<li>Add the "bar" account to keys ("Manage this account with SSH Key Authority") if it is not already listed.</li>
					<li>Go to the manage account page for "bar".</li>
					<li>On the Access tab, add server-to-server access for foo@source.example.com.</li>
				</ol>
				<p>In the above example if source.example.com is not yet known by SSH Key Authority, please contact <a href="mailto:<?php out($admin_mail)?>"><?php out($admin_mail)?></a> to add it to the system.</p>
			</div>
		</div>
	</div>
</div>
