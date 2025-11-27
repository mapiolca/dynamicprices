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


/**
 * Retrieve kit components for a parent product.
 *
 * @param DoliDB  $db       Database handler
 * @param Product $product  Product object
 * @return array<int, array{fk_child:int, qty:float}>
 */
function dynamicsPricesGetKitComponents(DoliDB $db, Product $product)
{
	$components = array();

	if (empty($product->id)) {
		return $components;
	}

	$sql = "SELECT fk_product_fils AS fk_child, qty";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_association";
	$sql .= " WHERE fk_product_pere = ".((int) $product->id);
	$sql .= " AND entity IN (".getEntity('product').")";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$childId = (int) $obj->fk_child;
			$qty = (float) $obj->qty;
			if ($childId > 0 && $qty > 0) {
				$components[] = array('fk_child' => $childId, 'qty' => $qty);
			}
		}
	}

	return $components;
}


/**
 * Compute kit cost price from component average costs.
 *
 * @param DoliDB $db          Database handler
 * @param array  $components  Components list from dynamicsPricesGetKitComponents
 * @return float|null         Aggregated cost price or null if no components
 */
function dynamicsPricesComputeKitCost(DoliDB $db, array $components)
{
	if (empty($components)) {
		return null;
	}

	dol_include_once('/product/class/product.class.php');

	$totalCost = 0.0;
	foreach ($components as $component) {
		$childProduct = new Product($db);
		if ($childProduct->fetch((int) $component['fk_child']) > 0) {
			$lineCost = price2num($childProduct->cost_price, 'MU') * (float) $component['qty'];
			$totalCost += $lineCost;
		}
	}

	return price2num($totalCost, 'MU');
}


/**
 * Update product cost price if different from target.
 *
 * @param DoliDB  $db       Database handler
 * @param User    $user     User performing update
 * @param Product $product  Product object
 * @param float   $newcost  New cost price
 * @return bool             True if updated, false otherwise
 */
function dynamicsPricesUpdateCostPrice(DoliDB $db, User $user, Product $product, $newcost)
{
	$current = price2num($product->cost_price, 'MU');
	$target = price2num($newcost, 'MU');

	if ($current == $target) {
		return false;
	}

	$sql = "UPDATE ".MAIN_DB_PREFIX."product";
	$sql .= " SET cost_price = ".$target."";
	$sql .= ", fk_user_modif = ".((int) $user->id);
	$sql .= ", tms = '".$db->idate(dol_now())."'";
	$sql .= " WHERE rowid = ".((int) $product->id);
	$sql .= " AND entity IN (".getEntity('product').")";

	$resql = $db->query($sql);
	if ($resql) {
		$product->cost_price = $target;
		return true;
	}

	dol_syslog(__METHOD__." Unable to update cost price for product " . $product->id, LOG_ERR);

	return false;
}


