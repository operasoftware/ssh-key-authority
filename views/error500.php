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

$content = new PageSection('error500');
$content->set('error_number', $error_number);
if(isset($active_user) && is_object($active_user) && isset($e)) {
	if($active_user->developer) {
		$content->set('exception_class', get_class($e));
		$content->set('error_details', $e);
	}
}

$page = new PageSection('base');
$page->set('title', 'An error occurred');
$page->set('content', $content);
$page->set('alerts', array());
header('HTTP/1.1 500 Internal Server Error');
echo $page->generate();
