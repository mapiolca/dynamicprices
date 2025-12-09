--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--
CREATE TABLE IF NOT EXISTS `llx_c_coefprice`(
`rowid`int(11) AUTO_INCREMENT,
`code`VARCHAR(255) NOT NULL UNIQUE,
`label`VARCHAR(255) NOT NULL,
`targetrate`FLOAT(24.8) NOT NULL,
`minrate`FLOAT(24.8) NOT NULL,
`active`TINYINT(4)NOT NULL DEFAULT 1,

PRIMARY KEY (`rowid`)
)ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;

CREATE TABLE IF NOT EXISTS `llx_c_margin_on_cost`(
`rowid`INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
`entity`INTEGER NOT NULL DEFAULT '1',
`code`VARCHAR(50) NOT NULL,
`code_nature`VARCHAR(10) DEFAULT NULL,
`datec`DATETIME NULL,
`tms`TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
`margin_on_cost_percent`FLOAT NOT NULL DEFAULT '0',
`import_key`VARCHAR(14) NULL,
`active`TINYINT NOT NULL DEFAULT '1'
)ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;
