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
* Class that represents a GPG signature that is claimed to sign the associated public key.
*/
class PublicKeySignature extends Record {
	protected $table = 'public_key_signature';

	/**
	* Perform basic validation that the signature at least looks like a valid signature and
	* retrieve the fingerprint and signing date.
	* We cannot check that the signature is actually a valid signature for the public key since we
	* would need to have the signing GPG public key on our keyring to do so.
	*/
	public function validate() {
		$gpg = new gnupg();
		// We assume that the pubkey file that was signed is equal to the uploaded pubkey + a single newline
		$line_endings = array("\n", "\r\n", "\r", ""); // Endings to try in order of expected likelihood
		foreach($line_endings as $line_ending) {
			$info = $gpg->verify($this->public_key->export().$line_ending, $this->signature);
			if(is_array($info)) {
				$sig = reset($info);
				if($sig['validity'] > 0) break;
			} else {
				throw new InvalidArgumentException("Signature doesn't seem valid");
			}
		}
		if($sig['validity'] == 0) {
			#throw new InvalidArgumentException("Signature doesn't validate against pubkey");
		}
		$this->fingerprint = $sig['fingerprint'];
		$this->sign_date = gmdate('Y-m-d H:i:s', $sig['timestamp']);
	}
}
