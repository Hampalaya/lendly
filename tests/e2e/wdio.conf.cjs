// WebDriverIO config — Lendly QA (Selenium via chromedriver, Chrome).
// Brave override: set BRAVE_BINARY to brave.exe (same Chromium engine, identical flags).
// By default Chrome runs visibly and maximized, and after every test (pass or fail) the whole
// primary screen is captured — including the Windows taskbar date and time — as evidence.
// Set E2E_HEADLESS=1 for a quick hidden run (page-only screenshots).
// Results go to artifacts/results.jsonl; tests/e2e/report/build-report.cjs builds the Word report.
const fs = require('fs');
const path = require('path');
const os = require('os');
const { execFileSync } = require('child_process');

const braveBinary = process.env.BRAVE_BINARY || process.env.CHROME_BINARY || undefined;
const baseUrl = process.env.E2E_BASE_URL || 'http://127.0.0.1:8000';
const headless = process.env.E2E_HEADLESS === '1';
const ARTIFACTS = path.join(__dirname, 'artifacts');
const SCREENS = path.join(ARTIFACTS, 'screens');
const RESULTS = path.join(ARTIFACTS, 'results.jsonl');
const CAPTURE = path.join(__dirname, 'support', 'capture-screen.ps1');
const chromeArgs = [
  ...(headless ? ['--headless=new', '--window-size=1280,900'] : ['--start-maximized']),
  '--no-sandbox',
  '--disable-dev-shm-usage',
  // Hide Chrome's own internal log noise (GPU, GCM, updater) from the terminal.
  '--log-level=3',
  '--silent',
  '--disable-notifications',
  `--user-data-dir=${path.join(os.tmpdir(), 'lendly-wdio-profile')}`,
];

// Spec file order = report order: unit → integration → system → smoke.
const specs = ['component', 'module', 'features', 'extra', 'full-cycle', 'smoke.*'].map((name) => path.join(__dirname, 'specs', `${name}.e2e.cjs`));

async function captureFullScreen(file) {
  // Re-maximizing brings the test browser to the front so it is what the capture shows.
  await browser.maximizeWindow().catch(() => {});
  await browser.pause(400);
  execFileSync('powershell', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', CAPTURE, '-Out', file], { timeout: 20000 });
}

exports.config = {
  runner: 'local',
  specs,
  maxInstances: 1,
  capabilities: [
    {
      browserName: 'chrome',
      'goog:chromeOptions': {
        ...(braveBinary ? { binary: braveBinary } : {}),
        args: chromeArgs,
        excludeSwitches: ['enable-logging'],
      },
    },
  ],
  logLevel: 'error',
  baseUrl,
  waitforTimeout: 15000,
  connectionRetryTimeout: 60000,
  framework: 'mocha',
  reporters: ['spec'],
  mochaOpts: { ui: 'bdd', timeout: 120000 },
  services: ['chromedriver'],

  onPrepare() {
    fs.rmSync(SCREENS, { recursive: true, force: true });
    fs.mkdirSync(SCREENS, { recursive: true });
    fs.rmSync(RESULTS, { force: true });
  },

  async before() {
    if (!headless) await browser.maximizeWindow().catch(() => {});
  },

  async afterTest(test, context, { passed, error, duration }) {
    const id = (test.title.match(/^([A-Z]+-\d+)/) || [])[1] || test.title.slice(0, 20);
    const file = path.join(SCREENS, `${id}-${passed ? 'PASS' : 'FAIL'}.png`);
    try {
      // A stuck confirm/alert blocks every browser command, including screenshots.
      await browser.acceptAlert().catch(() => {});
      if (headless) await browser.saveScreenshot(file);
      else await captureFullScreen(file);
    } catch {
      // Browser may be gone after a hard failure; the result line still records it.
    }
    const entry = {
      id,
      title: test.title,
      suite: test.parent,
      spec: path.basename(test.file || ''),
      result: passed ? 'PASS' : 'FAIL',
      ms: duration,
      error: error ? String(error.message || error).split('\n')[0].slice(0, 300) : null,
      screenshot: fs.existsSync(file) ? path.relative(ARTIFACTS, file).replace(/\\/g, '/') : null,
      at: new Date().toISOString(),
    };
    fs.appendFileSync(RESULTS, JSON.stringify(entry) + '\n');
  },
};
