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
    if (s === 'vi' || s.indexOf('vi-') === 0) return 'vi';
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
      if (!html) return;
      if (l === 'en') html.setAttribute('lang', 'en');
      else if (l === 'vi') html.setAttribute('lang', 'vi');
      else html.setAttribute('lang', 'zh-CN');
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
      'common.loading': '加载中…',
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
      'admin.menu.styleSearch': '寻款',
      'admin.menu.influencer': '达人',
      'admin.menu.terminal': '终端',
      'admin.menu.platform': '平台',
      'admin.menu.device': '设备',
      'admin.menu.system': '系统',
      'admin.menu.settings': '系统设置',
      'admin.menu.user': '用户',
      'admin.menu.clientLicense': '发卡',
      'admin.menu.clientVersion': '版本',
      'admin.menu.cache': '缓存',
      'admin.menu.errors': '异常',
      'admin.menu.logout': '退出',

      // pages (breadcrumbs/titles)
      'page.product.breadcrumb': '素材 / 商品',
      'page.product.title': '商品',
      'page.product.add': '+ 添加商品',
      'page.product.searchName': '搜索名称',
      'page.product.totalVideos': '总视频',
      'page.product.downloadedVideos': '已下载',
      'page.product.undownloadedVideos': '未下载',

      'page.distribute.breadcrumb': '素材 / 达人链',
      'page.distribute.title': '达人链',
      'page.distribute.add': '+ 生成达人链',
      'page.distribute.allProducts': '全部商品',
      'page.distribute.hint': '达人打开链接后，随机展示该商品下一条未下载视频；下载后全局标记为已下载，不再出现。',
      'page.distribute.empty': '暂无链接',

      'page.influencer.breadcrumb': '素材 / 达人',
      'page.influencer.title': '达人名录',
      'page.influencer.hint': 'tiktok_id 为 TikTok 用户名（@handle），导入列可含：tiktok_id / handle、昵称、粉丝、地区、联系方式等。支持 CSV、TXT、Excel。',
      'page.influencer.importBtn': '导入更新',
      'page.influencer.searchPh': '搜索 @handle 或昵称',
      'page.influencer.statusFilter': '状态',
      'page.influencer.status0': '待联系',
      'page.influencer.status1': '合作中',
      'page.influencer.status2': '黑名单',
      'page.influencer.colHandle': 'TikTok',
      'page.influencer.colNick': '昵称',
      'page.influencer.colFans': '粉丝',
      'page.influencer.colRegion': '地区',
      'page.influencer.colStatus': '状态',
      'page.influencer.colContact': '联系方式',
      'page.influencer.colUpdated': '更新时间',
      'page.influencer.importProgress': '导入进度',
      'page.influencer.sampleCsv': '示例 CSV',
      'page.influencer.editTitle': '编辑达人',
      'page.influencer.deleteConfirm': '确定删除该达人？已关联的达人链将自动取消关联。',
      'page.influencer.colActions': '操作',
      'page.influencer.colAvatar': '头像 URL',

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
      ,
      // dashboard
      'page.dashboard.title': '仪表盘',
      'dashboard.kpi.videosTotal': '视频总数',
      'dashboard.kpi.downloaded': '已下载',
      'dashboard.kpi.undownloaded': '未下载',
      'dashboard.kpi.downloadRate': '下载率（重点）',
      'dashboard.kpi.asof': '截至 {date}',
      'dashboard.kpi.target': '目标 {pct}%',
      'dashboard.kpi.pending': '待下载',
      'dashboard.kpi.platforms': '平台数量',
      'dashboard.kpi.devices': '设备数量',
      'dashboard.kpi.todayUpload': '今日上传',
      'dashboard.kpi.todayDownload': '今日下载',
      'dashboard.kpi.overview': '总览',
      'dashboard.kpi.yesterday': '昨日',
      'dashboard.kpi.avg7Label': '7日均值',
      'dashboard.action.view': '查看',
      'dashboard.action.filter': '筛选',
      'dashboard.action.handle': '处理',
      'dashboard.action.list': '列表',
      'dashboard.action.errors': '异常',
      'dashboard.ops.title': '今日数据总结',
      'dashboard.ops.todayUpload': '今日上传',
      'dashboard.ops.pending': '待处理',
      'dashboard.ops.downloadRate': '下载率',
      'dashboard.ops.hint': '建议：优先处理未下载素材，提高下载率。',
      'dashboard.chart.trendsTitle30': '近30天趋势（上传柱状 + 下载折线）',
      'dashboard.chart.days7': '7天',
      'dashboard.chart.days30': '30天',
      'dashboard.chart.trendsEmpty30': '近30天暂无明显数据变化',
      'dashboard.chart.platformDist': '平台分布',
      'dashboard.chart.errorsTrend': '近7天下载异常趋势',
      'dashboard.chart.openErrors': '打开异常',
      'dashboard.chart.topErrors': 'Top异常',
      'dashboard.chart.productStockTop': '商品库存（Top12）',
      'dashboard.chart.manageProduct': '管理商品',
      'dashboard.chart.capacity': '容量',
      'dashboard.storage.uploadsFiles': 'uploads 文件数',
      'dashboard.storage.cacheFiles': 'cache 文件数',
      'dashboard.legend.upload': '上传',
      'dashboard.legend.download': '下载',
      'dashboard.platform.noData': '暂无平台数据',
      'dashboard.platform.sub': '总量 {total} · 已下载 {downloaded} · 未下载 {undownloaded}',
      'dashboard.errors.download': '下载错误',
      'dashboard.errors.cache': '缓存错误',
      'dashboard.errors.other': '其他',
      'dashboard.loadFail.trends': '趋势加载失败',
      'dashboard.loadFail.platform': '平台分布加载失败',
      'dashboard.loadFail.errorsTrend': '异常趋势加载失败',
      'dashboard.loadFail.product': '商品分布加载失败',
      'dashboard.unit.count': '个'
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
      'common.loading': 'Loading…',
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
      'admin.menu.styleSearch': 'Style search',
      'admin.menu.influencer': 'Creators',
      'admin.menu.terminal': 'Devices',
      'admin.menu.platform': 'Platforms',
      'admin.menu.device': 'Terminals',
      'admin.menu.system': 'System',
      'admin.menu.settings': 'Settings',
      'admin.menu.user': 'Users',
      'admin.menu.clientLicense': 'Licenses',
      'admin.menu.clientVersion': 'Releases',
      'admin.menu.cache': 'Cache',
      'admin.menu.errors': 'Errors',
      'admin.menu.logout': 'Logout',

      'page.product.breadcrumb': 'Library / Products',
      'page.product.title': 'Products',
      'page.product.add': '+ Add product',
      'page.product.searchName': 'Search name',
      'page.product.totalVideos': 'Total videos',
      'page.product.downloadedVideos': 'Downloaded',
      'page.product.undownloadedVideos': 'Undownloaded',

      'page.distribute.breadcrumb': 'Library / Creator Links',
      'page.distribute.title': 'Creator Links',
      'page.distribute.add': '+ New link',
      'page.distribute.allProducts': 'All products',
      'page.distribute.hint': 'Creators open the link to get a random undownloaded video for the product. Once downloaded, it is globally marked and will not appear again.',
      'page.distribute.empty': 'No links',

      'page.influencer.breadcrumb': 'Library / Creators',
      'page.influencer.title': 'Creator directory',
      'page.influencer.hint': 'tiktok_id is the TikTok username (@handle). Columns may include handle, nickname, followers, region, contact. CSV, TXT, Excel supported.',
      'page.influencer.importBtn': 'Import / update',
      'page.influencer.searchPh': 'Search @handle or nickname',
      'page.influencer.statusFilter': 'Status',
      'page.influencer.status0': 'Pending',
      'page.influencer.status1': 'Active',
      'page.influencer.status2': 'Blocked',
      'page.influencer.colHandle': 'TikTok',
      'page.influencer.colNick': 'Nickname',
      'page.influencer.colFans': 'Followers',
      'page.influencer.colRegion': 'Region',
      'page.influencer.colStatus': 'Status',
      'page.influencer.colContact': 'Contact',
      'page.influencer.colUpdated': 'Updated',
      'page.influencer.importProgress': 'Import progress',
      'page.influencer.sampleCsv': 'Sample CSV',
      'page.influencer.editTitle': 'Edit creator',
      'page.influencer.deleteConfirm': 'Delete this creator? Linked creator links will be unlinked.',
      'page.influencer.colActions': 'Actions',
      'page.influencer.colAvatar': 'Avatar URL',

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
      ,
      // dashboard
      'page.dashboard.title': 'Dashboard',
      'dashboard.kpi.videosTotal': 'Total videos',
      'dashboard.kpi.downloaded': 'Downloaded',
      'dashboard.kpi.undownloaded': 'Undownloaded',
      'dashboard.kpi.downloadRate': 'Download rate',
      'dashboard.kpi.asof': 'As of {date}',
      'dashboard.kpi.target': 'Target {pct}%',
      'dashboard.kpi.pending': 'Pending',
      'dashboard.kpi.platforms': 'Platforms',
      'dashboard.kpi.devices': 'Devices',
      'dashboard.kpi.todayUpload': 'Today uploads',
      'dashboard.kpi.todayDownload': 'Today downloads',
      'dashboard.kpi.overview': 'Overview',
      'dashboard.kpi.yesterday': 'Yesterday',
      'dashboard.kpi.avg7Label': '7-day avg',
      'dashboard.action.view': 'View',
      'dashboard.action.filter': 'Filter',
      'dashboard.action.handle': 'Handle',
      'dashboard.action.list': 'List',
      'dashboard.action.errors': 'Errors',
      'dashboard.ops.title': 'Today summary',
      'dashboard.ops.todayUpload': 'Today uploads',
      'dashboard.ops.pending': 'Pending',
      'dashboard.ops.downloadRate': 'Download rate',
      'dashboard.ops.hint': 'Suggestion: prioritize undownloaded materials to improve download rate.',
      'dashboard.chart.trendsTitle30': 'Last 30 days trend (uploads bar + downloads line)',
      'dashboard.chart.days7': '7d',
      'dashboard.chart.days30': '30d',
      'dashboard.chart.trendsEmpty30': 'No significant data changes in last 30 days',
      'dashboard.chart.platformDist': 'Platform distribution',
      'dashboard.chart.errorsTrend': 'Download errors (last 7 days)',
      'dashboard.chart.openErrors': 'Open errors',
      'dashboard.chart.topErrors': 'Top errors',
      'dashboard.chart.productStockTop': 'Product stock (Top12)',
      'dashboard.chart.manageProduct': 'Manage products',
      'dashboard.chart.capacity': 'Storage',
      'dashboard.storage.uploadsFiles': 'uploads files',
      'dashboard.storage.cacheFiles': 'cache files',
      'dashboard.legend.upload': 'Uploads',
      'dashboard.legend.download': 'Downloads',
      'dashboard.platform.noData': 'No platform data',
      'dashboard.platform.sub': 'Total {total} · Downloaded {downloaded} · Undownloaded {undownloaded}',
      'dashboard.errors.download': 'Download errors',
      'dashboard.errors.cache': 'Cache errors',
      'dashboard.errors.other': 'Other',
      'dashboard.loadFail.trends': 'Failed to load trends',
      'dashboard.loadFail.platform': 'Failed to load platform distribution',
      'dashboard.loadFail.errorsTrend': 'Failed to load error trends',
      'dashboard.loadFail.product': 'Failed to load product distribution',
      'dashboard.unit.count': 'items'
    },
    vi: {
      'lang.zh': '中文',
      'lang.en': 'English',
      'common.ok': 'OK',
      'common.cancel': 'Hủy',
      'common.search': 'Tìm',
      'common.filter': 'Lọc',
      'common.reset': 'Đặt lại',
      'common.copy': 'Sao chép',
      'common.refresh': 'Làm mới',
      'common.statusAll': 'Tất cả',
      'common.loadingFailed': 'Tải thất bại',
      'common.loading': 'Đang tải…',
      'common.noData': 'Không có dữ liệu',
      'admin.menu.material': 'Thư viện',
      'admin.menu.influencer': 'Creator',
      'admin.menu.distribute': 'Link creator',
      'admin.menu.styleSearch': 'Tìm kiểu',
      'admin.menu.dashboard': 'Bảng điều khiển',
      'admin.menu.video': 'Video',
      'admin.menu.upload': 'Tải lên',
      'admin.menu.product': 'Sản phẩm',
      'page.influencer.breadcrumb': 'Thư viện / Creator',
      'page.influencer.title': 'Danh bạ creator',
      'page.influencer.hint': 'tiktok_id là tên TikTok (@handle). Cột: handle, biệt danh, follower, khu vực, liên hệ. Hỗ trợ CSV, TXT, Excel.',
      'page.influencer.importBtn': 'Nhập / cập nhật',
      'page.influencer.searchPh': 'Tìm @handle hoặc biệt danh',
      'page.influencer.statusFilter': 'Trạng thái',
      'page.influencer.status0': 'Chờ liên hệ',
      'page.influencer.status1': 'Đang hợp tác',
      'page.influencer.status2': 'Chặn',
      'page.influencer.colHandle': 'TikTok',
      'page.influencer.colNick': 'Biệt danh',
      'page.influencer.colFans': 'Follower',
      'page.influencer.colRegion': 'Khu vực',
      'page.influencer.colStatus': 'Trạng thái',
      'page.influencer.colContact': 'Liên hệ',
      'page.influencer.colUpdated': 'Cập nhật',
      'page.influencer.importProgress': 'Tiến trình nhập',
      'page.influencer.sampleCsv': 'CSV mẫu',
      'page.influencer.editTitle': 'Sửa creator',
      'page.influencer.deleteConfirm': 'Xóa creator này? Liên kết creator sẽ được gỡ.',
      'page.influencer.colActions': 'Thao tác',
      'page.influencer.colAvatar': 'URL ảnh đại diện'
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

