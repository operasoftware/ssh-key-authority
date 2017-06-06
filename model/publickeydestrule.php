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

/**
* Class that represents a destination restriction rule on a public key (based on account name and
* server hostname). Wildcards (*) are possible for use in either or both fields.
* Public keys with one or more PublicKeyDestRule objects associated with them will only be synced
* to a destination if it matches at least one of those rules.
*/
class PublicKeyDestRule extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'public_key_dest_rule';
}
