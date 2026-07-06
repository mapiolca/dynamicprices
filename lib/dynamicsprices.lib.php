<?php
/* Copyright (C) 2025		Pierre ARDOIN <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    dynamicsprices/lib/dynamicsprices.lib.php
 * \ingroup dynamicsprices
 * \brief   Library files with common functions for DynamicsPrices
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function dynamicspricesAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("dynamicsprices@dynamicsprices");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/dynamicsprices/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/dynamicsprices/admin/compatibility.php", 1);
	$head[$h][1] = $langs->trans("DynamicPricesCompatibility");
	$head[$h][2] = 'compatibility';
	$h++;

	$head[$h][0] = dol_buildpath("/dynamicsprices/admin/migrate_dynamic_cost.php", 1);
	$head[$h][1] = $langs->trans("DynamicPricesMigration");
	$head[$h][2] = 'migration';
	$h++;

	/*
	$head[$h][0] = dol_buildpath("/dynamicsprices/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dol_buildpath("/dynamicsprices/admin/myobjectline_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/
	$head[$h][0] = dol_buildpath("/dynamicsprices/admin/about.php", 1);
	$head[$h][1] = $langs->trans("LMDB_About");
	$head[$h][2] = 'about';
	$h++;

	/*
	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@dynamicsprices:/dynamicsprices/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@dynamicsprices:/dynamicsprices/mypage.php?id=__ID__'
	//); // to remove a tab
	*/
	complete_head_from_modules($conf, $langs, null, $head, $h, 'dynamicsprices@dynamicsprices');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dynamicsprices@dynamicsprices', 'remove');

	return $head;
}

/**
 * Check if a column exists on a table.
 *
 * @param DoliDB $db Database handler
 * @param string $tableName Table name
 * @param string $columnName Column name
 * @return bool
 */
function dynamicsprices_table_column_exists($db, $tableName, $columnName)
{
	$sql = "SHOW COLUMNS FROM ".$tableName." LIKE '".$db->escape($columnName)."'";
	$resql = $db->query($sql);

	return ($resql && $db->num_rows($resql) > 0);
}

/**
 * Return entity ids visible for a Dolibarr sharing element.
 *
 * @param string $element Dolibarr element key for getEntity()
 * @return array<int,int>
 */
function dynamicsprices_get_entity_ids_for_element($element)
{
	global $conf;

	$entityIds = array();
	$rawEntityList = (string) getEntity($element);
	$rawEntities = explode(',', $rawEntityList);

	foreach ($rawEntities as $rawEntity) {
		$entityId = (int) trim($rawEntity);
		if ($entityId > 0) {
			$entityIds[$entityId] = $entityId;
		}
	}

	if (empty($entityIds) && !empty($conf->entity)) {
		$entityIds[(int) $conf->entity] = (int) $conf->entity;
	}

	sort($entityIds);

	return array_values($entityIds);
}

/**
 * Check if automatic selling price writes are allowed in the current Multicompany scope.
 *
 * Shared selling prices based on local supplier prices are unsafe: two entities can write
 * different prices into the same shared sales catalogue. When supplier prices are not shared
 * over the same scope, only the configured source entity may write shared selling prices.
 *
 * @param string $context Diagnostic context
 * @return bool
 */
function dynamicsprices_can_update_shared_selling_prices($context = '')
{
	global $conf;

	static $canUpdate = null;
	static $logged = false;

	if ($canUpdate !== null) {
		return $canUpdate;
	}

	$sellingPriceEntities = dynamicsprices_get_entity_ids_for_element('productprice');
	if (count($sellingPriceEntities) <= 1) {
		$canUpdate = true;
		return true;
	}

	$supplierPriceEntities = dynamicsprices_get_entity_ids_for_element('product_fournisseur_price');
	$supplierScopeCoversSellingScope = count($supplierPriceEntities) > 1 && count(array_diff($sellingPriceEntities, $supplierPriceEntities)) === 0;
	if ($supplierScopeCoversSellingScope) {
		$canUpdate = true;
		return true;
	}

	$sourceEntity = getDolGlobalInt('DYNAMICPRICES_SHARED_SELL_PRICE_SOURCE_ENTITY', 0);
	if ($sourceEntity > 0) {
		$canUpdate = ((int) $conf->entity === $sourceEntity) && in_array($sourceEntity, $sellingPriceEntities, true);
		if (!$canUpdate && !$logged) {
			dol_syslog(
				__METHOD__.' skip shared selling price update: current_entity='.(int) $conf->entity
				.', source_entity='.$sourceEntity
				.', selling_scope='.implode(',', $sellingPriceEntities)
				.', supplier_price_scope='.implode(',', $supplierPriceEntities)
				.($context !== '' ? ', context='.$context : ''),
				LOG_WARNING
			);
			$logged = true;
		}

		return $canUpdate;
	}

	$canUpdate = false;
	if (!$logged) {
		dol_syslog(
			__METHOD__.' skip shared selling price update: product prices are shared but supplier purchase prices are not shared over the same scope'
			.', selling_scope='.implode(',', $sellingPriceEntities)
			.', supplier_price_scope='.implode(',', $supplierPriceEntities)
			.($context !== '' ? ', context='.$context : ''),
			LOG_WARNING
		);
		$logged = true;
	}

	return false;
}