function update_customer_prices_from_suppliers($db, $user, $langs, $conf, $productid = 0)
{
	dol_include_once('/product/class/product.class.php');
	
	global $conf;
	
	$products = [];
	$nb_line = 0;
	$entity = $conf->entity;

	if ($productid > 0) {
	$products[] = $productid;
	} else {
	$sql = "SELECT rowid, finished";
	$sql.= " FROM ".MAIN_DB_PREFIX."product";
	$sql.= " WHERE tosell = 1 ";
	$sql.= " AND entity IN (".getEntity('product').")";

	//var_dump($sql.'<br>');

	$resql = $db->query($sql);
	if ($resql === false) {
			dol_print_error($db);
			return;
		}

	while ($obj = $db->fetch_object($resql)) {
	    $products[] = array('id'=>$obj->rowid, 'nature'=>$obj->finished);
	}
	}

	foreach ($products as $prod) {
		$prodid = is_array($prod) ? $prod['id'] : $prod;
		$natureid = is_array($prod) ? $prod['nature'] : 0;
		$product = new Product($db);
		$product->fetch($prodid);

		$kitcomponents = dynamicsPricesGetKitComponents($db, $product);
		$kitcost = dynamicsPricesComputeKitCost($db, $kitcomponents);
		if ($kitcost !== null) {
			dynamicsPricesUpdateCostPrice($db, $user, $product, $kitcost);
			$costsource = $kitcost;
		} else {
			$costsource = null;
		}

		$tva_tx = (float) $product->tva_tx;

		// Supplier prices or kit cost
		if ($costsource === null) {
			$sqlf = "SELECT price FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
			$sqlf .= " WHERE fk_product = ".((int) $prodid);
			$sqlf .= " AND entity IN (".getEntity('product_fournisseur_price').")";

			$resqlf = $db->query($sqlf);

			$prices_fourn = array();
			while ($objf = $db->fetch_object($resqlf)) {
				$prices_fourn[] = (float) $objf->price;
			}

			if (!count($prices_fourn)) {
				continue;
			}

			$costsource = array_sum($prices_fourn) / count($prices_fourn);
		}

		$basecost = $costsource;

		// Coefficients par nature
		$sqlc = "SELECT code, pricelevel, minrate, targetrate";
		$sqlc .= " FROM ".MAIN_DB_PREFIX."c_coefprice";
		$sqlc .= " WHERE fk_nature = ".((int) $natureid);
		$sqlc .= " AND entity IN (".getEntity('entity').")";

		$resqlc = $db->query($sqlc);

		while ($objc = $db->fetch_object($resqlc)) {
			$level = (int) $objc->pricelevel;
			$minrate = (float) $objc->minrate;
			$targetrate = (float) $objc->targetrate;

			$price = $basecost * (1 + $targetrate / 100);
			$price_ttc = $price * (1 + $tva_tx / 100);
			$price_min = $basecost * (1 + $minrate / 100);
			$price_min_ttc = $price_min * (1 + $tva_tx / 100);

			$now = $db->idate(dol_now());

	    $sqlv = "SELECT price_level, price, price_ttc, price_min, price_min_ttc, tva_tx ";
	    $sqlv.= " FROM ".MAIN_DB_PREFIX."product_price";
	    $sqlv.= " WHERE fk_product = ".((int) $prodid) ;
	    $sqlv.= " AND price_level = ".$level;
	    $sqlv.= " AND entity IN (".getEntity('productprice').")";
	    $sqlv.= " ORDER BY date_price DESC LIMIT 1";
	    //var_dump('$sqlv = '.$sqlv.'<br>');

	    $resqlv = $db->query($sqlv);

	    while ($objv = $db->fetch_object($resqlv)) {
	    	$price_v = price2num($objv->price,2);
	    	$price_ttc_v = price2num($objv->price_ttc,2);
	    	$price_min_v = price2num($objv->price_min,2);
	    	$price_min_ttc_v = price2num($objv->price_min_ttc,2);
	    	//$tva_tx_v = $objv->tva_tx;
	    	
	    	if (price2num($price,2)!=$price_v || price2num($price_min,2)!=$price_min_v || price2num($price_ttc,2)!=$price_ttc_v || price2num($price_min_ttc,2)!=$price_min_ttc_v) {
	    		$sqlp = "INSERT INTO ".MAIN_DB_PREFIX."product_price
		                (entity, fk_product, price_level, fk_user_author, price, price_ttc, price_min, price_min_ttc, date_price, tva_tx)
		                VALUES (".((int )$entity).",
		                        ".((int) $prodid).",
		                        ".$level.",
		                        ".$user->id.",
		                        ".price2num($price,2).",
		                        ".price2num($price_ttc,2).",
		                        ".price2num($price_min,2).",
		                        ".price2num($price_min_ttc,2).",
		                        '".$now."',
		                        ".((float) $tva_tx).")
		                ON DUPLICATE KEY UPDATE
		                    price = VALUES(price),
		                    price_ttc = VALUES(price_ttc),
		                    price_min = VALUES(price_min),
		                    price_min_ttc = VALUES(price_min_ttc),
		                    date_price = VALUES(date_price),
		                    tva_tx = VALUES(tva_tx)";
		            $db->query($sqlp);
		    
		            $nb_line++ ;
		            //var_dump('$nb_line = '.$nb_line.'<br>');
	    	}
	    	//var_dump('$nb_line2 = '.$nb_line.'<br>');
	    }
	    //var_dump('$nb_line3 = '.$nb_line.'<br>');
	}
	}
	//var_dump('$nb_line4 = '.$nb_line.'<br>');
	
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
		$sql.= " FROM ".MAIN_DB_PREFIX."product";
		$sql.= " WHERE tosell = 1 ";
		$sql.= " AND entity IN (".getEntity('product').")";

		$resql = $db->query($sql);
		if ($resql === false) {
			dol_print_error($db);
			return;
		}

		while ($obj = $db->fetch_object($resql)) {
			$products[] = array('id'=>$obj->rowid, 'nature'=>$obj->finished, 'cost_price'=>$obj->cost_price);
		}
	}

	foreach ($products as $prod) {
		$prodid = is_array($prod) ? $prod['id'] : $prod;
		$natureid = is_array($prod) ? $prod['nature'] : 0;
		$cost = is_array($prod) ? $prod['cost_price'] : 0;
		$product = new Product($db);
		$product->fetch($prodid);

		$kitcomponents = dynamicsPricesGetKitComponents($db, $product);
		$kitcost = dynamicsPricesComputeKitCost($db, $kitcomponents);
		if ($kitcost !== null) {
			dynamicsPricesUpdateCostPrice($db, $user, $product, $kitcost);
			$cost = $kitcost;
		}

		$tva_tx = (float) $product->tva_tx;

		$sqlc = "SELECT code, pricelevel, minrate, targetrate";
		$sqlc.= " FROM ".MAIN_DB_PREFIX."c_coefprice";
		$sqlc.= " WHERE fk_nature = ".((int) $natureid);
		$sqlc.= " AND entity IN (".getEntity('entity').")";

		$resqlc = $db->query($sqlc);

		while ($objc = $db->fetch_object($resqlc)) {
			$level = (int) $objc->pricelevel;
			$minrate = (float) $objc->minrate;
			$targetrate = (float) $objc->targetrate;

			$price = $cost * (1 + $targetrate/100);
			$price_ttc = $price * (1 + $tva_tx/100);
			$price_min = $cost * (1 + $minrate/100);
			$price_min_ttc = $price_min * (1 + $tva_tx/100);

			$now = $db->idate(dol_now());

			$sqlv = "SELECT price_level, price, price_ttc, price_min, price_min_ttc, tva_tx ";
			$sqlv.= " FROM ".MAIN_DB_PREFIX."product_price";
			$sqlv.= " WHERE fk_product = ".((int) $prodid) ;
			$sqlv.= " AND price_level = ".$level;
			$sqlv.= " AND entity IN (".getEntity('productprice').")";
			$sqlv.= " ORDER BY date_price DESC LIMIT 1";

			$resqlv = $db->query($sqlv);

			while ($objv = $db->fetch_object($resqlv)) {
				$price_v = price2num($objv->price,2);
				$price_ttc_v = price2num($objv->price_ttc,2);
				$price_min_v = price2num($objv->price_min,2);
				$price_min_ttc_v = price2num($objv->price_min_ttc,2);

				if (price2num($price,2)!=$price_v || price2num($price_min,2)!=$price_min_v || price2num($price_ttc,2)!=$price_ttc_v || price2num($price_min_ttc,2)!=$price_min_ttc_v) {
					$sqlp = "INSERT INTO ".MAIN_DB_PREFIX."product_price";
					$sqlp.= " (entity, fk_product, price_level, fk_user_author, price, price_ttc, price_min, price_min_ttc, date_price, tva_tx)";
					$sqlp.= " VALUES (".$entity.",";
					$sqlp.= "".((int) $prodid).",";
					$sqlp.= "".$level.",";
					$sqlp.= "".$user->id.",";
					$sqlp.= "".price2num($price,2).",";
					$sqlp.= "".price2num($price_ttc,2).",";
					$sqlp.= "".price2num($price_min,2).",";
					$sqlp.= "".price2num($price_min_ttc,2).",";
					$sqlp.= "'".$now."',";
					$sqlp.= "".((float) $tva_tx).")";
					$sqlp.= " ON DUPLICATE KEY UPDATE";
					$sqlp.= " price = VALUES(price),";
					$sqlp.= " price_ttc = VALUES(price_ttc),";
					$sqlp.= " price_min = VALUES(price_min),";
					$sqlp.= " price_min_ttc = VALUES(price_min_ttc),";
					$sqlp.= " date_price = VALUES(date_price),";
					$sqlp.= " tva_tx = VALUES(tva_tx)";

					$db->query($sqlp);

					$nb_line++ ;
				}
			}
		}
	}

	return $nb_line;
}



/**
 * Display title
 * @param string $title
 */
function setup_print_title($title="Parameter", $width = 300)
{
	global $langs;
	print '<tr class="liste_titre">';
	print '<td td class="titlefield">'.$langs->trans($title) . '</td>';
	print '<td td class="titlefield" align="center" width="20">&nbsp;</td>';
	print '<td td class="titlefield" align="center">'.$langs->trans('Value').'</td>';
	print '</tr>';
}

/**
 * yes / no select
 * @param string $confkey
 * @param string $title
 * @param string $desc
 * @param $ajaxConstantOnOffInput will be send to ajax_constantonoff() input param
 *
 * exemple _print_on_off('CONSTNAME', 'ParamLabel' , 'ParamDesc');
 */
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
