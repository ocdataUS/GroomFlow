# QA Toolbelt (WSL Ubuntu) — Final Agent Guide

This machine has a shared **QA toolbelt** installed for any stateless agent.

## Scope
- **Lives here:** Installed QA utilities, commands, artifact paths, and how to run them.
- **Not here:** Product behaviour, architecture, or onboarding (see `AGENTS.md` map).

## What’s installed (machine‑wide)

- **Chromium (headless)** with **CDP** (DevTools) on demand
- **Lighthouse** CLI for performance/best‑practices reports
- **CLI wrappers** that save auditable **artifacts** (PNG/HTML/TXT/JSON) to `/opt/qa/artifacts`
- **WordPress tooling**: PHP 8.3 CLI, **WP‑CLI**, **Composer**, **PHPCS** + **WordPress Coding Standards**
- **Python QA**: Black, Ruff, Mypy, Pytest + wrappers
- **API & Load**: Newman (Postman CLI), autocannon, k6 + wrappers
- **Accessibility/HTML/Links/Visual**: pa11y, html-validate, lychee, odiff + wrappers
- **Security**: **Trivy** (filesystem & container image) + wrappers  
  *(Semgrep intentionally omitted here)*

### Paths & Environment
- Artifacts dir: `/opt/qa/artifacts`
- Playwright (optional) for UI capture scripts; install browsers with `npx playwright install chromium`
- Browsers cache: `/opt/qa/ms-playwright`
- Chromium executable: `$CHROME_PATH` (auto‑detected)
- CDP discovery URL: `http://127.0.0.1:9222/json/version`
- All commands on `$PATH`: `/opt/qa/bin`
- Composer global bin on PATH: `~/.config/composer/vendor/bin`
- Capabilities reference: `~/.agent/capabilities.json`

---

## Browser & Perf — Quick Start

```bash
qa-browser-start                         # start shared headless Chromium (CDP on :9222)
qa-cdp-shot https://example.com          # screenshot via the running CDP browser
qa-lh https://example.com                # Lighthouse HTML report
qa-browser-stop                          # stop the shared browser
qa-screenshot https://example.com        # one-off screenshot (launches its own Chromium wrapper)
```

All artifacts land in: `/opt/qa/artifacts`.

---

## WordPress QA Commands

- **Fast local gate (PHPCS + optional lint)**
  ```bash
  bash scripts/qa_fast.sh
  SKIP_PHP_LINT=1 bash scripts/qa_fast.sh
  LINT_CHANGED=1 bash scripts/qa_fast.sh
  ```
  Use this when iterating or before sharing a patch. Run `bash scripts/qa_smoke.sh` for full Docker build/install/seed + board smoke coverage.

- **QA smoke (build/install + board + notifications + stats)**
  ```bash
  bash scripts/qa_smoke.sh                       # full run (build ZIP, install in Docker, seed, PHPCS, board/stage move/stats smoke)
  SKIP_ASSET_BUILD=1 bash scripts/qa_smoke.sh    # reuse existing bundles
  # → /opt/qa/artifacts/qa-smoke-<timestamp>.txt plus PHPCS artifact
  ```

- **PHPCS report → artifact**
  ```bash
  qa-phpcs /path/to/theme-or-plugin           # baseline WordPress standard
  scripts/qa-phpcs /path/to/theme-or-plugin   # repo ruleset (custom capabilities)
  # ⇒ /opt/qa/artifacts/phpcs-<timestamp>.txt
  ```

- **PHPCBF auto‑fix in place** (WordPress standard) + summary → artifact
  ```bash
  qa-phpcbf /path/to/theme-or-plugin
  # ⇒ /opt/qa/artifacts/phpcbf-<timestamp>.txt
  ```

- **WP‑CLI runner** (tee output to artifact)
  ```bash
  qa-wp /var/www/html plugin list
  # ⇒ /opt/qa/artifacts/wp-<timestamp>.txt
  ```
  *Quick tip:* need to confirm a WordPress admin script’s dependency stack? Use
  `docker compose run --rm -T wpcli wp eval 'require_once ABSPATH . "wp-admin/includes/admin.php"; do_action( "admin_enqueue_scripts", "<hook>" ); print_r( wp_scripts()->registered["handle"]->deps );'` to dump it without opening the UI.

- **Admin Forms Regression (manual + scripted)**
  - After every install, walk the happy path for Stages, Guardians, Clients, Services, Packages, Flags, Views, and Settings: create a record, save, re-open in edit mode, and confirm all previously entered values re-populate (emoji/color pickers, selects, notes).
  - For Packages specifically, double-check that included services stay checked and their order inputs persist to prevent the "Select at least one service" regression.
  - Log the results in your breadcrumb. If a bug appears, capture repro steps plus any `qa-wp` or screenshot artifacts.
  - Automate repeat checks when possible with WP-CLI (`qa-wp /var/www/html option get ...`) or lightweight scripting; Playwright is available for UI capture scripts if needed.