/**
 * Build a scalar SQL expression resolving the commercial category code for a product.
 *
 * The commercial category dictionary follows product Multicompany sharing because the
 * category is carried by the product extrafield and must remain usable with shared products.
 *
 * @param DoliDB $db Database handler
 * @param string $rawCategoryExpression SQL expression containing the extrafield value
 * @param string $preferredEntityExpression SQL expression containing the product entity
 * @return string
 */
function dynamicsprices_get_commercial_category_code_sql($db, $rawCategoryExpression, $preferredEntityExpression = '')
{
	$hasEntity = dynamicsprices_table_column_exists($db, MAIN_DB_PREFIX."c_commercial_category", 'entity');
	$sql = "(SELECT cc.code";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_commercial_category AS cc";
	$sql .= " WHERE (cc.rowid = ".$rawCategoryExpression." OR BINARY cc.code = BINARY ".$rawCategoryExpression.")";
	if ($hasEntity) {
		$sql .= " AND cc.entity IN (".getEntity('product').")";
	}
	$sql .= " ORDER BY ";
	if ($preferredEntityExpression !== '' && $hasEntity) {
		$sql .= "CASE WHEN cc.entity = ".$preferredEntityExpression." THEN 0 ELSE 1 END, ";
	}
	$sql .= "cc.rowid ASC";
	$sql .= " LIMIT 1)";

	return $sql;
}

// Check if a product is a Kit using product associations
function dynamicsprices_is_kit($db, $productId)
{
	$sql = "SELECT COUNT(rowid) as nb";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_association";
	$sql .= " WHERE fk_product_pere = ".((int) $productId);
	//$sql .= " AND entity IN (".getEntity('product').")";
	//$sql .= " AND (type IS NULL OR type IN (0,1))";

	$resql = $db->query($sql);
	if ($resql === false) {
		return false;
	}

	$obj = $db->fetch_object($resql);
	return (!empty($obj->nb) && (int) $obj->nb > 0);
}

// Get Kit components with quantities
function dynamicsprices_get_kit_components($db, $productId)
{
	$components = array();

	$sql = "SELECT fk_product_fils as fk_product, qty";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_association";
	$sql .= " WHERE fk_product_pere = ".((int) $productId);
	//$sql .= " AND entity IN (".getEntity('product').")";
	//$sql .= " AND (type IS NULL OR type IN (0,1))";

	$resql = $db->query($sql);
	if ($resql === false) {
		return $components;
	}

	while ($obj = $db->fetch_object($resql)) {
		$components[] = array('id' => (int) $obj->fk_product, 'qty' => (float) $obj->qty);
	}

	return $components;
}

// Get parent Kits containing a component
function dynamicsprices_get_parent_kits($db, $productId)
{
	$parents = array();

	$sql = "SELECT fk_product_pere as fk_parent";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_association";
	$sql .= " WHERE fk_product_fils = ".((int) $productId);
	//$sql .= " AND entity IN (".getEntity('product').")";
	//$sql .= " AND (type IS NULL OR type IN (0,1))";

	$resql = $db->query($sql);
	if ($resql === false) {
		return $parents;
	}

	while ($obj = $db->fetch_object($resql)) {
		$parents[] = (int) $obj->fk_parent;
	}

	return $parents;
}

// Compute average supplier price
function dynamicsprices_get_average_supplier_price($db, $productId)
{
	$sql = "SELECT unitprice";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
	$sql .= " WHERE fk_product = ".((int) $productId);
	$sql .= " AND entity IN (".getEntity('product_fournisseur_price').")";

	$resql = $db->query($sql);
	if ($resql === false) {
		return null;
	}

	$prices = array();
	while ($obj = $db->fetch_object($resql)) {
		$prices[] = (float) $obj->unitprice;
	}

	if (count($prices) === 0) {
		return null;
	}

	return array_sum($prices) / count($prices);
}

// Get table column for commercial category with backward compatibility
function dynamicsprices_get_category_column_name($db, $tableName, $newColumn, $legacyColumn)
{
	$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX.$tableName." LIKE '".$db->escape($newColumn)."'";
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		return $newColumn;
	}

	return $legacyColumn;
}

