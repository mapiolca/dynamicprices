<?php
/* Copyright (C) 2026		Pierre Ardoin		<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('dynamicsprices@dynamicsprices', 'products'));

if (!isModEnabled('dynamicsprices')) {
	accessforbidden();
}
if (empty($user->admin) && !$user->hasRight('dynamicsprices', 'cost', 'history') && !$user->hasRight('dynamicsprices', 'cost', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$searchProduct = GETPOST('search_product', 'alphanohtml');
$searchContext = GETPOST('search_context', 'alphanohtml');
$searchStatus = GETPOST('search_status', 'int');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = strtoupper(GETPOST('sortorder', 'alpha'));
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit');

if ($page < 0) {
	$page = 0;
}
if ($limit <= 0) {
	$limit = empty($conf->liste_limit) ? 20 : (int) $conf->liste_limit;
}
$offset = $limit * $page;

$allowedSortFields = array('l.date_creation', 'p.ref', 'l.old_dynamic_cost_price', 'l.new_dynamic_cost_price', 'l.source_type', 'l.rule_code', 'l.calculation_context');
if (!in_array($sortfield, $allowedSortFields, true)) {
	$sortfield = 'l.date_creation';
}
if (!in_array($sortorder, array('ASC', 'DESC'), true)) {
	$sortorder = 'DESC';
}

$param = '';
if ($id > 0) {
	$param .= '&id='.$id;
}
if ($searchProduct !== '') {
	$param .= '&search_product='.urlencode($searchProduct);
}
if ($searchContext !== '') {
	$param .= '&search_context='.urlencode($searchContext);
}
if ($searchStatus !== '') {
	$param .= '&search_status='.urlencode($searchStatus);
}

$where = array();
$where[] = 'l.entity = '.((int) $conf->entity);
if ($id > 0) {
	$where[] = 'l.fk_product = '.$id;
}
if ($searchProduct !== '') {
	$where[] = "(p.ref LIKE '%".$db->escape($searchProduct)."%' OR p.label LIKE '%".$db->escape($searchProduct)."%')";
}
if ($searchContext !== '') {
	$where[] = "l.calculation_context LIKE '%".$db->escape($searchContext)."%'";
}
if ($searchStatus !== '') {
	$where[] = "l.new_dynamic_cost_price ".(((int) $searchStatus) < 0 ? 'IS NULL' : 'IS NOT NULL');
}

$sqlFrom = " FROM ".MAIN_DB_PREFIX."dynamicprices_product_cost_log AS l";
$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = l.fk_product";
$sqlWhere = " WHERE ".implode(' AND ', $where);

$sqlCount = "SELECT COUNT(l.rowid) as nb".$sqlFrom.$sqlWhere;
$resqlCount = $db->query($sqlCount);
$nbtotalofrecords = 0;
if ($resqlCount) {
	$objCount = $db->fetch_object($resqlCount);
	$nbtotalofrecords = is_object($objCount) ? (int) $objCount->nb : 0;
}

$sql = "SELECT l.rowid, l.entity, l.fk_product, l.old_dynamic_cost_price, l.new_dynamic_cost_price, l.dolibarr_cost_price_snapshot, l.pmp_snapshot, l.source_type, l.source_value, l.rule_code, l.coefficient, l.calculation_context, l.diff_abs, l.diff_percent, l.date_creation, l.fk_user_author, p.ref as product_ref, p.label as product_label";
$sql .= $sqlFrom.$sqlWhere;
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

llxHeader('', $langs->trans('DynamicPricesCostHistory'), '', '', 0, 0, '', '', '', 'mod-dynamicsprices page-product-cost-history');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=dynamicsprices">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('DynamicPricesCostHistory'), $linkback, 'margin');

if ($id > 0) {
	$product = new Product($db);
	if ($product->fetch($id) > 0) {
		print '<div class="refidno">'.$product->getNomUrl(1).' - '.dol_escape_htmltag($product->label).'</div>';
		print '<br>';
	}
}

print_barre_liste($langs->trans('DynamicPricesCostHistory'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'generic', 0, '', '', $limit);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
if ($id > 0) {
	print '<input type="hidden" name="id" value="'.$id.'">';
}
print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print_liste_field_titre('Product', $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Date', $_SERVER['PHP_SELF'], 'l.date_creation', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('DynamicPricesCostOldValue', $_SERVER['PHP_SELF'], 'l.old_dynamic_cost_price', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('DynamicPricesCostNewValue', $_SERVER['PHP_SELF'], 'l.new_dynamic_cost_price', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('DynamicPricesCostSource', $_SERVER['PHP_SELF'], 'l.source_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('DynamicPricesCostRule', $_SERVER['PHP_SELF'], 'l.rule_code', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('DynamicPricesCostContext', $_SERVER['PHP_SELF'], 'l.calculation_context', '', $param, '', $sortfield, $sortorder);
print '<td class="right">'.$langs->trans('DynamicPricesCostDiff').'</td>';
print '</tr>';

print '<tr class="liste_titre_filter">';
print '<td><input type="text" class="flat width100" name="search_product" value="'.dol_escape_htmltag($searchProduct).'"></td>';
print '<td></td>';
print '<td></td>';
print '<td></td>';
print '<td></td>';
print '<td></td>';
print '<td><input type="text" class="flat width100" name="search_context" value="'.dol_escape_htmltag($searchContext).'"></td>';
print '<td class="right">';
print '<input type="submit" class="button small" value="'.$langs->trans('Search').'">';
print '</td>';
print '</tr>';

$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	if (!is_object($obj)) {
		break;
	}

	$productLabel = dol_escape_htmltag((string) $obj->product_ref);
	if (!empty($obj->product_label)) {
		$productLabel .= ' - '.dol_escape_htmltag((string) $obj->product_label);
	}
	$productUrl = dol_buildpath('/product/card.php', 1).'?id='.(int) $obj->fk_product;

	print '<tr class="oddeven">';
	print '<td><a href="'.$productUrl.'">'.$productLabel.'</a></td>';
	print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
	print '<td class="right">'.($obj->old_dynamic_cost_price === null ? '' : price($obj->old_dynamic_cost_price)).'</td>';
	print '<td class="right">'.($obj->new_dynamic_cost_price === null ? '' : price($obj->new_dynamic_cost_price)).'</td>';
	print '<td>'.dol_escape_htmltag((string) $obj->source_type).'</td>';
	print '<td>'.dol_escape_htmltag((string) $obj->rule_code).'</td>';
	print '<td>'.dol_escape_htmltag((string) $obj->calculation_context).'</td>';
	print '<td class="right">'.($obj->diff_abs === null ? '' : price($obj->diff_abs)).'</td>';
	print '</tr>';
	$i++;
}

if ($num === 0) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
