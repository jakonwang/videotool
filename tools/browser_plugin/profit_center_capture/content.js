(function () {
  'use strict';

  if (window.__profitPluginContentReady) {
    return;
  }
  window.__profitPluginContentReady = true;

  function safeErrorMessage(err) {
    if (!err) return 'capture_failed';
    if (typeof err === 'string') return err;
    if (err && err.message) return String(err.message);
    return 'capture_failed';
  }

  function captureNow() {
    if (!window.ProfitPluginParser || typeof window.ProfitPluginParser.captureFromDocument !== 'function') {
      throw new Error('parser_unavailable');
    }

    const captured = window.ProfitPluginParser.captureFromDocument(document, window.location.href);
    if (!captured || !captured.row) {
      throw new Error('empty_capture');
    }

    return {
      ok: true,
      row: captured.row,
      debug: captured.debug || {}
    };
  }

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (!message || message.type !== 'profit_plugin_capture') {
      return false;
    }

    try {
      sendResponse(captureNow());
    } catch (err) {
      sendResponse({
        ok: false,
        error: safeErrorMessage(err)
      });
    }

    return true;
  });
})();
