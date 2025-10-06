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


function update_customer_prices_from_suppliers($db, $user, $langs, $conf, $productid = 0)
{
    dol_include_once('/product/class/product.class.php');

    $entity = (int) $conf->entity;
    $products = array();
    $updatedLines = 0;

    $sql = "SELECT rowid, finished";
    $sql .= " FROM ".MAIN_DB_PREFIX."product";
    $sql .= " WHERE tosell = 1";
    $sql .= " AND entity IN (".getEntity('product').")";

    if ($productid > 0) {
        $sql .= " AND rowid = ".((int) $productid);
    }

    $resql = $db->query($sql);
    if ($resql === false) {
        dol_print_error($db);
        return 0;
    }

    while ($obj = $db->fetch_object($resql)) {
        $products[] = array('id' => (int) $obj->rowid, 'nature' => (int) $obj->finished);
    }
    $db->free($resql);

    if (empty($products)) {
        return 0;
    }

    $product = new Product($db);

    foreach ($products as $prod) {
        $productId = (int) $prod['id'];
        $natureId = (int) $prod['nature'];

        if ($product->fetch($productId) <= 0) {
            continue;
        }

        $sqlf = "SELECT AVG(price) AS avg_price FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
        $sqlf .= " WHERE fk_product = ".$productId;
        $sqlf .= " AND entity IN (".getEntity('product_fournisseur_price').")";

        $resqlf = $db->query($sqlf);
        if ($resqlf === false) {
            dol_print_error($db);
            continue;
        }

        $objf = $db->fetch_object($resqlf);
        $db->free($resqlf);

        if (!$objf || $objf->avg_price === null) {
            continue;
        }

        $basePrice = (float) $objf->avg_price;
        if ($basePrice <= 0) {
            continue;
        }

        $updatedLines += dynamicsprices_apply_coefficients(
            $db,
            $user,
            $entity,
            $productId,
            $natureId,
            (float) $product->tva_tx,
            $basePrice
        );
    }

    return $updatedLines;
}

function update_customer_prices_from_cost_price($db, $user, $langs, $conf, $productid = 0)
{
    dol_include_once('/product/class/product.class.php');

    $entity = (int) $conf->entity;
    $products = array();
    $updatedLines = 0;

    $sql = "SELECT rowid, finished, cost_price";
    $sql .= " FROM ".MAIN_DB_PREFIX."product";
    $sql .= " WHERE tosell = 1";
    $sql .= " AND entity IN (".getEntity('product').")";

    if ($productid > 0) {
        $sql .= " AND rowid = ".((int) $productid);
    }

    $resql = $db->query($sql);
    if ($resql === false) {
        dol_print_error($db);
        return 0;
    }

    while ($obj = $db->fetch_object($resql)) {
        $products[] = array(
            'id' => (int) $obj->rowid,
            'nature' => (int) $obj->finished,
            'cost_price' => (float) $obj->cost_price
        );
    }
    $db->free($resql);

    if (empty($products)) {
        return 0;
    }

    $product = new Product($db);

    foreach ($products as $prod) {
        $productId = (int) $prod['id'];
        $natureId = (int) $prod['nature'];
        $costPrice = (float) $prod['cost_price'];

        if ($costPrice <= 0) {
            continue;
        }

        if ($product->fetch($productId) <= 0) {
            continue;
        }

        $updatedLines += dynamicsprices_apply_coefficients(
            $db,
            $user,
            $entity,
            $productId,
            $natureId,
            (float) $product->tva_tx,
            $costPrice
        );
    }

    return $updatedLines;
}

/**
 * Apply configured coefficients to compute and persist product prices.
 */
