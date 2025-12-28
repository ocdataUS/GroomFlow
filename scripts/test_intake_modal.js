#!/usr/bin/env node
/**
 * Headless smoke test for the GroomFlow check-in modal.
 * - Logs in (codexadmin/codexlocal by default)
 * - Opens the Staff Board
 * - Opens the check-in modal, fills guardian/client fields, submits
 * - Waits for the new client card to appear on the board
 *
 * Env:
 *   BBGF_BASE_URL   (default http://localhost:8083)
 *   BBGF_ADMIN_USER (default codexadmin)
 *   BBGF_ADMIN_PASS (default codexlocal)
 */

const { chromium } = require('playwright');

const BASE_URL = process.env.BBGF_BASE_URL || 'http://localhost:8083';
const ADMIN_USER = process.env.BBGF_ADMIN_USER || 'codexadmin';
const ADMIN_PASS = process.env.BBGF_ADMIN_PASS || 'codexlocal';

const randomName = (prefix) => `${prefix}-${Math.random().toString(36).slice(2, 7)}`;

const loginIfNeeded = async (page) => {
	await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded' });
	if (page.url().includes('/wp-admin/')) {
		return;
	}
	await page.fill('#user_login', ADMIN_USER);
	await page.fill('#user_pass', ADMIN_PASS);
	await page.click('#wp-submit');
	await page.waitForLoadState('networkidle');
};

const run = async () => {
	const browser = await chromium.launch({ headless: true });
	const page = await browser.newPage();
	const clientName = randomName('Pup');
	const guardianFirst = randomName('Alex');
	const guardianLast = randomName('Rivera');

	try {
		await loginIfNeeded(page);
		await page.goto(`${BASE_URL}/groomflow-staff-board/`, { waitUntil: 'networkidle' });
		await page.waitForSelector('.bbgf-card');

		await page.click('[data-role="bbgf-intake-open"]');
		await page.waitForSelector('#bbgf-intake-modal', { state: 'visible' });

		const selectTab = async (tabId) => page.click(`[data-role="bbgf-intake-tab"][data-tab="${tabId}"]`);

		await selectTab('guardian');
		await page.waitForSelector('[data-section="guardian"][data-field="first_name"]');
		await page.fill('[data-section="guardian"][data-field="first_name"]', guardianFirst);
		await page.fill('[data-section="guardian"][data-field="last_name"]', guardianLast);
		await page.fill('[data-section="guardian"][data-field="email"]', `${guardianFirst.toLowerCase()}@example.test`);
		await page.fill('[data-section="guardian"][data-field="phone"]', '555-0123');

		await selectTab('client');
		await page.waitForSelector('[data-section="client"][data-field="name"]');
		await page.fill('[data-section="client"][data-field="name"]', clientName);
		await page.fill('[data-section="client"][data-field="breed"]', 'Test Breed');
		await page.fill('[data-section="client"][data-field="weight"]', '22.5');

		await selectTab('visit');
		await page.waitForSelector('[data-section="visit"][data-field="instructions"]');
		await page.fill('[data-section="visit"][data-field="instructions"]', 'UI smoke check-in via Playwright');

		await page.click('[data-role="bbgf-intake-submit"]');
		await page.waitForSelector('#bbgf-intake-modal', { state: 'hidden', timeout: 10000 });

		await page.waitForSelector(`.bbgf-card .bbgf-card-name:has-text("${clientName}")`, { timeout: 10000 });

		console.log('Intake modal smoke test: PASS');
		console.log(`Created client: ${clientName} for guardian ${guardianFirst} ${guardianLast}`);
	} finally {
		await browser.close();
	}
};

run().catch((error) => {
	console.error('Intake modal smoke test: FAIL');
	console.error(error?.stack || error?.message || error);
	process.exitCode = 1;
});
