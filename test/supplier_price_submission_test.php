<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Real module hooks/trigger with simulated ERP objects and transactional storage.
// No running database, native CSRF middleware or browser is exercised.
define('MAIN_DB_PREFIX', 'test_');
define('DOL_VERSION', '24.0.0');
define('DOL_DOCUMENT_ROOT', __DIR__.'/../../dolibarr/htdocs');
class CommonHookActions { public $error = ''; public $errors = array(); public $resprints; }
class User {
	public $id = 1;
	public $admin = false;
	public $socid = 0;
	public $denied = '';
	public function hasRight(...$parts) { return implode('/', $parts) !== $this->denied; }
}
class Translate {
	public function load($name) {}
	public function trans($key, ...$args) { return $key; }
}
class Conf {
	public $entity = 2;
	public $browser;
	public $use_javascript_ajax = 0;
	public function __construct() { $this->browser = (object) array('layout' => 'desktop'); }
}
class DolibarrTriggers {
	public $db;
	public $error = '';
	public $errors = array();
	const VERSIONS = array('dev' => 'development');
	public function __construct($db) { $this->db = $db; }
}
class CommandeFournisseur {
	const STATUS_VALIDATED = 1;
	const STATUS_ACCEPTED = 2;
	public $id = 1233;
	public $entity = 2;
	public $socid = 42;
	public $status = 2;
	public $context = array();
	public $lines = array();
}
class ProductFournisseur {
	public $db;
	public $type = 0;
	public $entity = 2;
	public $product_fourn_price_id;
	public $product_fourn_packaging;
	public $packaging;
	public $error = '';
	public $errors = array();
	public function __construct($db) { $this->db = $db; }
	public function fetch($id) { return 1; }
	public function update_buyprice($qty, $total, ...$args) {
		$this->db->begin();
		if ($this->db->failWrite === $this->product_fourn_price_id) {
			$this->error = 'simulated native write failure';
			$this->db->rollback();
			return -1;
		}
		$this->db->prices[$this->product_fourn_price_id]['unitprice'] = $total / $qty;
		$this->db->writes++;
		$this->db->commit();
		return $this->product_fourn_price_id;
	}
}
class Societe {
	public $entity = 2;
	public function __construct($db) {}
	public function fetch($id) { return 1; }
}
class Product extends ProductFournisseur {
	public function getNomUrl($mode) { return 'INT300'; }
}
class SubmissionDb {
	public $prices = array();
	public $failRead = false;
	public $failWrite = 0;
	public $writes = 0;
	public $level = 0;
	public $snapshot;
	public $queries = array();
	public function begin() { if ($this->level++ === 0) { $this->snapshot = $this->prices; } return 1; }
	public function commit() { --$this->level; return 1; }
	public function rollback() { if (--$this->level === 0) { $this->prices = $this->snapshot; } return 1; }
	public function query($sql) {
		$this->queries[] = $sql;
		if (strpos($sql, 'SHOW COLUMNS') === 0) { return array(); }
		if ($this->failRead) { return false; }
		if (preg_match('/WHERE rowid = (\d+)/', $sql, $m)) {
			return isset($this->prices[$m[1]]) ? array((object) $this->prices[$m[1]]) : array();
		}
		return array();
	}
	public function num_rows($result) { return count($result); }
	public function fetch_object($result) { return $result[0] ?? false; }
	public function escape($text) { return addslashes($text); }
}
function GETPOST($key, $type) { return $_POST[$key] ?? ''; }
function getDolGlobalInt($key, $default = 0) { return $key === 'LMDB_ADD_UPDATE_SUPPLIER_PRICE_ON_SUBMIT' ? 1 : $default; }
function isModEnabled($module) { return true; }
function price2num($value, $mode = '') {
	if (!is_numeric($value)) { return ''; }
	// Simulate distinct native precision settings to expose accidental MT rounding.
	return $mode === 'MU' ? round((float) $value, 5) : ($mode === 'MT' ? round((float) $value, 2) : (float) $value);
}
function dol_syslog(...$args) {}
function dol_string_nohtmltag($value, $mode) { return strip_tags($value); }
function getEntity($element) { return '2'; }
function restrictedArea(...$args) {
	if (($args[6] ?? 'rowid') !== 'rowid') { throw new RuntimeException('Native access check requires its rowid SELECT column'); }
	if ($args[1] === 'societe' && $args[3] !== '&societe') { throw new RuntimeException('Native third-party sharing contract must be preserved'); }
	return 1;
}
function setEventMessages(...$args) {}
function GETPOSTINT($key) { return (int) GETPOST($key, 'int'); }
function dol_mktime(...$args) { return 1700000000; }
function newToken() { return 'simulated-token'; }
function dol_escape_htmltag($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function dol_include_once(...$args) {}
function img_picto(...$args) { return ''; }
function check($expected, $actual, $message) {
	if ($actual !== $expected) { throw new RuntimeException($message.' '.var_export($actual, true)); }
}
// Load complete production classes; omit only their dependency includes.
foreach (array('../class/actions_dynamicsprices.class.php', '../core/triggers/interface_99_modDynamicsPrices_DynamicsPricesTriggers.class.php') as $file) {
	$source = file_get_contents(__DIR__.'/'.$file);
	if (($argv[1] ?? '') === '--baseline') {
		$source = shell_exec('git show b93992b7ffa8c8d5a088cf2e30df6ed3aac59e39:'.substr($file, 3));
	}
	$source = preg_replace('/^[\t ]*require_once .*;\R/m', '', $source);
	eval(substr($source, 5));
}
$conf = new Conf();
$user = new User();
$langs = new Translate();
$db = new SubmissionDb();
$hook = new ActionsDynamicsPrices($db);
$trigger = new InterfaceDynamicsPricesTriggers($db);
$order = new CommandeFournisseur();
for ($id = 10; $id <= 11; $id++) {
	$db->prices[$id] = array('rowid' => $id, 'ref_fourn' => 'INT300', 'quantity' => 1, 'unitprice' => 10, 'tva_tx' => 20, 'remise_percent' => 0, 'fk_availability' => 0, 'delivery_time_days' => null, 'supplier_reputation' => 0);
	$order->lines[] = (object) array('id' => $id, 'fk_product' => $id, 'fk_prod_fourn_price' => $id, 'ref_fourn' => 'INT300', 'qty' => 1, 'subprice' => 12, 'tva_tx' => 20, 'remise_percent' => 0);
}
$request = array('dynamicsprices_modal' => '1', 'token' => 'middleware-validated', 'confirm' => 'yes', 'dynamicsprices_update_prices' => 'yes', 'dynamicsprices_apply_line' => array(10 => 1, 11 => 1));
foreach (array(10, 11) as $id) {
	$request['dynamicsprices_data'][$id] = array('qty' => '1', 'unitquantity' => '1', 'vat' => '20', 'unitprice' => '12', 'discount' => '0', 'supplier_ref' => 'INT300', 'expected_unitprice' => '10', 'current_rowid' => $id, 'fk_product' => 999, 'fk_soc' => 999);
}
$parameters = array('context' => 'ordersuppliercard');
$manager = null;
if (($argv[1] ?? '') === '--native-form') {
	if (!is_file(DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php')) { throw new RuntimeException('Native Dolibarr checkout is required for this optional test.'); }
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	$_SERVER['PHP_SELF'] = '/fourn/commande/card.php';
	$_POST = array();
	$action = 'commande';
	check(1, $hook->formConfirm($parameters, $order, $action, $manager), 'Native confirmation rendering');
	$dom = new DOMDocument();
	@$dom->loadHTML($hook->resprints);
	$xpath = new DOMXPath($dom);
	check(1, $xpath->query('//form[@method="POST"]')->length, 'Exactly one POST confirmation form');
	check(1, $xpath->query('//form/input[@name="confirm" and @value="yes"]')->length, 'Core confirmation is always yes');
	check(1, $xpath->query('//form//select[@name="dynamicsprices_update_prices"]')->length, 'Separate native selector for optional update');
	check(1, $xpath->query('//form/input[@name="token"]')->length, 'Native CSRF field');
	check(2, $xpath->query('//form//input[contains(@name,"[unitprice]")]')->length, 'Editable prices belong to POST form');
	$db->failRead = true;
	check(1, $hook->formConfirm($parameters, $order, $action, $manager), 'Lookup failure replaces native confirmation');
	check(false, strpos($hook->resprints, '<form') !== false, 'Lookup failure never opens a submission form');
	$db->failRead = false;
	echo "Native Form::formconfirm DOM test passed (simulated environment).\n";
}
function prepare($request, $expected = 0) {
	global $hook, $order, $parameters, $manager;
	$_POST = $request;
	$action = 'dynamicsprices_confirm_commande';
	check($expected, $hook->doActions($parameters, $order, $action, $manager), 'Preparation result');
	return $action;
}
check('confirm_commande', prepare($request), 'Native submission must remain active');
check(0, $db->writes, 'Hook preparation must never write prices');
check(10, $order->context['dynamicsprices_supplier_updates'][10]['fk_product'], 'Forged product ignored');
check(42, $order->context['dynamicsprices_supplier_updates'][10]['fk_soc'], 'Forged supplier ignored');
$db->begin();
check(1, $trigger->runTrigger('ORDER_SUPPLIER_SUBMIT', $order, $user, $langs, $conf), 'Submission trigger');
$db->commit();
check(12.0, $db->prices[10]['unitprice'], 'Price persisted');
check(false, isset($order->context['dynamicsprices_supplier_updates']), 'Context consumed once');
// Model the outer native order transaction: later failure restores both prices.
$db->prices[10]['unitprice'] = $db->prices[11]['unitprice'] = 10;
prepare($request);
$db->failWrite = 11;
$db->begin();
check(-1, $trigger->runTrigger('ORDER_SUPPLIER_SUBMIT', $order, $user, $langs, $conf), 'Second write failure');
$db->rollback();
check(10, $db->prices[10]['unitprice'], 'First write rolled back after second failure');
$db->failWrite = 0;
$precise = $request; $precise['dynamicsprices_data'][10]['unitprice'] = '12.12345';
prepare($precise);
$db->begin();
check(1, $trigger->runTrigger('ORDER_SUPPLIER_SUBMIT', $order, $user, $langs, $conf), 'Precision staging');
check(12.12345, $db->prices[10]['unitprice'], 'Supplier tariff follows MU precision, not commercial total precision');
$db->rollback();
prepare($request);
$db->begin();
check(1, $trigger->runTrigger('ORDER_SUPPLIER_SUBMIT', $order, $user, $langs, $conf), 'Successful price staging');
$db->rollback();
check(10, $db->prices[10]['unitprice'], 'Later order failure rolls prices back');
// Missing functional rights, including an administrator, never grant writes.
foreach (array(false, true) as $admin) {
	$user->admin = $admin;
	foreach (array('produit/creer', 'fournisseur/lire') as $denied) {
		prepare($request);
		$user->denied = $denied;
		$db->begin();
		check(-1, $trigger->runTrigger('ORDER_SUPPLIER_SUBMIT', $order, $user, $langs, $conf), 'Missing tariff permission');
		$db->rollback();
		$user->denied = '';
	}
}
$user->admin = false;
$user->socid = 42; prepare($request, -1); $user->socid = 0;
$user->denied = 'fournisseur/commande/commander'; prepare($request, -1); $user->denied = '';
$order->entity = 3; prepare($request, -1); $order->entity = 2;
$order->status = 3; prepare($request, -1); $order->status = 2;
$bad = $request; unset($bad['token']); prepare($bad, -1);
$bad = $request; $bad['dynamicsprices_data'][10]['unitprice'] = array('12'); prepare($bad, -1);
$bad = $request; $bad['dynamicsprices_data'][10]['expected_unitprice'] = 9; prepare($bad, -1);
$db->failRead = true; prepare($request, -1); $db->failRead = false;
// Change between preparation and trigger is detected while locking the price row.
prepare($request); $db->prices[10]['unitprice'] = 11;
$db->begin();
check(-1, $trigger->runTrigger('ORDER_SUPPLIER_SUBMIT', $order, $user, $langs, $conf), 'Concurrent change');
$db->rollback(); $db->prices[10]['unitprice'] = 10;
$bad = $request; $bad['dynamicsprices_data'][10]['discount'] = 101; prepare($bad);
$db->begin();
check(-1, $trigger->runTrigger('ORDER_SUPPLIER_SUBMIT', $order, $user, $langs, $conf), 'Invalid discount');
$db->rollback();
$ignore = $request; $ignore['dynamicsprices_update_prices'] = 'no';
unset($order->context['dynamicsprices_supplier_updates']);
check('confirm_commande', prepare($ignore), 'Explicit ignore continues order');
check(false, isset($order->context['dynamicsprices_supplier_updates']), 'Ignore prepares no updates');
$none = $request; $none['dynamicsprices_apply_line'] = array(); prepare($none);
check(array(), $order->context['dynamicsprices_supplier_updates'], 'Unchecked lines stay unchanged');
echo "Supplier submission regression scenarios passed (simulated ERP).\n";
