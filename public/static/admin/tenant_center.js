(function () {
  'use strict';

  if (typeof Vue === 'undefined' || typeof ElementPlus === 'undefined') {
    var root = document.getElementById('tenantCenterApp');
    if (root) {
      root.innerHTML = '<div class="alert alert-danger" style="margin:0;">租户中心依赖加载失败，请检查网络后刷新重试。</div>';
    }
    if (window.AdminApi && typeof window.AdminApi.healthReport === 'function') {
      window.AdminApi.healthReport({
        module: 'tenant_center',
        page: window.location.pathname || '',
        event: 'dependency_missing',
        detail: {
          has_vue: typeof Vue !== 'undefined' ? 1 : 0,
          has_element_plus: typeof ElementPlus !== 'undefined' ? 1 : 0
        }
      });
    }
    return;
  }

  var createApp = Vue.createApp;
  var ref = Vue.ref;
  var reactive = Vue.reactive;
  var computed = Vue.computed;
  var watch = Vue.watch;
  var onMounted = Vue.onMounted;
  var ElMessage = ElementPlus.ElMessage;
  var ElMessageBox = ElementPlus.ElMessageBox;

  function parseJson(text) {
    var raw = String(text || '').trim();
    if (!raw) return null;
    if (raw.charCodeAt(0) === 0xfeff) {
      raw = raw.slice(1);
    }
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function asInt(value, fallback) {
    var n = parseInt(value, 10);
    return Number.isNaN(n) ? (fallback || 0) : n;
  }

  function asArray(value) {
    if (Array.isArray(value)) return value.slice();
    return [];
  }

  function mountTenantCenterApp() {
    return createApp({
    setup: function () {
      var i18n = window.AppI18n || null;
      var lang = i18n ? i18n.getLang('zh') : 'zh';
      if (i18n) i18n.applyDom(document);
      var tt = function (key, vars) {
        return i18n ? i18n.t(key, vars || null, lang) : String(key || '');
      };

      var headers = {
        'Accept': 'application/json, text/javascript, */*;q=0.01',
        'X-Requested-With': 'XMLHttpRequest'
      };

      function resolveError(json, fallbackKey) {
        if (json && json.error_key) {
          var key = String(json.error_key || '');
          var translated = tt(key);
          if (translated && translated !== key) return translated;
        }
        if (json && json.msg) {
          var mapped = i18n && typeof i18n.msg === 'function' ? i18n.msg(String(json.msg || ''), lang) : '';
          if (mapped) return mapped;
          return String(json.msg || '');
        }
        return tt(fallbackKey || 'common.operationFailed');
      }

      function normalizePayload(obj) {
        var payload = obj || {};
        var keys = Object.keys(payload);
        var result = {};
        for (var i = 0; i < keys.length; i += 1) {
          var key = keys[i];
          var value = payload[key];
          if (value === '' || value === null || typeof value === 'undefined') continue;
          result[key] = value;
        }
        return result;
      }

      async function request(url, options) {
        var response = await fetch(url, options || {});
        var text = await response.text();
        var json = parseJson(text);
        if (json && typeof json.code !== 'undefined') {
          return json;
        }
        if (/auth\/login/i.test(text) || /<html/i.test(text)) {
          throw new Error(tt('common.sessionExpired'));
        }
        throw new Error(tt('common.operationFailed'));
      }

      async function apiGet(url, params) {
        if (window.AdminApi && typeof window.AdminApi.get === 'function') {
          return window.AdminApi.get(url, params || {}, { fallbackKey: 'common.loadingFailed' });
        }
        var u = new URL(url, window.location.origin);
        var payload = normalizePayload(params || {});
        var keys = Object.keys(payload);
        for (var i = 0; i < keys.length; i += 1) {
          u.searchParams.set(keys[i], String(payload[keys[i]]));
        }
        return request(u.pathname + '?' + u.searchParams.toString(), {
          credentials: 'same-origin',
          headers: headers
        });
      }

      async function apiPost(url, payload) {
        if (window.AdminApi && typeof window.AdminApi.post === 'function') {
          return window.AdminApi.post(url, payload || {}, { fallbackKey: 'common.operationFailed' });
        }
        return request(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: Object.assign({}, headers, { 'Content-Type': 'application/json; charset=UTF-8' }),
          body: JSON.stringify(payload || {})
        });
      }

      var allowedTabs = ['tenants', 'packages', 'subscriptions', 'admins', 'audit'];

      function normalizeTabName(input) {
        var raw = '';
        if (typeof input === 'string' || typeof input === 'number') {
          raw = String(input || '');
        } else if (input && typeof input === 'object') {
          if (typeof input.paneName !== 'undefined') {
            raw = String(input.paneName || '');
          } else if (typeof input.name !== 'undefined') {
            raw = String(input.name || '');
          } else if (input.props && typeof input.props.name !== 'undefined') {
            raw = String(input.props.name || '');
          }
        }
        raw = String(raw || '').trim();
        return allowedTabs.indexOf(raw) >= 0 ? raw : 'tenants';
      }

      function syncTabToUrl(tabName) {
        var tab = normalizeTabName(tabName);
        var u = new URL(window.location.href);
        u.searchParams.set('tab', tab);
        window.history.replaceState(null, '', u.pathname + '?' + u.searchParams.toString());
      }

      var tabName = String(new URL(window.location.href).searchParams.get('tab') || 'tenants');
      var activeTab = ref(normalizeTabName(tabName));
      var moduleAccessMode = ref('enabled');
      var panelErrors = reactive({
        tenants: '',
        packages: '',
        subscriptions: '',
        admins: '',
        audit: ''
      });
      var activePanelError = computed(function () {
        var key = normalizeTabName(activeTab.value);
        return String(panelErrors[key] || '');
      });

      var tenants = ref([]);
      var packages = ref([]);
      var moduleCatalog = ref([]);
      var subscriptionModules = ref([]);
      var admins = ref([]);
      var audits = ref([]);

      var tenantLoading = ref(false);
      var packageLoading = ref(false);
      var subscriptionModuleLoading = ref(false);
      var adminLoading = ref(false);
      var auditLoading = ref(false);

      var tenantSaving = ref(false);
      var packageSaving = ref(false);
      var subscriptionSaving = ref(false);
      var adminSaving = ref(false);

      var tenantDialogVisible = ref(false);
      var packageDialogVisible = ref(false);
      var adminDialogVisible = ref(false);

      var tenantFilters = reactive({
        keyword: '',
        status: ''
      });

      var tenantForm = reactive({
        tenant_id: 0,
        tenant_code: '',
        tenant_name: '',
        status: 1,
        remark: '',
        package_id: '',
        expires_at: ''
      });

      var packageForm = reactive({
        id: 0,
        package_code: '',
        package_name: '',
        description: '',
        status: 1,
        modules: [],
        optional_modules: []
      });

      var subscriptionForm = reactive({
        tenant_id: '',
        package_id: '',
        expires_at: '',
        status: 1,
        addon_modules: [],
        disabled_modules: []
      });

      var adminFilters = reactive({
        tenant_id: '',
        keyword: ''
      });

      var adminForm = reactive({
        id: 0,
        tenant_id: '',
        username: '',
        role: 'operator',
        status: 1,
        password: ''
      });

      var auditFilters = reactive({
        tenant_id: '',
        action: '',
        limit: 100
      });

      var moduleNames = computed(function () {
        var rows = asArray(moduleCatalog.value);
        var map = {};
        for (var i = 0; i < rows.length; i += 1) {
          var name = String((rows[i] || {}).name || '').trim();
          if (name) map[name] = true;
        }
        return Object.keys(map).sort();
      });

      function clearPanelError(panel) {
        var key = normalizeTabName(panel);
        panelErrors[key] = '';
      }

      function setPanelError(panel, message) {
        var key = normalizeTabName(panel);
        panelErrors[key] = String(message || '');
      }

      function resetTenantForm() {
        tenantForm.tenant_id = 0;
        tenantForm.tenant_code = '';
        tenantForm.tenant_name = '';
        tenantForm.status = 1;
        tenantForm.remark = '';
        tenantForm.package_id = '';
        tenantForm.expires_at = '';
      }

      function openTenantDialog(row) {
        resetTenantForm();
        if (row && typeof row === 'object') {
          tenantForm.tenant_id = asInt(row.tenant_id || row.id || 0, 0);
          tenantForm.tenant_code = String(row.tenant_code || '');
          tenantForm.tenant_name = String(row.tenant_name || '');
          tenantForm.status = asInt(row.status, 1) === 1 ? 1 : 0;
          tenantForm.remark = String(row.remark || '');
          tenantForm.package_id = row.package_id ? asInt(row.package_id, 0) : '';
          tenantForm.expires_at = String(row.expires_at || '');
        }
        tenantDialogVisible.value = true;
      }

      function resetPackageForm() {
        packageForm.id = 0;
        packageForm.package_code = '';
        packageForm.package_name = '';
        packageForm.description = '';
        packageForm.status = 1;
        packageForm.modules = [];
        packageForm.optional_modules = [];
      }

      function openPackageDialog(row) {
        resetPackageForm();
        if (row && typeof row === 'object') {
          packageForm.id = asInt(row.id || row.package_id || 0, 0);
          packageForm.package_code = String(row.package_code || '');
          packageForm.package_name = String(row.package_name || '');
          packageForm.description = String(row.description || '');
          packageForm.status = asInt(row.status, 1) === 1 ? 1 : 0;
          packageForm.modules = asArray(row.modules).map(function (item) { return String(item || ''); }).filter(Boolean);
          packageForm.optional_modules = asArray(row.optional_modules).map(function (item) { return String(item || ''); }).filter(Boolean);
        } else {
          packageForm.modules = moduleNames.value.slice();
        }
        packageDialogVisible.value = true;
      }

      function resetAdminForm() {
        adminForm.id = 0;
        adminForm.tenant_id = '';
        adminForm.username = '';
        adminForm.role = 'operator';
        adminForm.status = 1;
        adminForm.password = '';
      }

      function openAdminDialog(row) {
        resetAdminForm();
        if (row && typeof row === 'object') {
          adminForm.id = asInt(row.id, 0);
          adminForm.tenant_id = asInt(row.tenant_id, 0);
          adminForm.username = String(row.username || '');
          adminForm.role = String(row.role || 'operator');
          adminForm.status = asInt(row.status, 1) === 1 ? 1 : 0;
        } else if (tenants.value.length > 0) {
          adminForm.tenant_id = asInt((tenants.value[0] || {}).tenant_id || 0, 0);
        }
        adminDialogVisible.value = true;
      }

      function currentTenantById(tenantId) {
        var id = asInt(tenantId, 0);
        var rows = asArray(tenants.value);
        for (var i = 0; i < rows.length; i += 1) {
          if (asInt((rows[i] || {}).tenant_id, 0) === id) return rows[i];
        }
        return null;
      }

      async function loadTenants() {
        tenantLoading.value = true;
        clearPanelError('tenants');
        try {
          var json = await apiGet('/admin.php/tenant/list', {
            keyword: tenantFilters.keyword,
            status: tenantFilters.status
          });
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.loadingFailed'));
          var data = json.data || {};
          tenants.value = asArray(data.items);
          moduleAccessMode.value = String(data.module_access_mode || moduleAccessMode.value || 'enabled');

          if (!subscriptionForm.tenant_id && tenants.value.length > 0) {
            subscriptionForm.tenant_id = asInt((tenants.value[0] || {}).tenant_id, 0);
          }
        } catch (e) {
          var errMsg = String((e && e.message) || tt('common.loadingFailed'));
          setPanelError('tenants', errMsg);
          ElMessage.error(errMsg);
        } finally {
          tenantLoading.value = false;
        }
      }

      async function saveTenant() {
        if (!tenantForm.tenant_name) {
          ElMessage.warning(tt('page.tenant.msgTenantNameRequired'));
          return;
        }
        tenantSaving.value = true;
        try {
          var payload = {
            tenant_id: asInt(tenantForm.tenant_id, 0),
            tenant_code: tenantForm.tenant_code,
            tenant_name: tenantForm.tenant_name,
            status: asInt(tenantForm.status, 1),
            remark: tenantForm.remark,
            package_id: tenantForm.package_id ? asInt(tenantForm.package_id, 0) : undefined,
            expires_at: tenantForm.expires_at || undefined
          };
          var json = await apiPost('/admin.php/tenant/save', payload);
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.saveFailed'));
          tenantDialogVisible.value = false;
          ElMessage.success(tt('common.saved'));
          await loadTenants();
        } catch (e) {
          ElMessage.error(String((e && e.message) || tt('common.saveFailed')));
        } finally {
          tenantSaving.value = false;
        }
      }

      async function toggleTenantStatus(row) {
        var tenantId = asInt((row || {}).tenant_id, 0);
        if (tenantId <= 0) return;
        var nextStatus = asInt((row || {}).status, 1) === 1 ? 0 : 1;
        try {
          await ElMessageBox.confirm(
            tt('page.tenant.confirmStatus'),
            tt('common.ok'),
            { type: 'warning', confirmButtonText: tt('common.ok'), cancelButtonText: tt('common.cancel') }
          );
          var json = await apiPost('/admin.php/tenant/status', {
            tenant_id: tenantId,
            status: nextStatus
          });
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.operationFailed'));
          ElMessage.success(tt('common.updated'));
          await loadTenants();
        } catch (e) {
          var msg = String((e && e.message) || '');
          if (msg !== 'cancel' && msg !== 'close') {
            ElMessage.error(msg || tt('common.operationFailed'));
          }
        }
      }

      async function switchTenant(row) {
        var tenantId = asInt((row || {}).tenant_id, 0);
        if (tenantId <= 0) return;
        try {
          await ElMessageBox.confirm(
            tt('page.tenant.confirmSwitch'),
            tt('common.ok'),
            { type: 'warning', confirmButtonText: tt('common.ok'), cancelButtonText: tt('common.cancel') }
          );
          var json = await apiPost('/admin.php/tenant/switch', { tenant_id: tenantId });
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.operationFailed'));
          ElMessage.success(tt('page.tenant.msgSwitched'));
          window.setTimeout(function () { window.location.reload(); }, 280);
        } catch (e) {
          var msg = String((e && e.message) || '');
          if (msg !== 'cancel' && msg !== 'close') {
            ElMessage.error(msg || tt('common.operationFailed'));
          }
        }
      }

      async function loadPackages() {
        packageLoading.value = true;
        clearPanelError('packages');
        try {
          var json = await apiGet('/admin.php/tenant/package/list', {});
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.loadingFailed'));
          var data = json.data || {};
          packages.value = asArray(data.items);
          moduleCatalog.value = asArray(data.module_catalog);
          moduleAccessMode.value = String(data.module_access_mode || moduleAccessMode.value || 'enabled');
          if (!subscriptionForm.package_id && packages.value.length > 0) {
            subscriptionForm.package_id = asInt((packages.value[0] || {}).id, 0);
          }
        } catch (e) {
          var errMsg = String((e && e.message) || tt('common.loadingFailed'));
          setPanelError('packages', errMsg);
          ElMessage.error(errMsg);
        } finally {
          packageLoading.value = false;
        }
      }

      async function savePackage() {
        if (!packageForm.package_name) {
          ElMessage.warning(tt('page.tenant.msgPackageNameRequired'));
          return;
        }
        packageSaving.value = true;
        try {
          var payload = {
            id: asInt(packageForm.id, 0),
            package_code: packageForm.package_code,
            package_name: packageForm.package_name,
            description: packageForm.description,
            status: asInt(packageForm.status, 1),
            modules: asArray(packageForm.modules),
            optional_modules: asArray(packageForm.optional_modules)
          };
          var json = await apiPost('/admin.php/tenant/package/save', payload);
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.saveFailed'));
          packageDialogVisible.value = false;
          ElMessage.success(tt('common.saved'));
          await loadPackages();
        } catch (e) {
          ElMessage.error(String((e && e.message) || tt('common.saveFailed')));
        } finally {
          packageSaving.value = false;
        }
      }

      function hydrateSubscriptionFormByTenant(tenantId) {
        var row = currentTenantById(tenantId);
        if (!row) return;
        subscriptionForm.tenant_id = asInt(row.tenant_id, 0);
        subscriptionForm.package_id = row.package_id ? asInt(row.package_id, 0) : subscriptionForm.package_id;
        subscriptionForm.expires_at = String(row.expires_at || '');
        subscriptionForm.status = asInt(row.subscription_status, 1) === 1 ? 1 : 0;
      }

      async function onSubscriptionTenantChange(value) {
        var tid = asInt(value, 0);
        if (tid <= 0) {
          subscriptionModules.value = [];
          return;
        }
        hydrateSubscriptionFormByTenant(tid);
        await loadSubscriptionModules(tid);
      }

      async function saveSubscription() {
        var tenantId = asInt(subscriptionForm.tenant_id, 0);
        if (tenantId <= 0) {
          ElMessage.warning(tt('page.tenant.msgSelectTenant'));
          return;
        }
        var packageId = asInt(subscriptionForm.package_id, 0);
        if (packageId <= 0) {
          ElMessage.warning(tt('page.tenant.msgSelectPackage'));
          return;
        }

        subscriptionSaving.value = true;
        try {
          var payload = {
            tenant_id: tenantId,
            package_id: packageId,
            status: asInt(subscriptionForm.status, 1),
            expires_at: subscriptionForm.expires_at || null,
            addon_modules: asArray(subscriptionForm.addon_modules),
            disabled_modules: asArray(subscriptionForm.disabled_modules)
          };
          var json = await apiPost('/admin.php/tenant/subscription/save', payload);
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.saveFailed'));
          ElMessage.success(tt('common.saved'));
          await loadTenants();
          await loadSubscriptionModules(tenantId);
        } catch (e) {
          ElMessage.error(String((e && e.message) || tt('common.saveFailed')));
        } finally {
          subscriptionSaving.value = false;
        }
      }

      async function loadSubscriptionModules(tenantId) {
        var tid = asInt(tenantId, 0);
        if (tid <= 0) {
          subscriptionModules.value = [];
          return;
        }
        subscriptionModuleLoading.value = true;
        clearPanelError('subscriptions');
        try {
          var json = await apiGet('/admin.php/tenant/subscription/modules', {
            tenant_id: tid
          });
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.loadingFailed'));
          var data = json.data || {};
          subscriptionModules.value = asArray(data.items);
          moduleAccessMode.value = String(data.module_access_mode || moduleAccessMode.value || 'enabled');
        } catch (e) {
          var errMsg = String((e && e.message) || tt('common.loadingFailed'));
          setPanelError('subscriptions', errMsg);
          ElMessage.error(errMsg);
        } finally {
          subscriptionModuleLoading.value = false;
        }
      }

      async function loadAdmins() {
        adminLoading.value = true;
        clearPanelError('admins');
        try {
          var json = await apiGet('/admin.php/tenant/admin/list', {
            tenant_id: adminFilters.tenant_id,
            keyword: adminFilters.keyword
          });
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.loadingFailed'));
          var data = json.data || {};
          admins.value = asArray(data.items);
          moduleAccessMode.value = String(data.module_access_mode || moduleAccessMode.value || 'enabled');
        } catch (e) {
          var errMsg = String((e && e.message) || tt('common.loadingFailed'));
          setPanelError('admins', errMsg);
          ElMessage.error(errMsg);
        } finally {
          adminLoading.value = false;
        }
      }

      async function saveAdmin() {
        var tenantId = asInt(adminForm.tenant_id, 0);
        if (tenantId <= 0) {
          ElMessage.warning(tt('page.tenant.msgSelectTenant'));
          return;
        }
        if (!adminForm.username) {
          ElMessage.warning(tt('page.tenant.msgUsernameRequired'));
          return;
        }
        if (!adminForm.id && String(adminForm.password || '').length < 6) {
          ElMessage.warning(tt('page.tenant.msgPasswordTooShort'));
          return;
        }

        adminSaving.value = true;
        try {
          var payload = {
            id: asInt(adminForm.id, 0),
            tenant_id: tenantId,
            username: adminForm.username,
            role: adminForm.role,
            status: asInt(adminForm.status, 1),
            password: adminForm.password || ''
          };
          var json = await apiPost('/admin.php/tenant/admin/save', payload);
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.saveFailed'));
          adminDialogVisible.value = false;
          ElMessage.success(tt('common.saved'));
          await loadAdmins();
        } catch (e) {
          ElMessage.error(String((e && e.message) || tt('common.saveFailed')));
        } finally {
          adminSaving.value = false;
        }
      }

      async function loadAudit() {
        auditLoading.value = true;
        clearPanelError('audit');
        try {
          var json = await apiGet('/admin.php/tenant/audit/list', {
            tenant_id: auditFilters.tenant_id,
            action: auditFilters.action,
            limit: asInt(auditFilters.limit, 100)
          });
          if (!json || json.code !== 0) throw new Error(resolveError(json, 'common.loadingFailed'));
          var data = json.data || {};
          audits.value = asArray(data.items);
          moduleAccessMode.value = String(data.module_access_mode || moduleAccessMode.value || 'enabled');
        } catch (e) {
          var errMsg = String((e && e.message) || tt('common.loadingFailed'));
          setPanelError('audit', errMsg);
          ElMessage.error(errMsg);
        } finally {
          auditLoading.value = false;
        }
      }

      function formatPayload(payload) {
        if (!payload || typeof payload !== 'object') return '-';
        try {
          return JSON.stringify(payload, null, 2);
        } catch (e) {
          return '-';
        }
      }

      function ensureVisiblePanelFallback() {
        try {
          var rootEl = document.getElementById('tenantCenterApp');
          if (!rootEl) return;
          var panels = rootEl.querySelectorAll('.tenant-panel[data-panel-tab]');
          if (!panels || !panels.length) return;
          var hasVisible = false;
          for (var i = 0; i < panels.length; i += 1) {
            var panel = panels[i];
            if (panel && panel.style && panel.style.display !== 'none') {
              hasVisible = true;
              break;
            }
          }
          if (hasVisible) return;

          var tab = normalizeTabName(activeTab.value);
          var target = rootEl.querySelector('.tenant-panel[data-panel-tab="' + tab + '"]');
          if (!target) target = panels[0];
          if (target && target.style) target.style.display = 'block';
        } catch (e) {}
      }

      async function onTabChange(name) {
        var tab = normalizeTabName(name);
        syncTabToUrl(tab);
        activeTab.value = tab;
        if (tab === 'tenants') {
          await loadTenants();
          return;
        }
        if (tab === 'packages') {
          await loadPackages();
          return;
        }
        if (tab === 'subscriptions') {
          await Promise.all([loadTenants(), loadPackages()]);
          if (subscriptionForm.tenant_id) {
            await loadSubscriptionModules(subscriptionForm.tenant_id);
          }
          return;
        }
        if (tab === 'admins') {
          await Promise.all([loadTenants(), loadAdmins()]);
          return;
        }
        if (tab === 'audit') {
          await Promise.all([loadTenants(), loadAudit()]);
        }
      }

      async function retryActiveTab() {
        await onTabChange(activeTab.value);
      }

      watch(function () { return packageForm.modules.slice(); }, function (vals) {
        var allowed = {};
        for (var i = 0; i < vals.length; i += 1) {
          var name = String(vals[i] || '');
          if (name) allowed[name] = true;
        }
        packageForm.optional_modules = packageForm.optional_modules.filter(function (name) {
          return !!allowed[String(name || '')];
        });
      });

      watch(function () { return activeTab.value; }, function (val) {
        var normalized = normalizeTabName(val);
        if (normalized !== val) {
          activeTab.value = normalized;
          return;
        }
        window.setTimeout(ensureVisiblePanelFallback, 0);
      });

      onMounted(async function () {
        await onTabChange(activeTab.value);
        window.setTimeout(ensureVisiblePanelFallback, 40);
        window.setTimeout(ensureVisiblePanelFallback, 220);
      });

      return {
        tt: tt,
        activeTab: activeTab,
        moduleAccessMode: moduleAccessMode,

        tenants: tenants,
        packages: packages,
        moduleCatalog: moduleCatalog,
        moduleNames: moduleNames,
        subscriptionModules: subscriptionModules,
        admins: admins,
        audits: audits,

        tenantLoading: tenantLoading,
        packageLoading: packageLoading,
        subscriptionModuleLoading: subscriptionModuleLoading,
        adminLoading: adminLoading,
        auditLoading: auditLoading,

        tenantSaving: tenantSaving,
        packageSaving: packageSaving,
        subscriptionSaving: subscriptionSaving,
        adminSaving: adminSaving,

        tenantDialogVisible: tenantDialogVisible,
        packageDialogVisible: packageDialogVisible,
        adminDialogVisible: adminDialogVisible,

        tenantFilters: tenantFilters,
        tenantForm: tenantForm,
        packageForm: packageForm,
        subscriptionForm: subscriptionForm,
        adminFilters: adminFilters,
        adminForm: adminForm,
        auditFilters: auditFilters,

        openTenantDialog: openTenantDialog,
        openPackageDialog: openPackageDialog,
        openAdminDialog: openAdminDialog,
        loadTenants: loadTenants,
        saveTenant: saveTenant,
        toggleTenantStatus: toggleTenantStatus,
        switchTenant: switchTenant,
        loadPackages: loadPackages,
        savePackage: savePackage,
        onSubscriptionTenantChange: onSubscriptionTenantChange,
        saveSubscription: saveSubscription,
        loadAdmins: loadAdmins,
        saveAdmin: saveAdmin,
        loadAudit: loadAudit,
        onTabChange: onTabChange,
        activePanelError: activePanelError,
        retryActiveTab: retryActiveTab,
        formatPayload: formatPayload
      };
    }
    }).use(ElementPlus).mount('#tenantCenterApp');
  }

  if (window.AdminPageBootstrap && typeof window.AdminPageBootstrap.init === 'function') {
    window.AdminPageBootstrap.init({
      appId: 'tenantCenterApp',
      module: 'tenant_center',
      defaultSection: 'tenants',
      sectionSelector: '[data-section]',
      dependencies: ['Vue', 'ElementPlus'],
      onLoad: function () {
        return mountTenantCenterApp();
      }
    });
  } else {
    mountTenantCenterApp();
  }
})();

