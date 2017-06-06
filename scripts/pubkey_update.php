#!/usr/bin/php
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

chdir(__DIR__);
require('../core.php');

$pubkeys = $pubkey_dir->list_public_keys();
foreach($pubkeys as $pubkey) {
	try {
		$pubkey->import($pubkey->export(), null, true);
		$pubkey->update();
	} catch(InvalidArgumentException $e) {
		echo "Invalid public key {$pubkey->id}\n";
	}
}
