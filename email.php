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

class Email {
	public $from;
	public $subject;
	public $body;
	public $signature;
	private $to = array();
	private $cc = array();
	private $bcc = array();
	private $reply_to = array();
	private $headers = array();
	private $gpg_sign = true;

	public function __construct() {
		global $config;
		$this->from = array('email' => $config['email']['from_address'], 'name' => $config['email']['from_name']);
		$this->signature = $config['web']['baseurl']."\nYour friendly SSH key management system";
	}

	public function add_recipient($email, $name = null) {
		$this->to[] = array('email' => $email, 'name' => $name);
	}

	public function add_cc($email, $name = null) {
		$this->cc[] = array('email' => $email, 'name' => $name);
	}

	public function add_bcc($email, $name = null) {
		$this->bcc[] = array('email' => $email, 'name' => $name);
	}

	public function add_reply_to($email, $name = null) {
		$this->reply_to[] = array('email' => $email, 'name' => $name);
	}

	public function set_from($email, $name = null) {
		$this->from = array('email' => $email, 'name' => $name);
		$this->gpg_sign = false;
	}

	public function send() {
		global $config;
		if(!empty($config['email']['reroute'])) {
			$rcpt_summary = '';
			foreach(array('to', 'cc', 'bcc') as $rcpt_type) {
				if(count($this->$rcpt_type) > 0) {
					$rcpt_summary .= ucfirst($rcpt_type).":\n";
					foreach($this->$rcpt_type as $rcpt) {
						if(is_null($rcpt['name'])) {
							$rcpt_summary .= " $rcpt[email]\n";
						} else {
							$rcpt_summary .= " $rcpt[name] <$rcpt[email]>\n";
						}
					}
				}
			}
			$this->body = $rcpt_summary."\n".$this->body;
			$this->to = array(array('email' => $config['email']['reroute'], 'name' => null));
			$this->cc = array();
			$this->bcc = array();
		}
		$this->headers[] = "MIME-Version: 1.0";
		$this->headers[] = "Content-Transfer-Encoding: 8bit";
		$this->headers[] = "Auto-Submitted: auto-generated";
		$this->headers[] = "Precedence: bulk";
		$this->flow();
		$this->append_signature();
		if(function_exists('gnupg_init') && $this->gpg_sign) {
			$this->sign();
		}
		if(is_null($this->from['name'])) {
			$this->headers[] = "From: {$this->from['email']}";
		} else {
			$this->headers[] = "From: {$this->from['name']} <{$this->from['email']}>";
		}
		$to = array();
		foreach($this->to as $rcpt) {
			if(is_null($rcpt['name'])) {
				$to[] = "$rcpt[email]";
			} else {
				$to[] = "$rcpt[name] <$rcpt[email]>";
			}
		}
		if(count($this->reply_to) > 0) {
			$header = 'Reply-To: ';
			foreach($this->reply_to as $addr) {
				if(is_null($addr['name'])) {
					$header .= "$addr[email], ";
				} else {
					if(strrpos($header, "\n") === false) $indent = strlen($header);
					else $indent = strlen($header) - strrpos($header, "\n") - 1;
					$header .= $this->header_7bit_safe($addr['name'], $indent)." <$addr[email]>, ";
				}
			}
			$this->headers[] = substr($header, 0, -2);
		}
		foreach(array('cc', 'bcc') as $rcpt_type) {
			foreach($this->$rcpt_type as $rcpt) {
				if(is_null($rcpt['name'])) {
					$this->headers[] = ucfirst($rcpt_type).": $rcpt[email]";
				} else {
					$this->headers[] = ucfirst($rcpt_type).": ".$this->header_7bit_safe($rcpt['name'], strlen($rcpt_type) + 2)." <$rcpt[email]>";
				}
			}
		}
		if(!empty($config['email']['enabled'])) {
			mail(implode(', ', $to), $this->header_7bit_safe($this->subject, 9), $this->body, implode("\n", $this->headers));
		}
	}

