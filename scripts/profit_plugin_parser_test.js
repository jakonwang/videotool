'use strict';

const fs = require('fs');
const path = require('path');
const parser = require('../tools/browser_plugin/profit_center_capture/shared/parser.js');

function stripTags(html) {
  return String(html || '')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function decodeHtml(text) {
  return String(text || '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&#39;/g, "'")
    .replace(/&quot;/g, '"')
    .trim();
}

function firstTagTextByRegex(html, regex) {
  const m = String(html || '').match(regex);
  if (!m || !m[m.length - 1]) return '';
  return decodeHtml(stripTags(m[m.length - 1]));
}

function makeDoc(html) {
  const raw = String(html || '');
  const bodyText = stripTags(raw);

  return {
    body: {
      innerText: bodyText,
      textContent: bodyText
    },
    querySelector(selector) {
      const sel = String(selector || '').trim();
      if (!sel) return null;

      const keyMatch = sel.match(/^\[data-testid\*="([^"]+)"\]$/);
      if (keyMatch) {
        const key = keyMatch[1];
        const pattern = new RegExp(`<([a-z0-9]+)[^>]*data-testid=["'][^"']*${key}[^"']*["'][^>]*>([\\s\\S]*?)<\\/\\1>`, 'i');
        const text = firstTagTextByRegex(raw, pattern);
        return text ? { textContent: text } : null;
      }

      if (sel === '.shop-name' || sel === '.account-name') {
        const key = sel.replace('.', '');
        const pattern = new RegExp(`<([a-z0-9]+)[^>]*class=["'][^"']*${key}[^"']*["'][^>]*>([\\s\\S]*?)<\\/\\1>`, 'i');
        const text = firstTagTextByRegex(raw, pattern);
        return text ? { textContent: text } : null;
      }

      if (sel === 'header h1') {
        const text = firstTagTextByRegex(raw, /<header[^>]*>[\s\S]*?<h1[^>]*>([\s\S]*?)<\/h1>[\s\S]*?<\/header>/i);
        return text ? { textContent: text } : null;
      }

      return null;
    }
  };
}

function assertEqual(actual, expected, label) {
  if (actual !== expected) {
    throw new Error(`${label} expected=${expected} actual=${actual}`);
  }
}

function assertClose(actual, expected, label, tolerance = 0.01) {
  if (Math.abs(Number(actual) - Number(expected)) > tolerance) {
    throw new Error(`${label} expected=${expected} actual=${actual}`);
  }
}

function runCase(title, fixture, pageUrl, verify) {
  const fixturePath = path.join(__dirname, '..', 'tools', 'browser_plugin', 'profit_center_capture', 'test', 'fixtures', fixture);
  const html = fs.readFileSync(fixturePath, 'utf8');
  const doc = makeDoc(html);
  const captured = parser.captureFromDocument(doc, pageUrl);
  if (!captured || !captured.row) {
    throw new Error(`${title} capture row empty`);
  }
  verify(captured.row);
  console.log(`OK: ${title}`);
}

function run() {
  assertClose(parser.parseNumber('USD $1,234.56'), 1234.56, 'parseNumber mixed separators');
  assertClose(parser.parseNumber('12.5k'), 12500, 'parseNumber k unit');
  assertClose(parser.parseNumber('9.8万'), 98000, 'parseNumber chinese unit');
  assertEqual(parser.detectCurrency('₫ 123,000'), 'VND', 'detectCurrency VND');
  assertEqual(parser.detectCurrency('RMB 321'), 'CNY', 'detectCurrency CNY');
  assertEqual(parser.normalizeDate('18/04/2026'), '2026-04-18', 'normalizeDate dmy');

  runCase(
    'ads dashboard usd',
    'ads_dashboard_usd.html',
    'https://ads.tiktok.com/report/overview',
    (row) => {
      assertEqual(row.page_type, 'ad', 'page_type');
      assertEqual(row.entry_date, '2026-04-18', 'entry_date');
      assertEqual(row.store_ref, 'VN Jewelry Shop', 'store_ref');
      assertEqual(row.account_ref, 'GMV MAX US 01', 'account_ref');
      assertClose(row.ad_spend_amount, 1234.56, 'ad_spend_amount');
      assertEqual(row.ad_spend_currency, 'USD', 'ad_spend_currency');
      assertClose(row.gmv_amount, 12345678, 'gmv_amount');
      assertEqual(row.gmv_currency, 'VND', 'gmv_currency');
      assertEqual(row.order_count, 321, 'order_count');
    }
  );

  runCase(
    'shop dashboard vnd',
    'shop_dashboard_vnd.html',
    'https://seller.tiktokglobalshop.com/dashboard',
    (row) => {
      assertEqual(row.page_type, 'shop', 'page_type');
      assertEqual(row.entry_date, '2026-04-18', 'entry_date');
      assertEqual(row.store_ref, 'VN Earrings Flagship', 'store_ref');
      assertEqual(row.account_ref, 'GMV MAX VN MAIN', 'account_ref');
      assertClose(row.ad_spend_amount, 456.78, 'ad_spend_amount');
      assertEqual(row.ad_spend_currency, 'USD', 'ad_spend_currency');
      assertClose(row.gmv_amount, 98765432, 'gmv_amount');
      assertEqual(row.gmv_currency, 'VND', 'gmv_currency');
      assertEqual(row.order_count, 145, 'order_count');
    }
  );

  runCase(
    'shop dashboard cny',
    'shop_dashboard_cny.html',
    'https://seller.tiktokglobalshop.com/overview',
    (row) => {
      assertEqual(row.entry_date, '2026-04-17', 'entry_date');
      assertEqual(row.store_ref, '潮流耳环店', 'store_ref');
      assertEqual(row.account_ref, 'GMV MAX CN', 'account_ref');
      assertClose(row.ad_spend_amount, 888, 'ad_spend_amount');
      assertEqual(row.ad_spend_currency, 'CNY', 'ad_spend_currency');
      assertClose(row.gmv_amount, 6666.66, 'gmv_amount');
      assertEqual(row.gmv_currency, 'CNY', 'gmv_currency');
      assertEqual(row.order_count, 66, 'order_count');
    }
  );

  console.log('All parser tests passed.');
}

try {
  run();
} catch (err) {
  console.error('Parser tests failed:', err && err.message ? err.message : err);
  process.exit(1);
}
