ALTER TABLE `llx_dynamicprices_product_cost` ADD INDEX `idx_dynamicprices_product_cost_entity` (`entity`);
ALTER TABLE `llx_dynamicprices_product_cost` ADD INDEX `idx_dynamicprices_product_cost_fk_product` (`fk_product`);
ALTER TABLE `llx_dynamicprices_product_cost` ADD INDEX `idx_dynamicprices_product_cost_entity_product` (`entity`, `fk_product`);
ALTER TABLE `llx_dynamicprices_product_cost` ADD UNIQUE INDEX `uk_dynamicprices_product_cost_entity_product` (`entity`, `fk_product`);