// Get margin on cost percent for a commercial category
function dynamicsprices_get_margin_on_cost_percent($db, $commercialCategoryId)
{
	$categoryColumn = dynamicsprices_get_category_column_name($db, 'c_margin_on_cost', 'code_commercial_category', 'code_commercial_category');

	$sql = "SELECT margin_on_cost_percent";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_margin_on_cost";
	$sql .= " WHERE ".$categoryColumn." = '".$db->escape((string) $commercialCategoryId)."'";
	$sql .= " AND entity IN (".getEntity('c_margin_on_cost').")";
	$sql .= " AND active = 1";
	$sql .= " ORDER BY rowid DESC";
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if ($resql === false) {
		return 0;
	}

	$obj = $db->fetch_object($resql);
	return $obj ? (float) $obj->margin_on_cost_percent : 0;
}

// Fetch selling price rules from dictionary
function dynamicsprices_get_price_rules($db, $commercialCategoryId)
{
	$rules = array();
	$categoryColumn = dynamicsprices_get_category_column_name($db, 'c_coefprice', 'code_commercial_category', 'code_commercial_category');

	$sql = "SELECT pricelevel, minrate, targetrate";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_coefprice";
	$sql .= " WHERE ".$categoryColumn." = '".$db->escape((string) $commercialCategoryId)."'";
	$sql .= " AND entity IN (".getEntity('c_coefprice').")";
	$sql .= " AND active = 1";

	$resql = $db->query($sql);
	if ($resql === false) {
		return $rules;
	}

	while ($obj = $db->fetch_object($resql)) {
		$rules[(int) $obj->pricelevel] = array('minrate' => (float) $obj->minrate, 'targetrate' => (float) $obj->targetrate);
	}

	return $rules;
}

// Save DynamicPrices cost price without changing the native product cost by default.
function dynamicsprices_save_cost_price($db, $productId, $costPrice, $context = array())
{
	global $conf, $user;

	require_once __DIR__.'/../class/dynamicpricescostservice.class.php';

	if (!is_object($user)) {
		dol_syslog(__METHOD__.' no user object available to save DynamicPrices cost for product='.(int) $productId, LOG_ERR);
		return false;
	}

	$service = new DynamicPricesCostService($db);
	$entity = !empty($context['entity']) ? (int) $context['entity'] : (int) $conf->entity;
	$sourceType = !empty($context['source_type']) ? (string) $context['source_type'] : 'dynamicprices_engine';
	$sourceValue = array_key_exists('source_value', $context) ? $context['source_value'] : $costPrice;
	$coefficient = array_key_exists('coefficient', $context) ? $context['coefficient'] : null;

	$calculation = array(
		'entity' => $entity,
		'fk_product' => (int) $productId,
		'dynamic_cost_price' => $costPrice === null ? null : (float) price2num($costPrice, 'MU'),
		'price_base_type' => 'HT',
		'source_type' => $sourceType,
		'source_value' => $sourceValue === null ? null : (float) price2num($sourceValue, 'MU'),
		'source_details' => !empty($context['source_details']) ? (string) $context['source_details'] : '',
		'rule_code' => !empty($context['rule_code']) ? (string) $context['rule_code'] : '',
		'coefficient' => $coefficient === null ? null : (float) $coefficient,
		'rounding_rule' => (string) getDolGlobalString('DYNAMICPRICES_COST_ROUNDING_MODE', 'dolibarr'),
		'calculation_status' => 1,
		'calculation_message' => 'DynamicPricesCostCalculated',
		'status' => 1,
	);
	$calculation['calculation_hash'] = hash('sha256', json_encode(array(
		'dynamic_cost_price' => $calculation['dynamic_cost_price'],
		'source_type' => $calculation['source_type'],
		'source_value' => $calculation['source_value'],
		'rule_code' => $calculation['rule_code'],
		'coefficient' => $calculation['coefficient'],
	)));

	$result = $service->saveProductCost((int) $productId, $calculation, $user, array(
		'entity' => $entity,
		'calculation_context' => !empty($context['calculation_context']) ? (string) $context['calculation_context'] : 'engine',
	));

	if ($result < 0) {
		dol_syslog(__METHOD__.' '.$service->error, LOG_ERR);
		return false;
	}

	return true;
}

