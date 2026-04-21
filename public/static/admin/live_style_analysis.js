(function () {
  'use strict';

  if (!window.Vue || !window.ElementPlus) {
    return;
  }

  var createApp = Vue.createApp;
  var reactive = Vue.reactive;
  var ref = Vue.ref;
  var computed = Vue.computed;
  var watch = Vue.watch;
  var onMounted = Vue.onMounted;
  var ElMessage = ElementPlus.ElMessage;
  var ElMessageBox = ElementPlus.ElMessageBox;

  var i18n = window.AppI18n;
  var lang = i18n ? i18n.getLang('zh') : 'zh';
  function tt(key, vars) {
    return i18n ? i18n.t(key, vars || null, lang) : key;
  }
  function tx(key, fallback, vars) {
    var out = tt(key, vars);
    if (!fallback) return out;
    if (!out || out === key) return fallback;
    return out;
  }

  var headers = {
    Accept: 'application/json, text/javascript, */*;q=0.01',
    'X-Requested-With': 'XMLHttpRequest',
  };

  function parseJson(text) {
    if (typeof text !== 'string') {
      return null;
    }
    var raw = text;
    if (raw.charCodeAt(0) === 0xfeff) {
      raw = raw.slice(1);
    }
    raw = raw.trim();
    if (!raw) {
      return null;
    }
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function resolveApiMessage(json, fallback) {
    if (json && json.error_key) {
      var i18nMsg = tt(String(json.error_key));
      if (i18nMsg && i18nMsg !== String(json.error_key)) {
        return i18nMsg;
      }
    }
    if (json && json.data && typeof json.data.message === 'string' && json.data.message) {
      return String(json.data.message);
    }
    if (json && typeof json.msg === 'string' && json.msg) {
      var msgKey = String(json.msg);
      var maybe = tt(msgKey);
      if (maybe && maybe !== msgKey) return maybe;
      if (msgKey !== 'ok') return msgKey;
    }
    return fallback || tx('common.operationFailed', '操作失败');
  }

  async function requestJson(url, options, fallback) {
    var res = await fetch(url, options || { credentials: 'same-origin', headers: headers });
    if (!res.ok && res.status === 413) {
      throw new Error('上传文件过大（HTTP 413），请使用分片导入或调大服务器上传限制');
    }
    var text = await res.text();
    var json = parseJson(text);
    if (!json || json.code !== 0) {
      throw new Error(resolveApiMessage(json, fallback));
    }
    return json.data || {};
  }

  async function apiGet(url, params, fallback) {
    var qs = '';
    if (params) {
      var p = new URLSearchParams();
      Object.keys(params).forEach(function (k) {
        var v = params[k];
        if (v === null || v === undefined || v === '') return;
        p.set(k, String(v));
      });
      qs = p.toString();
    }
    var full = qs ? (url + '?' + qs) : url;
    return requestJson(full, { credentials: 'same-origin', headers: headers }, fallback);
  }

  async function apiPostJson(url, payload, fallback) {
    return requestJson(
      url,
      {
        method: 'POST',
        credentials: 'same-origin',
        headers: Object.assign({}, headers, { 'Content-Type': 'application/json; charset=UTF-8' }),
        body: JSON.stringify(payload || {}),
      },
      fallback
    );
  }

  async function apiUpload(url, formData, fallback) {
    return requestJson(
      url,
      {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: formData,
      },
      fallback
    );
  }

  function fmtPercent(v, digits) {
    var d = typeof digits === 'number' ? digits : 2;
    var n = Number(v || 0);
    if (!isFinite(n)) return '--';
    return (n * 100).toFixed(d) + '%';
  }

  function fmtNum(v, digits) {
    var d = typeof digits === 'number' ? digits : 2;
    var n = Number(v || 0);
    if (!isFinite(n)) return '0';
    return n.toFixed(d);
  }

  function moneyDigits(currency) {
    var cur = String(currency || '').toUpperCase();
    if (cur === 'VND') return 0;
    return 2;
  }

  function fmtMoney(v, currency) {
    var n = Number(v || 0);
    if (!isFinite(n)) return '--';
    var cur = String(currency || 'CNY').toUpperCase();
    var d = moneyDigits(cur);
    try {
      return new Intl.NumberFormat('zh-CN', {
        minimumFractionDigits: d,
        maximumFractionDigits: d,
      }).format(n) + ' ' + cur;
    } catch (e) {
      return n.toFixed(d) + ' ' + cur;
    }
  }

  function fmtCny(v) {
    var n = Number(v || 0);
    if (!isFinite(n)) return '¥0.00';
    try {
      return '¥' + new Intl.NumberFormat('zh-CN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(n);
    } catch (e) {
      return '¥' + n.toFixed(2);
    }
  }

  function currencyLabel(code) {
    var cur = String(code || 'CNY').toUpperCase();
    if (cur === 'USD') return '美元 (USD)';
    if (cur === 'VND') return '越南盾 (VND)';
    if (cur === 'MIXED') return '混合币种';
    return '人民币 (CNY)';
  }

  function fxStatusLabel(status) {
    var s = String(status || '').toLowerCase();
    if (s === 'exact') return '实时汇率';
    if (s === 'fallback_latest') return '最近汇率回退';
    if (s === 'identity') return '同币种';
    return '汇率缺失';
  }

  var storeCurrencyOptions = ['VND', 'USD', 'CNY'];

  createApp({
    setup: function () {
      var CATALOG_CHUNK_SIZE = 512 * 1024;
      var activeTab = ref('store_manage');
      var stores = ref([]);
      var selectedStoreId = ref(0);
      var storesLoading = ref(false);
      var storeManager = reactive({
        loading: false,
        keyword: '',
        status: '',
        items: [],
      });
      var storeDialogVisible = ref(false);
      var storeSaving = ref(false);
      var storeLoadErrorNotified = false;
      var storeForm = reactive({
        id: 0,
        store_name: '',
        store_code: '',
        default_gmv_currency: 'VND',
        status: 1,
        notes: '',
      });

      var catalog = reactive({
        loading: false,
        keyword: '',
        status: '',
        page: 1,
        pageSize: 20,
        total: 0,
        items: [],
      });
      var catalogImporting = ref(false);
      var catalogUploadProgress = ref(0);
      var catalogFileInput = ref(null);
      var catalogSelection = ref([]);
      var catalogDialogVisible = ref(false);
      var catalogSaving = ref(false);
      var catalogForm = reactive({
        id: 0,
        style_code: '',
        product_name: '',
        image_url: '',
        status: 1,
        notes: '',
      });

      var liveForm = reactive({
        session_date: '',
        session_name: '',
      });
      var liveImporting = ref(false);
      var liveFileInput = ref(null);

      var sessions = reactive({
        loading: false,
        date_from: '',
        date_to: '',
        page: 1,
        pageSize: 10,
        total: 0,
        items: [],
      });
      var sessionSelection = ref([]);
      var sessionDialogVisible = ref(false);
      var sessionSaving = ref(false);
      var sessionForm = reactive({
        id: 0,
        session_date: '',
        session_name: '',
      });

      var unmatched = reactive({
        loading: false,
        keyword: '',
        session_id: 0,
        page: 1,
        pageSize: 20,
        total: 0,
        items: [],
      });
      var unmatchedSelection = ref([]);
      var bindStyleCode = ref('');
      var bindLoading = ref(false);

      var ranking = reactive({
        loading: false,
        scope: 'store',
        window_type: 'd7',
        anchor_date: '',
        anchor_date_resolved: '',
        anchor_session_id: 0,
        session_id: 0,
        page: 1,
        pageSize: 20,
        total: 0,
        items: [],
        summary: {
          big_hit: 0,
          best_seller: 0,
          small_hit: 0,
        },
      });

      var detailVisible = ref(false);
      var detailLoading = ref(false);
      var detailImageLoading = ref(false);
      var detailImageInput = ref(null);
      var detail = reactive({
        style_code: '',
        image_store_id: 0,
        image_url_input: '',
        error_message: '',
        summary: {
          product_count: 0,
          session_count: 0,
          gmv_sum: 0,
          gmv_cny_sum: 0,
          impressions_sum: 0,
          clicks_sum: 0,
          add_to_cart_sum: 0,
          orders_sum: 0,
          ctr: 0,
          add_to_cart_rate: 0,
        },
        currency: {
          gmv_currency: 'VND',
          gmv_currency_label: '越南盾 (VND)',
          base_currency: 'CNY',
          fx_status: 'exact',
        },
        trend: [],
        catalog_items: [],
      });

      var sessionOptions = computed(function () {
        return sessions.items.map(function (s) {
          return {
            value: Number(s.id || 0),
            label: String(s.session_date || '') + ' · ' + String(s.session_name || ''),
          };
        });
      });
      var detailTitle = computed(function () {
        return '款式详情：' + String(detail.style_code || '');
      });

      function tierLabel(tier) {
        if (tier === 'big_hit') return '大爆款';
        if (tier === 'best_seller') return '畅销款';
        if (tier === 'small_hit') return '小爆款';
        return '观察款';
      }

      function tierType(tier) {
        if (tier === 'big_hit') return 'danger';
        if (tier === 'best_seller') return 'warning';
        if (tier === 'small_hit') return 'success';
        return 'info';
      }

      function ensureStore() {
        if (!selectedStoreId.value) {
          ElMessage.warning('请先选择店铺');
          return false;
        }
        return true;
      }

      function fillStoreForm(row) {
        storeForm.id = Number((row && row.id) || 0);
        storeForm.store_name = String((row && row.store_name) || '');
        storeForm.store_code = String((row && row.store_code) || '');
        storeForm.default_gmv_currency = String((row && (row.default_gmv_currency || row.gmv_currency)) || 'VND').toUpperCase();
        storeForm.status = Number((row && row.status) || 1) === 0 ? 0 : 1;
        storeForm.notes = String((row && row.notes) || '');
      }

      function resetStoreForm() {
        fillStoreForm({ id: 0, status: 1 });
      }

      function fillCatalogForm(row) {
        catalogForm.id = Number((row && row.id) || 0);
        catalogForm.style_code = String((row && row.style_code) || '');
        catalogForm.product_name = String((row && row.product_name) || '');
        catalogForm.image_url = String((row && row.image_url) || '');
        catalogForm.status = Number((row && row.status) || 1) === 0 ? 0 : 1;
        catalogForm.notes = String((row && row.notes) || '');
      }

      function resetCatalogForm() {
        fillCatalogForm({ id: 0, status: 1 });
      }

      function fillSessionForm(row) {
        sessionForm.id = Number((row && row.id) || 0);
        sessionForm.session_date = String((row && row.session_date) || '');
        sessionForm.session_name = String((row && row.session_name) || '');
      }

      function resetSessionForm() {
        fillSessionForm({ id: 0, session_date: '', session_name: '' });
      }

      function openStoreCreate() {
        resetStoreForm();
        storeDialogVisible.value = true;
      }

      function openStoreEdit(row) {
        fillStoreForm(row || {});
        storeDialogVisible.value = true;
      }

      function openCatalogCreate() {
        if (!ensureStore()) return;
        resetCatalogForm();
        catalogDialogVisible.value = true;
      }

      function openCatalogEdit(row) {
        fillCatalogForm(row || {});
        catalogDialogVisible.value = true;
      }

      function openSessionEdit(row) {
        fillSessionForm(row || {});
        sessionDialogVisible.value = true;
      }

      function onCatalogSelectionChange(rows) {
        catalogSelection.value = Array.isArray(rows) ? rows : [];
      }

      function onSessionSelectionChange(rows) {
        sessionSelection.value = Array.isArray(rows) ? rows : [];
      }

      function showStoreLoadError(message) {
        if (storeLoadErrorNotified) return;
        storeLoadErrorNotified = true;
        ElMessage.error(message || '加载店铺失败');
        window.setTimeout(function () {
          storeLoadErrorNotified = false;
        }, 1200);
      }

      async function fetchStoreManager(silent) {
        storeManager.loading = true;
        try {
          var data = await apiGet(
            '/admin.php/product_search/live/store/list',
            {
              keyword: storeManager.keyword,
              status: storeManager.status,
            },
            '加载店铺列表失败'
          );
          storeManager.items = Array.isArray(data.items) ? data.items : [];
        } catch (e) {
          var msg = e.message || '加载店铺列表失败';
          if (silent) {
            showStoreLoadError(msg);
          } else {
            ElMessage.error(msg);
          }
        } finally {
          storeManager.loading = false;
        }
      }

      async function fetchStores(preferredStoreId, silent) {
        storesLoading.value = true;
        try {
          var data = await apiGet('/admin.php/product_search/live/store/list', { status: 1 }, '加载店铺失败');
          stores.value = Array.isArray(data.items) ? data.items : [];
          var current = Number(preferredStoreId || selectedStoreId.value || 0);
          var hasCurrent = current > 0 && stores.value.some(function (s) { return Number(s.id || 0) === current; });
          if (hasCurrent) {
            selectedStoreId.value = current;
          } else if (stores.value.length > 0) {
            selectedStoreId.value = Number(stores.value[0].id || 0);
          } else {
            selectedStoreId.value = 0;
          }
        } catch (e) {
          var msg = e.message || '加载店铺失败';
          if (silent) {
            showStoreLoadError(msg);
          } else {
            ElMessage.error(msg);
          }
        } finally {
          storesLoading.value = false;
        }
      }

      async function saveStore() {
        if (!storeForm.store_name) {
          ElMessage.warning('请填写店铺名称');
          return;
        }
        storeSaving.value = true;
        try {
          var data = await apiPostJson(
            '/admin.php/product_search/live/store/save',
            {
              id: Number(storeForm.id || 0),
              store_name: storeForm.store_name,
              store_code: storeForm.store_code,
              default_gmv_currency: String(storeForm.default_gmv_currency || 'VND').toUpperCase(),
              status: Number(storeForm.status || 0) === 0 ? 0 : 1,
              notes: storeForm.notes,
            },
            '店铺保存失败'
          );
          var item = data.item || {};
          var keepId = Number(item.id || 0);
          ElMessage.success('店铺保存成功');
          storeDialogVisible.value = false;
          await fetchStoreManager();
          await fetchStores(keepId > 0 ? keepId : undefined, true);
          await onStoreChange();
        } catch (e) {
          ElMessage.error(e.message || '店铺保存失败');
        } finally {
          storeSaving.value = false;
        }
      }

      async function deleteStore(row) {
        var id = Number((row && row.id) || 0);
        if (id <= 0) return;
        try {
          await ElMessageBox.confirm('删除后不可恢复，确认删除该店铺吗？', '提示', { type: 'warning' });
          await apiPostJson('/admin.php/product_search/live/store/delete', { id: id }, '删除店铺失败');
          ElMessage.success('店铺已删除');
          if (Number(storeForm.id || 0) === id) {
            resetStoreForm();
          }
          var keepId = Number(selectedStoreId.value || 0);
          if (keepId === id) {
            keepId = 0;
          }
          await fetchStoreManager();
          await fetchStores(keepId, true);
          await onStoreChange();
        } catch (e) {
          if (e !== 'cancel' && e !== 'close') {
            ElMessage.error((e && e.message) || '删除店铺失败');
          }
        }
      }

      async function fetchCatalog() {
        if (!selectedStoreId.value) {
          catalog.items = [];
          catalog.total = 0;
          catalogSelection.value = [];
          return;
        }
        catalog.loading = true;
        try {
          var data = await apiGet(
            '/admin.php/product_search/live/catalog/list',
            {
              store_id: selectedStoreId.value,
              keyword: catalog.keyword,
              status: catalog.status,
              page: catalog.page,
              page_size: catalog.pageSize,
            },
            '加载商品库失败'
          );
          catalog.items = Array.isArray(data.items) ? data.items : [];
          catalog.total = Number(data.total || 0);
          catalogSelection.value = [];
        } catch (e) {
          ElMessage.error(e.message || '加载商品库失败');
        } finally {
          catalog.loading = false;
        }
      }

      async function saveCatalog() {
        if (!ensureStore()) return;
        if (!catalogForm.style_code) {
          ElMessage.warning('请填写款式编号');
          return;
        }
        catalogSaving.value = true;
        try {
          await apiPostJson(
            '/admin.php/product_search/live/catalog/save',
            {
              id: Number(catalogForm.id || 0),
              store_id: Number(selectedStoreId.value || 0),
              style_code: catalogForm.style_code,
              product_name: catalogForm.product_name,
              image_url: catalogForm.image_url,
              status: Number(catalogForm.status || 0) === 0 ? 0 : 1,
              notes: catalogForm.notes,
            },
            '商品保存失败'
          );
          ElMessage.success('商品保存成功');
          catalogDialogVisible.value = false;
          catalog.page = 1;
          await fetchCatalog();
          await fetchRankings();
        } catch (e) {
          ElMessage.error(e.message || '商品保存失败');
        } finally {
          catalogSaving.value = false;
        }
      }

      async function deleteCatalog(row) {
        if (!ensureStore()) return;
        var id = Number((row && row.id) || 0);
        if (id <= 0) return;
        try {
          await ElMessageBox.confirm('删除后不可恢复，确认删除该商品吗？', '提示', { type: 'warning' });
          await apiPostJson(
            '/admin.php/product_search/live/catalog/delete',
            { id: id, store_id: Number(selectedStoreId.value || 0) },
            '删除商品失败'
          );
          ElMessage.success('商品已删除');
          catalog.page = 1;
          await fetchCatalog();
          await fetchRankings();
        } catch (e) {
          if (e !== 'cancel' && e !== 'close') {
            ElMessage.error((e && e.message) || '删除商品失败');
          }
        }
      }

      async function batchDeleteCatalog() {
        if (!ensureStore()) return;
        var ids = catalogSelection.value.map(function (r) { return Number(r.id || 0); }).filter(function (v) { return v > 0; });
        if (ids.length === 0) {
          ElMessage.warning('请先勾选要删除的商品');
          return;
        }
        try {
          await ElMessageBox.confirm('将删除选中的 ' + ids.length + ' 条商品记录，确认继续吗？', '提示', { type: 'warning' });
          var data = await apiPostJson(
            '/admin.php/product_search/live/catalog/batchDelete',
            { store_id: Number(selectedStoreId.value || 0), ids: ids },
            '批量删除失败'
          );
          ElMessage.success('已删除 ' + Number((data && data.deleted) || 0) + ' 条');
          catalog.page = 1;
          await fetchCatalog();
          await fetchRankings();
        } catch (e) {
          if (e !== 'cancel' && e !== 'close') {
            ElMessage.error((e && e.message) || '批量删除失败');
          }
        }
      }

      function openCatalogFile() {
        if (!ensureStore()) return;
        if (catalogFileInput.value && catalogFileInput.value.click) {
          catalogFileInput.value.click();
        }
      }

      function makeCatalogUploadId(file) {
        var now = Date.now().toString(36);
        var rand = Math.random().toString(36).slice(2, 10);
        var size = String((file && file.size) || 0);
        var stamp = String((file && file.lastModified) || 0);
        return ('cat_' + now + '_' + rand + '_' + size + '_' + stamp).replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 64);
      }

      async function uploadCatalogInChunks(file) {
        var size = Number((file && file.size) || 0);
        if (!file || size <= 0) {
          throw new Error('文件为空');
        }
        var totalChunks = Math.max(1, Math.ceil(size / CATALOG_CHUNK_SIZE));
        var uploadId = makeCatalogUploadId(file);
        var finalData = null;
        catalogUploadProgress.value = 0;
        for (var i = 0; i < totalChunks; i++) {
          var start = i * CATALOG_CHUNK_SIZE;
          var end = Math.min(size, start + CATALOG_CHUNK_SIZE);
          var fd = new FormData();
          fd.append('store_id', String(selectedStoreId.value));
          fd.append('upload_id', uploadId);
          fd.append('file_name', String(file.name || 'catalog.xlsx'));
          fd.append('chunk_index', String(i));
          fd.append('total_chunks', String(totalChunks));
          fd.append('chunk', file.slice(start, end), String(file.name || 'catalog.xlsx'));
          var chunkData = await apiUpload('/admin.php/product_search/live/catalog/importChunk', fd, '商品库导入失败');
          if (chunkData && chunkData.completed) {
            finalData = chunkData;
          }
          catalogUploadProgress.value = Math.round(((i + 1) * 100) / totalChunks);
        }
        if (!finalData || !finalData.result) {
          throw new Error('导入结果未返回，请重试');
        }
        return finalData;
      }

      async function onCatalogFileChange(evt) {
        var file = evt && evt.target && evt.target.files ? evt.target.files[0] : null;
        if (!file) return;
        if (!ensureStore()) {
          evt.target.value = '';
          return;
        }
        catalogImporting.value = true;
        catalogUploadProgress.value = 0;
        try {
          var data = await uploadCatalogInChunks(file);
          var result = data.result || {};
          ElMessage.success(
            '导入完成：新增 ' +
              Number(result.inserted || 0) +
              '，更新 ' +
              Number(result.updated || 0) +
              '，跳过 ' +
              Number(result.skipped || 0)
          );
          catalog.page = 1;
          await fetchCatalog();
          await fetchRankings();
        } catch (e) {
          ElMessage.error(e.message || '商品库导入失败');
        } finally {
          catalogImporting.value = false;
          catalogUploadProgress.value = 0;
          evt.target.value = '';
        }
      }

      async function fetchSessions() {
        if (!selectedStoreId.value) {
          sessions.items = [];
          sessions.total = 0;
          sessionSelection.value = [];
          return;
        }
        sessions.loading = true;
        try {
          var data = await apiGet(
            '/admin.php/product_search/live/sessions',
            {
              store_id: selectedStoreId.value,
              date_from: sessions.date_from,
              date_to: sessions.date_to,
              page: sessions.page,
              page_size: sessions.pageSize,
            },
            '加载场次失败'
          );
          sessions.items = Array.isArray(data.items) ? data.items : [];
          sessions.total = Number(data.total || 0);
          sessionSelection.value = [];
        } catch (e) {
          ElMessage.error(e.message || '加载场次失败');
        } finally {
          sessions.loading = false;
        }
      }

      async function saveSession() {
        if (!ensureStore()) return;
        if (!sessionForm.id) {
          ElMessage.warning('请选择要编辑的场次');
          return;
        }
        if (!sessionForm.session_date) {
          ElMessage.warning('请填写直播日期');
          return;
        }
        sessionSaving.value = true;
        try {
          await apiPostJson(
            '/admin.php/product_search/live/session/save',
            {
              id: Number(sessionForm.id || 0),
              store_id: Number(selectedStoreId.value || 0),
              session_date: String(sessionForm.session_date || ''),
              session_name: String(sessionForm.session_name || ''),
            },
            '场次保存失败'
          );
          ElMessage.success('场次保存成功');
          sessionDialogVisible.value = false;
          sessions.page = 1;
          await fetchSessions();
          await fetchUnmatched();
          await fetchRankings();
        } catch (e) {
          ElMessage.error(e.message || '场次保存失败');
        } finally {
          sessionSaving.value = false;
        }
      }

      async function deleteSession(row) {
        if (!ensureStore()) return;
        var id = Number((row && row.id) || 0);
        if (id <= 0) return;
        try {
          await ElMessageBox.confirm('删除场次会同时删除该场次全部指标明细，确认继续吗？', '提示', { type: 'warning' });
          await apiPostJson(
            '/admin.php/product_search/live/session/delete',
            { id: id, store_id: Number(selectedStoreId.value || 0) },
            '删除场次失败'
          );
          ElMessage.success('场次已删除');
          sessions.page = 1;
          await fetchSessions();
          await fetchUnmatched();
          await fetchRankings();
        } catch (e) {
          if (e !== 'cancel' && e !== 'close') {
            ElMessage.error((e && e.message) || '删除场次失败');
          }
        }
      }

      async function batchDeleteSessions() {
        if (!ensureStore()) return;
        var ids = sessionSelection.value.map(function (r) { return Number(r.id || 0); }).filter(function (v) { return v > 0; });
        if (ids.length === 0) {
          ElMessage.warning('请先勾选要删除的场次');
          return;
        }
        try {
          await ElMessageBox.confirm('将删除选中的 ' + ids.length + ' 个场次及其指标明细，确认继续吗？', '提示', { type: 'warning' });
          var data = await apiPostJson(
            '/admin.php/product_search/live/session/batchDelete',
            { store_id: Number(selectedStoreId.value || 0), ids: ids },
            '批量删除场次失败'
          );
          ElMessage.success('已删除场次 ' + Number((data && data.deleted_sessions) || 0) + ' 个');
          sessions.page = 1;
          await fetchSessions();
          await fetchUnmatched();
          await fetchRankings();
        } catch (e) {
          if (e !== 'cancel' && e !== 'close') {
            ElMessage.error((e && e.message) || '批量删除场次失败');
          }
        }
      }

      function openLiveFile() {
        if (!ensureStore()) return;
        if (!liveForm.session_date) {
          ElMessage.warning('请先选择直播日期');
          return;
        }
        if (liveFileInput.value && liveFileInput.value.click) {
          liveFileInput.value.click();
        }
      }

      async function onLiveFileChange(evt) {
        var file = evt && evt.target && evt.target.files ? evt.target.files[0] : null;
        if (!file) return;
        if (!ensureStore()) {
          evt.target.value = '';
          return;
        }
        if (!liveForm.session_date) {
          ElMessage.warning('请先选择直播日期');
          evt.target.value = '';
          return;
        }
        var fd = new FormData();
        fd.append('store_id', String(selectedStoreId.value));
        fd.append('session_date', String(liveForm.session_date || ''));
        fd.append('session_name', String(liveForm.session_name || ''));
        fd.append('file', file);
        liveImporting.value = true;
        try {
          var data = await apiUpload('/admin.php/product_search/live/import/create', fd, '直播数据导入失败');
          var result = data.result || {};
          ElMessage.success(
            '导入完成：新增 ' +
              Number(result.inserted || 0) +
              '，更新 ' +
              Number(result.updated || 0) +
              '，删除 ' +
              Number(result.deleted || 0) +
              '，匹配 ' +
              Number(result.matched || 0) +
              '，未匹配 ' +
              Number(result.unmatched || 0)
          );
          sessions.page = 1;
          unmatched.page = 1;
          ranking.page = 1;
          await fetchSessions();
          await fetchUnmatched();
          await fetchRankings();
        } catch (e) {
          ElMessage.error(e.message || '直播数据导入失败');
        } finally {
          liveImporting.value = false;
          evt.target.value = '';
        }
      }

      async function fetchUnmatched() {
        if (!selectedStoreId.value) {
          unmatched.items = [];
          unmatched.total = 0;
          return;
        }
        unmatched.loading = true;
        try {
          var data = await apiGet(
            '/admin.php/product_search/live/unmatched',
            {
              store_id: selectedStoreId.value,
              session_id: unmatched.session_id || '',
              keyword: unmatched.keyword,
              page: unmatched.page,
              page_size: unmatched.pageSize,
            },
            '加载未匹配数据失败'
          );
          unmatched.items = Array.isArray(data.items) ? data.items : [];
          unmatched.total = Number(data.total || 0);
        } catch (e) {
          ElMessage.error(e.message || '加载未匹配数据失败');
        } finally {
          unmatched.loading = false;
        }
      }

      function onUnmatchedSelectionChange(rows) {
        unmatchedSelection.value = Array.isArray(rows) ? rows : [];
      }

      async function bindUnmatched() {
        if (!ensureStore()) return;
        var ids = unmatchedSelection.value.map(function (r) { return Number(r.id || 0); }).filter(function (v) { return v > 0; });
        if (ids.length === 0) {
          ElMessage.warning('请先勾选未匹配商品');
          return;
        }
        if (!bindStyleCode.value) {
          ElMessage.warning('请输入款式编号');
          return;
        }
        bindLoading.value = true;
        try {
          var data = await apiPostJson(
            '/admin.php/product_search/live/unmatched/bind',
            {
              store_id: selectedStoreId.value,
              style_code: bindStyleCode.value,
              metric_ids: ids,
            },
            '绑定失败'
          );
          ElMessage.success('绑定完成：更新 ' + Number(data.updated || 0) + ' 条');
          unmatchedSelection.value = [];
          bindStyleCode.value = '';
          await fetchUnmatched();
          await fetchSessions();
          await fetchRankings();
        } catch (e) {
          ElMessage.error(e.message || '绑定失败');
        } finally {
          bindLoading.value = false;
        }
      }

      async function fetchRankings() {
        if (ranking.scope === 'store' && !selectedStoreId.value) {
          ranking.items = [];
          ranking.total = 0;
          return;
        }
        ranking.loading = true;
        try {
          var data = await apiGet(
            '/admin.php/product_search/live/rankings',
            {
              scope: ranking.scope,
              store_id: ranking.scope === 'store' ? selectedStoreId.value : '',
              window_type: ranking.window_type,
              anchor_date: ranking.anchor_date,
              session_id: ranking.window_type === 'session' ? ranking.session_id : '',
              page: ranking.page,
              page_size: ranking.pageSize,
            },
            '加载榜单失败'
          );
          ranking.items = Array.isArray(data.items) ? data.items : [];
          ranking.total = Number(data.total || 0);
          ranking.anchor_date_resolved = String(data.anchor_date || '');
          ranking.anchor_session_id = Number(data.anchor_session_id || 0);
          ranking.summary = Object.assign(
            { big_hit: 0, best_seller: 0, small_hit: 0 },
            data.summary || {}
          );
        } catch (e) {
          ElMessage.error(e.message || '加载榜单失败');
        } finally {
          ranking.loading = false;
        }
      }

      async function openStyleDetail(row) {
        var styleCode = row && row.style_code ? String(row.style_code) : '';
        if (!styleCode) return;
        var detailStoreId = ranking.scope === 'store' ? (selectedStoreId.value || '') : '';
        var detailStore = stores.value.find(function (s) {
          return Number(s.id || 0) === Number(detailStoreId || 0);
        }) || null;
        var defaultCurrency = detailStore && detailStore.gmv_currency ? String(detailStore.gmv_currency) : 'VND';
        detailVisible.value = true;
        detailLoading.value = true;
        detail.style_code = styleCode;
        detail.image_store_id = detailStoreId ? Number(detailStoreId) : 0;
        detail.image_url_input = '';
        detail.error_message = '';
        detail.currency = {
          gmv_currency: defaultCurrency,
          gmv_currency_label: currencyLabel(defaultCurrency),
          base_currency: 'CNY',
          fx_status: defaultCurrency === 'CNY' ? 'identity' : 'exact',
        };
        if (row) {
          detail.summary = Object.assign({}, detail.summary, {
            session_count: Number(row.session_count || 0),
            gmv_sum: Number(row.gmv_sum || 0),
            gmv_cny_sum: Number(row.gmv_cny_sum || 0),
            impressions_sum: Number(row.impressions_sum || 0),
            clicks_sum: Number(row.clicks_sum || 0),
            add_to_cart_sum: Number(row.add_to_cart_sum || 0),
            orders_sum: Number(row.orders_sum || 0),
            ctr: Number(row.ctr || 0),
            add_to_cart_rate: Number(row.add_to_cart_rate || 0),
          });
        }
        try {
          var data = await apiGet(
            '/admin.php/product_search/live/styleDetail',
            { store_id: detailStoreId, style_code: styleCode },
            '加载款式详情失败'
          );
          detail.summary = Object.assign({}, detail.summary, data.summary || {});
          detail.currency = Object.assign({}, detail.currency, data.currency || {});
          detail.trend = Array.isArray(data.trend) ? data.trend : [];
          detail.catalog_items = Array.isArray(data.catalog_items) ? data.catalog_items : [];
          if (!detail.image_store_id && detail.catalog_items.length > 0) {
            detail.image_store_id = Number(detail.catalog_items[0].store_id || 0);
          }
          if (detail.catalog_items.length > 0) {
            detail.image_url_input = String(detail.catalog_items[0].image_url || '');
          }
        } catch (e) {
          detail.error_message = e && e.message ? String(e.message) : '加载款式详情失败';
          ElMessage.error(detail.error_message);
        } finally {
          detailLoading.value = false;
        }
      }

      async function updateImageWithUrl() {
        if (!detail.style_code) return;
        if (!detail.image_store_id) {
          ElMessage.warning('请选择店铺');
          return;
        }
        if (!detail.image_url_input) {
          ElMessage.warning('请填写图片地址');
          return;
        }
        detailImageLoading.value = true;
        try {
          var fd = new FormData();
          fd.append('store_id', String(detail.image_store_id));
          fd.append('image_url', detail.image_url_input);
          fd.append('style_code', detail.style_code);
          await apiUpload(
            '/admin.php/product_search/live/styleImageUpdate',
            fd,
            '更新图片失败'
          );
          ElMessage.success('图片已更新');
          await openStyleDetail({ style_code: detail.style_code });
          await fetchCatalog();
          await fetchRankings();
        } catch (e) {
          ElMessage.error(e.message || '更新图片失败');
        } finally {
          detailImageLoading.value = false;
        }
      }

      function triggerDetailImageFile() {
        if (detailImageInput.value && detailImageInput.value.click) {
          detailImageInput.value.click();
        }
      }

      async function onDetailImageChange(evt) {
        var file = evt && evt.target && evt.target.files ? evt.target.files[0] : null;
        if (!file) return;
        if (!detail.style_code || !detail.image_store_id) {
          ElMessage.warning('请选择款式和店铺');
          evt.target.value = '';
          return;
        }
        detailImageLoading.value = true;
        try {
          var fd = new FormData();
          fd.append('store_id', String(detail.image_store_id));
          fd.append('image', file);
          fd.append('style_code', detail.style_code);
          await apiUpload(
            '/admin.php/product_search/live/styleImageUpdate',
            fd,
            '上传图片失败'
          );
          ElMessage.success('图片已上传');
          await openStyleDetail({ style_code: detail.style_code });
          await fetchCatalog();
          await fetchRankings();
        } catch (e) {
          ElMessage.error(e.message || '上传图片失败');
        } finally {
          detailImageLoading.value = false;
          evt.target.value = '';
        }
      }

      async function onStoreChange() {
        catalog.page = 1;
        sessions.page = 1;
        unmatched.page = 1;
        ranking.page = 1;
        unmatched.session_id = 0;
        ranking.session_id = 0;
        await fetchCatalog();
        await fetchSessions();
        await fetchUnmatched();
        await fetchRankings();
      }

      async function resetRankingFilters() {
        ranking.window_type = 'd7';
        ranking.anchor_date = '';
        ranking.session_id = 0;
        ranking.page = 1;
        await fetchRankings();
      }

      onMounted(async function () {
        if (i18n) i18n.applyDom(document);
        resetStoreForm();
        resetCatalogForm();
        resetSessionForm();
        await fetchStoreManager(true);
        await fetchStores(undefined, true);
        if (selectedStoreId.value) {
          await fetchCatalog();
          await fetchSessions();
          await fetchUnmatched();
        }
        await fetchRankings();
      });

      watch(activeTab, function (tab) {
        if (tab === 'ranking') {
          ranking.page = 1;
          fetchRankings();
        }
      });

      return {
        tx: tx,
        activeTab: activeTab,
        stores: stores,
        storesLoading: storesLoading,
        selectedStoreId: selectedStoreId,
        onStoreChange: onStoreChange,
        storeManager: storeManager,
        storeDialogVisible: storeDialogVisible,
        storeSaving: storeSaving,
        storeForm: storeForm,
        fetchStoreManager: fetchStoreManager,
        openStoreCreate: openStoreCreate,
        openStoreEdit: openStoreEdit,
        saveStore: saveStore,
        deleteStore: deleteStore,
        resetStoreForm: resetStoreForm,
        storeCurrencyOptions: storeCurrencyOptions,

        catalog: catalog,
        catalogImporting: catalogImporting,
        catalogUploadProgress: catalogUploadProgress,
        catalogFileInput: catalogFileInput,
        catalogSelection: catalogSelection,
        catalogDialogVisible: catalogDialogVisible,
        catalogSaving: catalogSaving,
        catalogForm: catalogForm,
        fetchCatalog: fetchCatalog,
        saveCatalog: saveCatalog,
        deleteCatalog: deleteCatalog,
        batchDeleteCatalog: batchDeleteCatalog,
        openCatalogFile: openCatalogFile,
        onCatalogFileChange: onCatalogFileChange,
        openCatalogCreate: openCatalogCreate,
        openCatalogEdit: openCatalogEdit,
        resetCatalogForm: resetCatalogForm,
        onCatalogSelectionChange: onCatalogSelectionChange,

        liveForm: liveForm,
        liveImporting: liveImporting,
        liveFileInput: liveFileInput,
        openLiveFile: openLiveFile,
        onLiveFileChange: onLiveFileChange,
        sessions: sessions,
        sessionSelection: sessionSelection,
        sessionDialogVisible: sessionDialogVisible,
        sessionSaving: sessionSaving,
        sessionForm: sessionForm,
        fetchSessions: fetchSessions,
        onSessionSelectionChange: onSessionSelectionChange,
        openSessionEdit: openSessionEdit,
        saveSession: saveSession,
        deleteSession: deleteSession,
        batchDeleteSessions: batchDeleteSessions,
        resetSessionForm: resetSessionForm,

        unmatched: unmatched,
        unmatchedSelection: unmatchedSelection,
        bindStyleCode: bindStyleCode,
        bindLoading: bindLoading,
        onUnmatchedSelectionChange: onUnmatchedSelectionChange,
        fetchUnmatched: fetchUnmatched,
        bindUnmatched: bindUnmatched,

        ranking: ranking,
        fetchRankings: fetchRankings,
        resetRankingFilters: resetRankingFilters,
        tierLabel: tierLabel,
        tierType: tierType,
        fmtPercent: fmtPercent,
        fmtNum: fmtNum,
        fmtMoney: fmtMoney,
        fmtCny: fmtCny,
        currencyLabel: currencyLabel,
        fxStatusLabel: fxStatusLabel,
        sessionOptions: sessionOptions,
        detailTitle: detailTitle,

        detailVisible: detailVisible,
        detailLoading: detailLoading,
        detail: detail,
        openStyleDetail: openStyleDetail,
        detailImageInput: detailImageInput,
        detailImageLoading: detailImageLoading,
        updateImageWithUrl: updateImageWithUrl,
        triggerDetailImageFile: triggerDetailImageFile,
        onDetailImageChange: onDetailImageChange,
      };
    },
    template: `
<div class="admin-modern-card live-style-layout">
  <div class="live-style-toolbar">
    <el-select v-model="selectedStoreId" filterable clearable placeholder="选择店铺" :loading="storesLoading" @change="onStoreChange">
      <el-option
        v-for="s in stores"
        :key="s.id"
        :label="s.store_name + (s.store_code ? (' (' + s.store_code + ')') : '') + ' · ' + (s.default_gmv_currency || s.gmv_currency || 'VND')"
        :value="Number(s.id)"
      />
    </el-select>
    <el-button @click="activeTab='store_manage'">店铺管理</el-button>
    <span class="live-style-muted">店铺商品图片与款式编号来自店铺商品库，直播表只导入指标数据。</span>
  </div>

  <el-tabs v-model="activeTab">
    <el-tab-pane label="店铺管理" name="store_manage">
      <div class="live-style-toolbar">
        <el-input v-model="storeManager.keyword" clearable placeholder="搜索店铺名称/编码" @keyup.enter="fetchStoreManager" />
        <el-select v-model="storeManager.status" clearable placeholder="状态">
          <el-option label="启用" value="1" />
          <el-option label="停用" value="0" />
        </el-select>
        <el-button type="primary" @click="fetchStoreManager">查询</el-button>
        <el-button @click="storeManager.keyword='';storeManager.status='';fetchStoreManager()">重置</el-button>
        <el-button type="success" @click="openStoreCreate">新增店铺</el-button>
      </div>

      <el-table :data="storeManager.items" v-loading="storeManager.loading" stripe>
        <el-table-column prop="id" label="ID" width="90" />
        <el-table-column prop="store_name" label="店铺名称" min-width="180" />
        <el-table-column prop="store_code" label="店铺编码" min-width="140" />
        <el-table-column prop="default_gmv_currency" label="GMV币种" width="120">
          <template #default="scope">{{ scope.row.default_gmv_currency || scope.row.gmv_currency || 'VND' }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template #default="scope">
            <el-tag :type="Number(scope.row.status)===1?'success':'info'">{{ Number(scope.row.status)===1 ? "启用" : "停用" }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="notes" label="备注" min-width="180" />
        <el-table-column prop="updated_at" label="更新时间" width="170" />
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="scope">
            <el-button type="primary" link @click="openStoreEdit(scope.row)">编辑</el-button>
            <el-button type="danger" link @click="deleteStore(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-tab-pane>

    <el-tab-pane label="店铺商品库管理" name="catalog">
      <div class="live-style-toolbar">
        <el-input v-model="catalog.keyword" clearable placeholder="搜索款式编号/商品名" @keyup.enter="catalog.page=1;fetchCatalog()" />
        <el-select v-model="catalog.status" clearable placeholder="状态">
          <el-option label="启用" value="1" />
          <el-option label="停用" value="0" />
        </el-select>
        <el-button type="primary" @click="catalog.page=1;fetchCatalog()">查询</el-button>
        <el-button @click="catalog.keyword='';catalog.status='';catalog.page=1;fetchCatalog()">重置</el-button>
        <el-button type="primary" plain @click="openCatalogCreate">新增商品</el-button>
        <el-button type="danger" plain :disabled="catalogSelection.length===0" @click="batchDeleteCatalog">批量删除</el-button>
        <div class="live-style-file-inline">
          <el-button type="success" :loading="catalogImporting" @click="openCatalogFile">{{ catalogImporting ? ("导入中 " + catalogUploadProgress + "%") : "导入商品库文件" }}</el-button>
          <input ref="catalogFileInput" type="file" accept=".csv,.txt,.xlsx,.xls,.xlsm" style="display:none" @change="onCatalogFileChange" />
        </div>
      </div>

      <el-table :data="catalog.items" v-loading="catalog.loading" stripe @selection-change="onCatalogSelectionChange">
        <el-table-column type="selection" width="44" />
        <el-table-column prop="style_code" label="款式编号" min-width="120" />
        <el-table-column prop="product_name" label="商品名" min-width="180" />
        <el-table-column label="图片" width="110">
          <template #default="scope">
            <div class="live-style-image-box">
              <el-image
                v-if="scope.row.image_url"
                :src="scope.row.image_url"
                fit="cover"
                :preview-src-list="[scope.row.image_url]"
                :preview-teleported="true"
              />
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template #default="scope">
            <el-tag :type="Number(scope.row.status)===1?'success':'info'">{{ Number(scope.row.status)===1 ? "启用" : "停用" }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="notes" label="备注" min-width="140" />
        <el-table-column prop="updated_at" label="更新时间" width="170" />
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="scope">
            <el-button type="primary" link @click="openCatalogEdit(scope.row)">编辑</el-button>
            <el-button type="danger" link @click="deleteCatalog(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:12px;">
        <el-pagination
          v-model:current-page="catalog.page"
          v-model:page-size="catalog.pageSize"
          layout="total, prev, pager, next"
          :total="catalog.total"
          @update:current-page="fetchCatalog"
          @update:page-size="() => { catalog.page=1; fetchCatalog(); }"
        />
      </div>
    </el-tab-pane>

    <el-tab-pane label="直播场次导入" name="import">
      <div class="live-style-toolbar">
        <el-date-picker v-model="liveForm.session_date" type="date" value-format="YYYY-MM-DD" placeholder="直播日期" />
        <el-input v-model="liveForm.session_name" placeholder="场次名称（可选）" />
        <div class="live-style-file-inline">
          <el-button type="success" :loading="liveImporting" @click="openLiveFile">导入直播表</el-button>
          <input ref="liveFileInput" type="file" accept=".csv,.txt,.xlsx,.xls,.xlsm" style="display:none" @change="onLiveFileChange" />
        </div>
      </div>

      <div class="live-style-toolbar">
        <el-date-picker v-model="sessions.date_from" type="date" value-format="YYYY-MM-DD" placeholder="开始日期" />
        <el-date-picker v-model="sessions.date_to" type="date" value-format="YYYY-MM-DD" placeholder="结束日期" />
        <el-button type="primary" @click="sessions.page=1;fetchSessions()">筛选场次</el-button>
        <el-button type="danger" plain :disabled="sessionSelection.length===0" @click="batchDeleteSessions">批量删除场次</el-button>
      </div>

      <el-table :data="sessions.items" v-loading="sessions.loading" stripe @selection-change="onSessionSelectionChange">
        <el-table-column type="selection" width="44" />
        <el-table-column prop="session_date" label="直播日期" width="120" />
        <el-table-column prop="session_name" label="场次名称" min-width="160" />
        <el-table-column prop="total_rows" label="总行数" width="100" />
        <el-table-column prop="matched_rows" label="已匹配" width="100" />
        <el-table-column prop="unmatched_rows" label="未匹配" width="100" />
        <el-table-column prop="source_file" label="来源文件" min-width="180" />
        <el-table-column prop="updated_at" label="更新时间" width="170" />
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="scope">
            <el-button type="primary" link @click="openSessionEdit(scope.row)">编辑</el-button>
            <el-button type="danger" link @click="deleteSession(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:12px;">
        <el-pagination
          v-model:current-page="sessions.page"
          v-model:page-size="sessions.pageSize"
          layout="total, prev, pager, next"
          :total="sessions.total"
          @update:current-page="fetchSessions"
          @update:page-size="() => { sessions.page=1; fetchSessions(); }"
        />
      </div>
    </el-tab-pane>

    <el-tab-pane label="未匹配处理" name="unmatched">
      <div class="live-style-toolbar">
        <el-select v-model="unmatched.session_id" clearable filterable placeholder="按场次筛选">
          <el-option v-for="op in sessionOptions" :key="op.value" :label="op.label" :value="op.value" />
        </el-select>
        <el-input v-model="unmatched.keyword" clearable placeholder="搜索商品标题/ID" @keyup.enter="unmatched.page=1;fetchUnmatched()" />
        <el-button type="primary" @click="unmatched.page=1;fetchUnmatched()">查询</el-button>
      </div>

      <div class="live-style-toolbar">
        <el-input v-model="bindStyleCode" placeholder="输入要绑定的款式编号（如 A-21）" />
        <el-button type="success" :loading="bindLoading" @click="bindUnmatched">绑定并回算</el-button>
      </div>

      <el-table :data="unmatched.items" v-loading="unmatched.loading" stripe @selection-change="onUnmatchedSelectionChange">
        <el-table-column type="selection" width="44" />
        <el-table-column prop="session_date" label="日期" width="120" />
        <el-table-column prop="session_name" label="场次" min-width="140" />
        <el-table-column prop="product_id" label="商品ID" width="140" />
        <el-table-column prop="product_name" label="商品标题" min-width="220" />
        <el-table-column prop="extracted_style_code" label="提取款号" width="120" />
        <el-table-column prop="gmv" label="GMV" width="100" />
        <el-table-column label="点击率" width="100">
          <template #default="scope">{{ fmtPercent(scope.row.ctr, 2) }}</template>
        </el-table-column>
        <el-table-column label="加购率" width="100">
          <template #default="scope">{{ fmtPercent(scope.row.add_to_cart_rate, 2) }}</template>
        </el-table-column>
        <el-table-column label="支付转化率" width="120">
          <template #default="scope">{{ fmtPercent(scope.row.pay_cvr, 2) }}</template>
        </el-table-column>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:12px;">
        <el-pagination
          v-model:current-page="unmatched.page"
          v-model:page-size="unmatched.pageSize"
          layout="total, prev, pager, next"
          :total="unmatched.total"
          @update:current-page="fetchUnmatched"
          @update:page-size="() => { unmatched.page=1; fetchUnmatched(); }"
        />
      </div>
    </el-tab-pane>

    <el-tab-pane label="爆款榜" name="ranking">
      <div class="live-style-ranking-toolbar">
        <div class="live-style-toolbar live-style-toolbar--compact live-style-toolbar--no-margin">
          <el-select v-model="ranking.scope" @change="ranking.page=1;fetchRankings()">
            <el-option label="店铺榜" value="store" />
            <el-option label="全局榜" value="global" />
          </el-select>
          <el-select v-model="ranking.window_type" @change="ranking.page=1;fetchRankings()">
            <el-option label="场次窗口" value="session" />
            <el-option label="近7天" value="d7" />
            <el-option label="近30天" value="d30" />
            <el-option label="全历史" value="all" />
          </el-select>
          <el-date-picker v-model="ranking.anchor_date" type="date" value-format="YYYY-MM-DD" placeholder="锚点日期（可选）" />
          <el-select v-if="ranking.window_type==='session'" v-model="ranking.session_id" clearable filterable placeholder="锚点场次">
            <el-option v-for="op in sessionOptions" :key="op.value" :label="op.label" :value="op.value" />
          </el-select>
          <el-button type="primary" @click="ranking.page=1;fetchRankings()">查询榜单</el-button>
          <el-button @click="resetRankingFilters">重置</el-button>
        </div>
      </div>

      <div class="live-style-summary-grid">
        <div class="live-style-summary-card">
          <div class="label">大爆款</div>
          <div class="value">{{ Number(ranking.summary.big_hit || 0) }}</div>
          <div class="sub">综合分位 >= P90</div>
        </div>
        <div class="live-style-summary-card">
          <div class="label">畅销款</div>
          <div class="value">{{ Number(ranking.summary.best_seller || 0) }}</div>
          <div class="sub">P70 ~ P90</div>
        </div>
        <div class="live-style-summary-card">
          <div class="label">小爆款</div>
          <div class="value">{{ Number(ranking.summary.small_hit || 0) }}</div>
          <div class="sub">P50 ~ P70</div>
        </div>
        <div class="live-style-summary-card">
          <div class="label">锚点</div>
          <div class="value live-style-value-text">{{ ranking.anchor_date_resolved || "--" }}</div>
          <div class="sub">场次ID: {{ ranking.anchor_session_id || 0 }}</div>
        </div>
      </div>

      <div class="live-style-table-wrap">
        <el-table class="live-style-ranking-table" :data="ranking.items" v-loading="ranking.loading" stripe>
          <el-table-column prop="ranking" label="排名" width="84" align="center" />
          <el-table-column prop="style_code" label="款式编号" width="140" show-overflow-tooltip />
          <el-table-column label="图片" width="110">
            <template #default="scope">
              <div class="live-style-image-box">
                <el-image
                  v-if="scope.row.image_url"
                  :src="scope.row.image_url"
                  fit="cover"
                  :preview-src-list="[scope.row.image_url]"
                  :preview-teleported="true"
                />
              </div>
            </template>
          </el-table-column>
          <el-table-column label="分层" width="110">
            <template #default="scope">
              <el-tag class="live-style-tier-tag" :type="tierType(scope.row.tier)">{{ tierLabel(scope.row.tier) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="score" label="综合分" width="110" align="right">
            <template #default="scope">{{ fmtNum(scope.row.score, 2) }}</template>
          </el-table-column>
          <el-table-column prop="gmv_sum" label="GMV" width="130" align="right">
            <template #default="scope">{{ fmtNum(scope.row.gmv_sum, 2) }}</template>
          </el-table-column>
          <el-table-column label="CTR" width="110" align="right">
            <template #default="scope">{{ fmtPercent(scope.row.ctr, 2) }}</template>
          </el-table-column>
          <el-table-column label="加购率" width="110" align="right">
            <template #default="scope">{{ fmtPercent(scope.row.add_to_cart_rate, 2) }}</template>
          </el-table-column>
          <el-table-column label="支付转化率" width="120" align="right">
            <template #default="scope">{{ fmtPercent(scope.row.pay_cvr, 2) }}</template>
          </el-table-column>
          <el-table-column prop="session_count" label="场次数" width="92" align="right" />
          <el-table-column label="操作" width="100">
            <template #default="scope">
              <el-button type="primary" link @click="openStyleDetail(scope.row)">详情</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div style="display:flex;justify-content:flex-end;margin-top:12px;">
        <el-pagination
          v-model:current-page="ranking.page"
          v-model:page-size="ranking.pageSize"
          layout="total, prev, pager, next"
          :total="ranking.total"
          @update:current-page="fetchRankings"
          @update:page-size="() => { ranking.page=1; fetchRankings(); }"
        />
      </div>
    </el-tab-pane>
  </el-tabs>

  <el-dialog v-model="storeDialogVisible" :title="Number(storeForm.id || 0) > 0 ? '编辑店铺' : '新增店铺'" width="560px">
    <el-form label-width="96px">
      <el-form-item label="店铺名称" required>
        <el-input v-model="storeForm.store_name" placeholder="请输入店铺名称" />
      </el-form-item>
      <el-form-item label="店铺编码">
        <el-input v-model="storeForm.store_code" placeholder="如：VN-TT-01" />
      </el-form-item>
      <el-form-item label="GMV币种">
        <el-select v-model="storeForm.default_gmv_currency" placeholder="选择GMV币种">
          <el-option v-for="c in storeCurrencyOptions" :key="'sgmv'+c" :label="c" :value="c" />
        </el-select>
      </el-form-item>
      <el-form-item label="状态">
        <el-switch v-model="storeForm.status" :active-value="1" :inactive-value="0" />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="storeForm.notes" type="textarea" :rows="3" placeholder="可选" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="storeDialogVisible=false">取消</el-button>
      <el-button @click="resetStoreForm">重置</el-button>
      <el-button type="primary" :loading="storeSaving" @click="saveStore">保存</el-button>
    </template>
  </el-dialog>

  <el-dialog v-model="catalogDialogVisible" :title="Number(catalogForm.id || 0) > 0 ? '编辑商品' : '新增商品'" width="620px">
    <el-form label-width="96px">
      <el-form-item label="款式编号" required>
        <el-input v-model="catalogForm.style_code" placeholder="请输入款式编号" />
      </el-form-item>
      <el-form-item label="商品名">
        <el-input v-model="catalogForm.product_name" placeholder="可选" />
      </el-form-item>
      <el-form-item label="图片URL">
        <el-input v-model="catalogForm.image_url" placeholder="可选" />
      </el-form-item>
      <el-form-item label="状态">
        <el-switch v-model="catalogForm.status" :active-value="1" :inactive-value="0" />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="catalogForm.notes" type="textarea" :rows="3" placeholder="可选" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="catalogDialogVisible=false">取消</el-button>
      <el-button @click="resetCatalogForm">重置</el-button>
      <el-button type="primary" :loading="catalogSaving" @click="saveCatalog">保存</el-button>
    </template>
  </el-dialog>

  <el-dialog v-model="sessionDialogVisible" title="编辑直播场次" width="560px">
    <el-form label-width="96px">
      <el-form-item label="直播日期" required>
        <el-date-picker v-model="sessionForm.session_date" type="date" value-format="YYYY-MM-DD" placeholder="请选择日期" style="width:100%;" />
      </el-form-item>
      <el-form-item label="场次名称">
        <el-input v-model="sessionForm.session_name" placeholder="为空则默认使用日期" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="sessionDialogVisible=false">取消</el-button>
      <el-button @click="resetSessionForm">重置</el-button>
      <el-button type="primary" :loading="sessionSaving" @click="saveSession">保存</el-button>
    </template>
  </el-dialog>

  <el-drawer v-model="detailVisible" size="78%" :title="detailTitle">
    <div v-loading="detailLoading">
      <el-alert
        v-if="detail.error_message"
        :title="detail.error_message"
        type="error"
        :closable="false"
        style="margin-bottom:12px;"
      />
      <el-alert
        v-if="detail.currency && detail.currency.gmv_currency === 'MIXED'"
        title="当前明细包含多个店铺币种，原币GMV仅供参考，已提供人民币折算金额"
        type="warning"
        :closable="false"
        style="margin-bottom:12px;"
      />
      <div class="live-style-summary-grid">
        <div class="live-style-summary-card">
          <div class="label">场次覆盖</div>
          <div class="value">{{ Number(detail.summary.session_count || 0) }}</div>
          <div class="sub">商品记录 {{ Number(detail.summary.product_count || 0) }} 条</div>
        </div>
        <div class="live-style-summary-card">
          <div class="label">GMV原币（{{ detail.currency && detail.currency.gmv_currency ? detail.currency.gmv_currency : 'CNY' }}）</div>
          <div class="value live-style-money">{{ fmtMoney(detail.summary.gmv_sum, detail.currency && detail.currency.gmv_currency ? detail.currency.gmv_currency : 'CNY') }}</div>
          <div class="sub">{{ detail.currency && detail.currency.gmv_currency_label ? detail.currency.gmv_currency_label : currencyLabel('VND') }}</div>
        </div>
        <div class="live-style-summary-card">
          <div class="label">折合人民币（CNY）</div>
          <div class="value live-style-money-cny">{{ fmtCny(detail.summary.gmv_cny_sum) }}</div>
          <div class="sub">汇率状态：{{ fxStatusLabel(detail.currency && detail.currency.fx_status ? detail.currency.fx_status : '') }}</div>
        </div>
        <div class="live-style-summary-card">
          <div class="label">CTR</div>
          <div class="value">{{ fmtPercent(detail.summary.ctr, 2) }}</div>
          <div class="sub">点击 {{ Number(detail.summary.clicks_sum || 0) }}</div>
        </div>
        <div class="live-style-summary-card">
          <div class="label">加购率</div>
          <div class="value">{{ fmtPercent(detail.summary.add_to_cart_rate, 2) }}</div>
          <div class="sub">加购 {{ Number(detail.summary.add_to_cart_sum || 0) }}</div>
        </div>
      </div>

      <div class="live-style-detail-media-bar">
        <el-select v-model="detail.image_store_id" filterable placeholder="选择店铺（用于维护图片）">
          <el-option
            v-for="s in stores"
            :key="s.id"
            :label="s.store_name + ' · ' + (s.default_gmv_currency || s.gmv_currency || 'VND')"
            :value="Number(s.id)"
          />
        </el-select>
        <el-input v-model="detail.image_url_input" placeholder="图片URL（可选）" />
        <el-button type="primary" :loading="detailImageLoading" @click="updateImageWithUrl">更新图片 URL</el-button>
        <el-button :loading="detailImageLoading" @click="triggerDetailImageFile">上传图片文件</el-button>
        <input ref="detailImageInput" type="file" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none" @change="onDetailImageChange" />
      </div>

      <div class="live-style-section-title">日期趋势</div>
      <el-table class="live-style-detail-table" :data="detail.trend" stripe>
        <el-table-column prop="session_date" label="日期" min-width="120" />
        <el-table-column label="GMV原币" min-width="180" align="right">
          <template #default="scope">{{ fmtMoney(scope.row.gmv_sum, scope.row.gmv_currency || (detail.currency && detail.currency.gmv_currency ? detail.currency.gmv_currency : 'VND')) }}</template>
        </el-table-column>
        <el-table-column label="GMV(CNY)" min-width="160" align="right">
          <template #default="scope">{{ fmtCny(scope.row.gmv_cny_sum) }}</template>
        </el-table-column>
        <el-table-column prop="impressions_sum" label="曝光" min-width="100" align="right" />
        <el-table-column prop="clicks_sum" label="点击" min-width="90" align="right" />
        <el-table-column prop="add_to_cart_sum" label="加购" min-width="90" align="right" />
        <el-table-column prop="orders_sum" label="订单" min-width="90" align="right" />
        <el-table-column label="CTR" min-width="90" align="right"><template #default="scope">{{ fmtPercent(scope.row.ctr, 2) }}</template></el-table-column>
        <el-table-column label="加购率" min-width="100" align="right"><template #default="scope">{{ fmtPercent(scope.row.add_to_cart_rate, 2) }}</template></el-table-column>
      </el-table>

      <div class="live-style-detail-catalog">
        <div class="live-style-section-title">店铺商品库记录</div>
        <el-table class="live-style-detail-table" :data="detail.catalog_items" stripe>
          <el-table-column prop="store_id" label="店铺ID" min-width="100" />
          <el-table-column prop="style_code" label="款式编号" min-width="120" />
          <el-table-column prop="product_name" label="商品名" min-width="180" />
          <el-table-column label="图片" min-width="110">
            <template #default="scope">
              <div class="live-style-image-box">
                <el-image
                  v-if="scope.row.image_url"
                  :src="scope.row.image_url"
                  fit="cover"
                  :preview-src-list="[scope.row.image_url]"
                  :preview-teleported="true"
                />
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="updated_at" label="更新时间" min-width="170" />
        </el-table>
      </div>
    </div>
  </el-drawer>
</div>
`,
  })
    .use(ElementPlus)
    .mount('#liveStyleAnalysisApp');
})();
