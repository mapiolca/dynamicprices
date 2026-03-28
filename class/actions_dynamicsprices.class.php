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

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
 * Hooks for DynamicsPrices module.
 */
class ActionsDynamicsPrices extends CommonHookActions
{
	/** @var DoliDB */
	public $db;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Execute hook actions.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		dol_syslog(__METHOD__.' - Start doActions with action='.$action, LOG_DEBUG);
		dol_syslog(__METHOD__.' - WARNING trace: entering doActions', LOG_WARNING);

		if (empty($parameters['context']) || strpos($parameters['context'], 'ordersuppliercard') === false) {
			dol_syslog(__METHOD__.' - Skip: unsupported context', LOG_DEBUG);
			return 0;
		}

		if (!getDolGlobalInt('LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT')) {
			dol_syslog(__METHOD__.' - Skip: option LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT disabled', LOG_DEBUG);
			return 0;
		}

		if ($action !== 'dynamicsprices_confirm_commande') {
			dol_syslog(__METHOD__.' - Skip: action is not dynamicsprices_confirm_commande', LOG_DEBUG);
			return 0;
		}
		if (GETPOST('dynamicsprices_modal', 'alpha') !== '1') {
			dol_syslog(__METHOD__.' - Skip: request is not from dynamicsprices modal', LOG_DEBUG);
			return 0;
		}

		$confirm = GETPOST('confirm', 'alpha');
		if ($confirm !== 'yes') {
			dol_syslog(__METHOD__.' - Ignore flow detected from modal, continue supplier order submission without upsert', LOG_DEBUG);
			$_POST['dynamicsprices_skip_update'] = '1';
			$_REQUEST['dynamicsprices_skip_update'] = '1';
		}

		if ($action !== 'confirm_commande') {
			$_POST['action'] = 'confirm_commande';
			$_REQUEST['action'] = 'confirm_commande';
			$_POST['confirm'] = 'yes';
			$_REQUEST['confirm'] = 'yes';
			$action = 'confirm_commande';
		}

		if (GETPOST('dynamicsprices_skip_update', 'alpha') === '1') {
			dol_syslog(__METHOD__.' - Skip update requested, continue supplier order submission', LOG_DEBUG);
			return 0;
		}

		$selectedRows = GETPOST('dynamicsprices_apply_line', 'array');
		if (!is_array($selectedRows)) {
			$selectedRows = array();
		}
		$postedRowsData = GETPOST('dynamicsprices_data', 'array');
		if (!is_array($postedRowsData)) {
			$postedRowsData = array();
		}
		$selectedLinesCsv = GETPOST('dynamicsprices_selected_lines', 'alphanohtml');
		if (empty($selectedRows) && !empty($selectedLinesCsv)) {
			$selectedLineIds = explode(',', $selectedLinesCsv);
			foreach ($selectedLineIds as $selectedLineId) {
				$selectedLineId = (int) trim($selectedLineId);
				if ($selectedLineId > 0) {
					$selectedRows[$selectedLineId] = 1;
				}
			}
		}
		if (empty($selectedRows) && !empty($postedRowsData) && GETPOST('confirm', 'alpha') === 'yes') {
			foreach (array_keys($postedRowsData) as $lineIdFromPost) {
				$selectedRows[(int) $lineIdFromPost] = 1;
			}
		}
		dol_syslog(__METHOD__.' - Selected supplier price lines='.implode(',', array_keys($selectedRows)), LOG_DEBUG);

		$differences = $this->getOrderSupplierPriceDifferences($object);
		$priceDifferences = $this->filterPriceDifferences($differences);
		if (empty($priceDifferences)) {
			dol_syslog(__METHOD__.' - No supplier unit price difference found, nothing to update', LOG_DEBUG);
			return 0;
		}
		dol_syslog(__METHOD__.' - Found '.count($priceDifferences).' line(s) with supplier unit price differences', LOG_DEBUG);

