ALTER TABLE `llx_dynamicprices_product_cost_log` ADD INDEX `idx_dynamicprices_product_cost_log_entity` (`entity`);
ALTER TABLE `llx_dynamicprices_product_cost_log` ADD INDEX `idx_dynamicprices_product_cost_log_fk_product` (`fk_product`);
ALTER TABLE `llx_dynamicprices_product_cost_log` ADD INDEX `idx_dynamicprices_product_cost_log_date_creation` (`date_creation`);
ALTER TABLE `llx_dynamicprices_product_cost_log` ADD INDEX `idx_dynamicprices_product_cost_log_context` (`calculation_context`);
