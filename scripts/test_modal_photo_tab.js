#!/usr/bin/env node
/**
 * Headless smoke test for the GroomFlow visit modal photo tab.
 * - Logs in as the provided WP user (defaults to codexadmin/codexlocal).
 * - Opens the GroomFlow Staff Board page.
 * - Opens the first visit card, switches to Photos, selects a temp image file,
 *   and asserts the pending uploads list reflects the filename.
 *
 * Env:
 *   BBGF_BASE_URL   (default http://localhost:8083)
 *   BBGF_ADMIN_USER (default codexadmin)
 *   BBGF_ADMIN_PASS (default codexlocal)
 */

const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');

const BASE_URL = process.env.BBGF_BASE_URL || 'http://localhost:8083';
const ADMIN_USER = process.env.BBGF_ADMIN_USER || 'codexadmin';
const ADMIN_PASS = process.env.BBGF_ADMIN_PASS || 'codexlocal';

const TEMP_PHOTO_PATH = path.join(os.tmpdir(), 'bbgf-photo-smoke.png');

const writeTempPhoto = () => {
	// 1x1 transparent PNG
	const pngHex =
		'89504e470d0a1a0a0000000d4948445200000001000000010806000000' +
		'1f15c4890000000a49444154789c6360000002000100' +
		'05fe02fea7d3f60000000049454e44ae426082';
	fs.writeFileSync(TEMP_PHOTO_PATH, Buffer.from(pngHex, 'hex'));
	return TEMP_PHOTO_PATH;
};

const loginIfNeeded = async (page) => {
	await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded' });

	// If already logged in, WordPress redirects to /wp-admin/
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
	const context = await browser.newContext();
	const page = await context.newPage();

	try {
		writeTempPhoto();
		await loginIfNeeded(page);

		await page.goto(`${BASE_URL}/groomflow-staff-board/`, { waitUntil: 'networkidle' });
		await page.waitForSelector('.bbgf-card');

		await page.click('.bbgf-card');
		await page.click('[data-role="bbgf-modal-tab"][data-tab="photos"]');
		await page.waitForSelector('[data-role="bbgf-photo-file"]');

		const fileInput = page.locator('[data-role="bbgf-photo-file"]');
		await fileInput.setInputFiles(TEMP_PHOTO_PATH);

		await page.waitForSelector('.bbgf-photo-upload__list li');
		const pendingItems = await page.$$eval('.bbgf-photo-upload__list li', (els) => els.map((el) => el.textContent?.trim()));

		if (!pendingItems.length) {
			throw new Error('No pending upload entries found after selecting a file.');
		}

		console.log('Pending uploads:', pendingItems.join(', '));
		console.log('Photo upload tab smoke test: PASS');
	} finally {
		await browser.close();
		try {
			fs.unlinkSync(TEMP_PHOTO_PATH);
		} catch (error) {
			// ignore cleanup errors
		}
	}
};

run().catch((error) => {
	console.error('Photo upload tab smoke test: FAIL');
	console.error(error?.stack || error?.message || error);
	process.exitCode = 1;
});
