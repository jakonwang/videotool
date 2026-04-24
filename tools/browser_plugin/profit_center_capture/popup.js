(function () {
  'use strict';

  const STORAGE_KEY = 'profit_plugin_config_v1';
  const CREATIVE_STORAGE_KEY = 'profit_plugin_creative_opt_v1';

  const state = {
    config: {
      apiBase: '',
      token: ''
    },
    bootstrap: null,
    rows: [],
    creativeRows: [],
    creativeContext: null,
    creativeOnlyExclude: false,
    assistantRecommendation: null,
    seq: 1,
    connected: false,
    submitting: false
  };

  const refs = {
    apiBaseInput: document.getElementById('apiBaseInput'),
    tokenInput: document.getElementById('tokenInput'),
    connectBtn: document.getElementById('connectBtn'),
    saveConfigBtn: document.getElementById('saveConfigBtn'),
    captureBtn: document.getElementById('captureBtn'),
    addRowBtn: document.getElementById('addRowBtn'),
    clearRowsBtn: document.getElementById('clearRowsBtn'),
    batchDatesInput: document.getElementById('batchDatesInput'),
    expandByDatesBtn: document.getElementById('expandByDatesBtn'),
    clearBatchDatesBtn: document.getElementById('clearBatchDatesBtn'),
    submitBtn: document.getElementById('submitBtn'),
    connectBadge: document.getElementById('connectBadge'),
    statusBar: document.getElementById('statusBar'),
    summaryBar: document.getElementById('summaryBar'),
    rowsBody: document.getElementById('rowsBody'),
    resultBox: document.getElementById('resultBox'),
    creativeScanBtn: document.getElementById('creativeScanBtn'),
    creativeApplyBtn: document.getElementById('creativeApplyBtn'),
    creativeFilterExcludeBtn: document.getElementById('creativeFilterExcludeBtn'),
    creativeCopyExcludeBtn: document.getElementById('creativeCopyExcludeBtn'),
    creativeClearOverrideBtn: document.getElementById('creativeClearOverrideBtn'),
    creativeSummaryBar: document.getElementById('creativeSummaryBar'),
    creativeRowsBody: document.getElementById('creativeRowsBody'),
    assistantSyncBtn: document.getElementById('assistantSyncBtn'),
    assistantCopyExcludeBtn: document.getElementById('assistantCopyExcludeBtn'),
    assistantStoreSelect: document.getElementById('assistantStoreSelect'),
    assistantTargetRoiInput: document.getElementById('assistantTargetRoiInput'),
    assistantBudgetInput: document.getElementById('assistantBudgetInput'),
    assistantSummaryBar: document.getElementById('assistantSummaryBar'),
    assistantResultBox: document.getElementById('assistantResultBox')
  };

  const defaultChannels = [
    { value: 'video', label: '视频' },
    { value: 'live', label: '直播' },
    { value: 'influencer', label: '达人' }
  ];
  const defaultCurrencies = ['USD', 'VND', 'CNY'];

  function escapeHtml(input) {
    return String(input == null ? '' : input)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizeApiBase(raw) {
    let base = String(raw || '').trim();
    if (!base) return '';
    if (!/^https?:\/\//i.test(base)) {
      base = 'https://' + base;
    }
    return base.replace(/\/+$/, '');
  }

  function normalizeDate(raw) {
    const text = String(raw || '').trim();
    if (!text) return '';
    let m = text.match(/(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})/);
    if (m) return `${m[1]}-${String(Number(m[2])).padStart(2, '0')}-${String(Number(m[3])).padStart(2, '0')}`;
    m = text.match(/(\d{1,2})[-\/.](\d{1,2})[-\/.](20\d{2})/);
    if (m) return `${m[3]}-${String(Number(m[2])).padStart(2, '0')}-${String(Number(m[1])).padStart(2, '0')}`;
    return '';
  }

  function todayText() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  function dateToText(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  function parseDateText(dateText) {
    const normalized = normalizeDate(dateText);
    if (!normalized) return null;
    const dt = new Date(`${normalized}T00:00:00`);
    return Number.isNaN(dt.getTime()) ? null : dt;
  }

  function enumerateDateRange(startText, endText) {
    const start = parseDateText(startText);
    const end = parseDateText(endText);
    if (!start || !end) return [];
    const startTime = start.getTime();
    const endTime = end.getTime();
    if (startTime > endTime) return [];

    const list = [];
    const cursor = new Date(startTime);
    while (cursor.getTime() <= endTime) {
      list.push(dateToText(cursor));
      cursor.setDate(cursor.getDate() + 1);
      if (list.length > 370) break;
    }
    return list;
  }

  function parseBatchDatesInput(rawText) {
    const source = String(rawText || '').trim();
    if (!source) return [];
    const segments = source
      .split(/[\n,，;；]+/)
      .map((item) => String(item || '').trim())
      .filter(Boolean);

    const result = [];
    const seen = new Set();
    segments.forEach((segment) => {
      const rangeMatch = segment.match(/^(.+?)\s*(?:~|至|to)\s*(.+)$/i);
      const picked = rangeMatch
        ? enumerateDateRange(rangeMatch[1], rangeMatch[2])
        : [normalizeDate(segment)];
      picked.forEach((dateText) => {
        const normalized = normalizeDate(dateText);
        if (!normalized || seen.has(normalized)) return;
        seen.add(normalized);
        result.push(normalized);
      });
    });
    return result;
  }

  function lowerTrim(text) {
    return String(text || '').trim().toLowerCase();
  }

  function normalizeRawMetrics(raw) {
    if (raw == null || raw === '') return null;
    if (typeof raw === 'object' && !Array.isArray(raw)) {
      return Object.assign({}, raw);
    }
    if (typeof raw === 'string') {
      const text = raw.trim();
      if (!text) return null;
      try {
        const decoded = JSON.parse(text);
        if (decoded && typeof decoded === 'object' && !Array.isArray(decoded)) {
          return decoded;
        }
      } catch (_) {
        return { raw_text: text.slice(0, 1000) };
      }
    }
    return null;
  }

  function normalizeCreativeLabel(raw) {
    const text = String(raw || '').trim().toLowerCase();
    if (text === 'excellent' || text === 'scale') return 'excellent';
    if (text === 'optimize') return 'optimize';
    if (text === 'observe' || text === 'potential' || text === 'keep') return 'observe';
    if (text === 'garbage' || text === 'bad' || text === 'exclude_candidate') return 'garbage';
    if (text === 'ignore') return 'ignore';
    return 'observe';
  }

  function creativeLabelText(label) {
    const normalized = normalizeCreativeLabel(label);
    if (normalized === 'excellent') return '放量素材';
    if (normalized === 'optimize') return '优化素材';
    if (normalized === 'garbage') return '垃圾素材';
    if (normalized === 'ignore') return '忽略';
    return '观察中';
  }

  function normalizeCreativeContext(raw) {
    return {
      host: String(raw && raw.host || '').trim().toLowerCase(),
      campaign_id: String(raw && raw.campaign_id || '').trim(),
      date_range: String(raw && raw.date_range || '').trim()
    };
  }

  function creativeContextKey(raw) {
    const ctx = normalizeCreativeContext(raw);
    return `${ctx.host || 'unknown_host'}|${ctx.campaign_id || 'unknown_campaign'}|${ctx.date_range || 'unknown_date'}`;
  }

  function creativeDecisionKey(context, videoId) {
    return `${creativeContextKey(context)}|${String(videoId || '').trim()}`;
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

  function syncVideoId(row, index) {
    const raw = String(row && row.video_id || '').trim();
    if (raw && raw.toLowerCase() !== 'n/a') return raw;
    const seed = [
      row && row.row_key,
      row && row.title,
      row && row.tiktok_account,
      row && row.metrics_hash,
      index
    ].join('|');
    return `pseudo_${hashText(seed)}`;
  }

  function readCreativeStore() {
    return storageGet(CREATIVE_STORAGE_KEY).then((res) => {
      const payload = res && res[CREATIVE_STORAGE_KEY];
      if (payload && typeof payload === 'object') return Object.assign({}, payload);
      return {};
    });
  }

  function writeCreativeStore(payload) {
    return storageSet({ [CREATIVE_STORAGE_KEY]: payload || {} });
  }

  function storageGet(key) {
    return new Promise((resolve) => {
      chrome.storage.local.get([key], (res) => {
        resolve(res || {});
      });
    });
  }

  function storageSet(payload) {
    return new Promise((resolve) => {
      chrome.storage.local.set(payload, () => resolve());
    });
  }

  function runtimeSendMessage(payload) {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage(payload, (resp) => {
        const err = chrome.runtime.lastError;
        if (err) {
          reject(new Error(err.message || 'runtime_send_failed'));
          return;
        }
        resolve(resp);
      });
    });
  }

  function queryActiveTab() {
    return new Promise((resolve, reject) => {
      chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        const err = chrome.runtime.lastError;
        if (err) {
          reject(new Error(err.message || 'tab_query_failed'));
          return;
        }
        if (!tabs || tabs.length === 0 || !tabs[0].id) {
          reject(new Error('active_tab_not_found'));
          return;
        }
        resolve(tabs[0]);
      });
    });
  }

  function sendToTab(tabId, payload) {
    return new Promise((resolve, reject) => {
      chrome.tabs.sendMessage(tabId, payload, (resp) => {
        const err = chrome.runtime.lastError;
        if (err) {
          reject(new Error(err.message || 'tab_send_failed'));
          return;
        }
        resolve(resp);
      });
    });
  }

  function setStatus(text, type) {
    refs.statusBar.textContent = text || '';
    refs.statusBar.className = 'pcp-status';
    if (type === 'error') refs.statusBar.classList.add('is-error');
    if (type === 'success') refs.statusBar.classList.add('is-success');
  }

  function setConnected(connected) {
    state.connected = !!connected;
    refs.connectBadge.textContent = connected ? '已连接' : '未连接';
    refs.connectBadge.className = connected ? 'pcp-badge pcp-badge-ok' : 'pcp-badge pcp-badge-idle';
  }

  function getStores() {
    return Array.isArray(state.bootstrap && state.bootstrap.stores) ? state.bootstrap.stores : [];
  }

  function getAccounts() {
    return Array.isArray(state.bootstrap && state.bootstrap.accounts) ? state.bootstrap.accounts : [];
  }

  function getStoreMappings() {
    return Array.isArray(state.bootstrap && state.bootstrap.mappings && state.bootstrap.mappings.store)
      ? state.bootstrap.mappings.store
      : [];
  }

  function getAccountMappings() {
    return Array.isArray(state.bootstrap && state.bootstrap.mappings && state.bootstrap.mappings.account)
      ? state.bootstrap.mappings.account
      : [];
  }

  function getChannelOptions() {
    const raw = state.bootstrap && state.bootstrap.channel_options;
    if (Array.isArray(raw) && raw.length > 0) {
      return raw.map((item) => {
        const value = String(item || '').trim();
        const label = value === 'live' ? '直播' : value === 'influencer' ? '达人' : '视频';
        return { value, label };
      });
    }
    return defaultChannels;
  }

  function getCurrencyOptions() {
    const raw = state.bootstrap && state.bootstrap.currency_options;
    if (Array.isArray(raw) && raw.length > 0) {
      return raw.map((c) => String(c || '').toUpperCase()).filter(Boolean);
    }
    return defaultCurrencies;
  }

  function storeById(id) {
    const target = Number(id || 0);
    if (target <= 0) return null;
    return getStores().find((s) => Number(s.id || 0) === target) || null;
  }

  function accountById(id) {
    const target = Number(id || 0);
    if (target <= 0) return null;
    return getAccounts().find((a) => Number(a.id || 0) === target) || null;
  }

  function accountsByStore(storeId) {
    const sid = Number(storeId || 0);
    if (sid <= 0) return getAccounts();
    return getAccounts().filter((a) => Number(a.store_id || 0) === sid);
  }

  function guessStoreId(refText) {
    const ref = lowerTrim(refText);
    if (!ref) return 0;

    if (/^\d+$/.test(ref)) {
      const id = Number(ref);
      if (storeById(id)) return id;
    }

    const mapped = getStoreMappings().find((m) => lowerTrim(m.alias) === ref);
    if (mapped && Number(mapped.store_id || 0) > 0) {
      return Number(mapped.store_id);
    }

    const direct = getStores().find((s) => {
      return lowerTrim(s.store_name) === ref || lowerTrim(s.store_code) === ref;
    });
    return direct ? Number(direct.id || 0) : 0;
  }

  function guessAccountId(refText, storeId) {
    const ref = lowerTrim(refText);
    if (!ref) return 0;

    if (/^\d+$/.test(ref)) {
      const id = Number(ref);
      const found = accountById(id);
      if (found && (!storeId || Number(found.store_id || 0) === Number(storeId || 0))) {
        return id;
      }
    }

    const mapped = getAccountMappings().find((m) => lowerTrim(m.alias) === ref);
    if (mapped && Number(mapped.account_id || 0) > 0) {
      const mappedAccount = accountById(mapped.account_id);
      if (mappedAccount && (!storeId || Number(mappedAccount.store_id || 0) === Number(storeId || 0))) {
        return Number(mapped.account_id);
      }
    }

    const list = storeId ? accountsByStore(storeId) : getAccounts();
    const direct = list.find((a) => lowerTrim(a.account_name) === ref || lowerTrim(a.account_code) === ref);
    return direct ? Number(direct.id || 0) : 0;
  }

  function rowDefaults() {
    return {
      id: state.seq++,
      entry_date: todayText(),
      store_ref: '',
      account_ref: '',
      store_id: '',
      account_id: '',
      channel_type: 'video',
      ad_spend_amount: '',
      ad_spend_currency: 'USD',
      gmv_amount: '',
      gmv_currency: 'VND',
      order_count: '',
      roi_value: '',
      page_type: '',
      raw_metrics_json: null,
      source_page: '',
      status: '',
      message: ''
    };
  }

  function rowUniqueKey(row) {
    const date = normalizeDate(row.entry_date || '');
    const store = String(row.store_id || row.store_ref || '').trim().toLowerCase();
    const account = String(row.account_id || row.account_ref || '').trim().toLowerCase();
    const channel = String(row.channel_type || 'video').trim().toLowerCase();
    return [date, store, account, channel].join('|');
  }

  function cloneRowWithDate(sourceRow, dateText) {
    const cloned = Object.assign(rowDefaults(), sourceRow, {
      id: state.seq++,
      entry_date: dateText,
      status: '',
      message: ''
    });
    return cloned;
  }

  function fillCurrencyByAccount(row) {
    const account = accountById(row.account_id);
    if (account) {
      if (!row.ad_spend_currency) {
        row.ad_spend_currency = String(account.account_currency || 'USD').toUpperCase();
      }
      if (!row.gmv_currency) {
        row.gmv_currency = String(account.default_gmv_currency || 'VND').toUpperCase();
      }
    }
    if (String(row.page_type || '').toLowerCase() === 'ad' && row.ad_spend_currency) {
      row.gmv_currency = String(row.ad_spend_currency || '').toUpperCase();
    }
  }

  function normalizeCapturedRow(raw) {
    const row = Object.assign(rowDefaults(), {
      entry_date: normalizeDate(raw.entry_date) || todayText(),
      store_ref: String(raw.store_ref || '').trim(),
      account_ref: String(raw.account_ref || '').trim(),
      channel_type: String(raw.channel_type || 'video').trim() || 'video',
      ad_spend_amount: raw.ad_spend_amount == null ? '' : String(raw.ad_spend_amount),
      ad_spend_currency: String(raw.ad_spend_currency || '').toUpperCase() || 'USD',
      gmv_amount: raw.gmv_amount == null ? '' : String(raw.gmv_amount),
      gmv_currency: String(raw.gmv_currency || '').toUpperCase() || 'VND',
      order_count: raw.order_count == null ? '' : String(raw.order_count),
      roi_value: raw.roi_value == null ? '' : String(raw.roi_value),
      page_type: String(raw.page_type || '').trim().toLowerCase(),
      raw_metrics_json: normalizeRawMetrics(raw.raw_metrics_json || raw.raw_metrics || null),
      source_page: String(raw.source_page || '').trim()
    });

    row.store_id = guessStoreId(row.store_ref) || '';
    row.account_id = guessAccountId(row.account_ref, row.store_id || 0) || '';

    if (!row.store_ref && row.store_id) {
      const s = storeById(row.store_id);
      row.store_ref = s ? String(s.store_name || s.store_code || row.store_id) : '';
    }
    if (!row.account_ref && row.account_id) {
      const a = accountById(row.account_id);
      row.account_ref = a ? String(a.account_name || a.account_code || row.account_id) : '';
    }

    fillCurrencyByAccount(row);
    return row;
  }

  function autoMapRows() {
    state.rows.forEach((row) => {
      if (!row.store_id && row.store_ref) {
        row.store_id = guessStoreId(row.store_ref) || '';
      }
      if (!row.account_id && row.account_ref) {
        row.account_id = guessAccountId(row.account_ref, row.store_id || 0) || '';
      }
      fillCurrencyByAccount(row);
    });
  }

  function storeOptionsHtml(selectedId) {
    const current = Number(selectedId || 0);
    const options = ['<option value="">-- 手动别名 --</option>'];
    getStores().forEach((s) => {
      const id = Number(s.id || 0);
      const selected = id === current ? ' selected' : '';
      options.push(`<option value="${id}"${selected}>${escapeHtml(s.store_name || s.store_code || String(id))}</option>`);
    });
    return options.join('');
  }

  function accountOptionsHtml(storeId, selectedId) {
    const current = Number(selectedId || 0);
    const options = ['<option value="">-- 手动别名 --</option>'];
    accountsByStore(storeId).forEach((a) => {
      const id = Number(a.id || 0);
      const selected = id === current ? ' selected' : '';
      const label = `${a.account_name || a.account_code || id}`;
      options.push(`<option value="${id}"${selected}>${escapeHtml(label)}</option>`);
    });
    return options.join('');
  }

  function channelOptionsHtml(selected) {
    const current = String(selected || 'video');
    return getChannelOptions().map((c) => {
      const selectedFlag = c.value === current ? ' selected' : '';
      return `<option value="${escapeHtml(c.value)}"${selectedFlag}>${escapeHtml(c.label)}</option>`;
    }).join('');
  }

  function currencyOptionsHtml(selected) {
    const current = String(selected || '').toUpperCase();
    return getCurrencyOptions().map((c) => {
      const selectedFlag = c === current ? ' selected' : '';
      return `<option value="${escapeHtml(c)}"${selectedFlag}>${escapeHtml(c)}</option>`;
    }).join('');
  }

  function renderAssistantStoreOptions() {
    if (!refs.assistantStoreSelect) return;
    const current = refs.assistantStoreSelect.value;
    const options = ['<option value="">请选择店铺</option>'];
    getStores().forEach((s) => {
      const id = String(Number(s.id || 0));
      if (id === '0') return;
      const selected = id === current ? ' selected' : '';
      options.push(`<option value="${escapeHtml(id)}"${selected}>${escapeHtml(s.store_name || s.store_code || id)}</option>`);
    });
    refs.assistantStoreSelect.innerHTML = options.join('');
    if (current && Array.from(refs.assistantStoreSelect.options).some((opt) => opt.value === current)) {
      refs.assistantStoreSelect.value = current;
    }
  }

  function stageText(stage) {
    const map = {
      cold_start: '冷启动',
      learning: '学习期',
      stable: '稳定期',
      scale: '放量期',
      fatigue_risk: '疲劳风险'
    };
    return map[stage] || stage || '-';
  }

  function problemText(problem) {
    const map = {
      hook: '前3秒钩子',
      retention: '中段留存',
      conversion: '转化段',
      bid_budget: '出价/预算',
      insufficient_data: '样本不足',
      mixed: '多段混合'
    };
    return map[problem] || problem || '-';
  }

  function actionText(action) {
    const map = {
      scale: '小步放量',
      optimize: '先优化素材',
      observe: '继续观察',
      stop_loss: '先止损'
    };
    return map[action] || action || '-';
  }

  function renderList(items) {
    const list = Array.isArray(items) ? items.filter(Boolean) : [];
    if (list.length === 0) return '<p class="pcp-muted">暂无</p>';
    return `<ul>${list.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
  }

  function renderGmvMaxPlaybook() {
    const steps = [
      '冷启动准备：先算清保本 ROI、毛利、客单价和可承受 CPA；同一产品准备 5-10 条不同钩子素材，不要只靠一条素材起量。',
      '首测设置：新产品先用保本或略低于保本的目标 ROI 起量，预算按预估 CPA 的 10-20 倍设置；前 24 小时不要频繁改 ROI、预算、商品和定向。',
      '素材判断：前3秒看 CTR+2秒播放率，中段看 6秒/25%/50%/75% 播放率，转化段看 CVR+ROI+SKU orders；先优化素材，再考虑降 ROI 抢量。',
      '放量动作：素材 ROI 稳定高于目标且有订单时，预算每次加 20%-30%；高 ROI 但没量时，目标 ROI 每次下调 0.3 观察，不要一次性大幅降。',
      '止损动作：有花费无订单、ROI 低、转化率低且点击充足的素材，先排除或重剪；钩子弱就换首帧和前3秒，不要只改后半段。',
      '账户养护：新账户先让系统打标签，目标累计 20-30 单、消耗 700-1000 美金；同一账户不要频繁换完全不同产品，避免模型混乱。',
      '每日节奏：上午同步素材数据并排除垃圾，下午补 3-5 条新素材测试，晚上复盘放量素材和失败原因，第二天围绕胜出素材做变体。'
    ];
    return `<div class="pcp-playbook">
      <h3>GMV Max 从0到放量 SOP</h3>
      <ol>${steps.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ol>
      <p class="pcp-playbook-note">核心公式：ECPM = 点击率 × 转化率 × 出价。点击率和转化率主要靠素材，出价由预算和目标 ROI 决定。</p>
    </div>`;
  }

  function renderAssistantRecommendation() {
    if (!refs.assistantSummaryBar || !refs.assistantResultBox) return;
    const rec = state.assistantRecommendation;
    if (!rec) {
      refs.assistantSummaryBar.innerHTML = '';
      refs.assistantResultBox.innerHTML = [
        '<div class="pcp-advice-block"><h3>动态建议</h3><p>暂无后端建议。请先打开 TikTok GMV Max 素材列表页，勾选核心指标列后点击“同步到后端并生成建议”。</p></div>',
        renderGmvMaxPlaybook()
      ].join('');
      return;
    }
    const stats = rec.stats || {};
    refs.assistantSummaryBar.innerHTML = [
      `<div class="pcp-summary-item"><span>阶段</span><strong>${escapeHtml(stageText(rec.stage))}</strong></div>`,
      `<div class="pcp-summary-item"><span>主问题</span><strong>${escapeHtml(problemText(rec.main_problem))}</strong></div>`,
      `<div class="pcp-summary-item"><span>动作</span><strong>${escapeHtml(actionText(rec.action_level))}</strong></div>`,
      `<div class="pcp-summary-item"><span>样本</span><strong>${escapeHtml(rec.sample_level || '-')}</strong></div>`,
      `<div class="pcp-summary-item"><span>ROI</span><strong>${escapeHtml(stats.avg_roi || '0')}</strong></div>`,
      `<div class="pcp-summary-item"><span>订单</span><strong>${escapeHtml(stats.total_orders || '0')}</strong></div>`
    ].join('');
    refs.assistantResultBox.innerHTML = [
      `<div class="pcp-advice-block"><h3>核心结论</h3><p class="pcp-advice-strong">${escapeHtml(rec.core_conclusion || '-')}</p></div>`,
      `<div class="pcp-advice-block"><h3>今日该做</h3>${renderList(rec.today_do)}</div>`,
      `<div class="pcp-advice-block"><h3>今日不要做</h3>${renderList(rec.today_avoid)}</div>`,
      `<div class="pcp-advice-block"><h3>预算建议</h3><p>${escapeHtml(rec.budget_advice || '-')}</p></div>`,
      `<div class="pcp-advice-block"><h3>ROI建议</h3><p>${escapeHtml(rec.roi_advice || '-')}</p></div>`,
      `<div class="pcp-advice-block"><h3>素材新增方向</h3>${renderList(rec.creative_advice)}</div>`,
      `<div class="pcp-advice-block"><h3>放量视频ID</h3><p>${escapeHtml((rec.scale_video_ids || []).join('\\n') || '-')}</p></div>`,
      `<div class="pcp-advice-block"><h3>待排除视频ID</h3><p class="pcp-advice-warn">${escapeHtml((rec.exclude_video_ids || []).join('\\n') || '-')}</p></div>`,
      renderGmvMaxPlaybook()
    ].join('');
  }

  function renderRows() {
    const html = state.rows.map((row, i) => {
      const statusClass = row.status === 'ok' ? 'ok' : row.status === 'fail' ? 'fail' : '';
      const statusText = row.status === 'ok' ? '成功' : row.status === 'fail' ? (row.message || '失败') : '-';
      return `
        <tr data-id="${row.id}">
          <td>${i + 1}</td>
          <td><input type="date" data-field="entry_date" value="${escapeHtml(row.entry_date)}" /></td>
          <td>
            <div class="pcp-cell-stack">
              <select data-field="store_id">${storeOptionsHtml(row.store_id)}</select>
              <input type="text" data-field="store_ref" value="${escapeHtml(row.store_ref)}" placeholder="店铺别名/编号" />
            </div>
          </td>
          <td>
            <div class="pcp-cell-stack">
              <select data-field="account_id">${accountOptionsHtml(row.store_id, row.account_id)}</select>
              <input type="text" data-field="account_ref" value="${escapeHtml(row.account_ref)}" placeholder="广告户别名/编号" />
            </div>
          </td>
          <td><select data-field="channel_type">${channelOptionsHtml(row.channel_type)}</select></td>
          <td>
            <div class="pcp-cell-inline">
              <input type="number" step="0.01" min="0" data-field="ad_spend_amount" value="${escapeHtml(row.ad_spend_amount)}" />
              <select data-field="ad_spend_currency">${currencyOptionsHtml(row.ad_spend_currency)}</select>
            </div>
          </td>
          <td>
            <div class="pcp-cell-inline">
              <input type="number" step="0.01" min="0" data-field="gmv_amount" value="${escapeHtml(row.gmv_amount)}" />
              <select data-field="gmv_currency">${currencyOptionsHtml(row.gmv_currency)}</select>
            </div>
          </td>
          <td><input type="number" step="1" min="0" data-field="order_count" value="${escapeHtml(row.order_count)}" /></td>
          <td class="pcp-source" title="${escapeHtml(row.source_page)}">${escapeHtml(row.source_page)}</td>
          <td><span class="pcp-row-status ${statusClass}">${escapeHtml(statusText)}</span></td>
          <td>
            <button type="button" class="btn-link" data-action="duplicate-row">复制</button>
            <button type="button" class="btn-link-danger" data-action="delete-row">删除</button>
          </td>
        </tr>`;
    }).join('');

    refs.rowsBody.innerHTML = html || '<tr><td colspan="11" style="text-align:center;color:#64748b;padding:18px;">暂无抓取数据</td></tr>';
  }

  function renderSummary() {
    const total = state.rows.length;
    const invalid = state.rows.filter((row) => validateRow(row) !== '').length;
    const success = state.rows.filter((row) => row.status === 'ok').length;
    const failed = state.rows.filter((row) => row.status === 'fail').length;
    const uniqueDates = new Set(state.rows.map((row) => normalizeDate(row.entry_date)).filter(Boolean)).size;

    refs.summaryBar.innerHTML = [
      `<div class="pcp-summary-item"><span>预览行数</span><strong>${total}</strong></div>`,
      `<div class="pcp-summary-item"><span>覆盖日期</span><strong>${uniqueDates}</strong></div>`,
      `<div class="pcp-summary-item"><span>待修正</span><strong>${invalid}</strong></div>`,
      `<div class="pcp-summary-item"><span>最近成功</span><strong>${success}</strong></div>`,
      `<div class="pcp-summary-item"><span>最近失败</span><strong>${failed}</strong></div>`
    ].join('');
  }

  function renderAll() {
    renderRows();
    renderSummary();
    renderAssistantStoreOptions();
    renderAssistantRecommendation();
  }

  function normalizeCreativeRow(raw, context) {
    const row = Object.assign({
      id: `cr_${state.seq++}`,
      row_index: 0,
      video_id: '',
      title: '',
      auto_label: 'observe',
      manual_label: '',
      exclude_flag: 0,
      ignore: false,
      metrics: {},
      metrics_hash: '',
      hook_score: 'insufficient_data',
      retention_score: 'insufficient_data',
      conversion_score: 'insufficient_data',
      material_type: 'observe',
      problem_position: 'multi_stage',
      continue_delivery: 'yes',
      core_conclusion: '',
      actions: [],
      confidence: 0
    }, raw || {});

    row.row_key = String(row.row_key || '').trim();
    row.video_id = String(row.video_id || '').trim();
    row.title = String(row.title || '').trim();
    if (!row.video_id) {
      row.video_id = syncVideoId(row, Number(row.row_index || 0));
      row.source_video_id_type = 'pseudo';
    } else if (!row.source_video_id_type) {
      row.source_video_id_type = String(row.video_id).startsWith('pseudo_') ? 'pseudo' : 'actual';
    }
    row.auto_label = normalizeCreativeLabel(row.auto_label);
    row.manual_label = row.manual_label ? normalizeCreativeLabel(row.manual_label) : '';
    row.exclude_flag = row.exclude_flag ? 1 : 0;
    row.ignore = !!row.ignore;
    row.metrics = row.metrics && typeof row.metrics === 'object' ? Object.assign({}, row.metrics) : {};
    row.metrics_hash = String(row.metrics_hash || '');
    row.hook_score = String(row.hook_score || (row.diagnosis && row.diagnosis.hook_score) || 'insufficient_data');
    row.retention_score = String(row.retention_score || (row.diagnosis && row.diagnosis.retention_score) || 'insufficient_data');
    row.conversion_score = String(row.conversion_score || (row.diagnosis && row.diagnosis.conversion_score) || 'insufficient_data');
    row.material_type = String(row.material_type || (row.diagnosis && row.diagnosis.material_type) || 'observe');
    row.problem_position = String(row.problem_position || (row.diagnosis && row.diagnosis.problem_position) || 'multi_stage');
    row.continue_delivery = String(row.continue_delivery || (row.diagnosis && row.diagnosis.continue_delivery) || 'yes');
    row.core_conclusion = String(row.core_conclusion || (row.diagnosis && row.diagnosis.core_conclusion) || '').trim();
    row.actions = Array.isArray(row.actions)
      ? row.actions
      : (Array.isArray(row.diagnosis && row.diagnosis.actions) ? row.diagnosis.actions : []);
    row.confidence = Number(row.confidence || (row.diagnosis && row.diagnosis.confidence) || 0);

    if (row.video_id) {
      row.decision_key = creativeDecisionKey(context, row.video_id);
    } else {
      row.decision_key = '';
    }
    return row;
  }

  function effectiveCreativeLabel(row) {
    if (row && row.manual_label) return normalizeCreativeLabel(row.manual_label);
    return normalizeCreativeLabel(row && row.auto_label);
  }

  function scoreText(score) {
    const text = String(score || '').toLowerCase();
    if (text === 'high') return '高';
    if (text === 'mid') return '中';
    if (text === 'low') return '低';
    return '数据不足';
  }

  function materialTypeText(type) {
    const value = String(type || '').toLowerCase();
    if (value === 'scale') return '放量素材';
    if (value === 'bad') return '差素材';
    if (value === 'optimize') return '优化素材';
    if (value === 'ignore') return '忽略';
    return '观察素材';
  }

  function problemPositionText(position) {
    const value = String(position || '').toLowerCase();
    if (value === 'front_3s') return '前3秒';
    if (value === 'middle') return '中段';
    if (value === 'conversion_tail') return '转化段';
    return '多阶段';
  }

  function continueText(value) {
    return String(value || '').toLowerCase() === 'yes' ? 'Yes' : 'No';
  }

  function diagnosisInlineText(row) {
    return `前3秒:${scoreText(row.hook_score)} / 中段:${scoreText(row.retention_score)} / 转化:${scoreText(row.conversion_score)}`;
  }

  function filteredCreativeRows() {
    if (!state.creativeOnlyExclude) return state.creativeRows;
    return state.creativeRows.filter((row) => {
      const label = effectiveCreativeLabel(row);
      return row.exclude_flag || label === 'garbage';
    });
  }

  function renderCreativeSummary() {
    if (!refs.creativeSummaryBar) return;
    const total = state.creativeRows.length;
    const excellent = state.creativeRows.filter((row) => effectiveCreativeLabel(row) === 'excellent').length;
    const optimize = state.creativeRows.filter((row) => effectiveCreativeLabel(row) === 'optimize').length;
    const observe = state.creativeRows.filter((row) => effectiveCreativeLabel(row) === 'observe').length;
    const garbage = state.creativeRows.filter((row) => effectiveCreativeLabel(row) === 'garbage').length;
    const exclude = state.creativeRows.filter((row) => row.exclude_flag || effectiveCreativeLabel(row) === 'garbage').length;
    const ignored = state.creativeRows.filter((row) => effectiveCreativeLabel(row) === 'ignore').length;
    refs.creativeSummaryBar.innerHTML = [
      `<div class="pcp-summary-item"><span>素材行数</span><strong>${total}</strong></div>`,
      `<div class="pcp-summary-item"><span>放量素材</span><strong>${excellent}</strong></div>`,
      `<div class="pcp-summary-item"><span>优化素材</span><strong>${optimize}</strong></div>`,
      `<div class="pcp-summary-item"><span>观察中</span><strong>${observe}</strong></div>`,
      `<div class="pcp-summary-item"><span>垃圾素材</span><strong>${garbage}</strong></div>`,
      `<div class="pcp-summary-item"><span>排除清单</span><strong>${exclude}</strong></div>`,
      `<div class="pcp-summary-item"><span>忽略</span><strong>${ignored}</strong></div>`
    ].join('');
  }

  function renderCreativeRows() {
    if (!refs.creativeRowsBody) return;
    const rows = filteredCreativeRows();
    const html = rows.map((row, idx) => {
      const autoLabel = normalizeCreativeLabel(row.auto_label);
      const manualLabel = row.manual_label ? normalizeCreativeLabel(row.manual_label) : '';
      const finalLabel = effectiveCreativeLabel(row);
      const actions = Array.isArray(row.actions) ? row.actions.slice(0, 3) : [];
      const actionsHtml = actions.length > 0
        ? `<ul class="pcp-actions-list">${actions.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`
        : '';
      return `
        <tr data-creative-id="${escapeHtml(row.id)}">
          <td>${idx + 1}</td>
          <td>${escapeHtml(row.video_id || '-')}</td>
          <td class="pcp-creative-title" title="${escapeHtml(row.title || '')}">${escapeHtml(row.title || '-')}</td>
          <td><span class="pcp-badge-mini ${autoLabel}">${escapeHtml(creativeLabelText(autoLabel))}</span></td>
          <td>
            <select data-creative-field="manual_label">
              <option value=""${manualLabel === '' ? ' selected' : ''}>跟随自动</option>
              <option value="excellent"${manualLabel === 'excellent' ? ' selected' : ''}>放量素材</option>
              <option value="optimize"${manualLabel === 'optimize' ? ' selected' : ''}>优化素材</option>
              <option value="observe"${manualLabel === 'observe' ? ' selected' : ''}>观察中</option>
              <option value="garbage"${manualLabel === 'garbage' ? ' selected' : ''}>垃圾素材</option>
              <option value="ignore"${manualLabel === 'ignore' ? ' selected' : ''}>忽略</option>
            </select>
            <div class="pcp-metric-inline">当前：${escapeHtml(creativeLabelText(finalLabel))}</div>
          </td>
          <td><span class="pcp-metric-inline">${escapeHtml(diagnosisInlineText(row))}</span></td>
          <td><span class="pcp-metric-inline">${escapeHtml(problemPositionText(row.problem_position))}</span></td>
          <td><span class="pcp-metric-inline">${escapeHtml(continueText(row.continue_delivery))}</span></td>
          <td><input type="checkbox" data-creative-field="exclude_flag" ${row.exclude_flag ? 'checked' : ''} /></td>
          <td>
            <div class="pcp-diagnosis">
              <span class="pcp-metric-inline">${escapeHtml(materialTypeText(row.material_type))} | 置信度 ${escapeHtml(String(row.confidence || 0))}</span>
              <span class="pcp-metric-inline">${escapeHtml(row.core_conclusion || '-')}</span>
              ${actionsHtml}
            </div>
          </td>
        </tr>
      `;
    }).join('');
    refs.creativeRowsBody.innerHTML = html || '<tr><td colspan="10" style="text-align:center;color:#64748b;padding:16px;">暂无素材数据，请先点击“识别并打标”</td></tr>';
  }

  function renderCreativeAll() {
    renderCreativeRows();
    renderCreativeSummary();
    if (refs.creativeFilterExcludeBtn) {
      refs.creativeFilterExcludeBtn.textContent = state.creativeOnlyExclude ? '显示全部素材' : '仅看垃圾素材';
    }
  }

  function rowByCreativeId(rowId) {
    return state.creativeRows.find((row) => String(row.id) === String(rowId));
  }

  async function scanCreativeRows() {
    const tab = await queryActiveTab();
    const result = await sendToTab(tab.id, { type: 'profit_plugin_creative_scan' });
    if (!result || !result.ok) {
      throw new Error(result && result.error ? result.error : 'creative_scan_failed');
    }
    state.creativeContext = normalizeCreativeContext(result.context || {});
    state.creativeRows = (Array.isArray(result.rows) ? result.rows : [])
      .map((row) => normalizeCreativeRow(row, state.creativeContext));
    renderCreativeAll();
    return { tab, count: state.creativeRows.length };
  }

  async function saveCreativeDecisions() {
    if (!state.creativeContext || state.creativeRows.length === 0) return;
    const storeObj = await readCreativeStore();
    state.creativeRows.forEach((row) => {
      if (!row.video_id) return;
      const key = creativeDecisionKey(state.creativeContext, row.video_id);
      storeObj[key] = {
        auto_label: normalizeCreativeLabel(row.auto_label),
        manual_label: row.manual_label ? normalizeCreativeLabel(row.manual_label) : '',
        exclude_flag: row.exclude_flag ? 1 : 0,
        last_metrics_hash: row.metrics_hash || '',
        updated_at: new Date().toISOString()
      };
    });
    await writeCreativeStore(storeObj);
  }

  async function applyCreativeLabelsToPage() {
    if (state.creativeRows.length === 0) {
      setStatus('请先识别素材后再应用页面标签', 'error');
      return;
    }
    const tab = await queryActiveTab();
    const payloadRows = state.creativeRows.map((row) => ({
      video_id: row.video_id,
      row_key: row.row_key || '',
      source_video_id_type: row.source_video_id_type || '',
      auto_label: normalizeCreativeLabel(row.auto_label),
      manual_label: row.manual_label ? normalizeCreativeLabel(row.manual_label) : '',
      exclude_flag: row.exclude_flag ? 1 : 0
    }));
    const result = await sendToTab(tab.id, {
      type: 'profit_plugin_creative_apply_labels',
      payload: {
        context: state.creativeContext || {},
        rows: payloadRows
      }
    });
    if (!result || !result.ok) {
      throw new Error(result && result.error ? result.error : 'creative_apply_failed');
    }
  }

  async function copyCreativeExcludes() {
    const ids = state.creativeRows
      .filter((row) => row.video_id && (row.exclude_flag || effectiveCreativeLabel(row) === 'garbage'))
      .map((row) => String(row.video_id));
    const unique = Array.from(new Set(ids));
    if (unique.length === 0) {
      setStatus('当前没有待排除视频ID', 'error');
      return;
    }
    const text = unique.join('\n');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
    }
    setStatus(`已复制 ${unique.length} 个视频ID`, 'success');
  }

  function numberOrNull(value) {
    if (value == null || value === '') return null;
    const n = Number(String(value).replace(/[,％%]/g, '').trim());
    return Number.isFinite(n) ? n : null;
  }

  function resolveAssistantMetricDate() {
    const ctx = state.creativeContext || {};
    const fromCtx = normalizeDate(ctx.date_range || '');
    return fromCtx || todayText();
  }

  function creativeRowsForSync() {
    return state.creativeRows
      .filter((row) => row && effectiveCreativeLabel(row) !== 'ignore')
      .map((row, index) => ({
        video_id: syncVideoId(row, index),
        source_video_id_type: row.source_video_id_type || (String(row.video_id || '').startsWith('pseudo_') ? 'pseudo' : 'actual'),
        row_key: row.row_key || '',
        title: row.title || '',
        tiktok_account: row.tiktok_account || '',
        status: row.status || '',
        metrics: row.metrics || {},
        auto_label: effectiveCreativeLabel(row),
        manual_label: row.manual_label || '',
        exclude_flag: !!row.exclude_flag,
        hook_score: row.hook_score || '',
        retention_score: row.retention_score || '',
        conversion_score: row.conversion_score || '',
        material_type: row.material_type || '',
        problem_position: row.problem_position || '',
        continue_delivery: row.continue_delivery || '',
        core_conclusion: row.core_conclusion || '',
        actions: Array.isArray(row.actions) ? row.actions.slice(0, 3) : [],
        diagnosis: row.diagnosis || null,
        source_page: state.creativeContext && state.creativeContext.host ? `https://${state.creativeContext.host}` : ''
      }))
      .filter((row) => row.video_id && (row.title || Object.keys(row.metrics || {}).length > 0));
  }

  async function syncAssistantToBackend() {
    try {
      if (refs.assistantSyncBtn) refs.assistantSyncBtn.disabled = true;
      setStatus('正在扫描素材并同步到后端...', '');

      if (!state.connected || !state.bootstrap) {
        await connectBootstrap();
      }
      await scanCreativeRows();
      await saveCreativeDecisions();
      await applyCreativeLabelsToPage();

      const storeId = refs.assistantStoreSelect ? String(refs.assistantStoreSelect.value || '').trim() : '';
      if (!storeId) {
        throw new Error('请先选择店铺');
      }
      let rows = creativeRowsForSync();
      if (rows.length === 0) {
        await scanCreativeRows();
        rows = creativeRowsForSync();
      }
      if (rows.length === 0) {
        throw new Error('没有识别到可同步素材。请确认当前是 TikTok GMV Max 素材列表页，并至少显示 Creative、Cost、ROI 或 SKU orders 等指标列');
      }

      const ctx = state.creativeContext || {};
      const payload = {
        store_id: storeId,
        campaign_id: String(ctx.campaign_id || '').trim(),
        campaign_name: '',
        date_range: String(ctx.date_range || '').trim(),
        metric_date: resolveAssistantMetricDate(),
        target_roi: numberOrNull(refs.assistantTargetRoiInput && refs.assistantTargetRoiInput.value),
        campaign_budget: numberOrNull(refs.assistantBudgetInput && refs.assistantBudgetInput.value),
        source_page: ctx.host ? `https://${ctx.host}` : '',
        rows
      };

      const json = await requestApi('/admin.php/gmv_max/creative/sync', 'POST', payload);
      const data = json.data || {};
      state.assistantRecommendation = data.recommendation || null;
      renderAssistantRecommendation();
      setStatus(`同步成功：保存 ${data.saved_count || 0} 条，失败 ${data.failed_count || 0} 条`, 'success');
    } catch (err) {
      setStatus(`投放助手同步失败：${err && err.message ? err.message : 'unknown'}`, 'error');
    } finally {
      if (refs.assistantSyncBtn) refs.assistantSyncBtn.disabled = false;
    }
  }

  async function copyAssistantExcludeIds() {
    const rec = state.assistantRecommendation || {};
    const ids = Array.isArray(rec.exclude_video_ids) ? rec.exclude_video_ids.filter(Boolean) : [];
    if (ids.length === 0) {
      setStatus('当前后端建议没有待排除视频ID', 'error');
      return;
    }
    const text = ids.join('\n');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
    }
    setStatus(`已复制 ${ids.length} 个后端建议排除ID`, 'success');
  }

  async function clearCreativeOverridesCurrentPage() {
    if (!state.creativeContext) {
      setStatus('当前无素材上下文，先执行一次识别', 'error');
      return;
    }
    const ctxPrefix = `${creativeContextKey(state.creativeContext)}|`;
    const storeObj = await readCreativeStore();
    Object.keys(storeObj).forEach((key) => {
      if (String(key).startsWith(ctxPrefix)) {
        delete storeObj[key];
      }
    });
    await writeCreativeStore(storeObj);
    state.creativeRows = state.creativeRows.map((row) => {
      const next = Object.assign({}, row);
      next.manual_label = '';
      next.exclude_flag = 0;
      return next;
    });
    renderCreativeAll();
    await applyCreativeLabelsToPage();
    setStatus('已清空当前页面的手动覆盖与排除清单', 'success');
  }

  function validateRow(row) {
    if (!normalizeDate(row.entry_date)) return 'entry_date_required';

    const storeRef = String(row.store_id || row.store_ref || '').trim();
    if (!storeRef) return 'store_required';

    const accountRef = String(row.account_id || row.account_ref || '').trim();
    if (!accountRef) return 'account_required';

    const channel = String(row.channel_type || 'video').trim();
    const ad = Number(row.ad_spend_amount || 0);
    const gmv = Number(row.gmv_amount || 0);
    const order = Number(row.order_count || 0);

    if ((channel === 'video' || channel === 'live') && (ad <= 0 || gmv <= 0 || order <= 0)) {
      return 'invalid_live_video_required_fields';
    }
    if (channel === 'influencer' && order <= 0) {
      return 'invalid_influencer_order_count';
    }
    if (!String(row.ad_spend_currency || '').trim()) return 'ad_currency_required';
    if (!String(row.gmv_currency || '').trim()) return 'gmv_currency_required';

    return '';
  }

  function rowToPayload(row) {
    const adAmount = row.ad_spend_amount === '' ? null : Number(row.ad_spend_amount);
    const gmvAmount = row.gmv_amount === '' ? null : Number(row.gmv_amount);
    const orderCount = row.order_count === '' ? null : Number(row.order_count);
    const roiValue = row.roi_value === '' ? null : Number(row.roi_value);

    const payload = {
      entry_date: normalizeDate(row.entry_date),
      store_ref: String(row.store_id || row.store_ref || '').trim(),
      account_ref: String(row.account_id || row.account_ref || '').trim(),
      channel_type: String(row.channel_type || 'video').trim() || 'video',
      ad_spend_amount: Number.isFinite(adAmount) ? adAmount : null,
      ad_spend_currency: String(row.ad_spend_currency || '').toUpperCase(),
      gmv_amount: Number.isFinite(gmvAmount) ? gmvAmount : null,
      gmv_currency: String(row.gmv_currency || '').toUpperCase(),
      order_count: Number.isFinite(orderCount) ? Math.floor(orderCount) : null,
      source_page: String(row.source_page || '').trim()
    };

    if (String(row.page_type || '').toLowerCase() === 'ad' && payload.ad_spend_currency) {
      payload.gmv_currency = payload.ad_spend_currency;
    }

    const rawMetrics = normalizeRawMetrics(row.raw_metrics_json);
    if (rawMetrics) {
      if (Number.isFinite(roiValue)) {
        rawMetrics.total_roi = roiValue;
      }
      payload.raw_metrics_json = rawMetrics;
    } else if (Number.isFinite(roiValue)) {
      payload.raw_metrics_json = { total_roi: roiValue };
    }

    return payload;
  }

  function buildUrl(path) {
    const base = normalizeApiBase(state.config.apiBase);
    if (!base) throw new Error('api_base_required');
    if (/\/admin\.php$/i.test(base) && /^\/admin\.php\//.test(path)) {
      return base + path.slice('/admin.php'.length);
    }
    return base + path;
  }

  async function requestApi(path, method, body) {
    const token = String(state.config.token || '').trim();
    if (!token) throw new Error('token_required');
    const headers = {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    };
    const payload = {
      type: 'profit_plugin_fetch',
      method: method || 'GET',
      url: buildUrl(path),
      headers
    };
    if (body != null) {
      payload.body = JSON.stringify(body);
    }

    const response = await runtimeSendMessage(payload);
    if (!response || !response.ok) {
      throw new Error(response && response.error ? response.error : 'network_failed');
    }

    const json = response.json || null;
    if (!json) {
      throw new Error('invalid_json_response');
    }

    if (Number(json.code || 0) !== 0) {
      const msg = String(json.msg || 'api_failed');
      const trace = String(json.trace_id || '');
      throw new Error(trace ? `${msg} (trace_id=${trace})` : msg);
    }

    return json;
  }

  async function loadConfig() {
    const store = await storageGet(STORAGE_KEY);
    const config = store[STORAGE_KEY] || {};
    state.config.apiBase = normalizeApiBase(config.apiBase || '');
    state.config.token = String(config.token || '');

    refs.apiBaseInput.value = state.config.apiBase;
    refs.tokenInput.value = state.config.token;
  }

  async function saveConfig(showMessage) {
    state.config.apiBase = normalizeApiBase(refs.apiBaseInput.value);
    state.config.token = String(refs.tokenInput.value || '').trim();
    await storageSet({
      [STORAGE_KEY]: {
        apiBase: state.config.apiBase,
        token: state.config.token
      }
    });
    if (showMessage) {
      setStatus('配置已保存', 'success');
    }
  }

  async function connectBootstrap() {
    try {
      await saveConfig(false);
      if (!state.config.apiBase) throw new Error('api_base_required');
      if (!state.config.token) throw new Error('token_required');

      setStatus('正在拉取配置...', '');
      refs.connectBtn.disabled = true;
      const json = await requestApi('/admin.php/profit_center/plugin/bootstrap', 'GET');
      state.bootstrap = json.data || {};
      autoMapRows();
      renderAll();
      setConnected(true);
      setStatus('连接成功，已同步店铺/广告户/映射', 'success');
    } catch (err) {
      setConnected(false);
      setStatus(`连接失败：${err && err.message ? err.message : 'unknown'}`, 'error');
    } finally {
      refs.connectBtn.disabled = false;
    }
  }

  async function captureActivePage() {
    try {
      refs.captureBtn.disabled = true;
      setStatus('正在抓取当前页面...', '');
      const tab = await queryActiveTab();
      const result = await sendToTab(tab.id, { type: 'profit_plugin_capture' });
      if (!result || !result.ok || (!Array.isArray(result.rows) && !result.row)) {
        throw new Error(result && result.error ? result.error : 'capture_failed');
      }

      const rawRows = Array.isArray(result.rows) && result.rows.length > 0
        ? result.rows
        : [result.row];
      const normalizedRows = rawRows
        .filter((item) => item && typeof item === 'object')
        .map((item) => normalizeCapturedRow(item));

      if (normalizedRows.length === 0) {
        throw new Error('empty_capture');
      }

      for (let i = normalizedRows.length - 1; i >= 0; i -= 1) {
        const row = normalizedRows[i];
        if (!row.source_page && tab.url) {
          row.source_page = String(tab.url);
        }
        state.rows.unshift(row);
      }

      renderAll();
      if (normalizedRows.length > 1) {
        setStatus(`抓取成功，已加入 ${normalizedRows.length} 行汇总数据`, 'success');
      } else {
        setStatus('抓取成功，已加入预览列表', 'success');
      }
    } catch (err) {
      setStatus(`抓取失败：${err && err.message ? err.message : 'unknown'}`, 'error');
    } finally {
      refs.captureBtn.disabled = false;
    }
  }

  function addEmptyRow() {
    state.rows.unshift(rowDefaults());
    renderAll();
  }

  function clearRows() {
    state.rows = [];
    renderAll();
    refs.resultBox.textContent = '暂无提交记录';
    setStatus('已清空预览行', 'success');
  }

  function expandRowsByDates() {
    const dates = parseBatchDatesInput(refs.batchDatesInput && refs.batchDatesInput.value);
    if (dates.length === 0) {
      setStatus('请先输入有效日期，示例：2026-04-18 或 2026-04-01~2026-04-07', 'error');
      return;
    }
    if (state.rows.length === 0) {
      setStatus('请先抓取至少一行，再按日期扩展', 'error');
      return;
    }

    const keySet = new Set(state.rows.map((row) => rowUniqueKey(row)));
    const snapshots = state.rows.slice();
    const added = [];
    snapshots.forEach((row) => {
      dates.forEach((dateText) => {
        const key = [dateText, String(row.store_id || row.store_ref || '').trim().toLowerCase(), String(row.account_id || row.account_ref || '').trim().toLowerCase(), String(row.channel_type || 'video').trim().toLowerCase()].join('|');
        if (keySet.has(key)) return;
        const cloned = cloneRowWithDate(row, dateText);
        added.push(cloned);
        keySet.add(key);
      });
    });

    if (added.length === 0) {
      setStatus('没有新增行（可能这些日期已存在）', 'success');
      return;
    }

    state.rows = added.concat(state.rows);
    renderAll();
    setStatus(`已按日期新增 ${added.length} 行，可一次性提交`, 'success');
  }

  function updateRowField(row, field, value) {
    if (!row) return;
    row[field] = value;

    if (field === 'store_id') {
      const storeId = Number(value || 0);
      const store = storeById(storeId);
      row.store_id = storeId > 0 ? storeId : '';
      if (store) {
        row.store_ref = String(store.store_name || store.store_code || storeId);
      }
      if (row.account_id) {
        const account = accountById(row.account_id);
        if (!account || Number(account.store_id || 0) !== Number(row.store_id || 0)) {
          row.account_id = '';
        }
      }
      renderAll();
      return;
    }

    if (field === 'account_id') {
      const accountId = Number(value || 0);
      const account = accountById(accountId);
      row.account_id = accountId > 0 ? accountId : '';
      if (account) {
        row.account_ref = String(account.account_name || account.account_code || accountId);
        if (!row.store_id) {
          row.store_id = Number(account.store_id || 0) || '';
        }
      }
      fillCurrencyByAccount(row);
      renderAll();
      return;
    }

    if (field === 'channel_type' || field.endsWith('_currency')) {
      if (field === 'ad_spend_currency' && String(row.page_type || '').toLowerCase() === 'ad') {
        row.gmv_currency = String(row.ad_spend_currency || '').toUpperCase();
        renderAll();
        return;
      }
      renderSummary();
    }
  }

  function rowById(rowId) {
    return state.rows.find((row) => Number(row.id) === Number(rowId));
  }

  async function submitRows() {
    if (state.submitting) return;
    if (state.rows.length === 0) {
      setStatus('请先抓取或新增至少一行数据', 'error');
      return;
    }

    const invalids = [];
    state.rows.forEach((row, idx) => {
      const reason = validateRow(row);
      if (reason) {
        invalids.push(`#${idx + 1}:${reason}`);
      }
    });

    if (invalids.length > 0) {
      setStatus(`存在无效行：${invalids.slice(0, 4).join(' | ')}`, 'error');
      return;
    }

    state.submitting = true;
    refs.submitBtn.disabled = true;

    try {
      setStatus('正在回传，请稍候...', '');
      state.rows.forEach((row) => {
        row.status = '';
        row.message = '';
      });
      renderAll();

      const payload = {
        rows: state.rows.map(rowToPayload)
      };
      const json = await requestApi('/admin.php/profit_center/plugin/ingestBatch', 'POST', payload);
      const data = json.data || {};

      const savedMap = {};
      const failedMap = {};
      (Array.isArray(data.saved_items) ? data.saved_items : []).forEach((item) => {
        const idx = Number(item.index || 0);
        if (idx > 0) savedMap[idx] = item;
      });
      (Array.isArray(data.failed_items) ? data.failed_items : []).forEach((item) => {
        const idx = Number(item.index || 0);
        if (idx > 0) failedMap[idx] = item;
      });

      state.rows.forEach((row, index) => {
        const idx = index + 1;
        if (savedMap[idx]) {
          row.status = 'ok';
          row.message = 'saved';
        } else if (failedMap[idx]) {
          row.status = 'fail';
          row.message = String(failedMap[idx].message || 'save_failed');
        } else {
          row.status = 'fail';
          row.message = 'unknown_result';
        }
      });

      const trace = String(json.trace_id || '');
      refs.resultBox.textContent = [
        `总行数: ${Number(data.total || state.rows.length)}`,
        `成功: ${Number(data.saved_count || 0)}`,
        `失败: ${Number(data.failed_count || 0)}`,
        trace ? `trace_id: ${trace}` : ''
      ].filter(Boolean).join('\n');

      renderAll();
      setStatus('回传完成，可查看每行状态', 'success');
    } catch (err) {
      refs.resultBox.textContent = `回传失败：${err && err.message ? err.message : 'unknown'}`;
      setStatus(refs.resultBox.textContent, 'error');
    } finally {
      state.submitting = false;
      refs.submitBtn.disabled = false;
    }
  }

  function bindEvents() {
    refs.saveConfigBtn.addEventListener('click', () => {
      saveConfig(true).catch((err) => setStatus(`保存失败：${err.message || err}`, 'error'));
    });

    refs.connectBtn.addEventListener('click', () => {
      connectBootstrap();
    });

    refs.captureBtn.addEventListener('click', () => {
      captureActivePage();
    });

    refs.addRowBtn.addEventListener('click', () => {
      addEmptyRow();
    });

    refs.clearRowsBtn.addEventListener('click', () => {
      clearRows();
    });

    refs.expandByDatesBtn.addEventListener('click', () => {
      expandRowsByDates();
    });

    refs.clearBatchDatesBtn.addEventListener('click', () => {
      if (refs.batchDatesInput) refs.batchDatesInput.value = '';
      setStatus('已清空批量日期', 'success');
    });

    refs.submitBtn.addEventListener('click', () => {
      submitRows();
    });

    if (refs.creativeScanBtn) {
      refs.creativeScanBtn.addEventListener('click', async () => {
        try {
          refs.creativeScanBtn.disabled = true;
          setStatus('正在识别素材并计算标签...', '');
          const result = await scanCreativeRows();
          await saveCreativeDecisions();
          await applyCreativeLabelsToPage();
          setStatus(`识别完成，共 ${result.count} 行素材，已应用页面标签`, 'success');
        } catch (err) {
          setStatus(`素材识别失败：${err && err.message ? err.message : 'unknown'}`, 'error');
        } finally {
          refs.creativeScanBtn.disabled = false;
        }
      });
    }

    if (refs.creativeApplyBtn) {
      refs.creativeApplyBtn.addEventListener('click', async () => {
        try {
          refs.creativeApplyBtn.disabled = true;
          await saveCreativeDecisions();
          await applyCreativeLabelsToPage();
          setStatus('已将当前素材标签应用到页面', 'success');
        } catch (err) {
          setStatus(`应用页面标签失败：${err && err.message ? err.message : 'unknown'}`, 'error');
        } finally {
          refs.creativeApplyBtn.disabled = false;
        }
      });
    }

    if (refs.creativeFilterExcludeBtn) {
      refs.creativeFilterExcludeBtn.addEventListener('click', () => {
        state.creativeOnlyExclude = !state.creativeOnlyExclude;
        renderCreativeAll();
      });
    }

    if (refs.creativeCopyExcludeBtn) {
      refs.creativeCopyExcludeBtn.addEventListener('click', () => {
        copyCreativeExcludes().catch((err) => {
          setStatus(`复制失败：${err && err.message ? err.message : 'unknown'}`, 'error');
        });
      });
    }

    if (refs.creativeClearOverrideBtn) {
      refs.creativeClearOverrideBtn.addEventListener('click', () => {
        clearCreativeOverridesCurrentPage().catch((err) => {
          setStatus(`清空覆盖失败：${err && err.message ? err.message : 'unknown'}`, 'error');
        });
      });
    }

    if (refs.assistantSyncBtn) {
      refs.assistantSyncBtn.addEventListener('click', () => {
        syncAssistantToBackend();
      });
    }

    if (refs.assistantCopyExcludeBtn) {
      refs.assistantCopyExcludeBtn.addEventListener('click', () => {
        copyAssistantExcludeIds().catch((err) => {
          setStatus(`复制后端排除ID失败：${err && err.message ? err.message : 'unknown'}`, 'error');
        });
      });
    }

    refs.rowsBody.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action]');
      if (!button) return;
      const tr = event.target.closest('tr[data-id]');
      if (!tr) return;
      const id = Number(tr.getAttribute('data-id') || 0);
      const action = String(button.getAttribute('data-action') || '');
      if (action === 'delete-row') {
        state.rows = state.rows.filter((row) => Number(row.id) !== id);
        renderAll();
        return;
      }
      if (action === 'duplicate-row') {
        const target = rowById(id);
        if (!target) return;
        const cloned = cloneRowWithDate(target, normalizeDate(target.entry_date) || todayText());
        state.rows.unshift(cloned);
        renderAll();
      }
    });

    refs.rowsBody.addEventListener('change', (event) => {
      const target = event.target;
      if (!target || !target.matches('input,select')) return;
      const tr = target.closest('tr[data-id]');
      if (!tr) return;
      const id = Number(tr.getAttribute('data-id') || 0);
      const row = rowById(id);
      if (!row) return;
      const field = target.getAttribute('data-field');
      if (!field) return;
      updateRowField(row, field, target.value);
    });

    refs.rowsBody.addEventListener('input', (event) => {
      const target = event.target;
      if (!target || !target.matches('input[data-field]')) return;
      const tr = target.closest('tr[data-id]');
      if (!tr) return;
      const id = Number(tr.getAttribute('data-id') || 0);
      const row = rowById(id);
      if (!row) return;
      const field = target.getAttribute('data-field');
      if (!field) return;
      row[field] = target.value;
      renderSummary();
    });

    if (refs.creativeRowsBody) {
      refs.creativeRowsBody.addEventListener('change', (event) => {
        const target = event.target;
        if (!target) return;
        const tr = target.closest('tr[data-creative-id]');
        if (!tr) return;
        const row = rowByCreativeId(tr.getAttribute('data-creative-id'));
        if (!row) return;
        const field = target.getAttribute('data-creative-field');
        if (!field) return;
        if (field === 'manual_label') {
          row.manual_label = target.value ? normalizeCreativeLabel(target.value) : '';
        } else if (field === 'exclude_flag') {
          row.exclude_flag = target.checked ? 1 : 0;
        }
        saveCreativeDecisions()
          .then(() => applyCreativeLabelsToPage())
          .then(() => renderCreativeAll())
          .catch((err) => setStatus(`素材设置保存失败：${err && err.message ? err.message : 'unknown'}`, 'error'));
      });
    }
  }

  async function init() {
    bindEvents();
    await loadConfig();
    renderAll();
    renderCreativeAll();
    setConnected(false);
    setStatus('请先填写 API Base 与 Token，然后点击“连接并拉取配置”', '');
  }

  init().catch((err) => {
    setStatus(`初始化失败：${err && err.message ? err.message : 'unknown'}`, 'error');
  });
})();
