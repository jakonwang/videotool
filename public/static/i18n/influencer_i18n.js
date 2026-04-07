/* Influencer and mobile H5 i18n
 * - default: vi
 * - query: ?ilang=zh|vi|en (NOT ?lang=)
 * - storage: localStorage('influencer_lang')
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'influencer_lang';
  var QUERY_KEY = 'ilang';

  function normalize(lang) {
    var s = String(lang || '').toLowerCase();
    if (s === 'zh' || s.indexOf('zh-') === 0 || s === 'cn' || s === 'zh_cn' || s === 'zh-cn') return 'zh';
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
      if (html) html.setAttribute('lang', l);
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
      'lang.zh': '中文',
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
      'influencer.downloadStartedNext': '已开始下载，正在切换下一条视频…',
      'influencer.empty': '暂无视频',
      'influencer.failedPrefix': '失败',

      'catalog.title': '客户图册',
      'catalog.subtitle': '移动端浏览款式，支持预定下单',
      'catalog.keywordPlaceholder': '输入款号或关键词',
      'catalog.categoryPlaceholder': '选择分类',
      'catalog.categoryAll': '全部分类',
      'catalog.search': '搜索',
      'catalog.reset': '重置',
      'catalog.loading': '加载中…',
      'catalog.loadMore': '加载更多',
      'catalog.noMore': '已到底部',
      'catalog.empty': '暂无款式数据',
      'catalog.loaded': '已加载 {loaded}/{total}',
      'catalog.currency': '¥',
      'catalog.priceLabel': '批发价',
      'catalog.minQty': '起批量 {qty}',
      'catalog.addToCart': '加入预定',
      'catalog.previewTitle': '图片预览',
      'catalog.close': '关闭',
      'catalog.cart': '预定清单',
      'catalog.cartEmpty': '预定清单为空',
      'catalog.clearCart': '清空',
      'catalog.submitReserve': '提交预定',
      'catalog.total': '合计',
      'catalog.qty': '数量',
      'catalog.remove': '移除',
      'catalog.contactTitle': '填写客户信息',
      'catalog.customerName': '客户姓名',
      'catalog.customerNamePh': '请输入姓名',
      'catalog.phone': '电话',
      'catalog.phonePh': '用于联系客户（可选）',
      'catalog.whatsapp': 'WhatsApp',
      'catalog.whatsappPh': '请输入 WhatsApp 号码（可选）',
      'catalog.zalo': 'Zalo',
      'catalog.zaloPh': '请输入 Zalo 号码（可选）',
      'catalog.contactHint': '电话 / WhatsApp / Zalo 至少填写一项',
      'catalog.cancel': '取消',
      'catalog.submit': '确认提交',
      'catalog.submitting': '提交中…',
      'catalog.orderCreated': '预定已提交',
      'catalog.orderNo': '预定单号',
      'catalog.customerNameRequired': '请填写客户姓名',
      'catalog.contactRequired': '请至少填写一个联系方式',
      'catalog.addedToCart': '已加入预定清单',
      'catalog.networkError': '网络异常，请稍后重试',
      'catalog.submitFailed': '提交失败'
    },
    vi: {
      'lang.zh': '中文',
      'lang.vi': 'Tiếng Việt',
      'lang.en': 'English',
      'common.loadingFailed': 'Tải thất bại',

      'influencer.title': 'Tải video',
      'influencer.header': 'Tải video',
      'influencer.loading': 'Đang tải…',
      'influencer.product': 'Sản phẩm',
      'influencer.openLink': 'Mở liên kết',
      'influencer.videoTitle': 'Tiêu đề',
      'influencer.copyTitle': 'Sao chép tiêu đề',
      'influencer.downloadCover': 'Tải ảnh bìa',
      'influencer.downloadVideo': 'Tải video',
      'influencer.copied': 'Đã sao chép',
      'influencer.downloadStartedCover': 'Đã bắt đầu tải ảnh bìa',
      'influencer.downloadStartedNext': 'Đã bắt đầu tải, đang chuyển video tiếp theo…',
      'influencer.empty': 'Không có video',
      'influencer.failedPrefix': 'Lỗi',

      'catalog.title': 'Bộ sưu tập mẫu',
      'catalog.subtitle': 'Xem mẫu trên di động và đặt trước',
      'catalog.keywordPlaceholder': 'Nhập mã mẫu hoặc từ khóa',
      'catalog.categoryPlaceholder': 'Chọn danh mục',
      'catalog.categoryAll': 'Tất cả danh mục',
      'catalog.search': 'Tìm kiếm',
      'catalog.reset': 'Đặt lại',
      'catalog.loading': 'Đang tải…',
      'catalog.loadMore': 'Tải thêm',
      'catalog.noMore': 'Đã hết dữ liệu',
      'catalog.empty': 'Không có dữ liệu mẫu',
      'catalog.loaded': 'Đã tải {loaded}/{total}',
      'catalog.currency': '¥',
      'catalog.priceLabel': 'Giá sỉ',
      'catalog.minQty': 'Tối thiểu {qty}',
      'catalog.addToCart': 'Thêm vào đặt trước',
      'catalog.previewTitle': 'Xem ảnh lớn',
      'catalog.close': 'Đóng',
      'catalog.cart': 'Danh sách đặt trước',
      'catalog.cartEmpty': 'Danh sách đặt trước đang trống',
      'catalog.clearCart': 'Xóa tất cả',
      'catalog.submitReserve': 'Gửi đặt trước',
      'catalog.total': 'Tổng cộng',
      'catalog.qty': 'Số lượng',
      'catalog.remove': 'Xóa',
      'catalog.contactTitle': 'Thông tin khách hàng',
      'catalog.customerName': 'Tên khách hàng',
      'catalog.customerNamePh': 'Nhập tên khách hàng',
      'catalog.phone': 'Điện thoại',
      'catalog.phonePh': 'Số điện thoại liên hệ (không bắt buộc)',
      'catalog.whatsapp': 'WhatsApp',
      'catalog.whatsappPh': 'Nhập số WhatsApp (không bắt buộc)',
      'catalog.zalo': 'Zalo',
      'catalog.zaloPh': 'Nhập số Zalo (không bắt buộc)',
      'catalog.contactHint': 'Điện thoại / WhatsApp / Zalo: cần ít nhất 1 mục',
      'catalog.cancel': 'Hủy',
      'catalog.submit': 'Xác nhận gửi',
      'catalog.submitting': 'Đang gửi…',
      'catalog.orderCreated': 'Đã tạo đơn đặt trước',
      'catalog.orderNo': 'Mã đơn',
      'catalog.customerNameRequired': 'Vui lòng nhập tên khách hàng',
      'catalog.contactRequired': 'Vui lòng nhập ít nhất một cách liên hệ',
      'catalog.addedToCart': 'Đã thêm vào danh sách đặt trước',
      'catalog.networkError': 'Lỗi mạng, vui lòng thử lại',
      'catalog.submitFailed': 'Gửi thất bại'
    },
    en: {
      'lang.zh': '中文',
      'lang.vi': 'Tiếng Việt',
      'lang.en': 'English',
      'common.loadingFailed': 'Load failed',

      'influencer.title': 'Creator Download',
      'influencer.header': 'Creator Download',
      'influencer.loading': 'Loading…',
      'influencer.product': 'Product',
      'influencer.openLink': 'Open link',
      'influencer.videoTitle': 'Title',
      'influencer.copyTitle': 'Copy title',
      'influencer.downloadCover': 'Download cover',
      'influencer.downloadVideo': 'Download video',
      'influencer.copied': 'Copied',
      'influencer.downloadStartedCover': 'Cover download started',
      'influencer.downloadStartedNext': 'Download started, loading next video…',
      'influencer.empty': 'No video available',
      'influencer.failedPrefix': 'Failed',

      'catalog.title': 'Customer Catalog',
      'catalog.subtitle': 'Mobile style browsing and reservation',
      'catalog.keywordPlaceholder': 'Search by code or keyword',
      'catalog.categoryPlaceholder': 'Select category',
      'catalog.categoryAll': 'All categories',
      'catalog.search': 'Search',
      'catalog.reset': 'Reset',
      'catalog.loading': 'Loading…',
      'catalog.loadMore': 'Load more',
      'catalog.noMore': 'No more items',
      'catalog.empty': 'No styles found',
      'catalog.loaded': 'Loaded {loaded}/{total}',
      'catalog.currency': '¥',
      'catalog.priceLabel': 'Wholesale',
      'catalog.minQty': 'Min {qty}',
      'catalog.addToCart': 'Add to reservation',
      'catalog.previewTitle': 'Image preview',
      'catalog.close': 'Close',
      'catalog.cart': 'Reservation list',
      'catalog.cartEmpty': 'Reservation list is empty',
      'catalog.clearCart': 'Clear',
      'catalog.submitReserve': 'Submit reservation',
      'catalog.total': 'Total',
      'catalog.qty': 'Qty',
      'catalog.remove': 'Remove',
      'catalog.contactTitle': 'Customer info',
      'catalog.customerName': 'Customer name',
      'catalog.customerNamePh': 'Enter customer name',
      'catalog.phone': 'Phone',
      'catalog.phonePh': 'Phone number (optional)',
      'catalog.whatsapp': 'WhatsApp',
      'catalog.whatsappPh': 'WhatsApp number (optional)',
      'catalog.zalo': 'Zalo',
      'catalog.zaloPh': 'Zalo ID or phone (optional)',
      'catalog.contactHint': 'Phone / WhatsApp / Zalo: fill at least one',
      'catalog.cancel': 'Cancel',
      'catalog.submit': 'Submit',
      'catalog.submitting': 'Submitting…',
      'catalog.orderCreated': 'Reservation submitted',
      'catalog.orderNo': 'Order No.',
      'catalog.customerNameRequired': 'Customer name is required',
      'catalog.contactRequired': 'At least one contact is required',
      'catalog.addedToCart': 'Added to reservation list',
      'catalog.networkError': 'Network error, please retry',
      'catalog.submitFailed': 'Submit failed'
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