		$updatedLines = 0;
		$updatedUp = 0;
		$updatedDown = 0;
		$updatedSame = 0;
		foreach ($priceDifferences as $lineId => $diff) {
			if (!array_key_exists((int) $lineId, $selectedRows) && !array_key_exists((string) $lineId, $selectedRows)) {
				dol_syslog(__METHOD__.' - Skip line '.$lineId.' (unchecked)', LOG_DEBUG);
				continue;
			}

			$preparedDiff = $this->applyPostedValuesToDiff($lineId, $diff, $postedRowsData);
			dol_syslog(__METHOD__.' - Upsert supplier price for line '.$lineId.' (product '.$preparedDiff['fk_product'].')', LOG_DEBUG);
			$res = $this->upsertSupplierPriceFromDiff($preparedDiff);
			if ($res < 0) {
				dol_syslog(__METHOD__.' - Error while upserting supplier price for line '.$lineId.': '.$this->error, LOG_ERR);
				setEventMessages($this->error, $this->errors, 'errors');
				return -1;
			}

			$updatedLines++;
			$direction = isset($preparedDiff['price_direction']) ? (string) $preparedDiff['price_direction'] : 'same';
			if ($direction === 'up') {
				$updatedUp++;
			} elseif ($direction === 'down') {
				$updatedDown++;
			} else {
				$updatedSame++;
			}
			dol_syslog(__METHOD__.' - Applied supplier price update on order='.(int) $object->id.' line='.(int) $lineId.' product='.(int) $preparedDiff['fk_product'].' supplier='.(int) $preparedDiff['fk_soc'].' current='.(isset($preparedDiff['current_unitprice']) ? price2num((float) $preparedDiff['current_unitprice'], 'MS') : 0).' new='.price2num((float) $preparedDiff['unitprice'], 'MS').' delta='.(isset($preparedDiff['price_delta']) ? price2num((float) $preparedDiff['price_delta'], 'MS') : 0).' direction='.$direction, LOG_DEBUG);
		}

		if ($updatedLines > 0) {
			global $langs;
			$langs->load('dynamicsprices@dynamicsprices');
			setEventMessages($langs->trans('LMDB_SupplierPriceUpdatedCountWithDirection', $updatedLines, $updatedUp, $updatedDown, $updatedSame), null, 'mesgs');
		}
		dol_syslog(__METHOD__.' - End doActions with '.$updatedLines.' line(s) updated', LOG_DEBUG);