// Get commercial category code selected on product/service extrafield
function dynamicsprices_get_product_commercial_category($db, $productId)
{
	dol_include_once('/product/class/product.class.php');
	$product = new Product($db);
	if ($product->fetch((int) $productId) <= 0) {
		return '';
	}

	$product->fetch_optionals();
	if (empty($product->array_options['options_lmdb_commercial_category'])) {
		return '';
	}
	$rawValue = $product->array_options['options_lmdb_commercial_category'];
	if (!is_numeric($rawValue)) {
		return $rawValue;
	}
	$sql = "SELECT code FROM ".MAIN_DB_PREFIX."c_commercial_category WHERE rowid = ".((int) $rawValue);
	if (dynamicsprices_table_column_exists($db, MAIN_DB_PREFIX."c_commercial_category", 'entity')) {
		$sql .= " AND entity IN (".getEntity('product').")";
	}
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if ($resql === false) {
		return '';
	}
	$obj = $db->fetch_object($resql);
	return $obj ? $obj->code : '';
}

// Calculate and persist Kit cost price based on components
function dynamicsprices_update_kit_cost_price($db, $productId)
{
	global $langs;

	$langs->load("dynamicsprices@dynamicsprices");
	dol_include_once('/product/class/product.class.php');

	$kit = new Product($db);
	$kit->fetch((int) $productId);

	$components = dynamicsprices_get_kit_components($db, $productId);
	$totalCost = 0;

	foreach ($components as $component) {
		$componentUnitCost = dynamicsprices_get_component_unit_cost_for_kit($db, (int) $component['id'], $kit);
		if ($componentUnitCost === null) {
			return false;
		}
		$totalCost += $componentUnitCost * (float) $component['qty'];
	}

	dynamicsprices_save_cost_price($db, $productId, $totalCost, array('source_type' => 'kit_components'));

	return $totalCost;
}

/**
 * Resolve component unit cost used for kit cost computation.
 * Priority is: supplier average price, then cost price, then PMP.
 *
 * @param DoliDB $db Database handler
 * @param int    $componentId Component product id
 * @param Product $kit Kit product object
 * @return float|null Null when no usable price is available
 */
function dynamicsprices_get_component_unit_cost_for_kit($db, $componentId, $kit)
{
	global $langs;

	$langs->load("dynamicsprices@dynamicsprices");
	dol_include_once('/product/class/product.class.php');

	$avg = dynamicsprices_get_average_supplier_price($db, $componentId);
	if ($avg !== null) {
		return (float) $avg;
	}

	$component = new Product($db);
	if ($component->fetch($componentId) > 0) {
		$componentLink = dynamicsprices_get_product_ref_link($component->id, $component->ref);
		$kitLink = dynamicsprices_get_product_ref_link($kit->id, $kit->ref);
		$costPrice = price2num($component->cost_price, 'MU');
		if ($costPrice > 0) {
			setEventMessages($langs->trans('LMDB_KitCostFallbackToCostPriceWarning', $componentLink, $kitLink), null, 'warnings');
			return (float) $costPrice;
		}

		$pmp = price2num($component->pmp, 'MU');
		if ($pmp > 0) {
			setEventMessages($langs->trans('LMDB_KitCostFallbackToPmpWarning', $componentLink, $kitLink), null, 'warnings');
			return (float) $pmp;
		}

		setEventMessages($langs->trans('LMDB_KitCostMissingAllPricesError', $componentLink, $kitLink), null, 'errors');
	}

	return null;
}

/**
 * Build HTML link to product card.
 *
 * @param int    $productId Product id
 * @param string $productRef Product ref
 * @return string
 */
function dynamicsprices_get_product_ref_link($productId, $productRef)
{
	$url = dol_buildpath('/product/card.php?id='.(int) $productId, 1);
	return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.dol_escape_htmltag((string) $productRef).'</a>';
}

// Update selling prices from a base cost and rules
function dynamicsprices_update_prices_from_base($db, $user, $product, $basePrice, $rules, $tvaTx, $entity)
{
	if (!dynamicsprices_can_update_shared_selling_prices('update_prices_from_base')) {
		return 0;
	}

	$nb_line = 0;
	$now = $db->idate(dol_now());

	foreach ($rules as $level => $rule) {
		$price = $basePrice * (1 + ((float) $rule['targetrate'] / 100));
		$price_ttc = $price * (1 + ((float) $tvaTx / 100));
		$price_min = $basePrice * (1 + ((float) $rule['minrate'] / 100));
		$price_min_ttc = $price_min * (1 + ((float) $tvaTx / 100));
		$current = dynamicsprices_get_latest_price_for_level($db, $product->id, $level);

if (!$current || price2num($current->price, 2) != price2num($price, 2) || price2num($current->price_min, 2) != price2num($price_min, 2) || price2num($current->price_ttc, 2) != price2num($price_ttc, 2) || price2num($current->price_min_ttc, 2) != price2num($price_min_ttc, 2)) {
$sqlp = "INSERT INTO ".MAIN_DB_PREFIX."product_price (entity, fk_product, price_level, fk_user_author, price, price_ttc, price_min, price_min_ttc, date_price, tva_tx)";
$sqlp .= " VALUES (".((int) $entity).", ".((int) $product->id).", ".((int) $level).", ".((int) $user->id).", ".price2num($price, 2).", ".price2num($price_ttc, 2).", ".price2num($price_min, 2).", ".price2num($price_min_ttc, 2).", '".$now."', ".((float) $tvaTx).")";
$sqlp .= " ON DUPLICATE KEY UPDATE price = VALUES(price), price_ttc = VALUES(price_ttc), price_min = VALUES(price_min), price_min_ttc = VALUES(price_min_ttc), date_price = VALUES(date_price), tva_tx = VALUES(tva_tx)";
			$db->query($sqlp);
			$nb_line++;
		}
	}

	return $nb_line;
}

