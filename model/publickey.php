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
* Class that represents a stored SSH public key
*/
class PublicKey extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'public_key';

	/**
	* Import all key data from a provided OpenSSH-text-format public key.
	* Cope with some possible correctable whitespace data issues.
	* @param string $key data to import
	* @param string|null $uid if not null, used if key has no comment to generate a standard comment
	* @param bool $force if true, enable the use of lower security keys
	* @throws InvalidArgumentException if the public key cannot be parsed or is not sufficiently secure
	*/
	public function import($key, $uid = null, $force = false) {
		// Remove newlines (often included by accident) and trim
		$key = str_replace(array("\r", "\n"), array(), trim($key));

		// Initial sanity check and determine minimum length for algorithm
		if(preg_match('|^(ssh-[a-z]{3}) ([A-Za-z0-9+/]+={0,2})(?: (.*))?$|', $key, $matches)) {
			$minbits = 4096;
		} elseif(preg_match('|^(ecdsa-sha2-nistp[0-9]+) ([A-Za-z0-9+/]+={0,2})(?: (.*))?$|', $key, $matches)) {
			$minbits = 384;
		} elseif(preg_match('|^(ssh-ed25519) ([A-Za-z0-9+/]+={0,2})(?: (.*))?$|', $key, $matches)) {
			$minbits = 256;
		} else {
			throw new InvalidArgumentException("Public key doesn't look valid");
		}

		$this->type = $matches[1];
		$this->keydata = $matches[2];
		if(isset($matches[3])) {
			$this->comment = $matches[3];
		} elseif(is_null($uid)) {
			$this->comment = date('Y-m-d');
		} else {
			$this->comment = $uid.'-'.date('Y-m-d');
		}
		$algorithm = $this->get_openssh_info();
		$hash_md5 = md5(base64_decode($this->keydata));
		$hash_sha256 = hash('sha256', base64_decode($this->keydata), true);
		$this->fingerprint_md5 = rtrim(chunk_split($hash_md5, 2, ':'), ':');
		$this->fingerprint_sha256 = rtrim(base64_encode($hash_sha256), '=');
		$this->randomart_md5 = $this->generate_randomart($hash_md5, "{$algorithm} {$this->keysize}", 'MD5');
		$this->randomart_sha256 = $this->generate_randomart(bin2hex($hash_sha256), "{$algorithm} {$this->keysize}", 'SHA256');

		if($this->keysize < $minbits && !$force) {
			throw new InvalidArgumentException("Insufficient bits in public key");
		}
	}

	/**
	* Determine the algorithm and keysize of a key by passing it to OpenSSH's ssh-keygen utility.
	* @return string algorithm in use
	*/
	public function get_openssh_info() {
		$filename = tempnam('/tmp', 'key-test-');
		$file = fopen($filename, 'w');
		fwrite($file, $this->export());
		fclose($file);
		exec('/usr/bin/ssh-keygen -lf '.escapeshellarg($filename).' 2>/dev/null', $output);
		unlink($filename);
		if(count($output) == 1 && preg_match('|^([0-9]+) .* \(([A-Z0-9]+)\)$|', $output[0], $matches)) {
			$this->keysize = intval($matches[1]);
			return $matches[2];
		} else {
			throw new InvalidArgumentException("Public key doesn't look valid");
		}
	}

	/**
	* Generate random art for the key in the same way that OpenSSH does
	* OpenSSH random art uses the 'drunken bishop' algorithm as explained at
	* https://pthree.org/2013/05/30/openssh-keys-and-the-drunken-bishop/
	* @param string $string key hash to generate randomart of
	* @param string $keytype string containing text to include at the top of the randomart
	* @param string $algo string containing text to include at the bottom of the randomart
	* @return string containing generated randomart
	*/
	function generate_randomart($string, $keytype, $algo) {
		// Basic constants
		$max_x = 16; // Map size, x dimension
		$max_y = 8; // Map size, y dimension
		$s_x = 8; // Starting position, x coord
		$s_y = 4; // Starting position, y coord

		// Character mapping
		$char_map = array(' ', '.', 'o', '+', '=', '*', 'B', 'O', 'X', '@', '%', '&', '#', '/', '^');

		// Build empty map
		$map = array();
		for($x = 0; $x <= $max_x; $x++) {
			$map[$x] = array();
			for($y = 0; $y <= $max_y; $y++) {
				$map[$x][$y] = 0;
			}
		}

		// Set the bishop to his starting position
		$b_x = $s_x; // Bishop position, x coord
		$b_y = $s_y; // Bishop position, y coord

		// Let him wander
		$chunks = str_split($string, 2);
		foreach($chunks as $chunk) {
			$binary = str_pad(base_convert($chunk, 16, 2), 8, '0', STR_PAD_LEFT);
			foreach(array_reverse(str_split($binary, 2)) as $bit_pair) {
				// Work out which diagonal direction he will move based on the bit pair
				$dx = ($bit_pair[1] == 0 ? -1 : 1);
				$dy = ($bit_pair[0] == 0 ? -1 : 1);
				$b_x += $dx;
				$b_y += $dy;

				// Stop him wandering outside the map
				$b_x = min(max($b_x, 0), 16);
				$b_y = min(max($b_y, 0), 8);

				// Increment count at his new position
				$map[$b_x][$b_y]++;
			}
		}

		// Output his path within the map
		$output = "+".str_pad('['.$keytype.']', $max_x + 1, '-', STR_PAD_BOTH)."+\n";
		for($y = 0; $y <= $max_y; $y++) {
			$output .= "|";
			for($x = 0; $x <= $max_x; $x++) {
				if($x == $b_x && $y == $b_y) {
					// End position
					$output .= 'E';
				} elseif($x == $s_x && $y == $s_y) {
					// Start position
					$output .= 'S';
				} else {
					// Output character corresponding to number of passes
					if(isset($char_map[$map[$x][$y]])) {
						$output .= $char_map[$map[$x][$y]];
					} else {
						$output .= '^';
					}
				}
			}
			$output .= "|\n";
		}
		$output .= "+".str_pad('['.$algo.']', $max_x + 1, '-', STR_PAD_BOTH)."+";
		return $output;
	}

	/**
	* Provide the key in OpenSSH-text-format.
	* @return string key in OpenSSH-text-format
	*/
	public function export() {
		return "{$this->type} {$this->keydata} {$this->comment}";
	}

	/**
	* Provide a text summary of details about the key, including hashes, randomart and link to view it.
	* @return string text summary
	*/
	public function summarize_key_information() {
		global $config;
		$url = $config['web']['baseurl'].'/pubkeys/'.urlencode($this->id);
		$output = "The key fingerprint is:\n";
		$output .= " MD5:{$this->fingerprint_md5}\n";
		$output .= " SHA256:{$this->fingerprint_sha256}\n\n";
		$output .= "The key randomart is:\n";
		$randomart_md5 = explode("\n", $this->randomart_md5);
		$randomart_sha256 = explode("\n", $this->randomart_sha256);
		foreach($randomart_md5 as $ref => $line) {
			$output .= $line.' '.$randomart_sha256[$ref]."\n";
		}
		$output .= "\nYou can also view the key at <$url>";
		return $output;
	}

	/**
	* Add a GPG signature for this public key.
	* @param PublicKeySignature $sig GPG signature to add
	*/
	public function add_signature(PublicKeySignature $sig) {
		if(is_null($this->id)) throw new BadMethodCallException('Public key must be in directory before signatures can be added');
		$sig->validate();
		$stmt = $this->database->prepare("INSERT INTO public_key_signature SET public_key_id = ?, signature = ?, upload_date = UTC_TIMESTAMP(), fingerprint = ?, sign_date = ?");
		$stmt->bind_param('dsss', $this->id, $sig->signature, $sig->fingerprint, $sig->sign_date);
		$stmt->execute();
		$sig->id = $stmt->insert_id;
		$stmt->close();
		$this->owner->sync_remote_access();
	}

	/**
	* Delete a GPG signature for this public key.
	* @param PublicKeySignature $sig GPG signature to remove
	*/
	public function delete_signature(PublicKeySignature $sig) {
		if(is_null($this->id)) throw new BadMethodCallException('Public key must be in directory before signatures can be deleted');
		$stmt = $this->database->prepare("DELETE FROM public_key_signature WHERE public_key_id = ? AND id = ?");
		$stmt->bind_param('dd', $this->id, $sig->id);
		$stmt->execute();
		$stmt->close();
		$this->owner->sync_remote_access();
	}

	/**
	* List all GPG signatures stored for this public key.
	* @return array of PublicKeySignature objects
	*/
	public function list_signatures() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Public key must be in directory before signatures can be listed');
		$stmt = $this->database->prepare("SELECT * FROM public_key_signature WHERE public_key_id = ?");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$sigs = array();
		while($row = $result->fetch_assoc()) {
			$sig = new PublicKeySignature($row['id'], $row);
			$sig->public_key = $this;
			$sigs[] = $sig;
		}
		$stmt->close();
		return $sigs;
	}

	/**
	* Add a destination rule specifying where this key is allowed to be synced to.
	* @param PublicKeyDestRule $rule destination rule to be added
	*/
	public function add_destination_rule(PublicKeyDestRule $rule) {
		if(is_null($this->id)) throw new BadMethodCallException('Public key must be in directory before destination rules can be added');
		$stmt = $this->database->prepare("INSERT INTO public_key_dest_rule SET public_key_id = ?, account_name_filter = ?, hostname_filter = ?");
		$stmt->bind_param('dss', $this->id, $rule->account_name_filter, $rule->hostname_filter);
		$stmt->execute();
		$rule->id = $stmt->insert_id;
		$stmt->close();
		$this->owner->sync_remote_access();
	}

	/**
	* Delete a destination rule that specified where this key was allowed to be synced to.
	* @param PublicKeyDestRule $rule destination rule to be removed
	*/
	public function delete_destination_rule(PublicKeyDestRule $rule) {
		if(is_null($this->id)) throw new BadMethodCallException('Public key must be in directory before destination rules can be added');
		$stmt = $this->database->prepare("DELETE FROM public_key_dest_rule WHERE public_key_id = ? AND id = ?");
		$stmt->bind_param('dd', $this->id, $rule->id);
		$stmt->execute();
		$stmt->close();
		$this->owner->sync_remote_access();
	}

	/**
	* List all destination rule currently applying to this key.
	* @return array of PublicKeyDestRule objects
	*/
	public function list_destination_rules() {
		if(is_null($this->entity_id)) throw new BadMethodCallException('Public key must be in directory before destination rules can be listed');
		$stmt = $this->database->prepare("SELECT * FROM public_key_dest_rule WHERE public_key_id = ?");
		$stmt->bind_param('d', $this->id);
		$stmt->execute();
		$result = $stmt->get_result();
		$rules = array();
		while($row = $result->fetch_assoc()) {
			$rules[] = new PublicKeyDestRule($row['id'], $row);
		}
		$stmt->close();
		return $rules;
	}
}
