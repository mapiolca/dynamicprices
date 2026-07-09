<?php
/* Copyright (C) 2004-2018	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019	Nicolas ZABOURI				<info@inovea-conseil.com>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2025-2026	Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   dynamicsprices     Module DynamicsPrices
 *  \brief      DynamicsPrices module descriptor.
 *
 *  \file       htdocs/dynamicsprices/core/modules/modDynamicsPrices.class.php
 *  \ingroup    dynamicsprices
 *  \brief      Description and activation file for module DynamicsPrices
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module DynamicsPrices
 */
class modDynamicsPrices extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 450002; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'dynamicsprices';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = 'Les Métiers du Bâtiment';

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleDynamicsPricesName' not found (DynamicsPrices is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// DESCRIPTION_FLAG
		// Module description, used if translation string 'ModuleDynamicsPricesDesc' not found (DynamicsPrices is name of module).
		$this->description = "DynamicsPricesDescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "DynamicsPricesDescription";

		// Author
		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = 'lesmetiersdubatiment.fr';		// Must be an external online web site
		$this->editor_squarred_logo = 'logo.png@dynamicsprices';					// Must be image filename into the module/img directory followed with @modulename. Example: 'myimage.png@dynamicsprices'

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
		$this->version = '3.0.1';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where DYNAMICSPRICES is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'margin';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 0,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			'api' => 1,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(
				//    '/dynamicsprices/css/dynamicsprices.css.php',
			),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(
				'/dynamicsprices/js/dynamicsprices_commercial_line_cost.js.php',
			),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			/* BEGIN MODULEBUILDER HOOKSCONTEXTS */
			'hooks' => array(
				'data' => array(
					'ordersuppliercard',
					'pricesuppliercard',
				),
				'entity' => '0',
			),
			/* END MODULEBUILDER HOOKSCONTEXTS */
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
			// Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
			'websitetemplates' => 0,
			// Set this to 1 if the module provides a captcha driver
			'captcha' => 0
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/dynamicsprices/temp","/dynamicsprices/subdir");
		$this->dirs = array("/dynamicsprices/temp");

		// Config pages. Put here list of php page, stored into dynamicsprices/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@dynamicsprices");

		// Dependencies
		// A condition to hide module
		$this->hidden = getDolGlobalInt('MODULE_DYNAMICSPRICES_DISABLED'); // A condition to disable module;
		// List of module class names that must be enabled if this module is enabled. Example: array('always'=>array('modModuleToEnable1','modModuleToEnable2'), 'FR'=>array('modModuleToEnableFR')...)
		$this->depends = array('modProduct', 'modFournisseur', 'modSociete');
		// List of module class names to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->requiredby = array();
		// List of module class names this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array();

		// The language file dedicated to your module
		$this->langfiles = array("dynamicsprices@dynamicsprices");

		// Prerequisites
		$this->phpmin = array(8, 0); // Minimum version of PHP required by module
		// $this->phpmax = array(8, 0); // Maximum version of PHP required by module
		$this->need_dolibarr_version = array(20, 0); // Minimum version of Dolibarr required by module
		// $this->max_dolibarr_version = array(19, -3); // Maximum version of Dolibarr required by module
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		//$this->automatic_activation = array('FR'=>'DynamicsPricesWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('DYNAMICSPRICES_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('DYNAMICSPRICES_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array(
			1=> array('PRODUCT_PRICE_UNIQ','chaine', 0, "PriceCatalogue", 1, 'current', 0),
			2=> array('PRODUIT_MULTIPRICES','chaine', 1, "MultiPricesAbility", 1, 'current', 0),
			3=> array('PRODUIT_CUSTOMER_PRICES','chaine', 0, "MultiPricesAbility", 1, 'current', 0),
			4=> array('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES', 'chaine', 0, 'PriceByCustomeAndMultiPricesAbility', 1, 'current', 0),
			5=> array('PRODUIT_MULTIPRICES_LIMIT', 'chaine', 1, 'MultiPricesNumPrices', 1, 'current', 0),
			6=> array('DYNAMICPRICES_COST_ENABLE', 'yesno', 1, 'DynamicPricesCostEnable', 1, 'current', 0),
			7=> array('DYNAMICPRICES_COST_USE_FOR_SALES', 'yesno', 0, 'DynamicPricesCostUseForSales', 1, 'current', 0),
			8=> array('DYNAMICPRICES_COST_LINE_STRATEGY', 'chaine', 'on_create_only', 'DynamicPricesCostLineStrategy', 1, 'current', 0),
			9=> array('DYNAMICPRICES_COST_FALLBACK', 'chaine', 'keep_dolibarr', 'DynamicPricesCostFallback', 1, 'current', 0),
			10=> array('DYNAMICPRICES_COST_SOURCE_PRIORITY', 'chaine', 'supplier_average', 'DynamicPricesCostSourcePriority', 0, 'current', 0),
			11=> array('DYNAMICPRICES_COST_INCLUDE_SERVICES', 'yesno', 0, 'DynamicPricesCostIncludeServices', 1, 'current', 0),
			12=> array('DYNAMICPRICES_COST_RECALC_KITS', 'yesno', 1, 'DynamicPricesCostRecalcKits', 1, 'current', 0),
			13=> array('DYNAMICPRICES_COST_ROUNDING_MODE', 'chaine', 'dolibarr', 'DynamicPricesCostRoundingMode', 1, 'current', 0),
			14=> array('DYNAMICPRICES_COST_LOG_MODE', 'chaine', 'changes_only', 'DynamicPricesCostLogMode', 1, 'current', 0),
			15=> array('DYNAMICPRICES_COST_ALLOW_MANUAL_OVERRIDE', 'yesno', 1, 'DynamicPricesCostAllowManualOverride', 1, 'current', 0),
			16=> array('DYNAMICPRICES_COST_ALLOW_NATIVE_WRITE', 'yesno', 0, 'DynamicPricesCostAllowNativeWrite', 1, 'current', 0),
			17=> array('DYNAMICPRICES_COST_DEBUG_LOG', 'yesno', 0, 'DynamicPricesCostDebugLog', 1, 'current', 0),
			18=> array('DYNAMICPRICES_COST_LINE_SOURCE_PRIORITY', 'chaine', 'dynamicprices,dolibarr_default,pmp,native_cost_price', 'DynamicPricesCostLineSourcePriority', 1, 'current', 0),
			19=> array('DYNAMICPRICES_SHARED_SELL_PRICE_SOURCE_ENTITY', 'chaine', '0', 'DynamicPricesSharedSellPriceSourceEntity', 1, 'current', 0),
		);

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isModEnabled("dynamicsprices")) {
			$conf->dynamicsprices = new stdClass();
			$conf->dynamicsprices->enabled = 0;
		}

		// Array to add new pages in new tabs
		/* BEGIN MODULEBUILDER TABS */
		$this->tabs = array();
		/* END MODULEBUILDER TABS */
		// Example:
		// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data' => 'objecttype:+tabname1:Title1:mylangfile@dynamicsprices:$user->hasRight(\'dynamicsprices\', \'read\'):/dynamicsprices/mynewtab1.php?id=__ID__');
		// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data' => 'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@dynamicsprices:$user->hasRight(\'othermodule\', \'read\'):/dynamicsprices/mynewtab2.php?id=__ID__',
		// To remove an existing tab identified by code tabname
		// $this->tabs[] = array('data' => 'objecttype:-tabname:NU:conditiontoremove');
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'delivery'         to add a tab in delivery view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in foundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in sale order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view


		// Dictionaries
		/* Example:
		 $this->dictionaries=array(
		 'langs' => 'dynamicsprices@dynamicsprices',
		 // List of tables we want to see into dictionary editor
		 'tabname' => array("table1", "table2", "table3"),
		 // Label of tables
		 'tablib' => array("Table1", "Table2", "Table3"),
		 // Request to select fields
		 'tabsql' => array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table1 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table2 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table3 as f'),
		 // Sort order
		 'tabsqlsort' => array("label ASC", "label ASC", "label ASC"),
		 // List of fields (result of select to show dictionary)
		 'tabfield' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields to edit a record)
		 'tabfieldvalue' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields for insert)
		 'tabfieldinsert' => array("code,label", "code,label", "code,label"),
		 // Name of columns with primary key (try to always name it 'rowid')
		 'tabrowid' => array("rowid", "rowid", "rowid"),
		 // Condition to show each dictionary
		 'tabcond' => array(isModEnabled('dynamicsprices'), isModEnabled('dynamicsprices'), isModEnabled('dynamicsprices')),
		 // Tooltip for every fields of dictionaries: DO NOT PUT AN EMPTY ARRAY
		 'tabhelp' => array(array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), ...),
		 );
		 */
