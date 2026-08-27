<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

define('MAIN_DB_PREFIX', 'llx_');

$conf = new stdClass();
$conf->entity = 1;

/** @var array<string,int> $testGlobalInts */
$testGlobalInts = array(
	'DYNAMICPRICES_COST_INCLUDE_SERVICES' => 1,
	'DYNAMICPRICES_COST_RECALC_KITS' => 1,
);

function getDolGlobalInt($key, $default = 0)
{
	global $testGlobalInts;
	return array_key_exists($key, $testGlobalInts) ? (int) $testGlobalInts[$key] : (int) $default;
}

function getDolGlobalString($key, $default = '')
{
	return (string) $default;
}

function getEntity($element)
{
	return '1';
}

function price2num($value, $type = '')
{
	return (float) $value;
}

function dol_include_once($path)
{
	return 1;
}

class Product
{
	const TYPE_PRODUCT = 0;
	const TYPE_SERVICE = 1;

	/** @var int */
	public $id = 0;

	/** @var int */
	public $type = self::TYPE_PRODUCT;

	/** @var string */
	public $ref = '';

	/** @var object */
	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function fetch($id)
	{
		if (!isset($this->db->products[(int) $id])) {
			return 0;
		}

		$product = $this->db->products[(int) $id];
		$this->id = (int) $id;
		$this->type = (int) $product['type'];
		$this->ref = (string) $product['ref'];
		return 1;
	}
}

class DynamicPricesTestDb
{
	/** @var array<int,array{type:int,ref:string,cost_price:float,pmp:float}> */
	public $products = array();

	/** @var array<int,array<int,array{id:int,ref:string,qty:float}>> */
	public $components = array();

	/** @var array<int,object> */
	public $costRecords = array();

	/** @var array<int,float> */
	public $supplierPrices = array();

	/** @var array<int,object> */
	private $resultRows = array();

	/** @var string */
	public $lastQuery = '';

	public function query($sql)
	{
		$this->lastQuery = (string) $sql;
		$this->resultRows = array();
		if (strpos($sql, 'product_association AS pa') !== false) {
			preg_match('/fk_product_pere = ([0-9]+)/', $sql, $matches);
			$parentId = !empty($matches[1]) ? (int) $matches[1] : 0;
			foreach ($this->components[$parentId] ?? array() as $component) {
				$this->resultRows[] = (object) array(
					'fk_product_fils' => $component['id'],
					'ref' => $component['ref'],
					'qty' => $component['qty'],
				);
			}
			return true;
		}
		if (strpos($sql, 'dynamicprices_product_cost') !== false) {
			preg_match('/fk_product = ([0-9]+)/', $sql, $matches);
			$productId = !empty($matches[1]) ? (int) $matches[1] : 0;
			if (isset($this->costRecords[$productId])) {
				$this->resultRows[] = clone $this->costRecords[$productId];
			}
			return true;
		}
		if (strpos($sql, 'product_fournisseur_price AS p') !== false) {
			foreach ($this->supplierPrices as $price) {
				$this->resultRows[] = (object) array('unitprice' => $price);
			}
			return true;
		}
		if (strpos($sql, 'FROM llx_product') !== false) {
			preg_match('/rowid = ([0-9]+)/', $sql, $matches);
			$productId = !empty($matches[1]) ? (int) $matches[1] : 0;
			if (isset($this->products[$productId])) {
				$this->resultRows[] = (object) array(
					'cost_price' => $this->products[$productId]['cost_price'],
					'pmp' => $this->products[$productId]['pmp'],
				);
			}
			return true;
		}

		return false;
	}

	public function fetch_object($result)
	{
		return array_shift($this->resultRows);
	}

	public function lasterror()
	{
		return 'test query error';
	}
}

function assertSameValue($expected, $actual, $message)
{
	if ($expected !== $actual) {
		fwrite(STDERR, $message.' Expected '.var_export($expected, true).', got '.var_export($actual, true).PHP_EOL);
		exit(1);
	}
}

require_once __DIR__.'/../class/dynamicpricescostservice.class.php';

$db = new DynamicPricesTestDb();
$db->products[10] = array('type' => Product::TYPE_PRODUCT, 'ref' => 'KIT', 'cost_price' => 0.0, 'pmp' => 0.0);
$db->components[10] = array(
	array('id' => 11, 'ref' => 'COMP-A', 'qty' => 2.0),
	array('id' => 12, 'ref' => 'COMP-B', 'qty' => 3.0),
);
$db->costRecords[11] = (object) array('dynamic_cost_price' => 100.0, 'calculation_status' => 1, 'status' => 1);
$db->costRecords[12] = (object) array('dynamic_cost_price' => 25.0, 'calculation_status' => 1, 'status' => 1);

$service = new DynamicPricesCostService($db);
$calculation = $service->calculateProductCost(10, array('entity' => 1));
assertSameValue(275.0, $calculation['dynamic_cost_price'], 'Kit cost must be the sum of component DynamicPrices costs.');
assertSameValue('kit_components', $calculation['source_type'], 'Kit calculation must expose the component source.');
assertSameValue(1, $calculation['calculation_status'], 'Kit calculation must succeed when all component costs are available.');

$db->costRecords[12]->calculation_status = -1;
$calculation = $service->calculateProductCost(10, array('entity' => 1));
assertSameValue(null, $calculation['dynamic_cost_price'], 'Kit calculation must not retain a cost when a component is unavailable.');
assertSameValue(-1, $calculation['calculation_status'], 'Kit calculation must fail when a component cost is invalid.');
assertSameValue(null, $service->getDynamicCostPrice(12, 1), 'An unsuccessful DynamicPrices record must not be returned as an effective cost.');

$db->supplierPrices = array(1250.0);
assertSameValue(1250.0, $service->getSupplierAveragePrice(20, 1), 'Valid supplier prices must remain usable.');
assertSameValue(true, strpos($db->lastQuery, "TRIM(COALESCE(p.ref_fourn, '')) <> ''") !== false, 'Supplier prices without a reference must be excluded.');
assertSameValue(true, strpos($db->lastQuery, 'p.status = 1') !== false, 'Inactive supplier prices must be excluded.');

fwrite(STDOUT, "DynamicPricesCostService tests passed.\n");
