/*
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
*/
'use strict';

// Lightweight things to do before the page is displayed
// This should not rely on any JQuery or other libraries


// Hide the key fingerprints that we are not interested in
var sheet = document.styleSheets[0];
var fingerprint_hash;
if(localStorage && localStorage.getItem('preferred_fingerprint_hash') == 'SHA256') {
	sheet.insertRule('span.fingerprint_md5 {display:none}', 0)
} else {
	sheet.insertRule('span.fingerprint_sha256 {display:none}', 0)
}