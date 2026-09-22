/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Offline DOM integration: actual module JS and native jQuery/Select2, simulated AJAX.
// Usage: node test/commercial_line_cost_browser_test.cjs /path/to/dolibarr/htdocs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const core = process.argv[2];
if (!core) throw new Error('Pass the Dolibarr htdocs directory to load its jQuery and Select2.');
const jquery = fs.readFileSync(path.join(core, 'includes/jquery/js/jquery.min.js'), 'utf8');
const select2 = fs.readFileSync(path.join(core, 'includes/jquery/plugins/select2/dist/js/select2.min.js'), 'utf8');
const php = fs.readFileSync(path.join(__dirname, '../js/dynamicsprices_commercial_line_cost.js.php'), 'utf8');
const script = php.slice(php.indexOf('(function()')).replace(/<\?php echo dol_buildpath\([^\n]+?\?>/, '/dynamicsprices/ajax/commercial_line_cost.php');

(async () => {
	const browser = await chromium.launch({ headless: true, ...(process.platform === 'win32' ? { channel: 'msedge' } : {}) });
	try {
		const page = await browser.newPage();
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		let enabled = true;
		let fail = false;
		const requests = [];
		await page.route('**/*', async route => {
			const url = new URL(route.request().url());
			if (url.pathname.endsWith('/ajax/commercial_line_cost.php')) {
				requests.push(Object.fromEntries(url.searchParams));
				const qty = Number(url.searchParams.get('qty'));
				const cost = qty === 20 ? 0 : (qty >= 30 ? qty : 12.5);
				const payload = {
					success: true, enabled, available: true, price: 25, label: 'DynamicPrices: 25',
					priority: ['pricelist', 'dynamicprices', 'pmp'],
					pricelist: { available: !fail, price: fail ? null : cost, label: `Tarifs Dégréssifs: ${cost}`, error: fail },
					resolution: { cost: fail ? null : cost, source_type: 'pricelist', error: fail, label: `Tarifs Dégréssifs: ${cost}` },
					error_message: fail ? 'Tarif indisponible' : ''
				};
				if (qty === 30) await new Promise(resolve => setTimeout(resolve, 350));
				await route.fulfill({ contentType: 'application/json', body: JSON.stringify(payload) });
				return;
			}
			const html = `<!doctype html><html lang="fr"><body><form name="addproduct" method="post">
				<input name="action" value="addline" type="hidden"><input name="id" value="15" type="hidden">
				<select name="idprod"><option value="20" selected>Produit</option><option value="21">Autre produit</option></select>
				<input name="qty" value="10"><input name="price_ht" value="100">
				<div><select id="cost" name="fournprice_predef"><option value="inputprice" price="70">Saisie</option><option value="pmpprice" price="40">PMP</option></select>
				<input name="buying_price" value="70"></div><button type="submit">Ajouter</button></form>
				<script>${jquery}</script><script>${select2}</script><script>$('#cost').select2();</script>
				<script>${script}</script></body></html>`;
			await route.fulfill({ contentType: 'text/html', body: html });
		});
		for (const [type, card] of [['propal', 'comm/propal'], ['commande', 'commande'], ['facture', 'compta/facture']]) {
			await page.goto(`https://dynamicprices.test/${card}/card.php?id=15`);
			await page.waitForFunction(() => document.querySelector('[name=buying_price]').value === '12.5');
			assert.equal(requests.at(-1).document_type, type);
			assert.equal(requests.at(-1).document_id, '15');
			assert.equal(await page.locator('[name=dynamicsprices_cost_source_mode]').inputValue(), 'auto');
		}
		await page.evaluate(() => window.jQuery('#cost').trigger('change'));
		assert.equal(await page.locator('[name=dynamicsprices_cost_source_mode]').inputValue(), 'auto', 'A native AJAX change must not become a manual choice.');
		await page.locator('[name=qty]').fill('20');
		await page.waitForFunction(() => document.querySelector('[name=buying_price]').value === '0');
		await page.locator('[name=qty]').fill('30');
		await page.waitForRequest(request => request.url().includes('qty=30'));
		await page.locator('[name=qty]').fill('40');
		await page.waitForFunction(() => document.querySelector('[name=buying_price]').value === '40');
		await page.waitForTimeout(450);
		assert.equal(await page.locator('[name=buying_price]').inputValue(), '40', 'Late responses must not restore an old tier.');
		await page.locator('[name=buying_price]').fill('77.5');
		await page.locator('[name=qty]').fill('50');
		await page.waitForResponse(response => response.url().includes('qty=50'));
		await page.waitForTimeout(100);
		assert.equal(await page.locator('[name=buying_price]').inputValue(), '77.5', 'A manual input must survive a quantity refresh.');
		await page.evaluate(() => {
			const form = document.querySelector('form');
			form.addEventListener('submit', event => event.preventDefault());
			form.requestSubmit();
		});
		assert.equal(await page.locator('[name=dynamicsprices_manual_cost]').inputValue(), '77.5');
		await page.locator('.select2-selection').click();
		await page.locator('.select2-results__option').filter({ hasText: 'Tarifs Dégréssifs: 50' }).click();
		assert.equal(await page.locator('[name=dynamicsprices_cost_source]').inputValue(), 'dynamicsprices_pricelist_cost');
		assert.equal(await page.locator('[name=buying_price]').inputValue(), '50');

		fail = true;
		await page.goto('https://dynamicprices.test/comm/propal/card.php?id=15');
		await page.getByRole('alert').waitFor();
		assert.equal(await page.getByRole('alert').textContent(), 'Tarif indisponible');
		await page.locator('[name=buying_price]').fill('0');
		assert.equal(await page.getByRole('alert').textContent(), '');

		fail = false;
		enabled = false;
		await page.goto('https://dynamicprices.test/comm/propal/card.php?id=15');
		await page.waitForResponse(response => response.url().includes('commercial_line_cost.php'));
		await page.waitForTimeout(100);
		assert.equal(await page.locator('[name=buying_price]').inputValue(), '70', 'Disabled sales integration must keep the native input untouched.');
		assert.deepEqual(errors, []);
		console.log(`Offline commercial line browser tests passed (${browser.version()}, three native document routes, jQuery and Select2).`);
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error); process.exitCode = 1; });
