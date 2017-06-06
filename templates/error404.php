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
?>
<h1>Page not found</h1>
<p>Sorry, but the address you've given doesn't seem to point to a valid page.</p>
<p>If you got here by following a link, please <a href="mailto:<?php out($config['email']['admin_address'])?>?subject=<?php out('Broken link to '.$this->get('fulladdress').(empty($this->get('referrer')) ? '' : ' from '.$this->get('referrer')), ESC_URL_ALL)?>">report it to us</a>. Otherwise, please make sure that you have typed the address correctly, or just start browsing from the <a href="/">keys home page</a>.</p>
