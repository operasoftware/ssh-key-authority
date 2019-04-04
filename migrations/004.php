<?php
$migration_name = 'Add key deprication to public key';

// Removing duplicates
$this->database->query("
UPDATE `public_key` SET `fingerprint_sha256` = null where `fingerprint_sha256` IN (
    SELECT `fingerprint_sha256` FROM `public_key` GROUP BY `fingerprint_sha256` HAVING COUNT(*) > 1
)
");

$this->database->query("
ALTER TABLE `public_key` ADD CONSTRAINT `public_key_fingerprint` UNIQUE (`fingerprint_sha256`)
");

$this->database->query("
ALTER TABLE `public_key` ADD COLUMN `upload_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
");

$this->database->query("
ALTER TABLE `public_key` ADD COLUMN `active` BOOLEAN NOT NULL DEFAULT TRUE
");