function dynamicsprices_apply_coefficients($db, $user, $entity, $productId, $natureId, $tvaTx, $basePrice)
{
    $coefficients = dynamicsprices_get_coefficients($db, $natureId);
    if (empty($coefficients)) {
        return 0;
    }

    $now = $db->idate(dol_now());
    $updatedLines = 0;

    foreach ($coefficients as $coefficient) {
        $level = (int) $coefficient['pricelevel'];
        $minRate = (float) $coefficient['minrate'];
        $targetRate = (float) $coefficient['targetrate'];

        $price = $basePrice * (1 + $targetRate / 100);
        $priceTtc = $price * (1 + $tvaTx / 100);
        $priceMin = $basePrice * (1 + $minRate / 100);
        $priceMinTtc = $priceMin * (1 + $tvaTx / 100);

        $sqlv = "SELECT price, price_ttc, price_min, price_min_ttc";
        $sqlv .= " FROM ".MAIN_DB_PREFIX."product_price";
        $sqlv .= " WHERE fk_product = ".$productId;
        $sqlv .= " AND price_level = ".$level;
        $sqlv .= " AND entity IN (".getEntity('productprice').")";
        $sqlv .= " ORDER BY date_price DESC LIMIT 1";

        $resqlv = $db->query($sqlv);
        if ($resqlv === false) {
            dol_print_error($db);
            continue;
        }

        $objv = $db->fetch_object($resqlv);
        $db->free($resqlv);

        $needsUpdate = false;

        if ($objv) {
            $needsUpdate = (
                price2num($price, 2) != price2num($objv->price, 2) ||
                price2num($priceTtc, 2) != price2num($objv->price_ttc, 2) ||
                price2num($priceMin, 2) != price2num($objv->price_min, 2) ||
                price2num($priceMinTtc, 2) != price2num($objv->price_min_ttc, 2)
            );
        } else {
            $needsUpdate = true;
        }

        if (!$needsUpdate) {
            continue;
        }

        $sqlp = "INSERT INTO ".MAIN_DB_PREFIX."product_price";
        $sqlp .= " (entity, fk_product, price_level, fk_user_author, price, price_ttc, price_min, price_min_ttc, date_price, tva_tx)";
        $sqlp .= sprintf(
            " VALUES (%d,%d,%d,%d,%s,%s,%s,%s,'%s',%s)",
            $entity,
            $productId,
            $level,
            $user->id,
            price2num($price, 2),
            price2num($priceTtc, 2),
            price2num($priceMin, 2),
            price2num($priceMinTtc, 2),
            $now,
            (float) $tvaTx
        );
        $sqlp .= " ON DUPLICATE KEY UPDATE";
        $sqlp .= " price = VALUES(price),";
        $sqlp .= " price_ttc = VALUES(price_ttc),";
        $sqlp .= " price_min = VALUES(price_min),";
        $sqlp .= " price_min_ttc = VALUES(price_min_ttc),";
        $sqlp .= " date_price = VALUES(date_price),";
        $sqlp .= " tva_tx = VALUES(tva_tx)";

        if ($db->query($sqlp)) {
            $updatedLines++;
        } else {
            dol_print_error($db);
        }
    }

    return $updatedLines;
}

/**
 * Retrieve coefficients for a nature id with a simple runtime cache.
 */
function dynamicsprices_get_coefficients($db, $natureId)
{
    static $cache = array();

    if (isset($cache[$natureId])) {
        return $cache[$natureId];
    }

    $sql = "SELECT pricelevel, minrate, targetrate";
    $sql .= " FROM ".MAIN_DB_PREFIX."c_coefprice";
    $sql .= " WHERE fk_nature = ".((int) $natureId);
    $sql .= " AND entity IN (".getEntity('entity').")";

    $resql = $db->query($sql);
    if ($resql === false) {
        dol_print_error($db);
        $cache[$natureId] = array();
        return $cache[$natureId];
    }

    $coefficients = array();
    while ($row = $db->fetch_object($resql)) {
        $coefficients[] = array(
            'pricelevel' => (int) $row->pricelevel,
            'minrate' => (float) $row->minrate,
            'targetrate' => (float) $row->targetrate,
        );
    }
    $db->free($resql);

    $cache[$natureId] = $coefficients;

    return $coefficients;
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
