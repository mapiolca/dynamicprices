<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

// Standalone business tests with doubles; no running Dolibarr database is used.
require __DIR__.'/dynamicpricescostservice_test.php';
define('DOL_VERSION', '20.0.0');

$mode = $argv[1] ?? '';
if ($mode === 'missing') {
	$testModules['pricelist'] = true;
	assertSameValue(false, $service->getPriceListAvailability()['available'], 'Missing class must disable the integration.');
	exit(0);
}
if ($mode === 'incompatible') {
	class PriceList { public function get_price($product, $soc, $qty) {} }
	$testModules['pricelist'] = true;
	assertSameValue(false, $service->getPriceListAvailability()['available'], 'Old PriceList contracts must be unavailable.');
	exit(0);
}

if ($mode !== 'missing' && $mode !== 'incompatible') {
	class PriceList
	{
		/** @var object|int */
		public static $result = 0;
		/** @var array<int,array<int,mixed>> */
		public static $calls = array();
		public function __construct($db) {}
		public function get_price($product, $soc, $qty, $document = null)
		{
			self::$calls[] = array($product, $soc, $qty, $document);
			return self::$result;
		}
		public function getEffectiveCostPriceForRow($row)
		{
			return !empty($row->use_product_cost_price) ? $row->product_cost : $row->cost_price;
		}
	}
}

class User
{
	public $id = 7;
	public $chooseCost = true;
	public function hasRight($module, $permission)
	{
		return $module === 'margins' && $permission === 'creer' && $this->chooseCost;
	}
}

function dol_now() { return 1700000000; }

class CommercialCostTestDb extends DynamicPricesTestDb
{
	public $queries = array();
	public $failSnapshot = false;
	public function query($sql)
	{
		$this->queries[] = $sql;
		if (strpos($sql, 'SHOW COLUMNS') === 0 || strpos($sql, 'UPDATE') === 0) {
			return true;
		}
		if (strpos($sql, 'INSERT INTO llx_dynamicprices_line_cost_snapshot') === 0) {
			return !$this->failSnapshot;
		}
		return parent::query($sql);
	}
	public function num_rows($result) { return 1; }
	public function escape($value) { return addslashes($value); }
	public function sanitize($value) { return $value; }
	public function idate($value) { return '2026-09-22 12:00:00'; }
}

$db = new CommercialCostTestDb();
$db->products[20] = array('type' => 0, 'ref' => 'PRODUCT', 'cost_price' => 30.0, 'pmp' => 40.0);
$db->costRecords[20] = (object) array('dynamic_cost_price' => 25.0, 'status' => 1, 'calculation_status' => 1);
$service = new DynamicPricesCostService($db);
$product = new Product($db);
$product->fetch(20);
$thirdparty = (object) array('id' => 12);
$document = (object) array('id' => 15, 'entity' => 1, 'element' => 'propal', 'thirdparty' => $thirdparty);
$context = array('document' => $document, 'quantity' => 10.0);
$default = array('dynamicprices', 'dolibarr_default', 'pmp', 'native_cost_price');

assertSameValue($default, $service->getCommercialLineCostSourcePriority(), 'Default order must not change.');
assertSameValue(false, isset($service->getCommercialLineCostSourceOptions()['pricelist']), 'Disabled PriceList must not be offered.');
$testModules['pricelist'] = true;
assertSameValue(true, isset($service->getCommercialLineCostSourceOptions()['pricelist']), 'Active compatible PriceList must be offered.');
assertSameValue($default, $service->getCommercialLineCostSourcePriority(), 'Activation must not opt in to PriceList.');
$testGlobalStrings['DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY'] = 'pricelist,dynamicprices,native_cost_price,pmp,dolibarr_default';
$savedPriority = $service->getCommercialLineCostSourcePriority(null, true);
assertSameValue(array('pricelist', 'pmp'), $service->getCommercialLineCostSourcePriority('pricelist,unknown,pmp,pricelist'), 'Unknown and repeated sources are ignored.');

PriceList::$result = (object) array('cost_price' => 12.5);
$resolution = $service->resolveCommercialLineCostFromPriority(20, $product, 1, 80, $context);
assertSameValue(12.5, $resolution['cost'], 'PriceList must win when ranked first.');
assertSameValue('pricelist', $resolution['source_type'], 'Resolution must identify PriceList.');
assertSameValue(array(20, $thirdparty, 10.0, $document), PriceList::$calls[0], 'Product, customer, quantity and native document must be forwarded unchanged.');
foreach (array('propal', 'commande', 'facture') as $type) {
	$document->element = $type;
	$context['quantity'] = 25.0;
	$service->resolveCommercialLineCostFromPriority(20, $product, 1, null, $context);
	$lastCall = PriceList::$calls[count(PriceList::$calls) - 1];
	assertSameValue(25.0, $lastCall[2], 'The actual quantity must reach the tier resolver.');
	assertSameValue($document, $lastCall[3], 'Document categories must be resolved against the actual parent.');
}
$document->entity = 2;
$service->resolveCommercialLineCostFromPriority(20, $product, 2, null, $context);
assertSameValue(2, PriceList::$calls[count(PriceList::$calls) - 1][3]->entity, 'The document entity must be kept for shared products.');
$document->entity = 1;

