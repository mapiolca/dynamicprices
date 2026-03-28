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
	/*
	$head[$h][0] = dol_buildpath("/dynamicsprices/admin/other.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

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
 * Check if a table exists.
 *
 * @param DoliDB $db Database handler
 * @param string $tableName Table name
 * @return bool
 */
function dynamicsprices_table_exists($db, $tableName)
{
	$sql = "SHOW TABLES LIKE '".$db->escape($tableName)."'";
	$resql = $db->query($sql);

	return ($resql && $db->num_rows($resql) > 0);
}

/**
 * Check if a column exists on a table.
 *
 * @param DoliDB $db Database handler
 * @param string $tableName Table name
 * @param string $columnName Column name
 * @return bool
 */
function dynamicsprices_column_exists($db, $tableName, $columnName)
{
	$sql = "SHOW COLUMNS FROM ".$tableName." LIKE '".$db->escape($columnName)."'";
	$resql = $db->query($sql);

	return ($resql && $db->num_rows($resql) > 0);
}

/**
 * Run migration scripts manually from setup page.
 *
 * @param DoliDB $db Database handler
 * @return bool
 */
function dynamicsprices_run_manual_migrations($db)
{
	dol_syslog(__FUNCTION__.' - Start manual migrations', LOG_DEBUG);
	$ok = true;

	$coefpriceTable = MAIN_DB_PREFIX."c_coefprice";
	$marginOnCostTable = MAIN_DB_PREFIX."c_margin_on_cost";
	$commercialCategoryTable = MAIN_DB_PREFIX."c_commercial_category";
	$productNatureTable = MAIN_DB_PREFIX."c_product_nature";
	$productTable = MAIN_DB_PREFIX."product";
	$productExtraTable = MAIN_DB_PREFIX."product_extrafields";

	if (!dynamicsprices_column_exists($db, $coefpriceTable, 'entity')) {
		$res = $db->query("ALTER TABLE ".$coefpriceTable." ADD COLUMN entity INTEGER NOT NULL DEFAULT 1");
		dol_syslog(__FUNCTION__.' - Add c_coefprice.entity result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
		$ok = ($ok && (bool) $res);
	}
	$res = $db->query("UPDATE ".$coefpriceTable." SET entity = 1 WHERE entity IS NULL");
	dol_syslog(__FUNCTION__.' - Normalize c_coefprice.entity result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
	$ok = ($ok && (bool) $res);

	if (!dynamicsprices_column_exists($db, $coefpriceTable, 'code_commercial_category')) {
		$res = $db->query("ALTER TABLE ".$coefpriceTable." ADD COLUMN code_commercial_category VARCHAR(50) DEFAULT NULL");
		dol_syslog(__FUNCTION__.' - Add c_coefprice.code_commercial_category result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
		$ok = ($ok && (bool) $res);
	}

	$res = $db->query("INSERT INTO ".$commercialCategoryTable." (code, label, active) SELECT DISTINCT t.fk_nature, t.fk_nature, 1 FROM ".$coefpriceTable." as t LEFT JOIN ".$commercialCategoryTable." as cc ON cc.code = t.fk_nature WHERE t.fk_nature IS NOT NULL AND t.fk_nature <> '' AND cc.rowid IS NULL");
	dol_syslog(__FUNCTION__.' - Insert categories from c_coefprice result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
	$ok = ($ok && (bool) $res);
	$res = $db->query("UPDATE ".$coefpriceTable." SET code_commercial_category = fk_nature WHERE (code_commercial_category IS NULL OR code_commercial_category = '') AND fk_nature IS NOT NULL AND fk_nature <> ''");
	dol_syslog(__FUNCTION__.' - Fill c_coefprice.code_commercial_category result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
	$ok = ($ok && (bool) $res);

	if (!dynamicsprices_column_exists($db, $marginOnCostTable, 'entity')) {
		$res = $db->query("ALTER TABLE ".$marginOnCostTable." ADD COLUMN entity INTEGER NOT NULL DEFAULT 1");
		dol_syslog(__FUNCTION__.' - Add c_margin_on_cost.entity result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
		$ok = ($ok && (bool) $res);
	}
	$res = $db->query("UPDATE ".$marginOnCostTable." SET entity = 1 WHERE entity IS NULL");
	dol_syslog(__FUNCTION__.' - Normalize c_margin_on_cost.entity result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
	$ok = ($ok && (bool) $res);

	if (!dynamicsprices_column_exists($db, $marginOnCostTable, 'code_commercial_category')) {
		$res = $db->query("ALTER TABLE ".$marginOnCostTable." ADD COLUMN code_commercial_category VARCHAR(50) DEFAULT NULL");
		dol_syslog(__FUNCTION__.' - Add c_margin_on_cost.code_commercial_category result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
		$ok = ($ok && (bool) $res);
	}

	$res = $db->query("INSERT INTO ".$commercialCategoryTable." (code, label, active) SELECT DISTINCT t.code_nature, t.code_nature, 1 FROM ".$marginOnCostTable." as t LEFT JOIN ".$commercialCategoryTable." as cc ON cc.code = t.code_nature WHERE t.code_nature IS NOT NULL AND t.code_nature <> '' AND cc.rowid IS NULL");
	dol_syslog(__FUNCTION__.' - Insert categories from c_margin_on_cost result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
	$ok = ($ok && (bool) $res);
	$res = $db->query("UPDATE ".$marginOnCostTable." SET code_commercial_category = code_nature WHERE (code_commercial_category IS NULL OR code_commercial_category = '') AND code_nature IS NOT NULL AND code_nature <> ''");
	dol_syslog(__FUNCTION__.' - Fill c_margin_on_cost.code_commercial_category result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
	$ok = ($ok && (bool) $res);

	if (dynamicsprices_table_exists($db, $productNatureTable)) {
		$res = $db->query("INSERT INTO ".$commercialCategoryTable." (code, label, active) SELECT DISTINCT CAST(pn.code AS CHAR), COALESCE(NULLIF(TRIM(CAST(pn.label AS CHAR)), ''), CAST(pn.code AS CHAR)), 1 FROM ".$productNatureTable." as pn LEFT JOIN ".$commercialCategoryTable." as cc ON cc.code = CAST(pn.code AS CHAR) WHERE pn.code IS NOT NULL AND CAST(pn.code AS CHAR) <> '' AND cc.rowid IS NULL");
		dol_syslog(__FUNCTION__.' - Insert categories from c_product_nature result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
		$ok = ($ok && (bool) $res);
	} else {
		dol_syslog(__FUNCTION__.' - Table c_product_nature not found, skip dictionary copy', LOG_WARNING);
	}

	$sourceExpression = '';
	$sourceFromExtrafields = false;
	if (dynamicsprices_column_exists($db, $productExtraTable, 'fk_nature')) {
		$sourceExpression = "pe.fk_nature";
		$sourceFromExtrafields = true;
	} elseif (dynamicsprices_column_exists($db, $productExtraTable, 'nature')) {
		$sourceExpression = "pe.nature";
		$sourceFromExtrafields = true;
	} elseif (dynamicsprices_column_exists($db, $productTable, 'fk_nature')) {
		$sourceExpression = "p.fk_nature";
	} elseif (dynamicsprices_column_exists($db, $productTable, 'nature')) {
		$sourceExpression = "p.nature";
	}

	if (!empty($sourceExpression)) {
		if (!$sourceFromExtrafields) {
			$res = $db->query("INSERT INTO ".$productExtraTable." (fk_object, lmdb_commercial_category) SELECT p.rowid, ".$sourceExpression." FROM ".$productTable." as p LEFT JOIN ".$productExtraTable." as pe ON pe.fk_object = p.rowid WHERE pe.fk_object IS NULL AND ".$sourceExpression." IS NOT NULL AND ".$sourceExpression." <> '' AND p.entity IN (".getEntity('product').")");
			dol_syslog(__FUNCTION__.' - Insert product_extrafields rows result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
			$ok = ($ok && (bool) $res);
		}
		$res = $db->query("UPDATE ".$productExtraTable." as pe INNER JOIN ".$productTable." as p ON p.rowid = pe.fk_object SET pe.lmdb_commercial_category = ".$sourceExpression." WHERE (pe.lmdb_commercial_category IS NULL OR pe.lmdb_commercial_category = '') AND ".$sourceExpression." IS NOT NULL AND ".$sourceExpression." <> '' AND p.entity IN (".getEntity('product').")");
		dol_syslog(__FUNCTION__.' - Update product_extrafields.lmdb_commercial_category result='.((int) $res), $res ? LOG_DEBUG : LOG_ERR);
		$ok = ($ok && (bool) $res);
	} else {
		dol_syslog(__FUNCTION__.' - No product nature source found, skip extrafield migration', LOG_WARNING);
	}

	dol_syslog(__FUNCTION__.' - End manual migrations status='.((int) $ok), $ok ? LOG_DEBUG : LOG_ERR);
	return $ok;
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
	$sql .= " AND entity IN (".getEntity('entity').")";
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
	$sql .= " AND entity IN (".getEntity('entity').")";
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

// Save cost price on product table
function dynamicsprices_save_cost_price($db, $productId, $costPrice)
{
	$sql = "UPDATE ".MAIN_DB_PREFIX."product";
	$sql .= " SET cost_price = ".price2num($costPrice, 'MU');
	$sql .= " WHERE rowid = ".((int) $productId);
	$sql .= " AND entity IN (".getEntity('product').")";

	return $db->query($sql);
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
	$components = dynamicsprices_get_kit_components($db, $productId);
	$totalCost = 0;

	foreach ($components as $component) {
		$avg = dynamicsprices_get_average_supplier_price($db, $component['id']);
		$avg = ($avg === null) ? 0 : $avg;
		$totalCost += $avg * (float) $component['qty'];
	}

	dynamicsprices_save_cost_price($db, $productId, $totalCost);

	return $totalCost;
}

// Update selling prices from a base cost and rules
function dynamicsprices_update_prices_from_base($db, $user, $product, $basePrice, $rules, $tvaTx, $entity)
{
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
			$sql = "SELECT p.rowid, cc.code as code_commercial_category";
			$sql .= " FROM ".MAIN_DB_PREFIX."product";
			$sql .= " as p";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as ef ON ef.fk_object = p.rowid";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_commercial_category as cc ON (cc.rowid = ef.lmdb_commercial_category OR BINARY cc.code = BINARY ef.lmdb_commercial_category)";
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
		$commercialCategoryId = is_array($prod) ? ((int) $prod['commercial_category']) : dynamicsprices_get_product_commercial_category($db, $prodid);
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
		dynamicsprices_save_cost_price($db, $prodid, $costPrice);

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
			$sql = "SELECT p.rowid, p.cost_price, cc.code as code_commercial_category";
			$sql .= " FROM ".MAIN_DB_PREFIX."product";
			$sql .= " as p";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as ef ON ef.fk_object = p.rowid";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_commercial_category as cc ON (cc.rowid = ef.lmdb_commercial_category OR BINARY cc.code = BINARY ef.lmdb_commercial_category)";
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
		$commercialCategoryId = is_array($prod) ? ((int) $prod['commercial_category']) : dynamicsprices_get_product_commercial_category($db, $prodid);
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
		dynamicsprices_save_cost_price($db, $prodid, $currentCost);
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
