// Utility script to capture board and modal screenshots while authenticated.
// Usage: node scripts/capture_artifacts.js [before|after]
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const mode = process.argv[2] || 'before';
const baseUrl = process.env.BBGF_BASE_URL || 'http://localhost:8083';
const boardPath = '/groomflow-staff-board/';
const loginUrl = `${baseUrl}/wp-login.php`;
const boardUrl = `${baseUrl}${boardPath}`;
const username = process.env.WP_USERNAME || 'codexadmin';
const password = process.env.WP_PASSWORD || 'codexlocal';
const artifactDir = process.env.BBGF_ARTIFACT_DIR || '/opt/qa/artifacts';

const viewports = [
	{ name: '1366x768', width: 1366, height: 768 },
	{ name: '1280x800', width: 1280, height: 800 },
	{ name: '1024x768', width: 1024, height: 768 },
];

function ensureDir(dirPath) {
	if (!fs.existsSync(dirPath)) {
		fs.mkdirSync(dirPath, { recursive: true });
	}
}

async function login(page) {
	await page.goto(loginUrl, { waitUntil: 'networkidle' });
	const alreadyLoggedIn = await page.$('#wpadminbar');
	if (alreadyLoggedIn) {
		return;
	}

	await page.fill('#user_login', username);
	await page.fill('#user_pass', password);
	await page.click('#wp-submit');
	await page.waitForSelector('#wpadminbar', { timeout: 15000 });
}

async function captureBoard(page, viewport, prefix) {
	await page.setViewportSize({ width: viewport.width, height: viewport.height });
	await page.goto(boardUrl, { waitUntil: 'networkidle' });
	await page.waitForSelector('#wpadminbar', { timeout: 10000 });
	await page.waitForSelector('.bbgf-card', { timeout: 15000 });
	await page.waitForTimeout(1200);

	const filePath = path.join(artifactDir, `board-${prefix}-${viewport.name}.png`);
	await page.screenshot({ path: filePath, fullPage: true });
	console.log(`Saved board screenshot: ${filePath}`);
}

async function captureModal(page, viewport, prefix) {
	await page.setViewportSize({ width: viewport.width, height: viewport.height });
	await page.goto(boardUrl, { waitUntil: 'networkidle' });
	await page.waitForSelector('.bbgf-card', { timeout: 15000 });
	const firstCard = await page.$('.bbgf-card');
	if (!firstCard) {
		throw new Error('No cards found on board');
	}

	await firstCard.click();
	await page.waitForSelector('#bbgf-modal .bbgf-modal__dialog', { timeout: 15000 });
	await page.waitForTimeout(1200);

	const filePath = path.join(artifactDir, `modal-${prefix}-${viewport.name}.png`);
	await page.screenshot({ path: filePath, fullPage: true });
	console.log(`Saved modal screenshot: ${filePath}`);
}

async function run() {
	ensureDir(artifactDir);
	const browser = await chromium.launch({ headless: true });
	const context = await browser.newContext();
	const page = await context.newPage();

	await login(page);

	for (const viewport of viewports) {
		await captureBoard(page, viewport, mode);
		await captureModal(page, viewport, mode);
	}

	await browser.close();
}

run().catch((error) => {
	console.error(error);
	process.exit(1);
});
