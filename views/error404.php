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

$content = new PageSection('error404');
$content->set('address', $relative_request_url);
$content->set('fulladdress', $absolute_request_url);
$content->set('referrer', isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

$page = new PageSection('base');
$page->set('title', 'Page not found');
$page->set('content', $content);
$page->set('alerts', array());
header('HTTP/1.1 404 Not Found');
echo $page->generate();
