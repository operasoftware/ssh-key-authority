<?php
$migration_name = 'Add port number field';

$this->database->query("
ALTER TABLE `server` ADD COLUMN `port` int(10) unsigned NOT NULL DEFAULT 22
");
