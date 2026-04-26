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

  function normalizeMetricKey(text) {
    const raw = String(text || '').trim().toLowerCase();
    const compact = raw.replace(/[\s._\-:/]+/g, '');
    if (!raw) return '';
    if (raw.includes('creative')) return 'creative';
    if (raw.includes('tiktok account') || compact.includes('tiktokaccount') || compact.includes('tiktoaccount')) return 'tiktok_account';
    if (raw.includes('authorization type')) return 'authorization_type';
    if (raw.includes('status')) return 'status';
    if (raw.includes('time posted')) return 'time_posted';
    if (raw === 'cost' || raw.includes('ad spend') || raw.includes('spend')) return 'cost';
    if (raw.includes('sku orders')) return 'sku_orders';
    if (raw.includes('cost per order')) return 'cost_per_order';
    if (raw.includes('gross revenue') || raw.includes('gmv')) return 'gross_revenue';
    if (raw === 'roi' || raw.includes('roas')) return 'roi';
    if (raw.includes('product ad impressions')) return 'product_ad_impressions';
    if (raw.includes('product ad clicks')) return 'product_ad_clicks';
    if (raw.includes('product ad click rate')) return 'product_ad_click_rate';
    if (raw.includes('ad conversion rate')) return 'ad_conversion_rate';
    if (raw.includes('2-second ad video view rate')) return 'view_rate_2s';
    if (raw.includes('6-second ad video view rate')) return 'view_rate_6s';
    if (raw.includes('25% ad video view rate')) return 'view_rate_25';
    if (raw.includes('50% ad video view rate')) return 'view_rate_50';
    if (raw.includes('75% ad video view rate')) return 'view_rate_75';
    if (raw.includes('100% ad video view rate')) return 'view_rate_100';
    if (raw.includes('creative boost') || compact.includes('creativeboost')) return 'creative_boost';
    return '';
  }

  function textContentSafe(node) {
    if (!node) return '';
    return String(node.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function parseMetricNumber(raw) {
    const text = String(raw || '').trim();
    if (!text || /^(n\/a|--|-)$/i.test(text)) return null;
    const parsed = parseNumber(text);
    if (parsed === null || !Number.isFinite(parsed)) return null;
    return Number(parsed);
  }

  function parseBoostAvailability(raw) {
    const text = String(raw || '').trim().toLowerCase();
    if (!text) return null;
    if (/(n\/a|not\s*available|unavailable|disabled|exclude|excluded|none|不可|无|停用)/i.test(text)) return false;
    if (/(boost|available|enable|enabled|active|可加热|可加推|可投放|可提升|可用)/i.test(text)) return true;
    return null;
  }

  function detectCampaignId(doc, pageUrl, pageText) {
    const text = String(pageText || '');
    const fromText = text.match(/Campaign ID\s*[:：]?\s*(\d{6,})/i);
    if (fromText && fromText[1]) return fromText[1];

    const fromDom = pickText(doc, [
      '[data-testid*="campaign"]',
      '[class*="campaign"]'
    ]);
    const fromDomMatch = String(fromDom || '').match(/(\d{6,})/);
    if (fromDomMatch && fromDomMatch[1]) return fromDomMatch[1];

    const urlMatch = String(pageUrl || '').match(/[?&](?:campaign_id|campaignId|id)=(\d{6,})/i);
    return urlMatch && urlMatch[1] ? urlMatch[1] : '';
  }

  function detectVideoIdFromText(text) {
    const raw = String(text || '');
    let m = raw.match(/Video[^0-9]{0,10}([0-9][0-9\s]{7,30})/i);
    if (m && m[1]) {
      const compact = String(m[1]).replace(/\s+/g, '');
      if (/^[0-9]{8,25}$/.test(compact)) return compact;
    }
    m = raw.match(/Video\s*[:：]?\s*([0-9]{8,25})/i);
    if (m && m[1]) return String(m[1]);
    m = raw.match(/(?:^|\D)(\d{16,22})(?:\D|$)/);
    if (m && m[1]) return m[1];
    m = raw.match(/(?:^|\D)(\d{8,15})(?:\D|$)/);
    if (m && m[1]) return m[1];
    return '';
  }

  function detectVideoIdFromCell(cellNode, cellText) {
    const direct = detectVideoIdFromText(cellText);
    if (direct) return direct;
    if (!cellNode || typeof cellNode.querySelectorAll !== 'function') return '';

    const attrs = [
      'title',
      'aria-label',
      'data-title',
      'data-tooltip',
      'data-tooltip-content',
      'data-original-title',
      'data-tip',
      'data-content',
      'data-balloon',
      'data-popup',
      'content'
    ];
    const probes = [];
    const pushProbe = (value) => {
      const txt = String(value || '').trim();
      if (txt) probes.push(txt);
    };

    pushProbe(cellNode.textContent);
    attrs.forEach((key) => pushProbe(cellNode.getAttribute && cellNode.getAttribute(key)));

    const nodes = Array.from(cellNode.querySelectorAll('*'));
    nodes.forEach((node) => {
      attrs.forEach((key) => {
        if (typeof node.getAttribute === 'function') pushProbe(node.getAttribute(key));
      });
    });

    for (let i = 0; i < probes.length; i += 1) {
      const hit = detectVideoIdFromText(probes[i]);
      if (hit) return hit;
    }
    return '';
  }

  function detectCreativeTitleFromCell(cellText) {
    const text = String(cellText || '').replace(/\s+/g, ' ').trim();
    if (!text) return '';
    const rows = text.split(/Video\s*[:：]/i);
    const title = String(rows[0] || '').trim();
    if (title) return title;
    return text.slice(0, 120);
  }

  function normalizeCreativeLabel(raw) {
    const label = String(raw || '').trim().toLowerCase();
    if (label === 'excellent') return 'excellent';
    if (label === 'scale') return 'excellent';
    if (label === 'observe') return 'observe';
    if (label === 'optimize') return 'optimize';
    if (label === 'potential') return 'observe';
    if (label === 'garbage') return 'garbage';
    if (label === 'bad') return 'garbage';
    if (label === 'ignore') return 'ignore';
    if (label === 'exclude_candidate') return 'garbage';
    if (label === 'keep') return 'observe';
    return 'observe';
  }

  function labelReadable(label) {
    const key = normalizeCreativeLabel(label);
    if (key === 'excellent') return '优秀款';
    if (key === 'optimize') return '优化素材';
    if (key === 'garbage') return '垃圾素材';
    if (key === 'ignore') return '忽略';
    return '观察中';
  }

  function scoreBand(score) {
    const v = Number(score);
    if (!Number.isFinite(v)) return 'insufficient_data';
    if (v >= 70) return 'high';
    if (v >= 40) return 'mid';
    return 'low';
  }

  function safeMetric(metrics, key) {
    if (!metrics || typeof metrics !== 'object') return null;
    const raw = metrics[key];
    if (raw == null) return null;
    const n = Number(raw);
    return Number.isFinite(n) ? n : null;
  }

  function scoreHigher(value, low, high) {
    if (value == null) return null;
    if (value <= low) return 0;
    if (value >= high) return 100;
    return ((value - low) * 100) / (high - low);
  }

  function scoreLower(value, high, low) {
    if (value == null) return null;
    if (value <= low) return 100;
    if (value >= high) return 0;
    return ((high - value) * 100) / (high - low);
  }

  function weightedScore(parts) {
    let totalWeight = 0;
    let weighted = 0;
    let dimensions = 0;
    (parts || []).forEach((part) => {
      if (!part || part.score == null) return;
      const weight = Number(part.weight || 0);
      if (weight <= 0) return;
      totalWeight += weight;
      weighted += Number(part.score) * weight;
      dimensions += 1;
    });
    if (totalWeight <= 0) {
      return { score: null, dimensions: 0 };
    }
    return {
      score: roundTo(weighted / totalWeight, 4),
      dimensions
    };
  }

  function toBandNumeric(band) {
    if (band === 'high') return 3;
    if (band === 'mid') return 2;
    if (band === 'low') return 1;
    return 0;
  }

  function resolveProblemPosition(hookBand, retentionBand, conversionBand, hookScore, retentionScore, conversionScore) {
    const scored = [
      { key: 'front_3s', band: hookBand, score: hookScore },
      { key: 'middle', band: retentionBand, score: retentionScore },
      { key: 'conversion_tail', band: conversionBand, score: conversionScore }
    ];

    const lowStages = scored.filter((item) => item.band === 'low');
    if (lowStages.length >= 2) return 'multi_stage';
    if (lowStages.length === 1) return lowStages[0].key;

    const midStages = scored.filter((item) => item.band === 'mid');
    if (midStages.length >= 2) return 'multi_stage';
    if (midStages.length === 1) return midStages[0].key;

    let weakest = scored[0];
    scored.slice(1).forEach((item) => {
      const current = Number(item.score);
      const best = Number(weakest.score);
      if (Number.isFinite(current) && Number.isFinite(best) && current < best) {
        weakest = item;
      }
    });
    return weakest.key;
  }

  function actionSetByPosition(position) {
    if (position === 'front_3s') {
      return [
        '前3秒首帧改为佩戴近景+价格锚点，0.5秒内出现核心卖点，目标提升CTR与2秒率。',
        '开场文案改成强问题句+利益点（如“黑皮也显白？”），并保留产品特写，目标提升点击率。',
        '首屏节奏压缩到3秒内完成“痛点-结果”对比，减少空镜，目标提升初始留存。'
      ];
    }
    if (position === 'middle') {
      return [
        '中段增加“佩戴前后对比”与多角度转场，每3-4秒一个信息点，目标提升6秒率与25%/50%播放率。',
        '加入场景化证据（通勤/约会/直播实拍）并减少重复镜头，目标提升75%播放率。',
        '把卖点拆成“材质-舒适度-搭配”三段结构，强化连贯叙事，目标提升中段留存。'
      ];
    }
    if (position === 'conversion_tail') {
      return [
        '尾段补充信任背书（买家评价/实拍细节）+明确优惠时效，目标提升转化率与ROI。',
        '加入价格对比锚点（原价-活动价-到手价）并口播CTA，目标降低Cost per order。',
        '结尾固定“下单路径+售后承诺”双CTA，缩短决策链路，目标提升SKU orders。'
      ];
    }
    return [
      '重拍前3秒钩子（强利益点+特写），先解决点击不足问题。',
      '中段补齐场景证据与对比展示，降低观众流失。',
      '尾段增加信任背书与促单结构，提升转化闭环。'
    ];
  }

  function materialTypeText(materialType) {
    if (materialType === 'scale') return '放量素材（可加预算）';
    if (materialType === 'bad') return '差素材（直接淘汰）';
    if (materialType === 'optimize') return '优化素材（改稿再投）';
    return '观察素材（继续测试）';
  }

  function conclusionByProfile(hookBand, retentionBand, conversionBand, materialType) {
    if (materialType === 'scale') return '三段能力闭环，可作为放量主力素材。';
    if (hookBand === 'high' && (conversionBand === 'low' || conversionBand === 'mid')) return '钩子强但转化偏弱，需强化尾段成交结构。';
    if (conversionBand === 'high' && hookBand === 'low') return '转化能力不差，但前3秒吸引不足。';
    if (retentionBand === 'low') return '中段留存不足，导致有效流量无法进入转化段。';
    if (materialType === 'bad') return '整体效率偏弱且消耗已达门槛，建议淘汰。';
    return '具备优化空间，建议按弱项段位定向改稿。';
  }

  function buildCreativeDiagnosis(row) {
    const metrics = row && row.metrics ? row.metrics : {};
    const title = String(row && row.title || '').trim().toLowerCase();
    const ignore = !!(row && row.ignore) || /product\s*card/i.test(title);
    if (ignore) {
      return {
        hook_score: 'insufficient_data',
        retention_score: 'insufficient_data',
        conversion_score: 'insufficient_data',
        hook_score_value: null,
        retention_score_value: null,
        conversion_score_value: null,
        material_type: 'ignore',
        material_type_text: '忽略',
        problem_position: 'multi_stage',
        continue_delivery: 'no',
        core_conclusion: '素材不具备评估条件，建议忽略。',
        actions: ['该行缺少可评估数据，建议先补齐指标后再判定。'],
        confidence: 0.2
      };
    }

    const ctr = safeMetric(metrics, 'product_ad_click_rate');
    const view2 = safeMetric(metrics, 'view_rate_2s');
    const view6 = safeMetric(metrics, 'view_rate_6s');
    const view25 = safeMetric(metrics, 'view_rate_25');
    const view50 = safeMetric(metrics, 'view_rate_50');
    const view75 = safeMetric(metrics, 'view_rate_75');
    const cvr = safeMetric(metrics, 'ad_conversion_rate');
    const roi = safeMetric(metrics, 'roi');
    const cpo = safeMetric(metrics, 'cost_per_order');
    const skuOrders = safeMetric(metrics, 'sku_orders');
    const cost = safeMetric(metrics, 'cost');
    const impressions = safeMetric(metrics, 'product_ad_impressions');
    const clicks = safeMetric(metrics, 'product_ad_clicks');

    const hookCalc = weightedScore([
      { score: scoreHigher(ctr, 0.6, 1.5), weight: 0.55 },
      { score: scoreHigher(view2, 28, 45), weight: 0.45 }
    ]);
    const retentionCalc = weightedScore([
      { score: scoreHigher(view6, 10, 18), weight: 0.35 },
      { score: scoreHigher(view25, 6, 11), weight: 0.25 },
      { score: scoreHigher(view50, 3.5, 7), weight: 0.25 },
      { score: scoreHigher(view75, 2.2, 4.8), weight: 0.15 }
    ]);
    const conversionCalc = weightedScore([
      { score: scoreHigher(cvr, 1.0, 2.5), weight: 0.32 },
      { score: scoreHigher(roi, 1.0, 2.2), weight: 0.38 },
      { score: scoreLower(cpo, 2.2, 0.9), weight: 0.15 },
      { score: scoreHigher(skuOrders, 1, 4), weight: 0.15 }
    ]);

    const hookScoreValue = hookCalc.score;
    const retentionScoreValue = retentionCalc.score;
    const conversionScoreValue = conversionCalc.score;
    const hookBand = scoreBand(hookScoreValue);
    const retentionBand = scoreBand(retentionScoreValue);
    const conversionBand = scoreBand(conversionScoreValue);

    const learningReached = (cost != null && cost >= 1.2)
      || (impressions != null && impressions >= 800)
      || (clicks != null && clicks >= 20);
    const evidenceCount = [roi, cvr, skuOrders].filter((v) => v != null).length;
    const lowEvidence = evidenceCount < 2;
    const earlyStage = !learningReached || (cost != null && cost < 1.2);

    const hardBad = learningReached
      && conversionBand === 'low'
      && (
        (roi != null && roi < 0.9)
        || (skuOrders != null && skuOrders <= 0)
        || (cvr != null && cvr < 0.9)
      );

    const strongScale = (roi != null && roi >= 2.2)
      && (skuOrders != null && skuOrders >= 3)
      && (cvr != null && cvr >= 2.0);
    const isScale = (learningReached || strongScale) && conversionBand === 'high'
      && toBandNumeric(hookBand) >= 2
      && toBandNumeric(retentionBand) >= 2;

    let materialType = 'observe';
    if (hardBad) materialType = 'bad';
    else if (isScale) materialType = 'scale';
    else if (lowEvidence || earlyStage) materialType = 'observe';
    else {
      const lowStages = [hookBand, retentionBand, conversionBand].filter((v) => v === 'low').length;
      materialType = lowStages >= 1 ? 'optimize' : 'observe';
    }

    const position = resolveProblemPosition(
      hookBand,
      retentionBand,
      conversionBand,
      hookScoreValue,
      retentionScoreValue,
      conversionScoreValue
    );
    const actions = actionSetByPosition(position);
    const continueDelivery = materialType === 'bad' ? 'no' : 'yes';
    const confidenceParts = [hookCalc.dimensions, retentionCalc.dimensions, conversionCalc.dimensions];
    const filledDimensions = confidenceParts.reduce((sum, v) => sum + Number(v || 0), 0);
    const confidence = roundTo(Math.min(1, Math.max(0.3, filledDimensions / 10)), 2);

    return {
      hook_score: hookBand,
      retention_score: retentionBand,
      conversion_score: conversionBand,
      hook_score_value: hookScoreValue == null ? null : roundTo(hookScoreValue, 2),
      retention_score_value: retentionScoreValue == null ? null : roundTo(retentionScoreValue, 2),
      conversion_score_value: conversionScoreValue == null ? null : roundTo(conversionScoreValue, 2),
      material_type: materialType,
      material_type_text: materialTypeText(materialType),
      problem_position: position,
      continue_delivery: continueDelivery,
      core_conclusion: conclusionByProfile(hookBand, retentionBand, conversionBand, materialType),
      actions,
      confidence
    };
  }

  function percentileRank(value, list) {
    if (!Number.isFinite(Number(value)) || !Array.isArray(list) || list.length === 0) return null;
    const sorted = list.slice().map((v) => Number(v)).filter((v) => Number.isFinite(v)).sort((a, b) => a - b);
    if (sorted.length === 0) return null;
    if (sorted.length === 1) return 1;
    let count = 0;
    for (let i = 0; i < sorted.length; i += 1) {
      if (Number(value) >= sorted[i]) count += 1;
    }
    return (count - 1) / (sorted.length - 1);
  }

  function metricHash(metrics) {
    const keys = [
      'cost',
      'sku_orders',
      'cost_per_order',
      'gross_revenue',
      'roi',
      'product_ad_clicks',
      'product_ad_click_rate',
      'ad_conversion_rate',
      'view_rate_75'
    ];
    return keys.map((k) => {
      const v = metrics && metrics[k];
      return `${k}:${v == null ? 'null' : Number(v)}`;
    }).join('|');
  }

  function classifyCreativeRows(rows) {
    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const diagnosis = buildCreativeDiagnosis(row);
      row.diagnosis = diagnosis;
      row.hook_score = diagnosis.hook_score;
      row.retention_score = diagnosis.retention_score;
      row.conversion_score = diagnosis.conversion_score;
      row.material_type = diagnosis.material_type;
      row.problem_position = diagnosis.problem_position;
      row.continue_delivery = diagnosis.continue_delivery;
      row.core_conclusion = diagnosis.core_conclusion;
      row.actions = Array.isArray(diagnosis.actions) ? diagnosis.actions.slice(0, 3) : [];
      row.confidence = diagnosis.confidence;

      let autoLabel = 'observe';
      if (diagnosis.material_type === 'scale') autoLabel = 'excellent';
      else if (diagnosis.material_type === 'bad') autoLabel = 'garbage';
      else if (diagnosis.material_type === 'optimize') autoLabel = 'optimize';
      else if (diagnosis.material_type === 'ignore') autoLabel = 'ignore';

      row.auto_label = autoLabel;
      row.auto_label_text = labelReadable(autoLabel);
      row.auto_reasons = [
        `hook:${diagnosis.hook_score}`,
        `retention:${diagnosis.retention_score}`,
        `conversion:${diagnosis.conversion_score}`,
        `problem:${diagnosis.problem_position}`
      ];
    });
    return rows || [];
  }

  function captureCreativeRows(doc, pageUrl) {
    if (!doc || typeof doc.querySelectorAll !== 'function') {
      return { rows: [], context: {} };
    }

    const pageText = sanitizeText(doc);
    const dateRange = detectTopDateFromDom(doc, pageText) || '';
    const host = (() => {
      try { return new URL(String(pageUrl || '')).hostname.toLowerCase(); } catch (_) { return ''; }
    })();
    const campaignId = detectCampaignId(doc, pageUrl, pageText);

    const tableRoot = doc.querySelector('[data-testid^="creative-table-index"]')
      || doc.querySelector('.core-table')
      || doc.querySelector('[class*="creative-table"]');
    if (!tableRoot) {
      return {
        rows: [],
        context: {
          host,
          campaign_id: campaignId,
          date_range: dateRange
        }
      };
    }

    const headerNodes = Array.from(tableRoot.querySelectorAll('.core-table-th'));
    const headers = headerNodes.map((node) => {
      const title = textContentSafe(node.querySelector('.core-table-th-item-title') || node);
      return normalizeMetricKey(title);
    });

    const rowNodes = Array.from(tableRoot.querySelectorAll('.core-table-tr')).filter((node) => {
      if (node.classList.contains('core-table-tr') && node.querySelector('.core-table-th')) return false;
      return node.querySelectorAll('.core-table-td').length > 0;
    });

    const rows = [];
    rowNodes.forEach((node, index) => {
      const cellNodes = Array.from(node.querySelectorAll('.core-table-td'));
      const metrics = {};
      let creativeText = '';
      let creativeCellNode = null;
      let tiktokAccount = '';
      let status = '';
      let canBoost = null;

      cellNodes.forEach((cell, colIndex) => {
        const key = headers[colIndex] || '';
        const cellText = textContentSafe(cell);
        if (!key) return;

        if (key === 'creative') {
          creativeText = cellText;
          creativeCellNode = cell;
        } else if (key === 'tiktok_account') {
          tiktokAccount = cellText;
        } else if (key === 'status') {
          status = cellText;
        } else if (key === 'creative_boost') {
          const parsedBoost = parseBoostAvailability(cellText);
          if (parsedBoost !== null) canBoost = parsedBoost;
          else if (/boost/i.test(cellText)) canBoost = true;
        } else {
          metrics[key] = parseMetricNumber(cellText);
        }
      });

      const hasBoostButton = !!node.querySelector('button[data-uid^="creativeboostentrance:button"], button[data-tid="m4b_button"], button[class*="boost-action-button"]');
      if (hasBoostButton) canBoost = true;

      const videoId = detectVideoIdFromCell(creativeCellNode || cellNodes[0], creativeText);
      const title = detectCreativeTitleFromCell(creativeText);
      if (!videoId && !title) return;

      const allMetricValues = Object.keys(metrics).map((k) => metrics[k]).filter((v) => v != null);
      const ignore = /product\s*card/i.test(title) || (!videoId && allMetricValues.length === 0);

      rows.push({
        row_index: index + 1,
        row_key: `row_${index + 1}`,
        video_id: String(videoId || ''),
        title: String(title || ''),
        tiktok_account: String(tiktokAccount || ''),
        status: String(status || ''),
        can_boost: canBoost === true,
        ignore: !!ignore,
        metrics,
        metrics_hash: metricHash(metrics),
        auto_label: 'observe',
        auto_label_text: labelReadable('observe'),
        auto_reasons: []
      });
    });

    classifyCreativeRows(rows);

    return {
      rows,
      context: {
        host,
        campaign_id: campaignId,
        date_range: dateRange
      }
    };
  }

  const api = {
    normalizeDate,
    parseNumber,
    detectCurrency,
    captureFromDocument,
    captureCreativeRows,
    classifyCreativeRows,
    buildCreativeDiagnosis,
    normalizeCreativeLabel,
    labelReadable
  };

  global.ProfitPluginParser = api;
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
})(typeof window !== 'undefined' ? window : globalThis);