foreach (array(0, (object) array('cost_price' => null)) as $noCost) {
	PriceList::$result = $noCost;
	assertSameValue(25.0, $service->resolveCommercialLineCostFromPriority(20, $product, 1, null, $context)['cost'], 'Missing rule or missing cost must advance to DynamicPrices.');
}
PriceList::$result = (object) array('cost_price' => 0.0);
assertSameValue(0.0, $service->resolveCommercialLineCostFromPriority(20, $product, 1, null, $context)['cost'], 'Zero is a valid cost, not an unavailable source.');
PriceList::$result = (object) array('use_product_cost_price' => 1, 'product_cost' => 30.0);
assertSameValue(30.0, $service->resolveCommercialLineCostFromPriority(20, $product, 1, null, $context)['cost'], 'PriceList must control its native-product-cost mode.');

PriceList::$result = -1;
assertSameValue(true, $service->resolveCommercialLineCostFromPriority(20, $product, 1, null, $context)['error'], 'A resolver error must not fall through to another source.');
$testGlobalStrings['DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY'] = 'dynamicprices,pricelist';
$callsBefore = count(PriceList::$calls);
assertSameValue(25.0, $service->resolveCommercialLineCostFromPriority(20, $product, 1, null, $context)['cost'], 'An earlier valid source must win.');
assertSameValue($callsBefore, count(PriceList::$calls), 'Lower priority sources must not be queried by the write resolver.');
$testGlobalStrings['DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY'] = implode(',', $savedPriority);
$testModules['pricelist'] = false;
assertSameValue($savedPriority, $service->getCommercialLineCostSourcePriority(null, true), 'Disabling PriceList must preserve its stored position.');
assertSameValue(array_slice($savedPriority, 1), $service->getCommercialLineCostSourcePriority(), 'Disabled PriceList must be skipped at execution.');
$testModules['pricelist'] = true;
assertSameValue($savedPriority, $service->getCommercialLineCostSourcePriority(), 'Reactivation restores the same order.');

$user = new User();
$testGlobalInts['DYNAMICPRICES_COST_USE_FOR_SALES'] = 1;
$line = (object) array('id' => 42, 'fk_product' => 20, 'qty' => 10.0, 'pa_ht' => 70.0);
$writeContext = array('line_action' => 'create', 'line_table' => 'facturedet');
PriceList::$result = (object) array('cost_price' => 12.5);
assertSameValue(1, $service->applyCostToCommercialLine('facturedet', $line, $document, $user, $writeContext), 'Creation without browser state applies the priority.');
assertSameValue(12.5, $line->pa_ht, 'The resolved price must be written on the line.');
$updates = array_values(array_filter($db->queries, static function ($sql) { return strpos($sql, 'UPDATE ') === 0; }));
assertSameValue(true, strpos($updates[0], 'p.entity = 1') !== false && strpos($updates[0], 'p.rowid = 15') !== false, 'The write must be scoped to the authorized parent and entity.');
$snapshots = array_values(array_filter($db->queries, static function ($sql) { return strpos($sql, 'INSERT INTO llx_dynamicprices_line_cost_snapshot') === 0; }));
assertSameValue(true, strpos($snapshots[0], "'pricelist'") !== false, 'Snapshots must record the selected source.');
$writeContext['cost_source_mode'] = 'manual';
$writeContext['manual_cost'] = '19.25';
assertSameValue(1, $service->applyCostToCommercialLine('facturedet', $line, $document, $user, $writeContext), 'A permitted manual cost must survive a previous PriceList hook.');
assertSameValue(19.25, $line->pa_ht, 'Manual value takes precedence.');
$user->chooseCost = false;
$service->applyCostToCommercialLine('facturedet', $line, $document, $user, $writeContext);
assertSameValue(12.5, $line->pa_ht, 'Forged manual state cannot bypass the automatic priority.');
$user->chooseCost = true;
$writeContext['cost_source'] = 'dynamicsprices_pricelist_cost';
$writeContext['manual_cost'] = '9999';
$service->applyCostToCommercialLine('facturedet', $line, $document, $user, $writeContext);
assertSameValue(12.5, $line->pa_ht, 'Explicit PriceList selection is resolved again on the server.');

$writeContext['line_action'] = 'update';
$line->pa_ht = 123.0;
$queriesBefore = count($db->queries);
assertSameValue(0, $service->applyCostToCommercialLine('facturedet', $line, $document, $user, $writeContext), 'Editing must not apply automatic pricing.');
assertSameValue(123.0, $line->pa_ht, 'Existing line cost is preserved.');
assertSameValue($queriesBefore, count($db->queries), 'Editing must not write a cost or snapshot.');
$writeContext = array('line_action' => 'create', 'line_table' => 'facturedet');
PriceList::$result = -1;
assertSameValue(-1, $service->applyCostToCommercialLine('facturedet', $line, $document, $user, $writeContext), 'Errors must propagate to the native transaction.');
assertSameValue($queriesBefore, count($db->queries), 'No cost/snapshot is written after a PriceList error.');
PriceList::$result = (object) array('cost_price' => 12.5);
$db->failSnapshot = true;
assertSameValue(-1, $service->applyCostToCommercialLine('facturedet', $line, $document, $user, $writeContext), 'Snapshot failure must abort the native transaction.');
$db->failSnapshot = false;
$db->products[20]['entity'] = 9;
assertSameValue(-1, $service->applyCostToCommercialLine('facturedet', $line, $document, $user, $writeContext), 'A product outside the allowed entity scope must be refused.');
foreach ($db->queries as $sql) {
	assertSameValue(false, strpos($sql, 'UPDATE llx_product ') === 0, 'Commercial cost selection must never update product.cost_price.');
}

fwrite(STDOUT, "Commercial line cost tests passed.\n");
