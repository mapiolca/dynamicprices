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
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/dynamicsprices.lib.php';
require_once __DIR__.'/../class/dynamicpricescostservice.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'dynamicsprices@dynamicsprices', 'products'));

if (!isModEnabled('dynamicsprices')) {
	accessforbidden();
}
if (empty($user->admin)) {
	accessforbidden();
}

/**
 * Build migration calculation.
 *
 * @param DynamicPricesCostService $service Cost service
 * @param stdClass $product Product row
 * @param string $mode Migration mode
 * @return array<string,mixed>
 */
function dynamicspricesBuildMigrationCalculation(DynamicPricesCostService $service, $product, $mode)
{
	$nativeCost = $product->cost_price !== null ? (float) $product->cost_price : null;
	if ($mode === 'calculate' || $mode === 'mixed') {
		$calculation = $service->calculateProductCost((int) $product->rowid, array('calculation_context' => 'migration_preview'));
		if ($mode === 'calculate' || $calculation['dynamic_cost_price'] !== null) {
			return $calculation;
		}
	}

	if ($mode === 'none') {
		return array(
			'entity' => (int) $GLOBALS['conf']->entity,
			'fk_product' => (int) $product->rowid,
			'dynamic_cost_price' => null,
			'source_type' => '',
			'source_value' => null,
			'rule_code' => '',
			'coefficient' => null,
			'rounding_rule' => 'dolibarr',
			'calculation_status' => 0,
			'calculation_message' => 'DynamicPricesMigrationNoAction',
		);
	}

	$hashPayload = json_encode(array('product' => (int) $product->rowid, 'source' => 'cost_price', 'value' => $nativeCost));
	return array(
		'entity' => (int) $GLOBALS['conf']->entity,
		'fk_product' => (int) $product->rowid,
		'dynamic_cost_price' => $nativeCost,
		'price_base_type' => 'HT',
		'source_type' => 'cost_price',
		'source_value' => $nativeCost,
		'source_details' => '',
		'rule_code' => '',
		'coefficient' => 1,
		'rounding_rule' => 'dolibarr',
		'calculation_hash' => hash('sha256', is_string($hashPayload) ? $hashPayload : ''),
		'calculation_status' => $nativeCost === null ? 0 : 1,
		'calculation_message' => $nativeCost === null ? 'DynamicPricesCostNoSource' : 'DynamicPricesCostCalculated',
		'status' => 1,
	);
}

/**
 * Fetch products for migration.
 *
 * @param DoliDB $db Database handler
 * @param int $limit Limit
 * @return array<int,stdClass>
 */
function dynamicspricesFetchMigrationProducts($db, $limit)
{
	$sql = "SELECT p.rowid, p.ref, p.label, p.cost_price, p.pmp, c.dynamic_cost_price as existing_dynamic_cost_price";
	$sql .= " FROM ".MAIN_DB_PREFIX."product AS p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."dynamicprices_product_cost AS c ON c.fk_product = p.rowid AND c.entity = ".((int) $GLOBALS['conf']->entity);
	$sql .= " WHERE p.entity IN (".getEntity('product').")";
	$sql .= " ORDER BY p.ref ASC";
	$sql .= $db->plimit($limit, 0);

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

$action = GETPOST('action', 'aZ09');
$mode = GETPOST('migration_mode', 'alpha');
if (!in_array($mode, array('cost_price', 'calculate', 'mixed', 'none'), true)) {
	$mode = 'mixed';
}
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
	$limit = 100;
}

$service = new DynamicPricesCostService($db);
$products = dynamicspricesFetchMigrationProducts($db, $limit);
$rows = array();
foreach ($products as $product) {
	$rows[] = array(
		'product' => $product,
		'calculation' => dynamicspricesBuildMigrationCalculation($service, $product, $mode),
	);
}

