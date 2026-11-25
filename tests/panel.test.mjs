import assert from 'node:assert/strict';
import { execFileSync, spawn } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { once } from 'node:events';
import test from 'node:test';
import { chromium } from 'playwright-core';

test('tagged visit → campaign chart → read-only event in the Panel', async (t) => {
  mkdirSync('tests/logs', { recursive: true });
  const root = mkdtempSync(resolve('tests/logs/panel-'));
  t.after(() => rmSync(root, { recursive: true, force: true }));
  const env = { ...process.env, UTM_PANEL_TEST_ROOT: root };
  const password = randomUUID();
  const email = 'editor@example.test';
  execFileSync('php', ['-r', `$k = require 'tests/index.php'; $k->impersonate('kirby');
    $k->users()->create(['role'=>'admin','email'=>'${email}','password'=>stream_get_contents(STDIN)]);`],
    { env, input: password });

  const server = spawn('php', ['-S', '127.0.0.1:0', '-t', 'tests/public', 'tests/kirby/router.php'], { env });
  const [startup] = await once(server.stderr, 'data');
  t.after(() => server.kill());
  const base = /http:\/\/127\.0\.0\.1:\d+/.exec(startup.toString())?.[0];
  assert.ok(base, startup.toString());
  server.stderr.resume();

  const orbChrome = resolve('node_modules/.bin/utm-chrome');
  const browser = await chromium.launch({ executablePath: process.env.CHROME_BIN ?? (existsSync(orbChrome) ? orbChrome : undefined) });
  t.after(() => browser.close());
  // HeadlessChrome is intentionally filtered by the plugin's bot detectors.
  const page = await browser.newPage({ userAgent: `Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/${browser.version()} Safari/537.36` });
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  const visit = async (query, count) => {
    await page.goto(`${base}/en/zoo${query}`);
    assert.equal(await page.locator('body').innerText(), String(count));
  };
  await visit('', 0);
  await visit('?utm_source=journal&utm_medium=email&utm_campaign=Autumn+Launch&utm_term=guide&utm_content=story', 1);
  await visit('?utm_source=direct', 2);
  await visit('', 2);

  await page.goto(`${base}/panel`);
  await page.locator('input[type=email]').fill(email);
  await page.locator('input[type=password]').fill(password);
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.getByRole('link', { name: 'UTM', exact: true }).click();
  const row = page.getByRole('row').filter({ hasText: 'Autumn Launch' });
  await row.waitFor();
  assert.deepEqual((await row.getByRole('cell').allInnerTexts()).slice(2, 4), ['1', '1']);
  await page.getByRole('link', { name: 'Autumn Launch' }).click();
  const bar = page.locator('.utm-bar-wrapper > div').last();
  await bar.hover();
  assert.equal(await bar.innerText(), '1');
  assert.notEqual(await bar.evaluate(el => getComputedStyle(el).backgroundColor), 'rgba(0, 0, 0, 0)');
  assert.match(await bar.getAttribute('title'), /^\d{4}-\d{2}-\d{2}$/);
  await page.getByRole('link', { name: 'Events', exact: true }).click();
  await page.getByRole('row').filter({ hasText: 'journal' }).getByRole('link').click();
  await page.locator('input[name="page_id"]').waitFor();
  await page.reload();
  for (const [name, value] of Object.entries({page_id:'zoo', utm_source:'journal', utm_medium:'email', utm_campaign:'Autumn Launch', utm_term:'guide', utm_content:'story'})) {
    const field = page.locator(`input[name="${name}"]`);
    assert.equal(await field.inputValue(), value);
    assert.equal(await field.isEditable(), false);
  }
  await page.getByRole('link', { name: 'UTM', exact: true }).click();
  await page.getByRole('link', { name: 'undefined', exact: true }).click();
  await page.getByRole('link', { name: 'Events', exact: true }).click();
  await page.getByRole('cell', { name: 'direct', exact: true }).waitFor();
  assert.deepEqual(errors, []);
});
