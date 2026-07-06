<?php
/* Copyright (C) 2026		Pierre Ardoin		<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once __DIR__.'/dynamicpricescostservice.class.php';

/**
 * DynamicPrices cost REST API.
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class DynamicsPricesCostApi extends DolibarrApi
{
	/** @var DoliDB */
	public $db;

	/** @var DynamicPricesCostService */
	private $service;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $db;

		$this->db = $db;
		$this->service = new DynamicPricesCostService($db);
	}

	/**
	 * Get current DynamicPrices cost for a product.
	 *
	 * @url GET /costs/{product_id}
	 *
	 * @param int $product_id Product id
	 * @return array<string,mixed>
	 *
	 * @throws RestException
	 */
	public function getCost($product_id)
	{
		$this->checkAccess('read');

		$record = $this->service->getDynamicCostRecord((int) $product_id);
		if (!is_object($record)) {
			throw new RestException(404, 'DynamicPrices cost not found');
		}

		return $this->recordToArray($record);
	}

	/**
	 * Get DynamicPrices cost history for a product.
	 *
	 * @url GET /costs/{product_id}/history
	 *
	 * @param int $product_id Product id
	 * @return array<int,array<string,mixed>>
	 *
	 * @throws RestException
	 */
	public function getCostHistory($product_id)
	{
		global $conf;

		$this->checkAccess('history');

		$sql = "SELECT rowid, entity, fk_product, old_dynamic_cost_price, new_dynamic_cost_price, source_type, source_value, rule_code, coefficient, calculation_context, diff_abs, diff_percent, date_creation, fk_user_author";
		$sql .= " FROM ".MAIN_DB_PREFIX."dynamicprices_product_cost_log";
		$sql .= " WHERE fk_product = ".((int) $product_id);
		$sql .= " AND entity = ".((int) $conf->entity);
		$sql .= " ORDER BY date_creation DESC, rowid DESC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}

		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = $this->recordToArray($obj);
		}

		return $rows;
	}

	/**
	 * Recalculate one product cost.
	 *
	 * @url POST /costs/{product_id}/recalculate
	 *
	 * @param int $product_id Product id
	 * @return array<string,mixed>
	 *
	 * @throws RestException
	 */
	public function recalculateCost($product_id)
	{
		global $user;

		$this->checkAccess('write');

		$result = $this->service->recalculateProductCost((int) $product_id, $user, array('calculation_context' => 'api'));
		if ($result < 0) {
			throw new RestException(500, $this->service->error);
		}

		$record = $this->service->getDynamicCostRecord((int) $product_id);
		return is_object($record) ? $this->recordToArray($record) : array();
	}

	/**
	 * Recalculate several product costs.
	 *
	 * @url POST /costs/recalculate
	 *
	 * @return array<string,mixed>
	 *
	 * @throws RestException
	 */
	public function recalculateCosts()
	{
		global $user;

		$this->checkAccess('massupdate');

		$productIds = isset($this->request_data['product_ids']) && is_array($this->request_data['product_ids']) ? $this->request_data['product_ids'] : array();
		if (empty($productIds)) {
			throw new RestException(400, 'product_ids is required');
		}

		$updated = 0;
		foreach ($productIds as $productId) {
			$result = $this->service->recalculateProductCost((int) $productId, $user, array('calculation_context' => 'api_mass'));
			if ($result < 0) {
				throw new RestException(500, $this->service->error);
			}
			$updated++;
		}

		return array('updated' => $updated);
	}

	/**
	 * Set manual DynamicPrices cost.
	 *
	 * @url POST /costs/{product_id}/manual
	 *
	 * @param int $product_id Product id
	 * @return array<string,mixed>
	 *
	 * @throws RestException
	 */
	public function setManualCost($product_id)
	{
		global $conf, $user;

		$this->checkAccess('override');

		if (!isset($this->request_data['dynamic_cost_price']) || !is_numeric($this->request_data['dynamic_cost_price'])) {
			throw new RestException(400, 'dynamic_cost_price is required');
		}

		$cost = price2num($this->request_data['dynamic_cost_price'], 'MU');
		$hashPayload = json_encode(array('product' => (int) $product_id, 'source' => 'manual', 'value' => $cost));
		$calculation = array(
			'entity' => (int) $conf->entity,
			'fk_product' => (int) $product_id,
			'dynamic_cost_price' => (float) $cost,
			'price_base_type' => 'HT',
			'source_type' => 'manual',
			'source_value' => (float) $cost,
			'source_details' => '',
			'rule_code' => '',
			'coefficient' => 1,
			'rounding_rule' => 'dolibarr',
			'calculation_hash' => hash('sha256', is_string($hashPayload) ? $hashPayload : ''),
			'calculation_status' => 1,
			'calculation_message' => 'DynamicPricesCostCalculated',
			'status' => 1,
		);

		$result = $this->service->saveProductCost((int) $product_id, $calculation, $user, array('calculation_context' => 'api_manual'));
		if ($result < 0) {
			throw new RestException(500, $this->service->error);
		}

		$record = $this->service->getDynamicCostRecord((int) $product_id);
		return is_object($record) ? $this->recordToArray($record) : array();
	}

	/**
	 * Remove manual DynamicPrices cost.
	 *
	 * @url DELETE /costs/{product_id}/manual
	 *
	 * @param int $product_id Product id
	 * @return array<string,mixed>
	 *
	 * @throws RestException
	 */
	public function deleteManualCost($product_id)
	{
		global $conf, $user;

		$this->checkAccess('override');

		$calculation = array(
			'entity' => (int) $conf->entity,
			'fk_product' => (int) $product_id,
			'dynamic_cost_price' => null,
			'price_base_type' => 'HT',
			'source_type' => 'manual',
			'source_value' => null,
			'source_details' => '',
			'rule_code' => '',
			'coefficient' => null,
			'rounding_rule' => 'dolibarr',
			'calculation_hash' => '',
			'calculation_status' => 0,
			'calculation_message' => 'DynamicPricesManualCostRemoved',
			'status' => 0,
		);

		$result = $this->service->saveProductCost((int) $product_id, $calculation, $user, array('calculation_context' => 'api_manual_delete', 'allow_null_overwrite' => true));
		if ($result < 0) {
			throw new RestException(500, $this->service->error);
		}

		return array('deleted' => true);
	}

	/**
	 * Check module and permission.
	 *
	 * @param string $right Right action
	 * @return void
	 *
	 * @throws RestException
	 */
	private function checkAccess($right)
	{
		global $user;

		if (!isModEnabled('dynamicsprices')) {
			throw new RestException(403, 'DynamicPrices module is disabled');
		}
		if (empty($user->admin) && !$user->hasRight('dynamicsprices', 'cost', $right)) {
			throw new RestException(403, 'Forbidden');
		}
	}

	/**
	 * Convert database record to API array.
	 *
	 * @param stdClass $record Record
	 * @return array<string,mixed>
	 */
	private function recordToArray($record)
	{
		$json = json_encode($record);
		if (!is_string($json)) {
			return array();
		}

		$data = json_decode($json, true);
		return is_array($data) ? $data : array();
	}
}
