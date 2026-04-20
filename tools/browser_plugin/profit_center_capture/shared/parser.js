(function (global) {
  'use strict';

  const CHANNEL_VIDEO = 'video';
  const CHANNEL_LIVE = 'live';
  const CHANNEL_INFLUENCER = 'influencer';

  const currencyMatchers = [
    { code: 'VND', pattern: /(\bVND\b|₫|đ|dong|vietnamese dong)/i },
    { code: 'USD', pattern: /(\bUSD\b|\$|dollar|us\$)/i },
    { code: 'CNY', pattern: /(\bCNY\b|RMB|¥|yuan|元|人民币)/i }
  ];

  const adKeywords = ['ad spend', 'spend', 'cost', '广告费', '广告支出', 'chi tiêu', 'chi phi quang cao'];
  const gmvKeywords = ['gmv', 'gross merchandise value', '成交', '成交额', 'doanh thu', 'sales'];
  const orderKeywords = ['orders', 'order', 'paid orders', 'sku orders', 'sku order', 'skuorders', '订单', '订单数', 'đơn', 'so don'];
  const roiKeywords = ['roi', 'roas', '投入产出', '回报'];

  const tableHeaderKeywords = {
    campaign: ['campaign', 'campaign name', 'ad group', 'advertising group', '广告系列', '广告组', '计划', '活动'],
    spend: ['ad spend', 'spend', 'cost', '花费', '广告费', '广告支出', 'chi tieu', 'chi phi'],
    gmv: ['gmv', 'sales', 'revenue', '成交', '成交额', 'doanh thu'],
    orders: ['orders', 'order', 'paid orders', '订单', '订单数', 'so don', 'don'],
    roi: ['roi', 'roas', '投入产出', '回报']
  };

  function roundTo(value, precision) {
    const p = Number(precision || 0);
    const n = Number(value || 0);
    if (!Number.isFinite(n)) return 0;
    const factor = Math.pow(10, p);
    return Math.round(n * factor) / factor;
  }

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

  function detectUnitMultiplier(unitToken) {
    const token = String(unitToken || '').trim();
    if (!token) return 1;
    if (/^[bB]$/.test(token)) return 1000000000;
    if (/^[mM]$/.test(token)) return 1000000;
    if (/^[kK]$/.test(token)) return 1000;
    if (/^(亿|億)$/.test(token)) return 100000000;
    if (/^(万|萬)$/.test(token)) return 10000;
    if (/^千$/.test(token)) return 1000;
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

    const match = text.match(/([-+]?\d[\d.,\s]*\d|[-+]?\d)\s*([kKmMbB]|亿|億|万|萬|千)?/);
    if (!match) return null;
    const multiplier = detectUnitMultiplier(match[2] || '');
    const normalized = normalizeNumberText(match[1] || '');
    const value = Number(normalized);
    if (!Number.isFinite(value)) return null;
    return roundTo(value * multiplier, 2);
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

  function pickAttr(doc, selectors, attrName) {
    if (!doc || typeof doc.querySelector !== 'function') return '';
    const key = String(attrName || '').trim();
    if (!key) return '';
    for (const sel of selectors) {
      const node = doc.querySelector(sel);
      if (!node || typeof node.getAttribute !== 'function') continue;
      const raw = node.getAttribute(key);
      const txt = String(raw || '').trim();
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

  function detectChannelFromUrl(pageUrl) {
    const raw = String(pageUrl || '').toLowerCase();
    if (!raw) return '';
    if (raw.includes('live') && raw.includes('gmv')) return CHANNEL_LIVE;
    if (raw.includes('product') && raw.includes('gmv')) return CHANNEL_VIDEO;
    if (raw.includes('influencer') || raw.includes('creator')) return CHANNEL_INFLUENCER;
    return '';
  }

  function fallbackCurrency(pageType) {
    return pageType === 'ad' ? 'USD' : 'VND';
  }

  function extractRangeStartDate(raw) {
    const text = String(raw || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
    if (!text) return '';

    const sep = '(?:-|–|—|~|to)';
    const ymd = new RegExp(`(20\\d{2}[\\/.\\-]\\d{1,2}[\\/.\\-]\\d{1,2})\\s*${sep}\\s*(20\\d{2}[\\/.\\-]\\d{1,2}[\\/.\\-]\\d{1,2})`, 'i');
    const dmy = new RegExp(`(\\d{1,2}[\\/.\\-]\\d{1,2}[\\/.\\-]20\\d{2})\\s*${sep}\\s*(\\d{1,2}[\\/.\\-]\\d{1,2}[\\/.\\-]20\\d{2})`, 'i');

    let matched = text.match(ymd);
    if (matched && matched[1]) {
      return normalizeDate(matched[1]);
    }
    matched = text.match(dmy);
    if (matched && matched[1]) {
      return normalizeDate(matched[1]);
    }
    return '';
  }

  function parseTopDateCandidates(doc) {
    if (!doc || typeof doc.querySelectorAll !== 'function') return [];
    const selectors = [
      '[data-testid*="date"]',
      '[data-testid*="Date"]',
      '[class*="date-range"]',
      '[class*="DateRange"]',
      '[class*="daterange"]',
      '[class*="date-picker"]',
      '[class*="DatePicker"]',
      '[aria-label*="date"]',
      '[aria-label*="Date"]',
      'input[placeholder*="date"]',
      'input[placeholder*="Date"]',
      'input[type="text"]'
    ];

    const seen = new Set();
    const candidates = [];

    selectors.forEach((selector) => {
      let nodes = [];
      try {
        nodes = Array.from(doc.querySelectorAll(selector));
      } catch (_) {
        nodes = [];
      }
      nodes.forEach((node) => {
        if (!node || seen.has(node)) return;
        seen.add(node);
        const value = String((node && node.value) || '');
        const attrValue = typeof node.getAttribute === 'function' ? String(node.getAttribute('value') || '') : '';
        const text = [nodeText(node), value, attrValue].join(' ').trim();
        if (!text) return;
        const date = extractRangeStartDate(text) || normalizeDate(text);
        if (!date) return;

        let top = Number.MAX_SAFE_INTEGER;
        if (typeof node.getBoundingClientRect === 'function') {
          try {
            const rect = node.getBoundingClientRect();
            if (rect && Number.isFinite(rect.top) && Number(rect.top) > -200) {
              top = Number(rect.top);
            }
          } catch (_) {
            top = Number.MAX_SAFE_INTEGER;
          }
        }
        candidates.push({ date, top });
      });
    });

    return candidates;
  }

  function detectTopDateFromDom(doc, text) {
    const candidates = parseTopDateCandidates(doc);
    if (candidates.length > 0) {
      candidates.sort((a, b) => a.top - b.top);
      return candidates[0].date || '';
    }
    return extractRangeStartDate(text);
  }

  function hasNoDataHint(text) {
    const raw = String(text || '').toLowerCase();
    if (!raw) return false;
    const hints = [
      'no data',
      'no data available',
      '暂无数据',
      '无数据',
      '没有数据',
      'khong co du lieu',
      'không có dữ liệu'
    ];
    return hints.some((hint) => raw.includes(hint));
  }

  function normalizeKey(text) {
    return String(text || '')
      .toLowerCase()
      .replace(/\s+/g, '')
      .replace(/[_\-]/g, '')
      .replace(/[^\w\u4e00-\u9fa5]/g, '');
  }

  function detectChannelFromText(raw) {
    const text = normalizeKey(raw);
    if (!text) return '';

    if (text.includes('livegmvmax') || text.includes('livegvmmax') || text.includes('直播')) {
      return CHANNEL_LIVE;
    }
    if (text.includes('productgmvmax') || text.includes('videogmvmax') || text.includes('视频')) {
      return CHANNEL_VIDEO;
    }
    if (text.includes('influencer') || text.includes('creator') || text.includes('达人')) {
      return CHANNEL_INFLUENCER;
    }
    return '';
  }

  function detectSelectedTabChannel(doc) {
    if (!doc || typeof doc.querySelectorAll !== 'function') return '';
    const selectors = [
      '[role="tab"][aria-selected="true"]',
      '[role="tab"][aria-checked="true"]',
      '[role="tab"][data-selected="true"]',
      '[role="tab"][aria-current="page"]',
      '.ant-tabs-tab-active',
      '.el-tabs__item.is-active',
      '.is-active[role="tab"]'
    ];
    const seen = new Set();
    for (const selector of selectors) {
      let nodes = [];
      try {
        nodes = Array.from(doc.querySelectorAll(selector));
      } catch (_) {
        nodes = [];
      }
      for (const node of nodes) {
        if (!node || seen.has(node)) continue;
        seen.add(node);
        const text = nodeText(node);
        const channel = detectChannelFromText(text);
        if (channel) {
          return channel;
        }
      }
    }
    return '';
  }

  function nodeText(node) {
    return String((node && (node.innerText || node.textContent)) || '').replace(/\s+/g, ' ').trim();
  }

  function extractSkuOrdersFromRawLines(doc) {
    if (!doc || !doc.body) return null;
    const raw = String(doc.body.innerText || doc.body.textContent || '');
    if (!raw) return null;
    const lines = raw.split(/\r?\n/).map((line) => String(line || '').trim());
    if (lines.length === 0) return null;

    const headerPattern = /sku\s*orders?/i;
    let bestSum = 0;
    let bestCount = 0;

    for (let i = 0; i < lines.length; i += 1) {
      if (!headerPattern.test(lines[i])) continue;
      let sum = 0;
      let count = 0;
      for (let j = i + 1; j < lines.length && j <= i + 260; j += 1) {
        const line = lines[j];
        if (!line) {
          if (count > 0) break;
          continue;
        }
        if (headerPattern.test(line) && count > 0) break;

        const hasLetters = /[a-zA-Z\u4e00-\u9fa5]/.test(line);
        const value = parseNumber(line);
        if (value !== null) {
          sum += Math.max(0, Math.floor(Number(value)));
          count += 1;
          continue;
        }
        if (hasLetters && count > 0) break;
      }
      if (count > bestCount || (count === bestCount && sum > bestSum)) {
        bestCount = count;
        bestSum = sum;
      }
    }

    return bestCount > 0 ? bestSum : null;
  }

  function findHeaderIndex(headers, keywords) {
    const normalized = headers.map((h) => normalizeKey(h));
    const keys = (keywords || []).map((k) => normalizeKey(k));
    for (let i = 0; i < normalized.length; i += 1) {
      for (const key of keys) {
        if (!key) continue;
        if (normalized[i].includes(key)) {
          return i;
        }
      }
    }
    return -1;
  }

  function aggregateCampaignTables(doc, pageType, defaultChannel, forceChannelByTab) {
    if (!doc || typeof doc.querySelectorAll !== 'function') return [];
    const tables = Array.from(doc.querySelectorAll('table'));
    if (tables.length === 0) return [];

    const groups = {};

    tables.forEach((table) => {
      let headerCells = Array.from(table.querySelectorAll('thead th'));
      if (headerCells.length === 0) {
        const firstRow = table.querySelector('tr');
        if (firstRow) {
          headerCells = Array.from(firstRow.querySelectorAll('th,td'));
        }
      }
      const headers = headerCells.map((node) => nodeText(node)).filter(Boolean);
      if (headers.length === 0) return;

      const idxCampaign = findHeaderIndex(headers, tableHeaderKeywords.campaign);
      const idxSpend = findHeaderIndex(headers, tableHeaderKeywords.spend);
      const idxGmv = findHeaderIndex(headers, tableHeaderKeywords.gmv);
      const idxOrders = findHeaderIndex(headers, tableHeaderKeywords.orders);
      const idxRoi = findHeaderIndex(headers, tableHeaderKeywords.roi);

      if (idxSpend < 0 && idxGmv < 0 && idxOrders < 0 && idxRoi < 0) return;

      let rows = Array.from(table.querySelectorAll('tbody tr'));
      rows = rows.filter((row) => row.querySelectorAll('td').length > 0);
      if (rows.length === 0) {
        rows = Array.from(table.querySelectorAll('tr')).filter((row) => row.querySelectorAll('td').length > 0);
      }

      rows.forEach((row) => {
        const cells = Array.from(row.querySelectorAll('td'));
        if (cells.length === 0) return;

        const getCellText = (idx) => {
          if (idx < 0 || idx >= cells.length) return '';
          return nodeText(cells[idx]);
        };

        const rowText = nodeText(row);
        if (!rowText) return;

        const campaignText = getCellText(idxCampaign) || rowText;
        let channelType = '';
        if (forceChannelByTab && defaultChannel) {
          channelType = defaultChannel;
        } else {
          channelType = detectChannelFromText(campaignText) || detectChannelFromText(rowText);
        }
        if (!channelType && defaultChannel) {
          channelType = defaultChannel;
        }
        if (!channelType && pageType === 'ad') {
          channelType = CHANNEL_VIDEO;
        }

        let spendValue = idxSpend >= 0 ? parseNumber(getCellText(idxSpend)) : null;
        let gmvValue = idxGmv >= 0 ? parseNumber(getCellText(idxGmv)) : null;
        let orderValue = idxOrders >= 0 ? parseNumber(getCellText(idxOrders)) : null;
        let roiValue = idxRoi >= 0 ? parseNumber(getCellText(idxRoi)) : null;

        if (spendValue === null) {
          const m = findMetricNear(rowText, adKeywords);
          spendValue = m.value;
        }
        if (gmvValue === null) {
          const m = findMetricNear(rowText, gmvKeywords);
          gmvValue = m.value;
        }
        if (orderValue === null) {
          orderValue = findOrderNear(rowText);
        }
        if (roiValue === null) {
          const m = findMetricNear(rowText, roiKeywords);
          roiValue = m.value;
        }

        if (spendValue === null && gmvValue === null && orderValue === null) return;

        const adCurrency = detectCurrency(`${getCellText(idxSpend)} ${rowText}`);
        const gmvCurrency = detectCurrency(`${getCellText(idxGmv)} ${rowText}`);
        const key = channelType || defaultChannel || (pageType === 'ad' ? CHANNEL_VIDEO : 'unknown');

        if (!groups[key]) {
          groups[key] = {
            channel_type: key,
            ad_spend_amount: 0,
            gmv_amount: 0,
            order_count: 0,
            roi_sum: 0,
            roi_count: 0,
            campaign_count: 0,
            ad_spend_currency: '',
            gmv_currency: '',
            example_campaign: ''
          };
        }

        const group = groups[key];
        if (spendValue !== null) group.ad_spend_amount += Number(spendValue);
        if (gmvValue !== null) group.gmv_amount += Number(gmvValue);
        if (orderValue !== null) group.order_count += Math.max(0, Math.floor(Number(orderValue)));
        if (roiValue !== null) {
          group.roi_sum += Number(roiValue);
          group.roi_count += 1;
        }
        group.campaign_count += 1;

        if (!group.ad_spend_currency && adCurrency) group.ad_spend_currency = adCurrency;
        if (!group.gmv_currency && gmvCurrency) group.gmv_currency = gmvCurrency;
        if (!group.example_campaign) group.example_campaign = campaignText;
      });
    });

    const list = Object.values(groups).filter((item) => {
      return item.campaign_count > 0 && (
        item.ad_spend_amount > 0 || item.gmv_amount > 0 || item.order_count > 0
      );
    });

    if (list.length === 0) return [];

    const preferred = list.filter((item) => {
      return item.channel_type === CHANNEL_VIDEO || item.channel_type === CHANNEL_LIVE || item.channel_type === CHANNEL_INFLUENCER;
    });

    return preferred.length > 0 ? preferred : list;
  }

  function aggregateCampaignRoleGrids(doc, pageType, defaultChannel, forceChannelByTab) {
    if (!doc || typeof doc.querySelectorAll !== 'function') return [];
    const grids = Array.from(doc.querySelectorAll('[role="grid"], [role="table"]'));
    if (grids.length === 0) return [];

    const groups = {};
    grids.forEach((grid) => {
      const allRows = Array.from(grid.querySelectorAll('[role="row"]'));
      if (allRows.length === 0) return;

      let headerNodes = [];
      for (const row of allRows) {
        const headers = Array.from(row.querySelectorAll('[role="columnheader"]'));
        if (headers.length > 0) {
          headerNodes = headers;
          break;
        }
      }
      const headers = headerNodes.map((node) => nodeText(node)).filter(Boolean);
      if (headers.length === 0) return;

      const idxCampaign = findHeaderIndex(headers, tableHeaderKeywords.campaign);
      const idxSpend = findHeaderIndex(headers, tableHeaderKeywords.spend);
      const idxGmv = findHeaderIndex(headers, tableHeaderKeywords.gmv);
      const idxOrders = findHeaderIndex(headers, tableHeaderKeywords.orders);
      const idxRoi = findHeaderIndex(headers, tableHeaderKeywords.roi);
      if (idxSpend < 0 && idxGmv < 0 && idxOrders < 0 && idxRoi < 0) return;

      const dataRows = allRows.filter((row) => {
        const cells = row.querySelectorAll('[role="gridcell"], [role="cell"]');
        return cells && cells.length > 0;
      });
      dataRows.forEach((row) => {
        const cells = Array.from(row.querySelectorAll('[role="gridcell"], [role="cell"]'));
        if (cells.length === 0) return;

        const getCellText = (idx) => {
          if (idx < 0 || idx >= cells.length) return '';
          return nodeText(cells[idx]);
        };

        const rowText = nodeText(row);
        if (!rowText) return;

        const campaignText = getCellText(idxCampaign) || rowText;
        let channelType = '';
        if (forceChannelByTab && defaultChannel) {
          channelType = defaultChannel;
        } else {
          channelType = detectChannelFromText(campaignText) || detectChannelFromText(rowText);
        }
        if (!channelType && defaultChannel) {
          channelType = defaultChannel;
        }
        if (!channelType && pageType === 'ad') {
          channelType = CHANNEL_VIDEO;
        }

        let spendValue = idxSpend >= 0 ? parseNumber(getCellText(idxSpend)) : null;
        let gmvValue = idxGmv >= 0 ? parseNumber(getCellText(idxGmv)) : null;
        let orderValue = idxOrders >= 0 ? parseNumber(getCellText(idxOrders)) : null;
        let roiValue = idxRoi >= 0 ? parseNumber(getCellText(idxRoi)) : null;

        if (spendValue === null) {
          const m = findMetricNear(rowText, adKeywords);
          spendValue = m.value;
        }
        if (gmvValue === null) {
          const m = findMetricNear(rowText, gmvKeywords);
          gmvValue = m.value;
        }
        if (orderValue === null) {
          orderValue = findOrderNear(rowText);
        }
        if (roiValue === null) {
          const m = findMetricNear(rowText, roiKeywords);
          roiValue = m.value;
        }

        if (spendValue === null && gmvValue === null && orderValue === null) return;

        const adCurrency = detectCurrency(`${getCellText(idxSpend)} ${rowText}`);
        const gmvCurrency = detectCurrency(`${getCellText(idxGmv)} ${rowText}`);
        const key = channelType || defaultChannel || (pageType === 'ad' ? CHANNEL_VIDEO : 'unknown');

        if (!groups[key]) {
          groups[key] = {
            channel_type: key,
            ad_spend_amount: 0,
            gmv_amount: 0,
            order_count: 0,
            roi_sum: 0,
            roi_count: 0,
            campaign_count: 0,
            ad_spend_currency: '',
            gmv_currency: '',
            example_campaign: ''
          };
        }

        const group = groups[key];
        if (spendValue !== null) group.ad_spend_amount += Number(spendValue);
        if (gmvValue !== null) group.gmv_amount += Number(gmvValue);
        if (orderValue !== null) group.order_count += Math.max(0, Math.floor(Number(orderValue)));
        if (roiValue !== null) {
          group.roi_sum += Number(roiValue);
          group.roi_count += 1;
        }
        group.campaign_count += 1;

        if (!group.ad_spend_currency && adCurrency) group.ad_spend_currency = adCurrency;
        if (!group.gmv_currency && gmvCurrency) group.gmv_currency = gmvCurrency;
        if (!group.example_campaign) group.example_campaign = campaignText;
      });
    });

    const list = Object.values(groups).filter((item) => {
      return item.campaign_count > 0 && (
        item.ad_spend_amount > 0 || item.gmv_amount > 0 || item.order_count > 0
      );
    });
    if (list.length === 0) return [];
    const preferred = list.filter((item) => {
      return item.channel_type === CHANNEL_VIDEO || item.channel_type === CHANNEL_LIVE || item.channel_type === CHANNEL_INFLUENCER;
    });
    return preferred.length > 0 ? preferred : list;
  }

  function buildAggregatedRows(aggregates, base) {
    const rows = [];
    aggregates.forEach((agg) => {
      const adSpendAmount = roundTo(agg.ad_spend_amount || 0, 2);
      const gmvAmount = roundTo(agg.gmv_amount || 0, 2);
      const adCurrency = String(agg.ad_spend_currency || '').toUpperCase() || fallbackCurrency('ad');
      let gmvCurrency = String(agg.gmv_currency || '').toUpperCase();
      if (!gmvCurrency) {
        gmvCurrency = base.page_type === 'ad' ? adCurrency : fallbackCurrency('shop');
      }
      if (base.page_type === 'ad') {
        gmvCurrency = adCurrency;
      }

      const computedRoi = adSpendAmount > 0 ? roundTo(gmvAmount / adSpendAmount, 6) : null;
      const avgRoi = agg.roi_count > 0 ? roundTo(agg.roi_sum / agg.roi_count, 6) : null;

      const rawMetrics = {
        capture_mode: 'campaign_aggregate',
        campaign_count: Number(agg.campaign_count || 0),
        total_roi: computedRoi,
        total_ad_spend: adSpendAmount,
        total_gmv: gmvAmount,
        total_orders: Math.max(0, Number(agg.order_count || 0))
      };
      if (avgRoi !== null) {
        rawMetrics.avg_roi_from_rows = avgRoi;
      }
      if (agg.example_campaign) {
        rawMetrics.example_campaign = String(agg.example_campaign).slice(0, 180);
      }

      rows.push({
        source_page: base.source_page,
        page_type: base.page_type,
        captured_at: new Date().toISOString(),
        entry_date: base.entry_date,
        store_ref: base.store_ref,
        account_ref: base.account_ref,
        channel_type: agg.channel_type || base.channel_type || CHANNEL_VIDEO,
        ad_spend_amount: adSpendAmount,
        ad_spend_currency: adCurrency,
        gmv_amount: gmvAmount,
        gmv_currency: gmvCurrency,
        order_count: Math.max(0, Number(agg.order_count || 0)),
        roi_value: computedRoi,
        raw_metrics_json: rawMetrics
      });
    });
    return rows;
  }

  function buildZeroRow(base, reason) {
    const adCurrency = fallbackCurrency('ad');
    const gmvCurrency = base.page_type === 'ad' ? adCurrency : fallbackCurrency('shop');
    return {
      row: {
        source_page: base.source_page,
        page_type: base.page_type,
        captured_at: new Date().toISOString(),
        entry_date: base.entry_date,
        store_ref: base.store_ref || '',
        account_ref: base.account_ref || '',
        channel_type: base.channel_type || CHANNEL_VIDEO,
        ad_spend_amount: 0,
        ad_spend_currency: adCurrency,
        gmv_amount: 0,
        gmv_currency: gmvCurrency,
        order_count: 0,
        roi_value: null,
        raw_metrics_json: {
          capture_mode: 'zero_fallback',
          no_data_reason: String(reason || 'tab_no_rows')
        }
      },
      rows: [],
      debug: {
        page_type: base.page_type,
        aggregate_mode: 'zero',
        resolved_channel: base.channel_type || '',
        no_data_reason: String(reason || 'tab_no_rows')
      }
    };
  }

  function captureFromDocument(doc, pageUrl) {
    const text = sanitizeText(doc);
    const pageType = detectPageType(pageUrl, text);
    const entryDate = detectTopDateFromDom(doc, text) || findDateFromText(text) || todayText();
    const selectedTabChannel = detectSelectedTabChannel(doc) || detectChannelFromUrl(pageUrl);
    const inferredChannel = detectChannelFromText(text);
    const resolvedChannel = selectedTabChannel || inferredChannel || CHANNEL_VIDEO;
    const forceChannelByTab = !!selectedTabChannel;

    const storeRef = pickText(doc, [
      '[data-testid*="store"]',
      '[class*="store"] [class*="name"]',
      '.shop-name',
      'header h1'
    ]) || pickAttr(doc, [
      '.p-avatar-image img[alt]',
      'img[alt][src*="tiktok"]',
      'img[alt]'
    ], 'alt') || fieldByLabel(text, ['Store', 'Shop', '店铺', 'Cua hang']);

    const accountRef = pickText(doc, [
      '[data-testid*="account"]',
      '[class*="account"] [class*="name"]',
      '.account-name'
    ]) || fieldByLabel(text, ['Account', '广告账户', 'Tai khoan']);

    const skuOrdersFallback = extractSkuOrdersFromRawLines(doc);

    const base = {
      source_page: String(pageUrl || ''),
      page_type: pageType,
      entry_date: entryDate,
      store_ref: storeRef || '',
      account_ref: accountRef || '',
      channel_type: resolvedChannel,
      force_channel_by_tab: forceChannelByTab,
      sku_orders_fallback: skuOrdersFallback
    };

    if (forceChannelByTab && hasNoDataHint(text)) {
      return buildZeroRow(base, 'tab_no_data_hint');
    }

    const tableAggregates = aggregateCampaignTables(doc, pageType, base.channel_type || CHANNEL_VIDEO, forceChannelByTab);
    if (tableAggregates.length > 0) {
      const rows = buildAggregatedRows(tableAggregates, base);
      if (rows.length > 0) {
        if (
          forceChannelByTab &&
          Number(base.sku_orders_fallback || 0) > 0 &&
          rows.length === 1 &&
          Number(rows[0].order_count || 0) <= 0
        ) {
          rows[0].order_count = Math.max(0, Number(base.sku_orders_fallback || 0));
          if (rows[0].raw_metrics_json && typeof rows[0].raw_metrics_json === 'object') {
            rows[0].raw_metrics_json.total_orders = rows[0].order_count;
          }
        }
        return {
          row: rows[0],
          rows,
          debug: {
            page_type: pageType,
            aggregate_mode: 'table',
            aggregate_rows: rows.length,
            aggregate_campaigns: rows.reduce((sum, item) => sum + Number((item.raw_metrics_json && item.raw_metrics_json.campaign_count) || 0), 0),
            selected_tab_channel: selectedTabChannel || '',
            resolved_channel: resolvedChannel
          }
        };
      }
    }

    const roleGridAggregates = aggregateCampaignRoleGrids(doc, pageType, base.channel_type || CHANNEL_VIDEO, forceChannelByTab);
    if (roleGridAggregates.length > 0) {
      const rows = buildAggregatedRows(roleGridAggregates, base);
      if (rows.length > 0) {
        if (
          forceChannelByTab &&
          Number(base.sku_orders_fallback || 0) > 0 &&
          rows.length === 1 &&
          Number(rows[0].order_count || 0) <= 0
        ) {
          rows[0].order_count = Math.max(0, Number(base.sku_orders_fallback || 0));
          if (rows[0].raw_metrics_json && typeof rows[0].raw_metrics_json === 'object') {
            rows[0].raw_metrics_json.total_orders = rows[0].order_count;
          }
        }
        return {
          row: rows[0],
          rows,
          debug: {
            page_type: pageType,
            aggregate_mode: 'role_grid',
            aggregate_rows: rows.length,
            aggregate_campaigns: rows.reduce((sum, item) => sum + Number((item.raw_metrics_json && item.raw_metrics_json.campaign_count) || 0), 0),
            selected_tab_channel: selectedTabChannel || '',
            resolved_channel: resolvedChannel
          }
        };
      }
    }

    if (forceChannelByTab) {
      return buildZeroRow(base, 'tab_no_aggregate_rows');
    }

    const adMetric = findMetricNear(text, adKeywords);
    const gmvMetric = findMetricNear(text, gmvKeywords);
    const orderCount = findOrderNear(text);
    const resolvedOrderCount = orderCount !== null ? orderCount : (Number.isFinite(Number(skuOrdersFallback)) ? Math.max(0, Math.floor(Number(skuOrdersFallback))) : null);

    const adCurrency = adMetric.currency || fallbackCurrency('ad');
    const gmvCurrency = pageType === 'ad'
      ? adCurrency
      : (gmvMetric.currency || fallbackCurrency('shop'));
    const adAmount = adMetric.value;
    const gmvAmount = gmvMetric.value;
    const computedRoi = adAmount && gmvAmount && adAmount > 0
      ? roundTo(gmvAmount / adAmount, 6)
      : null;

    return {
      row: {
        source_page: String(pageUrl || ''),
        page_type: pageType,
        captured_at: new Date().toISOString(),
        entry_date: entryDate,
        store_ref: storeRef || '',
        account_ref: accountRef || '',
        channel_type: resolvedChannel,
        ad_spend_amount: adAmount,
        ad_spend_currency: adCurrency,
        gmv_amount: gmvAmount,
        gmv_currency: gmvCurrency,
        order_count: resolvedOrderCount,
        roi_value: computedRoi,
        raw_metrics_json: {
          capture_mode: 'text_aggregate',
          total_roi: computedRoi
        }
      },
      rows: [],
      debug: {
        page_type: pageType,
        aggregate_mode: 'text',
        selected_tab_channel: selectedTabChannel || '',
        resolved_channel: resolvedChannel,
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