/* BEGIN MODULEBUILDER DICTIONARIES */
		$coefPriceDictionaryKey = 0;
		$marginOnCostDictionaryKey = 1;
		$commercialCategoryDictionaryKey = 2;
		$commercialCategoryHasEntity = $this->columnExists(MAIN_DB_PREFIX."c_commercial_category", 'entity');
		$coefPriceEntityList = getEntity('c_coefprice');
		$marginOnCostEntityList = getEntity('c_margin_on_cost');
		$commercialCategoryEntityList = getEntity('product');
		$commercialCategorySelectSql = $commercialCategoryHasEntity
			? 'SELECT t.rowid as rowid, t.entity, t.code, t.label, t.active FROM '.MAIN_DB_PREFIX.'c_commercial_category AS t WHERE t.entity IN ('.$commercialCategoryEntityList.')'
			: 'SELECT t.rowid as rowid, t.code, t.label, t.active FROM '.MAIN_DB_PREFIX.'c_commercial_category AS t';
		$commercialCategoryFieldValue = $commercialCategoryHasEntity ? "code,entity,label" : "code,label";
		$commercialCategoryLabelSql = $commercialCategoryHasEntity
			? '(SELECT cc.label FROM '.MAIN_DB_PREFIX.'c_commercial_category AS cc WHERE cc.code = t.code_commercial_category AND cc.entity IN ('.$commercialCategoryEntityList.') ORDER BY CASE WHEN cc.entity = t.entity THEN 0 WHEN cc.entity = '.((int) $conf->entity).' THEN 1 ELSE 2 END, cc.rowid ASC LIMIT 1)'
			: '(SELECT cc.label FROM '.MAIN_DB_PREFIX.'c_commercial_category AS cc WHERE cc.code = t.code_commercial_category ORDER BY cc.rowid ASC LIMIT 1)';

		$this->dictionaries = array(
			'langs' => 'dynamicsprices@dynamicsprices',
			'tabname' => array(
				$coefPriceDictionaryKey => "c_coefprice",
				$marginOnCostDictionaryKey => "c_margin_on_cost",
				$commercialCategoryDictionaryKey => "c_commercial_category",
			),
			'tablib' => array(
				$coefPriceDictionaryKey => "LMDB_coefprice",
				$marginOnCostDictionaryKey => "LMDB_marginoncost",
				$commercialCategoryDictionaryKey => "LMDB_commercialcategories",
			),
			'tabsql' => array(
				$coefPriceDictionaryKey => 'SELECT t.rowid as rowid, t.entity, t.code, t.code_commercial_category, '.$commercialCategoryLabelSql.' as commercial_category_label, t.pricelevel, t.minrate, t.targetrate, t.active FROM '.MAIN_DB_PREFIX.'c_coefprice AS t WHERE t.entity IN ('.$coefPriceEntityList.')',
				$marginOnCostDictionaryKey => 'SELECT t.rowid as rowid, t.entity, t.code, t.code_commercial_category, '.$commercialCategoryLabelSql.' as commercial_category_label, t.margin_on_cost_percent, t.active FROM '.MAIN_DB_PREFIX.'c_margin_on_cost AS t WHERE t.entity IN ('.$marginOnCostEntityList.')',
				$commercialCategoryDictionaryKey => $commercialCategorySelectSql,
			),
			'tabsqlsort' => array(
				$coefPriceDictionaryKey => "code ASC",
				$marginOnCostDictionaryKey => "code ASC",
				$commercialCategoryDictionaryKey => "label ASC",
			),
			'tabfield' => array(
				$coefPriceDictionaryKey => "code,code_commercial_category,pricelevel,targetrate,minrate",
				$marginOnCostDictionaryKey => "code,code_commercial_category,margin_on_cost_percent",
				$commercialCategoryDictionaryKey => "code,label",
			),
			'tabfieldvalue' => array(
				$coefPriceDictionaryKey => "code,entity,code_commercial_category,pricelevel,targetrate,minrate",
				$marginOnCostDictionaryKey => "code,entity,code_commercial_category,margin_on_cost_percent",
				$commercialCategoryDictionaryKey => $commercialCategoryFieldValue,
			),
			'tabfieldinsert' => array(
				$coefPriceDictionaryKey => "code,entity,code_commercial_category,pricelevel,targetrate,minrate",
				$marginOnCostDictionaryKey => "code,entity,code_commercial_category,margin_on_cost_percent",
				$commercialCategoryDictionaryKey => $commercialCategoryFieldValue,
			),
			'tabrowid' => array(
				$coefPriceDictionaryKey => 'rowid',
				$marginOnCostDictionaryKey => 'rowid',
				$commercialCategoryDictionaryKey => 'rowid',
			),
			'tabcond' => array(
				$coefPriceDictionaryKey => isModEnabled('dynamicsprices'),
				$marginOnCostDictionaryKey => isModEnabled('dynamicsprices'),
				$commercialCategoryDictionaryKey => isModEnabled('dynamicsprices'),
			),
			'tabhelp' => array(
				$coefPriceDictionaryKey => array(
					'code' => $langs->trans('LMDB_CodeTooltipHelp'),
					'entity' => $langs->trans('LMDB_ENtityTooltipHelp'),
					'code_commercial_category' => $langs->trans('LMDB_CodeCommercialCategoryTooltipHelp'),
					'commercial_category_label' => $langs->trans('LMDB_CommercialCategoryLabelTooltipHelp'),
					'pricelevel' => $langs->trans('LMDB_PriceLevelTooltipHelp'),
					'targetrate' => $langs->trans('LMDB_TargetRateTooltipHelp'),
					'minrate' => $langs->trans('LMDB_MinRateTooltipHelp'),
					'active' => $langs->trans('LMDB_ActiveTooltipHelp'),
				),
				$marginOnCostDictionaryKey => array(
					'code' => $langs->trans('LMDB_CodeTooltipHelp'),
					'entity' => $langs->trans('LMDB_ENtityTooltipHelp'),
					'code_commercial_category' => $langs->trans('LMDB_CodeCommercialCategoryTooltipHelp'),
					'commercial_category_label' => $langs->trans('LMDB_CommercialCategoryLabelTooltipHelp'),
					'margin_on_cost_percent' => $langs->trans('LMDB_MarginOnCostTooltipHelp'),
					'active' => $langs->trans('LMDB_ActiveTooltipHelp'),
				),
				$commercialCategoryDictionaryKey => array(
					'code' => $langs->trans('LMDB_CodeTooltipHelp'),
					'entity' => $langs->trans('LMDB_ENtityTooltipHelp'),
					'label' => $langs->trans('LMDB_LabelTooltipHelp'),
					'active' => $langs->trans('LMDB_ActiveTooltipHelp'),
				),
			),
		);