		return 0;
	}

	/**
	 * Build a confirmation modal with supplier prices to add/update.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Current object
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function formConfirm($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		dol_syslog(__METHOD__.' - Start formConfirm with action='.$action, LOG_DEBUG);
		dol_syslog(__METHOD__.' - WARNING trace: entering formConfirm', LOG_WARNING);

		if (empty($parameters['context']) || strpos($parameters['context'], 'ordersuppliercard') === false) {
			dol_syslog(__METHOD__.' - Skip: unsupported context', LOG_DEBUG);
			return 0;
		}

		if (!getDolGlobalInt('LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT')) {
			dol_syslog(__METHOD__.' - Skip: option LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT disabled', LOG_DEBUG);
			return 0;
		}

		if ($action !== 'commande') {
			dol_syslog(__METHOD__.' - Skip: action is not commande', LOG_DEBUG);
			return 0;
		}

		$differences = $this->getOrderSupplierPriceDifferences($object);
		$priceDifferences = $this->filterPriceDifferences($differences);
		if (empty($priceDifferences)) {
			dol_syslog(__METHOD__.' - No supplier unit price difference found, native confirmation will be used', LOG_DEBUG);
			return 0;
		}
		$displayDifferences = $this->getOrderSupplierPriceDifferences($object, true);
		dol_syslog(__METHOD__.' - Prepare modal with '.count($displayDifferences).' line(s), including unchanged lines, because at least one supplier unit price differs', LOG_DEBUG);

		$langs->load('dynamicsprices@dynamicsprices');
		$url = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;
		$datecommande = GETPOST('datecommande', 'alphanohtml');
		$methodecommande = GETPOST('methodecommande', 'alpha');
		$comment = GETPOST('comment', 'alphanohtml');
		$csrfToken = newToken();
		$url .= '&token='.$csrfToken;

		$html = '<div class="div-table-responsive">';
		$html .= '<table class="noborder">';
		$html .= '<tr class="liste_titre">';
		$html .= '<td>&nbsp;</td>';
		$html .= '<td>'.$langs->trans('ProductRef').'</td>';
		$html .= '<td>'.$langs->trans('LMDB_SupplierRef').'</td>';
		$html .= '<td class="right">'.$langs->trans('QtyMin').'</td>';
		$html .= '<td class="right">'.$langs->trans('LMDB_QuantityPackaging').'</td>';
		$html .= '<td class="right">'.$langs->trans('VATRate').'</td>';
		$html .= '<td class="right">'.$langs->trans('LMDB_CurrentUnitPriceHT').'</td>';
		$html .= '<td class="right">'.$langs->trans('LMDB_ProposedUnitPriceHT').'</td>';
		$html .= '<td class="right">'.$langs->trans('LMDB_PriceDeltaHT').'</td>';
		$html .= '<td class="center">'.$langs->trans('LMDB_PriceDirection').'</td>';
		$html .= '<td class="right">'.$langs->trans('Discount').' (%)</td>';
		$html .= '</tr>';

		foreach ($displayDifferences as $lineId => $diff) {
			$html .= '<tr class="oddeven">';
			$html .= '<td><input type="checkbox" name="dynamicsprices_apply_line['.$lineId.']" value="1" checked></td>';
			$html .= '<td>'.$this->getProductNomUrl((int) $diff['fk_product'], $diff['ref']).'</td>';
			$html .= '<td><input class="maxwidth25" type="text" name="dynamicsprices_data['.$lineId.'][supplier_ref]" value="'.dol_escape_htmltag($diff['supplier_ref']).'"></td>';
			$html .= '<td class="right"><input class="right maxwidth25" type="text" name="dynamicsprices_data['.$lineId.'][qty]" value="'.dol_escape_htmltag((string) $diff['qty']).'"></td>';
			$html .= '<td class="right"><input class="right maxwidth25" type="text" name="dynamicsprices_data['.$lineId.'][unitquantity]" value="'.dol_escape_htmltag((string) $diff['unitquantity']).'"></td>';
			$html .= '<td class="right"><input class="right maxwidth25" type="text" name="dynamicsprices_data['.$lineId.'][vat]" value="'.dol_escape_htmltag((string) $diff['vat']).'"></td>';
			$html .= '<td class="right">'.dol_escape_htmltag((string) $diff['current_unitprice']).'</td>';
			$html .= '<td class="right"><input class="right maxwidth25" type="text" name="dynamicsprices_data['.$lineId.'][unitprice]" value="'.dol_escape_htmltag((string) $diff['new_unitprice']).'"></td>';
			$html .= '<td class="right">'.dol_escape_htmltag($this->getPriceDeltaLabel($diff)).'</td>';
			$html .= '<td class="center">'.$this->getPriceDirectionBadgeHtml($diff['price_direction']).'</td>';
			$html .= '<td class="right"><input class="right maxwidth25" type="text" name="dynamicsprices_data['.$lineId.'][discount]" value="'.dol_escape_htmltag((string) $diff['discount']).'" placeholder="%"></td>';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][fk_product]" value="'.((int) $diff['fk_product']).'">';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][fk_soc]" value="'.((int) $diff['fk_soc']).'">';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][current_rowid]" value="'.((int) $diff['current_rowid']).'">';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][fk_availability]" value="'.((int) $diff['fk_availability']).'">';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][delivery_time_days]" value="'.dol_escape_htmltag($diff['delivery_time_days'] === null ? '' : (string) $diff['delivery_time_days']).'">';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][supplier_reputation]" value="'.dol_escape_htmltag((string) $diff['supplier_reputation']).'">';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][supplier_ref]" value="'.dol_escape_htmltag($diff['supplier_ref']).'">';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$html .= '</div>';

		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
		$form = new Form($this->db);
		$formquestion = array(
			array('type' => 'other', 'name' => 'dynamicsprices_diff_table', 'label' => '', 'value' => $html),
			array('type' => 'hidden', 'name' => 'dynamicsprices_modal', 'value' => '1'),
			array('type' => 'hidden', 'name' => 'dynamicsprices_selected_lines', 'value' => implode(',', array_keys($displayDifferences))),
			array('type' => 'hidden', 'name' => 'datecommande', 'value' => $datecommande),
			array('type' => 'hidden', 'name' => 'methodecommande', 'value' => $methodecommande),
			array('type' => 'hidden', 'name' => 'methode', 'value' => $methodecommande),
			array('type' => 'hidden', 'name' => 'comment', 'value' => $comment),
		);

		$ignoreUrl = $url.'&action=dynamicsprices_confirm_commande&confirm=yes&dynamicsprices_modal=1&dynamicsprices_skip_update=1';
		$ignoreUrl .= '&datecommande='.urlencode($datecommande);
		$ignoreUrl .= '&methodecommande='.urlencode($methodecommande);
		$ignoreUrl .= '&methode='.urlencode($methodecommande);
		$ignoreUrl .= '&comment='.urlencode($comment);
		$this->resprints = $form->formconfirm($url, $langs->trans('LMDB_SupplierPriceModalTitle'), $langs->trans('LMDB_SupplierPriceModalDescription'), 'dynamicsprices_confirm_commande', $formquestion, 1, 1, 0, 'auto', '', $langs->trans('Validate'), $langs->trans('LMDB_Ignore'));
		$this->resprints .= '<script>';
		$this->resprints .= 'jQuery(function($){';
		$this->resprints .= 'var applyModalSizing=function(){';
		$this->resprints .= 'var $dialog=$(".ui-dialog:has(input[name=\'dynamicsprices_modal\'])");';
		$this->resprints .= 'if(!$dialog.length) return;';
		$this->resprints .= 'var $content=$dialog.find(".ui-dialog-content");';
		$this->resprints .= 'if(!$content.length) return;';
		$this->resprints .= 'var rows=$dialog.find("tr.oddeven").length;';
		$this->resprints .= 'var maxHeight=Math.max(200,$(window).height()-100);';
		$this->resprints .= 'var wantedHeight=Math.min(maxHeight,210+(rows*34));';
		$this->resprints .= 'var wantedWidth=Math.min($(window).width()-100,Math.max(900,$dialog.find("table").outerWidth()+80));';
		$this->resprints .= '$dialog.css("max-width",( $(window).width()-100 )+"px");';
		$this->resprints .= '$dialog.find(".ui-dialog-content").css({"max-height":wantedHeight+"px","overflow-y":"auto"});';
		$this->resprints .= '$content.dialog("option","width",wantedWidth);';
		$this->resprints .= '$content.dialog("option","position",{my:"center",at:"center",of:window,collision:"fit"});';
		$this->resprints .= 'var left=Math.max(0,($(window).width()-$dialog.outerWidth())/2);';
		$this->resprints .= 'var top=Math.max(10,($(window).height()-$dialog.outerHeight())/2+$(window).scrollTop());';
		$this->resprints .= '$dialog.css({left:left+"px",top:top+"px"});';
		$this->resprints .= '};';
		$this->resprints .= 'setTimeout(applyModalSizing,0);';
		$this->resprints .= '$(window).on("resize", applyModalSizing);';
		$this->resprints .= '$(document).on("click", ".ui-dialog-buttonset .ui-button", function(){';
		$this->resprints .= 'if($.trim($(this).text())==="'.$langs->transnoentitiesnoconv('LMDB_Ignore').'"){window.location.href="'.$ignoreUrl.'";return false;}';
		$this->resprints .= 'var selected=[];';
		$this->resprints .= '$("input[name^=\'dynamicsprices_apply_line\']:checked").each(function(){';
		$this->resprints .= 'var m=($(this).attr("name")||"").match(/\\[(\\d+)\\]/);';
		$this->resprints .= 'if(m&&m[1]) selected.push(m[1]);';
		$this->resprints .= '});';
		$this->resprints .= '$("input[name=\'dynamicsprices_selected_lines\']").val(selected.join(","));';
		$this->resprints .= '});';
		$this->resprints .= '$(document).on("click", ".ui-dialog-titlebar-close", function(){';
		$this->resprints .= 'window.location.href = "'.$ignoreUrl.'";';
		$this->resprints .= '});';
		$this->resprints .= '});';
		$this->resprints .= '</script>';
		dol_syslog(__METHOD__.' - Custom confirmation modal rendered', LOG_DEBUG);
		return 1;
	}

	/**
	 * Get supplier price differences between order lines and current supplier prices.
	 *
	 * @param CommandeFournisseur $object Supplier order
	 * @param bool $includeUnchanged Include unchanged lines for display purposes
	 * @return array<int,array<string,mixed>>
	 */
	private function getOrderSupplierPriceDifferences($object, $includeUnchanged = false)
	{
		$differences = array();
		if (empty($object->id) || empty($object->socid)) {
			dol_syslog(__METHOD__.' - Skip comparison: missing order id or supplier id', LOG_DEBUG);
			return $differences;
		}

		if (empty($object->lines) || !is_array($object->lines)) {
			$object->fetch_lines();
		}

		foreach ($object->lines as $line) {
			if (empty($line->fk_product)) {
				dol_syslog(__METHOD__.' - Skip line without product id', LOG_DEBUG);
				continue;
			}

			$qty = price2num((float) $line->qty, 'MS');
			$unitquantity = price2num((float) (empty($line->unitquantity) ? $line->qty : $line->unitquantity), 'MS');
			$vat = price2num((float) $line->tva_tx, 'MS');
			$discount = price2num((float) $line->remise_percent, 'MS');
			$unitprice = $this->getLineUnitPrice($line);
			$fkAvailability = isset($line->fk_availability) ? (int) $line->fk_availability : 0;
			$deliveryTimeDays = null;
			if (isset($line->delivery_time_days) && $line->delivery_time_days !== '') {
				$deliveryTimeDays = (int) $line->delivery_time_days;
			}
			$reputation = isset($line->supplier_reputation) ? price2num((float) $line->supplier_reputation, 'MS') : 0;

			$linkedSupplierPriceId = $this->getSupplierPriceIdFromOrderLine($line, (int) $object->id);
			$current = $this->getCurrentSupplierPrice((int) $line->fk_product, (int) $object->socid, $qty, $linkedSupplierPriceId);
			$currentUnitprice = !empty($current) ? price2num((float) $current['unitprice'], 'MS') : 0;
			$newUnitprice = price2num((float) $unitprice, 'MS');
			$priceDelta = price2num($newUnitprice - $currentUnitprice, 'MS');
			$priceDirection = $this->getPriceDirection($currentUnitprice, $newUnitprice);
			$priceDeltaPercent = null;
			if ($currentUnitprice != 0) {
				$priceDeltaPercent = price2num(($priceDelta / $currentUnitprice) * 100, 'MS');
			}

			$isPriceDifferent = empty($current) || $priceDirection !== 'same';
			$isOtherFieldsDifferent = empty($current)
				|| price2num((float) $current['tva_tx'], 'MS') !== $vat
				|| price2num((float) $current['remise_percent'], 'MS') !== $discount
				|| (int) $current['fk_availability'] !== $fkAvailability
				|| ($deliveryTimeDays !== null && (int) $current['delivery_time_days'] !== $deliveryTimeDays)
				|| price2num((float) $current['supplier_reputation'], 'MS') !== $reputation;
			$isDifferent = $isPriceDifferent || $isOtherFieldsDifferent;

			if (!$isDifferent && !$includeUnchanged) {
				continue;
			}

			$differences[(int) $line->id] = array(
				'lineid' => (int) $line->id,
				'fk_product' => (int) $line->fk_product,
				'fk_soc' => (int) $object->socid,
				'qty' => $qty,
				'unitquantity' => $unitquantity,
				'vat' => $vat,
				'unitprice' => $newUnitprice,
				'current_unitprice' => $currentUnitprice,
				'new_unitprice' => $newUnitprice,
				'price_delta' => $priceDelta,
				'price_delta_percent' => $priceDeltaPercent,
				'price_direction' => $priceDirection,
				'is_price_different' => $isPriceDifferent ? 1 : 0,
				'is_other_fields_different' => $isOtherFieldsDifferent ? 1 : 0,
				'discount' => $discount,
				'fk_availability' => $fkAvailability,
				'delivery_time_days' => $deliveryTimeDays,
				'supplier_reputation' => $reputation,
				'current_rowid' => !empty($current['rowid']) ? (int) $current['rowid'] : 0,
				'ref' => isset($line->ref) ? $line->ref : '',
				'supplier_ref' => $this->getSupplierReferenceFromLine($line, (int) $object->socid),
				'label' => isset($line->product_label) ? $line->product_label : (isset($line->desc) ? $line->desc : ''),
			);
			dol_syslog(__METHOD__.' - Supplier price diff detected order='.(int) $object->id.' line='.(int) $line->id.' product='.(int) $line->fk_product.' supplier='.(int) $object->socid.' current='.$currentUnitprice.' proposed='.$newUnitprice.' delta='.$priceDelta.' direction='.$priceDirection.' fk_availability='.$fkAvailability.' delivery_time_days='.($deliveryTimeDays === null ? 'null' : $deliveryTimeDays), LOG_DEBUG);
		}
		dol_syslog(__METHOD__.' - Comparison completed with '.count($differences).' difference(s)', LOG_DEBUG);

		return $differences;
	}

	/**
	 * Keep only differences where supplier unit price changed.
	 *
	 * @param array<int,array<string,mixed>> $differences Raw differences
	 * @return array<int,array<string,mixed>>
	 */
	private function filterPriceDifferences(array $differences)
	{
		$priceDifferences = array();
		foreach ($differences as $lineId => $diff) {
			if (!empty($diff['is_price_different'])) {
				$priceDifferences[(int) $lineId] = $diff;
			}
		}

		return $priceDifferences;
	}

	/**
	 * Return current supplier price line for a product/supplier/minimum quantity.
	 *
	 * @param int $fkProduct Product id
	 * @param int $fkSoc Supplier thirdparty id
	 * @param float $qty Minimum quantity
	 * @param int $preferredRowid Preferred supplier price rowid linked to the order line
	 * @return array<string,mixed>
	 */
	private function getCurrentSupplierPrice($fkProduct, $fkSoc, $qty, $preferredRowid = 0)
	{
		global $conf;
		if (!empty($preferredRowid)) {
			$sql = 'SELECT rowid, unitprice, tva_tx, remise_percent, fk_availability, delivery_time_days, supplier_reputation';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'product_fournisseur_price';
			$sql .= ' WHERE rowid = '.((int) $preferredRowid);
			$sql .= ' AND fk_product = '.((int) $fkProduct);
			$sql .= ' AND fk_soc = '.((int) $fkSoc);
			$sql .= ' AND entity = '.((int) $conf->entity);
			$sql .= ' LIMIT 1';

			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				if ($obj) {
					return array(
						'rowid' => (int) $obj->rowid,
						'unitprice' => (float) $obj->unitprice,
						'tva_tx' => (float) $obj->tva_tx,
						'remise_percent' => (float) $obj->remise_percent,
						'fk_availability' => (int) $obj->fk_availability,
						'delivery_time_days' => ($obj->delivery_time_days !== null ? (int) $obj->delivery_time_days : null),
						'supplier_reputation' => (float) $obj->supplier_reputation,
					);
				}
			}
		}

		$sql = 'SELECT rowid, unitprice, tva_tx, remise_percent, fk_availability, delivery_time_days, supplier_reputation';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'product_fournisseur_price';
		$sql .= ' WHERE fk_product = '.((int) $fkProduct);
		$sql .= ' AND fk_soc = '.((int) $fkSoc);
		$sql .= ' AND quantity = '.price2num((float) $qty, 'MS');
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' ORDER BY rowid DESC';

		$resql = $this->db->query($sql);
		if (!$resql || !$this->db->num_rows($resql)) {
			return array();
		}

		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return array();
		}

		return array(
			'rowid' => (int) $obj->rowid,
			'unitprice' => (float) $obj->unitprice,
			'tva_tx' => (float) $obj->tva_tx,
			'remise_percent' => (float) $obj->remise_percent,
			'fk_availability' => (int) $obj->fk_availability,
			'delivery_time_days' => ($obj->delivery_time_days !== null ? (int) $obj->delivery_time_days : null),
			'supplier_reputation' => (float) $obj->supplier_reputation,
		);
	}

	/**
	 * Resolve supplier price rowid linked to a supplier order line.
	 *
	 * @param CommonObjectLine $line Supplier order line
	 * @param int $orderId Supplier order id
	 * @return int
	 */
	private function getSupplierPriceIdFromOrderLine($line, $orderId)
	{
		$propertyCandidates = array('fk_prod_fourn_price', 'fk_fournprice');
		foreach ($propertyCandidates as $propertyName) {
			if (!empty($line->{$propertyName})) {
				return (int) $line->{$propertyName};
			}
		}

		$lineId = !empty($line->id) ? (int) $line->id : 0;
		if (empty($lineId) || empty($orderId)) {
			return 0;
		}

		$lineSupplierPriceField = $this->getSupplierPriceFieldNameOnOrderLineTable();
		if (empty($lineSupplierPriceField)) {
			return 0;
		}

		$sql = 'SELECT '.$lineSupplierPriceField.' as linked_supplier_price_id';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet';
		$sql .= ' WHERE rowid = '.$lineId;
		$sql .= ' AND fk_commande = '.((int) $orderId);
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if ($obj && !empty($obj->linked_supplier_price_id)) {
				return (int) $obj->linked_supplier_price_id;
			}
		}

		return 0;
	}

	/**
	 * Get supplier price field name on commande_fournisseurdet table.
	 *
	 * @return string
	 */
	private function getSupplierPriceFieldNameOnOrderLineTable()
	{
		static $cachedFieldName = null;
		if ($cachedFieldName !== null) {
			return $cachedFieldName;
		}

		$fieldCandidates = array('fk_prod_fourn_price', 'fk_fournprice');
		foreach ($fieldCandidates as $fieldName) {
			$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."commande_fournisseurdet LIKE '".$this->db->escape($fieldName)."'";
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				$cachedFieldName = $fieldName;
				return $cachedFieldName;
			}
		}

		$cachedFieldName = '';
		return $cachedFieldName;
	}

	/**
	 * Get unit purchase price from supplier order line.
	 *
	 * @param CommonObjectLine $line Supplier order line
	 * @return float
	 */
	private function getLineUnitPrice($line)
	{
		if (isset($line->subprice)) {
			return price2num((float) $line->subprice, 'MS');
		}

		if (isset($line->pu_ht)) {
			return price2num((float) $line->pu_ht, 'MS');
		}

		return 0.0;
	}

	/**
	 * Get price direction between current supplier price and order line price.
	 *
	 * @param float $currentUnitprice Current supplier unit price
	 * @param float $newUnitprice New unit price from order line
	 * @return string
	 */
	private function getPriceDirection($currentUnitprice, $newUnitprice)
	{
		$delta = price2num((float) $newUnitprice - (float) $currentUnitprice, 'MS');
		if (abs($delta) < 0.000001) {
			return 'same';
		}

		return ($delta > 0 ? 'up' : 'down');
	}

	/**
	 * Build display label for the price delta.
	 *
	 * @param array<string,mixed> $diff Difference payload
	 * @return string
	 */
	private function getPriceDeltaLabel(array $diff)
	{
		$delta = isset($diff['price_delta']) ? price2num($diff['price_delta'], 'MS') : 0;
		$prefix = ($delta > 0 ? '+' : '');
		$label = $prefix.$delta;

		if (isset($diff['price_delta_percent']) && $diff['price_delta_percent'] !== null && $diff['price_delta_percent'] !== '') {
			$percent = price2num($diff['price_delta_percent'], 'MS');
			$label .= ' ('.$prefix.$percent.'%)';
		}

		return $label;
	}

	/**
	 * Build badge HTML for the price direction.
	 *
	 * @param string $direction Direction key up|down|same
	 * @return string
	 */
	private function getPriceDirectionBadgeHtml($direction)
	{
		global $langs;

		$direction = (string) $direction;
		$cssClass = 'badge';
		$labelKey = 'LMDB_PriceDirectionSame';
		if ($direction === 'up') {
			$cssClass .= ' badge-status4';
			$labelKey = 'LMDB_PriceDirectionUp';
		} elseif ($direction === 'down') {
			$cssClass .= ' badge-status1';
			$labelKey = 'LMDB_PriceDirectionDown';
		} else {
			$cssClass .= ' badge-status6';
		}

		return '<span class="'.$cssClass.'">'.$langs->trans($labelKey).'</span>';
	}

	/**
	 * Insert or update supplier price line from a difference.
	 *
	 * @param array<string,mixed> $diff Difference payload
	 * @return int
	 */
	private function upsertSupplierPriceFromDiff(array $diff)
	{
		global $user;
		$targetSupplierPriceRowId = !empty($diff['current_rowid']) ? (int) $diff['current_rowid'] : 0;
		if (!empty($targetSupplierPriceRowId)) {
			$targetSupplierPrice = $this->getCurrentSupplierPrice((int) $diff['fk_product'], (int) $diff['fk_soc'], (float) $diff['qty'], $targetSupplierPriceRowId);
			if (empty($targetSupplierPrice)) {
				$targetSupplierPriceRowId = 0;
			}
		}

		$productFournisseur = new ProductFournisseur($this->db);
		$resultFetchProduct = $productFournisseur->fetch((int) $diff['fk_product']);
		if ($resultFetchProduct <= 0) {
			$this->error = 'Unable to load product id='.((int) $diff['fk_product']).' to update supplier price.';
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__.' - '.$this->error, LOG_ERR);
			return -1;
		}

		$supplier = new Societe($this->db);
		$resultFetchSupplier = $supplier->fetch((int) $diff['fk_soc']);
		if ($resultFetchSupplier <= 0) {
			$this->error = 'Unable to load supplier id='.((int) $diff['fk_soc']).' to update supplier price.';
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__.' - '.$this->error, LOG_ERR);
			return -1;
		}

		$productFournisseur->product_fourn_price_id = $targetSupplierPriceRowId;
		dol_syslog(__METHOD__.' - Upsert supplier price through ProductFournisseur::update_buyprice product='.(int) $diff['fk_product'].' supplier='.(int) $diff['fk_soc'].' target_rowid='.$targetSupplierPriceRowId, LOG_DEBUG);
		$qtyForApi = price2num((float) $diff['qty'], 'MS');
		$unitpriceForApi = price2num((float) $diff['unitprice'], 'MS');
		$buypriceForApi = price2num($unitpriceForApi * $qtyForApi, 'MS');
		dol_syslog(__METHOD__.' - update_buyprice payload qty='.$qtyForApi.' unitprice='.$unitpriceForApi.' buyprice_for_api='.$buypriceForApi.' current_rowid='.$targetSupplierPriceRowId, LOG_DEBUG);

		$resultUpdate = $productFournisseur->update_buyprice(
			$qtyForApi,
			$buypriceForApi,
			$user,
			'HT',
			$supplier,
			((int) $diff['fk_availability']),
			(isset($diff['supplier_ref']) ? (string) $diff['supplier_ref'] : ''),
			price2num((float) $diff['vat'], 'MS'),
			0,
			price2num((float) $diff['discount'], 'MS'),
			0,
			0,
			(isset($diff['delivery_time_days']) && $diff['delivery_time_days'] !== null ? (int) $diff['delivery_time_days'] : ''),
			price2num((float) $diff['supplier_reputation'], 'MS')
		);
		if ($resultUpdate <= 0) {
			$this->error = !empty($productFournisseur->error) ? $productFournisseur->error : 'Error while updating supplier price through ProductFournisseur::update_buyprice';
			$this->errors = !empty($productFournisseur->errors) ? $productFournisseur->errors : $this->errors;
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__.' - Business API error: '.$this->error, LOG_ERR);
			return -1;
		}

		dol_syslog(__METHOD__.' - Business API supplier price upsert successful with rowid='.$resultUpdate, LOG_DEBUG);

		return 1;
	}

	/**
	 * Merge posted form values into difference payload.
	 *
	 * @param int $lineId Line id
	 * @param array<string,mixed> $diff Computed difference
	 * @param array<string,mixed> $postedRowsData Submitted rows data
	 * @return array<string,mixed>
	 */
	private function applyPostedValuesToDiff($lineId, array $diff, array $postedRowsData)
	{
		if (empty($postedRowsData[$lineId]) || !is_array($postedRowsData[$lineId])) {
			return $diff;
		}

		$rowData = $postedRowsData[$lineId];

		$diff['qty'] = isset($rowData['qty']) ? price2num($rowData['qty'], 'MS') : $diff['qty'];
		$diff['unitquantity'] = isset($rowData['unitquantity']) ? price2num($rowData['unitquantity'], 'MS') : $diff['unitquantity'];
		$diff['vat'] = isset($rowData['vat']) ? price2num($rowData['vat'], 'MS') : $diff['vat'];
		$diff['unitprice'] = isset($rowData['unitprice']) ? price2num($rowData['unitprice'], 'MS') : $diff['unitprice'];
		$diff['discount'] = isset($rowData['discount']) ? price2num($rowData['discount'], 'MS') : $diff['discount'];
		$diff['fk_availability'] = isset($rowData['fk_availability']) ? (int) $rowData['fk_availability'] : $diff['fk_availability'];
		$diff['delivery_time_days'] = (isset($rowData['delivery_time_days']) && $rowData['delivery_time_days'] !== '') ? (int) $rowData['delivery_time_days'] : null;
		$diff['supplier_reputation'] = isset($rowData['supplier_reputation']) ? price2num($rowData['supplier_reputation'], 'MS') : $diff['supplier_reputation'];
		$diff['fk_product'] = isset($rowData['fk_product']) ? (int) $rowData['fk_product'] : $diff['fk_product'];
		$diff['fk_soc'] = isset($rowData['fk_soc']) ? (int) $rowData['fk_soc'] : $diff['fk_soc'];
		$diff['current_rowid'] = isset($rowData['current_rowid']) ? (int) $rowData['current_rowid'] : $diff['current_rowid'];
		$diff['supplier_ref'] = isset($rowData['supplier_ref']) ? $rowData['supplier_ref'] : $diff['supplier_ref'];
		$diff['label'] = isset($rowData['label']) ? $rowData['label'] : $diff['label'];
		$diff['new_unitprice'] = $diff['unitprice'];

		if (isset($diff['current_unitprice'])) {
			$currentUnitprice = price2num((float) $diff['current_unitprice'], 'MS');
			$newUnitprice = price2num((float) $diff['new_unitprice'], 'MS');
			$diff['price_delta'] = price2num($newUnitprice - $currentUnitprice, 'MS');
			$diff['price_direction'] = $this->getPriceDirection($currentUnitprice, $newUnitprice);
			$diff['price_delta_percent'] = null;
			if ($currentUnitprice != 0) {
				$diff['price_delta_percent'] = price2num(($diff['price_delta'] / $currentUnitprice) * 100, 'MS');
			}
		}

		return $diff;
	}

	/**
	 * Get supplier reference to display next to product internal ref.
	 *
	 * @param CommonObjectLine $line Order line
	 * @param int $socid Supplier id
	 * @return string
	 */
	private function getSupplierReferenceFromLine($line, $socid)
	{
		if (!empty($line->ref_fourn)) {
			return (string) $line->ref_fourn;
		}
		if (!empty($line->ref_supplier)) {
			return (string) $line->ref_supplier;
		}
		if (empty($line->fk_product) || empty($socid)) {
			return '';
		}

		$sql = 'SELECT ref_fourn FROM '.MAIN_DB_PREFIX.'product_fournisseur_price';
		$sql .= ' WHERE fk_product = '.((int) $line->fk_product);
		$sql .= ' AND fk_soc = '.((int) $socid);
		$sql .= ' AND ref_fourn IS NOT NULL AND ref_fourn <> \'\'';
		$sql .= ' ORDER BY rowid DESC';
		$sql .= ' LIMIT 1';
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if ($obj && isset($obj->ref_fourn)) {
				return (string) $obj->ref_fourn;
			}
		}

		return '';
	}

	/**
	 * Build product ref output with getNomUrl for modal display.
	 *
	 * @param int $fkProduct Product id
	 * @param string $fallbackRef Fallback ref
	 * @return string
	 */
	private function getProductNomUrl($fkProduct, $fallbackRef = '')
	{
		static $cache = array();
		if (isset($cache[$fkProduct])) {
			return $cache[$fkProduct];
		}

		if ($fkProduct <= 0) {
			return dol_escape_htmltag($fallbackRef);
		}

		dol_include_once('/product/class/product.class.php');
		$product = new Product($this->db);
		if ($product->fetch($fkProduct) > 0) {
			$cache[$fkProduct] = $product->getNomUrl(1);
			return $cache[$fkProduct];
		}

		$cache[$fkProduct] = dol_escape_htmltag($fallbackRef);
		return $cache[$fkProduct];
	}
}
