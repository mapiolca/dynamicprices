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

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	header('Content-Type: application/javascript; charset=UTF-8');
	exit;
}

header('Content-Type: application/javascript; charset=UTF-8');
?>
(function() {
	'use strict';

	var optionValue = 'dynamicsprices_cost';
	var priceListOptionValue = 'dynamicsprices_pricelist_cost';
	var automaticOptionValue = 'dynamicsprices_automatic_cost';
	var endpoint = '<?php echo dol_buildpath('/dynamicsprices/ajax/commercial_line_cost.php', 1); ?>';
	var currentProductId = '';
	var userTouchedCostSelect = false;
	var isApplyingAutomaticCost = false;
	var pendingTimer = null;
	var requestRevision = 0;
	var nativeCost = '';
	var nativeOptionValue = '';
	var previewError = '';
	var manualSource = '';
	var defaultPriority = ['dynamicprices', 'dolibarr_default', 'pmp', 'native_cost_price'];

	function getCommercialLineForm() {
		var actionInput = document.querySelector('form input[name="action"][value="addline"]');
		if (actionInput && actionInput.form) {
			return actionInput.form;
		}

		var form = document.querySelector('form[name="addproduct"]');
		if (form) {
			var formActionInput = form.querySelector('input[name="action"]');
			if (formActionInput && formActionInput.value === 'addline') {
				return form;
			}
		}

		return null;
	}

	function getProductId(form) {
		if (!form) {
			return '';
		}

		var productSelect = form.querySelector('select[name="idprod"]');
		if (productSelect && Number(productSelect.value) > 0) {
			return productSelect.value;
		}

		var productInput = form.querySelector('input[name="productid"], input[name="idprod"]');
		return productInput && Number(productInput.value) > 0 ? productInput.value : '';
	}

	function getCostSelect(form) {
		return form ? form.querySelector('select[name="fournprice_predef"], select[name="fournprice"]') : null;
	}

	function getBuyingPriceInput(form) {
		return form ? form.querySelector('input[name="buying_price_predef"], input[name="buying_price"]') : null;
	}

	function getDocumentContext(form) {
		var path = window.location.pathname;
		var type = /\/comm\/propal\/card\.php$/.test(path) ? 'propal' :
			(/\/commande\/card\.php$/.test(path) ? 'commande' : (/\/compta\/facture\/card\.php$/.test(path) ? 'facture' : ''));
		var idInput = form.querySelector('input[name="id"], input[name="facid"]');
		var params = new URLSearchParams(window.location.search);
		var id = idInput ? idInput.value : (params.get('id') || params.get('facid') || '');
		var quantity = form.querySelector('input[name="qty"]');
		var salePrice = form.querySelector('input[name="price_ht"]');
		return { type: type, id: id, qty: quantity ? quantity.value : '', salePrice: salePrice ? salePrice.value : '' };
	}

	function contextKey(form) {
		var context = getDocumentContext(form);
		return [getProductId(form), context.type, context.id, context.qty, context.salePrice].join('|');
	}

	function showPreviewError(form, message) {
		previewError = message || '';
		var status = form.querySelector('[data-dynamicsprices-cost-error]');
		if (!status && previewError) {
			status = document.createElement('span');
			status.className = 'error';
			status.setAttribute('data-dynamicsprices-cost-error', '1');
			status.setAttribute('role', 'alert');
			getCostSelect(form).parentNode.appendChild(status);
		}
		if (status) { status.textContent = previewError; }
	}

	function getHiddenInput(form, name) {
		var input = form.querySelector('input[name="' + name + '"]');
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			form.appendChild(input);
		}

		return input;
	}

	function setCostSourceMode(form, mode, source) {
		if (!form) {
			return;
		}

		getHiddenInput(form, 'dynamicsprices_cost_source_mode').value = mode || 'auto';
		getHiddenInput(form, 'dynamicsprices_cost_source').value = source || '';
	}

	function findOptionByValue(select, value) {
		if (!select) {
			return null;
		}

		for (var i = 0; i < select.options.length; i++) {
			if (select.options[i].value === value) {
				return select.options[i];
			}
		}

		return null;
	}

	function removeDynamicOption(select, value) {
		if (!select) {
			return;
		}
		var oldOption = findOptionByValue(select, value || optionValue);
		if (oldOption) {
			oldOption.remove();
		}
	}

	function ensureDynamicOption(select, payload, value) {
		value = value || optionValue;
		if (!payload || !payload.success || !payload.available || payload.price === null || typeof payload.price === 'undefined') {
			removeDynamicOption(select, value);
			return null;
		}

		var selectedValue = select.value;
		var option = findOptionByValue(select, value);
		// Select2 caches option labels; replace changed options through the native DOM.
		if (option && option.textContent !== payload.label) {
			option.remove();
			option = null;
		}
		if (!option) {
			option = document.createElement('option');
			option.value = value;
			select.insertBefore(option, select.firstChild);
		}

		if (option.getAttribute('price') !== String(payload.price)) {
			option.setAttribute('price', payload.price);
		}
		if (option.textContent !== payload.label) {
			option.textContent = payload.label;
		}

		if (selectedValue && findOptionByValue(select, selectedValue)) {
			select.value = selectedValue;
			if (window.jQuery && selectedValue === value) {
				window.jQuery(select).trigger('change.select2');
			}
		}

		return option;
	}

	function scheduleApply(delay) {
		requestRevision++;
		if (pendingTimer !== null) {
			window.clearTimeout(pendingTimer);
		}
		pendingTimer = window.setTimeout(applyConfiguredCostDefault, delay || 150);
	}

	function fetchDynamicCost(productId, context) {
		var url = endpoint + '?product_id=' + encodeURIComponent(productId);
		url += '&document_type=' + encodeURIComponent(context.type) + '&document_id=' + encodeURIComponent(context.id);
		url += '&qty=' + encodeURIComponent(context.qty) + '&sale_price=' + encodeURIComponent(context.salePrice);
		url += '&line_current_cost=' + encodeURIComponent(nativeCost);
		return window.fetch(url, {
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json'
			}
		}).then(function(response) {
			if (!response.ok) {
				return null;
			}
			return response.json();
		}).catch(function() {
			return null;
		});
	}

	function getOptionPrice(option, buyingPriceInput) {
		if (!option) {
			return null;
		}

		var price = option.getAttribute('price');
		if ((price === null || typeof price === 'undefined' || price === '') && buyingPriceInput && buyingPriceInput.value !== '') {
			price = buyingPriceInput.value;
		}

		return (price === null || typeof price === 'undefined' || price === '') ? null : price;
	}

	function getCandidateForSource(select, buyingPriceInput, source, payload) {
		var option = null;
		if (source === 'dynamicprices') {
			option = ensureDynamicOption(select, payload);
		} else if (source === 'pricelist') {
			option = ensureDynamicOption(select, Object.assign({ success: true }, payload.pricelist), priceListOptionValue);
		} else if (source === 'dolibarr_default') {
			option = select.options[select.selectedIndex] || null;
			if (option && [optionValue, priceListOptionValue, automaticOptionValue].indexOf(option.value) !== -1) {
				option = null;
			}
		} else if (source === 'pmp') {
			option = findOptionByValue(select, 'pmpprice');
		} else if (source === 'native_cost_price') {
			option = findOptionByValue(select, 'costprice');
		}

		var price = getOptionPrice(option, buyingPriceInput);
		if (!option || price === null) {
			return null;
		}

		return {
			source: source,
			value: option.value,
			price: price
		};
	}

	function triggerNativeCostChange(select) {
		if (window.jQuery) {
			window.jQuery(select).trigger('change');
		} else {
			select.dispatchEvent(new Event('change', { bubbles: true }));
		}
	}

	function applyCandidate(form, select, buyingPriceInput, candidate) {
		if (!candidate) {
			return;
		}

		isApplyingAutomaticCost = true;
		select.value = candidate.value;
		if (buyingPriceInput) {
			buyingPriceInput.value = candidate.price;
		}
		triggerNativeCostChange(select);
		if (buyingPriceInput) {
			buyingPriceInput.value = candidate.price;
		}
		setCostSourceMode(form, 'auto', candidate.source);
		isApplyingAutomaticCost = false;
	}

	function applyConfiguredCostDefault() {
		pendingTimer = null;
		var form = getCommercialLineForm();
		var costSelect = getCostSelect(form);
		var buyingPriceInput = getBuyingPriceInput(form);
		if (!form || !costSelect) {
			return;
		}
		var context = getDocumentContext(form);
		if (!context.type || !context.id) {
			return;
		}

		var productId = getProductId(form);
		if (!productId) {
			removeDynamicOption(costSelect);
			removeDynamicOption(costSelect, priceListOptionValue);
			removeDynamicOption(costSelect, automaticOptionValue);
			currentProductId = '';
			nativeCost = '';
			setCostSourceMode(form, 'auto', '');
			return;
		}

		if (productId !== currentProductId) {
			currentProductId = productId;
			userTouchedCostSelect = false;
			setCostSourceMode(form, 'auto', '');
		}
		if ([optionValue, priceListOptionValue, automaticOptionValue].indexOf(costSelect.value) === -1) {
			nativeCost = buyingPriceInput ? buyingPriceInput.value : '';
			nativeOptionValue = costSelect.value;
		}

		var key = contextKey(form);
		var revision = requestRevision;
		fetchDynamicCost(productId, context).then(function(payload) {
			var latestForm = getCommercialLineForm();
			var latestCostSelect = getCostSelect(latestForm);
			if (!latestCostSelect || revision !== requestRevision || contextKey(latestForm) !== key || !payload || !payload.success) {
				return;
			}
			if (payload.enabled === false) {
				removeDynamicOption(latestCostSelect);
				removeDynamicOption(latestCostSelect, priceListOptionValue);
				removeDynamicOption(latestCostSelect, automaticOptionValue);
				return;
			}

			ensureDynamicOption(latestCostSelect, payload);
			ensureDynamicOption(latestCostSelect, Object.assign({ success: true }, payload.pricelist), priceListOptionValue);
			if (userTouchedCostSelect) {
				// Refresh a deliberately selected named source when quantity changes.
				var chosen = latestCostSelect.options[latestCostSelect.selectedIndex];
				if (chosen && chosen.value === manualSource && [optionValue, priceListOptionValue].indexOf(chosen.value) !== -1) {
					var manualInput = getBuyingPriceInput(latestForm);
					if (manualInput) { manualInput.value = chosen.getAttribute('price'); }
				}
				return;
			}

			var priority = Array.isArray(payload.priority) && payload.priority.length ? payload.priority : defaultPriority;
			var latestBuyingPriceInput = getBuyingPriceInput(latestForm);
			if (payload.resolution) {
				showPreviewError(latestForm, payload.resolution.error ? payload.error_message : '');
				if (payload.resolution.error || payload.resolution.cost === null) {
					removeDynamicOption(latestCostSelect, automaticOptionValue);
					if (!payload.resolution.error && findOptionByValue(latestCostSelect, nativeOptionValue)) {
						applyCandidate(latestForm, latestCostSelect, latestBuyingPriceInput, { source: '', value: nativeOptionValue, price: nativeCost });
					}
					setCostSourceMode(latestForm, 'auto', '');
					return;
				}
				var sourceValue = payload.resolution.source_type === 'pricelist' ? priceListOptionValue :
					(payload.resolution.source_type === 'dynamicprices' ? optionValue : automaticOptionValue);
				ensureDynamicOption(latestCostSelect, { success: true, available: true, price: payload.resolution.cost, label: payload.resolution.label }, sourceValue);
				applyCandidate(latestForm, latestCostSelect, latestBuyingPriceInput, { source: payload.resolution.source_type, value: sourceValue, price: payload.resolution.cost });
				return;
			}
			for (var i = 0; i < priority.length; i++) {
				var candidate = getCandidateForSource(latestCostSelect, latestBuyingPriceInput, priority[i], payload);
				if (candidate) {
					applyCandidate(latestForm, latestCostSelect, latestBuyingPriceInput, candidate);
					return;
				}
			}

			setCostSourceMode(latestForm, 'auto', '');
		});
	}

	function bindConfiguredCostDefault() {
		var form = getCommercialLineForm();
		var costSelect = getCostSelect(form);
		var buyingPriceInput = getBuyingPriceInput(form);
		if (!form || !costSelect) {
			return;
		}
		if (!getDocumentContext(form).type) {
			return;
		}

		setCostSourceMode(form, 'auto', '');
		var onSubmit = function(event) {
			// This dedicated field survives PriceList's changes to buying_price in doActions.
			var input = getBuyingPriceInput(form);
			getHiddenInput(form, 'dynamicsprices_manual_cost').value = userTouchedCostSelect && input ? input.value : '';
			if (previewError && !userTouchedCostSelect) { event.preventDefault(); }
		};
		if (window.jQuery) {
			window.jQuery(form).on('submit.dynamicpricesCost', onSubmit);
		} else {
			form.addEventListener('submit', onSubmit);
		}
		var quantityInput = form.querySelector('input[name="qty"]');
		var salePriceInput = form.querySelector('input[name="price_ht"]');
		[quantityInput, salePriceInput].forEach(function(input) {
			if (input) {
				input.addEventListener('input', function() { scheduleApply(200); });
				input.addEventListener('change', function() { scheduleApply(100); });
			}
		});
		var productSelect = form.querySelector('select[name="idprod"]');
		if (productSelect && productSelect.dataset.dynamicpricesCostBound !== '1') {
			productSelect.dataset.dynamicpricesCostBound = '1';
			var onProductChange = function() {
				currentProductId = getProductId(form);
				nativeCost = '';
				nativeOptionValue = '';
				userTouchedCostSelect = false;
				manualSource = '';
				showPreviewError(form, '');
				setCostSourceMode(form, 'auto', '');
				scheduleApply(250);
			};
			if (window.jQuery) {
				window.jQuery(productSelect).on('change.dynamicpricesCost', onProductChange);
			} else {
				productSelect.addEventListener('change', onProductChange);
			}
		}

		if (costSelect.dataset.dynamicpricesCostBound !== '1') {
			costSelect.dataset.dynamicpricesCostBound = '1';
			var onCostChange = function(event) {
				// Native product AJAX also triggers change; only user selection is manual.
				if (window.jQuery && event.type === 'change' && !event.originalEvent) { return; }
				if (!isApplyingAutomaticCost) {
					userTouchedCostSelect = true;
					manualSource = costSelect.value || '';
					setCostSourceMode(form, 'manual', manualSource);
					var selected = costSelect.options[costSelect.selectedIndex];
					if (selected && buyingPriceInput && [optionValue, priceListOptionValue, automaticOptionValue].indexOf(manualSource) !== -1) {
						buyingPriceInput.value = selected.getAttribute('price');
					}
					showPreviewError(form, '');
				}
			};
			if (window.jQuery) {
				window.jQuery(costSelect).on('change.dynamicpricesCost select2:select.dynamicpricesCost', onCostChange);
			} else {
				costSelect.addEventListener('change', onCostChange);
			}

			var observer = new MutationObserver(function() {
				if (!isApplyingAutomaticCost && !userTouchedCostSelect) {
					scheduleApply(100);
				}
			});
			observer.observe(costSelect, { childList: true, subtree: false });
		}

		if (buyingPriceInput && buyingPriceInput.dataset.dynamicpricesCostBound !== '1') {
			buyingPriceInput.dataset.dynamicpricesCostBound = '1';
			buyingPriceInput.addEventListener('input', function() {
				if (!isApplyingAutomaticCost) {
					userTouchedCostSelect = true;
					manualSource = 'inputprice';
					setCostSourceMode(form, 'manual', 'inputprice');
					if (findOptionByValue(costSelect, 'inputprice')) {
						costSelect.value = 'inputprice';
						if (window.jQuery) { window.jQuery(costSelect).trigger('change.select2'); }
					}
					showPreviewError(form, '');
				}
			});
		}

		scheduleApply(250);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindConfiguredCostDefault);
	} else {
		bindConfiguredCostDefault();
	}
})();
