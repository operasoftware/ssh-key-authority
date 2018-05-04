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
$web_config = $this->get('web_config');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'");
?>
<!DOCTYPE html>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php out($this->get('title'))?></title>
<link rel="stylesheet" href="<?php outurl('/bootstrap/css/bootstrap.min.css')?>">
<link rel="stylesheet" href="<?php outurl('/style.css?'.filemtime('public_html/style.css'))?>">
<link rel="icon" href="<?php outurl('/key.png')?>">
<script src="<?php outurl('/header.js?'.filemtime('public_html/header.js'))?>"></script>
<?php out($this->get('head'), ESC_NONE) ?>
<div id="wrap">
<a href="#content" class="sr-only">Skip to main content</a>
<div class="navbar navbar-default navbar-fixed-top">
	<div class="container">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<?php if(!empty($web_config['logo'])) { ?>
			<a class="navbar-brand" href="/">
				<img src="<?php out($web_config['logo'])?>">
				SSH Key Authority
			</a>
			<?php } ?>
		</div>
		<div class="navbar-collapse collapse">
			<ul class="nav navbar-nav">
				<?php foreach($this->get('menu_items') as $url => $name) { ?>
				<li<?php if($url == $this->get('relative_request_url')) out(' class="active"', ESC_NONE); ?>><a href="<?php outurl($url)?>"><?php out($name)?></a></li>
				<?php } ?>
			</ul>
		</div>
	</div>
</div>
<div class="container" id="content">
<?php foreach($this->get('alerts') as $alert) { ?>
<div class="alert alert-<?php out($alert->class)?> alert-dismissable">
	<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
	<?php out($alert->content, $alert->escaping)?>
</div>
<?php } ?>
<?php out($this->get('content'), ESC_NONE) ?>
</div>
</div>
<div id="footer">
	<div class="container">
		<p class="text-muted credit"><?php out($web_config['footer'], ESC_NONE)?></p>
		<?php if($this->get('active_user') && $this->get('active_user')->developer) { ?>
		<?php } ?>
	</div>
</div>
<script src="<?php outurl('/jquery/jquery-3.2.1.min.js')?>"></script>
<script src="<?php outurl('/bootstrap/js/bootstrap.min.js')?>"></script>
<script src="<?php outurl('/extra.js?'.filemtime('public_html/extra.js'))?>"></script>
