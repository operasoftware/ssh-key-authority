<?php

$migration_name = 'Add ldap_guid group column for system groups';

$this->database->query("
ALTER TABLE `group` ADD `ldap_guid` varchar(36) NULL
");
