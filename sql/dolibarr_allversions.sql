--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
--
CREATE TABLE IF NOT EXISTS `llx_c_coefprice`(
        `rowid`                         int(11) AUTO_INCREMENT,
        `code`                  VARCHAR(255) NOT NULL UNIQUE,
        `label`                         VARCHAR(255) NOT NULL,
        `targetrate`      FLOAT(24.8) NOT NULL,
        `minrate`      FLOAT(24.8) NOT NULL,
        `element_type`          TINYINT(4)    NOT NULL DEFAULT 0,
        `active`                TINYINT(4)    NOT NULL DEFAULT 1,

        PRIMARY KEY (`rowid`)
)ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;

ALTER TABLE `llx_c_coefprice`
        ADD COLUMN IF NOT EXISTS `element_type` TINYINT(4) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `llx_c_service_nature`(
        `rowid`         int(11) AUTO_INCREMENT,
        `entity`        int(11) NOT NULL DEFAULT 1,
        `code`          VARCHAR(64) NOT NULL,
        `label`         VARCHAR(255) NOT NULL,
        `active`        TINYINT(4) NOT NULL DEFAULT 1,
        `position`      int(11) NOT NULL DEFAULT 0,

        PRIMARY KEY (`rowid`),
        UNIQUE KEY uk_service_nature_code (`code`,`entity`)
)ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;
