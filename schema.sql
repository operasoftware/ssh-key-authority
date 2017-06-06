/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `access`
--

CREATE TABLE `access` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_entity_id` int(10) unsigned NOT NULL,
  `dest_entity_id` int(10) unsigned NOT NULL,
  `grant_date` datetime NOT NULL,
  `granted_by` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_entity_id_dest_entity_id` (`source_entity_id`,`dest_entity_id`),
  KEY `FK_access_entity_2` (`dest_entity_id`),
  KEY `FK_access_entity_3` (`granted_by`),
  CONSTRAINT `FK_access_entity` FOREIGN KEY (`source_entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_access_entity_2` FOREIGN KEY (`dest_entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_access_entity_3` FOREIGN KEY (`granted_by`) REFERENCES `entity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

--
-- Table structure for table `access_option`
--

CREATE TABLE `access_option` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `access_id` int(10) unsigned NOT NULL,
  `option` enum('command','from','no-agent-forwarding','no-port-forwarding','no-pty','no-X11-forwarding') NOT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_id_option` (`access_id`,`option`),
  CONSTRAINT `FK_access_option_access` FOREIGN KEY (`access_id`) REFERENCES `access` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `access_request`
--

CREATE TABLE `access_request` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_entity_id` int(10) unsigned NOT NULL,
  `dest_entity_id` int(10) unsigned NOT NULL,
  `request_date` datetime NOT NULL,
  `requested_by` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_entity_id_dest_entity_id` (`source_entity_id`,`dest_entity_id`),
  KEY `FK_access_request_entity_2` (`dest_entity_id`),
  KEY `FK_access_request_entity_3` (`requested_by`),
  CONSTRAINT `FK_access_request_entity` FOREIGN KEY (`source_entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_access_request_entity_2` FOREIGN KEY (`dest_entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_access_request_entity_3` FOREIGN KEY (`requested_by`) REFERENCES `entity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

--
-- Table structure for table `entity`
--

CREATE TABLE `entity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('user','server account','group') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `entity_admin`
--

CREATE TABLE `entity_admin` (
  `entity_id` int(10) unsigned NOT NULL,
  `admin` int(10) unsigned NOT NULL,
  PRIMARY KEY (`entity_id`,`admin`),
  KEY `FK_entity_admin_entity_2` (`admin`),
  CONSTRAINT `FK_entity_admin_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_entity_admin_entity_2` FOREIGN KEY (`admin`) REFERENCES `entity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

--
-- Table structure for table `entity_event`
--

CREATE TABLE `entity_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(10) unsigned NOT NULL,
  `actor_id` int(10) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `details` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_entity_event_entity_id` (`entity_id`),
  KEY `FK_entity_event_actor_id` (`actor_id`),
  CONSTRAINT `FK_entity_event_actor_id` FOREIGN KEY (`actor_id`) REFERENCES `entity` (`id`),
  CONSTRAINT `FK_entity_event_entity_id` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `group`
--

CREATE TABLE `group` (
  `entity_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `system` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`entity_id`),
  UNIQUE KEY `name` (`name`),
  CONSTRAINT `FK_group_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `group_event`
--

CREATE TABLE `group_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group` int(10) unsigned NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `details` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_group_event_group` (`group`),
  KEY `FK_group_event_entity` (`entity_id`),
  CONSTRAINT `FK_group_event_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`),
  CONSTRAINT `FK_group_event_group` FOREIGN KEY (`group`) REFERENCES `group` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

--
-- Table structure for table `group_member`
--

CREATE TABLE `group_member` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group` int(10) unsigned NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `add_date` datetime NOT NULL,
  `added_by` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_entity_id` (`group`,`entity_id`),
  KEY `FK_group_member_entity` (`entity_id`),
  KEY `FK_group_member_entity_2` (`added_by`),
  CONSTRAINT `FK_group_member_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_group_member_entity_2` FOREIGN KEY (`added_by`) REFERENCES `entity` (`id`),
  CONSTRAINT `FK_group_member_group` FOREIGN KEY (`group`) REFERENCES `group` (`entity_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

--
-- Table structure for table `public_key`
--

CREATE TABLE `public_key` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(10) unsigned NOT NULL,
  `type` varchar(30) NOT NULL,
  `keydata` mediumtext NOT NULL,
  `comment` mediumtext NOT NULL,
  `keysize` int(11) DEFAULT NULL,
  `fingerprint_md5` char(47) DEFAULT NULL,
  `fingerprint_sha256` varchar(50) DEFAULT NULL,
  `randomart_md5` text,
  `randomart_sha256` text,
  PRIMARY KEY (`id`),
  KEY `FK_public_key_entity` (`entity_id`),
  CONSTRAINT `FK_public_key_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `public_key_dest_rule`
--

CREATE TABLE `public_key_dest_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_key_id` int(10) unsigned NOT NULL,
  `account_name_filter` varchar(50) NOT NULL,
  `hostname_filter` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_public_key_dest_rule_public_key` (`public_key_id`),
  CONSTRAINT `FK_public_key_dest_rule_public_key` FOREIGN KEY (`public_key_id`) REFERENCES `public_key` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `public_key_signature`
--

CREATE TABLE `public_key_signature` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_key_id` int(10) unsigned NOT NULL,
  `signature` blob NOT NULL,
  `upload_date` datetime NOT NULL,
  `fingerprint` varchar(50) NOT NULL,
  `sign_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_public_key_signature_public_key` (`public_key_id`),
  CONSTRAINT `FK_public_key_signature_public_key` FOREIGN KEY (`public_key_id`) REFERENCES `public_key` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `server`
--

CREATE TABLE `server` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) DEFAULT NULL,
  `hostname` varchar(150) NOT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `key_management` enum('none','keys','other','decommissioned') NOT NULL DEFAULT 'keys',
  `authorization` enum('manual','automatic LDAP','manual LDAP') NOT NULL DEFAULT 'manual',
  `use_sync_client` enum('no','yes') NOT NULL DEFAULT 'no',
  `sync_status` enum('not synced yet','sync success','sync failure','sync warning') NOT NULL DEFAULT 'not synced yet',
  `configuration_system` enum('unknown','cf-sysadmin','puppet-devops','puppet-miniops','puppet-tvstore','none') NOT NULL DEFAULT 'unknown',
  `custom_keys` enum('not allowed','allowed') NOT NULL DEFAULT 'not allowed',
  `rsa_key_fingerprint` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostname` (`hostname`),
  KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `server_account`
--

CREATE TABLE `server_account` (
  `entity_id` int(10) unsigned NOT NULL,
  `server_id` int(10) unsigned NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `sync_status` enum('not synced yet','sync success','sync failure','sync warning','proposed') NOT NULL DEFAULT 'not synced yet',
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`entity_id`),
  UNIQUE KEY `server_id_name` (`server_id`,`name`),
  KEY `FK_server_account_server` (`server_id`),
  CONSTRAINT `FK_server_account_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_server_account_server` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `server_admin`
--

CREATE TABLE `server_admin` (
  `server_id` int(10) unsigned NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`server_id`,`entity_id`),
  KEY `FK_server_admin_entity` (`entity_id`),
  CONSTRAINT `FK_server_admin_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_server_admin_server` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `server_event`
--

CREATE TABLE `server_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `server_id` int(10) unsigned NOT NULL,
  `actor_id` int(10) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `details` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_server_log_server` (`server_id`),
  KEY `FK_server_event_actor_id` (`actor_id`),
  CONSTRAINT `FK_server_event_actor_id` FOREIGN KEY (`actor_id`) REFERENCES `entity` (`id`),
  CONSTRAINT `FK_server_log_server` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `server_ldap_access_option`
--

CREATE TABLE `server_ldap_access_option` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `server_id` int(10) unsigned NOT NULL,
  `option` enum('command','from','no-agent-forwarding','no-port-forwarding','no-pty','no-X11-forwarding') NOT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_id_option` (`server_id`,`option`),
  CONSTRAINT `FK_server_ldap_access_option_server` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `server_note`
--

CREATE TABLE `server_note` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `server_id` int(10) unsigned NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `note` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_server_note_server` (`server_id`),
  KEY `FK_server_note_user` (`entity_id`),
  CONSTRAINT `FK_server_note_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`),
  CONSTRAINT `FK_server_note_server` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `sync_request`
--

CREATE TABLE `sync_request` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `server_id` int(10) unsigned NOT NULL,
  `account_name` varchar(50) DEFAULT NULL,
  `processing` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_id_account_name` (`server_id`,`account_name`),
  CONSTRAINT `FK_sync_request_server` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `entity_id` int(10) unsigned NOT NULL,
  `uid` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `superior_entity_id` int(10) unsigned DEFAULT NULL,
  `auth_realm` enum('LDAP','local','external') NOT NULL DEFAULT 'LDAP',
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `admin` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `developer` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `force_disable` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `csrf_token` binary(128) DEFAULT NULL,
  PRIMARY KEY (`entity_id`),
  UNIQUE KEY `uid` (`uid`),
  CONSTRAINT `FK_user_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `user_alert`
--

CREATE TABLE `user_alert` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_id` int(10) unsigned NOT NULL,
  `class` varchar(15) NOT NULL,
  `content` mediumtext NOT NULL,
  `escaping` int(10) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `FK_user_alert_entity` (`entity_id`),
  CONSTRAINT `FK_user_alert_entity` FOREIGN KEY (`entity_id`) REFERENCES `entity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
