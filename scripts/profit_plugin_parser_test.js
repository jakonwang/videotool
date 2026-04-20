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

      if (sel === '.p-avatar-image img[alt]' || sel === 'img[alt][src*="tiktok"]' || sel === 'img[alt]') {
        const altMatch = raw.match(/<img[^>]*alt=["']([^"']+)["'][^>]*>/i);
        if (!altMatch || !altMatch[1]) return null;
        const altText = decodeHtml(String(altMatch[1] || '').trim());
        if (!altText) return null;
        return {
          textContent: '',
          getAttribute(name) {
            return String(name || '').toLowerCase() === 'alt' ? altText : '';
          }
        };
      }

      return null;
    }
  };
}

function makeTextNode(text) {
  return {
    innerText: String(text || ''),
    textContent: String(text || '')
  };
}

function makeTableRow(cellsText) {
  const cells = (cellsText || []).map((item) => makeTextNode(item));
  return {
    innerText: cells.map((c) => c.innerText).join(' '),
    textContent: cells.map((c) => c.textContent).join(' '),
    querySelectorAll(selector) {
      if (selector === 'td') return cells;
      return [];
    }
  };
}

function makeTableDoc(options) {
  const headers = Array.isArray(options && options.headers) ? options.headers : [];
  const rows = Array.isArray(options && options.rows) ? options.rows : [];
  const headerNodes = headers.map((h) => makeTextNode(h));
  const bodyRows = rows.map((r) => makeTableRow(r));
  const bodyText = String(options && options.bodyText ? options.bodyText : '').trim();
  const tabNode = makeTextNode(options && options.selectedTabText ? options.selectedTabText : '');
  const dateNode = Object.assign(makeTextNode(options && options.topDateRangeText ? options.topDateRangeText : ''), {
    value: String(options && options.topDateRangeText ? options.topDateRangeText : ''),
    getAttribute(name) {
      if (name === 'value') return this.value;
      return '';
    },
    getBoundingClientRect() {
      return { top: Number(options && options.topDateTop != null ? options.topDateTop : 8) };
    }
  });

  const table = {
    querySelectorAll(selector) {
      if (selector === 'thead th') return headerNodes;
      if (selector === 'tbody tr') return bodyRows;
      if (selector === 'tr') return bodyRows;
      return [];
    },
    querySelector(selector) {
      if (selector === 'tr') {
        return {
          querySelectorAll(innerSelector) {
            if (innerSelector === 'th,td') return headerNodes;
            return [];
          }
        };
      }
      return null;
    }
  };

  return {
    body: {
      innerText: bodyText,
      textContent: bodyText
    },
    querySelector(selector) {
      const sel = String(selector || '');
      if (sel === '[data-testid*="store"]') {
        return makeTextNode(options.storeRef || '');
      }
      if (sel === '[data-testid*="account"]') {
        return makeTextNode(options.accountRef || '');
      }
      return null;
    },
    querySelectorAll(selector) {
      if (selector === 'table') return [table];
      if (
        options &&
        options.selectedTabText &&
        (
          selector === '[role="tab"][aria-selected="true"]' ||
          selector === '[role="tab"][aria-checked="true"]' ||
          selector === '[role="tab"][data-selected="true"]' ||
          selector === '[role="tab"][aria-current="page"]' ||
          selector === '.ant-tabs-tab-active' ||
          selector === '.el-tabs__item.is-active' ||
          selector === '.is-active[role="tab"]'
        )
      ) {
        return [tabNode];
      }
      if (options && options.topDateRangeText && /date/i.test(String(selector || ''))) {
        return [dateNode];
      }
      return [];
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
      assertEqual(row.gmv_currency, 'USD', 'gmv_currency');
      assertEqual(row.order_count, 321, 'order_count');
    }
  );

  (function runAvatarAltStoreCase() {
    const html = `
      <div class="p-avatar-image">
        <img src="https://p16-oec-va.ibyteimg.com/test.jpeg" alt="Banano VN" />
      </div>
      <div data-testid="account-name">GMV MAX US 01</div>
      <div>Date: 2026-04-18</div>
      <div>Ad spend: USD 100</div>
      <div>GMV: USD 300</div>
      <div>SKU orders: 12</div>
    `;
    const captured = parser.captureFromDocument(makeDoc(html), 'https://ads.tiktok.com/report/overview');
    if (!captured || !captured.row) {
      throw new Error('avatar alt capture row empty');
    }
    assertEqual(captured.row.store_ref, 'Banano VN', 'store_ref from avatar alt');
    assertEqual(captured.row.account_ref, 'GMV MAX US 01', 'account_ref from testid');
    assertEqual(captured.row.order_count, 12, 'order_count from sku orders');
    console.log('OK: store name from avatar alt');
  })();

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

  (function runCampaignAggregateCase() {
    const doc = makeTableDoc({
      storeRef: 'VN Jewelry Shop',
      accountRef: 'GMV MAX US 01',
      headers: ['Campaign', 'Ad Spend', 'GMV', 'Orders', 'ROI'],
      rows: [
        ['Product GMV Max / A', 'USD $100', 'USD $350', '10', '3.5'],
        ['Product GMV Max / B', 'USD $200', 'USD $600', '20', '3.0'],
        ['LIVE GMV Max / A', 'USD $80', 'USD $240', '8', '3.0'],
        ['LIVE GMV Max / B', 'USD $120', 'USD $420', '12', '3.5']
      ],
      bodyText: 'Date: 2026-04-18 Product GMV Max LIVE GMV Max'
    });
    const captured = parser.captureFromDocument(doc, 'https://ads.tiktok.com/report/campaign');
    if (!captured || !Array.isArray(captured.rows)) {
      throw new Error('campaign aggregate rows empty');
    }
    assertEqual(captured.rows.length, 2, 'campaign aggregate row count');

    const video = captured.rows.find((r) => r.channel_type === 'video');
    const live = captured.rows.find((r) => r.channel_type === 'live');
    if (!video || !live) {
      throw new Error('campaign aggregate missing video/live rows');
    }

    assertClose(video.ad_spend_amount, 300, 'video ad spend');
    assertClose(video.gmv_amount, 950, 'video gmv');
    assertEqual(video.order_count, 30, 'video orders');
    assertEqual(video.ad_spend_currency, 'USD', 'video ad currency');
    assertEqual(video.gmv_currency, 'USD', 'video gmv currency');
    assertClose(video.roi_value, 3.166667, 'video roi', 0.00001);
    assertEqual(Number((video.raw_metrics_json || {}).campaign_count || 0), 2, 'video campaign count');

    assertClose(live.ad_spend_amount, 200, 'live ad spend');
    assertClose(live.gmv_amount, 660, 'live gmv');
    assertEqual(live.order_count, 20, 'live orders');
    assertEqual(live.ad_spend_currency, 'USD', 'live ad currency');
    assertEqual(live.gmv_currency, 'USD', 'live gmv currency');
    assertClose(live.roi_value, 3.3, 'live roi', 0.00001);
    assertEqual(Number((live.raw_metrics_json || {}).campaign_count || 0), 2, 'live campaign count');

    console.log('OK: campaign aggregate by channel');
  })();

  (function runTabAndTopDateCase() {
    const doc = makeTableDoc({
      storeRef: 'VN Jewelry Shop',
      accountRef: 'xRgrSUUE0122Primary',
      selectedTabText: 'LIVE GMV Max',
      topDateRangeText: '2026-04-18 - 2026-04-18',
      headers: ['Campaign', 'Cost', 'GMV', 'SKU orders'],
      rows: [
        ['Series A', 'USD 120.29', 'USD 360.87', '187'],
        ['Series B', 'USD 80.00', 'USD 200.00', '45']
      ],
      bodyText: 'Date: 2026-02-02 LIVE GMV Max'
    });
    const captured = parser.captureFromDocument(doc, 'https://ads.tiktok.com/report/campaign');
    if (!captured || !captured.row) {
      throw new Error('tab/date capture empty');
    }
    const row = captured.row;
    assertEqual(row.entry_date, '2026-04-18', 'top date from range');
    assertEqual(row.channel_type, 'live', 'channel from selected tab');
    assertClose(row.order_count, 232, 'sku orders aggregate', 0.001);
    assertEqual(row.ad_spend_currency, 'USD', 'tab/date ad currency');
    assertEqual(row.gmv_currency, 'USD', 'tab/date gmv currency');
    console.log('OK: selected tab + top date + sku orders');
  })();

  (function runTabSkuNoOverrideCase() {
    const doc = makeTableDoc({
      storeRef: 'VN Jewelry Shop',
      accountRef: 'xRgrSUUE0122Primary',
      selectedTabText: 'LIVE GMV Max',
      topDateRangeText: '2026-04-18 - 2026-04-18',
      headers: ['Campaign', 'Cost', 'GMV', 'SKU orders'],
      rows: [
        ['Series A', 'USD 120.29', 'USD 360.87', '187'],
        ['Series B', 'USD 80.00', 'USD 200.00', '45']
      ],
      bodyText: 'Date: 2026-02-02 LIVE GMV Max SKU orders 999'
    });
    const captured = parser.captureFromDocument(doc, 'https://ads.tiktok.com/report/campaign');
    if (!captured || !captured.row) {
      throw new Error('tab sku no override capture empty');
    }
    const row = captured.row;
    assertEqual(row.channel_type, 'live', 'no override channel');
    assertClose(row.order_count, 232, 'no override keep table sku orders', 0.001);
    console.log('OK: selected tab sku orders no fallback override');
  })();

  (function runTabNoDataZeroCase() {
    const doc = makeTableDoc({
      storeRef: 'VN Jewelry Shop',
      accountRef: 'GMV MAX US 01',
      selectedTabText: 'LIVE GMV Max',
      topDateRangeText: '2026-04-18 - 2026-04-18',
      headers: [],
      rows: [],
      bodyText: 'Date: 2026-04-18 No data available Ad spend: USD 999.99 GMV: USD 3333.33 SKU orders: 88'
    });
    const captured = parser.captureFromDocument(doc, 'https://ads.tiktok.com/report/overview');
    if (!captured || !captured.row) {
      throw new Error('tab no data zero capture empty');
    }
    const row = captured.row;
    assertEqual(row.channel_type, 'live', 'tab no data channel');
    assertEqual(Number(row.ad_spend_amount || 0), 0, 'tab no data ad spend must be zero');
    assertEqual(Number(row.gmv_amount || 0), 0, 'tab no data gmv must be zero');
    assertEqual(Number(row.order_count || 0), 0, 'tab no data orders must be zero');
    console.log('OK: selected tab no data returns zero row');
  })();

  console.log('All parser tests passed.');
}

try {
  run();
} catch (err) {
  console.error('Parser tests failed:', err && err.message ? err.message : err);
  process.exit(1);
}