### Manual Admin Happy-Path Template
When a GUI session is not available, script the persistence checks via WP-CLI and store the entire transcript as an artifact:

```bash
ART=/opt/qa/artifacts/manual-admin-happy-path-$(date -u +%Y%m%dT%H%M%S).txt
PREFIX=$(docker compose run --rm -T wpcli wp config get table_prefix | tr -d '\r')

{
  echo "Manual admin verification $(date -u)"
  docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_guardians SET notes = CONCAT('QA guardian edit ', NOW()) LIMIT 1"
  docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_clients SET notes = CONCAT('QA client edit ', NOW()) LIMIT 1"
  docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_services SET description = CONCAT('QA service edit ', NOW()) LIMIT 1"
  docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_service_packages SET description = CONCAT('QA package edit ', NOW()) LIMIT 1"
  docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_flags SET description = CONCAT('QA flag edit ', NOW()) LIMIT 1"
  docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_views SET updated_at = UTC_TIMESTAMP() LIMIT 1"
  docker compose run --rm -T wpcli wp eval 'update_option( "bbgf_settings", bbgf()->get_default_settings() );'
} | tee "$ART"
```

Add a short markdown checklist (“Clients ✅”, “Settings ✅”) at the top of the artifact so reviewers can confirm every admin surface was exercised. Reference `$ART` inside `qa/QA_LOG.md` and the breadcrumb for the slice.

---

## Python QA Commands

- **Check (no writes)**: Black‑check, Ruff, Mypy, Pytest (if present)
  ```bash
  qa-pycheck /path/to/python-project
  # ⇒ /opt/qa/artifacts/pycheck-<timestamp>.txt
  ```

- **Fix (writes in place)**: Black format + Ruff --fix
  ```bash
  qa-pyfix /path/to/python-project
  # ⇒ /opt/qa/artifacts/pyfix-<timestamp>.txt
  ```

---

## Web Formatters

- **Prettier / ESLint / Stylelint** (auto‑detect configs / files)
  ```bash
  qa-format /path/to/web-project
  # ⇒ /opt/qa/artifacts/format-<timestamp>.txt
  ```

---

## API & Load

- **Postman collection → HTML report** (Newman)
  ```bash
  qa-newman ./collection.json [./env.json]
  # ⇒ /opt/qa/artifacts/newman-<timestamp>.html
  ```

- **Quick HTTP load (autocannon)**
  ```bash
  qa-autocannon https://example.com 10 50
  # ⇒ /opt/qa/artifacts/autocannon-<timestamp>.txt
  ```

- **k6 GET load test**
  ```bash
  qa-k6-url https://example.com 10 10s
  # ⇒ /opt/qa/artifacts/k6-<timestamp>.txt
  ```

---

## Accessibility / HTML / Links / Visual

```bash
qa-pa11y https://example.com                 # → pa11y JSON
qa-html-validate .                           # → HTML validate text report
qa-lychee https://example.com                # → dead link check report
qa-odiff baseline.png current.png [diff.png] # → visual diff image
```

Artifacts are written to `/opt/qa/artifacts` with timestamps.

---

## Document & Media Utilities

```bash
qa-pdftext ./file.pdf      # → pdf-<timestamp>.txt
qa-ocr ./image.png         # → ocr-<timestamp>.txt
qa-exif ./photo.jpg        # → exif-<timestamp>.json
qa-search "regex" .        # → search-<timestamp>.txt
qa-serve /opt/qa/artifacts 8081   # start HTTP server; stop with: qa-serve-stop 8081
qa-zip-artifacts           # → qa-artifacts-<timestamp>.tgz (all artifacts)
qa-open /opt/qa/artifacts/shot-123.png   # open file in Windows
```

---

## Security

```bash
qa-trivy-fs .                 # Trivy filesystem vulnerability scan → text report
qa-trivy-image node:22        # Trivy image scan → text report
# (Semgrep intentionally omitted)
```

---

## How agents should “think”

1. **Decide flow** (URL + actions).  
2. Use **`qa-browser-start`** if you want a persistent browser; otherwise just call `qa-screenshot`.  
3. Save artifacts and **return the printed paths**.  
4. Never run destructive actions on production.

---

## Minimal “Tool Calling” Schema (if your agent supports functions)

