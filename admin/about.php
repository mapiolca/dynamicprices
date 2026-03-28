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
 * \file    dynamicsprices/admin/about.php
 * \ingroup dynamicsprices
 * \brief   About page of DynamicsPrices module.
 */

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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/dynamicsprices.lib.php';
require_once '../core/modules/modDynamicsPrices.class.php';

$langs->loadLangs(array("admin", "dynamicsprices@dynamicsprices"));

if (!$user->admin) {
	accessforbidden();
}

$title = $langs->trans('LMDB_AboutTitle');
$help_url = '';
$moduleDescriptor = new modDynamicsPrices($db);

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-dynamicsprices page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

$head = dynamicspricesAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $title, -1, "dynamicsprices@dynamicsprices");

print '<div class="opacitymedium">'.$langs->trans('LMDB_AboutDescription').'</div>';
print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LMDB_AboutGeneral').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Module').'</td><td>'.$langs->trans('ModuleDynamicsPricesName').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.dol_escape_htmltag($langs->trans($moduleDescriptor->description)).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag((string) $moduleDescriptor->version).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Author').'</td><td>'.dol_escape_htmltag((string) $moduleDescriptor->editor_name).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Website').'</td><td><a href="https://'.dol_escape_htmltag((string) $moduleDescriptor->editor_url).'" target="_blank" rel="noopener noreferrer">'.dol_escape_htmltag((string) $moduleDescriptor->editor_url).'</a></td></tr>';
print '</table>';
print '</div>';
print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('LMDB_AboutResources').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Documentation').'</td><td><a href="https://wiki.dolibarr.org/" target="_blank" rel="noopener noreferrer">wiki.dolibarr.org</a></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Support').'</td><td><a href="https://'.dol_escape_htmltag((string) $moduleDescriptor->editor_url).'" target="_blank" rel="noopener noreferrer">'.dol_escape_htmltag((string) $moduleDescriptor->editor_url).'</a></td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
