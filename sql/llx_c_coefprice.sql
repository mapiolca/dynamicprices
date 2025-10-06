--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--
CREATE TABLE IF NOT EXISTS `llx_c_coefprice`(
        `rowid` integer  AUTO_INCREMENT PRIMARY KEY NOT NULL,
        `entity` integer NOT NULL DEFAULT '1',
        `code` varchar(10) DEFAULT NULL,
        `fk_nature` varchar(10) DEFAULT NULL,
        `pricelevel` integer NOT NULL,
        `targetrate` float NOT NULL,
        `minrate` float NOT NULL,
        `element_type` tinyint NOT NULL DEFAULT '0',
        `active` tinyint NOT NULL DEFAULT '1'
)ENGINE=innodb;

ALTER TABLE `llx_c_coefprice`
        ADD COLUMN IF NOT EXISTS `element_type` tinyint NOT NULL DEFAULT '0';
