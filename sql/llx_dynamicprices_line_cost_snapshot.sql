CREATE TABLE IF NOT EXISTS `llx_dynamicprices_line_cost_snapshot` (
	`rowid` integer AUTO_INCREMENT PRIMARY KEY,
	`entity` integer NOT NULL DEFAULT 1,
	`element_type` varchar(32) NOT NULL,
	`fk_elementdet` integer NOT NULL,
	`fk_element` integer NULL,
	`fk_product` integer NULL,
	`dynamic_cost_price` double(24,8) NULL,
	`native_pa_ht_before` double(24,8) NULL,
	`native_pa_ht_after` double(24,8) NULL,
	`fk_product_cost` integer NULL,
	`fk_product_cost_log` integer NULL,
	`source_type` varchar(32) NULL,
	`rule_code` varchar(64) NULL,
	`calculation_hash` varchar(64) NULL,
	`date_creation` datetime NOT NULL,
	`fk_user_author` integer NULL,
	`status` smallint DEFAULT 1
) ENGINE=innodb;