/**
 * Update selling prices from the current DynamicPrices cost price.
 *
 * @param DoliDB $db Database handler
 * @param User   $user User authoring the price change
 * @param int    $productId Product id
 * @param int    $entity Entity id
 * @return int Number of price rows updated, -1 on error
 */
function dynamicsprices_update_sales_prices_from_dynamic_cost($db, $user, $productId, $entity = 0)
{
	global $conf;

	$productId = (int) $productId;
	if ($productId <= 0) {
		return -1;
	}

	dol_include_once('/product/class/product.class.php');
	require_once __DIR__.'/../class/dynamicpricescostservice.class.php';

	$product = new Product($db);
	if ($product->fetch($productId) <= 0) {
		return -1;
	}
	if (!in_array((int) $product->type, array(Product::TYPE_PRODUCT, Product::TYPE_SERVICE), true)) {
		return 0;
	}

	$entity = $entity > 0 ? (int) $entity : (int) $conf->entity;
	$service = new DynamicPricesCostService($db);
	$costPrice = $service->getDynamicCostPrice($productId, $entity);
	if ($costPrice === null) {
		return 0;
	}

	$commercialCategoryId = dynamicsprices_get_product_commercial_category($db, $productId);
	$rules = dynamicsprices_get_price_rules($db, $commercialCategoryId);
	if (empty($rules)) {
		dol_syslog(__METHOD__.' no selling price rule found for product='.(int) $productId.' category='.$commercialCategoryId, LOG_WARNING);
		return 0;
	}

	return dynamicsprices_update_prices_from_base($db, $user, $product, (float) $costPrice, $rules, (float) $product->tva_tx, $entity);
}

// Fetch latest component prices per level
function dynamicsprices_get_component_prices_by_level($db, $productId)
{
	$prices = array();

	$sql = "SELECT price_level, price, price_min";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_price";
	$sql .= " WHERE fk_product = ".((int) $productId);
	$sql .= " AND entity IN (".getEntity('productprice').")";
	$sql .= " ORDER BY date_price DESC";

	$resql = $db->query($sql);
	if ($resql === false) {
	return $prices;
	}

	while ($obj = $db->fetch_object($resql)) {
	$level = (int) $obj->price_level;
	if (!isset($prices[$level])) {
	$prices[$level] = array('price' => (float) $obj->price, 'price_min' => (float) $obj->price_min);
	}
	}

	return $prices;
}

// Get latest price line for a product and level
function dynamicsprices_get_latest_price_for_level($db, $productId, $level)
{
	$sql = "SELECT price, price_ttc, price_min, price_min_ttc";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_price";
	$sql .= " WHERE fk_product = ".((int) $productId);
	$sql .= " AND price_level = ".((int) $level);
	$sql .= " AND entity IN (".getEntity('productprice').")";
	$sql .= " ORDER BY date_price DESC, rowid DESC";
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if ($resql === false) {
	return null;
	}

	return $db->fetch_object($resql);
}

