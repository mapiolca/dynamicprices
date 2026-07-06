<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
* Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
* Copyright (C) 2025		Pierre ARDOIN
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
* \file    dynamicsprices/admin/setup.php
* \ingroup dynamicsprices
* \brief   DynamicsPrices setup page.
*/

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/dynamicsprices.lib.php';
//require_once "../class/myclass.class.php";
require_once __DIR__.'/../core/modules/modDynamicsPrices.class.php';
require_once __DIR__.'/../class/dynamicpricescostservice.class.php';

/**
* @var Conf $conf
* @var DoliDB $db
* @var HookManager $hookmanager
* @var Translate $langs
* @var User $user
*/

// Translations
$langs->loadLangs(array("admin", "dynamicsprices@dynamicsprices"));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
/** @var HookManager $hookmanager */
$hookmanager->initHooks(array('dynamicspricessetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$error = 0;
$setupnotempty = 1;

// Access control
if (!$user->admin) {
	accessforbidden();
}

if (preg_match('/^set_(DYNAMICPRICES_COST_[A-Z0-9_]+)$/', $action, $matches)) {
	$constName = $matches[1];
	$allowedConstants = array(
		'DYNAMICPRICES_COST_LINE_STRATEGY',
		'DYNAMICPRICES_COST_FALLBACK',
		'DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY',
		'DYNAMICPRICES_COST_ROUNDING_MODE',
		'DYNAMICPRICES_COST_LOG_MODE',
	);
	if (!in_array($constName, $allowedConstants, true)) {
		accessforbidden();
	}
	if (GETPOST('token', 'alphanohtml') === '') {
		accessforbidden($langs->trans('ErrorBadToken'));
	}
	if ($constName === 'DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY') {
		$constValue = dynamicspricesGetPostedLineSourcePriority();
	} else {
		$constValue = GETPOST($constName, 'alphanohtml');
	}
	$result = dolibarr_set_const($db, $constName, $constValue, 'chaine', 0, '', (int) $conf->entity);
	if ($result < 0) {
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

// Actions on module constants
include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

// Load dictionary definitions
$module = new modDynamicsPrices($db);
$taborder = empty($module->dictionaries['taborder']) ? array() : $module->dictionaries['taborder'];
$tabname = empty($module->dictionaries['tabname']) ? array() : $module->dictionaries['tabname'];
$tablib = empty($module->dictionaries['tablib']) ? array() : $module->dictionaries['tablib'];
$tabsql = empty($module->dictionaries['tabsql']) ? array() : $module->dictionaries['tabsql'];
$tabsqlsort = empty($module->dictionaries['tabsqlsort']) ? array() : $module->dictionaries['tabsqlsort'];
$tabfield = empty($module->dictionaries['tabfield']) ? array() : $module->dictionaries['tabfield'];
$tabfieldvalue = empty($module->dictionaries['tabfieldvalue']) ? array() : $module->dictionaries['tabfieldvalue'];
$tabfieldinsert = empty($module->dictionaries['tabfieldinsert']) ? array() : $module->dictionaries['tabfieldinsert'];
$tabrowid = empty($module->dictionaries['tabrowid']) ? array() : $module->dictionaries['tabrowid'];
$tabcond = empty($module->dictionaries['tabcond']) ? array() : $module->dictionaries['tabcond'];
$tabhelp = empty($module->dictionaries['tabhelp']) ? array() : $module->dictionaries['tabhelp'];
$tabsave = empty($module->dictionaries['tabsave']) ? array() : $module->dictionaries['tabsave'];
$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

include DOL_DOCUMENT_ROOT.'/core/actions_dictionnaire.inc.php';

/**
 * Build options list for commercial category select.
 *
 * @param DoliDB $db Database handler
 * @return array<string,string>
 */
function dynamicspricesGetCommercialCategoryOptions($db)
{
	$options = array();

	$sql = "SELECT rowid, code, label";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_commercial_category";
	$sql .= " WHERE active = 1";
	$sql .= " ORDER BY label ASC, code ASC";

	$resql = $db->query($sql);
	if ($resql === false) {
		return $options;
	}

	while (is_object($obj = $db->fetch_object($resql))) {
		$options[$obj->code] = $obj->label.' ('.$obj->code.')';
	}

	return $options;
}

/**
 * Replace text input for code_commercial_category with a select.
 *
 * @param string $html Dictionary HTML output
 * @param Form   $form Form helper
 * @param array<string,string> $options Select options
 * @return string
 */
function dynamicspricesInjectCommercialCategorySelect($html, $form, $options)
{
	if (empty($options)) {
		return $html;
	}

	return preg_replace_callback(
		'/<input\b[^>]*name=[\"\']code_commercial_category[\"\'][^>]*>/i',
		function ($matches) use ($form, $options) {
			$input = $matches[0];
			$selected = 0;
			if (preg_match('/value=[\"\']([^\"\']*)[\"\']/i', $input, $valueMatch)) {
				$selected = $valueMatch[1];
			} else {
				$selected = GETPOST('code_commercial_category', 'aZ09');
			}
			return $form->selectarray('code_commercial_category', $options, $selected, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
		},
		$html
	);
}

/**
 * Print a select setting row.
 *
 * @param string $confkey Constant name
 * @param array<string,string> $options Select options
 * @param string $help Translation key for help
 * @return void
 */
function dynamicspricesPrintSelectSetting($confkey, array $options, $help = '')
{
	global $conf, $form, $langs;

	$value = getDolGlobalString($confkey);
	print '<tr>';
	print '<td>';
	if ($help !== '') {
		print $form->textwithtooltip($langs->trans($confkey), $langs->trans($help), 2, 1, img_help(1, ''));
	} else {
		print $langs->trans($confkey);
	}
	print '</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="set_'.$confkey.'">';
	print $form->selectarray($confkey, $options, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
	print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
	print '</form>';
	print '</td>';
	print '</tr>';
}

/**
 * Return source options for commercial line automatic cost priority.
 *
 * @return array<string,string>
 */
function dynamicspricesGetLineSourcePriorityOptions()
{
	global $langs;

	return array(
		'dynamicprices' => $langs->trans('DynamicPricesCostLineSourceDynamicPrices'),
		'dolibarr_default' => $langs->trans('DynamicPricesCostLineSourceDolibarrDefault'),
		'pmp' => $langs->trans('DynamicPricesCostLineSourcePmp'),
		'native_cost_price' => $langs->trans('DynamicPricesCostLineSourceNativeCostPrice'),
	);
}

/**
 * Normalize posted commercial line cost source priority.
 *
 * @return string
 */
function dynamicspricesGetPostedLineSourcePriority()
{
	$allowed = array_keys(dynamicspricesGetLineSourcePriorityOptions());
	$priority = array();
	for ($i = 1; $i <= 4; $i++) {
		$source = GETPOST('DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY_'.$i, 'alphanohtml');
		if ($source !== '' && in_array($source, $allowed, true) && !in_array($source, $priority, true)) {
			$priority[] = $source;
		}
	}

	if (empty($priority)) {
		$priority = array('dynamicprices', 'dolibarr_default', 'pmp', 'native_cost_price');
	}

	return implode(',', $priority);
}

/**
 * Print commercial line cost source priority setting.
 *
 * @return void
 */
function dynamicspricesPrintLineSourcePrioritySetting()
{
	global $db, $form, $langs;

	$service = new DynamicPricesCostService($db);
	$priority = $service->getCommercialLineCostSourcePriority();
	$options = array('' => $langs->trans('DynamicPricesCostLineSourceIgnore')) + dynamicspricesGetLineSourcePriorityOptions();

	print '<tr class="oddeven">';
	print '<td colspan="3"><span class="opacitymedium">'.$langs->trans('DynamicPricesCostLineSourcePriorityIntro').'</span></td>';
	print '</tr>';
	print '<tr>';
	print '<td>';
	print $form->textwithtooltip($langs->trans('DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY'), $langs->trans('DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY_HELP'), 2, 1, img_help(1, ''));
	print '</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="set_DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY">';
	for ($i = 1; $i <= 4; $i++) {
		$inputName = 'DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY_'.$i;
		$selected = isset($priority[$i - 1]) ? $priority[$i - 1] : '';
		print '<span class="nowrap">';
		print $langs->trans('DynamicPricesCostLineSourcePriorityRank', $i).' ';
		print $form->selectarray($inputName, $options, $selected, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		print '</span> ';
		print ajax_combobox($inputName);
	}
	print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
	print '</form>';
	print '</td>';
	print '</tr>';
}

/**
 * Print a read-only information row.
 *
 * @param string $labelkey Label translation key
 * @param string $valuekey Value translation key
 * @return void
 */
function dynamicspricesPrintInfoSetting($labelkey, $valuekey)
{
	global $langs;

	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($labelkey).'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right"><span class="opacitymedium">'.$langs->trans($valuekey).'</span></td>';
	print '</tr>';
}


// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 1;

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// Access control
if (!$user->admin) {
	accessforbidden();
}


$action = 'edit';


/*
* View
*/

$form = new Form($db);

$help_url = '';
$title = "DynamicsPricesSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-dynamicsprices page-admin');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = dynamicspricesAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, "dynamicsprices@dynamicsprices");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("DynamicsPricesSetupPage").'</span><br><br>';

print '<table class="noborder" width="100%">';

// Settings
setup_print_title($langs->trans("LMDB_UpdateOptions"));
setup_print_on_off('LMDB_COST_PRICE_ONLY');
setup_print_on_off('LMDB_SUPPLIER_BUYPRICE_ALTERED');
setup_print_on_off('LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT');
setup_print_on_off('LMDB_KIT_PRICE_FROM_COMPONENTS');

setup_print_title($langs->trans("DynamicPricesCostOptions"));
setup_print_on_off('DYNAMICPRICES_COST_ENABLE');
setup_print_on_off('DYNAMICPRICES_COST_USE_FOR_SALES');
dynamicspricesPrintSelectSetting('DYNAMICPRICES_COST_LINE_STRATEGY', array(
	'on_create_only' => $langs->trans('DynamicPricesCostLineStrategyOnCreateOnly'),
	'on_create_and_update' => $langs->trans('DynamicPricesCostLineStrategyOnCreateAndUpdate'),
	'manual_button' => $langs->trans('DynamicPricesCostLineStrategyManualButton'),
	'preserve_origin' => $langs->trans('DynamicPricesCostLineStrategyPreserveOrigin'),
	'never' => $langs->trans('DynamicPricesCostLineStrategyNever'),
));
dynamicspricesPrintSelectSetting('DYNAMICPRICES_COST_FALLBACK', array(
	'keep_dolibarr' => $langs->trans('DynamicPricesCostFallbackKeepDolibarr'),
	'native_cost_price' => $langs->trans('DynamicPricesCostFallbackNativeCostPrice'),
	'pmp' => $langs->trans('DynamicPricesCostFallbackPmp'),
	'zero' => $langs->trans('DynamicPricesCostFallbackZero'),
	'block' => $langs->trans('DynamicPricesCostFallbackBlock'),
));
setup_print_title($langs->trans('DynamicPricesCostLineSourcePriorityTitle'));
dynamicspricesPrintLineSourcePrioritySetting();
dynamicspricesPrintInfoSetting('DynamicPricesCostCalculationFormula', 'DynamicPricesCostCalculationFormulaHelp');
setup_print_on_off('DYNAMICPRICES_COST_INCLUDE_SERVICES');
setup_print_on_off('DYNAMICPRICES_COST_RECALC_KITS');
dynamicspricesPrintSelectSetting('DYNAMICPRICES_COST_ROUNDING_MODE', array(
	'dolibarr' => $langs->trans('DynamicPricesCostRoundingDolibarr'),
	'none' => $langs->trans('DynamicPricesCostRoundingNone'),
));
dynamicspricesPrintSelectSetting('DYNAMICPRICES_COST_LOG_MODE', array(
	'changes_only' => $langs->trans('DynamicPricesCostLogChangesOnly'),
	'all' => $langs->trans('DynamicPricesCostLogAll'),
));
setup_print_on_off('DYNAMICPRICES_COST_ALLOW_MANUAL_OVERRIDE');
setup_print_on_off('DYNAMICPRICES_COST_ALLOW_NATIVE_WRITE', false, 'DYNAMICPRICES_COST_ALLOW_NATIVE_WRITE_WARNING');
setup_print_on_off('DYNAMICPRICES_COST_DEBUG_LOG');

print '</table>';

print '<br>';

// Dictionary management
$commercialCategoryOptions = dynamicspricesGetCommercialCategoryOptions($db);
ob_start();
include DOL_DOCUMENT_ROOT.'/core/tpl/admin/dict.tpl.php';
$dictionaryHtml = ob_get_clean();
echo dynamicspricesInjectCommercialCategorySelect($dictionaryHtml, $form, $commercialCategoryOptions);

if (empty($setupnotempty)) {
print '<br>'.$langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
