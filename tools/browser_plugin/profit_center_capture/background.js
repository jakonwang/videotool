chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message || message.type !== 'profit_plugin_fetch') {
    return false;
  }

  const method = String(message.method || 'GET').toUpperCase();
  const headers = Object.assign({}, message.headers || {});
  const body = typeof message.body === 'string' ? message.body : undefined;

  fetch(String(message.url || ''), { method, headers, body })
    .then(async (res) => {
      const text = await res.text();
      let json = null;
      try { json = JSON.parse(text); } catch (_) {}
      sendResponse({ ok: true, status: res.status, text, json });
    })
    .catch((err) => {
      sendResponse({ ok: false, status: 0, error: err && err.message ? err.message : 'fetch_failed' });
    });

  return true;
});