// Update Kit prices by summing component prices
function dynamicsprices_update_kit_prices_from_components($db, $user, $product, $components, $tvaTx, $entity)
{
	if (!dynamicsprices_can_update_shared_selling_prices('update_kit_prices_from_components')) {
		return 0;
	}

	$levelTotals = array();
	$nb_line = 0;
	$now = $db->idate(dol_now());

	foreach ($components as $component) {
		$componentPrices = dynamicsprices_get_component_prices_by_level($db, $component['id']);
		foreach ($componentPrices as $level => $values) {
			if (!isset($levelTotals[$level])) {
				$levelTotals[$level] = array('price' => 0, 'price_min' => 0);
			}
			$levelTotals[$level]['price'] += ((float) $values['price']) * (float) $component['qty'];
			$levelTotals[$level]['price_min'] += ((float) $values['price_min']) * (float) $component['qty'];
		}
	}

	foreach ($levelTotals as $level => $values) {
		$price = (float) $values['price'];
		$price_min = (float) $values['price_min'];
		$price_ttc = $price * (1 + ((float) $tvaTx / 100));
		$price_min_ttc = $price_min * (1 + ((float) $tvaTx / 100));
		$current = dynamicsprices_get_latest_price_for_level($db, $product->id, $level);

if (!$current || price2num($current->price, 2) != price2num($price, 2) || price2num($current->price_min, 2) != price2num($price_min, 2) || price2num($current->price_ttc, 2) != price2num($price_ttc, 2) || price2num($current->price_min_ttc, 2) != price2num($price_min_ttc, 2)) {
$sqlp = "INSERT INTO ".MAIN_DB_PREFIX."product_price (entity, fk_product, price_level, fk_user_author, price, price_ttc, price_min, price_min_ttc, date_price, tva_tx)";
$sqlp .= " VALUES (".((int) $entity).", ".((int) $product->id).", ".((int) $level).", ".((int) $user->id).", ".price2num($price, 2).", ".price2num($price_ttc, 2).", ".price2num($price_min, 2).", ".price2num($price_min_ttc, 2).", '".$now."', ".((float) $tvaTx).")";
$sqlp .= " ON DUPLICATE KEY UPDATE price = VALUES(price), price_ttc = VALUES(price_ttc), price_min = VALUES(price_min), price_min_ttc = VALUES(price_min_ttc), date_price = VALUES(date_price), tva_tx = VALUES(tva_tx)";
			$db->query($sqlp);
			$nb_line++;
		}
	}

	return $nb_line;
}

function update_customer_prices_from_suppliers($db, $user, $langs, $conf, $productid = 0)
{




		dol_include_once('/product/class/product.class.php');
	
		global $conf;
	
		$products = array();
		$kits = array();
		$nb_line = 0;
		$entity = $conf->entity;
	
		if ($productid > 0) {
			$product = new Product($db);
			if ($product->fetch($productid) > 0 && !in_array((int) $product->type, array(Product::TYPE_PRODUCT, Product::TYPE_SERVICE), true)) {
				return 0;
			}
			$products[] = $productid;
		} else {
			$sql = "SELECT p.rowid, ".dynamicsprices_get_commercial_category_code_sql($db, 'ef.lmdb_commercial_category', 'p.entity')." as code_commercial_category";
			$sql .= " FROM ".MAIN_DB_PREFIX."product";
			$sql .= " as p";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as ef ON ef.fk_object = p.rowid";
			$sql .= " WHERE p.tosell = 1";
			$sql .= " AND p.fk_product_type IN (0,1)";
			$sql .= " AND p.entity IN (".getEntity('product').")";
		
			$resql = $db->query($sql);
			if ($resql === false) {
				dol_print_error($db);
				return 0;
			}
		
			while ($obj = $db->fetch_object($resql)) {
				$products[] = array('id' => $obj->rowid, 'commercial_category' => $obj->code_commercial_category);
			}
	}
	
		foreach ($products as $prod) {
		$prodid = is_array($prod) ? $prod['id'] : $prod;
		$commercialCategoryId = is_array($prod) ? (string) $prod['commercial_category'] : dynamicsprices_get_product_commercial_category($db, $prodid);
		$product = new Product($db);
		$product->fetch($prodid);
		if (!in_array((int) $product->type, array(Product::TYPE_PRODUCT, Product::TYPE_SERVICE), true)) {
			continue;
		}
		$tva_tx = (float) $product->tva_tx;
	
		if (dynamicsprices_is_kit($db, $prodid)) {
		$kits[] = array('id' => $prodid, 'commercial_category' => $commercialCategoryId, 'tva' => $tva_tx);
		continue;
		}
	
		$avgPrice = dynamicsprices_get_average_supplier_price($db, $prodid);
		if ($avgPrice === null) {
		continue;
		}
	
		$marginPercent = dynamicsprices_get_margin_on_cost_percent($db, $commercialCategoryId);
		$costPrice = $avgPrice * (1 + ($marginPercent / 100));
		dynamicsprices_save_cost_price($db, $prodid, $costPrice, array(
			'entity' => $entity,
			'source_type' => 'supplier_average',
			'source_value' => $avgPrice,
			'rule_code' => (string) $commercialCategoryId,
			'coefficient' => 1 + (((float) $marginPercent) / 100),
		));

		$rules = dynamicsprices_get_price_rules($db, $commercialCategoryId);
		$nb_line += dynamicsprices_update_prices_from_base($db, $user, $product, $avgPrice, $rules, $tva_tx, $entity);
		}

		foreach ($kits as $kit) {
		$prodid = $kit['id'];
		$commercialCategoryId = $kit['commercial_category'];
		$tva_tx = $kit['tva'];
		$product = new Product($db);
		$product->fetch($prodid);

		$costPrice = dynamicsprices_update_kit_cost_price($db, $prodid);
		if ($costPrice === false) {
			return -1;
		}
		$rules = dynamicsprices_get_price_rules($db, $commercialCategoryId);
		if (getDolGlobalInt('LMDB_KIT_PRICE_FROM_COMPONENTS')) {
		$components = dynamicsprices_get_kit_components($db, $prodid);
		$nb_line += dynamicsprices_update_kit_prices_from_components($db, $user, $product, $components, $tva_tx, $entity);
		} else {
		$nb_line += dynamicsprices_update_prices_from_base($db, $user, $product, $costPrice, $rules, $tva_tx, $entity);
		}
		}
	
		return $nb_line;




}

