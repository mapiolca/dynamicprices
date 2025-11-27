<?php
/* Copyright (C) 2025 Pierre Ardoin		<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modDynamicsPrices_DynamicsPricesTriggers.class.php
 * \ingroup dynamicsprices
 * \brief   Triggers for DynamicPrices module.
 *
 * This class handles price recalculation hooks for DynamicPrices module.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class of triggers for DynamicsPrices module
 */
class InterfaceDynamicsPricesTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * EN: Initialize trigger metadata
	 * FR: Initialiser les métadonnées du trigger
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = 'product';
		$this->description = 'Dynamicsprices triggers.';
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'logo.png@dynamicsprices';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 * All functions "runTrigger" are triggered if the file is inside the directory core/triggers
	 *
	 * EN: Dispatch DynamicPrices reactions for supported actions
	 * FR: Dispatcher les réactions DynamicPrices pour les actions supportées

	 * @param string       $action Event action code
	 * @param CommonObject $object Object
	 * @param User         $user   Object user
	 * @param Translate    $langs  Object langs
	 * @param Conf         $conf   Object conf
	 * @return int                 Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		global $db;

		if (!isModEnabled('dynamicsprices')) {
			var_dump('DynamicPrices trigger skipped: module disabled');
			return 0; // If module is not enabled, we do nothing
		}

		if (!getDolGlobalString('LMDB_COST_PRICE_ONLY')) {
			if (in_array($action, array('SUPPLIER_PRODUCT_BUYPRICE_CREATE', 'SUPPLIER_PRODUCT_BUYPRICE_MODIFY', 'SUPPLIER_PRODUCT_BUYPRICE_DELETE'), true)) {
				require_once __DIR__.'/../../lib/dynamicsprices.lib.php';
				if (getDolGlobalString('LMDB_SUPPLIER_BUYPRICE_ALTERED')) {
					var_dump('Trigger recalculation from supplier prices for product id', $this->getProductId($object));
					update_customer_prices_from_suppliers($db, $user, $langs, $conf, $this->getProductId($object));
				}
			}
		} else {
			if ($action === 'PRODUCT_MODIFY') {
				require_once __DIR__.'/../../lib/dynamicsprices.lib.php';
				if (getDolGlobalString('LMDB_SUPPLIER_BUYPRICE_ALTERED')) {
					var_dump('Trigger recalculation from cost price for product id', $this->getProductId($object));
					update_customer_prices_from_cost_price($db, $user, $langs, $conf, $this->getProductId($object));
				}
			}
		}

		if (in_array($action, array('PRODUCT_MODIFY', 'PRODUCT_BUYPRICE_MODIFY', 'PRODUCT_BUYPRICE_DELETE', 'PRODUCT_SUBPRODUCT_ADD', 'PRODUCT_SUBPRODUCT_UPDATE', 'PRODUCT_SUBPRODUCT_DELETE'), true)) {
			var_dump('Trigger kit price update for action', $action);
			$this->triggerKitPriceUpdate($object, $user, $langs, $conf);
		}

		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		}

		return 0;
	}

	/**
	 * Launch kit price recalculation when a kit or its buy prices are updated.
	 *
	 * EN: Recalculate kit cost and selling prices after component or kit updates
	 * FR: Recalculer le coût et les prix de vente du kit après mise à jour du kit ou des composants
	 *
	 * @param CommonObject $object Triggered object
	 * @param User         $user   Current user
	 * @param Translate    $langs  Language handler
	 * @param Conf         $conf   Global configuration
	 * @return int                 >0 if a kit was processed, 0 otherwise
	 */
	private function triggerKitPriceUpdate($object, User $user, Translate $langs, Conf $conf)
	{
		global $db;

		$productId = $this->getProductId($object);
		if (empty($productId)) {
			var_dump('No product id detected for trigger, skipping kit update');
			return 0;
		}

		dol_include_once('/product/class/product.class.php');
		require_once __DIR__.'/../../lib/dynamicsprices.lib.php';

		$product = new Product($db);
		if ($product->fetch($productId) <= 0) {
			var_dump('Unable to fetch product for kit update', $productId);
			return 0;
		}

		$components = dynamicsPricesGetKitComponents($db, $product);
		if (empty($components)) {
			var_dump('Product is not a kit or has no components', $productId);
			return 0;
		}

		if (getDolGlobalString('LMDB_COST_PRICE_ONLY')) {
			var_dump('Updating kit prices from cost price strategy for product id', $productId);
			update_customer_prices_from_cost_price($db, $user, $langs, $conf, $productId);
		} else {
			var_dump('Updating kit prices from supplier price strategy for product id', $productId);
			update_customer_prices_from_suppliers($db, $user, $langs, $conf, $productId);
		}

		return 1;
	}

	/**
	 * Extract product id from triggered object.
	 *
	 * EN: Identify the target product regardless of trigger payload structure
	 * FR: Identifier le produit cible quelle que soit la structure de l'objet du trigger
	 *
	 * @param CommonObject $object Triggered object
	 * @return int                 Product id if found, 0 otherwise
	 */
	private function getProductId($object)
	{
		if (!empty($object->fk_product_pere)) {
			return (int) $object->fk_product_pere;
		}

		if (!empty($object->fk_product)) {
			return (int) $object->fk_product;
		}

		if (!empty($object->id)) {
			return (int) $object->id;
		}

		if (!empty($object->productid)) {
			return (int) $object->productid;
		}

		return 0;
	}
}
