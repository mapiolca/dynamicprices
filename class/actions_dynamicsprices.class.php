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

		if (empty($parameters['context']) || strpos($parameters['context'], 'ordersuppliercard') === false) {
			dol_syslog(__METHOD__.' - Skip: unsupported context', LOG_DEBUG);
			return 0;
		}

		if (!getDolGlobalInt('LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT')) {
			dol_syslog(__METHOD__.' - Skip: option LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT disabled', LOG_DEBUG);
			return 0;
		}

		if ($action === 'commande' && GETPOST('confirm', 'alpha') === 'no') {
			dol_syslog(__METHOD__.' - Convert cancel flow to confirm_commande with skip update', LOG_DEBUG);
			$_POST['action'] = 'confirm_commande';
			$_REQUEST['action'] = 'confirm_commande';
			$_POST['confirm'] = 'yes';
			$_REQUEST['confirm'] = 'yes';
			$_POST['dynamicsprices_skip_update'] = '1';
			$_REQUEST['dynamicsprices_skip_update'] = '1';
			$action = 'confirm_commande';
		}

		if ($action !== 'confirm_commande') {
			dol_syslog(__METHOD__.' - Skip: action is not confirm_commande', LOG_DEBUG);
			return 0;
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
		if (empty($selectedRows) && !empty($postedRowsData)) {
			foreach ($postedRowsData as $lineId => $unused) {
				$selectedRows[$lineId] = 1;
			}
		}

		$differences = $this->getOrderSupplierPriceDifferences($object);
		if (empty($differences)) {
			dol_syslog(__METHOD__.' - No supplier price difference found, nothing to update', LOG_DEBUG);
			return 0;
		}
		dol_syslog(__METHOD__.' - Found '.count($differences).' differing line(s)', LOG_DEBUG);

		$updatedLines = 0;
		foreach ($differences as $lineId => $diff) {
			if (empty($selectedRows[$lineId])) {
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
		}

		if ($updatedLines > 0) {
			global $langs;
			$langs->load('dynamicsprices@dynamicsprices');
			setEventMessages($langs->trans('LMDB_SupplierPriceUpdatedCount', $updatedLines), null, 'mesgs');
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
		if (empty($differences)) {
			dol_syslog(__METHOD__.' - No supplier price difference found, native confirmation will be used', LOG_DEBUG);
			return 0;
		}
		dol_syslog(__METHOD__.' - Prepare modal for '.count($differences).' differing line(s)', LOG_DEBUG);

		$langs->load('dynamicsprices@dynamicsprices');
		$url = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;
		$url .= '&datecommande='.(int) GETPOST('datecommande', 'int');
		$url .= '&methode='.urlencode(GETPOST('methodecommande', 'alpha'));
		$url .= '&comment='.urlencode(GETPOST('comment', 'alphanohtml'));

		$html = '<div class="div-table-responsive">';
		$html .= '<table class="noborder centpercent">';
		$html .= '<tr class="liste_titre">';
		$html .= '<td>'.$langs->trans('LMDB_AddOrUpdate').'</td>';
		$html .= '<td>'.$langs->trans('Ref').'</td>';
		$html .= '<td class="right">'.$langs->trans('QtyMin').'</td>';
		$html .= '<td class="right">'.$langs->trans('LMDB_QuantityPackaging').'</td>';
		$html .= '<td class="right">'.$langs->trans('VATRate').'</td>';
		$html .= '<td class="right">'.$langs->trans('LMDB_MinQtyPriceHT').'</td>';
		$html .= '<td class="right">'.$langs->trans('Discount').'</td>';
		$html .= '<td class="right">'.$langs->trans('DeliveryDelay').'</td>';
		$html .= '<td class="right">'.$langs->trans('LMDB_SupplierReputation').'</td>';
		$html .= '</tr>';

		foreach ($differences as $lineId => $diff) {
			$html .= '<tr class="oddeven">';
			$html .= '<td><input type="checkbox" name="dynamicsprices_apply_line['.$lineId.']" value="1" checked></td>';
			$html .= '<td><input class="minwidth75" type="text" name="dynamicsprices_data['.$lineId.'][ref]" value="'.dol_escape_htmltag($diff['ref']).'" readonly></td>';
			$html .= '<td class="right"><input class="right width75" type="text" name="dynamicsprices_data['.$lineId.'][qty]" value="'.dol_escape_htmltag((string) $diff['qty']).'"></td>';
			$html .= '<td class="right"><input class="right width75" type="text" name="dynamicsprices_data['.$lineId.'][unitquantity]" value="'.dol_escape_htmltag((string) $diff['unitquantity']).'"></td>';
			$html .= '<td class="right"><input class="right width75" type="text" name="dynamicsprices_data['.$lineId.'][vat]" value="'.dol_escape_htmltag((string) $diff['vat']).'"></td>';
			$html .= '<td class="right"><input class="right width75" type="text" name="dynamicsprices_data['.$lineId.'][unitprice]" value="'.dol_escape_htmltag((string) $diff['unitprice']).'"></td>';
			$html .= '<td class="right"><input class="right width75" type="text" name="dynamicsprices_data['.$lineId.'][discount]" value="'.dol_escape_htmltag((string) $diff['discount']).'"></td>';
			$html .= '<td class="right"><input class="right width75" type="text" name="dynamicsprices_data['.$lineId.'][delivery_time_days]" value="'.dol_escape_htmltag((string) $diff['delivery_time_days']).'"></td>';
			$html .= '<td class="right"><input class="right width75" type="text" name="dynamicsprices_data['.$lineId.'][supplier_reputation]" value="'.dol_escape_htmltag((string) $diff['supplier_reputation']).'"></td>';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][fk_product]" value="'.((int) $diff['fk_product']).'">';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][fk_soc]" value="'.((int) $diff['fk_soc']).'">';
			$html .= '<input type="hidden" name="dynamicsprices_data['.$lineId.'][current_rowid]" value="'.((int) $diff['current_rowid']).'">';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$html .= '</div>';

		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
		$form = new Form($this->db);
		$formquestion = array(
			array('type' => 'other', 'name' => 'dynamicsprices_diff_table', 'label' => '', 'value' => $html),
		);

		$ignoreUrl = $url.'&action=confirm_commande&confirm=yes&dynamicsprices_skip_update=1&token='.newToken();
		$this->resprints = $form->formconfirm($url, $langs->trans('LMDB_SupplierPriceModalTitle'), $langs->trans('LMDB_SupplierPriceModalDescription'), 'confirm_commande', $formquestion, 1, 1, 600, '90%', '', $langs->trans('Validate'), $langs->trans('LMDB_Ignore'));
		$this->resprints .= '<script>';
		$this->resprints .= 'jQuery(function($){';
		$this->resprints .= '$(document).on("click", ".ui-dialog-titlebar-close, .ui-dialog-buttonset .ui-button", function(){';
		$this->resprints .= 'var txt=$.trim($(this).text());';
		$this->resprints .= 'if (txt === "'.$langs->transnoentitiesnoconv('LMDB_Ignore').'" || $(this).hasClass("ui-dialog-titlebar-close")) { window.location.href = "'.$ignoreUrl.'"; }';
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
	 * @return array<int,array<string,mixed>>
	 */
	private function getOrderSupplierPriceDifferences($object)
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
			$delivery = isset($line->fk_availability) ? (int) $line->fk_availability : 0;
			$reputation = isset($line->supplier_reputation) ? price2num((float) $line->supplier_reputation, 'MS') : 0;

			$current = $this->getCurrentSupplierPrice((int) $line->fk_product, (int) $object->socid, $qty);
			$isDifferent = empty($current)
				|| price2num((float) $current['unitprice'], 'MS') !== $unitprice
				|| price2num((float) $current['tva_tx'], 'MS') !== $vat
				|| price2num((float) $current['remise_percent'], 'MS') !== $discount
				|| (int) $current['fk_availability'] !== $delivery
				|| price2num((float) $current['supplier_reputation'], 'MS') !== $reputation;

			if (!$isDifferent) {
				continue;
			}

			$differences[(int) $line->id] = array(
				'lineid' => (int) $line->id,
				'fk_product' => (int) $line->fk_product,
				'fk_soc' => (int) $object->socid,
				'qty' => $qty,
				'unitquantity' => $unitquantity,
				'vat' => $vat,
				'unitprice' => $unitprice,
				'discount' => $discount,
				'delivery_time_days' => $delivery,
				'supplier_reputation' => $reputation,
				'current_rowid' => !empty($current['rowid']) ? (int) $current['rowid'] : 0,
				'ref' => isset($line->ref) ? $line->ref : '',
				'label' => isset($line->product_label) ? $line->product_label : (isset($line->desc) ? $line->desc : ''),
			);
		}
		dol_syslog(__METHOD__.' - Comparison completed with '.count($differences).' difference(s)', LOG_DEBUG);

		return $differences;
	}

	/**
	 * Return current supplier price line for a product/supplier/minimum quantity.
	 *
	 * @param int $fkProduct Product id
	 * @param int $fkSoc Supplier thirdparty id
	 * @param float $qty Minimum quantity
	 * @return array<string,mixed>
	 */
	private function getCurrentSupplierPrice($fkProduct, $fkSoc, $qty)
	{
		global $conf;
		$sql = 'SELECT rowid, unitprice, tva_tx, remise_percent, fk_availability, supplier_reputation';
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
			'supplier_reputation' => (float) $obj->supplier_reputation,
		);
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
	 * Insert or update supplier price line from a difference.
	 *
	 * @param array<string,mixed> $diff Difference payload
	 * @return int
	 */
	private function upsertSupplierPriceFromDiff(array $diff)
	{
		global $conf, $user;

		if (!empty($diff['current_rowid'])) {
			dol_syslog(__METHOD__.' - Update existing supplier price rowid='.$diff['current_rowid'], LOG_DEBUG);
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_fournisseur_price';
			$sql .= ' SET unitprice = '.price2num((float) $diff['unitprice'], 'MS');
			$sql .= ', price = '.price2num((float) $diff['unitprice'], 'MS');
			$sql .= ', tva_tx = '.price2num((float) $diff['vat'], 'MS');
			$sql .= ', remise_percent = '.price2num((float) $diff['discount'], 'MS');
			$sql .= ', fk_availability = '.((int) $diff['delivery_time_days']);
			$sql .= ', supplier_reputation = '.price2num((float) $diff['supplier_reputation'], 'MS');
			$sql .= ', fk_user = '.((int) $user->id);
			$sql .= ', tms = '.$this->db->idate(dol_now());
			$sql .= ' WHERE rowid = '.((int) $diff['current_rowid']);
			$sql .= ' AND entity = '.((int) $conf->entity);
		} else {
			dol_syslog(__METHOD__.' - Insert new supplier price for product='.$diff['fk_product'].' supplier='.$diff['fk_soc'], LOG_DEBUG);
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_fournisseur_price(';
			$sql .= 'entity, fk_product, fk_soc, quantity, unitquantity, unitprice, price, tva_tx, remise_percent, fk_availability, supplier_reputation, fk_user, datec';
			$sql .= ') VALUES (';
			$sql .= ((int) $conf->entity).', ';
			$sql .= ((int) $diff['fk_product']).', ';
			$sql .= ((int) $diff['fk_soc']).', ';
			$sql .= price2num((float) $diff['qty'], 'MS').', ';
			$sql .= price2num((float) $diff['unitquantity'], 'MS').', ';
			$sql .= price2num((float) $diff['unitprice'], 'MS').', ';
			$sql .= price2num((float) $diff['unitprice'], 'MS').', ';
			$sql .= price2num((float) $diff['vat'], 'MS').', ';
			$sql .= price2num((float) $diff['discount'], 'MS').', ';
			$sql .= ((int) $diff['delivery_time_days']).', ';
			$sql .= price2num((float) $diff['supplier_reputation'], 'MS').', ';
			$sql .= ((int) $user->id).', ';
			$sql .= $this->db->idate(dol_now());
			$sql .= ')';
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__.' - SQL error: '.$this->error, LOG_ERR);
			return -1;
		}
		dol_syslog(__METHOD__.' - SQL upsert successful', LOG_DEBUG);

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
		$diff['delivery_time_days'] = isset($rowData['delivery_time_days']) ? (int) $rowData['delivery_time_days'] : $diff['delivery_time_days'];
		$diff['supplier_reputation'] = isset($rowData['supplier_reputation']) ? price2num($rowData['supplier_reputation'], 'MS') : $diff['supplier_reputation'];
		$diff['fk_product'] = isset($rowData['fk_product']) ? (int) $rowData['fk_product'] : $diff['fk_product'];
		$diff['fk_soc'] = isset($rowData['fk_soc']) ? (int) $rowData['fk_soc'] : $diff['fk_soc'];
		$diff['current_rowid'] = isset($rowData['current_rowid']) ? (int) $rowData['current_rowid'] : $diff['current_rowid'];
		$diff['label'] = isset($rowData['label']) ? $rowData['label'] : $diff['label'];

		return $diff;
	}
}