```json
{
  "name": "run_browser_action",
  "description": "Produces screenshots, Lighthouse reports, and QA artifacts using the local toolbelt.",
  "parameters": {
    "type": "object",
    "properties": {
      "action": { "type": "string",
        "enum": ["screenshot","cdp_screenshot","lighthouse","phpcs","phpcbf","wp",
                 "pycheck","pyfix","format","newman","autocannon","k6_url",
                 "pa11y","html_validate","lychee","odiff","trivy_fs","trivy_image"] },
      "url":  { "type": "string" },
      "path": { "type": "string" },
      "wpArgs": { "type": "string" }
    },
    "required": ["action"]
  }
}
```

**Mapping**:  
`screenshot` → `qa-screenshot <url>`  
`cdp_screenshot` → `qa-browser-start` (if needed) then `qa-cdp-shot <url>`  
`lighthouse` → `qa-lh <url>`  
`phpcs` → `qa-phpcs <path>`  
`phpcbf` → `qa-phpcbf <path>`  
`wp` → `qa-wp <path> <wpArgs>`  
`pycheck` → `qa-pycheck <path>`  
`pyfix` → `qa-pyfix <path>`  
`format` → `qa-format <path>`  
`newman` → `qa-newman <collection.json> [env.json]`  
`autocannon` → `qa-autocannon <url> [seconds] [connections]`  
`k6_url` → `qa-k6-url <url> [vus] [duration]`  
`pa11y` → `qa-pa11y <url>`  
`html_validate` → `qa-html-validate <path>`  
`lychee` → `qa-lychee <url-or-path>`  
`odiff` → `qa-odiff <baseline.png> <current.png> [diff.png]`  
`trivy_fs` → `qa-trivy-fs <path>`  
`trivy_image` → `qa-trivy-image <image:tag>`

---

## Appendix — Where this toolbelt lives

- Executables: `/opt/qa/bin`  
- Artifacts: `/opt/qa/artifacts`  
- Playwright browsers cache: `/opt/qa/ms-playwright` (install with `npx playwright install chromium`)  
- Config reference: `~/.agent/capabilities.json`

## Tips & Tricks

- Always finish work by rebuilding the plugin ZIP (`bash scripts/build_plugin_zip.sh`) and reinstalling it in Docker WP so the active environment matches your branch.
- Intake modal is tabbed (search/guardian/client/visit); switch tabs via `data-role="bbgf-intake-tab"` before filling fields, and use the selected-chip under the tab bar to clear/reseed quickly.
- Intake search now fires after 2+ characters; empty/short queries keep results blank to avoid loading huge guardian/client lists.
- Running `qa-phpcbf` against multiple files can dump report output into the target file; run it per-file or use PHPCS manually to avoid accidental overwrites.
- Export `WPCS_DIR=/usr/share/php/PHP_CodeSniffer/Standards` (or your install path) before running `qa-phpcs` so the repo `phpcs.xml.dist` resolves WordPress Coding Standards without errors.
- Run `npm run build` before packaging or QA runs so Vite regenerates `plugin/bb-groomflow/assets/build/*`; missing bundles break enqueues and cause placeholder board regressions.
- When installing a freshly built ZIP into Docker WP, copy it into `/var/www/html/` (shared volume) first; paths in `/tmp` live on the container layer, so the `wpcli` service cannot read them.
- Handy shortcut: `docker compose cp ../build/<zip> wordpress:/var/www/html/` drops the ZIP into the container before running `wp plugin install … --force --activate`.
- Need to smoke-test REST endpoints? In Docker WP-CLI run `wp eval 'wp_set_current_user( get_user_by("login","codexadmin")->ID ); $request = new WP_REST_Request("GET","/bb-groomflow/v1/guardians"); $response = rest_do_request( $request ); print_r( $response->get_data() );'` to hit routes without juggling cookies or nonces.
- To validate lobby masking quickly, flip a view with `docker compose run --rm -T wpcli wp db query "UPDATE wp_7ptz4bz8ht_bb_views SET type = 'lobby', show_guardian = 0 WHERE id = 1"`; run the matching update to restore the row when finished.
- Need to probe private helpers or complex flows without a browser? Pipe a temporary PHP script into the wpcli container (`docker compose run --rm -T --user root wpcli sh -c 'cat > /var/www/html/check.php' <<'PHP' … PHP`) and call it via `wp eval-file`, then clean it up with another `rm` run.
- Alignment warnings from WPCS? Run `qa-phpcbf` on the specific file to normalise equals/arrow spacing without formatting the whole plugin.
- Need sample visits fast? After installing the plugin, run `docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8` (add `--force` to reset) to populate every view with demo guardians/clients before QA.
