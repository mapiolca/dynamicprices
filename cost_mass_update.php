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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/class/dynamicpricescostservice.class.php';

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
if (empty($user->admin) && !$user->hasRight('dynamicsprices', 'cost', 'massupdate')) {
	accessforbidden();
}

/**
 * Build mass update filters from request.
 *
 * @return array<string,mixed>
 */
function dynamicspricesCostMassReadFilters()
{
	return array(
		'search_product' => GETPOST('search_product', 'alphanohtml'),
		'filter_type' => GETPOST('filter_type', 'int'),
		'filter_tosell' => GETPOST('filter_tosell', 'int'),
		'filter_tobuy' => GETPOST('filter_tobuy', 'int'),
		'filter_missing_cost' => GETPOST('filter_missing_cost', 'int'),
		'filter_stale_days' => GETPOSTINT('filter_stale_days'),
		'limit' => GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : 100,
	);
}

/**
 * Return SQL where conditions for mass update.
 *
 * @param DoliDB $db Database handler
 * @param array<string,mixed> $filters Filters
 * @return array<int,string>
 */
function dynamicspricesCostMassBuildWhere($db, array $filters)
{
	$where = array();
	$where[] = 'p.entity IN ('.getEntity('product').')';
	if ($filters['search_product'] !== '') {
		$where[] = "(p.ref LIKE '%".$db->escape((string) $filters['search_product'])."%' OR p.label LIKE '%".$db->escape((string) $filters['search_product'])."%')";
	}
	if ($filters['filter_type'] !== '') {
		$where[] = 'p.fk_product_type = '.((int) $filters['filter_type']);
	}
	if ($filters['filter_tosell'] !== '') {
		$where[] = 'p.tosell = '.((int) $filters['filter_tosell']);
	}
	if ($filters['filter_tobuy'] !== '') {
		$where[] = 'p.tobuy = '.((int) $filters['filter_tobuy']);
	}
	if (!empty($filters['filter_missing_cost'])) {
		$where[] = 'c.rowid IS NULL';
	}
	if (!empty($filters['filter_stale_days'])) {
		$where[] = "(c.date_calculation IS NULL OR c.date_calculation < '".$db->escape(date('Y-m-d H:i:s', dol_now() - (((int) $filters['filter_stale_days']) * 86400)))."')";
	}

	return $where;
}

/**
 * Fetch products for preview or confirmation.
 *
 * @param DoliDB $db Database handler
 * @param array<string,mixed> $filters Filters
 * @return array<int,stdClass>
 */
function dynamicspricesCostMassFetchProducts($db, array $filters)
{
	$sql = "SELECT p.rowid, p.ref, p.label, p.cost_price, p.pmp, p.fk_product_type, c.dynamic_cost_price as old_dynamic_cost_price";
	$sql .= " FROM ".MAIN_DB_PREFIX."product AS p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."dynamicprices_product_cost AS c ON c.fk_product = p.rowid AND c.entity = ".((int) $GLOBALS['conf']->entity);
	$sql .= " WHERE ".implode(' AND ', dynamicspricesCostMassBuildWhere($db, $filters));
	$sql .= " ORDER BY p.ref ASC";
	$sql .= $db->plimit((int) $filters['limit'], 0);

	$resql = $db->query($sql);
	if (!$resql) {
		return array();
	}

	$products = array();
	while (is_object($obj = $db->fetch_object($resql))) {
		$products[] = $obj;
	}

	return $products;
}

/**
 * Build calculation preview rows.
 *
 * @param DynamicPricesCostService $service Cost service
 * @param array<int,stdClass> $products Products
 * @return array<int,array<string,mixed>>
 */
function dynamicspricesCostMassBuildPreview(DynamicPricesCostService $service, array $products)
{
	$rows = array();
	foreach ($products as $product) {
		$calculation = $service->calculateProductCost((int) $product->rowid, array('calculation_context' => 'mass_preview'));
		$newCost = $calculation['dynamic_cost_price'];
		$oldCost = $product->old_dynamic_cost_price !== null ? (float) $product->old_dynamic_cost_price : null;
		$diff = ($newCost !== null && $oldCost !== null) ? ((float) $newCost - $oldCost) : null;
		$rows[] = array(
			'product' => $product,
			'calculation' => $calculation,
			'old_cost' => $oldCost,
			'new_cost' => $newCost,
			'diff' => $diff,
		);
	}

	return $rows;
}

/**
 * Output preview as CSV.
 *
 * @param array<int,array<string,mixed>> $rows Preview rows
 * @return void
 */
function dynamicspricesCostMassOutputCsv(array $rows)
{
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="dynamicprices-cost-preview.csv"');
	$out = fopen('php://output', 'w');
	if ($out === false) {
		return;
	}
	fputcsv($out, array('product_id', 'product_ref', 'product_label', 'dolibarr_cost_price', 'pmp', 'old_dynamic_cost_price', 'new_dynamic_cost_price', 'diff', 'source_type', 'rule_code', 'status'));
	foreach ($rows as $row) {
		$product = $row['product'];
		$calculation = $row['calculation'];
		fputcsv($out, array(
			$product->rowid,
			$product->ref,
			$product->label,
			$product->cost_price,
			$product->pmp,
			$row['old_cost'],
			$row['new_cost'],
			$row['diff'],
			$calculation['source_type'],
			$calculation['rule_code'],
			$calculation['calculation_status'],
		));
	}
	fclose($out);
}

$action = GETPOST('action', 'aZ09');
$filters = dynamicspricesCostMassReadFilters();
$service = new DynamicPricesCostService($db);
$previewRows = array();

