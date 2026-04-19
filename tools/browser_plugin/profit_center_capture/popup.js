(function () {
  'use strict';

  const STORAGE_KEY = 'profit_plugin_config_v1';

  const state = {
    config: {
      apiBase: '',
      token: ''
    },
    bootstrap: null,
    rows: [],
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
    submitBtn: document.getElementById('submitBtn'),
    connectBadge: document.getElementById('connectBadge'),
    statusBar: document.getElementById('statusBar'),
    summaryBar: document.getElementById('summaryBar'),
    rowsBody: document.getElementById('rowsBody'),
    resultBox: document.getElementById('resultBox')
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

  function lowerTrim(text) {
    return String(text || '').trim().toLowerCase();
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
      source_page: '',
      status: '',
      message: ''
    };
  }

  function fillCurrencyByAccount(row) {
    const account = accountById(row.account_id);
    if (!account) return;
    if (!row.ad_spend_currency) {
      row.ad_spend_currency = String(account.account_currency || 'USD').toUpperCase();
    }
    if (!row.gmv_currency) {
      row.gmv_currency = String(account.default_gmv_currency || 'VND').toUpperCase();
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
          <td><button type="button" class="btn-link-danger" data-action="delete-row">删除</button></td>
        </tr>`;
    }).join('');

    refs.rowsBody.innerHTML = html || '<tr><td colspan="11" style="text-align:center;color:#64748b;padding:18px;">暂无抓取数据</td></tr>';
  }

  function renderSummary() {
    const total = state.rows.length;
    const invalid = state.rows.filter((row) => validateRow(row) !== '').length;
    const success = state.rows.filter((row) => row.status === 'ok').length;
    const failed = state.rows.filter((row) => row.status === 'fail').length;

    refs.summaryBar.innerHTML = [
      `<div class="pcp-summary-item"><span>预览行数</span><strong>${total}</strong></div>`,
      `<div class="pcp-summary-item"><span>待修正</span><strong>${invalid}</strong></div>`,
      `<div class="pcp-summary-item"><span>最近成功</span><strong>${success}</strong></div>`,
      `<div class="pcp-summary-item"><span>最近失败</span><strong>${failed}</strong></div>`
    ].join('');
  }

  function renderAll() {
    renderRows();
    renderSummary();
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

    return {
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
      if (!result || !result.ok || !result.row) {
        throw new Error(result && result.error ? result.error : 'capture_failed');
      }
      const row = normalizeCapturedRow(result.row);
      if (!row.source_page && tab.url) {
        row.source_page = String(tab.url);
      }
      state.rows.unshift(row);
      renderAll();
      setStatus('抓取成功，已加入预览列表', 'success');
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

    refs.submitBtn.addEventListener('click', () => {
      submitRows();
    });

    refs.rowsBody.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="delete-row"]');
      if (!button) return;
      const tr = event.target.closest('tr[data-id]');
      if (!tr) return;
      const id = Number(tr.getAttribute('data-id') || 0);
      state.rows = state.rows.filter((row) => Number(row.id) !== id);
      renderAll();
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
  }

  async function init() {
    bindEvents();
    await loadConfig();
    renderAll();
    setConnected(false);
    setStatus('请先填写 API Base 与 Token，然后点击“连接并拉取配置”', '');
  }

  init().catch((err) => {
    setStatus(`初始化失败：${err && err.message ? err.message : 'unknown'}`, 'error');
  });
})();
