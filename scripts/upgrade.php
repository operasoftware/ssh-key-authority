#!/usr/bin/php
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

chdir(__DIR__);
require('../core.php');

$data = $database->query("SHOW TABLES");
$tables = array();
while(list($table) = $data->fetch_array()) {
	$tables[$table] = $table;
}
if(!isset($tables['entity'])) {
	echo "On database v1\n";
	upgrade_to_v2();
}
$data = $database->query("SHOW COLUMNS FROM entity");
while($row = $data->fetch_assoc()) {
	$fields[$row['Field']] = $row;
}
if(!isset($fields['type'])) {
	echo "On database v2\n";
	upgrade_to_v3();
}

echo "Database is at the latest version\n";

function upgrade_to_v2() {
	echo "Performing v1 -> v2 upgrade\n";
	global $database;
	$query = "
	CREATE TABLE `entity` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8
	";
	$database->query($query);
	$query = "
	CREATE TABLE `access` (
		`source_entity_id` int(10) unsigned NOT NULL,
		`dest_entity_id` int(10) unsigned NOT NULL,
		`grant_date` datetime NOT NULL,
		`granted_by` int(10) unsigned NOT NULL,
		PRIMARY KEY (`source_entity_id`,`dest_entity_id`),
		KEY `FK_access_entity_2` (`dest_entity_id`),
		KEY `FK_access_entity_3` (`granted_by`),
		CONSTRAINT `FK_access_entity` FOREIGN KEY (`source_entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
		CONSTRAINT `FK_access_entity_2` FOREIGN KEY (`dest_entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
		CONSTRAINT `FK_access_entity_3` FOREIGN KEY (`granted_by`) REFERENCES `entity` (`id`) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT
	";
	$database->query($query);
	$query = "
	CREATE TABLE `access_request` (
		`source_entity_id` int(10) unsigned NOT NULL,
		`dest_entity_id` int(10) unsigned NOT NULL,
		`request_date` datetime NOT NULL,
		`requested_by` int(10) unsigned NOT NULL,
		PRIMARY KEY (`source_entity_id`,`dest_entity_id`),
		KEY `FK_access_request_entity_2` (`dest_entity_id`),
		KEY `FK_access_request_entity_3` (`requested_by`),
		CONSTRAINT `FK_access_request_entity` FOREIGN KEY (`source_entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
		CONSTRAINT `FK_access_request_entity_2` FOREIGN KEY (`dest_entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
		CONSTRAINT `FK_access_request_entity_3` FOREIGN KEY (`requested_by`) REFERENCES `entity` (`id`) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT
	";
	$database->query($query);

	$database->query("ALTER TABLE `server_account` ADD COLUMN `entity_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `id`;");
	$database->query("ALTER TABLE `user` ADD COLUMN `entity_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `id`;");
	$database->query("ALTER TABLE `public_key` ADD COLUMN `entity_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `id`");

	$database->query("LOCK TABLES entity WRITE, server_account WRITE, user WRITE");
	list($max_user_id) = $database->query("SELECT MAX(id) FROM user")->fetch_array();
	$database->query("INSERT INTO entity (id) SELECT id FROM user");
	$database->query("INSERT INTO entity (id) SELECT id + $max_user_id FROM server_account");
	$database->query("UPDATE user SET entity_id = id");
	$database->query("UPDATE server_account SET entity_id = id + $max_user_id");
	$database->query("UNLOCK TABLES");

	$query = "
	INSERT INTO access (source_entity_id, dest_entity_id, grant_date, granted_by)
	SELECT se.entity_id, de.entity_id, ua.grant_date, gb.entity_id
	FROM user_access ua
	INNER JOIN user se ON se.id = ua.user_id
	INNER JOIN server_account de ON de.id = ua.server_account_id
	INNER JOIN user gb ON gb.id = ua.granted_by
	";
	$database->query($query);
	$query = "
	INSERT INTO access (source_entity_id, dest_entity_id, grant_date, granted_by)
	SELECT se.entity_id, de.entity_id, saa.grant_date, gb.entity_id
	FROM server_account_access saa
	INNER JOIN server_account se ON se.id = saa.client_server_account_id
	INNER JOIN server_account de ON de.id = saa.server_account_id
	INNER JOIN user gb ON gb.id = saa.granted_by
	";
	$database->query($query);
	$query = "
	INSERT INTO access_request (source_entity_id, dest_entity_id, request_date, requested_by)
	SELECT se.entity_id, de.entity_id, uar.request_date, se.entity_id
	FROM user_access_request uar
	INNER JOIN user se ON se.id = uar.user_id
	INNER JOIN server_account de ON de.id = uar.server_account_id
	";
	$database->query($query);
	$query = "
	INSERT INTO access_request (source_entity_id, dest_entity_id, request_date, requested_by)
	SELECT se.entity_id, de.entity_id, sar.request_date, rb.entity_id
	FROM server_account_access_request sar
	INNER JOIN server_account se ON se.id = sar.client_server_account_id
	INNER JOIN server_account de ON de.id = sar.server_account_id
	INNER JOIN user rb ON rb.id = sar.requested_by
	";
	$database->query($query);
	$database->query("DROP TABLE user_access");
	$database->query("DROP TABLE user_access_request");
	$database->query("DROP TABLE server_account_access");
	$database->query("DROP TABLE server_account_access_request");

	$query = "
	UPDATE public_key pk
	INNER JOIN user_public_key upk ON upk.public_key_id = pk.id
	INNER JOIN user ON user.id = upk.user_id
	SET pk.entity_id = user.entity_id
	";
	$database->query($query);
	$query = "
	UPDATE public_key pk
	INNER JOIN server_account_public_key sapk ON sapk.public_key_id = pk.id
	INNER JOIN server_account sa ON sa.id = sapk.server_account_id
	SET pk.entity_id = sa.entity_id
	";
	$database->query($query);
	$database->query("DELETE FROM public_key WHERE entity_id IS NULL");
	$database->query("ALTER TABLE `public_key` CHANGE COLUMN `entity_id` `entity_id` INT(10) UNSIGNED NOT NULL, ADD CONSTRAINT `FK_public_key_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE;");
	$database->query("DROP TABLE user_public_key");
	$database->query("DROP TABLE server_account_public_key");

	$database->query("ALTER TABLE `project_admin` DROP INDEX `FK_project_admin_user`, DROP FOREIGN KEY `FK_project_admin_user`");
	$database->query("ALTER TABLE `project_admin` CHANGE COLUMN `user_id` `entity_id` INT(10) UNSIGNED NOT NULL");
	$database->query("ALTER TABLE `project_admin` ADD CONSTRAINT `FK_project_admin_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE");

	$database->query("ALTER TABLE `server_admin` DROP INDEX `FK_server_admin_user`, DROP FOREIGN KEY `FK_server_admin_user`");
	$database->query("ALTER TABLE `server_admin` CHANGE COLUMN `user_id` `entity_id` INT(10) UNSIGNED NOT NULL");
	$database->query("ALTER TABLE `server_admin` ADD CONSTRAINT `FK_server_admin_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE");

	$database->query("ALTER TABLE `server_event` DROP INDEX `FK_server_log_user`, DROP FOREIGN KEY `FK_server_log_user`");
	$database->query("ALTER TABLE `server_event` CHANGE COLUMN `user_id` `entity_id` INT(10) UNSIGNED NOT NULL");
	$database->query("ALTER TABLE `server_event` ADD CONSTRAINT `FK_server_event_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`)");

	$database->query("ALTER TABLE `server_note` DROP FOREIGN KEY `FK_server_note_user`");
	$database->query("ALTER TABLE `server_note` CHANGE COLUMN `user_id` `entity_id` INT(10) UNSIGNED NOT NULL");
	$database->query("ALTER TABLE `server_note` ADD CONSTRAINT `FK_server_note_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`)");

	$database->query("ALTER TABLE `user_alert` DROP INDEX `FK_user_alert_user`, DROP FOREIGN KEY `FK_user_alert_user`");
	$database->query("ALTER TABLE `user_alert` CHANGE COLUMN `user_id` `entity_id` INT(10) UNSIGNED NOT NULL");
	$database->query("ALTER TABLE `user_alert` ADD CONSTRAINT `FK_user_alert_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE");

	$query = "
	ALTER TABLE `user`
	CHANGE COLUMN `entity_id` `entity_id` INT(10) UNSIGNED NOT NULL FIRST,
	DROP COLUMN `id`,
	DROP PRIMARY KEY,
	ADD PRIMARY KEY (`entity_id`),
	ADD CONSTRAINT `FK_user_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE
	";
	$database->query($query);

	$query = "
	ALTER TABLE `server_account`
	CHANGE COLUMN `entity_id` `entity_id` INT(10) UNSIGNED NOT NULL FIRST,
	DROP COLUMN `id`,
	DROP PRIMARY KEY,
	ADD PRIMARY KEY (`entity_id`),
	ADD CONSTRAINT `FK_server_account_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE
	";
	$database->query($query);
	echo "v1 -> v2 upgrade finished\n";
}
function upgrade_to_v3() {
	global $database;
	$database->query("ALTER TABLE `entity` ADD COLUMN `type` ENUM('user','server account','group') NULL DEFAULT NULL AFTER `id`");
	$database->query("UPDATE entity INNER JOIN user ON user.entity_id = entity.id SET entity.type = 'user'");
	$database->query("UPDATE entity INNER JOIN server_account sa ON sa.entity_id = entity.id SET entity.type = 'server account'");
	$database->query("UPDATE entity INNER JOIN `group` g ON g.entity_id = entity.id SET entity.type = 'group'");
	$database->query("DELETE FROM entity WHERE `type` IS NULL");
	$database->query("ALTER TABLE `entity` CHANGE COLUMN `type` `type` ENUM('user','server account','group') NOT NULL");
}
