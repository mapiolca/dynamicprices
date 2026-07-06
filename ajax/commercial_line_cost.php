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

/**
 * AJAX endpoint returning the DynamicPrices cost for commercial line forms.
 */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

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
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	http_response_code(500);
	header('Content-Type: application/json; charset=UTF-8');
	print json_encode(array('success' => false, 'error' => 'Include of main fails'));
	exit;
}

require_once __DIR__.'/../class/dynamicpricescostservice.class.php';

/**
 * Send a JSON response and stop script execution.
 *
 * @param array<string,mixed> $payload Payload
 * @param int $statusCode HTTP status code
 * @return void
 */
function dynamicsprices_ajax_response(array $payload, $statusCode = 200)
{
	http_response_code((int) $statusCode);
	header('Content-Type: application/json; charset=UTF-8');
	print json_encode($payload);
	exit;
}

global $conf, $db, $langs, $user;

$langs->load('dynamicsprices@dynamicsprices');

if (!isModEnabled('dynamicsprices')) {
	dynamicsprices_ajax_response(array('success' => false, 'error' => 'ModuleDisabled'), 403);
}
if (!getDolGlobalInt('DYNAMICPRICES_COST_USE_FOR_SALES', 0)) {
	dynamicsprices_ajax_response(array('success' => true, 'available' => false));
}
if (empty($user->admin) && !$user->hasRight('dynamicsprices', 'cost', 'read')) {
	dynamicsprices_ajax_response(array('success' => false, 'error' => 'Forbidden'), 403);
}

$productId = GETPOSTINT('product_id');
if ($productId <= 0) {
	dynamicsprices_ajax_response(array('success' => true, 'available' => false));
}

$service = new DynamicPricesCostService($db);
$cost = $service->getDynamicCostPrice($productId, (int) $conf->entity);
if ($cost === null) {
	dynamicsprices_ajax_response(array('success' => true, 'available' => false));
}

$costForInput = price2num($cost, 'MU');
dynamicsprices_ajax_response(array(
	'success' => true,
	'available' => true,
	'product_id' => $productId,
	'price' => $costForInput,
	'label' => $langs->trans('DynamicPricesCostCommercialLineOption', price($cost)),
));
