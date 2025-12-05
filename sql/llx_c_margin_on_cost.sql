--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--
CREATE TABLE IF NOT EXISTS `llx_c_margin_on_cost`(
`rowid` integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
`entity` integer NOT NULL DEFAULT '1',
`code` varchar(50) NOT NULL,
`code_nature` varchar(10) DEFAULT NULL,
`datec` datetime NULL,
`tms` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
`margin_on_cost_percent` float NOT NULL DEFAULT '0',
`import_key` varchar(14) NULL,
`active` tinyint NOT NULL DEFAULT '1'
)ENGINE=innodb;
