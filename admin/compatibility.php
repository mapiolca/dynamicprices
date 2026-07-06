<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    dynamicsprices/admin/compatibility.php
 * \ingroup dynamicsprices
 * \brief   Compatibility page of DynamicsPrices module.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/dynamicsprices.lib.php';
require_once '../core/modules/modDynamicsPrices.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'dynamicsprices@dynamicsprices'));

if (empty($user->admin)) {
	accessforbidden();
}

$moduleDescriptor = new modDynamicsPrices($db);
$dolibarrVersion = defined('DOL_VERSION') ? (string) DOL_VERSION : '';
$isDolibarrSupported = $dolibarrVersion !== '' && version_compare($dolibarrVersion, '20.0.0', '>=');
$isPhpSupported = version_compare(PHP_VERSION, '8.0.0', '>=');

$features = array(
	array(
		'label' => 'DynamicPricesCompatibilityFeatureDynamicCostStorage',
		'min_dolibarr' => '20.0.0',
		'min_php' => '8.0.0',
		'available' => $isDolibarrSupported && $isPhpSupported,
		'reason' => 'DynamicPricesCompatibilityReasonAvailableV20',
	),
	array(
		'label' => 'DynamicPricesCompatibilityFeatureProductCard',
		'min_dolibarr' => '20.0.0',
		'min_php' => '8.0.0',
		'available' => $isDolibarrSupported && $isPhpSupported,
		'reason' => 'DynamicPricesCompatibilityReasonAvailableV20',
	),
	array(
		'label' => 'DynamicPricesCompatibilityFeatureSalesLines',
		'min_dolibarr' => '20.0.0',
		'min_php' => '8.0.0',
		'available' => $isDolibarrSupported && $isPhpSupported,
		'reason' => 'DynamicPricesCompatibilityReasonAvailableV20',
	),
	array(
		'label' => 'DynamicPricesCompatibilityFeatureMigration',
		'min_dolibarr' => '20.0.0',
		'min_php' => '8.0.0',
		'available' => $isDolibarrSupported && $isPhpSupported,
		'reason' => 'DynamicPricesCompatibilityReasonAvailableV20',
	),
	array(
		'label' => 'DynamicPricesCompatibilityFeatureApi',
		'min_dolibarr' => '20.0.0',
		'min_php' => '8.0.0',
		'available' => $isDolibarrSupported && $isPhpSupported,
		'reason' => 'DynamicPricesCompatibilityReasonAvailableV20',
	),
	array(
		'label' => 'DynamicPricesCompatibilityFeatureExport',
		'min_dolibarr' => '20.0.0',
		'min_php' => '8.0.0',
		'available' => $isDolibarrSupported && $isPhpSupported,
		'reason' => 'DynamicPricesCompatibilityReasonAvailableV20',
	),
	array(
		'label' => 'DynamicPricesCompatibilityFeatureLegacyNativeWrite',
		'min_dolibarr' => '20.0.0',
		'min_php' => '8.0.0',
		'available' => $isDolibarrSupported && $isPhpSupported,
		'reason' => 'DynamicPricesCompatibilityReasonLegacyDisabledDefault',
	),
);

$title = $langs->trans('DynamicPricesCompatibilityTitle');

llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=dynamicsprices">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

$head = dynamicspricesAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('DynamicsPricesSetup'), -1, 'dynamicsprices@dynamicsprices');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('DynamicPricesCompatibilityEnvironment').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DynamicPricesCompatibilityDetectedDolibarr').'</td><td>'.dol_escape_htmltag($dolibarrVersion).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DynamicPricesCompatibilityDetectedPhp').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DynamicPricesCompatibilityMinimumDolibarr').'</td><td>20.0.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DynamicPricesCompatibilityMinimumPhp').'</td><td>8.0.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DynamicPricesCompatibilityModuleVersion').'</td><td>'.dol_escape_htmltag((string) $moduleDescriptor->version).'</td></tr>';
print '</table>';

print '<br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('DynamicPricesCompatibilityFeature').'</td>';
print '<td>'.$langs->trans('DynamicPricesCompatibilityMinimumDolibarr').'</td>';
print '<td>'.$langs->trans('DynamicPricesCompatibilityMinimumPhp').'</td>';
print '<td>'.$langs->trans('DynamicPricesCompatibilityStatus').'</td>';
print '<td>'.$langs->trans('DynamicPricesCompatibilityReason').'</td>';
print '</tr>';

foreach ($features as $feature) {
	$statusKey = !empty($feature['available']) ? 'DynamicPricesCompatibilityAvailable' : 'DynamicPricesCompatibilityUnavailable';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['min_dolibarr']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['min_php']).'</td>';
	print '<td>'.$langs->trans($statusKey).'</td>';
	print '<td>'.$langs->trans($feature['reason']).'</td>';
	print '</tr>';
}

print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
