<?php
/* Copyright (C) 2025		Pierre ARDOIN
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

// Get margin on cost percent for a nature
function dynamicsprices_get_margin_on_cost_percent($db, $natureId)
{
	$sql = "SELECT margin_on_cost_percent";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_margin_on_cost";
	$sql .= " WHERE code_nature = '".$db->escape($natureId)."'";
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
function dynamicsprices_get_price_rules($db, $natureId)
{
	$rules = array();

	$sql = "SELECT pricelevel, minrate, targetrate";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_coefprice";
	$sql .= " WHERE fk_nature = ".((int) $natureId);
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

		$sqlv = "SELECT price, price_ttc, price_min, price_min_ttc";
		$sqlv .= " FROM ".MAIN_DB_PREFIX."product_price";
		$sqlv .= " WHERE fk_product = ".((int) $product->id);
		$sqlv .= " AND price_level = ".((int) $level);
		$sqlv .= " AND entity IN (".getEntity('productprice').")";
		$sqlv .= " ORDER BY date_price DESC LIMIT 1";

		$resqlv = $db->query($sqlv);
		$current = $db->fetch_object($resqlv);

		var_dump($current->fk_product_type);
		
		if ($current->fk_product_type != 0 || !$current || price2num($current->price, 2) != price2num($price, 2) || price2num($current->price_min, 2) != price2num($price_min, 2) || price2num($current->price_ttc, 2) != price2num($price_ttc, 2) || price2num($current->price_min_ttc, 2) != price2num($price_min_ttc, 2)) {
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

		$sqlw = "SELECT price, price_ttc, price_min, price_min_ttc";
		$sqlw .= " FROM ".MAIN_DB_PREFIX."product_price";
		$sqlw .= " WHERE fk_product = ".((int) $product->id);
		$sqlw .= " AND price_level = ".((int) $level);
		$sqlw .= " AND entity IN (".getEntity('productprice').")";
		$sqlw .= " ORDER BY date_price DESC LIMIT 1";

		$resqlv = $db->query($sqlw);
		$current = $db->fetch_object($resqlw);

		var_dump($current->fk_product_type);

		if ($current->fk_product_type != 0 || !$current || price2num($current->price, 2) != price2num($price, 2) || price2num($current->price_min, 2) != price2num($price_min, 2) || price2num($current->price_ttc, 2) != price2num($price_ttc, 2) || price2num($current->price_min_ttc, 2) != price2num($price_min_ttc, 2)) {
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
	$nb_line = 0;
	$entity = $conf->entity;

	if ($productid > 0) {
		$products[] = $productid;
	} else {
		$sql = "SELECT rowid, finished";
		$sql .= " FROM ".MAIN_DB_PREFIX."product";
		$sql .= " WHERE tosell = 1";
		$sql .= " AND entity IN (".getEntity('product').")";

		$resql = $db->query($sql);
		if ($resql === false) {
			dol_print_error($db);
			return 0;
		}

		while ($obj = $db->fetch_object($resql)) {
			$products[] = array('id' => $obj->rowid, 'nature' => $obj->finished);
		}
	}

	foreach ($products as $prod) {
		$prodid = is_array($prod) ? $prod['id'] : $prod;
		$natureid = is_array($prod) ? $prod['nature'] : 0;
		$product = new Product($db);
		$product->fetch($prodid);
		$tva_tx = (float) $product->tva_tx;

		if (dynamicsprices_is_kit($db, $prodid)) {
			$costPrice = dynamicsprices_update_kit_cost_price($db, $prodid);
			$rules = dynamicsprices_get_price_rules($db, $natureid);
			if (getDolGlobalInt('LMDB_KIT_PRICE_FROM_COMPONENTS')) {
				$components = dynamicsprices_get_kit_components($db, $prodid);
				$nb_line += dynamicsprices_update_kit_prices_from_components($db, $user, $product, $components, $tva_tx, $entity);
			} else {
				$nb_line += dynamicsprices_update_prices_from_base($db, $user, $product, $costPrice, $rules, $tva_tx, $entity);
			}
			continue;
		}

		$avgPrice = dynamicsprices_get_average_supplier_price($db, $prodid);
		if ($avgPrice === null) {
			continue;
		}

		$marginPercent = dynamicsprices_get_margin_on_cost_percent($db, $natureid);
		$costPrice = $avgPrice * (1 + ($marginPercent / 100));
		dynamicsprices_save_cost_price($db, $prodid, $costPrice);

		$rules = dynamicsprices_get_price_rules($db, $natureid);
		$nb_line += dynamicsprices_update_prices_from_base($db, $user, $product, $avgPrice, $rules, $tva_tx, $entity);
	}

	return $nb_line;
}

function update_customer_prices_from_cost_price($db, $user, $langs, $conf, $productid = 0)
{
	dol_include_once('/product/class/product.class.php');

	global $conf;

	$products = array();
	$nb_line = 0;
	$entity = $conf->entity;

	if ($productid > 0) {
		$products[] = $productid;
	} else {
		$sql = "SELECT rowid, finished, cost_price";
		$sql .= " FROM ".MAIN_DB_PREFIX."product";
		$sql .= " WHERE tosell = 1";
		$sql .= " AND entity IN (".getEntity('product').")";

		$resql = $db->query($sql);
		if ($resql === false) {
			dol_print_error($db);
			return 0;
		}

		while ($obj = $db->fetch_object($resql)) {
			$products[] = array('id' => $obj->rowid, 'nature' => $obj->finished, 'cost_price' => $obj->cost_price);
		}
	}

	foreach ($products as $prod) {
		$prodid = is_array($prod) ? $prod['id'] : $prod;
		$natureid = is_array($prod) ? $prod['nature'] : 0;
		$currentCost = is_array($prod) ? $prod['cost_price'] : 0;
		$product = new Product($db);
		$product->fetch($prodid);
		$tva_tx = (float) $product->tva_tx;

		if (dynamicsprices_is_kit($db, $prodid)) {
			$costPrice = dynamicsprices_update_kit_cost_price($db, $prodid);
			$rules = dynamicsprices_get_price_rules($db, $natureid);
			if (getDolGlobalInt('LMDB_KIT_PRICE_FROM_COMPONENTS')) {
				$components = dynamicsprices_get_kit_components($db, $prodid);
				$nb_line += dynamicsprices_update_kit_prices_from_components($db, $user, $product, $components, $tva_tx, $entity);
			} else {
				$nb_line += dynamicsprices_update_prices_from_base($db, $user, $product, $costPrice, $rules, $tva_tx, $entity);
			}
			continue;
		}

		$avgPrice = dynamicsprices_get_average_supplier_price($db, $prodid);
		if ($avgPrice !== null) {
			$marginPercent = dynamicsprices_get_margin_on_cost_percent($db, $natureid);
			$currentCost = $avgPrice * (1 + ($marginPercent / 100));
			dynamicsprices_save_cost_price($db, $prodid, $currentCost);
		}

		$rules = dynamicsprices_get_price_rules($db, $natureid);
		$nb_line += dynamicsprices_update_prices_from_base($db, $user, $product, $currentCost, $rules, $tva_tx, $entity);
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
