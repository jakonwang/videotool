// Admin global helpers used by SSR pages.
// Keep this file free of template syntax to avoid view compiler issues.
(function () {
  'use strict';

  function onReady(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  onReady(function () {
    try {
      if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
      }
    } catch (e) {}

    // Sidebar footer stats
    try {
      function formatBytes(bytes) {
        var b = parseInt(bytes || 0, 10);
        if (!b) return '0 B';
        var u = ['B', 'KB', 'MB', 'GB', 'TB'];
        var i = 0;
        var v = b;
        while (v >= 1024 && i < u.length - 1) {
          v /= 1024;
          i++;
        }
        return v.toFixed(i === 0 ? 0 : 2) + ' ' + u[i];
      }

      if (window.jQuery) {
        window.jQuery.get('/admin.php/stats/overview').done(function (res) {
          try {
            if (res && res.code === 0 && res.data) {
              window.jQuery('#sbDeviceCount').text(res.data.devices ?? '—');
            }
          } catch (e) {}
        });
        window.jQuery.get('/admin.php/stats/storageUsage').done(function (res) {
          try {
            if (res && res.code === 0 && res.data && res.data.uploads) {
              window.jQuery('#sbUploadsSize').text(formatBytes(res.data.uploads.bytes));
            }
          } catch (e) {}
        });
      }
    } catch (e) {}

    // Sidebar auto expand group by URL
    try {
      if (!window.jQuery) return;
      var $ = window.jQuery;
      var path = (window.location.pathname || '').replace(/\/+$/, '');
      var full = window.location.href || '';

      function syncGroupHead($collapseEl) {
        var id = $collapseEl.attr('id');
        if (!id) return;
        var $head = $('.admin-sidenav-group-head[data-target=\"#' + id + '\"]');
        if ($head.length) {
          $head.removeClass('collapsed').addClass('admin-sidenav-group-head--hot');
        }
      }

      var $collapses = $('.admin-sidenav-subwrap.collapse');
      if (!$collapses.length) return;

      var matched = false;
      $('.admin-sidenav-sublink').each(function () {
        var href = (($(this).attr('href') || '') + '').replace(/\/+$/, '');
        if (!href) return;
        var hit = (path === href) || (path && href && path.indexOf(href) !== -1) || (full && full.indexOf(href) !== -1);
        if (hit) {
          var $collapse = $(this).closest('.collapse');
          if ($collapse.length) {
            $collapse.collapse('show');
            syncGroupHead($collapse);
            matched = true;
            return false;
          }
        }
      });

      if (!matched) {
        $collapses.each(function () {
          var $c = $(this);
          if ($c.hasClass('show')) {
            syncGroupHead($c);
            return false;
          }
        });
      }
    } catch (e) {}

    // Logout
    try {
      if (!window.jQuery) return;
      var $ = window.jQuery;
      function doLogout() {
        $.post('/admin.php/auth/logout', {}, function () {
          window.location.href = '/admin.php/auth/login';
        }).fail(function () {
          window.location.href = '/admin.php/auth/logout';
        });
      }
      $('#btnAdminLogout').on('click', doLogout);
      $('#btnAdminLogout2').on('click', doLogout);
    } catch (e) {}

    // i18n buttons
    try {
      if (!window.AppI18n) return;
      var lang = window.AppI18n.getLang('zh');
      window.AppI18n.applyDom(document);

      function setActive() {
        var pairs = [
          ['btnLangZh', 'zh'], ['btnLangEn', 'en'], ['btnLangVi', 'vi'],
          ['btnLangZhTop', 'zh'], ['btnLangEnTop', 'en'], ['btnLangViTop', 'vi']
        ];
        pairs.forEach(function (p) {
          var el = document.getElementById(p[0]);
          if (el) el.classList.toggle('active', lang === p[1]);
        });
      }

      function bind(id, targetLang) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', function () { window.AppI18n.setLang(targetLang); });
      }

      bind('btnLangZh', 'zh');
      bind('btnLangEn', 'en');
      bind('btnLangVi', 'vi');
      bind('btnLangZhTop', 'zh');
      bind('btnLangEnTop', 'en');
      bind('btnLangViTop', 'vi');
      setActive();
    } catch (e) {}
  });

  // AdminUI global (requires jQuery + bootstrap toast/modal)
  onReady(function () {
    try {
      if (!window.jQuery) return;
      var $ = window.jQuery;
      var toastEl = $('#globalToast');
      var toastTitle = $('#toastTitle');
      var toastBody = $('#toastBody');
      var confirmModal = $('#confirmModal');
      var confirmMessage = $('#confirmMessage');
      var confirmOkBtn = $('#confirmOkBtn');
      var confirmCallback = null;

      confirmOkBtn.on('click', function () {
        confirmModal.modal('hide');
        if (typeof confirmCallback === 'function') {
          confirmCallback();
        }
      });

      window.AdminUI = {
        showToast: function (type, message, title) {
          var typeMap = {
            success: { icon: 'fa-check-circle', title: title || '成功' },
            error: { icon: 'fa-times-circle', title: title || '错误' },
            warning: { icon: 'fa-exclamation-triangle', title: title || '提醒' },
            info: { icon: 'fa-info-circle', title: title || '提示' }
          };
          var meta = typeMap[type] || typeMap.info;
          toastTitle.html('<i class=\"fas ' + meta.icon + ' mr-1\"></i>' + meta.title);
          toastBody.text(message);
          toastEl.toast('show');
        },
        confirm: function (message, onOk) {
          confirmMessage.text(message || '确认执行该操作吗？');
          confirmCallback = onOk;
          confirmModal.modal('show');
        }
      };
    } catch (e) {}
  });
})();