if ($action === 'export_csv') {
	$previewRows = dynamicspricesCostMassBuildPreview($service, dynamicspricesCostMassFetchProducts($db, $filters));
	dynamicspricesCostMassOutputCsv($previewRows);
	exit;
}

if ($action === 'confirm') {
	if (GETPOST('token', 'alphanohtml') === '') {
		accessforbidden($langs->trans('ErrorBadToken'));
	}
	$products = dynamicspricesCostMassFetchProducts($db, $filters);
	$created = 0;
	$errors = 0;
	$db->begin();
	foreach ($products as $product) {
		$calculation = $service->calculateProductCost((int) $product->rowid, array('entity' => (int) $conf->entity, 'calculation_context' => 'mass'));
		$result = $service->saveProductCost((int) $product->rowid, $calculation, $user, array('entity' => (int) $conf->entity, 'calculation_context' => 'mass'));
		if ($result < 0) {
			$errors++;
			break;
		}
		$created++;
	}
	if ($errors > 0) {
		$db->rollback();
		setEventMessages($service->error, $service->errors, 'errors');
	} else {
		$db->commit();
		setEventMessages($langs->trans('DynamicPricesCostMassUpdated', $created), null, 'mesgs');
	}
}

if (in_array($action, array('preview', 'confirm'), true)) {
	$previewRows = dynamicspricesCostMassBuildPreview($service, dynamicspricesCostMassFetchProducts($db, $filters));
}

$form = new Form($db);

llxHeader('', $langs->trans('DynamicPricesCostMassUpdate'), '', '', 0, 0, '', '', '', 'mod-dynamicsprices page-cost-mass-update');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=dynamicsprices">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('DynamicPricesCostMassUpdate'), $linkback, 'margin');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="4">'.$langs->trans('DynamicPricesCostMassFilters').'</td></tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('Product').'</td>';
print '<td><input type="text" class="flat minwidth300" name="search_product" value="'.dol_escape_htmltag((string) $filters['search_product']).'"></td>';
print '<td>'.$langs->trans('Type').'</td>';
print '<td>'.$form->selectarray('filter_type', array('' => '', '0' => $langs->trans('Product'), '1' => $langs->trans('Service')), $filters['filter_type'], 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('Sell').'</td>';
print '<td>'.$form->selectarray('filter_tosell', array('' => '', '0' => $langs->trans('No'), '1' => $langs->trans('Yes')), $filters['filter_tosell'], 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
print '<td>'.$langs->trans('Buy').'</td>';
print '<td>'.$form->selectarray('filter_tobuy', array('' => '', '0' => $langs->trans('No'), '1' => $langs->trans('Yes')), $filters['filter_tobuy'], 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DynamicPricesCostMissingOnly').'</td>';
print '<td>'.$form->selectarray('filter_missing_cost', array('' => '', '0' => $langs->trans('No'), '1' => $langs->trans('Yes')), $filters['filter_missing_cost'], 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
print '<td>'.$langs->trans('DynamicPricesCostStaleDays').'</td>';
print '<td><input type="text" class="flat maxwidth75 right" name="filter_stale_days" value="'.dol_escape_htmltag((string) $filters['filter_stale_days']).'"></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('Limit').'</td>';
print '<td><input type="text" class="flat maxwidth75 right" name="limit" value="'.((int) $filters['limit']).'"></td>';
print '<td colspan="2" class="right">';
print '<button class="button" type="submit" name="action" value="preview">'.$langs->trans('DynamicPricesCostPreview').'</button>';
print ' <button class="button" type="submit" name="action" value="export_csv">'.$langs->trans('Export').'</button>';
print ' <button class="button button-save" type="submit" name="action" value="confirm">'.$langs->trans('DynamicPricesCostConfirmMassUpdate').'</button>';
print '</td>';
print '</tr>';
print '</table>';
print '</form>';

print '<br>';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Product').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesNativeCostPrice').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesPmp').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesCostOldValue').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesCostNewValue').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesCostDiff').'</td>';
print '<td>'.$langs->trans('DynamicPricesCostSource').'</td>';
print '<td>'.$langs->trans('DynamicPricesCostRule').'</td>';
print '<td>'.$langs->trans('DynamicPricesCostStatus').'</td>';
print '</tr>';

if (empty($previewRows)) {
	print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	foreach ($previewRows as $row) {
		$product = $row['product'];
		$calculation = $row['calculation'];
		$productUrl = dol_buildpath('/product/card.php', 1).'?id='.(int) $product->rowid;
		print '<tr class="oddeven">';
		print '<td><a href="'.$productUrl.'">'.dol_escape_htmltag($product->ref).' - '.dol_escape_htmltag($product->label).'</a></td>';
		print '<td class="right">'.($product->cost_price === null ? '' : price($product->cost_price)).'</td>';
		print '<td class="right">'.($product->pmp === null ? '' : price($product->pmp)).'</td>';
		print '<td class="right">'.($row['old_cost'] === null ? '' : price($row['old_cost'])).'</td>';
		print '<td class="right">'.($row['new_cost'] === null ? '' : price($row['new_cost'])).'</td>';
		print '<td class="right">'.($row['diff'] === null ? '' : price($row['diff'])).'</td>';
		print '<td>'.dol_escape_htmltag((string) $calculation['source_type']).'</td>';
		print '<td>'.dol_escape_htmltag((string) $calculation['rule_code']).'</td>';
		print '<td>'.$langs->trans((string) $calculation['calculation_message']).'</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
