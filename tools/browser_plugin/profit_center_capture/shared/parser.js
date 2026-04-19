(function (global) {
  'use strict';

  const currencyMatchers = [
    { code: 'VND', pattern: /(\bVND\b|₫|đ|dong|vietnamese dong)/i },
    { code: 'USD', pattern: /(\bUSD\b|\$|dollar|us\$)/i },
    { code: 'CNY', pattern: /(\bCNY\b|RMB|¥|yuan|元|人民币)/i }
  ];

  const adKeywords = ['ad spend', 'spend', 'cost', '广告费', '广告支出', 'chi tiêu', 'chi phi quang cao'];
  const gmvKeywords = ['gmv', 'gross merchandise value', '成交', '成交额', 'doanh thu', 'sales'];
  const orderKeywords = ['orders', 'order', 'paid orders', '订单', '订单数', 'đơn', 'so don'];

  function normalizeDate(raw) {
    const text = String(raw || '').trim();
    if (!text) return '';

    let m = text.match(/(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})/);
    if (m) {
      return `${m[1]}-${String(Number(m[2])).padStart(2, '0')}-${String(Number(m[3])).padStart(2, '0')}`;
    }

    m = text.match(/(\d{1,2})[-\/.](\d{1,2})[-\/.](20\d{2})/);
    if (m) {
      return `${m[3]}-${String(Number(m[2])).padStart(2, '0')}-${String(Number(m[1])).padStart(2, '0')}`;
    }

    return '';
  }

  function todayText() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  }

  function detectCurrency(raw) {
    const text = String(raw || '');
    let picked = '';
    let pickedIndex = Number.MAX_SAFE_INTEGER;
    for (const item of currencyMatchers) {
      const probe = new RegExp(item.pattern.source, item.pattern.flags);
      const matched = probe.exec(text);
      if (matched && matched.index < pickedIndex) {
        picked = item.code;
        pickedIndex = matched.index;
      }
    }
    return picked;
  }

  function detectUnitMultiplier(text) {
    const raw = String(text || '');
    if (/[bB]/.test(raw)) return 1000000000;
    if (/[mM]/.test(raw)) return 1000000;
    if (/[kK]/.test(raw)) return 1000;
    if (/亿|億/.test(raw)) return 100000000;
    if (/万|萬/.test(raw)) return 10000;
    if (/千/.test(raw)) return 1000;
    return 1;
  }

  function normalizeNumberText(text) {
    let value = String(text || '').trim();
    if (!value) return '';

    value = value.replace(/[，]/g, ',').replace(/[．]/g, '.');
    value = value.replace(/\s+/g, '');

    if (value.includes(',') && value.includes('.')) {
      const lastComma = value.lastIndexOf(',');
      const lastDot = value.lastIndexOf('.');
      if (lastComma > lastDot) {
        value = value.replace(/\./g, '').replace(',', '.');
      } else {
        value = value.replace(/,/g, '');
      }
      return value;
    }

    if (value.includes(',') && !value.includes('.')) {
      const parts = value.split(',');
      if (parts.length > 1 && parts[parts.length - 1].length <= 2) {
        return parts.slice(0, -1).join('') + '.' + parts[parts.length - 1];
      }
      return parts.join('');
    }

    if (value.includes('.') && !value.includes(',')) {
      const parts = value.split('.');
      if (parts.length > 2 && parts.slice(1).every((p) => p.length === 3)) {
        return parts.join('');
      }
      if (parts.length === 2 && parts[1].length === 3) {
        return parts.join('');
      }
    }

    return value;
  }

  function parseNumber(raw) {
    const text = String(raw || '').trim();
    if (!text) return null;

    const match = text.match(/-?\d[\d.,\s]*\d|-?\d/);
    if (!match) return null;
    const multiplier = detectUnitMultiplier(text);
    const normalized = normalizeNumberText(match[0]);
    const value = Number(normalized);
    if (!Number.isFinite(value)) return null;
    return Math.round(value * multiplier * 100) / 100;
  }

  function sanitizeText(doc) {
    if (!doc || !doc.body) return '';
    return String(doc.body.innerText || doc.body.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function findDateFromText(text) {
    const raw = String(text || '');
    const candidates = raw.match(/(20\d{2}[-\/.]\d{1,2}[-\/.]\d{1,2})|(\d{1,2}[-\/.]\d{1,2}[-\/.]20\d{2})/g) || [];
    for (const c of candidates) {
      const normalized = normalizeDate(c);
      if (normalized) return normalized;
    }
    return '';
  }

  function safeRegExp(text) {
    return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function metricCandidates(rawText, keyword) {
    const source = String(rawText || '');
    const list = [];
    const pattern = new RegExp(`(${safeRegExp(keyword)})[^\\d\\-]{0,20}([-+]?\\d[\\d.,\\s]*\\d|[-+]?\\d)(\\s*[kKmMbB千萬万亿億]?)`, 'ig');
    let m;
    while ((m = pattern.exec(source))) {
      const chunk = source.slice(Math.max(0, m.index - 20), Math.min(source.length, pattern.lastIndex + 30));
      const scoped = String(m[0] || '') + source.slice(pattern.lastIndex, Math.min(source.length, pattern.lastIndex + 12));
      const number = parseNumber((m[2] || '') + (m[3] || ''));
      if (number !== null) {
        list.push({
          value: number,
          currency: detectCurrency(scoped) || detectCurrency(chunk),
          chunk,
          index: m.index
        });
      }
    }
    return list;
  }

  function findMetricNear(text, keywords) {
    const raw = String(text || '');
    const candidates = [];
    for (const keyword of keywords) {
      const list = metricCandidates(raw, keyword);
      if (list.length > 0) {
        candidates.push(...list);
        continue;
      }
      const lower = raw.toLowerCase();
      const idx = lower.indexOf(String(keyword).toLowerCase());
      if (idx >= 0) {
        const chunk = raw.slice(Math.max(0, idx - 20), Math.min(raw.length, idx + 140));
        const number = parseNumber(chunk);
        if (number !== null) {
          return {
            value: number,
            currency: detectCurrency(chunk),
            chunk
          };
        }
      }
    }
    if (candidates.length > 0) {
      const pool = candidates.filter((item) => item.currency !== '');
      const target = pool.length > 0 ? pool : candidates;
      target.sort((a, b) => Math.abs(Number(b.value || 0)) - Math.abs(Number(a.value || 0)));
      return target[0];
    }
    return { value: null, currency: '', chunk: '' };
  }

  function findOrderNear(text) {
    const metric = findMetricNear(text, orderKeywords);
    if (metric.value === null) return null;
    return Math.max(0, Math.floor(metric.value));
  }

  function pickText(doc, selectors) {
    if (!doc || typeof doc.querySelector !== 'function') return '';
    for (const sel of selectors) {
      const node = doc.querySelector(sel);
      if (!node) continue;
      const txt = String(node.textContent || '').trim();
      if (txt) return txt;
    }
    return '';
  }

  function fieldByLabel(text, labels) {
    const source = String(text || '');
    const stopWords = ['Store', 'Shop', '店铺', 'Cua hang', 'Account', '广告账户', 'Tai khoan', '广告费', '成交', 'GMV', '订单', 'Date', '统计日期'];
    const stopPattern = stopWords.map((item) => safeRegExp(item)).join('|');
    for (const label of labels) {
      const pattern = new RegExp(`${safeRegExp(label)}\\s*[:：]\\s*([\\s\\S]{1,80}?)(?=\\s*(?:${stopPattern})\\s*[:：]|$)`, 'i');
      const m = source.match(pattern);
      if (m && m[1]) {
        const out = String(m[1]).trim();
        if (out) return out;
      }
    }
    return '';
  }

  function detectPageType(url, text) {
    const host = (() => {
      try { return new URL(String(url || '')).hostname.toLowerCase(); } catch (_) { return ''; }
    })();
    if (host.includes('ads.tiktok.com')) return 'ad';
    if (host.includes('seller.tiktokglobalshop.com') || host.includes('tiktokglobalshop.com')) return 'shop';

    const lower = String(text || '').toLowerCase();
    if (lower.includes('ad spend') || lower.includes('广告费') || lower.includes('campaign')) return 'ad';
    if (lower.includes('gmv') || lower.includes('订单') || lower.includes('shop')) return 'shop';
    return 'unknown';
  }

  function fallbackCurrency(pageType) {
    return pageType === 'ad' ? 'USD' : 'VND';
  }

  function captureFromDocument(doc, pageUrl) {
    const text = sanitizeText(doc);
    const pageType = detectPageType(pageUrl, text);
    const entryDate = findDateFromText(text) || todayText();

    const storeRef = pickText(doc, [
      '[data-testid*="store"]',
      '[class*="store"] [class*="name"]',
      '.shop-name',
      'header h1'
    ]) || fieldByLabel(text, ['Store', 'Shop', '店铺', 'Cua hang']);

    const accountRef = pickText(doc, [
      '[data-testid*="account"]',
      '[class*="account"] [class*="name"]',
      '.account-name'
    ]) || fieldByLabel(text, ['Account', '广告账户', 'Tai khoan']);

    const adMetric = findMetricNear(text, adKeywords);
    const gmvMetric = findMetricNear(text, gmvKeywords);
    const orderCount = findOrderNear(text);

    return {
      row: {
        source_page: String(pageUrl || ''),
        page_type: pageType,
        captured_at: new Date().toISOString(),
        entry_date: entryDate,
        store_ref: storeRef || '',
        account_ref: accountRef || '',
        channel_type: 'video',
        ad_spend_amount: adMetric.value,
        ad_spend_currency: adMetric.currency || fallbackCurrency('ad'),
        gmv_amount: gmvMetric.value,
        gmv_currency: gmvMetric.currency || fallbackCurrency('shop'),
        order_count: orderCount
      },
      debug: {
        page_type: pageType,
        ad_chunk: adMetric.chunk,
        gmv_chunk: gmvMetric.chunk
      }
    };
  }

  const api = {
    normalizeDate,
    parseNumber,
    detectCurrency,
    captureFromDocument
  };

  global.ProfitPluginParser = api;
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
})(typeof window !== 'undefined' ? window : globalThis);
