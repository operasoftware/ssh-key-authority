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

chdir(dirname(__FILE__));
mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
set_error_handler('exception_error_handler');
spl_autoload_register('autoload_model');

require('pagesection.php');

$config_file = 'config/config.ini';
if(file_exists($config_file)) {
	$config = parse_ini_file($config_file, true);
} else {
	throw new Exception("Config file $config_file does not exist.");
}

require('router.php');
require('routes.php');
require('ldap.php');
require('email.php');

$ldap = new LDAP($config['ldap']['host'], $config['ldap']['starttls'], $config['ldap']['bind_dn'], $config['ldap']['bind_password']);
setup_database();

$relative_frontend_base_url = (string)parse_url($config['web']['baseurl'], PHP_URL_PATH);

// Convert all non-fatal errors into exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline) {
	throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

// Autoload needed model files
function autoload_model($classname) {
	global $base_path;
	$classname = preg_replace('/[^a-z]/', '', strtolower($classname)); # Prevent directory traversal and sanitize name
	$filename = path_join($base_path, 'model', $classname.'.php');
	if(file_exists($filename)) {
		include($filename);
	} else {
		eval("class $classname {}");
		throw new InvalidArgumentException("Attempted to load a class $classname that did not exist.");
	}
}

// Setup database connection and models
function setup_database() {
	global $config, $database, $driver, $pubkey_dir, $user_dir, $group_dir, $server_dir, $server_account_dir, $event_dir, $sync_request_dir;
	try {
		$database = new mysqli($config['database']['hostname'], $config['database']['username'], $config['database']['password'], $config['database']['database'], $config['database']['port']);
	} catch(ErrorException $e) {
		throw new DBConnectionFailedException($e->getMessage());
	}
	$database->set_charset('utf8mb4');
	$driver = new mysqli_driver();
	$driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
	$migration_dir = new MigrationDirectory;
	$pubkey_dir = new PublicKeyDirectory;
	$user_dir = new UserDirectory;
	$group_dir = new GroupDirectory;
	$server_dir = new ServerDirectory;
	$server_account_dir = new ServerAccountDirectory;
	$event_dir = new EventDirectory;
	$sync_request_dir = new SyncRequestDirectory;
}

/**
 * Join a sequence of partial paths into a complete path
 * e.g. pathJoin("foo", "bar") -> foo/bar
 *      pathJoin("f/oo", "b/ar") -> f/oo/b/ar
 *      pathJoin("/foo/b/", "ar") -> "/foo/b/ar"
 * @param string part of path
 * @return string joined path
 */
function path_join() {
	$args = func_get_args();
	$parts = array();
	foreach($args as $arg) {
		$parts = array_merge($parts, explode("/", $arg));
	}
	$parts = array_filter($parts, function($x) {return (bool)($x);});
	$prefix = $args[0][0] == "/" ? "/" : "";
	return $prefix . implode("/", $parts);
}

define('ESC_HTML', 1);
define('ESC_URL', 2);
define('ESC_URL_ALL', 3);
define('ESC_NONE', 9);

/**
* Output the given string, HTML-escaped by default
* @param string $string to output
* @param integer $escaping method of escaping to use
*/
function out($string, $escaping = ESC_HTML) {
	switch($escaping) {
	case ESC_HTML:
		echo htmlspecialchars($string);
		break;
	case ESC_URL:
		echo urlencode($string);
		break;
	case ESC_URL_ALL:
		echo rawurlencode($string);
		break;
	case ESC_NONE:
		echo $string;
		break;
	default:
		throw new InvalidArgumentException("Escaping format $escaping not known.");
	}
}

/**
* Generate a root-relative URL from the base URL and the given base-relative URL
* @param string $url base-relative URL
* @return string root-relative URL
*/
function rrurl($url) {
	global $relative_frontend_base_url;
	return $relative_frontend_base_url.$url;
}

/**
* Output a root-relative URL from the base URL and the given base-relative URL
* @param string $url relative URL
*/
function outurl($url) {
	out(rrurl($url));
}

/**
 * Short-name HTML escape convenience function
 * @param string $string string to escape
 * @return string HTML-escaped string
 */
function hesc($string) {
	return htmlspecialchars($string);
}

function english_list($array) {
	if(count($array) == 1) return reset($array);
	else return implode(', ', array_slice($array, 0, -1)).' and '.end($array);
}

/**
 * Perform an HTTP redirect to the given URL (or the current URL if none given)
 * @param string|null $url URL to redirect to
 * @param string $type HTTP response code/name to use
 */
function redirect($url = null, $type = '303 See other') {
	global $absolute_request_url, $relative_frontend_base_url;
	if(is_null($url)) {
		// Redirect is to current URL
		$url = $absolute_request_url;
	} elseif(substr($url, 0, 1) !== '#') {
		$url = $relative_frontend_base_url.$url;
	}
	header("HTTP/1.1 $type");
	header("Location: $url");
	print("\n");
	exit;
}

/**
 * Given a set of defaults and an array of querystring data, convert to a simpler
 * easy-to-read form and redirect if any conversion was done.  Also return array
 * combining defaults with any querysting parameters that do not match defaults.
 * @param array $defaults associative array of default values
 * @param array $values associative array of querystring data
 * @return array result of combining defaults and querystring data
 */
function simplify_search($defaults, $values) {
	global $relative_request_url;
	$simplify = false;
	$simplified = array();
	foreach($defaults as $key => $default) {
		if(!isset($values[$key])) {
			// No value provided, use default
			$values[$key] = $default;
		} elseif(is_array($values[$key])) {
			if($values[$key] == $default) {
				// Parameter not needed in URL if it matches the default
			} else {
				// Simplify array to semicolon-separated string in URL
				$simplified[] = urlencode($key).'='.implode(';', array_map('urlencode', $values[$key]));
			}
			$simplify = true;
		} elseif($values[$key] == $default) {
			// Parameter not needed in URL if it matches the default
			$simplify = true;
		} else {
			// Pass value as-is to simplified array
			$simplified[] = urlencode($key).'='.urlencode($values[$key]);
			if(is_array($default)) {
				// We expect an array; extract array values from semicolon-separated string
				$values[$key] = explode(';', $values[$key]);
			}
		}
	}
	if($simplify) {
		$url = preg_replace('/\?.*$/', '', $relative_request_url);
		if(count($simplified) > 0) $url .= '?'.implode('&', $simplified);
		redirect($url);
	} else {
		return $values;
	}
}

class OutputFormatter {
	public function comment_format($text) {
		return hesc($text);
	}
}

$output_formatter = new OutputFormatter;

foreach(glob("extensions/*.php") as $filename) {
    include $filename;
}

class DBConnectionFailedException extends RuntimeException {}