	private function flow() {
		$message = $this->body;
		/* Excerpt from RFC 3676 - 4.2.  Generating Format=Flowed

			A generating agent SHOULD:

			o Ensure all lines (fixed and flowed) are 78 characters or fewer in
			  length, counting any trailing space as well as a space added as
			  stuffing, but not counting the CRLF, unless a word by itself
			  exceeds 78 characters.

			o Trim spaces before user-inserted hard line breaks.

			A generating agent MUST:

			o Space-stuff lines which start with a space, "From ", or ">".

		*/
		// Trimming spaces before user-inserted hard line breaks, and wrapping.
		$lines = explode("\n", $message);
		foreach($lines as $ref => $line) {
			$lines[$ref] = wordwrap(rtrim($line), 76, " \n", false);
		}
		$message = implode("\n", $lines);
		// Space-stuffing lines which start with a space, "From ", or ">".
		$lines = explode("\n", $message);
		foreach($lines as $ref => $line) {
			if(strpos($line, " ") === 0 || strpos($line, "From ") === 0 || strpos($line, ">") === 0) $lines[$ref] = " ".$line;
		}
		$message = implode("\n", $lines);

		$message = "$message\n\n";
		$this->body = $message;
		$this->headers[] = "Content-Type: text/plain; charset=utf-8; format=flowed";
	}

	private function header_7bit_safe($string, $indent = 0) {
		if(is_null($string)) return null;
		return mb_encode_mimeheader($string, 'UTF-8', 'Q', "\n", $indent);
	}

	private function append_signature() {
		//Add a signature
		$this->body .= "-- \n";
		$this->body .= $this->signature;
	}

	private function sign() {
		$localheaders = array();
		foreach($this->headers as $k => $v) {
			if(preg_match('/^Content-Type:/i', $v)) {
				$localheaders[] = $v;
				unset($this->headers[$k]);
			}
		}
		$localheaders[] = "Content-Transfer-Encoding: quoted-printable";
		$lines = explode("\n", $this->body);
		foreach($lines as $ref => $line) {
			$line = quoted_printable_encode($line);
			if(substr($line, -1) == ' ') $line = substr($line, 0, -1).'=20';
			$lines[$ref] = $line;
		}
		$boundary = uniqid(php_uname('n'));
		$innerboundary = uniqid(php_uname('n').'1');
		$this->headers[] = 'Content-Type: multipart/signed; micalg=pgp-sha1; protocol="application/pgp-signature"; boundary="'.$boundary.'"';
		$message = "Content-Type: multipart/mixed; boundary=\"{$innerboundary}\";\r\n";
		$message .= " protected-headers=\"v1\"\r\n";
		$message .= "From: {$this->from['email']}\r\n";
		foreach(array('to', 'cc') as $rcpt_type) {
			foreach($this->$rcpt_type as $rcpt) {
				if(is_null($rcpt['name'])) {
					$message .= ucfirst($rcpt_type).": $rcpt[email]\r\n";
				} else {
					$message .= ucfirst($rcpt_type).": ".$this->header_7bit_safe($rcpt['name'], strlen($rcpt_type) + 2)." <$rcpt[email]>\r\n";
				}
			}
		}
		$message .= "Subject: ".$this->header_7bit_safe($this->subject, 9)."\r\n\r\n";
		$message .= "--{$innerboundary}\r\n".implode("\r\n", $localheaders)."\r\n\r\n".implode("\r\n", $lines)."\r\n--{$innerboundary}--\r\n";
		$signature = $this->get_gpg_signature($message);
		$message = "This is an OpenPGP/MIME signed message (RFC 4880 and 3156)\r\n--{$boundary}\r\n{$message}\r\n--{$boundary}\r\n";
		$message .= "Content-Type: application/pgp-signature; name=\"signature.asc\"\r\n";
		$message .= "Content-Description: OpenPGP digital signature\r\n";
		$message .= "Content-Disposition: attachment; filename=\"signature.asc\"\r\n\r\n";
		$message .= $signature;
		$message .= "\r\n--$boundary--";
		$this->body = $message;
	}

	private function get_gpg_signature($message) {
		$gpg = new gnupg();
		$gpg->addsignkey('5BF47B590E2629854FC99BCEE8D5397409381BE2');
		$gpg->setsignmode(GNUPG::SIG_MODE_DETACH);
		return $gpg->sign($message);
	}
}
