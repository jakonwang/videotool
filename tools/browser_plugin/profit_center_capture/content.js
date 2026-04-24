(function () {
  'use strict';

  if (window.__profitPluginContentReady) return;
  window.__profitPluginContentReady = true;

  const STYLE_ID = 'pcp-creative-opt-style';
  const CREATIVE_STORE_KEY = 'profit_plugin_creative_opt_v1';

  const state = {
    context: null,
    rows: [],
    rowMapByVideo: {},
    observer: null,
    refreshTimer: null,
    lastFingerprint: '',
    lastScheduleAt: 0,
    refreshQueued: false,
    lastUrl: String(window.location.href || '')
  };

  function safeErrorMessage(err) {
    if (!err) return 'capture_failed';
    if (typeof err === 'string') return err;
    if (err && err.message) return String(err.message);
    return 'capture_failed';
  }

  function normalizeLabel(raw) {
    const label = String(raw || '').trim().toLowerCase();
    if (label === 'excellent' || label === 'scale') return 'excellent';
    if (label === 'optimize') return 'optimize';
    if (label === 'observe' || label === 'potential' || label === 'keep') return 'observe';
    if (label === 'garbage' || label === 'bad' || label === 'exclude_candidate') return 'garbage';
    if (label === 'ignore') return 'ignore';
    return 'observe';
  }

  function labelText(label) {
    const key = normalizeLabel(label);
    if (key === 'excellent') return '放量素材';
    if (key === 'optimize') return '优化素材';
    if (key === 'garbage') return '垃圾素材';
    if (key === 'ignore') return '忽略';
    return '观察中';
  }

  function storageGet(key, done) {
    chrome.storage.local.get([key], (res) => done(res || {}));
  }

  function loadCreativeStore(done) {
    storageGet(CREATIVE_STORE_KEY, (res) => {
      const raw = res[CREATIVE_STORE_KEY];
      done(raw && typeof raw === 'object' ? Object.assign({}, raw) : {});
    });
  }

  function contextKey(context) {
    const host = String(context && context.host || window.location.hostname || '').trim().toLowerCase();
    const campaignId = String(context && context.campaign_id || '').trim() || 'unknown_campaign';
    const dateRange = String(context && context.date_range || '').trim() || 'unknown_date';
    return `${host}|${campaignId}|${dateRange}`;
  }

  function decisionKey(context, videoId) {
    return `${contextKey(context)}|${String(videoId || '').trim()}`;
  }

  function effectiveLabel(row) {
    if (row && row.manual_label) return normalizeLabel(row.manual_label);
    return normalizeLabel(row && row.auto_label);
  }

  function ensureStyle() {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      .pcp-boost-tag-wrap{
        display:inline-flex;
        align-items:center;
        margin-right:8px;
        vertical-align:middle;
        max-width:120px;
      }
      .pcp-el-tag{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        height:22px;
        padding:0 10px;
        font-size:12px;
        font-weight:600;
        line-height:20px;
        border-radius:4px;
        border:1px solid transparent;
        white-space:nowrap;
      }
      .pcp-el-tag.excellent{color:#389e0d;background:#f6ffed;border-color:#b7eb8f;}
      .pcp-el-tag.optimize{color:#d46b08;background:#fff7e6;border-color:#ffd591;}
      .pcp-el-tag.observe{color:#1d39c4;background:#f0f5ff;border-color:#adc6ff;}
      .pcp-el-tag.garbage{color:#cf1322;background:#fff1f0;border-color:#ffa39e;}
      .pcp-el-tag.ignore{color:#8c8c8c;background:#fafafa;border-color:#d9d9d9;}
      .pcp-el-tag.manual::after{
        content:'手动';
        margin-left:6px;
        padding-left:6px;
        border-left:1px solid rgba(0,0,0,.15);
        font-size:10px;
      }
    `;
    document.head.appendChild(style);
  }

  function getBoostButtons() {
    const selectors = [
      'button[data-uid^="creativeboostentrance:button"]',
      'button[data-tid="m4b_button"].boost-action-button-pqyM',
      'button[class*="boost-action-button"]'
    ];
    const result = [];
    const seen = new Set();
    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((node) => {
        if (!node || seen.has(node)) return;
        seen.add(node);
        result.push(node);
      });
    });
    return result;
  }

  function clearInjectedTags() {
    document.querySelectorAll('.pcp-boost-tag-wrap').forEach((node) => node.remove());
  }

  function detectCampaignIdFromPage() {
    const text = String(document.body && document.body.innerText || '');
    const m = text.match(/Campaign ID\s*[:：]?\s*(\d{6,})/i);
    if (m && m[1]) return m[1];
    const m2 = window.location.href.match(/[?&](?:campaign_id|campaignId|id)=(\d{6,})/i);
    return m2 && m2[1] ? m2[1] : '';
  }

  function detectVideoIdFromText(text) {
    const raw = String(text || '');
    let m = raw.match(/Video\s*[:：]?\s*([0-9]{6,})/i);
    if (m && m[1]) return String(m[1]);
    m = raw.match(/([0-9]{8,})/);
    return m && m[1] ? String(m[1]) : '';
  }

  function hashText(input) {
    const text = String(input || '');
    let hash = 2166136261;
    for (let i = 0; i < text.length; i += 1) {
      hash ^= text.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }
    return (hash >>> 0).toString(16);
  }

  function fallbackVideoId(seed, idx) {
    return `pseudo_${hashText(`${seed || 'creative'}|${idx + 1}|${window.location.pathname || ''}`)}`;
  }

  function parseNumberLike(text) {
    const raw = String(text || '').replace(/,/g, '').trim();
    if (!raw || /^(n\/a|--|-)$/i.test(raw)) return null;
    const m = raw.match(/([-+]?\d+(?:\.\d+)?)/);
    if (!m || !m[1]) return null;
    const n = Number(m[1]);
    return Number.isFinite(n) ? n : null;
  }

  function scoreHigher(value, low, high) {
    if (value == null) return null;
    if (value <= low) return 0;
    if (value >= high) return 1;
    return (value - low) / (high - low);
  }

  function weightedScore(parts) {
    let num = 0;
    let den = 0;
    parts.forEach((part) => {
      if (!part || part.score == null) return;
      const w = Number(part.weight || 0);
      if (!Number.isFinite(w) || w <= 0) return;
      num += part.score * w;
      den += w;
    });
    if (den <= 0) return null;
    return num / den;
  }

  function classifyFallback(metrics, title) {
    const safeTitle = String(title || '').toLowerCase();
    if (safeTitle.includes('product card')) return 'ignore';

    const cost = metrics.cost;
    const sku = metrics.sku_orders;
    const roi = metrics.roi;
    const cvr = metrics.ad_conversion_rate;
    const ctr = metrics.product_ad_click_rate;
    const view75 = metrics.view_rate_75;
    const cpo = metrics.cost_per_order != null
      ? metrics.cost_per_order
      : (cost != null && sku != null && sku > 0 ? cost / sku : null);
    const learningReached = (cost != null && cost >= 1.2)
      || (metrics.product_ad_impressions != null && metrics.product_ad_impressions >= 800)
      || (metrics.product_ad_clicks != null && metrics.product_ad_clicks >= 20);
    const evidenceCount = [roi, cvr, sku].filter((v) => v != null).length;
    const lowEvidence = evidenceCount < 2;

    if (learningReached) {
      if (sku != null && cost != null && sku <= 0 && cost >= 0.6) return 'garbage';
      if (roi != null && roi < 0.9 && cost != null && cost >= 0.6) return 'garbage';
      if (cvr != null && cvr < 0.9 && cost != null && cost >= 0.6) return 'garbage';
      if (cpo != null && cpo > 1.8) return 'garbage';
    }

    if ((roi != null && roi >= 2.3) && (sku != null && sku >= 3) && (cvr != null && cvr >= 2.0)) {
      return 'excellent';
    }

    const score = weightedScore([
      { score: scoreHigher(roi, 1.0, 2.2), weight: 0.35 },
      { score: scoreHigher(sku, 0, 3), weight: 0.30 },
      { score: scoreHigher(cvr, 1.0, 2.2), weight: 0.18 },
      { score: scoreHigher(ctr, 0.6, 1.5), weight: 0.10 },
      { score: scoreHigher(view75, 2.0, 5.0), weight: 0.07 }
    ]);

    if (score == null || lowEvidence || !learningReached) {
      return 'observe';
    }
    if (score >= 0.72) return 'excellent';
    if (score <= 0.28) return learningReached ? 'garbage' : 'optimize';
    if (score <= 0.48) return 'optimize';
    return 'observe';
  }

  function normalizeContext(raw) {
    return {
      host: String(raw && raw.host || window.location.hostname || '').trim().toLowerCase(),
      campaign_id: String(raw && raw.campaign_id || detectCampaignIdFromPage() || '').trim(),
      date_range: String(raw && raw.date_range || '').trim()
    };
  }

  function detectHeaderIndexMap(rowNode) {
    const tableRoot = rowNode && (rowNode.closest('.core-table') || rowNode.closest('table'));
    const map = {};
    if (!tableRoot) return map;
    const headers = Array.from(tableRoot.querySelectorAll('.core-table-th, th'));
    headers.forEach((th, idx) => {
      const text = String(th.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (!text) return;
      if (text.includes('creative')) map.creative = idx;
      if (text.includes('cost per order')) map.cost_per_order = idx;
      else if (text === 'cost' || text.includes(' ad cost') || text.includes('spend')) map.cost = idx;
      if (text.includes('sku order')) map.sku_orders = idx;
      if (text === 'roi' || text.includes(' roi')) map.roi = idx;
      if (text.includes('ad conversion rate')) map.ad_conversion_rate = idx;
      if (text.includes('product ad click rate')) map.product_ad_click_rate = idx;
      if (text.includes('2-second ad video view rate')) map.view_rate_2s = idx;
      if (text.includes('6-second ad video view rate')) map.view_rate_6s = idx;
      if (text.includes('25% ad video view rate')) map.view_rate_25 = idx;
      if (text.includes('50% ad video view rate')) map.view_rate_50 = idx;
      if (text.includes('75% ad video view rate')) map.view_rate_75 = idx;
      if (text.includes('100% ad video view rate')) map.view_rate_100 = idx;
      if (text.includes('product ad clicks')) map.product_ad_clicks = idx;
      if (text.includes('product ad impressions')) map.product_ad_impressions = idx;
    });
    return map;
  }

  function collectCandidateRowNodes() {
    const rows = [];
    const seen = new Set();
    const add = (node) => {
      if (!node || seen.has(node)) return;
      const cells = node.querySelectorAll('.core-table-td, td');
      if (!cells || cells.length === 0) return;
      const text = String(node.textContent || '').replace(/\s+/g, ' ').trim();
      if (!text || /^creative\s+/i.test(text)) return;
      seen.add(node);
      rows.push(node);
    };

    getBoostButtons().forEach((btn) => add(btn.closest('.core-table-tr') || btn.closest('tr')));
    document.querySelectorAll('.core-table-tr, table tr').forEach((node) => add(node));
    return rows;
  }

  function fallbackBuildRows() {
    const rowNodes = collectCandidateRowNodes();
    const rows = [];
    rowNodes.forEach((rowNode, idx) => {
      const cells = Array.from(rowNode.querySelectorAll('.core-table-td, td'));
      if (!cells.length) return;
      const header = detectHeaderIndexMap(rowNode);
      const creativeCell = cells[header.creative != null ? header.creative : 0];
      const creativeText = String(creativeCell && creativeCell.textContent || '').replace(/\s+/g, ' ').trim();
      const rowText = String(rowNode.textContent || '').replace(/\s+/g, ' ').trim();
      const rawVideoId = detectVideoIdFromText(creativeText) || detectVideoIdFromText(rowText);
      const videoId = rawVideoId || fallbackVideoId(creativeText || rowText, idx);
      const metrics = {};
      Object.keys(header).forEach((key) => {
        if (key === 'creative') return;
        const cell = cells[header[key]];
        metrics[key] = parseNumberLike(cell && cell.textContent);
      });
      const hasBoost = !!rowNode.querySelector('button[data-uid^="creativeboostentrance:button"], button[data-tid="m4b_button"], button[class*="boost-action-button"]')
        || /\bBoost\b/i.test(rowText);
      const autoLabel = classifyFallback(metrics, creativeText);
      rows.push({
        row_index: idx + 1,
        row_key: `row_${idx + 1}_${hashText(videoId || creativeText || rowText).slice(0, 8)}`,
        video_id: videoId,
        source_video_id_type: rawVideoId ? 'actual' : 'pseudo',
        title: creativeText.slice(0, 120),
        can_boost: hasBoost,
        ignore: /product\s*card/i.test(creativeText),
        metrics,
        metrics_hash: `${videoId}|${Object.keys(metrics).map((k) => `${k}:${metrics[k]}`).join('|')}`,
        auto_label: autoLabel,
        manual_label: '',
        exclude_flag: autoLabel === 'garbage' ? 1 : 0
      });
    });
    return rows;
  }

  function parseCreativeRows() {
    const parser = window.ProfitPluginParser;
    if (parser && typeof parser.captureCreativeRows === 'function') {
      const captured = parser.captureCreativeRows(document, window.location.href) || {};
      const parsedRows = Array.isArray(captured.rows) ? captured.rows : [];
      if (parsedRows.length > 0) {
        return {
          context: normalizeContext(captured.context || {}),
          rows: parsedRows
        };
      }
      return {
        context: normalizeContext(captured.context || {}),
        rows: fallbackBuildRows()
      };
    }
    return {
      context: normalizeContext({}),
      rows: fallbackBuildRows()
    };
  }

  function fingerprint(rows, context) {
    const head = contextKey(context);
    const body = (rows || []).map((row) => {
      const label = normalizeLabel(row.auto_label);
      return [
        row.video_id || row.row_key || '',
        row.metrics_hash || '',
        label,
        row.material_type || '',
        row.problem_position || ''
      ].join('|');
    }).join('||');
    return `${head}||${body}`;
  }

  function normalizeRow(raw, idx) {
    const row = Object.assign({}, raw || {});
    row.row_index = Number(row.row_index || idx + 1);
    const seed = [
      row.video_id,
      row.title,
      row.tiktok_account,
      row.metrics_hash,
      row.row_index
    ].join('|');
    row.video_id = String(row.video_id || '').trim();
    if (!row.video_id) {
      row.video_id = fallbackVideoId(seed, idx);
      row.source_video_id_type = 'pseudo';
    } else if (!row.source_video_id_type) {
      row.source_video_id_type = 'actual';
    }
    row.row_key = String(row.row_key || `row_${row.row_index}_${hashText(seed || row.video_id).slice(0, 8)}`);
    row.auto_label = normalizeLabel(row.auto_label);
    row.manual_label = row.manual_label ? normalizeLabel(row.manual_label) : '';
    row.exclude_flag = row.exclude_flag ? 1 : 0;
    row.metrics_hash = String(row.metrics_hash || '');
    row.can_boost = row.can_boost !== false;
    return row;
  }

  function mergeLocalDecision(context, row, storeObj) {
    if (!row || !row.video_id) return row;
    const decision = storeObj[decisionKey(context, row.video_id)];
    if (!decision || typeof decision !== 'object') return row;
    const next = Object.assign({}, row);
    if (decision.auto_label) next.auto_label = normalizeLabel(decision.auto_label);
    if (decision.manual_label) next.manual_label = normalizeLabel(decision.manual_label);
    if (decision.exclude_flag != null) next.exclude_flag = decision.exclude_flag ? 1 : 0;
    return next;
  }

  function rebuild(done) {
    const parsed = parseCreativeRows();
    const context = parsed.context;
    const rows = (parsed.rows || []).map((row, idx) => normalizeRow(row, idx));
    const nextFingerprint = fingerprint(rows, context);

    if (nextFingerprint === state.lastFingerprint && state.rows.length > 0) {
      done(null, { context: state.context, rows: state.rows.slice() });
      return;
    }

    loadCreativeStore((storeObj) => {
      const merged = rows.map((row) => mergeLocalDecision(context, row, storeObj));
      const rowMapByVideo = {};
      merged.forEach((row) => {
        if (row.video_id && !rowMapByVideo[row.video_id]) {
          rowMapByVideo[row.video_id] = row;
        }
      });
      state.context = context;
      state.rows = merged;
      state.rowMapByVideo = rowMapByVideo;
      state.lastFingerprint = nextFingerprint;
      done(null, { context, rows: merged });
    });
  }

  function findRowForButton(button, boostRows, fallbackIndex) {
    const rowNode = button.closest('.core-table-tr') || button.closest('tr');
    if (rowNode) {
      const header = detectHeaderIndexMap(rowNode);
      const cells = Array.from(rowNode.querySelectorAll('.core-table-td, td'));
      const creativeCell = cells[header.creative != null ? header.creative : 0];
      const videoId = detectVideoIdFromText(creativeCell ? creativeCell.textContent : '');
      if (videoId && state.rowMapByVideo[videoId]) {
        return state.rowMapByVideo[videoId];
      }
    }
    const indexed = boostRows[fallbackIndex] || null;
    if (indexed) return indexed;

    const fallbackNode = button.closest('.core-table-tr') || button.closest('tr');
    if (!fallbackNode) return null;
    const header = detectHeaderIndexMap(fallbackNode);
    const cells = Array.from(fallbackNode.querySelectorAll('.core-table-td, td'));
    const creativeCell = cells[header.creative != null ? header.creative : 0];
    const creativeText = String(creativeCell && creativeCell.textContent || '').replace(/\s+/g, ' ').trim();
    const videoId = detectVideoIdFromText(creativeText);
    const metrics = {};
    Object.keys(header).forEach((key) => {
      if (key === 'creative') return;
      const cell = cells[header[key]];
      metrics[key] = parseNumberLike(cell && cell.textContent);
    });
    const autoLabel = classifyFallback(metrics, creativeText);
    return {
      row_key: `fallback_${fallbackIndex + 1}`,
      video_id: videoId,
      title: creativeText.slice(0, 120),
      auto_label: autoLabel,
      manual_label: '',
      exclude_flag: autoLabel === 'garbage' ? 1 : 0,
      can_boost: true,
      metrics
    };
  }

  function render() {
    const buttons = getBoostButtons();
    if (!buttons.length) {
      clearInjectedTags();
      return;
    }

    const boostRows = state.rows.filter((row) => row && row.can_boost !== false);
    let cursor = 0;
    const activeWraps = new Set();
    buttons.forEach((button) => {
      const row = findRowForButton(button, boostRows, cursor);
      cursor += 1;
      if (!row) return;

      const finalLabel = effectiveLabel(row);
      const parent = button.parentElement || button;
      if (!parent) return;

      let wrap = button.previousElementSibling;
      if (!wrap || !wrap.classList || !wrap.classList.contains('pcp-boost-tag-wrap')) {
        wrap = document.createElement('span');
        wrap.className = 'pcp-boost-tag-wrap';
        const tag = document.createElement('span');
        tag.className = 'pcp-el-tag';
        wrap.appendChild(tag);
        parent.insertBefore(wrap, button);
      }

      const tag = wrap.firstElementChild || wrap.appendChild(document.createElement('span'));
      tag.className = `pcp-el-tag ${finalLabel}${row.manual_label ? ' manual' : ''}`;
      tag.textContent = labelText(finalLabel);
      activeWraps.add(wrap);
    });

    document.querySelectorAll('.pcp-boost-tag-wrap').forEach((node) => {
      if (!activeWraps.has(node)) node.remove();
    });
  }

  function runRefresh() {
    state.refreshQueued = false;
    rebuild(() => render());
  }

  function scheduleRefresh(delay) {
    if (document.hidden) return;
    const now = Date.now();
    if (state.refreshQueued && (now - state.lastScheduleAt) < 220) return;
    state.lastScheduleAt = now;
    state.refreshQueued = true;

    if (state.refreshTimer) clearTimeout(state.refreshTimer);
    state.refreshTimer = window.setTimeout(() => {
      state.refreshTimer = null;
      const runner = () => runRefresh();
      if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(runner, { timeout: 260 });
      } else {
        window.requestAnimationFrame(runner);
      }
    }, Number(delay || 320));
  }

  function shouldRefreshByMutations(mutations) {
    if (!Array.isArray(mutations) || mutations.length === 0) return false;
    for (let i = 0; i < mutations.length; i += 1) {
      const m = mutations[i];
      if (!m) continue;
      if (m.type === 'attributes') {
        const t = m.target;
        if (t && t.classList && (t.classList.contains('core-table-tr') || t.classList.contains('core-table-td'))) {
          return true;
        }
      }
      const nodes = []
        .concat(Array.from(m.addedNodes || []))
        .concat(Array.from(m.removedNodes || []));
      for (let j = 0; j < nodes.length; j += 1) {
        const node = nodes[j];
        if (!node || node.nodeType !== 1) continue;
        const el = node;
        if (
          (el.matches && (el.matches('.core-table-tr, .core-table-td, .core-table, button[data-uid^="creativeboostentrance:button"]')))
          || (el.querySelector && el.querySelector('.core-table-tr, .core-table-td, button[data-uid^="creativeboostentrance:button"]'))
        ) {
          return true;
        }
      }
    }
    return false;
  }

  function onUrlMaybeChanged() {
    const current = String(window.location.href || '');
    if (current === state.lastUrl) return;
    state.lastUrl = current;
    state.lastFingerprint = '';
    scheduleRefresh(120);
  }

  function ensureObserver() {
    if (state.observer) return;
    const root = document.querySelector('.core-table') || document.body;
    state.observer = new MutationObserver((mutations) => {
      if (shouldRefreshByMutations(mutations)) {
        scheduleRefresh(260);
      }
    });
    state.observer.observe(root, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class']
    });
    window.addEventListener('popstate', onUrlMaybeChanged);
    window.addEventListener('hashchange', onUrlMaybeChanged);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) scheduleRefresh(140);
    });
  }

  function exportExcludeIds(payload, done) {
    const inputRows = Array.isArray(payload && payload.rows) ? payload.rows : [];
    const sourceRows = inputRows.length > 0 ? inputRows : state.rows;
    const ids = sourceRows
      .filter((row) => row && row.video_id)
      .filter((row) => {
        const label = normalizeLabel(row.manual_label || row.auto_label);
        return row.exclude_flag || label === 'garbage';
      })
      .map((row) => String(row.video_id));
    done(null, Array.from(new Set(ids)));
  }

  function captureNow() {
    if (!window.ProfitPluginParser || typeof window.ProfitPluginParser.captureFromDocument !== 'function') {
      throw new Error('parser_unavailable');
    }
    const captured = window.ProfitPluginParser.captureFromDocument(document, window.location.href);
    const rows = Array.isArray(captured && captured.rows) ? captured.rows.filter((item) => item && typeof item === 'object') : [];
    const primaryRow = captured && captured.row ? captured.row : (rows.length > 0 ? rows[0] : null);
    if (!captured || !primaryRow) throw new Error('empty_capture');
    return { ok: true, row: primaryRow, rows: rows.length > 0 ? rows : [primaryRow], debug: captured.debug || {} };
  }

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (!message || !message.type) return false;

    if (message.type === 'profit_plugin_capture') {
      try {
        sendResponse(captureNow());
      } catch (err) {
        sendResponse({ ok: false, error: safeErrorMessage(err) });
      }
      return true;
    }

    if (message.type === 'profit_plugin_creative_scan') {
      rebuild((err, data) => {
        if (err) {
          sendResponse({ ok: false, error: safeErrorMessage(err) });
          return;
        }
        sendResponse({
          ok: true,
          context: data.context || {},
          rows: Array.isArray(data.rows) ? data.rows : []
        });
      });
      return true;
    }

    if (message.type === 'profit_plugin_creative_apply_labels') {
      try {
        const payloadRows = Array.isArray(message.payload && message.payload.rows) ? message.payload.rows : [];
        const nextRows = state.rows.slice();
        payloadRows.forEach((item, idx) => {
          const rowKey = String(item.row_key || '').trim();
          const videoId = String(item.video_id || '').trim();
          let targetIndex = -1;

          if (rowKey) {
            targetIndex = nextRows.findIndex((row) => String(row.row_key || '') === rowKey);
          }
          if (targetIndex < 0 && videoId) {
            targetIndex = nextRows.findIndex((row) => String(row.video_id || '') === videoId);
          }
          if (targetIndex < 0 && idx < nextRows.length) {
            targetIndex = idx;
          }

          const base = targetIndex >= 0 ? nextRows[targetIndex] : {};
          const merged = Object.assign({}, base, {
            row_key: rowKey || base.row_key || `row_${idx + 1}`,
            video_id: videoId || base.video_id || '',
            auto_label: normalizeLabel(item.auto_label || base.auto_label || 'observe'),
            manual_label: item.manual_label ? normalizeLabel(item.manual_label) : (base.manual_label || ''),
            exclude_flag: item.exclude_flag ? 1 : (base.exclude_flag || 0)
          });

          if (targetIndex >= 0) nextRows[targetIndex] = merged;
          else nextRows.push(merged);
        });

        state.rows = nextRows;
        const rowMapByVideo = {};
        state.rows.forEach((row) => {
          if (row.video_id && !rowMapByVideo[row.video_id]) rowMapByVideo[row.video_id] = row;
        });
        state.rowMapByVideo = rowMapByVideo;
        render();
        sendResponse({ ok: true });
      } catch (err) {
        sendResponse({ ok: false, error: safeErrorMessage(err) });
      }
      return true;
    }

    if (message.type === 'profit_plugin_creative_export_excludes') {
      exportExcludeIds(message.payload || {}, (err, ids) => {
        if (err) {
          sendResponse({ ok: false, error: safeErrorMessage(err) });
          return;
        }
        sendResponse({ ok: true, video_ids: ids || [] });
      });
      return true;
    }

    return false;
  });

  ensureStyle();
  ensureObserver();
  scheduleRefresh(500);
})();
