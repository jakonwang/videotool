/* Influencer i18n (independent from admin).
 * - default: vi
 * - query: ?ilang=vi|en (NOT ?lang=)
 * - storage: localStorage('influencer_lang')
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'influencer_lang';
  var QUERY_KEY = 'ilang';

  function normalize(lang) {
    var s = String(lang || '').toLowerCase();
    if (s === 'vi' || s.indexOf('vi-') === 0) return 'vi';
    if (s === 'en' || s.indexOf('en-') === 0) return 'en';
    return '';
  }

  function getQueryLang() {
    try {
      var sp = new URLSearchParams(window.location.search || '');
      return normalize(sp.get(QUERY_KEY));
    } catch (e) { return ''; }
  }

  function getSavedLang() {
    try { return normalize(window.localStorage.getItem(STORAGE_KEY)); } catch (e) { return ''; }
  }

  function getNavigatorLang() {
    try { return normalize(navigator.language || navigator.userLanguage || ''); } catch (e) { return ''; }
  }

  function getLang(defaultLang) {
    return getQueryLang() || getSavedLang() || getNavigatorLang() || normalize(defaultLang) || 'vi';
  }

  function setLang(lang) {
    var l = normalize(lang) || 'vi';
    try { window.localStorage.setItem(STORAGE_KEY, l); } catch (e) {}
    try {
      var sp = new URLSearchParams(window.location.search || '');
      sp.set(QUERY_KEY, l);
      var newUrl = window.location.pathname + '?' + sp.toString() + (window.location.hash || '');
      window.location.href = newUrl;
    } catch (e) {
      window.location.reload();
    }
  }

  function applyHtmlLang(lang) {
    try {
      var l = normalize(lang) || getLang('vi');
      var html = document && document.documentElement;
      if (html) html.setAttribute('lang', l === 'en' ? 'en' : 'vi');
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
    vi: {
      'lang.vi': 'Tiếng Việt',
      'lang.en': 'English',
      'common.loadingFailed': 'Tải thất bại',

      'influencer.title': 'Tải video',
      'influencer.header': '📦 Tải video',
      'influencer.loading': 'Đang tải…',
      'influencer.product': 'Sản phẩm',
      'influencer.openLink': 'Mở liên kết',
      'influencer.videoTitle': 'Tiêu đề',
      'influencer.copyTitle': 'Sao chép tiêu đề',
      'influencer.downloadCover': 'Tải ảnh bìa',
      'influencer.downloadVideo': 'Tải video',
      'influencer.copied': 'Đã sao chép',
      'influencer.downloadStartedCover': 'Đã bắt đầu tải ảnh bìa',
      'influencer.downloadStartedNext': 'Đã bắt đầu tải. Đang lấy video tiếp theo…',
      'influencer.empty': 'Không có video',
      'influencer.failedPrefix': 'Lỗi'
    },
    en: {
      'lang.vi': 'Tiếng Việt',
      'lang.en': 'English',
      'common.loadingFailed': 'Load failed',

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
      'influencer.failedPrefix': 'Failed'
    },
    // aliases (normalize currently returns vi/en only; keep for future extension)
    zh: {
      'lang.vi': 'Tiếng Việt',
      'lang.en': 'English',
      'common.loadingFailed': '加载失败',
      'influencer.title': '达人下载',
      'influencer.header': '达人下载',
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
      'influencer.failedPrefix': '失败'
    }
  };

  function t(key, vars, optLang) {
    var lang = normalize(optLang) || getLang('vi');
    var dict = DICT[lang] || DICT.vi;
    var val = dict[key];
    if (val === undefined || val === null) {
      val = (DICT.vi && DICT.vi[key] !== undefined) ? DICT.vi[key] : key;
    }
    return fmt(val, vars);
  }

  window.InfluencerI18n = {
    getLang: getLang,
    setLang: setLang,
    t: t,
    applyHtmlLang: applyHtmlLang,
    _dict: DICT
  };
})();

