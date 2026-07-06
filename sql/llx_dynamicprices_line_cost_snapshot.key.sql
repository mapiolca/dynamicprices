ALTER TABLE `llx_dynamicprices_line_cost_snapshot` ADD INDEX `idx_dynamicprices_line_cost_snapshot_entity` (`entity`);
ALTER TABLE `llx_dynamicprices_line_cost_snapshot` ADD INDEX `idx_dynamicprices_line_cost_snapshot_element` (`element_type`, `fk_elementdet`);
ALTER TABLE `llx_dynamicprices_line_cost_snapshot` ADD INDEX `idx_dynamicprices_line_cost_snapshot_fk_product` (`fk_product`);
ALTER TABLE `llx_dynamicprices_line_cost_snapshot` ADD UNIQUE INDEX `uk_dynamicprices_line_cost_snapshot_entity_line` (`entity`, `element_type`, `fk_elementdet`);
