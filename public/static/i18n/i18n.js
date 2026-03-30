/* Lightweight i18n for CDN pages (admin + influencer).
 * - lang priority: ?lang= > localStorage > navigator.language > default zh
 * - setLang will persist + update URL + reload
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'app_lang';

  function normalizeLang(lang) {
    var s = String(lang || '').toLowerCase();
    if (s === 'en' || s.indexOf('en-') === 0) return 'en';
    if (s === 'zh' || s.indexOf('zh-') === 0) return 'zh';
    return '';
  }

  function getQueryLang() {
    try {
      var sp = new URLSearchParams(window.location.search || '');
      return normalizeLang(sp.get('lang'));
    } catch (e) {
      return '';
    }
  }

  function getSavedLang() {
    try {
      return normalizeLang(window.localStorage.getItem(STORAGE_KEY));
    } catch (e) {
      return '';
    }
  }

  function getNavigatorLang() {
    try {
      return normalizeLang((navigator.language || navigator.userLanguage || ''));
    } catch (e) {
      return '';
    }
  }

  function getLang(defaultLang) {
    return getQueryLang() || getSavedLang() || getNavigatorLang() || normalizeLang(defaultLang) || 'zh';
  }

  function setLang(lang) {
    var l = normalizeLang(lang) || 'zh';
    try { window.localStorage.setItem(STORAGE_KEY, l); } catch (e) {}
    try {
      var sp = new URLSearchParams(window.location.search || '');
      sp.set('lang', l);
      var newUrl = window.location.pathname + '?' + sp.toString() + (window.location.hash || '');
      window.location.href = newUrl;
    } catch (e) {
      window.location.reload();
    }
  }

  function applyHtmlLang(lang) {
    try {
      var l = normalizeLang(lang) || getLang('zh');
      var html = document && document.documentElement;
      if (html) html.setAttribute('lang', l === 'en' ? 'en' : 'zh-CN');
    } catch (e) {}
  }

  function fmt(str, vars) {
    var s = String(str || '');
    if (!vars) return s;
    return s.replace(/\{(\w+)\}/g, function (_, k) {
      return (vars[k] === undefined || vars[k] === null) ? '' : String(vars[k]);
    });
  }

  var DICT = {
    zh: {
      // common
      'lang.zh': '中文',
      'lang.en': 'English',
      'common.ok': '确定',
      'common.cancel': '取消',
      'common.search': '查询',
      'common.filter': '筛选',
      'common.reset': '重置',
      'common.add': '添加',
      'common.create': '创建',
      'common.save': '保存',
      'common.edit': '编辑',
      'common.delete': '删除',
      'common.open': '打开',
      'common.copy': '复制',
      'common.refresh': '刷新',
      'common.statusAll': '全部状态',
      'common.statusEnabled': '启用',
      'common.statusDisabled': '禁用',
      'common.loadingFailed': '加载失败',
      'common.noData': '暂无数据',

      // influencer
      'influencer.title': '达人取片',
      'influencer.header': '📦 达人取片',
      'influencer.loading': '加载中…',
      'influencer.product': '商品',
      'influencer.openLink': '打开链接',
      'influencer.videoTitle': '标题',
      'influencer.copyTitle': '复制标题',
      'influencer.downloadCover': '下载封面',
      'influencer.downloadVideo': '下载视频',
      'influencer.copied': '已复制',
      'influencer.downloadStartedCover': '已开始下载封面',
      'influencer.downloadStartedNext': '已开始下载，下一条随机视频',
      'influencer.empty': '暂无视频',
      'influencer.invalidLink': '链接无效',

      // auth
      'auth.pageTitle': '后台登录',
      'auth.loginTitle': '登录后台',
      'auth.loginDesc': '请输入账号密码以继续',
      'auth.username': '用户名',
      'auth.password': '密码',
      'auth.usernamePh': '请输入用户名',
      'auth.passwordPh': '请输入密码',
      'auth.loginBtn': '登录',
      'auth.needUserPass': '请输入用户名和密码',
      'auth.defaultAccount': '默认账号：admin / admin123（首次使用后请立即修改密码）',
      'auth.loginProblem': '遇到登录问题？请联系管理员或检查服务端 Session 配置。',

      // layout/menu
      'admin.topTitle': '后台管理',
      'admin.menu.data': '数据',
      'admin.menu.dashboard': '仪表盘',
      'admin.menu.material': '素材',
      'admin.menu.video': '视频',
      'admin.menu.upload': '上传',
      'admin.menu.product': '商品',
      'admin.menu.distribute': '达人链',
      'admin.menu.terminal': '终端',
      'admin.menu.platform': '平台',
      'admin.menu.device': '设备',
      'admin.menu.system': '系统',
      'admin.menu.settings': '系统设置',
      'admin.menu.user': '用户',
      'admin.menu.cache': '缓存',
      'admin.menu.errors': '异常',
      'admin.menu.logout': '退出',

      // pages (breadcrumbs/titles)
      'page.product.breadcrumb': '素材 / 商品',
      'page.product.title': '商品',
      'page.product.add': '+ 添加商品',
      'page.product.searchName': '搜索名称',

      'page.distribute.breadcrumb': '素材 / 达人链',
      'page.distribute.title': '达人链',
      'page.distribute.add': '+ 生成达人链',
      'page.distribute.allProducts': '全部商品',
      'page.distribute.hint': '达人打开链接后，随机展示该商品下一条未下载视频；下载后全局标记为已下载，不再出现。',
      'page.distribute.empty': '暂无链接',

      'page.platform.breadcrumb': '素材 / 平台',
      'page.platform.title': '平台',
      'page.platform.add': '+ 添加平台',
      'page.platform.searchNameCode': '搜索名称或代码',
      'page.platform.empty': '暂无平台数据',

      'page.device.breadcrumb': '终端 / 设备',
      'page.device.title': '设备',
      'page.device.add': '+ 添加设备',
      'page.device.allPlatforms': '全部平台',
      'page.device.searchDeviceIp': '搜索设备名称或 IP',
      'page.device.empty': '暂无设备数据',

      'page.cache.breadcrumb': '系统 / 缓存',
      'page.cache.title': '缓存',
      'page.cache.clear': '清空缓存',
      'page.cache.search': '搜索文件名或来源URL',

      'page.errors.breadcrumb': '系统 / 异常',
      'page.errors.title': '下载异常',
      'page.errors.clear': '清空异常',
      'page.errors.tip': '本页从运行日志中提取下载/缓存相关异常，用于快速定位问题；可按日期与关键词筛选。',
      'page.errors.datePh': '选择日期',
      'page.errors.keywordPh': '搜索错误信息...',

      'page.user.breadcrumb': '系统 / 用户',
      'page.user.title': '用户',
      'page.user.add': '+ 添加用户',
      'page.user.searchUser': '搜索用户名'
    },
    en: {
      'lang.zh': '中文',
      'lang.en': 'English',
      'common.ok': 'OK',
      'common.cancel': 'Cancel',
      'common.search': 'Search',
      'common.filter': 'Filter',
      'common.reset': 'Reset',
      'common.add': 'Add',
      'common.create': 'Create',
      'common.save': 'Save',
      'common.edit': 'Edit',
      'common.delete': 'Delete',
      'common.open': 'Open',
      'common.copy': 'Copy',
      'common.refresh': 'Refresh',
      'common.statusAll': 'All status',
      'common.statusEnabled': 'Enabled',
      'common.statusDisabled': 'Disabled',
      'common.loadingFailed': 'Load failed',
      'common.noData': 'No data',

      'influencer.title': 'Creator Download',
      'influencer.header': '📦 Creator Download',
      'influencer.loading': 'Loading…',
      'influencer.product': 'Product',
      'influencer.openLink': 'Open link',
      'influencer.videoTitle': 'Title',
      'influencer.copyTitle': 'Copy title',
      'influencer.downloadCover': 'Download cover',
      'influencer.downloadVideo': 'Download video',
      'influencer.copied': 'Copied',
      'influencer.downloadStartedCover': 'Download started (cover)',
      'influencer.downloadStartedNext': 'Download started. Loading next video…',
      'influencer.empty': 'No video available',
      'influencer.invalidLink': 'Invalid link',

      'auth.pageTitle': 'Admin Login',
      'auth.loginTitle': 'Sign in',
      'auth.loginDesc': 'Enter your credentials to continue',
      'auth.username': 'Username',
      'auth.password': 'Password',
      'auth.usernamePh': 'Enter username',
      'auth.passwordPh': 'Enter password',
      'auth.loginBtn': 'Sign in',
      'auth.needUserPass': 'Please enter username and password',
      'auth.defaultAccount': 'Default account: admin / admin123 (please change password after first login)',
      'auth.loginProblem': 'Login issues? Contact admin or check server Session settings.',

      'admin.topTitle': 'Admin',
      'admin.menu.data': 'Data',
      'admin.menu.dashboard': 'Dashboard',
      'admin.menu.material': 'Library',
      'admin.menu.video': 'Videos',
      'admin.menu.upload': 'Upload',
      'admin.menu.product': 'Products',
      'admin.menu.distribute': 'Creator Links',
      'admin.menu.terminal': 'Devices',
      'admin.menu.platform': 'Platforms',
      'admin.menu.device': 'Terminals',
      'admin.menu.system': 'System',
      'admin.menu.settings': 'Settings',
      'admin.menu.user': 'Users',
      'admin.menu.cache': 'Cache',
      'admin.menu.errors': 'Errors',
      'admin.menu.logout': 'Logout',

      'page.product.breadcrumb': 'Library / Products',
      'page.product.title': 'Products',
      'page.product.add': '+ Add product',
      'page.product.searchName': 'Search name',

      'page.distribute.breadcrumb': 'Library / Creator Links',
      'page.distribute.title': 'Creator Links',
      'page.distribute.add': '+ New link',
      'page.distribute.allProducts': 'All products',
      'page.distribute.hint': 'Creators open the link to get a random undownloaded video for the product. Once downloaded, it is globally marked and will not appear again.',
      'page.distribute.empty': 'No links',

      'page.platform.breadcrumb': 'Library / Platforms',
      'page.platform.title': 'Platforms',
      'page.platform.add': '+ Add platform',
      'page.platform.searchNameCode': 'Search name or code',
      'page.platform.empty': 'No platforms',

      'page.device.breadcrumb': 'Devices / Terminals',
      'page.device.title': 'Terminals',
      'page.device.add': '+ Add terminal',
      'page.device.allPlatforms': 'All platforms',
      'page.device.searchDeviceIp': 'Search device name or IP',
      'page.device.empty': 'No terminals',

      'page.cache.breadcrumb': 'System / Cache',
      'page.cache.title': 'Cache',
      'page.cache.clear': 'Clear cache',
      'page.cache.search': 'Search filename or source URL',

      'page.errors.breadcrumb': 'System / Errors',
      'page.errors.title': 'Download Errors',
      'page.errors.clear': 'Clear',
      'page.errors.tip': 'This page extracts download/cache errors from runtime logs for quick troubleshooting. Filter by date or keyword.',
      'page.errors.datePh': 'Select date',
      'page.errors.keywordPh': 'Search error message...',

      'page.user.breadcrumb': 'System / Users',
      'page.user.title': 'Users',
      'page.user.add': '+ Add user',
      'page.user.searchUser': 'Search username'
    }
  };

  function t(key, vars, optLang) {
    var lang = normalizeLang(optLang) || getLang('zh');
    var dict = (DICT[lang] || DICT.zh);
    var val = dict[key];
    if (typeof val === 'function') return val(vars || {});
    if (val === undefined || val === null) {
      // fallback to zh
      val = (DICT.zh && DICT.zh[key] !== undefined) ? DICT.zh[key] : key;
    }
    return fmt(val, vars);
  }

  // Minimal DOM i18n helper for SSR templates
  function applyDom(root) {
    try {
      var lang = getLang('zh');
      applyHtmlLang(lang);
      var el = root || document;
      if (!el) return;
      var nodes = el.querySelectorAll('[data-i18n]');
      if (!nodes || !nodes.length) return;
      nodes.forEach(function (n) {
        var k = n.getAttribute('data-i18n');
        if (!k) return;
        n.textContent = t(k, null, lang);
      });
      var phNodes = el.querySelectorAll('[data-i18n-ph]');
      phNodes.forEach(function (n) {
        var k = n.getAttribute('data-i18n-ph');
        if (!k) return;
        n.setAttribute('placeholder', t(k, null, lang));
      });
      var titleNodes = el.querySelectorAll('[data-i18n-title]');
      titleNodes.forEach(function (n) {
        var k = n.getAttribute('data-i18n-title');
        if (!k) return;
        n.setAttribute('title', t(k, null, lang));
      });
    } catch (e) {}
  }

  window.AppI18n = {
    getLang: getLang,
    setLang: setLang,
    t: t,
    applyDom: applyDom,
    applyHtmlLang: applyHtmlLang,
    _dict: DICT
  };
})();

