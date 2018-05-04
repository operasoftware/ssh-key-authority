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
<h1>Public keys</h1>
<div class="panel-group">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">Filter options</h3>
		</div>
		<div id="search_filter">
			<div class="panel-body">
				<form>
					<div class="row">
						<div class="col-md-6 form-group">
							<label for="fingerprint-search">Fingerprint</label>
							<input type="text" id="fingerprint-search" name="fingerprint" class="form-control" value="<?php out($this->get('filter')['fingerprint'])?>">
						</div>
						<div class="col-md-2 form-group">
							<label for="type-search">Key type</label>
							<input type="text" id="type-search" name="type" class="form-control" value="<?php out($this->get('filter')['type'])?>">
						</div>
						<div class="col-md-2 form-group">
							<label for="keysize-min">Min key size</label>
							<div class="input-group">
								<span class="input-group-addon">≥</span>
								<input type="text" id="keysize-min" name="keysize-min" class="form-control" value="<?php out($this->get('filter')['keysize-min'])?>">
							</div>
						</div>
						<div class="col-md-2 form-group">
							<label for="keysize-max">Max key size</label>
							<div class="input-group">
								<span class="input-group-addon">≤</span>
								<input type="text" id="keysize-max" name="keysize-max" class="form-control" value="<?php out($this->get('filter')['keysize-max'])?>">
							</div>
						</div>
					</div>
					<button type="submit" class="btn btn-primary">Display results</button>
				</form>
			</div>
		</div>
	</div>
</div>
<p><?php $total = count($this->get('pubkeys')); out(number_format($total).' public key'.($total == 1 ? '' : 's').' found')?></p>
<table class="table table-striped">
	<thead>
		<tr>
			<th class="fingerprint">Fingerprint</th>
			<th>Type</th>
			<th>Size</th>
			<th>Comment</th>
			<th>Owner</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach($this->get('pubkeys') as $pubkey) {
		?>
		<tr>
			<td>
				<a href="<?php outurl('/pubkeys/'.urlencode($pubkey->id))?>">
					<span class="fingerprint_md5"><?php out($pubkey->fingerprint_md5)?></span>
					<span class="fingerprint_sha256"><?php out($pubkey->fingerprint_sha256)?></span>
				</a>
			</td>
			<td class="nowrap"><?php out($pubkey->type)?></td>
			<td<?php if($pubkey->keysize < 4095) out(' class="danger"', ESC_NONE)?>><?php out($pubkey->keysize)?></td>
			<td><?php out($pubkey->comment)?></td>
			<td>
				<?php
				switch(get_class($pubkey->owner)) {
				case 'User':
				?>
				<a href="<?php outurl('/users/'.urlencode($pubkey->owner->uid))?>" class="user"><?php out($pubkey->owner->uid)?></a>
				<?php if(!$pubkey->owner->active) out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?>
				<?php
					break;
				case 'ServerAccount':
				?>
				<a href="<?php outurl('/servers/'.urlencode($pubkey->owner->server->hostname))?>/accounts/<?php out($pubkey->owner->name, ESC_URL)?>" class="serveraccount"><?php out($pubkey->owner->name.'@'.$pubkey->owner->server->hostname)?></a>
				<?php if($pubkey->owner->server->key_management == 'decommissioned') out(' <span class="label label-default">Inactive</span>', ESC_NONE) ?>
				<?php
					break;
				}
				?>
			</td>
		</tr>
		<?php
		}
		?>
	</tbody>
</table>
