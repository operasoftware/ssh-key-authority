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

$options = getopt('', array('systemd', 'user:'));

/**
* Handle process control signals
*/
function sig_handler($signo) {
	global $signal;
	$signal = $signo;
}
/**
* Daemon log - write log message
*/
function dlog($txt) {
	global $options;
	if(isset($options['systemd'])) {
		echo "{$txt}\n";
	} else {
		echo date('c')." {$txt}\n";
	}
}

chdir(__DIR__);
error_reporting(E_ALL);
ini_set('display_errors', 1);
cli_set_process_title('keys-sync');

umask(027);

if(!isset($options['systemd'])) {
	$pidfile = '/var/run/keys-sync.pid';
	$lockfile = '/var/run/keys-sync.lock';
	$logfile = '/var/log/keys/sync.log';

	if(!isset($options['user'])) {
		fwrite(STDERR, "--user parameter must be provided");
		exit(1);
	}
	$username = $options['user'];

	if(posix_getuid() !== 0) {
		fwrite(STDERR, "This command must be run as root\n");
		exit(1);
	}
	if(!$user = posix_getpwnam($username)) {
		fwrite(STDERR, "Could not find $username user details\n");
		exit(1);
	}

	// Attempt to establish lock
	$lock = fopen($lockfile, 'w+');
	if(!flock($lock, LOCK_EX | LOCK_NB)) {
		fwrite(STDERR, "Could not establish lock, process already running?\n");
		exit(0);
	}
	// Fork process
	$pid = pcntl_fork();
	if($pid == -1) {
		// Something went wrong
		fwrite(STDERR, "Failed to fork\n");
		exit(1);
	} elseif($pid == 0) {
		// This is the child process
	} else {
		// This is the parent process
		// Write pidfile and exit
		$fh = fopen($pidfile, 'w');
		fwrite($fh, "$pid\n");
		fclose($fh);
		exit();
	}

	// We have now forked
	// Close STDIN/STDOUT/STDERR and redirect output to logfile
	fclose(STDIN);
	fclose(STDOUT);
	fclose(STDERR);
	$stdin = fopen('/dev/null', 'r');
	$stdout = fopen($logfile, 'a');
	$stderr = fopen('php://stdout', 'a');

	// Change user/group that we are running as
	posix_setgid($user['gid']);
	posix_setuid($user['uid']);
	if(!isset($options['systemd'])) {
		// Make the current process a session leader
		if(posix_setsid() == -1) {
			die("Could not detach from terminal.");
		}
	}
}

// Set up signal handling
declare(ticks = 1);
$signal = null;
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");

require('../core.php');
require('sync-common.php');
dlog("Daemon started");

$sync_procs = array();
define('MAX_PROCS', 20);

// Primary loop
while(is_null($signal)) {
	try {
		$reqs = $sync_request_dir->list_pending_sync_requests();
		foreach($reqs as $req) {
			$args = array();
			$args[] = '--id';
			$args[] = $req->server_id;
			if(!is_null($req->account_name)) {
				$args[] = '--user';
				$args[] = $req->account_name;
			}
			if(count($sync_procs) > MAX_PROCS) break;
			$req->set_in_progress();
			dlog("Sync process spawning for: {$req->server_id}/{$req->account_name}");
			$sync_procs[] = new SyncProcess(__DIR__.'/sync.php', $args, $req);
		}
	} catch(mysqli_sql_exception $e) {
		if($e->getMessage() == 'MySQL server has gone away') {
			dlog("MySQL server has gone away");
			$connected = false;
			while(!$connected) {
				try {
					setup_database();
					$connected = true;
					dlog("MySQL connection re-established");
				} catch(mysqli_sql_exception $e2) {
					dlog("Attempt to reconnect failed: ".$e2->getMessage());
					sleep(5);
				}
			}
		}
	}
	foreach($sync_procs as $ref => &$sync_proc) {
		$data = $sync_proc->get_data();
		if(!empty($data)) {
			dlog($data['output']);
			unset($sync_proc);
			unset($sync_procs[$ref]);
		}
	}
	sleep(1);
}
dlog("Received exit signal");

if(!isset($options['systemd'])) {
	// Release lock
	flock($lock, LOCK_UN);
	fclose($lock);
}
