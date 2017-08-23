<?php
$migration_name = 'Add migration support';

$this->database->query("
CREATE TABLE `migration` (
	`id` int(10) unsigned NOT NULL,
	`name` text NOT NULL,
	`applied` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
