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
	var endpoint = '<?php echo dol_buildpath('/dynamicsprices/ajax/commercial_line_cost.php', 1); ?>';
	var currentProductId = '';
	var userTouchedCostSelect = false;
	var isApplyingAutomaticCost = false;
	var pendingTimer = null;
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
		if (productSelect && productSelect.value && productSelect.value !== '-1') {
			return productSelect.value;
		}

		var productInput = form.querySelector('input[name="productid"], input[name="idprod"]');
		return productInput && productInput.value ? productInput.value : '';
	}

	function getCostSelect(form) {
		return form ? form.querySelector('select[name="fournprice_predef"], select[name="fournprice"]') : null;
	}

	function getBuyingPriceInput(form) {
		return form ? form.querySelector('input[name="buying_price"]') : null;
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

	function removeDynamicOption(select) {
		if (!select) {
			return;
		}
		var oldOption = findOptionByValue(select, optionValue);
		if (oldOption) {
			oldOption.remove();
		}
	}

	function ensureDynamicOption(select, payload) {
		if (!payload || !payload.success || !payload.available || payload.price === null || typeof payload.price === 'undefined') {
			removeDynamicOption(select);
			return null;
		}

		var selectedValue = select.value;
		var option = findOptionByValue(select, optionValue);
		if (!option) {
			option = document.createElement('option');
			option.value = optionValue;
			select.insertBefore(option, select.firstChild);
		}

		option.setAttribute('price', payload.price);
		option.textContent = payload.label || ('DynamicPrices: ' + payload.price);

		if (selectedValue && findOptionByValue(select, selectedValue)) {
			select.value = selectedValue;
		}

		return option;
	}

	function scheduleApply(delay) {
		if (pendingTimer !== null) {
			window.clearTimeout(pendingTimer);
		}
		pendingTimer = window.setTimeout(applyConfiguredCostDefault, delay || 150);
	}

	function fetchDynamicCost(productId) {
		var url = endpoint + '?product_id=' + encodeURIComponent(productId);
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
		} else if (source === 'dolibarr_default') {
			option = select.options[select.selectedIndex] || null;
			if (option && option.value === optionValue) {
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

		var productId = getProductId(form);
		if (!productId) {
			removeDynamicOption(costSelect);
			currentProductId = '';
			setCostSourceMode(form, 'auto', '');
			return;
		}

		if (productId !== currentProductId) {
			currentProductId = productId;
			userTouchedCostSelect = false;
			setCostSourceMode(form, 'auto', '');
		}

		fetchDynamicCost(productId).then(function(payload) {
			var latestForm = getCommercialLineForm();
			var latestCostSelect = getCostSelect(latestForm);
			if (!latestCostSelect || getProductId(latestForm) !== productId || !payload || !payload.success) {
				return;
			}

			ensureDynamicOption(latestCostSelect, payload);
			if (userTouchedCostSelect) {
				return;
			}

			var priority = Array.isArray(payload.priority) && payload.priority.length ? payload.priority : defaultPriority;
			var latestBuyingPriceInput = getBuyingPriceInput(latestForm);
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

		setCostSourceMode(form, 'auto', '');
		var productSelect = form.querySelector('select[name="idprod"]');
		if (productSelect && productSelect.dataset.dynamicpricesCostBound !== '1') {
			productSelect.dataset.dynamicpricesCostBound = '1';
			productSelect.addEventListener('change', function() {
				currentProductId = getProductId(form);
				userTouchedCostSelect = false;
				setCostSourceMode(form, 'auto', '');
				scheduleApply(250);
			});
		}

		if (costSelect.dataset.dynamicpricesCostBound !== '1') {
			costSelect.dataset.dynamicpricesCostBound = '1';
			costSelect.addEventListener('change', function() {
				if (!isApplyingAutomaticCost) {
					userTouchedCostSelect = true;
					setCostSourceMode(form, 'manual', costSelect.value || '');
				}
			});

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
					setCostSourceMode(form, 'manual', 'inputprice');
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