if ($action === 'confirm') {
	if (GETPOST('token', 'alphanohtml') === '') {
		accessforbidden($langs->trans('ErrorBadToken'));
	}
	$created = 0;
	$updated = 0;
	$ignored = 0;
	$errors = 0;

	$db->begin();
	foreach ($rows as $row) {
		$product = $row['product'];
		$calculation = $row['calculation'];
		if ($mode === 'none' || $calculation['dynamic_cost_price'] === null) {
			$ignored++;
			continue;
		}
		$oldRecord = $service->getDynamicCostRecord((int) $product->rowid, (int) $conf->entity);
		$result = $service->saveProductCost((int) $product->rowid, $calculation, $user, array('entity' => (int) $conf->entity, 'calculation_context' => 'migration'));
		if ($result < 0) {
			$errors++;
			break;
		}
		if (is_object($oldRecord)) {
			$updated++;
		} else {
			$created++;
		}
	}

	if ($errors > 0) {
		$db->rollback();
		setEventMessages($service->error, $service->errors, 'errors');
	} else {
		$db->commit();
		setEventMessages($langs->trans('DynamicPricesMigrationResult', $created, $updated, $ignored), null, 'mesgs');
	}
}

$form = new Form($db);

llxHeader('', $langs->trans('DynamicPricesMigrationTitle'), '', '', 0, 0, '', '', '', 'mod-dynamicsprices page-admin page-dynamic-cost-migration');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=dynamicsprices">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('DynamicPricesMigrationTitle'), $linkback, 'margin');

$head = dynamicspricesAdminPrepareHead();
print dol_get_fiche_head($head, 'migration', $langs->trans('DynamicsPricesSetup'), -1, 'dynamicsprices@dynamicsprices');

print '<p class="opacitymedium">'.$langs->trans('DynamicPricesMigrationIntro').'</p>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('DynamicPricesMigrationOptions').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DynamicPricesMigrationMode').'</td><td>';
print $form->selectarray('migration_mode', array(
	'cost_price' => $langs->trans('DynamicPricesMigrationModeCostPrice'),
	'calculate' => $langs->trans('DynamicPricesMigrationModeCalculate'),
	'mixed' => $langs->trans('DynamicPricesMigrationModeMixed'),
	'none' => $langs->trans('DynamicPricesMigrationModeNone'),
), $mode, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Limit').'</td><td><input type="text" class="flat maxwidth75 right" name="limit" value="'.$limit.'"></td></tr>';
print '<tr class="oddeven"><td colspan="2" class="right">';
print '<button class="button" type="submit" name="action" value="preview">'.$langs->trans('Preview').'</button>';
print ' <button class="button button-save" type="submit" name="action" value="confirm">'.$langs->trans('Confirm').'</button>';
print '</td></tr>';
print '</table>';
print '</form>';

print '<br>';
print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Product').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesNativeCostPrice').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesPmp').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesDynamicCostPrice').'</td>';
print '<td class="right">'.$langs->trans('DynamicPricesMigrationProposedCost').'</td>';
print '<td>'.$langs->trans('DynamicPricesCostSource').'</td>';
print '<td>'.$langs->trans('DynamicPricesMigrationAction').'</td>';
print '</tr>';

if (empty($rows)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	foreach ($rows as $row) {
		$product = $row['product'];
		$calculation = $row['calculation'];
		$actionLabel = $langs->trans('DynamicPricesMigrationActionIgnore');
		if ($mode !== 'none' && $calculation['dynamic_cost_price'] !== null) {
			$actionLabel = $product->existing_dynamic_cost_price === null ? $langs->trans('DynamicPricesMigrationActionCreate') : $langs->trans('DynamicPricesMigrationActionUpdate');
		}
		$productUrl = dol_buildpath('/product/card.php', 1).'?id='.(int) $product->rowid;
		print '<tr class="oddeven">';
		print '<td><a href="'.$productUrl.'">'.dol_escape_htmltag($product->ref).' - '.dol_escape_htmltag($product->label).'</a></td>';
		print '<td class="right">'.($product->cost_price === null ? '' : price($product->cost_price)).'</td>';
		print '<td class="right">'.($product->pmp === null ? '' : price($product->pmp)).'</td>';
		print '<td class="right">'.($product->existing_dynamic_cost_price === null ? '' : price($product->existing_dynamic_cost_price)).'</td>';
		print '<td class="right">'.($calculation['dynamic_cost_price'] === null ? '' : price($calculation['dynamic_cost_price'])).'</td>';
		print '<td>'.dol_escape_htmltag((string) $calculation['source_type']).'</td>';
		print '<td>'.dol_escape_htmltag($actionLabel).'</td>';
		print '</tr>';
	}
}
print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
