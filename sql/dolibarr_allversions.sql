--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--
CREATE TABLE IF NOT EXISTS `llx_c_coefprice`( 
  	`rowid`				int(11) AUTO_INCREMENT,
  	`code` 			VARCHAR(255) NOT NULL UNIQUE, 
  	`label` 			VARCHAR(255) NOT NULL,   
	`targetrate`      FLOAT(24.8) NOT NULL,
	`minrate`      FLOAT(24.8) NOT NULL,
	`active`           	TINYINT(4)    NOT NULL DEFAULT 1,

	PRIMARY KEY (`rowid`) 
)ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;