function update_customer_prices_from_cost_price($db, $user, $langs, $conf, $productid = 0)
{




		dol_include_once('/product/class/product.class.php');
	
		global $conf;
	
		$products = array();
		$kits = array();
		$nb_line = 0;
		$entity = $conf->entity;
	
		if ($productid > 0) {
			$product = new Product($db);
			if ($product->fetch($productid) > 0 && !in_array((int) $product->type, array(Product::TYPE_PRODUCT, Product::TYPE_SERVICE), true)) {
				return 0;
			}
			$products[] = $productid;
		} else {
			$sql = "SELECT p.rowid, p.cost_price, ".dynamicsprices_get_commercial_category_code_sql($db, 'ef.lmdb_commercial_category', 'p.entity')." as code_commercial_category";
			$sql .= " FROM ".MAIN_DB_PREFIX."product";
			$sql .= " as p";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as ef ON ef.fk_object = p.rowid";
			$sql .= " WHERE p.tosell = 1";
			$sql .= " AND p.fk_product_type IN (0,1)";
			$sql .= " AND p.entity IN (".getEntity('product').")";
		
			$resql = $db->query($sql);
			if ($resql === false) {
				dol_print_error($db);
				return 0;
			}
		
			while ($obj = $db->fetch_object($resql)) {
				$products[] = array('id' => $obj->rowid, 'commercial_category' => $obj->code_commercial_category, 'cost_price' => $obj->cost_price);
			}
	}
	
		foreach ($products as $prod) {
		$prodid = is_array($prod) ? $prod['id'] : $prod;
		$commercialCategoryId = is_array($prod) ? (string) $prod['commercial_category'] : dynamicsprices_get_product_commercial_category($db, $prodid);
		$currentCost = is_array($prod) ? $prod['cost_price'] : 0;
		$product = new Product($db);
		$product->fetch($prodid);
		if (!in_array((int) $product->type, array(Product::TYPE_PRODUCT, Product::TYPE_SERVICE), true)) {
			continue;
		}
		$tva_tx = (float) $product->tva_tx;
	
		if (dynamicsprices_is_kit($db, $prodid)) {
		$kits[] = array('id' => $prodid, 'commercial_category' => $commercialCategoryId, 'tva' => $tva_tx);
		continue;
		}
	
		$avgPrice = dynamicsprices_get_average_supplier_price($db, $prodid);
		if ($avgPrice !== null) {
		$marginPercent = dynamicsprices_get_margin_on_cost_percent($db, $commercialCategoryId);
		$currentCost = $avgPrice * (1 + ($marginPercent / 100));
		dynamicsprices_save_cost_price($db, $prodid, $currentCost, array(
			'entity' => $entity,
			'source_type' => 'supplier_average',
			'source_value' => $avgPrice,
			'rule_code' => (string) $commercialCategoryId,
			'coefficient' => 1 + (((float) $marginPercent) / 100),
		));
		} else {
		dynamicsprices_save_cost_price($db, $prodid, $currentCost, array(
			'entity' => $entity,
			'source_type' => 'cost_price',
			'source_value' => $currentCost,
			'rule_code' => (string) $commercialCategoryId,
			'coefficient' => 1,
		));
		}

		$rules = dynamicsprices_get_price_rules($db, $commercialCategoryId);
		$nb_line += dynamicsprices_update_prices_from_base($db, $user, $product, $currentCost, $rules, $tva_tx, $entity);
		}

		foreach ($kits as $kit) {
		$prodid = $kit['id'];
		$commercialCategoryId = $kit['commercial_category'];
		$tva_tx = $kit['tva'];
		$product = new Product($db);
		$product->fetch($prodid);

		$costPrice = dynamicsprices_update_kit_cost_price($db, $prodid);
		if ($costPrice === false) {
			return -1;
		}
		$rules = dynamicsprices_get_price_rules($db, $commercialCategoryId);
		if (getDolGlobalInt('LMDB_KIT_PRICE_FROM_COMPONENTS')) {
		$components = dynamicsprices_get_kit_components($db, $prodid);
		$nb_line += dynamicsprices_update_kit_prices_from_components($db, $user, $product, $components, $tva_tx, $entity);
		} else {
		$nb_line += dynamicsprices_update_prices_from_base($db, $user, $product, $costPrice, $rules, $tva_tx, $entity);
		}
		}
	
		return $nb_line;
	
	
	
	
	}
	
	/**
	 * Print a table section title.
	 *
	 * @param string $title Title key to translate
	 * @param int    $width Width of the column
	 * @return void
	 */
	function setup_print_title($title = "Parameter", $width = 300)
	{
		global $langs;
	
		print '<tr class="liste_titre">';
		print '<td class="titlefield">'.$langs->trans($title).'</td>';
		print '<td class="titlefield" align="center" width="20">&nbsp;</td>';
	print '<td class="titlefield" align="center">'.$langs->trans('Value').'</td>';
	print '</tr>';
}

