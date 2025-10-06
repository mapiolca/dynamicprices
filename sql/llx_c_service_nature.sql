--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--
CREATE TABLE IF NOT EXISTS `llx_c_service_nature`(
        `rowid` integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
        `entity` integer NOT NULL DEFAULT '1',
        `code` varchar(64) NOT NULL,
        `label` varchar(255) NOT NULL,
        `active` tinyint NOT NULL DEFAULT '1',
        `position` integer NOT NULL DEFAULT '0',
        UNIQUE `uk_service_nature_code` (`code`,`entity`)
)ENGINE=innodb;