/* END MODULEBUILDER DICTIONARIES */

		// Boxes/Widgets
		// Add here list of php file(s) stored in dynamicsprices/core/boxes that contains a class to show a widget.
		/* BEGIN MODULEBUILDER WIDGETS */
		$this->boxes = array(
			//  0 => array(
			//      'file' => 'dynamicspriceswidget1.php@dynamicsprices',
			//      'note' => 'Widget provided by DynamicsPrices',
			//      'enabledbydefaulton' => 'Home',
			//  ),
			//  ...
		);
		/* END MODULEBUILDER WIDGETS */

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		/* BEGIN MODULEBUILDER CRON */
		$langs->load("dynamicsprices@dynamicsprices");
		$this->cronjobs = array(
			//  0 => array(
			//      'label' => 'MyJob label',
			//      'jobtype' => 'method',
			//      'class' => '/dynamicsprices/class/myobject.class.php',
			//      'objectname' => 'MyObject',
			//      'method' => 'doScheduledJob',
			//      'parameters' => '',
			//      'comment' => 'Comment',
			//      'frequency' => 2,
			//      'unitfrequency' => 3600,
			//      'status' => 0,
			//      'test' => 'isModEnabled("dynamicsprices")',
			//      'priority' => 50,
			//  ),
			0 => array(
			      'label' => $langs->trans("LMDB_LabelAutoUpdateSellPrice"),
			      'jobtype' => 'method',
			      'class' => '/dynamicsprices/class/cron_dynamicsprices.class.php',
			      'objectname' => 'Cron_DynamicsPrices',
			      'method' => 'updatePrices',
			      'parameters' => '',
			      'comment' => $langs->trans("LMDB_CommentAutoUpdateSellPrice"),
			      'frequency' => 1,
			      'unitfrequency' => 86400,
			      'status' => 1,
			      'test' => 'isModEnabled("dynamicsprices")',
			      'priority' => 50,
			  ),
		);
		/* END MODULEBUILDER CRON */
		// Example: $this->cronjobs=array(
		//    0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>'isModEnabled("dynamicsprices")', 'priority'=>50),
		//    1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>'isModEnabled("dynamicsprices")', 'priority'=>50)
		// );

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'DynamicPricesCostRead';
		$this->rights[$r][4] = 'cost';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'DynamicPricesCostWrite';
		$this->rights[$r][4] = 'cost';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'DynamicPricesCostMassUpdateRight';
		$this->rights[$r][4] = 'cost';
		$this->rights[$r][5] = 'massupdate';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'DynamicPricesCostAdmin';
		$this->rights[$r][4] = 'cost';
		$this->rights[$r][5] = 'admin';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'DynamicPricesCostHistoryRight';
		$this->rights[$r][4] = 'cost';
		$this->rights[$r][5] = 'history';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'DynamicPricesCostOverride';
		$this->rights[$r][4] = 'cost';
		$this->rights[$r][5] = 'override';
		/* END MODULEBUILDER PERMISSIONS */


		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		/*
		$this->menu[$r++] = array(
			'fk_menu' => '', // Will be stored into mainmenu + leftmenu. Use '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top', // This is a Top menu entry
			'titre' => 'ModuleDynamicsPricesName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dynamicsprices',
			'leftmenu' => '',
			'url' => '/dynamicsprices/dynamicspricesindex.php',
			'langs' => 'dynamicsprices@dynamicsprices', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dynamicsprices")', // Define condition to show or hide menu entry. Use 'isModEnabled("dynamicsprices")' if entry must be visible if module is enabled.
			'perms' => '1', // Use 'perms'=>'$user->hasRight("dynamicsprices", "myobject", "read")' if you want your menu with a permission rules
			'target' => '',
			'user' => 2, // 0=Menu for internal users, 1=external users, 2=both
		);
		*/
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		/*
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=dynamicsprices',      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                          // This is a Left menu entry
			'titre' => 'MyObject',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'dynamicsprices',
			'leftmenu' => 'myobject',
			'url' => '/dynamicsprices/dynamicspricesindex.php',
			'langs' => 'dynamicsprices@dynamicsprices',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dynamicsprices")', // Define condition to show or hide menu entry. Use 'isModEnabled("dynamicsprices")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("dynamicsprices", "myobject", "read")',
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'MyObject'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=dynamicsprices,fk_leftmenu=myobject',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'New_MyObject',
			'mainmenu' => 'dynamicsprices',
			'leftmenu' => 'dynamicsprices_myobject_new',
			'url' => '/dynamicsprices/myobject_card.php?action=create',
			'langs' => 'dynamicsprices@dynamicsprices',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dynamicsprices")', // Define condition to show or hide menu entry. Use 'isModEnabled("dynamicsprices")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->hasRight("dynamicsprices", "myobject", "write")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'MyObject'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=dynamicsprices,fk_leftmenu=myobject',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'List_MyObject',
			'mainmenu' => 'dynamicsprices',
			'leftmenu' => 'dynamicsprices_myobject_list',
			'url' => '/dynamicsprices/myobject_list.php',
			'langs' => 'dynamicsprices@dynamicsprices',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dynamicsprices")', // Define condition to show or hide menu entry. Use 'isModEnabled("dynamicsprices")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("dynamicsprices", "myobject", "read")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'MyObject'
		);
		*/
		/* END MODULEBUILDER LEFTMENU MYOBJECT */


		// Exports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER EXPORT MYOBJECT */
		$langs->load("dynamicsprices@dynamicsprices");
		$this->export_code[$r] = $this->rights_class.'_dynamic_cost';
		$this->export_label[$r] = 'DynamicPricesCostExport';
		$this->export_icon[$r] = $this->picto;
		$this->export_fields_array[$r] = array(
			'c.entity' => 'Entity',
			'c.fk_product' => 'ProductId',
			'p.ref' => 'ProductRef',
			'p.label' => 'ProductLabel',
			'p.fk_product_type' => 'Type',
			'p.cost_price' => 'DynamicPricesNativeCostPrice',
			'p.pmp' => 'DynamicPricesPmp',
			'c.dynamic_cost_price' => 'DynamicPricesDynamicCostPrice',
			'c.source_type' => 'DynamicPricesCostSource',
			'c.source_value' => 'DynamicPricesCostSourceValue',
			'c.rule_code' => 'DynamicPricesCostRule',
			'c.coefficient' => 'DynamicPricesCostCoefficient',
			'c.date_calculation' => 'DynamicPricesCostLastCalculation',
			'c.calculation_status' => 'DynamicPricesCostStatus',
			'c.calculation_message' => 'DynamicPricesCostLastMessage',
		);
		$this->export_TypeFields_array[$r] = array(
			'c.entity' => 'Numeric',
			'c.fk_product' => 'Numeric',
			'p.ref' => 'Text',
			'p.label' => 'Text',
			'p.fk_product_type' => 'Numeric',
			'p.cost_price' => 'Numeric',
			'p.pmp' => 'Numeric',
			'c.dynamic_cost_price' => 'Numeric',
			'c.source_type' => 'Text',
			'c.source_value' => 'Numeric',
			'c.rule_code' => 'Text',
			'c.coefficient' => 'Numeric',
			'c.date_calculation' => 'Date',
			'c.calculation_status' => 'Numeric',
			'c.calculation_message' => 'Text',
		);
		$this->export_entities_array[$r] = array(
			'c.entity' => 'product',
			'c.fk_product' => 'product',
			'p.ref' => 'product',
			'p.label' => 'product',
			'p.fk_product_type' => 'product',
			'p.cost_price' => 'product',
			'p.pmp' => 'product',
			'c.dynamic_cost_price' => 'product',
			'c.source_type' => 'product',
			'c.source_value' => 'product',
			'c.rule_code' => 'product',
			'c.coefficient' => 'product',
			'c.date_calculation' => 'product',
			'c.calculation_status' => 'product',
			'c.calculation_message' => 'product',
		);
		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM '.$this->db->prefix().'dynamicprices_product_cost AS c';
		$this->export_sql_end[$r] .=' LEFT JOIN '.$this->db->prefix().'product AS p ON p.rowid = c.fk_product';
		$this->export_sql_end[$r] .=' WHERE 1 = 1';
		$this->export_sql_end[$r] .=' AND c.entity = '.((int) $conf->entity);
		$r++;
		/* END MODULEBUILDER EXPORT MYOBJECT */

		// Imports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER IMPORT MYOBJECT */
		/*
		$langs->load("dynamicsprices@dynamicsprices");
		$this->import_code[$r] = $this->rights_class.'_'.$r;
		$this->import_label[$r] = 'MyObjectLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->import_icon[$r] = $this->picto;
		$this->import_tables_array[$r] = array('t' => $this->db->prefix().'dynamicsprices_myobject', 'extra' => $this->db->prefix().'dynamicsprices_myobject_extrafields');
		$this->import_tables_creator_array[$r] = array('t' => 'fk_user_author'); // Fields to store import user id
		$import_sample = array();
		$keyforclass = 'MyObject'; $keyforclassfile='/dynamicsprices/class/myobject.class.php'; $keyforelement='myobject@dynamicsprices';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinimport.inc.php';
		$import_extrafield_sample = array();
		$keyforselect='myobject'; $keyforaliasextra='extra'; $keyforelement='myobject@dynamicsprices';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinimport.inc.php';
		$this->import_fieldshidden_array[$r] = array('extra.fk_object' => 'lastrowid-'.$this->db->prefix().'dynamicsprices_myobject');
		$this->import_regex_array[$r] = array();
		$this->import_examplevalues_array[$r] = array_merge($import_sample, $import_extrafield_sample);
		$this->import_updatekeys_array[$r] = array('t.ref' => 'Ref');
		$this->import_convertvalue_array[$r] = array(
			't.ref' => array(
				'rule'=>'getrefifauto',
				'class'=>(!getDolGlobalString('DYNAMICSPRICES_MYOBJECT_ADDON') ? 'mod_myobject_standard' : getDolGlobalString('DYNAMICSPRICES_MYOBJECT_ADDON')),
				'path'=>"/core/modules/dynamicsprices/".(!getDolGlobalString('DYNAMICSPRICES_MYOBJECT_ADDON') ? 'mod_myobject_standard' : getDolGlobalString('DYNAMICSPRICES_MYOBJECT_ADDON')).'.php',
				'classobject'=>'MyObject',
				'pathobject'=>'/dynamicsprices/class/myobject.class.php',
			),
			't.fk_soc' => array('rule' => 'fetchidfromref', 'file' => '/societe/class/societe.class.php', 'class' => 'Societe', 'method' => 'fetch', 'element' => 'ThirdParty'),
			't.fk_user_valid' => array('rule' => 'fetchidfromref', 'file' => '/user/class/user.class.php', 'class' => 'User', 'method' => 'fetch', 'element' => 'user'),
			't.fk_mode_reglement' => array('rule' => 'fetchidfromcodeorlabel', 'file' => '/compta/paiement/class/cpaiement.class.php', 'class' => 'Cpaiement', 'method' => 'fetch', 'element' => 'cpayment'),
		);
		$this->import_run_sql_after_array[$r] = array();
		$r++; */
		/* END MODULEBUILDER IMPORT MYOBJECT */
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		global $conf;

		// Create tables of module at module activation
		//$result = $this->_load_tables('/install/mysql/', 'dynamicsprices');
		$result = $this->_load_tables('/dynamicsprices/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}
		$result = $this->ensureCommercialCategoryColumns();
		if ($result < 0) {
			return -1;
		}

		// Create product/service extrafield during init.
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);
		$extrafields->fetch_name_optionals_label('product');
		if (empty($extrafields->attributes['product']['label']['lmdb_commercial_category'])) {
			$result = $extrafields->addExtraField(
				'lmdb_commercial_category',
				'LMDB_CommercialCategoryExtrafield',
				'sellist',
				100,
				255,
				'product',
				0,
				0,
				'',
				array('options' => array('c_commercial_category:label:rowid::(active:=:1)' => null)),
				1,
				'',
				-1,
				'',
				'',
				'',
				'dynamicsprices@dynamicsprices',
				'isModEnabled("dynamicsprices")'
			);
			if ($result < 0) {
				$this->error = $extrafields->error;
				return -1;
			}
		}

		$this->skipExistingCronjobsAcrossEntities();

		$sql = array();

		// Document templates
		$moduledir = dol_sanitizeFileName('dynamicsprices');
		$myTmpObjects = array();
		$myTmpObjects['MyObject'] = array('includerefgeneration' => 0, 'includedocgeneration' => 0);

		foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
			if ($myTmpObjectArray['includerefgeneration']) {
				$src = DOL_DOCUMENT_ROOT.'/install/doctemplates/'.$moduledir.'/template_myobjects.odt';
				$dirodt = DOL_DATA_ROOT.($conf->entity > 1 ? '/'.$conf->entity : '').'/doctemplates/'.$moduledir;
				$dest = $dirodt.'/template_myobjects.odt';

				if (file_exists($src) && !file_exists($dest)) {
					require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
					dol_mkdir($dirodt);
					$result = dol_copy($src, $dest, '0', 0);
					if ($result < 0) {
						$langs->load("errors");
						$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
						return 0;
					}
				}

				$sql = array_merge($sql, array(
					"DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'standard_".strtolower($myTmpObjectKey)."' AND type = '".$this->db->escape(strtolower($myTmpObjectKey))."' AND entity = ".((int) $conf->entity),
					"INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES('standard_".strtolower($myTmpObjectKey)."', '".$this->db->escape(strtolower($myTmpObjectKey))."', ".((int) $conf->entity).")",
					"DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'generic_".strtolower($myTmpObjectKey)."_odt' AND type = '".$this->db->escape(strtolower($myTmpObjectKey))."' AND entity = ".((int) $conf->entity),
					"INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES('generic_".strtolower($myTmpObjectKey)."_odt', '".$this->db->escape(strtolower($myTmpObjectKey))."', ".((int) $conf->entity).")"
				));
			}
		}

		return $this->_init($sql, $options);
	}

	/**
	 * Check if a column exists on a table.
	 *
	 * @param string $tableName Table name
	 * @param string $columnName Column name
	 * @return bool
	 */
	private function columnExists($tableName, $columnName)
	{
		$sql = "SHOW COLUMNS FROM ".$tableName." LIKE '".$this->db->escape($columnName)."'";
		$resql = $this->db->query($sql);

		return ($resql && $this->db->num_rows($resql) > 0);
	}

	/**
	 * Add and populate code_commercial_category columns for module dictionaries.
	 *
	 * @return int
	 */
	private function ensureCommercialCategoryColumns()
	{
		$queries = array();

		if (!$this->columnExists(MAIN_DB_PREFIX."c_coefprice", 'code_commercial_category')) {
			$queries[] = "ALTER TABLE ".MAIN_DB_PREFIX."c_coefprice ADD COLUMN code_commercial_category VARCHAR(50) DEFAULT NULL";
		}
		if (!$this->columnExists(MAIN_DB_PREFIX."c_margin_on_cost", 'code_commercial_category')) {
			$queries[] = "ALTER TABLE ".MAIN_DB_PREFIX."c_margin_on_cost ADD COLUMN code_commercial_category VARCHAR(50) DEFAULT NULL";
		}
		if ($this->columnExists(MAIN_DB_PREFIX."c_coefprice", 'fk_nature')) {
			$queries[] = "UPDATE ".MAIN_DB_PREFIX."c_coefprice SET code_commercial_category = fk_nature WHERE (code_commercial_category IS NULL OR code_commercial_category = '') AND fk_nature IS NOT NULL AND fk_nature <> ''";
		}
		if ($this->columnExists(MAIN_DB_PREFIX."c_margin_on_cost", 'code_nature')) {
			$queries[] = "UPDATE ".MAIN_DB_PREFIX."c_margin_on_cost SET code_commercial_category = code_nature WHERE (code_commercial_category IS NULL OR code_commercial_category = '') AND code_nature IS NOT NULL AND code_nature <> ''";
		}

		foreach ($queries as $sql) {
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Keep already configured cron jobs when the module is activated from another entity.
	 *
	 * Dolibarr core checks existing cron jobs with the current entity only. DynamicPrices
	 * has a single functional scheduled job, so a job already present in any entity must
	 * be reused instead of creating another copy during a Multicompany activation.
	 *
	 * @return void
	 */
	private function skipExistingCronjobsAcrossEntities()
	{
		if (empty($this->cronjobs) || !is_array($this->cronjobs)) {
			return;
		}

		$moduleName = empty($this->rights_class) ? strtolower($this->name) : $this->rights_class;
		foreach ($this->cronjobs as $key => $cronjob) {
			if (!is_array($cronjob) || !$this->doesCronjobAlreadyExistAcrossEntities($cronjob, $moduleName)) {
				continue;
			}

			unset($this->cronjobs[$key]);
		}
	}

	/**
	 * Check if a cron job already exists, without restricting the lookup to current entity.
	 *
	 * @param array<string,mixed> $cronjob Cron job descriptor
	 * @param string $moduleName Module name stored in llx_cronjob
	 * @return bool
	 */
	private function doesCronjobAlreadyExistAcrossEntities(array $cronjob, $moduleName)
	{
		$jobtype = !empty($cronjob['jobtype']) ? (string) $cronjob['jobtype'] : '';
		$classesname = !empty($cronjob['class']) ? (string) $cronjob['class'] : '';
		$objectname = !empty($cronjob['objectname']) ? (string) $cronjob['objectname'] : '';
		$methodename = !empty($cronjob['method']) ? (string) $cronjob['method'] : '';
		$command = !empty($cronjob['command']) ? (string) $cronjob['command'] : '';
		$params = array_key_exists('parameters', $cronjob) ? (string) $cronjob['parameters'] : '';
		$label = !empty($cronjob['label']) ? (string) $cronjob['label'] : '';

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."cronjob";
		$sql .= " WHERE module_name = '".$this->db->escape($moduleName)."'";
		$sql .= " AND jobtype = '".$this->db->escape($jobtype)."'";
		if ($jobtype === 'method') {
			$sql .= " AND classesname = '".$this->db->escape($classesname)."'";
			$sql .= " AND objectname = '".$this->db->escape($objectname)."'";
			$sql .= " AND methodename = '".$this->db->escape($methodename)."'";
			$sql .= " AND params = '".$this->db->escape($params)."'";
		} elseif ($jobtype === 'command') {
			$sql .= " AND command = '".$this->db->escape($command)."'";
			$sql .= " AND params = '".$this->db->escape($params)."'";
		} else {
			$sql .= " AND label = '".$this->db->escape($label)."'";
		}
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return is_object($this->db->fetch_object($resql));
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}

