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
$entity = $this->get('entity');
switch(get_class($entity)) {
	case 'ServerAccount': $account = $entity; $server = $entity->server; break;
	case 'Group': $group = $entity; break;
}
$remote_entity = $this->get('remote_entity');
$mode = $this->get('mode');
$options = $this->get('options');
switch(get_class($remote_entity)) {
	case 'User': $remote_entity_name = $remote_entity->uid; break;
	case 'ServerAccount': $remote_entity_name = $remote_entity->name.'@'.$remote_entity->server->hostname; break;
	case 'Group': $remote_entity_name = $remote_entity->name; break;
}
?>
<h1><?php if($mode == 'create') out('Grant'); else out('Modify')?> access</h1>
<form method="post" action="<?php outurl($this->data->relative_request_url)?>" id="access_options">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<?php
	switch(get_class($remote_entity)) {
	case 'User':
		$re_url = '/users/'.urlencode($remote_entity->uid);
		?>
	<input type="hidden" name="username" value="<?php out($remote_entity->uid)?>">
	<?php
		break;
	case 'ServerAccount':
		$re_url = '/servers/'.urlencode($remote_entity->server->hostname).'/accounts/'.urlencode($remote_entity->name);
		?>
	<input type="hidden" name="account" value="<?php out($remote_entity->name)?>">
	<input type="hidden" name="hostname" value="<?php out($remote_entity->server->hostname)?>">
	<?php
		break;
	case 'Group':
		$re_url = '/groups/'.urlencode($remote_entity->name);
		?>
	<input type="hidden" name="group" value="<?php out($remote_entity->name)?>">
		<?php
		break;
	}
	?>
	<p>
		You are <?php if($mode == 'create') out('granting'); else out('modifying the')?> SSH access to
		<?php if(isset($server)) { ?>
		<a href="<?php outurl('/servers/'.urlencode($server->hostname).'/accounts/'.urlencode($account->name))?>" class="serveraccount"><?php out($account->name.'@'.$server->hostname)?></a>
		<?php } elseif(isset($group)) { ?>
		resources in the <a href="<?php outurl('/groups/'.urlencode($group->name))?>"><?php out($group->name)?></a> group
		<?php } ?>
		for
		<a href="<?php outurl($re_url)?>" class="<?php out(strtolower(get_class($remote_entity)))?>"><?php out($remote_entity_name)?></a>.
	</p>
	<?php if($mode == 'create') { ?>
	<div class="form-group">
		<div class="panel-group">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">
						<a data-toggle="collapse" href="#advanced_options">
							Advanced options <span class="caret"></span>
						</a>
					</h3>
				</div>
				<div id="advanced_options" class="collapse">
	<?php } ?>
					<div class="panel-body">
						<p>
							Presets:
							<button type="button" class="btn btn-default btn-xs" data-preset="default">Default</button>
							<button type="button" class="btn btn-default btn-xs" data-preset="command">Command</button>
							<button type="button" class="btn btn-default btn-xs" data-preset="dbbackup">DB backup</button>
						</p>
						<div class="checkbox">
							<label><input type="checkbox" name="access_option[command][enabled]"<?php if(isset($options['command'])) out(' checked'); ?>> Specify command (<code>command=&quot;command&quot;</code>)</label>
						</div>
						<div class="form-group">
							<input type="text" id="command_value" name="access_option[command][value]" value="<?php if(isset($options['command'])) out($options['command']->value); ?>" class="form-control">
						</div>
						<div class="checkbox">
							<label><input type="checkbox" name="access_option[from][enabled]"<?php if(isset($options['from'])) out(' checked'); ?>> Restrict source address (<code>from=&quot;<abbr title="A pattern-list is a comma-separated list of patterns.  Each pattern can be either a hostname or an IP address, with wildcards (* and ?) allowed.">pattern-list</abbr>&quot;</code>)</label>
						</div>
						<div class="form-group">
							<input type="text" id="from_value" name="access_option[from][value]" value="<?php if(isset($options['from'])) out($options['from']->value); ?>" class="form-control">
						</div>
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
	<?php if($mode == 'create') { ?>
				</div>
			</div>
		</div>
	</div>
	<?php } ?>
	<div class="form-group row">
		<div class="col-md-8">
			<button type="submit" name="<?php if($mode == 'create') out('add_access'); else out('update_access')?>" value="2" class="btn btn-primary btn-block"><?php if($mode == 'create') out('Confirm'); else out('Modify')?> access</button>
		</div>
		<div class="col-md-4">
			<?php if(isset($server)) { ?>
			<a href="<?php outurl('/servers/'.urlencode($server->hostname).'/accounts/'.urlencode($account->name))?>" class="btn btn-default btn-block">Cancel</a>
			<?php } elseif(isset($group)) { ?>
			<a href="<?php outurl('/groups/'.urlencode($group->name))?>" class="btn btn-default btn-block">Cancel</a>
			<?php } ?>
		</div>
	</div>
</form>
