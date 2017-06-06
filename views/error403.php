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

$content = new PageSection('error403');
$content->set('address', $relative_request_url);
$content->set('fulladdress', $absolute_request_url);

$page = new PageSection('base');
$page->set('title', 'Access denied');
$page->set('content', $content);
$page->set('alerts', array());
header('HTTP/1.1 403 Forbidden');
echo $page->generate();
