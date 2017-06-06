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

$e = $this->get('exception');
?>
<h1>Naming conflict</h1>
<div class="alert alert-danger">
<?php if(count($e->fields) == 1) { ?>
<p>The <?php out(str_replace('_', ' ', implode(',', $e->fields)))?> "<?php out(implode(',', $e->values))?>" already exists. Please <a href="" class="navigate-back">go back</a> and try again.</p>
<?php } else { ?>
<p>The values you provided are in conflict with existing records. Please <a href="" class="navigate-back">go back</a> and try again.</p>
<?php } ?>
</div>
