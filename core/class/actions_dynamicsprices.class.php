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
		if (empty($parameters['context']) || strpos($parameters['context'], 'ordersuppliercard') === false) {
			return 0;
		}

		if (!getDolGlobalInt('LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT')) {
			return 0;
		}

		if ($action !== 'confirm_commande') {
			return 0;
		}

		$selectedRows = GETPOST('dynamicsprices_apply_line', 'array');
		if (!is_array($selectedRows)) {
			$selectedRows = array();
		}

		$differences = $this->getOrderSupplierPriceDifferences($object);
		if (empty($differences)) {
			return 0;
		}

		$updatedLines = 0;
		foreach ($differences as $lineId => $diff) {
			if (empty($selectedRows[$lineId])) {
				continue;
			}

			$res = $this->upsertSupplierPriceFromDiff($diff);
			if ($res < 0) {
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

		if (empty($parameters['context']) || strpos($parameters['context'], 'ordersuppliercard') === false) {
			return 0;
		}

		if (!getDolGlobalInt('LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT')) {
			return 0;
		}

		if ($action !== 'commande') {
			return 0;
		}

		$differences = $this->getOrderSupplierPriceDifferences($object);
		if (empty($differences)) {
			return 0;
		}

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
		$html .= '<td>'.$langs->trans('Description').'</td>';
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
			$html .= '<td>'.dol_escape_htmltag($diff['ref']).'</td>';
			$html .= '<td>'.dol_escape_htmltag($diff['label']).'</td>';
			$html .= '<td class="right">'.price($diff['qty']).'</td>';
			$html .= '<td class="right">'.price($diff['unitquantity']).'</td>';
			$html .= '<td class="right">'.price($diff['vat']).'</td>';
			$html .= '<td class="right">'.price($diff['unitprice']).'</td>';
			$html .= '<td class="right">'.price($diff['discount']).'</td>';
			$html .= '<td class="right">'.((int) $diff['delivery_time_days']).'</td>';
			$html .= '<td class="right">'.price($diff['supplier_reputation']).'</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$html .= '</div>';

		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
		$form = new Form($this->db);
		$formquestion = array(
			array('type' => 'other', 'name' => 'dynamicsprices_diff_table', 'label' => '', 'value' => $html),
		);

		$this->resPrint = $form->formconfirm($url, $langs->trans('LMDB_SupplierPriceModalTitle'), $langs->trans('LMDB_SupplierPriceModalDescription'), 'confirm_commande', $formquestion, 1, 1, 600, '90%');
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
			return $differences;
		}

		if (empty($object->lines) || !is_array($object->lines)) {
			$object->fetch_lines();
		}

		foreach ($object->lines as $line) {
			if (empty($line->fk_product)) {
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
			return -1;
		}

		return 1;
	}
}
