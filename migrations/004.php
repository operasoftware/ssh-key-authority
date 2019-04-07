<?php
$migration_name = 'Add local usermanagment';

function free_results($database) {
    do {
        if ($res = $database->store_result()) {
            $res->free();
        }
    } while ($database->more_results() && $database->next_result()); 
}

$this->database->autocommit(FALSE);

$result = $this->database->query("
    SELECT uid FROM user WHERE uid = 'keys-sync'
");
if ($result) {
    if($result->num_rows === 0) {
        $result->close();
        $result = $this->database->multi_query("
            INSERT INTO entity SET type = 'user';
            INSERT INTO user SET entity_id = (
                SELECT LAST_INSERT_ID()
            ), uid = 'keys-sync', name = 'Synchronization script', email = '', auth_realm = 'local', admin = 1;
        "); 
        free_results($this->database);
    } else {
        $result->close();
        $this->database->query("
            UPDATE user SET auth_realm = 'local', active = 1 WHERE uid = 'keys-sync';
        ");
    }
}


$this->database->multi_query("
CREATE TABLE `entity_event_2` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `entity_id` int(10) unsigned NOT NULL,
    `actor_id` int(10) unsigned,
    `date` datetime NOT NULL,
    `details` mediumtext NOT NULL,
    PRIMARY KEY (`id`),
    KEY `FK_entity_event_entity_id` (`entity_id`),
    KEY `FK_entity_event_actor_id` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT entity_event_2 SELECT * FROM entity_event;

DROP TABLE entity_event;
RENAME TABLE entity_event_2 TO entity_event;

ALTER TABLE `entity_event`
    ADD CONSTRAINT `FK_entity_event_actor_id` FOREIGN KEY (`actor_id`) REFERENCES `entity` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `FK_entity_event_entity_id` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE;
");
free_results($this->database);


$this->database->multi_query("
CREATE TABLE `group_event_2` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `group` int(10) unsigned NOT NULL,
    `entity_id` int(10) unsigned,
    `date` datetime NOT NULL,
    `details` mediumtext NOT NULL,
    PRIMARY KEY (`id`),
    KEY `FK_group_event_group` (`group`),
    KEY `FK_group_event_entity` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

INSERT group_event_2 SELECT * FROM group_event;

DROP TABLE group_event;
RENAME TABLE group_event_2 TO group_event;

ALTER TABLE `group_event`
    ADD CONSTRAINT `FK_group_event_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `FK_group_event_group` FOREIGN KEY (`group`) REFERENCES `group` (`entity_id`) ON DELETE CASCADE;
");
free_results($this->database);


$this->database->multi_query("
CREATE TABLE `group_member_2` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `group` int(10) unsigned NOT NULL,
    `entity_id` int(10) unsigned NOT NULL,
    `add_date` datetime NOT NULL,
    `added_by` int(10) unsigned,
    PRIMARY KEY (`id`),
    UNIQUE KEY `group_entity_id` (`group`, `entity_id`),
    KEY `FK_group_member_entity` (`entity_id`),
    KEY `FK_group_member_entity_2` (`added_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

INSERT group_member_2 SELECT * FROM group_member;

DROP TABLE group_member;
RENAME TABLE group_member_2 TO group_member;

ALTER TABLE `group_member`
    ADD CONSTRAINT `FK_group_member_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `FK_group_member_entity_2` FOREIGN KEY (`added_by`) REFERENCES `entity` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `FK_group_member_group` FOREIGN KEY (`group`) REFERENCES `group` (`entity_id`) ON DELETE CASCADE
");
free_results($this->database);


$this->database->multi_query("
CREATE TABLE `server_event_2` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `server_id` int(10) unsigned NOT NULL,
    `actor_id` int(10) unsigned,
    `date` datetime NOT NULL,
    `details` mediumtext NOT NULL,
    PRIMARY KEY (`id`),
    KEY `FK_server_log_server` (`server_id`),
    KEY `FK_server_event_actor_id` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT server_event_2 SELECT * FROM server_event;

DROP TABLE server_event;
RENAME TABLE server_event_2 TO server_event;

ALTER TABLE `server_event`
    ADD CONSTRAINT `FK_server_event_actor_id` FOREIGN KEY (`actor_id`) REFERENCES `entity` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `FK_server_log_server` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE CASCADE;
");
free_results($this->database);


$this->database->multi_query("
CREATE TABLE `server_note_2` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `server_id` int(10) unsigned NOT NULL,
    `entity_id` int(10) unsigned,
    `date` datetime NOT NULL,
    `note` mediumtext NOT NULL,
    PRIMARY KEY (`id`),
    KEY `FK_server_note_server` (`server_id`),
    KEY `FK_server_note_user` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT server_note_2 SELECT * FROM server_note;

DROP TABLE server_note;
RENAME TABLE server_note_2 TO server_note;

ALTER TABLE `server_note`
    ADD CONSTRAINT `FK_server_note_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `FK_server_note_server` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE CASCADE
");
free_results($this->database);

$this->database->commit();

$this->database->autocommit(TRUE);
