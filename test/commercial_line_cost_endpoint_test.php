<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

// Run the actual AJAX entry point and triggers in isolated PHP processes with a simulated ERP.
$scenario = $argv[1] ?? '';
// Exercise every passive trigger independently of the actor's administrator role.
$permissionCases = array();
foreach (array('propal', 'commande', 'facture') as $type) {
	foreach (array('standard', 'admin') as $role) {
		foreach (array('positive' => 70.0, 'zero' => 0.0, 'null' => null) as $costName => $cost) {
			$permissionCases['trigger_skip_'.$type.'_'.$role.'_'.$costName] = array('type' => $type, 'admin' => $role === 'admin', 'cost' => $cost);
		}
		$permissionCases['ajax_denied_'.$type.'_'.$role] = array('type' => $type, 'admin' => $role === 'admin', 'cost' => 70.0);
	}
}
if ($scenario === '') {
	$cases = array(
		'propal' => 200, 'commande' => 200, 'facture' => 200, 'tier_zero' => 200,
		'price_error' => 200, 'disabled' => 200, 'no_rule' => 200,
		'no_cost_right' => 403, 'admin_no_cost_right' => 403, 'no_document_right' => 403,
		'no_product_right' => 403, 'document_denied' => 403, 'thirdparty_denied' => 403,
		'product_denied' => 403, 'product_entity' => 404, 'document_entity' => 403,
		'invalid_type' => 400, 'invalid_quantity' => 400,
		'trigger_propal' => 200, 'trigger_commande' => 200, 'trigger_facture' => 200,
		'trigger_no_cost_right' => 200, 'trigger_no_document_right' => 200,
		'trigger_document_denied' => 200, 'trigger_thirdparty_denied' => 200,
		'trigger_price_error' => 200, 'trigger_update' => 200, 'trigger_never' => 200, 'trigger_document_entity' => 200,
	);
	foreach ($permissionCases as $case => $settings) {
		$cases[$case] = strpos($case, 'trigger_') === 0 ? 200 : 403;
	}
	foreach ($cases as $case => $expectedStatus) {
		$process = proc_open(array(PHP_BINARY, __FILE__, $case), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if (!is_resource($process)) { throw new RuntimeException('Cannot start endpoint test.'); }
		$output = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit = proc_close($process);
		$result = json_decode($output, true);
		if ($exit !== 0 || $stderr !== '' || !is_array($result) || $result['status'] !== $expectedStatus) {
			throw new RuntimeException($case.': '.$stderr.' '.$output);
		}
		$payload = $result['body'];
		if (strpos($case, 'trigger_') === 0) {
			$skipped = isset($permissionCases[$case]) || $case === 'trigger_no_document_right';
			$denied = in_array($case, array('trigger_document_denied', 'trigger_thirdparty_denied', 'trigger_price_error', 'trigger_document_entity'), true);
			$unchanged = $denied || $skipped || in_array($case, array('trigger_update', 'trigger_never'), true);
			$expectedCost = isset($permissionCases[$case]) ? $permissionCases[$case]['cost'] : ($unchanged ? 70.0 : 12.5);
			$actualCost = $payload['cost'] === null ? null : (float) $payload['cost'];
			if (($payload['result'] < 0) !== $denied || $actualCost !== $expectedCost
				|| $payload['writes'] !== ($unchanged ? 0 : 2)) { throw new RuntimeException($case.': '.$output); }
			if ($skipped && ($payload['result'] !== 0 || $payload['queries'] !== 0 || $payload['price_calls'] !== 0
				|| $payload['error'] !== '' || count($payload['skip_logs']) !== 1)) {
				throw new RuntimeException($case.': skipped enrichment had side effects: '.$output);
			}
			if ($skipped) {
				$type = $permissionCases[$case]['type'] ?? 'propal';
				$expectedLog = 'InterfaceDynamicsPricesTriggers::applyDynamicCostToCommercialLine skip automatic cost recalculation: missing create permission for '.$type;
				if ($payload['skip_logs'][0] !== $expectedLog) { throw new RuntimeException($case.': unexpected diagnostic content.'); }
			}
		} elseif ($expectedStatus !== 200) {
			if ($payload['success'] !== false || isset($payload['price']) || isset($payload['pricelist'])) {
				throw new RuntimeException($case.': a forbidden response exposed prices.');
			}
		} elseif ($case === 'disabled') {
			if ($payload['enabled'] !== false || $payload['available'] !== false) { throw new RuntimeException('Disabled integration exposed costs.'); }
		} elseif ($case === 'price_error') {
			if (!$payload['resolution']['error'] || $payload['resolution']['cost'] !== null || empty($payload['error_message'])) { throw new RuntimeException('PriceList error was hidden.'); }
		} else {
			$expectedCost = $case === 'tier_zero' ? 0 : ($case === 'no_rule' ? 25 : 12.5);
			if ((float) $payload['resolution']['cost'] !== (float) $expectedCost) { throw new RuntimeException($case.': incorrect cost.'); }
			if ($case !== 'no_rule' && $payload['resolution']['source_type'] !== 'pricelist') { throw new RuntimeException('Wrong resolved source.'); }
		}
	}
	fwrite(STDOUT, 'AJAX and trigger tests passed ('.count($cases)." isolated scenarios).\n");
	exit(0);
}

$isTrigger = strpos($scenario, 'trigger_') === 0;
$permissionCase = $permissionCases[$scenario] ?? null;
if ($isTrigger) { $scenario = substr($scenario, 8); }
if ($permissionCase !== null) { $scenario = 'no_document_right'; }

$fixtureRoot = sys_get_temp_dir().'/dynamicprices-endpoint-'.bin2hex(random_bytes(8));
$fixtureFiles = array('/main.inc.php', '/product/class/product.class.php', '/core/lib/security.lib.php', '/core/triggers/dolibarrtriggers.class.php', '/comm/propal/class/propal.class.php', '/commande/class/commande.class.php', '/compta/facture/class/facture.class.php');
foreach ($fixtureFiles as $file) {
	if (!is_dir(dirname($fixtureRoot.$file))) { mkdir(dirname($fixtureRoot.$file), 0700, true); }
	file_put_contents($fixtureRoot.$file, "<?php\n");
}
register_shutdown_function(static function () use ($fixtureRoot, $fixtureFiles) {
	foreach ($fixtureFiles as $file) { unlink($fixtureRoot.$file); }
	$directories = array();
	foreach ($fixtureFiles as $file) {
		$directory = dirname($fixtureRoot.$file);
		while ($directory !== $fixtureRoot) {
			$directories[$directory] = strlen($directory);
			$directory = dirname($directory);
		}
	}
	arsort($directories);
	foreach ($directories as $directory => $length) { rmdir($directory); }
	rmdir($fixtureRoot);
});
define('DOL_DOCUMENT_ROOT', $fixtureRoot);
define('DOL_VERSION', '20.0.0');
define('MAIN_DB_PREFIX', 'llx_');
$_SERVER['CONTEXT_DOCUMENT_ROOT'] = $fixtureRoot;
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../ajax/commercial_line_cost.php';
$documentType = in_array($scenario, array('commande', 'facture'), true) ? $scenario : 'propal';
if ($permissionCase !== null) { $documentType = $permissionCase['type']; }
$input = array('product_id' => 20, 'document_type' => $scenario === 'invalid_type' ? 'contrat' : $documentType, 'document_id' => 15, 'qty' => $scenario === 'invalid_quantity' ? 'invalid' : ($scenario === 'tier_zero' ? '20' : '10'), 'line_current_cost' => '70', 'sale_price' => '100');
function GETPOST($key, $filter = '') { global $input; return $input[$key] ?? ''; }
function GETPOSTINT($key) { return (int) GETPOST($key); }
function getDolGlobalInt($key, $default = 0) {
	global $scenario;
	return $key === 'DYNAMICPRICES_COST_USE_FOR_SALES' ? (int) ($scenario !== 'disabled') : $default;
}
function getDolGlobalString($key, $default = '') {
	global $scenario;
	if ($key === 'DYNAMICPRICES_COST_LINE_STRATEGY' && $scenario === 'never') { return 'never'; }
	return $key === 'DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY' ? 'pricelist,dynamicprices,pmp' : $default;
}
function isModEnabled($key) { return true; }
function getEntity($key) { return '1'; }
function price2num($value, $rounding = '', $option = 0) { return is_numeric($value) ? (float) $value : $value; }
function price($value) { return (string) $value; }
function dol_include_once($path) { return 1; }
$skipLogs = array();
function dol_syslog($message, $level = 0) {
	global $skipLogs;
	if (strpos($message, 'skip automatic cost recalculation:') !== false) {
		if ($level !== LOG_DEBUG) { throw new RuntimeException('Skipped enrichment must be a debug diagnostic.'); }
		$skipLogs[] = $message;
	}
}
function dol_now() { return 1700000000; }
function checkUserAccessToObject($user, $features, $object, $table = '', $feature2 = '', $key = '') {
	global $scenario;
	return !(($scenario === 'document_denied' && in_array($features[0], array('propal', 'commande', 'facture'), true))
		|| ($scenario === 'thirdparty_denied' && $features[0] === 'societe')
		|| ($scenario === 'product_denied' && $features[0] === 'produit'));
}
class User
{
	public $id = 7;
	public $admin = 1;
	public function hasRight($module, ...$permissions) {
		global $scenario, $documentType, $isTrigger;
		if (in_array($scenario, array('no_cost_right', 'admin_no_cost_right'), true) && in_array($module, array('margins', 'dynamicsprices'), true)) { return false; }
		if ($scenario === 'no_document_right' && $module === $documentType && $permissions === array($isTrigger ? 'creer' : 'lire')) { return false; }
		if ($scenario === 'no_product_right' && $module === 'produit') { return false; }
		return true;
	}
}
class Translate
{
	public function load($file) {}
	public function trans($key, ...$args) { return $key.implode(':', $args); }
}
class Product
{
	const TYPE_SERVICE = 1;
	public $id = 20;
	public $type = 0;
	public $entity = 1;
	public function __construct($db) {}
	public function fetch($id) { global $scenario; $this->entity = $scenario === 'product_entity' ? 9 : 1; return 1; }
}
class EndpointDocument
{
	public $id = 15;
	public $entity = 1;
	public $element;
	public $thirdparty;
	public function __construct($db) { global $documentType; $this->element = $documentType; }
	public function fetch($id) { global $scenario; $this->entity = $scenario === 'document_entity' ? 9 : 1; return 1; }
	public function fetch_thirdparty() { $this->thirdparty = (object) array('id' => 12, 'entity' => 1); return 1; }
}
class Propal extends EndpointDocument {}
class Commande extends EndpointDocument {}
class Facture extends EndpointDocument {}
class PriceList
{
	public static $calls = 0;
	public function __construct($db) {}
	public function get_price($product, $soc, $quantity, $document = null) {
		self::$calls++;
		global $scenario, $documentType;
		if ($product !== 20 || $soc->id !== 12 || $document->id !== 15 || $document->element !== $documentType) { throw new RuntimeException('Incorrect PriceList context.'); }
		if ($scenario === 'price_error') { return -1; }
		if ($scenario === 'no_rule') { return 0; }
		return (object) array('cost_price' => $quantity >= 20 ? 0.0 : 12.5);
	}
	public function getEffectiveCostPriceForRow($row) { return $row->cost_price; }
}
class EndpointDb
{
	public $writes = 0;
	public $queries = 0;
	public $lastQuery = '';
	public function query($sql) {
		$this->queries++;
		global $isTrigger;
		$this->lastQuery = $sql;
		if ($isTrigger) {
			if (strpos($sql, 'UPDATE ') === 0 || strpos($sql, 'INSERT ') === 0) { $this->writes++; }
		} elseif (strpos($sql, 'SELECT ') !== 0) { throw new RuntimeException('A preview attempted to mutate data.'); }
		return true;
	}
	public function fetch_object($result) {
		if (strpos($this->lastQuery, 'SELECT l.rowid') === 0) {
			if (strpos($this->lastQuery, 'AND p.entity IN (1)') === false) { throw new RuntimeException('Unscoped line lookup.'); }
			return (object) array('rowid' => 42, 'fk_product' => 20, 'qty' => 10.0, 'pa_ht' => 70, 'fk_parent' => 15, 'entity' => 1);
		}
		return (object) array('dynamic_cost_price' => 25.0, 'calculation_status' => 1, 'status' => 1);
	}
	public function num_rows($result) { return 1; }
	public function sanitize($value) { return $value; }
	public function escape($value) { return addslashes($value); }
	public function idate($value) { return '2026-09-22 12:00:00'; }
}
class Conf { public $entity = 1; }
class DolibarrTriggers
{
	const VERSIONS = array('dev' => 'development');
	public $db;
	public $name = 'test';
	public $family;
	public $description;
	public $version;
	public $picto;
	public function __construct($db) { $this->db = $db; }
}
$conf = new Conf();
$user = new User();
$user->admin = $scenario === 'admin_no_cost_right' ? 1 : 0;
if ($permissionCase !== null) { $user->admin = (int) $permissionCase['admin']; }
$langs = new Translate();
$db = new EndpointDb();
ob_start(static function ($output) { return json_encode(array('status' => http_response_code() ?: 200, 'body' => json_decode($output, true))); });
if ($isTrigger) {
	require __DIR__.'/../core/triggers/interface_99_modDynamicsPrices_DynamicsPricesTriggers.class.php';
	$trigger = new InterfaceDynamicsPricesTriggers($db);
	$line = (object) array('id' => 42, 'pa_ht' => 70.0);
	if ($permissionCase !== null) { $line->pa_ht = $permissionCase['cost']; }
	$actions = array('propal' => 'LINEPROPAL_INSERT', 'commande' => 'LINEORDER_INSERT', 'facture' => 'LINEBILL_INSERT');
	$action = $scenario === 'update' ? 'LINEPROPAL_MODIFY' : $actions[$documentType];
	$result = $trigger->runTrigger($action, $line, $user, $langs, $conf);
	print json_encode(array('result' => $result, 'cost' => $line->pa_ht, 'writes' => $db->writes,
		'queries' => $db->queries, 'price_calls' => PriceList::$calls, 'error' => $line->error ?? '', 'skip_logs' => $skipLogs));
	exit(0);
}
require __DIR__.'/../ajax/commercial_line_cost.php';