function setup_print_on_off($confkey, $title = false, $desc ='', $help = false, $width = 300, $forcereload = false, $ajaxConstantOnOffInput = array())
{
	global $var, $bc, $langs, $conf, $form;
	$var=!$var;

	print '<tr>';
	print '<td>';


	if(empty($help) && !empty($langs->tab_translate[$confkey . '_HELP'])){
		$help = $confkey . '_HELP';
	}

	if(!empty($help)){
		print $form->textwithtooltip( ($title?$title:$langs->trans($confkey)) , $langs->trans($help),2,1,img_help(1,''));
	}
	else {
		print $title?$title:$langs->trans($confkey);
	}

	if(!empty($desc))
	{
		print '<br><small>'.$langs->trans($desc).'</small>';
	}
	print '</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="'.$width.'">';

	if($forcereload){
		$link = $_SERVER['PHP_SELF'].'?action=set_'.$confkey.'&token='. newToken() .'&'.$confkey.'='.intval((empty($conf->global->{$confkey})));
		$toggleClass = empty($conf->global->{$confkey})?'fa-toggle-off':'fa-toggle-on font-status4';
		print '<a href="'.$link.'" ><span class="fas '.$toggleClass.' marginleftonly" style=" color: #999;"></span></a>';
	}
	else{
		print ajax_constantonoff($confkey, $ajaxConstantOnOffInput);
	}
	print '</td></tr>';
}

/**
 * Auto print form part for setup
 * @param string $confkey
 * @param bool $title
 * @param string $desc
 * @param array $metas exemple use with color array('type'=>'color') or  with placeholder array('placeholder'=>'http://')
 * @param string $type = 'imput', 'textarea' or custom html
 * @param bool $help
 * @param int $width
 */
function setup_print_input_form_part($confkey, $title = false, $desc ='', $metas = array(), $type='input', $help = false, $width = 300)
{
	global $var, $bc, $langs, $conf, $db;
	$var=!$var;

	if(empty($help) && !empty($langs->tab_translate[$confkey . '_HELP'])){
		$help = $confkey . '_HELP';
	}

	$form=new Form($db);

	$defaultMetas = array(
		'name' => $confkey
	);

	if($type!='textarea'){
		$defaultMetas['type']   = 'text';
		$defaultMetas['value']  = isset($conf->global->{$confkey}) ? $conf->global->{$confkey} : '';
	}


	$metas = array_merge ($defaultMetas, $metas);
	$metascompil = '';
	foreach ($metas as $key => $values)
	{
		$metascompil .= ' '.$key.'="'.$values.'" ';
	}

	print '<tr>';
	print '<td>';

	if(!empty($help)){
		print $form->textwithtooltip( ($title?$title:$langs->trans($confkey)) , $langs->trans($help),2,1,img_help(1,''));
	}
	else {
		print $title?$title:$langs->trans($confkey);
	}

	if(!empty($desc))
	{
		print '<br><small>'.$langs->trans($desc).'</small>';
	}

	print '</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="'.$width.'">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" '.($metas['type'] === 'file' ? 'enctype="multipart/form-data"' : '').'>';
	print '<input type="hidden" name="token" value="'. newToken() .'">';
	print '<input type="hidden" name="action" value="set_'.$confkey.'">';

		if($type=='textarea'){
			print '<textarea '.$metascompil.'  >'.dol_htmlentities($conf->global->{$confkey}).'</textarea>';
		}
		elseif($type=='input'){
			print '<input '.$metascompil.'  />';
		}
		else{
			// custom
			print $type;
		}

	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
}
