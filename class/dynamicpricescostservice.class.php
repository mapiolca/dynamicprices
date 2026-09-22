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
 * Service layer for DynamicPrices cost prices.
 */
class DynamicPricesCostService
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

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
	 * Read current DynamicPrices cost amount for a product and entity.
	 *
	 * @param int|string $fk_product Product id
	 * @param int        $entity     Entity id, current entity when 0
	 * @param array<string,mixed> $options Options
	 * @return float|null
	 */
	public function getDynamicCostPrice($fk_product, $entity = 0, $options = array())
	{
		$record = $this->getDynamicCostRecord($fk_product, $entity);
		if (!is_object($record) || $record->dynamic_cost_price === null) {
			return null;
		}
		$requireSuccessfulCalculation = !array_key_exists('require_success', $options) || !empty($options['require_success']);
		if ($requireSuccessfulCalculation && ((int) $record->calculation_status <= 0 || (int) $record->status <= 0)) {
			return null;
		}

		return (float) $record->dynamic_cost_price;
	}

	/**
	 * Read current DynamicPrices cost row.
	 *
	 * @param int|string $fk_product Product id
	 * @param int        $entity     Entity id, current entity when 0
	 * @return stdClass|null
	 */
	public function getDynamicCostRecord($fk_product, $entity = 0)
	{
		$productId = (int) $fk_product;
		if ($productId <= 0) {
			return null;
		}

		$entity = $this->resolveEntity($entity);

		$sql = "SELECT rowid, entity, fk_product, dynamic_cost_price, price_base_type, currency_code, source_type, source_value, source_details, fk_rule, rule_code, coefficient, rounding_rule, calculation_hash, calculation_status, calculation_message, date_calculation, date_creation, fk_user_author, fk_user_modif, import_key, status";
		$sql .= " FROM ".MAIN_DB_PREFIX."dynamicprices_product_cost";
		$sql .= " WHERE fk_product = ".$productId;
		$sql .= " AND entity = ".$entity;
		$sql .= " ORDER BY date_calculation DESC, rowid DESC";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return null;
		}

		$obj = $this->db->fetch_object($resql);
		return is_object($obj) ? $obj : null;
	}

	/**
	 * Return effective cost according to module configuration and fallback.
	 *
	 * @param Product|stdClass $product Product object
	 * @param array<string,mixed> $context Context
	 * @return float|null
	 */
	public function getEffectiveCostPrice($product, $context = array())
	{
		if (!is_object($product)) {
			return null;
		}

		$productId = $this->getObjectIntProperty($product, array('id', 'rowid'));
		if ($productId <= 0) {
			return null;
		}

		$entity = !empty($context['entity']) ? (int) $context['entity'] : 0;
		$lineCurrentCost = array_key_exists('line_current_cost', $context) ? $context['line_current_cost'] : null;
		$resolution = $this->resolveCommercialLineCostFromPriority($productId, $product, $entity, $lineCurrentCost, $context);
		if ($resolution['error']) {
			return null;
		}
		return $resolution['cost'];
	}

	/**
	 * Return available commercial line cost source codes and translation keys.
	 *
	 * @param bool $includeUnavailable Include known sources for saved configuration
	 * @return array<string,string>
	 */
	public function getCommercialLineCostSourceOptions($includeUnavailable = false)
	{
		$options = array(
			'dynamicprices' => 'DynamicPricesCostLineSourceDynamicPrices',
			'dolibarr_default' => 'DynamicPricesCostLineSourceDolibarrDefault',
			'pmp' => 'DynamicPricesCostLineSourcePmp',
			'native_cost_price' => 'DynamicPricesCostLineSourceNativeCostPrice',
		);
		if ($includeUnavailable || $this->getPriceListAvailability()['available']) {
			$options['pricelist'] = 'DynamicPricesCostLineSourcePriceList';
		}
		return $options;
	}

	/**
	 * Check the optional PriceList contract without activating the module.
	 *
	 * @return array{available:bool,reason:string}
	 */
	public function getPriceListAvailability()
	{
		if (!isModEnabled('pricelist')) {
			return array('available' => false, 'reason' => 'DynamicPricesPriceListDisabled');
		}
		if (!defined('DOL_VERSION') || version_compare(DOL_VERSION, '20.0.0', '<') || version_compare(PHP_VERSION, '8.0.0', '<')) {
			return array('available' => false, 'reason' => 'DynamicPricesPriceListIncompatible');
		}
		dol_include_once('/pricelist/class/pricelist.class.php');
		if (!class_exists('PriceList') || !method_exists('PriceList', 'get_price') || !method_exists('PriceList', 'getEffectiveCostPriceForRow')) {
			return array('available' => false, 'reason' => 'DynamicPricesPriceListIncompatible');
		}
		$method = new ReflectionMethod('PriceList', 'get_price');
		$costMethod = new ReflectionMethod('PriceList', 'getEffectiveCostPriceForRow');
		if (!$method->isPublic() || $method->getNumberOfParameters() < 4 || $method->getNumberOfRequiredParameters() > 4
			|| !$costMethod->isPublic() || $costMethod->getNumberOfParameters() < 1 || $costMethod->getNumberOfRequiredParameters() > 1) {
			return array('available' => false, 'reason' => 'DynamicPricesPriceListIncompatible');
		}
		return array('available' => true, 'reason' => 'DynamicPricesPriceListAvailable');
	}

	/**
	 * Native sales documents supported by the AJAX endpoint and line triggers.
	 *
	 * @return array<string,array{class:string,file:string,line_table:string,parent_field:string}>
	 */
	public static function getCommercialDocumentTypes()
	{
		return array(
			'propal' => array('class' => 'Propal', 'file' => '/comm/propal/class/propal.class.php', 'line_table' => 'propaldet', 'parent_field' => 'fk_propal'),
			'commande' => array('class' => 'Commande', 'file' => '/commande/class/commande.class.php', 'line_table' => 'commandedet', 'parent_field' => 'fk_commande'),
			'facture' => array('class' => 'Facture', 'file' => '/compta/facture/class/facture.class.php', 'line_table' => 'facturedet', 'parent_field' => 'fk_facture'),
		);
	}

	/**
	 * Resolve the cost of the applicable PriceList row using its own business rules.
	 * Callers must authorize the loaded document, thirdparty and product first.
	 *
	 * @param Product|stdClass $product Authorized product
	 * @param CommonObject|stdClass|null $document Authorized native document with thirdparty loaded
	 * @param int|float|string|null $quantity Line quantity
	 * @return array{cost:float|null,error:bool}
	 */
	public function getPriceListCost($product, $document, $quantity)
	{
		$result = array('cost' => null, 'error' => false);
		if (!$this->getPriceListAvailability()['available']) {
			return $result;
		}
		if (!is_object($document) || empty($document->id) || empty($document->entity)
			|| !isset(self::getCommercialDocumentTypes()[$document->element ?? ''])
			|| !is_object($document->thirdparty ?? null) || empty($document->thirdparty->id)
			|| !is_object($product) || empty($product->id) || !is_numeric($quantity) || !is_finite((float) $quantity)) {
			$this->error = $this->trans('DynamicPricesCostInvalidLine');
			return array('cost' => null, 'error' => true);
		}
		$pricelist = new PriceList($this->db);
		$row = $pricelist->get_price((int) $product->id, $document->thirdparty, (float) $quantity, $document);
		if ($row === 0) {
			return $result;
		}
		if (!is_object($row)) {
			$this->error = $this->trans('DynamicPricesPriceListError');
			return array('cost' => null, 'error' => true);
		}
		$cost = $pricelist->getEffectiveCostPriceForRow($row);
		if ($cost !== null && (!is_numeric($cost) || !is_finite((float) $cost))) {
			$this->error = $this->trans('DynamicPricesPriceListError');
			return array('cost' => null, 'error' => true);
		}
		$result['cost'] = $cost === null ? null : (float) price2num($cost, 'MU');
		return $result;
	}

	/**
	 * Return configured priority for commercial line cost sources.
	 *
	 * @param string|null $configured Configured comma-separated value
	 * @param bool $includeUnavailable Preserve configured ranks even when PriceList is disabled
	 * @return array<int,string>
	 */
	public function getCommercialLineCostSourcePriority($configured = null, $includeUnavailable = false)
	{
		$defaultPriority = array('dynamicprices', 'dolibarr_default', 'pmp', 'native_cost_price');
		$configuredValue = $configured !== null ? (string) $configured : (string) getDolGlobalString('DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY', implode(',', $defaultPriority));
		if ($configuredValue === '') {
			return $defaultPriority;
		}

		$allowed = array_keys($this->getCommercialLineCostSourceOptions(true));
		$priority = array();
		foreach (explode(',', $configuredValue) as $source) {
			$source = trim((string) $source);
			if ($source !== '' && in_array($source, $allowed, true) && !in_array($source, $priority, true)) {
				$priority[] = $source;
			}
		}

		if (empty($priority)) {
			$priority = $defaultPriority;
		}
		if (!$includeUnavailable) {
			$priority = array_values(array_intersect($priority, array_keys($this->getCommercialLineCostSourceOptions())));
		}
		return $priority;
	}

	/**
	 * Calculate DynamicPrices cost for one product.
	 *
	 * @param int|string $fk_product Product id
	 * @param array<string,mixed> $context Context
	 * @return array<string,mixed>
	 */
	public function calculateProductCost($fk_product, $context = array())
	{
		global $conf;

		$productId = (int) $fk_product;
		$entity = !empty($context['entity']) ? (int) $context['entity'] : (int) $conf->entity;
		$result = $this->getEmptyCalculation($productId, $entity);

		if ($productId <= 0) {
			$result['calculation_status'] = -1;
			$result['calculation_message'] = 'DynamicPricesCostInvalidProduct';
			return $result;
		}

		dol_include_once('/product/class/product.class.php');
		$product = new Product($this->db);
		if ($product->fetch($productId) <= 0) {
			$result['calculation_status'] = -1;
			$result['calculation_message'] = 'DynamicPricesCostProductNotFound';
			return $result;
		}

		if ((int) $product->type === Product::TYPE_SERVICE && !getDolGlobalInt('DYNAMICPRICES_COST_INCLUDE_SERVICES', 0)) {
			$result['calculation_status'] = 0;
			$result['calculation_message'] = 'DynamicPricesCostServiceIgnored';
			return $result;
		}

		$productNativeValues = $this->getProductNativeCostValues($productId, $entity);
		$result['dolibarr_cost_price_snapshot'] = $productNativeValues['cost_price'];
		$result['pmp_snapshot'] = $productNativeValues['pmp'];

		$kitComponents = $this->getKitComponents($productId);
		if ($kitComponents === null) {
			$result['calculation_status'] = -1;
			$result['calculation_message'] = 'DynamicPricesCostKitComponentsReadError';
			$result['source_type'] = 'kit_components';
			$result['calculation_hash'] = $this->buildCalculationHash($result);
			return $result;
		}
		if (!empty($kitComponents)) {
			if (!getDolGlobalInt('DYNAMICPRICES_COST_RECALC_KITS', 1)) {
				$result['calculation_status'] = 0;
				$result['calculation_message'] = 'DynamicPricesCostKitRecalculationDisabled';
				$result['source_type'] = 'kit_components';
				$result['source_details'] = $this->encodeJson(array('components' => $kitComponents));
				$result['calculation_hash'] = $this->buildCalculationHash($result);
				return $result;
			}

			return $this->calculateKitCost($result, $kitComponents, $entity);
		}

		$supplierAveragePrice = $this->getSupplierAveragePrice($productId, $entity);
		$sourceDetails = array(
			array(
				'source_type' => 'supplier_average',
				'value' => $supplierAveragePrice,
			),
		);
		if ($supplierAveragePrice === null) {
			$result['calculation_status'] = -1;
			$result['calculation_message'] = 'DynamicPricesCostNoSource';
			$result['source_type'] = 'supplier_average';
			$result['source_details'] = $this->encodeJson(array('sources' => $sourceDetails));
			$result['calculation_hash'] = $this->buildCalculationHash($result);
			return $result;
		}

		$commercialCategory = $this->getProductCommercialCategory($productId);
		$marginPercent = $this->getMarginOnCostPercent($commercialCategory);
		$coefficient = 1 + (((float) $marginPercent) / 100);
		$rawCost = ((float) $supplierAveragePrice) * $coefficient;
		$roundedCost = $this->roundCost($rawCost);

		$result['dynamic_cost_price'] = $roundedCost;
		$result['source_type'] = 'supplier_average';
		$result['source_value'] = (float) $supplierAveragePrice;
		$result['source_details'] = $this->encodeJson(array('sources' => $sourceDetails, 'commercial_category' => $commercialCategory));
		$result['rule_code'] = $commercialCategory;
		$result['coefficient'] = $coefficient;
		$result['rounding_rule'] = (string) getDolGlobalString('DYNAMICPRICES_COST_ROUNDING_MODE', 'dolibarr');
		$result['calculation_status'] = 1;
		$result['calculation_message'] = 'DynamicPricesCostCalculated';
		$result['calculation_hash'] = $this->buildCalculationHash($result);

		return $result;
	}

	/**
	 * Save current cost and write history.
	 *
	 * @param int|string $fk_product Product id
	 * @param array<string,mixed> $calculation Calculation data
	 * @param User $user User
	 * @param array<string,mixed> $context Context
	 * @return int
	 */
	public function saveProductCost($fk_product, array $calculation, User $user, array $context = array())
	{
		$productId = (int) $fk_product;
		if ($productId <= 0) {
			$this->error = $this->trans('DynamicPricesCostInvalidProduct');
			$this->errors[] = $this->error;
			return -1;
		}

		$entity = !empty($calculation['entity']) ? (int) $calculation['entity'] : $this->resolveEntity(!empty($context['entity']) ? (int) $context['entity'] : 0);
		$oldRecord = $this->getDynamicCostRecord($productId, $entity);
		$dynamicCost = array_key_exists('dynamic_cost_price', $calculation) ? $calculation['dynamic_cost_price'] : null;

		$date = $this->db->idate(dol_now());
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dynamicprices_product_cost (";
		$sql .= "entity, fk_product, dynamic_cost_price, price_base_type, currency_code, source_type, source_value, source_details, fk_rule, rule_code, coefficient, rounding_rule, calculation_hash, calculation_status, calculation_message, date_calculation, date_creation, fk_user_author, fk_user_modif, import_key, status";
		$sql .= ") VALUES (";
		$sql .= $entity;
		$sql .= ", ".$productId;
		$sql .= ", ".$this->sqlFloatOrNull($dynamicCost);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['price_base_type']) ? (string) $calculation['price_base_type'] : 'HT');
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['currency_code']) ? (string) $calculation['currency_code'] : '');
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['source_type']) ? (string) $calculation['source_type'] : '');
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('source_value', $calculation) ? $calculation['source_value'] : null);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['source_details']) ? (string) $calculation['source_details'] : '');
		$sql .= ", ".$this->sqlIntOrNull(!empty($calculation['fk_rule']) ? (int) $calculation['fk_rule'] : null);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['rule_code']) ? (string) $calculation['rule_code'] : '');
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('coefficient', $calculation) ? $calculation['coefficient'] : null);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['rounding_rule']) ? (string) $calculation['rounding_rule'] : '');
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['calculation_hash']) ? (string) $calculation['calculation_hash'] : '');
		$sql .= ", ".(isset($calculation['calculation_status']) ? (int) $calculation['calculation_status'] : 1);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['calculation_message']) ? (string) $calculation['calculation_message'] : '');
		$sql .= ", '".$this->db->escape($date)."'";
		$sql .= ", '".$this->db->escape($date)."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ", ".((int) $user->id);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['import_key']) ? (string) $calculation['import_key'] : '');
		$sql .= ", ".(isset($calculation['status']) ? (int) $calculation['status'] : 1);
		$sql .= ")";
		$sql .= " ON DUPLICATE KEY UPDATE";
		$sql .= " dynamic_cost_price = VALUES(dynamic_cost_price)";
		$sql .= ", price_base_type = VALUES(price_base_type)";
		$sql .= ", currency_code = VALUES(currency_code)";
		$sql .= ", source_type = VALUES(source_type)";
		$sql .= ", source_value = VALUES(source_value)";
		$sql .= ", source_details = VALUES(source_details)";
		$sql .= ", fk_rule = VALUES(fk_rule)";
		$sql .= ", rule_code = VALUES(rule_code)";
		$sql .= ", coefficient = VALUES(coefficient)";
		$sql .= ", rounding_rule = VALUES(rounding_rule)";
		$sql .= ", calculation_hash = VALUES(calculation_hash)";
		$sql .= ", calculation_status = VALUES(calculation_status)";
		$sql .= ", calculation_message = VALUES(calculation_message)";
		$sql .= ", date_calculation = VALUES(date_calculation)";
		$sql .= ", fk_user_modif = VALUES(fk_user_modif)";
		$sql .= ", import_key = VALUES(import_key)";
		$sql .= ", status = VALUES(status)";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		$logResult = $this->logProductCostChange($productId, $oldRecord, $calculation, $user, $context);
		if ($logResult < 0) {
			return -1;
		}

		if (getDolGlobalInt('DYNAMICPRICES_COST_ALLOW_NATIVE_WRITE', 0) && $dynamicCost !== null && (int) ($calculation['calculation_status'] ?? 1) > 0 && (int) ($calculation['status'] ?? 1) > 0) {
			dol_syslog(__METHOD__.' legacy native cost write enabled for product='.(int) $productId, LOG_WARNING);
			$this->writeNativeCostPrice($productId, $dynamicCost, $entity);
		}

		return 1;
	}

	/**
	 * Recalculate and save current cost.
	 *
	 * @param int|string $fk_product Product id
	 * @param User $user User
	 * @param array<string,mixed> $context Context
	 * @return int 1 on success, 0 when no valid cost can be calculated, -1 on persistence error
	 */
	public function recalculateProductCost($fk_product, User $user, array $context = array())
	{
		$this->db->begin();

		$calculation = $this->calculateProductCost($fk_product, $context);
		$result = $this->saveProductCost($fk_product, $calculation, $user, $context);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return (int) ($calculation['calculation_status'] ?? 0) > 0 && $calculation['dynamic_cost_price'] !== null ? 1 : 0;
	}

	/**
	 * Write a log row for a calculation.
	 *
	 * @param int|string $fk_product Product id
	 * @param stdClass|null $oldRecord Previous record
	 * @param array<string,mixed> $calculation Calculation data
	 * @param User $user User
	 * @param array<string,mixed> $context Context
	 * @return int
	 */
	public function logProductCostChange($fk_product, $oldRecord, array $calculation, User $user, array $context = array())
	{
		$productId = (int) $fk_product;
		$entity = !empty($calculation['entity']) ? (int) $calculation['entity'] : $this->resolveEntity(!empty($context['entity']) ? (int) $context['entity'] : 0);
		$oldCost = is_object($oldRecord) && $oldRecord->dynamic_cost_price !== null ? (float) $oldRecord->dynamic_cost_price : null;
		$newCost = array_key_exists('dynamic_cost_price', $calculation) && $calculation['dynamic_cost_price'] !== null ? (float) $calculation['dynamic_cost_price'] : null;

		$logMode = (string) getDolGlobalString('DYNAMICPRICES_COST_LOG_MODE', 'changes_only');
		if ($logMode === 'changes_only' && $oldCost !== null && $newCost !== null && price2num($oldCost, 'MU') == price2num($newCost, 'MU') && (int) ($calculation['calculation_status'] ?? 1) === 1) {
			return 0;
		}

		$currentRecord = $this->getDynamicCostRecord($productId, $entity);
		$diffAbs = ($oldCost !== null && $newCost !== null) ? ($newCost - $oldCost) : null;
		$diffPercent = ($oldCost !== null && $oldCost != 0 && $newCost !== null) ? (($newCost - $oldCost) / $oldCost) * 100 : null;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dynamicprices_product_cost_log (";
		$sql .= "entity, fk_product, fk_product_cost, old_dynamic_cost_price, new_dynamic_cost_price, dolibarr_cost_price_snapshot, pmp_snapshot, source_type, source_value, source_details, fk_rule, rule_code, coefficient, rounding_rule, calculation_context, object_type_source, fk_object_source, diff_abs, diff_percent, date_creation, fk_user_author, note_private, import_key";
		$sql .= ") VALUES (";
		$sql .= $entity;
		$sql .= ", ".$productId;
		$sql .= ", ".$this->sqlIntOrNull(is_object($currentRecord) ? (int) $currentRecord->rowid : null);
		$sql .= ", ".$this->sqlFloatOrNull($oldCost);
		$sql .= ", ".$this->sqlFloatOrNull($newCost);
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('dolibarr_cost_price_snapshot', $calculation) ? $calculation['dolibarr_cost_price_snapshot'] : null);
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('pmp_snapshot', $calculation) ? $calculation['pmp_snapshot'] : null);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['source_type']) ? (string) $calculation['source_type'] : '');
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('source_value', $calculation) ? $calculation['source_value'] : null);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['source_details']) ? (string) $calculation['source_details'] : '');
		$sql .= ", ".$this->sqlIntOrNull(!empty($calculation['fk_rule']) ? (int) $calculation['fk_rule'] : null);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['rule_code']) ? (string) $calculation['rule_code'] : '');
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('coefficient', $calculation) ? $calculation['coefficient'] : null);
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['rounding_rule']) ? (string) $calculation['rounding_rule'] : '');
		$sql .= ", ".$this->sqlStringOrNull(!empty($context['calculation_context']) ? (string) $context['calculation_context'] : 'manual');
		$sql .= ", ".$this->sqlStringOrNull(!empty($context['object_type_source']) ? (string) $context['object_type_source'] : '');
		$sql .= ", ".$this->sqlIntOrNull(!empty($context['fk_object_source']) ? (int) $context['fk_object_source'] : null);
		$sql .= ", ".$this->sqlFloatOrNull($diffAbs);
		$sql .= ", ".$this->sqlFloatOrNull($diffPercent);
		$sql .= ", '".$this->db->escape($this->db->idate(dol_now()))."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ", ".$this->sqlStringOrNull(!empty($context['note_private']) ? (string) $context['note_private'] : '');
		$sql .= ", ".$this->sqlStringOrNull(!empty($calculation['import_key']) ? (string) $calculation['import_key'] : '');
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Apply the configured cost to a commercial line within its native transaction.
	 *
	 * @param string $element_type Line element type
	 * @param CommonObjectLine|stdClass $line Line object
	 * @param CommonObject|stdClass $object Parent object
	 * @param User $user User
	 * @param array<string,mixed> $context Context
	 * @return int
	 */
	public function applyCostToCommercialLine($element_type, &$line, &$object, User $user, array $context = array())
	{
		if (!getDolGlobalInt('DYNAMICPRICES_COST_USE_FOR_SALES', 0)) {
			return 0;
		}

		$strategy = (string) getDolGlobalString('DYNAMICPRICES_COST_LINE_STRATEGY', 'on_create_only');
		if ($strategy === 'never') {
			return 0;
		}
		if (!empty($context['line_action']) && (string) $context['line_action'] === 'update') {
			return 0;
		}
		if ($strategy === 'on_create_only' && !empty($context['line_action']) && $context['line_action'] !== 'create') {
			return 0;
		}
		if ($strategy === 'preserve_origin' && !empty($context['from_source_document'])) {
			return 0;
		}

		$productId = $this->getObjectIntProperty($line, array('fk_product', 'fk_product_fils', 'product_id'));
		if ($productId <= 0) {
			return 0;
		}

		if (!class_exists('Product')) {
			require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		}
		$product = new Product($this->db);
		if ($product->fetch($productId) <= 0) {
			$this->error = $this->trans('DynamicPricesCostProductNotFound');
			$this->errors[] = $this->error;
			return -1;
		}

		$entity = $this->getObjectIntProperty($object, array('entity'));
		if (empty($product->entity) || !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)) {
			$this->error = $this->trans('DynamicPricesCostProductNotFound');
			return -1;
		}
		$before = $this->getObjectFloatProperty($line, array('pa_ht'));
		$cost = null;
		$sourceType = '';
		if (!empty($context['cost_source_mode']) && (string) $context['cost_source_mode'] === 'manual' && $user->hasRight('margins', 'creer')) {
			if (($context['cost_source'] ?? '') === 'dynamicsprices_pricelist_cost') {
				$priceListResult = $this->getPriceListCost($product, $object, $line->qty ?? null);
				if ($priceListResult['error'] || $priceListResult['cost'] === null) {
					$this->error = $this->trans('DynamicPricesPriceListError');
					return -1;
				}
				$context['manual_cost'] = $priceListResult['cost'];
				$sourceType = 'pricelist';
			}
			if (!isset($context['manual_cost']) || $context['manual_cost'] === '') {
				return 0;
			}
			$manualCost = price2num($context['manual_cost'], '', is_int($context['manual_cost']) || is_float($context['manual_cost']) ? 1 : 2);
			if (!is_numeric($manualCost) || !is_finite((float) $manualCost)) {
				$this->error = $this->trans('DynamicPricesCostInvalidLine');
				return -1;
			}
			$cost = (float) $manualCost;
			$sourceType = $sourceType === 'pricelist' ? 'pricelist' : 'manual';
		}

		if ($sourceType === '') {
			$context['document'] = $object;
			$context['quantity'] = isset($line->qty) ? $line->qty : null;
			$resolution = $this->resolveCommercialLineCostFromPriority($productId, $product, $entity, $before, $context);
			if ($resolution['error']) {
				return -1;
			}
			$cost = $resolution['cost'];
			$sourceType = $resolution['source_type'];
		}
		if ($cost === null) {
			return 0;
		}

		$lineId = $this->getObjectIntProperty($line, array('id', 'rowid'));
		$line->pa_ht = price2num($cost, 'MU');

		if ($lineId > 0 && !empty($context['line_table'])) {
			$updateResult = $this->updateCommercialLinePaHt((string) $context['line_table'], $lineId, $cost, $this->getObjectIntProperty($object, array('id', 'rowid')), $entity);
			if ($updateResult < 0) {
				return -1;
			}
		}

		if ($lineId > 0) {
			$snapshotData = array(
				'entity' => $this->resolveEntity($entity),
				'fk_element' => $this->getObjectIntProperty($object, array('id', 'rowid')),
				'fk_product' => $productId,
				'dynamic_cost_price' => $cost,
				'native_pa_ht_before' => $before,
				'native_pa_ht_after' => $cost,
				'source_type' => $sourceType,
				'status' => 1,
			);
			if ($this->createLineCostSnapshot($element_type, $lineId, $snapshotData, $user) < 0) {
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Create or update a line cost snapshot.
	 *
	 * @param string $element_type Line element type
	 * @param int|string $fk_elementdet Line id
	 * @param array<string,mixed> $data Snapshot data
	 * @param User $user User
	 * @return int
	 */
	public function createLineCostSnapshot($element_type, $fk_elementdet, array $data, User $user)
	{
		$lineId = (int) $fk_elementdet;
		if ($lineId <= 0 || $element_type === '') {
			$this->error = $this->trans('DynamicPricesCostInvalidLine');
			$this->errors[] = $this->error;
			return -1;
		}

		$entity = $this->resolveEntity(!empty($data['entity']) ? (int) $data['entity'] : 0);
		$date = $this->db->idate(dol_now());

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."dynamicprices_line_cost_snapshot (";
		$sql .= "entity, element_type, fk_elementdet, fk_element, fk_product, dynamic_cost_price, native_pa_ht_before, native_pa_ht_after, fk_product_cost, fk_product_cost_log, source_type, rule_code, calculation_hash, date_creation, fk_user_author, status";
		$sql .= ") VALUES (";
		$sql .= $entity;
		$sql .= ", ".$this->sqlStringOrNull($element_type);
		$sql .= ", ".$lineId;
		$sql .= ", ".$this->sqlIntOrNull(!empty($data['fk_element']) ? (int) $data['fk_element'] : null);
		$sql .= ", ".$this->sqlIntOrNull(!empty($data['fk_product']) ? (int) $data['fk_product'] : null);
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('dynamic_cost_price', $data) ? $data['dynamic_cost_price'] : null);
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('native_pa_ht_before', $data) ? $data['native_pa_ht_before'] : null);
		$sql .= ", ".$this->sqlFloatOrNull(array_key_exists('native_pa_ht_after', $data) ? $data['native_pa_ht_after'] : null);
		$sql .= ", ".$this->sqlIntOrNull(!empty($data['fk_product_cost']) ? (int) $data['fk_product_cost'] : null);
		$sql .= ", ".$this->sqlIntOrNull(!empty($data['fk_product_cost_log']) ? (int) $data['fk_product_cost_log'] : null);
		$sql .= ", ".$this->sqlStringOrNull(!empty($data['source_type']) ? (string) $data['source_type'] : '');
		$sql .= ", ".$this->sqlStringOrNull(!empty($data['rule_code']) ? (string) $data['rule_code'] : '');
		$sql .= ", ".$this->sqlStringOrNull(!empty($data['calculation_hash']) ? (string) $data['calculation_hash'] : '');
		$sql .= ", '".$this->db->escape($date)."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ", ".(isset($data['status']) ? (int) $data['status'] : 1);
		$sql .= ")";
		$sql .= " ON DUPLICATE KEY UPDATE";
		$sql .= " fk_element = VALUES(fk_element)";
		$sql .= ", fk_product = VALUES(fk_product)";
		$sql .= ", dynamic_cost_price = VALUES(dynamic_cost_price)";
		$sql .= ", native_pa_ht_before = VALUES(native_pa_ht_before)";
		$sql .= ", native_pa_ht_after = VALUES(native_pa_ht_after)";
		$sql .= ", fk_product_cost = VALUES(fk_product_cost)";
		$sql .= ", fk_product_cost_log = VALUES(fk_product_cost_log)";
		$sql .= ", source_type = VALUES(source_type)";
		$sql .= ", rule_code = VALUES(rule_code)";
		$sql .= ", calculation_hash = VALUES(calculation_hash)";
		$sql .= ", fk_user_author = VALUES(fk_user_author)";
		$sql .= ", status = VALUES(status)";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Return empty calculation structure.
	 *
	 * @param int $productId Product id
	 * @param int $entity Entity id
	 * @return array<string,mixed>
	 */
	private function getEmptyCalculation($productId, $entity)
	{
		return array(
			'entity' => (int) $entity,
			'fk_product' => (int) $productId,
			'dynamic_cost_price' => null,
			'price_base_type' => 'HT',
			'currency_code' => '',
			'source_type' => '',
			'source_value' => null,
			'source_details' => '',
			'fk_rule' => null,
			'rule_code' => '',
			'coefficient' => null,
			'rounding_rule' => (string) getDolGlobalString('DYNAMICPRICES_COST_ROUNDING_MODE', 'dolibarr'),
			'calculation_hash' => '',
			'calculation_status' => 0,
			'calculation_message' => '',
			'dolibarr_cost_price_snapshot' => null,
			'pmp_snapshot' => null,
			'status' => 1,
		);
	}

	/**
	 * Resolve current entity.
	 *
	 * @param int $entity Requested entity
	 * @return int
	 */
	private function resolveEntity($entity)
	{
		global $conf;

		$entity = (int) $entity;
		return $entity > 0 ? $entity : (int) $conf->entity;
	}

	/**
	 * Read native product cost values.
	 *
	 * @param int $productId Product id
	 * @param int $entity Entity id
	 * @return array{cost_price:float|null,pmp:float|null}
	 */
	private function getProductNativeCostValues($productId, $entity)
	{
		$sql = "SELECT cost_price, pmp";
		$sql .= " FROM ".MAIN_DB_PREFIX."product";
		$sql .= " WHERE rowid = ".((int) $productId);
		$sql .= " AND entity IN (".getEntity('product').")";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return array('cost_price' => null, 'pmp' => null);
		}

		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			return array('cost_price' => null, 'pmp' => null);
		}

		return array(
			'cost_price' => $obj->cost_price !== null ? (float) $obj->cost_price : null,
			'pmp' => $obj->pmp !== null ? (float) $obj->pmp : null,
		);
	}

	/**
	 * Return average valid supplier unit price.
	 *
	 * @param int $productId Product id
	 * @param int $entity Entity id, current entity when 0
	 * @return float|null
	 */
	public function getSupplierAveragePrice($productId, $entity = 0)
	{
		global $conf;

		$productId = (int) $productId;
		if ($productId <= 0) {
			return null;
		}

		$entity = $this->resolveEntity($entity);
		$entityFilter = $entity === (int) $conf->entity ? getEntity('product_fournisseur_price') : (string) $entity;
		$sql = "SELECT p.unitprice";
		$sql .= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price AS p";
		$sql .= " WHERE p.fk_product = ".$productId;
		$sql .= " AND p.entity IN (".$entityFilter.")";
		$sql .= " AND p.status = 1";
		$sql .= " AND p.quantity > 0";
		$sql .= " AND p.unitprice IS NOT NULL";
		$sql .= " AND TRIM(COALESCE(p.ref_fourn, '')) <> ''";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}

		$total = 0;
		$count = 0;
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$total += (float) $obj->unitprice;
			$count++;
		}

		return $count > 0 ? ($total / $count) : null;
	}

	/**
	 * Count incomplete supplier price rows ignored by DynamicPrices.
	 *
	 * @param int $productId Product id
	 * @param int $entity Entity id, current entity when 0
	 * @return int
	 */
	public function getInvalidSupplierPriceCount($productId, $entity = 0)
	{
		global $conf;

		$productId = (int) $productId;
		if ($productId <= 0) {
			return 0;
		}

		$entity = $this->resolveEntity($entity);
		$entityFilter = $entity === (int) $conf->entity ? getEntity('product_fournisseur_price') : (string) $entity;
		$sql = "SELECT COUNT(p.rowid) AS nb";
		$sql .= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price AS p";
		$sql .= " WHERE p.fk_product = ".$productId;
		$sql .= " AND p.entity IN (".$entityFilter.")";
		$sql .= " AND p.status = 1";
		$sql .= " AND (p.quantity <= 0 OR p.unitprice IS NULL OR TRIM(COALESCE(p.ref_fourn, '')) = '')";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		return is_object($obj) && isset($obj->nb) ? (int) $obj->nb : 0;
	}

	/**
	 * Read first-level components of a kit.
	 *
	 * @param int $productId Parent product id
	 * @return array<int,array{id:int,ref:string,qty:float}>|null
	 */
	private function getKitComponents($productId)
	{
		$components = array();
		$sql = "SELECT pa.fk_product_fils, pa.qty, p.ref";
		$sql .= " FROM ".MAIN_DB_PREFIX."product_association AS pa";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = pa.fk_product_fils";
		$sql .= " WHERE pa.fk_product_pere = ".((int) $productId);
		$sql .= " ORDER BY pa.rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return null;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$components[] = array(
				'id' => (int) $obj->fk_product_fils,
				'ref' => isset($obj->ref) ? (string) $obj->ref : '',
				'qty' => (float) price2num($obj->qty, 'MS'),
			);
		}

		return $components;
	}

	/**
	 * Calculate a kit from the current successful DynamicPrices costs of its components.
	 *
	 * @param array<string,mixed> $result Base calculation result
	 * @param array<int,array{id:int,ref:string,qty:float}> $components Kit components
	 * @param int $entity Entity id
	 * @return array<string,mixed>
	 */
	private function calculateKitCost(array $result, array $components, $entity)
	{
		$componentDetails = array();
		$missingComponents = array();
		$totalCost = 0.0;

		foreach ($components as $component) {
			$componentId = (int) $component['id'];
			$componentRef = (string) $component['ref'];
			$quantity = (float) $component['qty'];
			$record = $componentId > 0 && $componentId !== (int) $result['fk_product'] ? $this->getDynamicCostRecord($componentId, $entity) : null;
			$componentCost = null;
			if (is_object($record) && $record->dynamic_cost_price !== null && (int) $record->calculation_status > 0 && (int) $record->status > 0) {
				$componentCost = (float) $record->dynamic_cost_price;
			}

			$subtotal = $componentCost !== null ? $componentCost * $quantity : null;
			$componentDetails[] = array(
				'fk_product' => $componentId,
				'ref' => $componentRef,
				'quantity' => $quantity,
				'dynamic_cost_price' => $componentCost,
				'subtotal' => $subtotal,
			);

			if ($componentCost === null) {
				$missingComponents[] = array('fk_product' => $componentId, 'ref' => $componentRef);
				continue;
			}

			if ($subtotal !== null) {
				$totalCost += $subtotal;
			}
		}

		$result['source_type'] = 'kit_components';
		$result['source_details'] = $this->encodeJson(array(
			'components' => $componentDetails,
			'missing_components' => $missingComponents,
		));
		$result['coefficient'] = 1.0;
		$result['rounding_rule'] = (string) getDolGlobalString('DYNAMICPRICES_COST_ROUNDING_MODE', 'dolibarr');

		if (!empty($missingComponents)) {
			$result['calculation_status'] = -1;
			$result['calculation_message'] = 'DynamicPricesCostKitComponentUnavailable';
			$result['calculation_hash'] = $this->buildCalculationHash($result);
			return $result;
		}

		$result['source_value'] = (float) $totalCost;
		$result['dynamic_cost_price'] = $this->roundCost($totalCost);
		$result['calculation_status'] = 1;
		$result['calculation_message'] = 'DynamicPricesCostKitCalculated';
		$result['calculation_hash'] = $this->buildCalculationHash($result);

		return $result;
	}

	/**
	 * Return product commercial category code.
	 *
	 * @param int $productId Product id
	 * @return string
	 */
	private function getProductCommercialCategory($productId)
	{
		dol_include_once('/dynamicsprices/lib/dynamicsprices.lib.php');
		if (function_exists('dynamicsprices_get_product_commercial_category')) {
			return (string) dynamicsprices_get_product_commercial_category($this->db, $productId);
		}

		return '';
	}

	/**
	 * Return margin percent for a commercial category.
	 *
	 * @param string $commercialCategory Commercial category code
	 * @return float
	 */
	private function getMarginOnCostPercent($commercialCategory)
	{
		dol_include_once('/dynamicsprices/lib/dynamicsprices.lib.php');
		if (function_exists('dynamicsprices_get_margin_on_cost_percent')) {
			return (float) dynamicsprices_get_margin_on_cost_percent($this->db, $commercialCategory);
		}

		return 0;
	}

	/**
	 * Apply configured rounding.
	 *
	 * @param float $value Raw cost
	 * @return float
	 */
	private function roundCost($value)
	{
		$roundingMode = (string) getDolGlobalString('DYNAMICPRICES_COST_ROUNDING_MODE', 'dolibarr');
		if ($roundingMode === 'none') {
			return (float) $value;
		}

		return (float) price2num($value, 'MU');
	}

	/**
	 * Build deterministic hash from calculation data.
	 *
	 * @param array<string,mixed> $calculation Calculation data
	 * @return string
	 */
	private function buildCalculationHash(array $calculation)
	{
		$payload = array(
			'dynamic_cost_price' => $calculation['dynamic_cost_price'] ?? null,
			'source_type' => $calculation['source_type'] ?? '',
			'source_value' => $calculation['source_value'] ?? null,
			'source_details' => $calculation['source_details'] ?? '',
			'rule_code' => $calculation['rule_code'] ?? '',
			'coefficient' => $calculation['coefficient'] ?? null,
			'rounding_rule' => $calculation['rounding_rule'] ?? '',
			'calculation_status' => $calculation['calculation_status'] ?? 0,
			'calculation_message' => $calculation['calculation_message'] ?? '',
		);

		return hash('sha256', $this->encodeJson($payload));
	}

	/**
	 * Return fallback cost according to configuration.
	 *
	 * @param int $productId Product id
	 * @param Product|stdClass $product Product object
	 * @param string $fallback Fallback code
	 * @param int $entity Entity
	 * @return float|null
	 */
	private function getFallbackCostPrice($productId, $product, $fallback, $entity)
	{
		if ($fallback === 'keep_dolibarr') {
			return null;
		}
		if ($fallback === 'zero') {
			return 0.0;
		}
		if ($fallback === 'block') {
			$this->error = $this->trans('DynamicPricesCostMissingBlocked');
			$this->errors[] = $this->error;
			return null;
		}

		$nativeValues = $this->getProductNativeCostValues($productId, $entity);
		if ($fallback === 'pmp') {
			return $nativeValues['pmp'];
		}
		if ($fallback === 'native_cost_price') {
			return $nativeValues['cost_price'];
		}

		return null;
	}

	/**
	 * Resolve commercial line cost using the configured source priority.
	 *
	 * @param int $productId Product id
	 * @param Product|stdClass $product Product object
	 * @param int $entity Entity
	 * @param mixed $lineCurrentCost Current native line cost
	 * @param array{document?:CommonObject|stdClass,quantity?:int|float|string|null} $context Authorized document context
	 * @return array{cost:float|null,source_type:string,error:bool}
	 */
	public function resolveCommercialLineCostFromPriority($productId, $product, $entity, $lineCurrentCost = null, array $context = array())
	{
		$nativeValues = null;
		foreach ($this->getCommercialLineCostSourcePriority() as $source) {
			$cost = null;
			if ($source === 'pricelist') {
				$priceListResult = $this->getPriceListCost($product, $context['document'] ?? null, $context['quantity'] ?? null);
				if ($priceListResult['error']) {
					return array('cost' => null, 'source_type' => $source, 'error' => true);
				}
				$cost = $priceListResult['cost'];
			} elseif ($source === 'dynamicprices') {
				if (getDolGlobalInt('DYNAMICPRICES_COST_ENABLE', 1)) {
					$cost = $this->getDynamicCostPrice($productId, $entity);
				}
			} elseif ($source === 'dolibarr_default') {
				$cost = ($lineCurrentCost !== null && $lineCurrentCost !== '') ? (float) $lineCurrentCost : null;
			} elseif ($source === 'pmp' || $source === 'native_cost_price') {
				if ($nativeValues === null) {
					$nativeValues = $this->getProductNativeCostValues($productId, $entity);
				}
				$cost = $source === 'pmp' ? $nativeValues['pmp'] : $nativeValues['cost_price'];
			}

			if ($cost !== null) {
				return array(
					'cost' => (float) $cost,
					'source_type' => $source,
					'error' => false,
				);
			}
		}

		$fallback = getDolGlobalString('DYNAMICPRICES_COST_FALLBACK', 'keep_dolibarr');
		$cost = $this->getFallbackCostPrice($productId, $product, $fallback, $entity);
		return array(
			'cost' => $cost,
			'source_type' => $cost === null ? '' : 'fallback_'.$fallback,
			'error' => $fallback === 'block',
		);
	}

	/**
	 * Write native product cost price only when legacy option is enabled.
	 *
	 * @param int $productId Product id
	 * @param float|null $cost Cost
	 * @param int $entity Entity
	 * @return int
	 */
	private function writeNativeCostPrice($productId, $cost, $entity)
	{
		if ($cost === null) {
			return 0;
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."product";
		$sql .= " SET cost_price = ".price2num($cost, 'MU');
		$sql .= " WHERE rowid = ".((int) $productId);
		$sql .= " AND entity IN (".getEntity('product').")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Encode data as JSON.
	 *
	 * @param mixed $data Data
	 * @return string
	 */
	private function encodeJson($data)
	{
		$json = json_encode($data);
		return is_string($json) ? $json : '';
	}

	/**
	 * Return SQL string or NULL.
	 *
	 * @param string|null $value Value
	 * @return string
	 */
	private function sqlStringOrNull($value)
	{
		if ($value === null || $value === '') {
			return "NULL";
		}

		return "'".$this->db->escape((string) $value)."'";
	}

	/**
	 * Return SQL float or NULL.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private function sqlFloatOrNull($value)
	{
		if ($value === null || $value === '') {
			return "NULL";
		}

		return price2num($value, 'MU');
	}

	/**
	 * Return SQL integer or NULL.
	 *
	 * @param int|null $value Value
	 * @return string
	 */
	private function sqlIntOrNull($value)
	{
		if ($value === null) {
			return "NULL";
		}

		return (string) ((int) $value);
	}

	/**
	 * Return first existing integer property from an object.
	 *
	 * @param object $object Object
	 * @param array<int,string> $properties Properties
	 * @return int
	 */
	private function getObjectIntProperty($object, array $properties)
	{
		foreach ($properties as $property) {
			if (isset($object->{$property}) && $object->{$property} !== '') {
				return (int) $object->{$property};
			}
		}

		return 0;
	}

	/**
	 * Return first existing float property from an object.
	 *
	 * @param object $object Object
	 * @param array<int,string> $properties Properties
	 * @return float|null
	 */
	private function getObjectFloatProperty($object, array $properties)
	{
		foreach ($properties as $property) {
			if (isset($object->{$property}) && $object->{$property} !== '') {
				return (float) $object->{$property};
			}
		}

		return null;
	}

	/**
	 * Resolve the native purchase cost column for supported commercial line tables.
	 *
	 * @param string $tableElement Line table element without prefix
	 * @return string
	 */
	public function resolveCommercialLineCostColumn($tableElement)
	{
		$columnsByTable = array(
			'propaldet' => array('buy_price_ht', 'pa_ht'),
			'commandedet' => array('buy_price_ht', 'pa_ht'),
			'facturedet' => array('buy_price_ht', 'pa_ht'),
		);
		if (empty($columnsByTable[$tableElement])) {
			return '';
		}

		foreach ($columnsByTable[$tableElement] as $columnName) {
			if ($this->tableColumnExists(MAIN_DB_PREFIX.$tableElement, $columnName)) {
				return $columnName;
			}
		}

		return '';
	}

	/**
	 * Update native line purchase cost on supported commercial line tables.
	 *
	 * @param string $tableElement Line table element without prefix
	 * @param int $lineId Line id
	 * @param float $cost Cost to write
	 * @param int $parentId Authorized document id
	 * @param int $entity Document entity
	 * @return int
	 */
	private function updateCommercialLinePaHt($tableElement, $lineId, $cost, $parentId, $entity)
	{
		$allowedTables = array('propaldet', 'commandedet', 'facturedet');
		if (!in_array($tableElement, $allowedTables, true)) {
			$this->error = $this->trans('DynamicPricesCostInvalidLine');
			$this->errors[] = $this->error;
			return -1;
		}
		$costColumn = $this->resolveCommercialLineCostColumn($tableElement);
		if ($costColumn === '') {
			dol_syslog(__METHOD__.' no purchase cost column found for table '.MAIN_DB_PREFIX.$tableElement, LOG_WARNING);
			return 0;
		}

		$parentTables = array('propaldet' => 'propal', 'commandedet' => 'commande', 'facturedet' => 'facture');
		$parentTable = $parentTables[$tableElement];
		$definition = self::getCommercialDocumentTypes()[$parentTable];
		$sql = "UPDATE ".MAIN_DB_PREFIX.$tableElement." AS l";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX.$parentTable." AS p ON p.rowid = l.".$definition['parent_field'];
		$sql .= " SET l.".$costColumn." = ".price2num($cost, 'MU');
		$sql .= " WHERE l.rowid = ".((int) $lineId)." AND p.rowid = ".((int) $parentId);
		$sql .= " AND p.entity = ".((int) $entity)." AND p.entity IN (".$this->db->sanitize(getEntity($parentTable)).")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Check if a table column exists.
	 *
	 * @param string $tableName Table name
	 * @param string $columnName Column name
	 * @return bool
	 */
	private function tableColumnExists($tableName, $columnName)
	{
		$sql = "SHOW COLUMNS FROM ".$tableName." LIKE '".$this->db->escape($columnName)."'";
		$resql = $this->db->query($sql);

		return ($resql && $this->db->num_rows($resql) > 0);
	}

	/**
	 * Translate a service message.
	 *
	 * @param string $key Translation key
	 * @return string
	 */
	private function trans($key)
	{
		global $langs;

		if (is_object($langs)) {
			$langs->load('dynamicsprices@dynamicsprices');
			return $langs->trans($key);
		}

		return $key;
	}
}
