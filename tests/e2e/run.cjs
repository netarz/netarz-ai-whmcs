#!/usr/bin/env node
/*
 * End-to-end run in a real browser.
 *
 *   node tests/e2e/run.cjs            (needs PHP, puppeteer-core and a Chrome; see tests/e2e/README.md)
 *
 * Starts two PHP servers — a WHMCS stand-in with the module installed, and a mock NetArz
 * gateway — then walks through what a visitor and a staff member would do, asserting
 * every step. Screenshots land in $NTZ_E2E_SHOTS (default tests/e2e/shots).
 */
'use strict';

const { spawn, execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const ROOT = path.resolve(__dirname, '../..');
const PHP = process.env.PHP_BINARY || 'php';
const CHROME = process.env.CHROME_PATH || findChrome();
const puppeteer = require(process.env.PUPPETEER_PATH || 'puppeteer-core');
const WHMCS_PORT = 8791;
const GW_PORT = 8792;
// The browser sees a realistic client-area host (mapped to the local server), so screenshots show real-looking links.
const WHMCS = 'http://my.parshost.test';
const LOCAL = `http://127.0.0.1:${WHMCS_PORT}`;
const GW = `http://127.0.0.1:${GW_PORT}`;
const SHOTS = process.env.NTZ_E2E_SHOTS || path.join(__dirname, 'shots');
const DB = path.join(os.tmpdir(), `netarz-ai-whmcs-e2e-${process.pid}.sqlite`);
const LOG = path.join(os.tmpdir(), `netarz-ai-whmcs-e2e-${process.pid}.log`);

let passed = 0;
const failures = [];
const servers = [];

function findChrome() {
  const base = path.join(os.homedir(), '.cache/puppeteer/chrome');
  if (!fs.existsSync(base)) return '';
  const dirs = fs.readdirSync(base).sort().reverse();
  for (const d of dirs) {
    const p = path.join(base, d, 'chrome-linux64/chrome');
    if (fs.existsSync(p)) return p;
  }
  return '';
}

function check(name, condition, detail) {
  if (condition) {
    passed++;
    console.log(`  \u001b[32mok\u001b[0m   ${name}`);
  } else {
    failures.push(`${name}${detail ? ' — ' + detail : ''}`);
    console.log(`  \u001b[31mFAIL\u001b[0m ${name}${detail ? ' — ' + detail : ''}`);
  }
}

function gatewayLog() {
  if (!fs.existsSync(LOG)) return [];
  return fs.readFileSync(LOG, 'utf8').trim().split('\n').filter(Boolean).map((l) => JSON.parse(l));
}

function serve(port, docroot, router) {
  const env = Object.assign({}, process.env, { NTZ_E2E_DB: DB, NTZ_E2E_LOG: LOG, PHP_CLI_SERVER_WORKERS: '6' });
  const p = spawn(PHP, ['-S', `127.0.0.1:${port}`, '-t', docroot, router], { env, stdio: ['ignore', 'ignore', 'pipe'] });
  p.stderr.on('data', (d) => {
    const s = d.toString();
    if (/Fatal|Warning|Notice|Deprecated|Parse error/.test(s)) process.stderr.write('[php] ' + s);
  });
  servers.push(p);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function waitFor(fn, timeout = 15000, every = 200) {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    const v = await fn();
    if (v) return v;
    await sleep(every);
  }
  return null;
}

async function shot(page, name) {
  fs.mkdirSync(SHOTS, { recursive: true });
  await sleep(700); // let entrance animations finish, or bubbles come out half-faded
  await page.screenshot({ path: path.join(SHOTS, name + '.png') });
}

(async () => {
  fs.rmSync(LOG, { force: true });
  execFileSync(PHP, [path.join(__dirname, 'setup.php'), WHMCS, GW], { env: Object.assign({}, process.env, { NTZ_E2E_DB: DB }), stdio: 'inherit' });
  serve(WHMCS_PORT, path.join(__dirname, 'www'), path.join(__dirname, 'www/router.php'));
  serve(GW_PORT, __dirname, path.join(__dirname, 'mock-gateway.php'));
  await sleep(700);

  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--lang=en-US', `--host-resolver-rules=MAP my.parshost.test:80 127.0.0.1:${WHMCS_PORT}`],
  });

  const jsErrors = [];
  const dialogs = [];
  const watch = (page, label) => {
    page.on('pageerror', (e) => jsErrors.push(`${label}: ${e.message}`));
    page.on('console', (m) => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) jsErrors.push(`${label}: ${m.text()}`); });
    page.on('response', (r) => { if (r.status() >= 400 && !/favicon/.test(r.url())) jsErrors.push(`${label}: HTTP ${r.status()} ${r.url()}`); });
    page.on('dialog', async (d) => { dialogs.push(d.message()); await d.dismiss(); });
  };

  try {
    /* ---------------------------------------------------- visitor, English */
    console.log('\nVisitor (guest, English)');
    const visitorCtx = await browser.createBrowserContext();
    const v = await visitorCtx.newPage();
    watch(v, 'visitor');
    await v.setViewport({ width: 1366, height: 860, deviceScaleFactor: 1 });
    await v.goto(WHMCS + '/clientarea.php?lang=english', { waitUntil: 'networkidle0' });

    check('the chat button appears on the client area', await v.$('.ntzc-launcher') !== null);
    await v.click('.ntzc-launcher');
    await v.waitForSelector('.ntzc.is-shown');
    check('the greeting names the company', (await v.$eval('.ntzc-greeting', (e) => e.textContent)).includes('Pars Host'));
    check('a guest is asked for name and email first', await v.$eval('.ntzc-identity', (e) => !e.hidden));
    await shot(v, '01-guest-identity');

    await v.type('.ntzc-identity input[name=name]', 'Ali Test');
    await v.type('.ntzc-identity input[name=email]', 'ali@example.com');
    await v.click('.ntzc-identity button');
    await v.waitForFunction(() => !document.querySelector('.ntzc-composer').hidden);
    check('the composer opens after the identity step', true);

    // Two quick lines: the AI must answer once, for both.
    await v.type('.ntzc-textarea', 'Hi');
    await v.keyboard.press('Enter');
    await sleep(400);
    await v.type('.ntzc-textarea', 'how much is the Starter plan?');
    await v.keyboard.press('Enter');
    const typingSeen = await waitFor(() => v.$eval('.ntzc-typing', (e) => !e.hidden), 6000, 100);
    check('the typing indicator shows while the AI works', !!typingSeen);
    await v.waitForSelector('.ntzc-msg.is-ai', { timeout: 15000 });
    const aiText = await v.$eval('.ntzc-msg.is-ai .ntzc-bubble', (e) => e.textContent);
    check('the AI answers from the WHMCS product catalogue', aiText.includes('$5 a month') && aiText.includes('cart.php?a=add&pid=1'), aiText);
    await sleep(2500);
    check('two quick lines get exactly one answer', (await v.$$('.ntzc-msg.is-ai')).length === 1);
    const completions = gatewayLog().filter((r) => r.path === '/api/ai/v1/chat/completions');
    check('one model call was made for the burst', completions.length === 1, `calls: ${completions.length}`);
    const lastUser = completions[0] && completions[0].body.messages.filter((m) => m.role === 'user').pop();
    check('the model saw both lines as one turn', lastUser && lastUser.content === 'Hi\nhow much is the Starter plan?', lastUser && lastUser.content);
    check('the key and the module user-agent were sent', completions[0] && completions[0].auth && /^NetArz-WHMCS\//.test(completions[0].ua));
    check('JSON mode was requested', completions[0] && completions[0].body.response_format && completions[0].body.response_format.type === 'json_object');
    await shot(v, '02-guest-answer');

    // A refund request goes to a person.
    await v.type('.ntzc-textarea', 'I want a refund, the site was down all week');
    await v.keyboard.press('Enter');
    await v.waitForFunction(() => document.querySelectorAll('.ntzc-msg.is-ai').length >= 2, { timeout: 15000 });
    await waitFor(() => v.$eval('.ntzc-status', (e) => e.textContent.includes('colleague')), 8000);
    check('a refund request is handed to a person', (await v.$eval('.ntzc-status', (e) => e.textContent)).includes('colleague'));

    /* ------------------------------------------------------- admin, Persian */
    console.log('\nStaff (Sara, Persian admin area)');
    const adminCtx = await browser.createBrowserContext();
    const a = await adminCtx.newPage();
    watch(a, 'admin');
    await a.setViewport({ width: 1440, height: 900 });
    await a.goto(WHMCS + '/_admin?id=2&lang=farsi', { waitUntil: 'networkidle0' });
    check('the admin page is Persian and right-to-left', await a.$eval('.ntz', (e) => e.getAttribute('dir')) === 'rtl');
    check('the dashboard shows the NetArz AI credit', (await a.$eval('[data-balance-usd]', (e) => e.textContent)).includes('$8.41'));
    check('the waiting chat is counted on the inbox tab', (await a.$eval('.ntz-tab [data-waiting]', (e) => e.textContent.trim())) === '1');
    const toast = await waitFor(() => a.$('.ntz-alert-toast.is-shown'), 8000);
    check('a "visitor is waiting" notice pops up in the admin area', !!toast);
    await shot(a, '03-admin-dashboard-fa');

    await a.goto(WHMCS + '/admin/addonmodules.php?module=netarz_ai&tab=inbox', { waitUntil: 'networkidle0' });
    await a.waitForSelector('.ntz-thread');
    check('the conversation is in the inbox', (await a.$eval('.ntz-thread', (e) => e.textContent)).includes('Ali Test'));
    await a.click('.ntz-thread');
    await a.waitForFunction(() => document.querySelectorAll('.ntz-messages .ntz-msg').length >= 4, { timeout: 8000 });
    check('staff see the whole transcript', (await a.$$('.ntz-messages .ntz-msg')).length >= 4);
    check('staff see why it was handed over', (await a.$eval('[data-convo-handoff]', (e) => !e.hidden && e.textContent.includes('refund request'))));
    await shot(a, '04-admin-inbox-fa');

    await a.type('[data-composer-input]', 'سلام، سارا هستم. بررسی می‌کنم.');
    await a.keyboard.press('Enter');
    await a.waitForFunction(() => [...document.querySelectorAll('.ntz-msg.is-admin')].length === 1, { timeout: 8000 });
    check('the staff reply is sent', true);

    const got = await waitFor(() => v.$eval('.ntzc-list', (e) => [...e.querySelectorAll('.ntzc-msg.is-admin')].length === 1), 12000);
    check('the visitor receives the staff reply live', !!got);
    check('the widget shows who is answering', (await v.$eval('.ntzc-name', (e) => e.textContent)) === 'Sara Ahmadi');
    check('the admin bubble is marked as a person', await v.$('.ntzc-author.is-human') !== null);
    await shot(v, '05-visitor-gets-staff-reply');

    // Seen ticks: the admin opened the thread, so the visitor's messages turn to "seen".
    const seen = await waitFor(() => v.$eval('.ntzc-msg.is-visitor .ntzc-tick', (e) => e.classList.contains('is-seen')), 8000);
    check('visitor messages show as seen once staff open them', !!seen);

    // Hand back to the AI; the next question is answered by the AI again.
    await a.click('[data-action="handback"]');
    await sleep(600);
    await v.type('.ntzc-textarea', 'What are your support hours?');
    await v.keyboard.press('Enter');
    await v.waitForFunction(() => document.querySelectorAll('.ntzc-msg.is-ai').length >= 3, { timeout: 15000 });
    check('after hand-back the AI answers from the owner notes', (await v.$$eval('.ntzc-msg.is-ai .ntzc-bubble', (els) => els.pop().textContent)).includes('Saturday to Wednesday'));

    /* --------------------------------------------- ticket offer when no one comes */
    console.log('\nChat to ticket');
    await v.type('.ntzc-textarea', 'refund please, again');
    await v.keyboard.press('Enter');
    await v.waitForFunction(() => document.querySelectorAll('.ntzc-msg.is-ai').length >= 4, { timeout: 15000 });
    await (await fetch(LOCAL + '/_age')).text();
    const offer = await waitFor(() => v.$eval('.ntzc-offer', (e) => !e.hidden), 8000);
    check('nobody answered in time, so the visitor is offered a ticket', !!offer);
    await shot(v, '06-ticket-offer');
    await v.click('.ntzc-offer button');
    const ticketLink = await waitFor(() => v.$('.ntzc-ticket-link'), 8000);
    check('the chat becomes a WHMCS ticket with a link', !!ticketLink);
    check('a system line confirms the ticket number', (await v.$$eval('.ntzc-system', (els) => els.map((e) => e.textContent).join(' '))).includes('Ticket #'));

    /* ------------------------------------------------------------- tickets */
    console.log('\nTickets (draft mode)');
    const t = await (await fetch(LOCAL + '/_ticket?client=1&dept=2&subject=' + encodeURIComponent('DNS setup') + '&message=' + encodeURIComponent('Which nameservers should I use for rezashop.test?'))).json();
    await a.goto(WHMCS + '/admin/supporttickets.php?id=' + t.id, { waitUntil: 'networkidle0' });
    const draftShown = await a.$eval('[data-tp-draft]', (e) => !e.hidden);
    check('a new ticket gets an AI draft on the ticket page', draftShown);
    check('the draft uses the knowledgebase', (await a.$eval('[data-tp-text]', (e) => e.textContent)).includes('ns1.parshost.test'));
    await a.click('[data-tp="use"]');
    check('"Put in reply box" fills the WHMCS reply box', (await a.$eval('#replymessage', (e) => e.value)).includes('ns1.parshost.test'));
    await shot(a, '07-ticket-draft-fa');
    await a.click('[data-tp="generate"]');
    await a.waitForFunction(() => !document.querySelector('[data-ticket-panel]').classList.contains('is-working'), { timeout: 15000 });
    check('"Write again" produces a fresh draft', await a.$eval('[data-tp-draft]', (e) => !e.hidden));

    /* ------------------------------------------------------------ settings */
    console.log('\nSettings, knowledge and widget (English admin)');
    await a.goto(WHMCS + '/_admin?id=1&lang=english', { waitUntil: 'networkidle0' });
    await a.goto(WHMCS + '/admin/addonmodules.php?module=netarz_ai&tab=settings', { waitUntil: 'networkidle0' });
    check('the saved key is masked', (await a.$eval('#ntz-api_key', (e) => e.value)).includes('••••'));
    await a.click('[data-action="test-connection"]');
    await a.waitForSelector('[data-test-result].is-ok', { timeout: 8000 });
    check('"Test connection" reports the project and balance', (await a.$eval('[data-test-result]', (e) => e.textContent)).includes('Pars Host support'));
    const models = await waitFor(() => a.$$eval('#ntz-models option', (o) => o.length), 8000);
    check('the model list is loaded from the gateway', models >= 2);
    await shot(a, '08-settings-en');
    await a.$eval('#ntz-agent_name', (e) => { e.value = ''; });
    await a.type('#ntz-agent_name', 'نیکا');
    await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle0' }), a.click('.ntz-savebar button')]);
    check('settings save', (await a.$eval('.ntz-flash', (e) => e.textContent)).includes('Settings saved'));
    check('the saved value is kept', (await a.$eval('#ntz-agent_name', (e) => e.value)) === 'نیکا');

    await a.goto(WHMCS + '/admin/addonmodules.php?module=netarz_ai&tab=knowledge', { waitUntil: 'networkidle0' });
    await a.type('[data-console-form] textarea', 'What are your support hours?');
    await a.click('[data-console-form] button[type=submit]');
    await a.waitForSelector('.ntz-console-reply', { timeout: 15000 });
    check('the test console answers from the owner notes', (await a.$eval('.ntz-console-reply', (e) => e.textContent)).includes('Saturday to Wednesday'));
    await shot(a, '09-knowledge-console-en');

    await a.goto(WHMCS + '/admin/addonmodules.php?module=netarz_ai&tab=logs', { waitUntil: 'networkidle0' });
    check('the request log lists the calls with request ids', (await a.$$('.ntz-table tbody tr')).length >= 5 && (await a.$eval('.ntz-table', (e) => e.textContent)).includes('req_e2e_'));

    await a.goto(WHMCS + '/admin/index.php', { waitUntil: 'networkidle0' });
    check('the admin dashboard widget shows the credit', (await a.$eval('.ntz-widget', (e) => e.textContent)).includes('$8.41'));
    await shot(a, '10-dashboard-widget');

    /* ----------------------------------------- signed-in client, Persian, phone */
    console.log('\nSigned-in client (Persian, phone)');
    const phoneCtx = await browser.createBrowserContext();
    const p = await phoneCtx.newPage();
    watch(p, 'phone');
    await p.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    await p.goto(WHMCS + '/_login?as=1', { waitUntil: 'networkidle0' });
    await p.goto(WHMCS + '/clientarea.php?lang=farsi', { waitUntil: 'networkidle0' });
    await p.click('.ntzc-launcher');
    await p.waitForSelector('.ntzc.is-shown');
    check('the widget is right-to-left for a Persian client area', await p.$eval('.ntzc', (e) => e.getAttribute('dir')) === 'rtl');
    check('a signed-in client goes straight to the composer', await p.$eval('.ntzc-identity', (e) => e.hidden) && await p.$eval('.ntzc-composer', (e) => !e.hidden));
    const box = await p.$eval('.ntzc-panel', (e) => { const r = e.getBoundingClientRect(); return [r.width, r.height]; });
    check('on a phone the chat fills the screen', box[0] >= 389 && box[1] >= 800, box.join('x'));
    await p.type('.ntzc-textarea', 'قیمت پلن Starter چنده؟');
    await p.keyboard.press('Enter');
    await p.waitForSelector('.ntzc-msg.is-ai', { timeout: 15000 });
    check('a Persian question gets a Persian answer with the price', /پلن Starter ماهی ۵ دلاره.*cart\.php\?a=add&pid=1/.test(await p.$eval('.ntzc-msg.is-ai .ntzc-bubble', (e) => e.textContent)));
    const lastCall = gatewayLog().filter((r) => r.path === '/api/ai/v1/chat/completions').pop();
    check('the signed-in client\'s own services reach the model', lastCall.body.messages[0].content.includes('rezashop.test'));
    check('their hosting password never does', !lastCall.body.messages[0].content.includes('P@ssw0rd-SECRET'));
    await shot(p, '11-phone-fa');

    // Reload keeps the conversation.
    await p.reload({ waitUntil: 'networkidle0' });
    await p.waitForSelector('.ntzc-msg.is-ai', { timeout: 8000 });
    check('a reload restores the conversation', (await p.$$('.ntzc-msg')).length >= 2);

    /* ---------------------------------------------------- hostile input */
    console.log('\nHostile input');
    // HTML typed by a visitor is shown as text.
    await v.type('.ntzc-textarea', '<img src=x onerror=alert(1)> test');
    await v.keyboard.press('Enter');
    await v.waitForFunction(() => [...document.querySelectorAll('.ntzc-msg.is-visitor .ntzc-bubble')].some((b) => b.textContent.includes('<img src=x')));
    check('visitor HTML is rendered as text, not markup', (await v.$$('.ntzc-bubble img')).length === 0 && dialogs.length === 0);


    /* ------------------------------------------------------------ hygiene */
    console.log('\nHygiene');
    check('no JavaScript errors on any page', jsErrors.length === 0, jsErrors.join(' | '));
    check('no alert() ever fired', dialogs.length === 0, dialogs.join(' | '));
  } catch (e) {
    failures.push('crashed: ' + (e && e.stack || e));
    console.error(e);
  } finally {
    await browser.close().catch(() => {});
    servers.forEach((s) => s.kill());
    fs.rmSync(DB, { force: true });
    fs.rmSync(LOG, { force: true });
  }

  console.log(`\n${passed} checks passed, ${failures.length} failed.`);
  if (failures.length) {
    failures.forEach((f) => console.log('  - ' + f));
    process.exit(1);
  }
})();
