(function () {
  'use strict';

  if (window.__profitPluginContentReady) return;
  window.__profitPluginContentReady = true;

  const STYLE_ID = 'pcp-creative-opt-style';
  const CREATIVE_STORE_KEY = 'profit_plugin_creative_opt_v1';
  const CONFIG_STORE_KEY = 'profit_plugin_config_v1';
  const PANEL_TAB_KEY = 'profit_plugin_drawer_tab_v1';

  const state = {
    context: null,
    rows: [],
    rowMapByVideo: {},
    observer: null,
    refreshTimer: null,
    lastFingerprint: '',
    lastScheduleAt: 0,
    refreshQueued: false,
    lastUrl: String(window.location.href || ''),
    lastRefreshAt: 0,
    panelHost: null,
    panelConfig: { apiBase: '', token: '' },
    panelBootstrap: null,
    panelRecommendation: null,
    panelTab: 'overview',
    panelMaterialQuery: '',
    panelMaterialFilter: 'all',
    panelMaterialSort: 'roi_desc',
    panelSelectedRowKeys: {},
    panelExpandedRowKeys: {},
    panelSyncScope: 'all',
    panelProfitRows: [],
    panelProfitSeq: 1,
    panelProfitSubmitting: false,
    panelOpen: false
  };

  const defaultChannels = [
    { value: 'video', label: '视频' },
    { value: 'live', label: '直播' },
    { value: 'influencer', label: '达人' }
  ];
  const defaultCurrencies = ['USD', 'VND', 'CNY'];

  function safeErrorMessage(err) {
    if (!err) return 'capture_failed';
    if (typeof err === 'string') return err;
    if (err && err.message) return String(err.message);
    return 'capture_failed';
  }

  function normalizeLabel(raw) {
    const label = String(raw || '').trim().toLowerCase();
    if (label === 'excellent' || label === 'scale') return 'excellent';
    if (label === 'optimize') return 'optimize';
    if (label === 'observe' || label === 'potential' || label === 'keep') return 'observe';
    if (label === 'garbage' || label === 'bad' || label === 'exclude_candidate') return 'garbage';
    if (label === 'ignore') return 'ignore';
    return 'observe';
  }

  function labelText(label) {
    const key = normalizeLabel(label);
    if (key === 'excellent') return '放量素材';
    if (key === 'optimize') return '优化素材';
    if (key === 'garbage') return '垃圾素材';
    if (key === 'ignore') return '忽略';
    return '观察中';
  }

  function storageGet(key, done) {
    chrome.storage.local.get([key], (res) => done(res || {}));
  }

  function loadCreativeStore(done) {
    storageGet(CREATIVE_STORE_KEY, (res) => {
      const raw = res[CREATIVE_STORE_KEY];
      done(raw && typeof raw === 'object' ? Object.assign({}, raw) : {});
    });
  }

  function contextKey(context) {
    const host = String(context && context.host || window.location.hostname || '').trim().toLowerCase();
    const campaignId = String(context && context.campaign_id || '').trim() || 'unknown_campaign';
    const dateRange = String(context && context.date_range || '').trim() || 'unknown_date';
    return `${host}|${campaignId}|${dateRange}`;
  }

  function decisionKey(context, videoId) {
    return `${contextKey(context)}|${String(videoId || '').trim()}`;
  }

  function effectiveLabel(row) {
    if (row && row.manual_label) return normalizeLabel(row.manual_label);
    return normalizeLabel(row && row.auto_label);
  }

  function escapeHtml(input) {
    return String(input == null ? '' : input)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function ensureStyle() {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      :root{
        --pcp-font-mini:12px;
        --pcp-font-small:13px;
        --pcp-font-base:14px;
        --pcp-font-head:16px;
        --pcp-font-title:18px;
      }
      .pcp-boost-tag-wrap{
        display:inline-flex;
        align-items:center;
        margin-right:8px;
        vertical-align:middle;
        max-width:120px;
      }
      .pcp-el-tag{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        height:22px;
        padding:0 10px;
        font-size:var(--pcp-font-small);
        font-weight:600;
        line-height:20px;
        border-radius:4px;
        border:1px solid transparent;
        white-space:nowrap;
      }
      .pcp-el-tag.excellent{color:#389e0d;background:#f6ffed;border-color:#b7eb8f;}
      .pcp-el-tag.optimize{color:#d46b08;background:#fff7e6;border-color:#ffd591;}
      .pcp-el-tag.observe{color:#1d39c4;background:#f0f5ff;border-color:#adc6ff;}
      .pcp-el-tag.garbage{color:#cf1322;background:#fff1f0;border-color:#ffa39e;}
      .pcp-el-tag.ignore{color:#8c8c8c;background:#fafafa;border-color:#d9d9d9;}
      .pcp-el-tag.manual::after{
        content:'手动';
        margin-left:6px;
        padding-left:6px;
        border-left:1px solid rgba(0,0,0,.15);
        font-size:10px;
      }
      .pcp-inline-launcher{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        margin:10px 0 12px;
        padding:12px 14px;
        border-radius:10px;
        border:1px solid #dbeafe;
        background:linear-gradient(180deg,#f8fbff 0%,#eef5ff 100%);
      }
      .pcp-inline-launcher .pcp-inline-title{
        color:#1e3a8a;
        font-size:var(--pcp-font-base);
        font-weight:700;
      }
      .pcp-inline-launcher .pcp-inline-sub{
        color:#475569;
        font-size:var(--pcp-font-small);
      }
      .pcp-inline-btn{
        height:30px;
        padding:0 14px;
        border:none;
        border-radius:8px;
        background:#2563eb;
        color:#fff;
        font-size:var(--pcp-font-small);
        font-weight:700;
        cursor:pointer;
      }
      .pcp-inline-btn:hover{background:#1d4ed8;}
      .pcp-modal-mask{
        position:fixed;
        inset:0;
        z-index:2147483646;
        background:rgba(15,23,42,.48);
        display:none;
        align-items:center;
        justify-content:center;
        padding:20px;
      }
      .pcp-modal-mask.show{display:flex;}
      .pcp-modal{
        width:min(760px,96vw);
        max-height:82vh;
        overflow:auto;
        border-radius:14px;
        border:1px solid #cbd5e1;
        background:#fff;
        box-shadow:0 30px 80px rgba(2,6,23,.32);
      }
      .pcp-modal-head{
        display:flex;
        justify-content:space-between;
        align-items:center;
        padding:14px 16px;
        border-bottom:1px solid #e2e8f0;
      }
      .pcp-modal-head h3{
        margin:0;
        color:#0f172a;
        font-size:var(--pcp-font-title);
      }
      .pcp-modal-close{
        border:none;
        background:transparent;
        color:#64748b;
        font-size:18px;
        cursor:pointer;
      }
      .pcp-modal-body{padding:14px 16px;}
      .pcp-modal-form{
        display:grid;
        grid-template-columns:1.25fr 1fr 1fr;
        gap:10px;
        margin-bottom:12px;
      }
      .pcp-modal-field{
        display:flex;
        flex-direction:column;
        gap:6px;
      }
      .pcp-modal-field span{
        color:#64748b;
        font-size:var(--pcp-font-small);
        line-height:1;
      }
      .pcp-modal-field select,
      .pcp-modal-field input{
        height:32px;
        border:1px solid #cbd5e1;
        border-radius:8px;
        padding:0 10px;
        font-size:var(--pcp-font-base);
        color:#0f172a;
        background:#fff;
      }
      .pcp-modal-field select{
        color:#0f172a !important;
      }
      .pcp-modal-field select option{
        color:#0f172a !important;
        background:#fff !important;
      }
      .pcp-modal-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:10px;
        margin-bottom:12px;
      }
      .pcp-modal-kpi{
        border:1px solid #e2e8f0;
        border-radius:10px;
        background:#f8fafc;
        padding:10px;
      }
      .pcp-modal-kpi span{display:block;color:#64748b;font-size:var(--pcp-font-small);margin-bottom:6px;}
      .pcp-modal-kpi strong{display:block;color:#0f172a;font-size:22px;line-height:1.1;}
      .pcp-modal-actions{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        margin-bottom:12px;
      }
      .pcp-modal-actions button{
        height:32px;
        padding:0 12px;
        border:1px solid #cbd5e1;
        border-radius:8px;
        background:#fff;
        color:#0f172a;
        font-size:var(--pcp-font-base);
        cursor:pointer;
      }
      .pcp-modal-actions button.primary{
        border-color:#2563eb;
        background:#2563eb;
        color:#fff;
      }
      .pcp-modal-note{
        border:1px dashed #bfdbfe;
        border-radius:10px;
        background:#f8fbff;
        color:#334155;
        line-height:1.7;
        font-size:var(--pcp-font-base);
        padding:10px 12px;
        margin-bottom:10px;
      }
      .pcp-quick-list{
        margin:8px 0 0;
        padding-left:18px;
        color:#334155;
        font-size:var(--pcp-font-small);
        line-height:1.6;
      }
      .pcp-quick-list li{margin:2px 0;}
      .pcp-inline-rec{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:10px;
      }
      .pcp-inline-rec-card{
        border:1px solid #e2e8f0;
        border-radius:10px;
        background:#fff;
        padding:10px;
      }
      .pcp-inline-rec-card h4{
        margin:0 0 8px;
        color:#0f172a;
        font-size:var(--pcp-font-base);
        font-weight:700;
      }
      .pcp-inline-rec-card p{
        margin:0;
        color:#475569;
        font-size:var(--pcp-font-small);
        line-height:1.7;
      }
      .pcp-inline-tag{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:44px;
        height:22px;
        padding:0 8px;
        border-radius:999px;
        font-size:var(--pcp-font-small);
        font-weight:700;
      }
      .pcp-inline-tag.ok{color:#166534;background:#dcfce7;border:1px solid #86efac;}
      .pcp-inline-tag.warn{color:#92400e;background:#fef3c7;border:1px solid #fcd34d;}
      .pcp-inline-tag.bad{color:#b91c1c;background:#fee2e2;border:1px solid #fca5a5;}
      .pcp-inline-split{
        margin:8px 0 0;
        padding:0;
        list-style:none;
        display:grid;
        gap:6px;
      }
      .pcp-inline-split li{
        display:flex;
        align-items:center;
        gap:8px;
        font-size:var(--pcp-font-small);
        color:#475569;
      }
      .pcp-inline-split li .bar{
        flex:1;
        height:7px;
        border-radius:999px;
        background:#e2e8f0;
        overflow:hidden;
      }
      .pcp-inline-split li .bar span{
        display:block;
        height:100%;
        border-radius:inherit;
        background:linear-gradient(90deg,#2563eb,#06b6d4);
      }
      .pcp-side-entry{
        position:fixed;
        right:0;
        top:46%;
        transform:translateY(-50%);
        z-index:2147483644;
        width:44px;
        min-height:132px;
        border:none;
        border-radius:12px 0 0 12px;
        background:linear-gradient(180deg,#1d4ed8,#0284c7);
        color:#fff;
        font-size:var(--pcp-font-small);
        font-weight:800;
        line-height:1.3;
        letter-spacing:.5px;
        box-shadow:0 14px 30px rgba(2,6,23,.32);
        cursor:pointer;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:10px 8px;
        writing-mode:vertical-rl;
        text-orientation:mixed;
      }
      .pcp-side-entry:hover{
        right:0;
        filter:brightness(1.04);
      }
      .pcp-drawer{
        position:fixed;
        right:10px;
        top:5vh;
        width:min(460px,94vw);
        height:90vh;
        z-index:2147483645;
        border:1px solid #cbd5e1;
        border-radius:14px;
        background:#fff;
        box-shadow:0 36px 80px rgba(2,6,23,.35);
        display:flex;
        flex-direction:column;
        transform:translateX(calc(100% + 18px));
        transition:transform .28s ease;
      }
      .pcp-drawer.open{transform:translateX(0);}
      .pcp-full-mask{
        position:fixed;
        inset:0;
        z-index:2147483645;
        background:rgba(15,23,42,.45);
        backdrop-filter:blur(4px);
        display:none;
        align-items:center;
        justify-content:center;
        padding:18px;
      }
      .pcp-full-mask.show{display:flex;}
      .pcp-drawer-hero{
        margin:10px 12px 4px;
        border-radius:14px;
        padding:12px 12px 10px;
        background:
          radial-gradient(90% 140% at 0% 0%, rgba(147,197,253,.24), transparent 44%),
          radial-gradient(110% 140% at 100% 100%, rgba(56,189,248,.22), transparent 46%),
          linear-gradient(135deg,#0b3d91,#0e6abf);
        color:#eff6ff;
        box-shadow:0 10px 24px rgba(2,6,23,.18);
      }
      .pcp-drawer-hero h4{
        margin:0 0 6px;
        font-size:var(--pcp-font-head);
        line-height:1.25;
        font-weight:800;
      }
      .pcp-drawer-hero p{
        margin:0;
        color:#dbeafe;
        font-size:var(--pcp-font-small);
        line-height:1.5;
      }
      .pcp-drawer-formula{
        margin-top:8px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border:1px solid rgba(191,219,254,.42);
        border-radius:999px;
        padding:3px 10px;
        font-size:var(--pcp-font-small);
        color:#e2e8f0;
        background:rgba(15,23,42,.2);
        font-weight:700;
      }
      .pcp-drawer-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        padding:12px 14px;
        border-bottom:1px solid #e2e8f0;
      }
      .pcp-drawer-head h3{
        margin:0;
        color:#0f172a;
        font-size:var(--pcp-font-head);
      }
      .pcp-drawer-close{
        border:none;
        background:transparent;
        color:#64748b;
        font-size:18px;
        cursor:pointer;
      }
      .pcp-drawer-tabs{
        display:flex;
        gap:8px;
        padding:10px 14px;
        border-bottom:1px solid #e2e8f0;
      }
      .pcp-drawer-tab{
        display:inline-flex;
        align-items:center;
        gap:6px;
        height:30px;
        padding:0 12px;
        border:1px solid #cbd5e1;
        border-radius:999px;
        background:#fff;
        color:#0f172a;
        font-size:var(--pcp-font-base);
        cursor:pointer;
      }
      .pcp-tab-badge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:18px;
        height:18px;
        border-radius:999px;
        padding:0 5px;
        font-size:var(--pcp-font-mini);
        font-weight:700;
        color:#334155;
        background:#e2e8f0;
      }
      .pcp-drawer-tab.active .pcp-tab-badge{
        color:#1e3a8a;
        background:#dbeafe;
      }
      .pcp-drawer-tab.active{
        border-color:#2563eb;
        color:#1d4ed8;
        background:#eff6ff;
        font-weight:700;
      }
      .pcp-drawer-body{
        flex:1;
        overflow:auto;
        padding:12px 14px;
      }
      .pcp-tab-panel{display:none;}
      .pcp-tab-panel.active{display:block;}
      .pcp-config-grid{
        display:grid;
        gap:10px;
      }
      .pcp-config-grid label{
        display:flex;
        flex-direction:column;
        gap:6px;
        color:#64748b;
        font-size:var(--pcp-font-small);
      }
      .pcp-config-grid input{
        height:32px;
        border:1px solid #cbd5e1;
        border-radius:8px;
        padding:0 10px;
        font-size:var(--pcp-font-base);
        color:#0f172a;
      }
      .pcp-config-actions{
        display:flex;
        gap:8px;
        margin-top:8px;
      }
      .pcp-config-actions button{
        height:32px;
        border:1px solid #cbd5e1;
        border-radius:8px;
        padding:0 12px;
        background:#fff;
        cursor:pointer;
      }
      .pcp-config-actions .primary{
        border-color:#2563eb;
        color:#fff;
        background:#2563eb;
      }
      .pcp-embedded-launcher{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        margin:10px 0 14px;
        padding:12px 14px;
        border-radius:14px;
        border:1px solid rgba(148,163,184,.35);
        background:
          radial-gradient(120% 120% at 0% 0%, rgba(191,219,254,.25), transparent 45%),
          radial-gradient(120% 120% at 100% 100%, rgba(125,211,252,.2), transparent 45%),
          rgba(255,255,255,.92);
        backdrop-filter: blur(8px);
        box-shadow:0 10px 30px rgba(15,23,42,.08);
      }
      .pcp-embedded-main{display:flex;flex-direction:column;gap:4px;min-width:0;}
      .pcp-embedded-title{font-size:var(--pcp-font-base);font-weight:800;color:#0f172a;}
      .pcp-embedded-sub{font-size:var(--pcp-font-small);color:#64748b;line-height:1.45;}
      .pcp-embedded-stats{display:flex;flex-wrap:wrap;gap:8px;}
      .pcp-embedded-chip{
        padding:2px 8px;
        border-radius:999px;
        border:1px solid #cbd5e1;
        background:#fff;
        color:#334155;
        font-size:var(--pcp-font-small);
        font-weight:700;
      }
      .pcp-embedded-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
      .pcp-embedded-actions button{
        height:30px;
        border-radius:10px;
        border:1px solid #cbd5e1;
        background:#fff;
        color:#0f172a;
        padding:0 12px;
        font-size:var(--pcp-font-base);
        font-weight:700;
        cursor:pointer;
      }
      .pcp-embedded-actions .primary{
        border-color:#2563eb;
        color:#fff;
        background:linear-gradient(135deg,#2563eb,#0284c7);
      }
      .pcp-side-entry{
        border-left:1px solid rgba(255,255,255,.35);
        border-top:1px solid rgba(255,255,255,.25);
      }
      .pcp-drawer{
        width:min(1120px,96vw);
        height:92vh;
        right:auto;
        top:auto;
        transform:none;
        border:none;
        border-radius:18px;
        background:rgba(247,249,252,.96);
        backdrop-filter: blur(16px);
        box-shadow:0 24px 60px rgba(15,23,42,.28);
        overflow:hidden;
      }
      .pcp-drawer-head{
        padding:14px 16px;
        border-bottom:1px solid rgba(148,163,184,.28);
      }
      .pcp-drawer-head h3{
        font-size:var(--pcp-font-head);
        font-weight:800;
      }
      .pcp-drawer-tabs{
        padding:8px 12px;
        border-bottom:1px solid rgba(148,163,184,.24);
        background:rgba(255,255,255,.65);
      }
      .pcp-drawer-tab{
        background:transparent;
        border-color:transparent;
        color:#475569;
      }
      .pcp-drawer-tab.active{
        background:#ffffff;
        color:#0f172a;
        border-color:#dbeafe;
        box-shadow:0 6px 16px rgba(37,99,235,.14);
      }
      .pcp-drawer-body{padding:14px;}
      .pcp-modal-kpi{
        border:none;
        border-radius:12px;
        background:#ffffff;
        box-shadow:0 8px 18px rgba(15,23,42,.06);
      }
      .pcp-inline-rec-card{
        border:none;
        border-radius:12px;
        box-shadow:0 8px 18px rgba(15,23,42,.06);
      }
      .pcp-modal-note{
        border:1px dashed rgba(59,130,246,.35);
        border-radius:12px;
        background:#f8fbff;
      }
      .pcp-material-tools{
        display:grid;
        grid-template-columns:1fr 120px 130px 80px;
        gap:8px;
        margin-bottom:10px;
      }
      .pcp-material-tools input,
      .pcp-material-tools select{
        height:32px;
        border:1px solid #cbd5e1;
        border-radius:10px;
        padding:0 10px;
        background:#fff;
        color:#0f172a;
        font-size:var(--pcp-font-base);
      }
      .pcp-material-wrap{
        border-radius:12px;
        background:transparent;
        box-shadow:none;
        overflow:visible;
      }
      .pcp-material-head{
        display:none;
      }
      .pcp-material-body{
        max-height:52vh;
        overflow:auto;
        display:grid;
        gap:10px;
      }
      .pcp-material-row{
        display:grid;
        grid-template-columns:minmax(240px,1.6fr) minmax(240px,1fr);
        gap:10px;
        align-items:stretch;
        padding:0;
        border:none;
        background:#fff;
        border-radius:12px;
        box-shadow:0 8px 20px rgba(15,23,42,.08);
      }
      .pcp-material-row:hover{box-shadow:0 12px 26px rgba(15,23,42,.12);}
      .pcp-material-main,
      .pcp-material-side{
        padding:12px;
      }
      .pcp-material-main{
        border-right:1px solid rgba(226,232,240,.9);
      }
      .pcp-material-top{
        display:flex;
        align-items:flex-start;
        gap:10px;
      }
      .pcp-material-check{
        width:15px;
        height:15px;
        cursor:pointer;
        margin-top:2px;
      }
      .pcp-material-id{
        font-family:Consolas,Monaco,monospace;
        font-size:var(--pcp-font-small);
        color:#475569;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }
      .pcp-material-title{
        min-width:0;
        color:#0f172a;
        font-size:var(--pcp-font-base);
        line-height:1.45;
      }
      .pcp-material-title strong{
        display:block;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }
      .pcp-material-title span{
        display:block;
        color:#64748b;
        font-size:var(--pcp-font-small);
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }
      .pcp-material-meta{
        display:flex;
        flex-wrap:wrap;
        align-items:center;
        gap:6px;
        margin-top:4px;
      }
      .pcp-material-meta .chip{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        height:20px;
        border-radius:999px;
        padding:0 7px;
        font-size:var(--pcp-font-mini);
        font-weight:700;
        border:1px solid #cbd5e1;
        background:#f8fafc;
        color:#475569;
      }
      .pcp-material-meta .chip.boost{background:#eff6ff;border-color:#bfdbfe;color:#1e40af;}
      .pcp-material-meta .chip.nonboost{background:#f8fafc;border-color:#cbd5e1;color:#475569;}
      .pcp-material-meta .chip.problem{background:#fffbeb;border-color:#fde68a;color:#92400e;}
      .pcp-metrics-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
        margin-bottom:10px;
      }
      .pcp-metric-card{
        border:1px solid #e2e8f0;
        border-radius:10px;
        background:#f8fafc;
        padding:8px 9px;
      }
      .pcp-metric-card span{
        display:block;
        color:#64748b;
        font-size:var(--pcp-font-mini);
        margin-bottom:4px;
      }
      .pcp-metric-card strong{
        display:block;
        color:#0f172a;
        font-size:var(--pcp-font-base);
        line-height:1.2;
      }
      .pcp-material-actions{
        display:flex;
        justify-content:flex-end;
        gap:6px;
        flex-wrap:wrap;
      }
      .pcp-material-detail{
        grid-column:1 / -1;
        border-top:1px dashed #dbeafe;
        background:#f8fbff;
        border-radius:0 0 12px 12px;
        padding:10px 12px 12px;
      }
      .pcp-detail-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:8px;
        margin-bottom:8px;
      }
      .pcp-detail-item{
        border:1px solid #dbeafe;
        border-radius:10px;
        background:#fff;
        padding:7px 8px;
      }
      .pcp-detail-item span{
        display:block;
        font-size:var(--pcp-font-mini);
        color:#64748b;
        margin-bottom:4px;
      }
      .pcp-detail-item strong{
        display:block;
        font-size:var(--pcp-font-small);
        color:#0f172a;
      }
      .pcp-detail-actions{
        margin:0;
        padding-left:18px;
        color:#334155;
        font-size:var(--pcp-font-small);
        line-height:1.6;
      }
      .pcp-detail-actions li{margin:2px 0;}
      .pcp-act{
        height:24px;
        border-radius:999px;
        border:1px solid #cbd5e1;
        background:#fff;
        color:#334155;
        padding:0 8px;
        font-size:var(--pcp-font-small);
        cursor:pointer;
      }
      .pcp-act.active{
        border-color:#2563eb;
        color:#1d4ed8;
        background:#eff6ff;
      }
      .pcp-act.danger.active{
        border-color:#ef4444;
        color:#b91c1c;
        background:#fee2e2;
      }
      .pcp-material-empty{
        padding:18px 12px;
        color:#64748b;
        font-size:var(--pcp-font-base);
        text-align:center;
      }
      .pcp-material-bulk{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        margin-bottom:10px;
      }
      .pcp-material-bulk .pcp-act{height:28px;}
      .pcp-material-selected{
        margin-left:auto;
        font-size:var(--pcp-font-base);
        color:#475569;
        align-self:center;
      }
      .pcp-sop-grid{
        display:grid;
        gap:10px;
      }
      .pcp-sop-card{
        border-radius:12px;
        background:#ffffff;
        box-shadow:0 8px 18px rgba(15,23,42,.06);
        padding:12px;
      }
      .pcp-sop-card h4{
        margin:0 0 8px;
        color:#0f172a;
        font-size:var(--pcp-font-base);
      }
      .pcp-sop-list{
        margin:0;
        padding-left:16px;
        color:#475569;
        font-size:var(--pcp-font-base);
        line-height:1.7;
      }
      .pcp-profit-tools{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        margin-bottom:10px;
      }
      .pcp-profit-tools button{
        height:32px;
        border-radius:10px;
        border:1px solid #cbd5e1;
        background:#fff;
        color:#0f172a;
        padding:0 12px;
        font-size:var(--pcp-font-base);
        font-weight:700;
        cursor:pointer;
      }
      .pcp-profit-tools .primary{
        border-color:#2563eb;
        background:#2563eb;
        color:#fff;
      }
      .pcp-profit-batch{margin-bottom:10px;}
      .pcp-profit-batch textarea{
        width:100%;
        min-height:64px;
        resize:vertical;
        border:1px solid #cbd5e1;
        border-radius:10px;
        padding:8px 10px;
        font-size:var(--pcp-font-base);
        color:#0f172a;
        background:#fff;
      }
      .pcp-profit-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:8px;
        margin-bottom:10px;
      }
      .pcp-profit-list{
        max-height:42vh;
        overflow:auto;
        display:grid;
        gap:8px;
      }
      .pcp-profit-item{
        border:1px solid #e2e8f0;
        border-radius:12px;
        background:#fff;
        padding:10px;
      }
      .pcp-profit-row{
        display:grid;
        grid-template-columns:130px 1.2fr 1.2fr 90px 110px 110px 80px 1fr auto;
        gap:6px;
        align-items:center;
      }
      .pcp-profit-row input,
      .pcp-profit-row select{
        height:30px;
        border:1px solid #cbd5e1;
        border-radius:8px;
        padding:0 8px;
        font-size:var(--pcp-font-small);
        color:#0f172a;
      }
      .pcp-profit-status{
        font-size:var(--pcp-font-small);
        color:#334155;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }
      .pcp-profit-status.ok{color:#15803d;}
      .pcp-profit-status.fail{color:#b91c1c;}
      .pcp-profit-remove{
        height:28px;
        border:1px solid #fecaca;
        background:#fff1f2;
        color:#b91c1c;
        border-radius:8px;
        padding:0 10px;
        cursor:pointer;
        font-size:var(--pcp-font-small);
      }
      @media (max-width: 900px){
        .pcp-embedded-launcher{flex-direction:column;align-items:flex-start;}
        .pcp-embedded-actions{justify-content:flex-start;}
        .pcp-material-tools{grid-template-columns:1fr;}
        .pcp-material-row{
          grid-template-columns:1fr;
          gap:8px;
        }
        .pcp-material-main{border-right:none;border-bottom:1px solid rgba(226,232,240,.9);}
        .pcp-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
        .pcp-material-actions{justify-content:flex-start;}
        .pcp-modal-form{grid-template-columns:1fr;}
        .pcp-modal-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
        .pcp-profit-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
        .pcp-profit-row{grid-template-columns:1fr;}
        .pcp-inline-rec{grid-template-columns:1fr;}
        .pcp-side-entry{
          top:auto;
          bottom:18px;
          right:0;
          transform:none;
          writing-mode:horizontal-tb;
          width:auto;
          min-height:36px;
          padding:0 12px;
          border-radius:12px 0 0 12px;
        }
        .pcp-drawer{width:96vw;height:94vh;}
      }
    `;
    document.head.appendChild(style);
  }

  function getTableRoot() {
    return document.querySelector('.core-table') || document.querySelector('table');
  }

  function getSavedPanelTab() {
    try {
      const value = String(window.localStorage.getItem(PANEL_TAB_KEY) || '').trim();
      if (value === 'overview' || value === 'profit' || value === 'sync' || value === 'materials' || value === 'sop' || value === 'config') return value;
    } catch (e) {}
    return 'overview';
  }

  function setSavedPanelTab(tab) {
    try { window.localStorage.setItem(PANEL_TAB_KEY, tab); } catch (e) {}
  }

  function setDrawerTab(tab) {
    const next = tab === 'profit' || tab === 'sync' || tab === 'materials' || tab === 'sop' || tab === 'config' ? tab : 'overview';
    state.panelTab = next;
    setSavedPanelTab(next);
    if (!state.panelHost) return;
    (state.panelHost.tabButtons || []).forEach((btn) => {
      const active = String(btn.getAttribute('data-tab') || '') === next;
      btn.classList.toggle('active', active);
    });
    (state.panelHost.tabPanels || []).forEach((panel) => {
      const active = String(panel.getAttribute('data-panel') || '') === next;
      panel.classList.toggle('active', active);
    });
    if (next === 'profit') renderProfitPanel();
    if (next === 'materials') renderMaterialsPanel();
  }

  function setDrawerOpen(open) {
    if (!state.panelHost || !state.panelHost.drawer || !state.panelHost.mask) return;
    const active = !!open;
    state.panelOpen = active;
    state.panelHost.mask.classList.toggle('show', active);
    state.panelHost.drawer.classList.toggle('open', active);
  }

  function createModal() {
    if (state.panelHost && state.panelHost.drawer) return state.panelHost;

    const sideEntry = document.createElement('button');
    sideEntry.className = 'pcp-side-entry';
    sideEntry.type = 'button';
    sideEntry.textContent = 'GMV 助手';
    document.body.appendChild(sideEntry);

    const mask = document.createElement('div');
    mask.className = 'pcp-full-mask';

    const drawer = document.createElement('aside');
    drawer.className = 'pcp-drawer';
    drawer.innerHTML = `
      <div class="pcp-drawer-head">
        <h3>GMV Max 投放助手</h3>
        <button class="pcp-drawer-close" type="button" data-role="close">×</button>
      </div>
      <div class="pcp-drawer-hero">
        <h4>从冷启动到快速放量</h4>
        <p>先稳素材，再稳转化，再稳预算。避免频繁换品和大幅改参数。</p>
        <span class="pcp-drawer-formula">ECPM = 点击率 × 转化率 × 出价</span>
      </div>
      <div class="pcp-drawer-tabs">
        <button class="pcp-drawer-tab active" type="button" data-tab="overview">总览 <span class="pcp-tab-badge" data-role="badge-overview">0</span></button>
        <button class="pcp-drawer-tab" type="button" data-tab="profit">利润回传</button>
        <button class="pcp-drawer-tab" type="button" data-tab="sync">数据同步</button>
        <button class="pcp-drawer-tab" type="button" data-tab="materials">素材列表 <span class="pcp-tab-badge" data-role="badge-materials">0</span></button>
        <button class="pcp-drawer-tab" type="button" data-tab="sop">放量SOP</button>
        <button class="pcp-drawer-tab" type="button" data-tab="config">配置</button>
      </div>
      <div class="pcp-drawer-body">
        <section class="pcp-tab-panel active" data-panel="overview">
          <div class="pcp-modal-grid" data-role="kpi-grid"></div>
          <div class="pcp-modal-actions">
            <button class="primary" type="button" data-role="sync-backend-overview">一键同步</button>
            <button class="primary" type="button" data-role="refresh">刷新识别</button>
            <button type="button" data-role="copy-exclude">复制待排除ID</button>
          </div>
          <div class="pcp-modal-note" data-role="note">点击“一键同步”后，会自动采集当前素材并生成建议。</div>
          <div class="pcp-inline-rec" data-role="rec"></div>
        </section>

        <section class="pcp-tab-panel" data-panel="profit">
          <div class="pcp-profit-tools">
            <button class="primary" type="button" data-role="profit-capture">抓取当前页</button>
            <button type="button" data-role="profit-add">新增空行</button>
            <button type="button" data-role="profit-expand-date">按日期扩展</button>
            <button type="button" data-role="profit-clear">清空</button>
            <button class="primary" type="button" data-role="profit-submit">回传利润中心</button>
          </div>
          <div class="pcp-profit-batch">
            <textarea data-role="profit-batch-dates" placeholder="批量日期（每行一个，支持区间：2026-04-01~2026-04-07）"></textarea>
          </div>
          <div class="pcp-profit-grid" data-role="profit-summary"></div>
          <div class="pcp-profit-list" data-role="profit-list"></div>
          <div class="pcp-modal-note" data-role="profit-note">先点击“抓取当前页”，确认行数据后再回传。</div>
        </section>

        <section class="pcp-tab-panel" data-panel="sync">
          <div class="pcp-modal-form">
            <label class="pcp-modal-field">
              <span>店铺</span>
              <select data-role="store"></select>
            </label>
            <label class="pcp-modal-field">
              <span>目标ROI（可选）</span>
              <input data-role="target-roi" type="number" step="0.01" placeholder="如 6.5" />
            </label>
            <label class="pcp-modal-field">
              <span>当前预算（可选）</span>
              <input data-role="budget" type="number" step="0.01" placeholder="如 200" />
            </label>
            <label class="pcp-modal-field">
              <span>同步范围</span>
              <select data-role="sync-scope">
                <option value="all">全部素材</option>
                <option value="filtered">当前筛选</option>
                <option value="selected">仅已选中</option>
              </select>
            </label>
          </div>
          <div class="pcp-modal-actions">
            <button class="primary" type="button" data-role="sync-backend">同步到后端并生成建议</button>
          </div>
          <div class="pcp-modal-note">用于把当前页面素材数据同步到后端，并生成放量建议。</div>
        </section>

        <section class="pcp-tab-panel" data-panel="materials">
          <div class="pcp-material-tools">
            <input type="text" data-role="material-search" placeholder="搜索标题 / Video ID / 账号" />
            <select data-role="material-filter">
              <option value="all">全部</option>
              <option value="boost_only">仅Boost</option>
              <option value="non_boost">非Boost</option>
              <option value="excellent">放量</option>
              <option value="optimize">优化</option>
              <option value="observe">观察</option>
              <option value="garbage">垃圾</option>
              <option value="excluded">已排除</option>
            </select>
            <select data-role="material-sort">
              <option value="roi_desc">按ROI</option>
              <option value="orders_desc">按订单</option>
              <option value="ctr_desc">按CTR</option>
              <option value="cvr_desc">按CVR</option>
            </select>
            <button type="button" class="pcp-act" data-role="material-reset">重置</button>
          </div>
          <div class="pcp-material-bulk">
            <button type="button" class="pcp-act" data-role="bulk-label-observe">当前筛选设为观察</button>
            <button type="button" class="pcp-act" data-role="bulk-label-garbage">当前筛选设为垃圾</button>
            <button type="button" class="pcp-act danger" data-role="bulk-exclude-on">当前筛选加入排除</button>
            <button type="button" class="pcp-act" data-role="bulk-exclude-off">当前筛选取消排除</button>
            <button type="button" class="pcp-act" data-role="bulk-select-filtered">全选筛选结果</button>
            <button type="button" class="pcp-act" data-role="bulk-clear-selected">清空选择</button>
            <button type="button" class="pcp-act" data-role="bulk-export-csv">导出当前列表CSV</button>
            <span class="pcp-material-selected" data-role="selected-count">已选 0</span>
          </div>
          <div class="pcp-material-wrap">
            <div class="pcp-material-head">
              <span><input class="pcp-material-check" type="checkbox" data-role="material-select-all" /></span>
              <span>Video ID</span>
              <span>素材</span>
              <span style="text-align:right;">ROI</span>
              <span style="text-align:right;">CTR</span>
              <span style="text-align:right;">CVR</span>
              <span style="text-align:right;">订单</span>
              <span style="text-align:right;">操作</span>
            </div>
            <div class="pcp-material-body" data-role="material-body"></div>
          </div>
        </section>

        <section class="pcp-tab-panel" data-panel="sop">
          <div class="pcp-sop-grid">
            <div class="pcp-sop-card">
              <h4>第0-1天：冷启动建模</h4>
              <ol class="pcp-sop-list">
                <li>同品准备 6-10 条素材，覆盖 3 种钩子角度。</li>
                <li>目标ROI按保本值起跑，预算设为预估CPA的10-20倍。</li>
                <li>优先稳定拿到首批转化，不频繁改品改结构。</li>
              </ol>
            </div>
            <div class="pcp-sop-card">
              <h4>第2-4天：筛素材与控风险</h4>
              <ol class="pcp-sop-list">
                <li>日更素材，垃圾素材及时排除，保留潜力素材继续测。</li>
                <li>观察 CTR、CVR、ROI 三段数据，定位前中后段问题。</li>
                <li>无数据或明显亏损素材直接止损，避免拖累模型。</li>
              </ol>
            </div>
            <div class="pcp-sop-card">
              <h4>第5天后：小步放量</h4>
              <ol class="pcp-sop-list">
                <li>ROI稳定后，预算按10%-20%逐步上调。</li>
                <li>每次调参只改一个变量（预算或ROI其一）。</li>
                <li>一旦掉量，回撤到上一个稳定档位继续观察。</li>
              </ol>
            </div>
          </div>
        </section>

        <section class="pcp-tab-panel" data-panel="config">
          <div class="pcp-config-grid">
            <label>
              <span>API Base</span>
              <input data-role="api-base" type="text" placeholder="例如：https://your-domain.com" />
            </label>
            <label>
              <span>插件 Token</span>
              <input data-role="token" type="password" placeholder="在利润中心插件接入中生成" />
            </label>
          </div>
          <div class="pcp-config-actions">
            <button type="button" data-role="save-config">保存配置</button>
            <button class="primary" type="button" data-role="test-connect">连接并拉取配置</button>
          </div>
          <div class="pcp-modal-note" data-role="config-status">未连接。</div>
        </section>
      </div>
    `;
    mask.appendChild(drawer);
    document.body.appendChild(mask);

    sideEntry.addEventListener('click', () => {
      setDrawerOpen(!drawer.classList.contains('open'));
    });

    const closeBtn = drawer.querySelector('[data-role="close"]');
    if (closeBtn) closeBtn.addEventListener('click', () => setDrawerOpen(false));
    mask.addEventListener('click', (evt) => {
      if (evt.target === mask) setDrawerOpen(false);
    });

    const tabButtons = Array.from(drawer.querySelectorAll('[data-tab]'));
    const tabPanels = Array.from(drawer.querySelectorAll('[data-panel]'));
    tabButtons.forEach((btn) => {
      btn.addEventListener('click', () => setDrawerTab(String(btn.getAttribute('data-tab') || 'overview')));
    });

    const materialSearchInput = drawer.querySelector('[data-role="material-search"]');
    const materialFilterSelect = drawer.querySelector('[data-role="material-filter"]');
    const materialSortSelect = drawer.querySelector('[data-role="material-sort"]');
    const materialBody = drawer.querySelector('[data-role="material-body"]');
    const materialResetBtn = drawer.querySelector('[data-role="material-reset"]');
    const materialSelectAll = drawer.querySelector('[data-role="material-select-all"]');
    const selectedCountEl = drawer.querySelector('[data-role="selected-count"]');
    const bulkLabelObserveBtn = drawer.querySelector('[data-role="bulk-label-observe"]');
    const bulkLabelGarbageBtn = drawer.querySelector('[data-role="bulk-label-garbage"]');
    const bulkExcludeOnBtn = drawer.querySelector('[data-role="bulk-exclude-on"]');
    const bulkExcludeOffBtn = drawer.querySelector('[data-role="bulk-exclude-off"]');
    const bulkSelectFilteredBtn = drawer.querySelector('[data-role="bulk-select-filtered"]');
    const bulkClearSelectedBtn = drawer.querySelector('[data-role="bulk-clear-selected"]');
    const bulkExportCsvBtn = drawer.querySelector('[data-role="bulk-export-csv"]');
    const syncScopeSelect = drawer.querySelector('[data-role="sync-scope"]');

    if (materialSearchInput) {
      materialSearchInput.value = state.panelMaterialQuery || '';
      materialSearchInput.addEventListener('input', () => {
        state.panelMaterialQuery = String(materialSearchInput.value || '').trim();
        renderMaterialsPanel();
      });
    }
    if (materialFilterSelect) {
      materialFilterSelect.value = state.panelMaterialFilter || 'all';
      materialFilterSelect.addEventListener('change', () => {
        state.panelMaterialFilter = String(materialFilterSelect.value || 'all').trim() || 'all';
        renderMaterialsPanel();
      });
    }
    if (materialSortSelect) {
      materialSortSelect.value = state.panelMaterialSort || 'roi_desc';
      materialSortSelect.addEventListener('change', () => {
        state.panelMaterialSort = String(materialSortSelect.value || 'roi_desc').trim() || 'roi_desc';
        renderMaterialsPanel();
      });
    }
    if (materialResetBtn) {
      materialResetBtn.addEventListener('click', () => {
        state.panelMaterialQuery = '';
        state.panelMaterialFilter = 'all';
        state.panelMaterialSort = 'roi_desc';
        if (materialSearchInput) materialSearchInput.value = '';
        if (materialFilterSelect) materialFilterSelect.value = 'all';
        if (materialSortSelect) materialSortSelect.value = 'roi_desc';
        renderMaterialsPanel();
      });
    }
    if (materialSelectAll) {
      materialSelectAll.addEventListener('change', () => {
        if (materialSelectAll.checked) selectFilteredRows();
        else clearSelectedRows();
      });
    }
    if (bulkLabelObserveBtn) bulkLabelObserveBtn.addEventListener('click', () => applyBulkLabel('observe'));
    if (bulkLabelGarbageBtn) bulkLabelGarbageBtn.addEventListener('click', () => applyBulkLabel('garbage'));
    if (bulkExcludeOnBtn) bulkExcludeOnBtn.addEventListener('click', () => applyBulkExclude(true));
    if (bulkExcludeOffBtn) bulkExcludeOffBtn.addEventListener('click', () => applyBulkExclude(false));
    if (bulkSelectFilteredBtn) bulkSelectFilteredBtn.addEventListener('click', () => selectFilteredRows());
    if (bulkClearSelectedBtn) bulkClearSelectedBtn.addEventListener('click', () => clearSelectedRows());
    if (bulkExportCsvBtn) bulkExportCsvBtn.addEventListener('click', () => exportFilteredRowsCsv(bulkExportCsvBtn));
    if (syncScopeSelect) {
      syncScopeSelect.value = state.panelSyncScope || 'all';
      syncScopeSelect.addEventListener('change', () => {
        state.panelSyncScope = String(syncScopeSelect.value || 'all').trim() || 'all';
      });
    }
    if (materialBody) {
      materialBody.addEventListener('click', (evt) => {
        const target = evt.target && evt.target.closest ? evt.target.closest('[data-action]') : null;
        if (!target) return;
        const action = String(target.getAttribute('data-action') || '');
        const rowKey = String(target.getAttribute('data-row-key') || '');
        if (!action || !rowKey) return;
        if (action === 'label') {
          const value = normalizeLabel(String(target.getAttribute('data-value') || 'observe'));
          applyManualLabel(rowKey, value);
          return;
        }
        if (action === 'exclude') {
          toggleExclude(rowKey);
          return;
        }
        if (action === 'expand') {
          toggleExpandRow(rowKey);
        }
      });
      materialBody.addEventListener('change', (evt) => {
        const target = evt.target;
        if (!target || !target.matches || !target.matches('[data-action="select"]')) return;
        const rowKey = String(target.getAttribute('data-row-key') || '');
        toggleSelectRow(rowKey, !!target.checked);
      });
    }

    const refreshBtn = drawer.querySelector('[data-role="refresh"]');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        state.lastFingerprint = '';
        scheduleRefresh(40);
        updateInlinePanel();
      });
    }

    const copyBtn = drawer.querySelector('[data-role="copy-exclude"]');
    if (copyBtn) {
      copyBtn.addEventListener('click', async () => {
        try {
          const ids = state.rows
            .filter((row) => row && row.video_id)
            .filter((row) => row.exclude_flag || normalizeLabel(row.manual_label || row.auto_label) === 'garbage')
            .map((row) => String(row.video_id));
          const uniq = Array.from(new Set(ids));
          await navigator.clipboard.writeText(uniq.join('\n'));
          copyBtn.textContent = `已复制 ${uniq.length} 条`;
          setTimeout(() => { copyBtn.textContent = '复制待排除ID'; }, 1400);
        } catch (err) {
          copyBtn.textContent = '复制失败';
          setTimeout(() => { copyBtn.textContent = '复制待排除ID'; }, 1400);
        }
      });
    }

    const syncMainBtn = drawer.querySelector('[data-role="sync-backend"]');
    if (syncMainBtn) syncMainBtn.addEventListener('click', () => syncAssistantFromInline(syncMainBtn));

    const syncOverviewBtn = drawer.querySelector('[data-role="sync-backend-overview"]');
    if (syncOverviewBtn) syncOverviewBtn.addEventListener('click', () => syncAssistantFromInline(syncOverviewBtn));

    const apiBaseInput = drawer.querySelector('[data-role="api-base"]');
    const tokenInput = drawer.querySelector('[data-role="token"]');
    const cfgStatus = drawer.querySelector('[data-role="config-status"]');

    const saveCfgBtn = drawer.querySelector('[data-role="save-config"]');
    if (saveCfgBtn) {
      saveCfgBtn.addEventListener('click', async () => {
        try {
          state.panelConfig.apiBase = normalizeApiBase(apiBaseInput && apiBaseInput.value || '');
          state.panelConfig.token = String(tokenInput && tokenInput.value || '').trim();
          await new Promise((resolve) => {
            chrome.storage.local.set({ [CONFIG_STORE_KEY]: { apiBase: state.panelConfig.apiBase, token: state.panelConfig.token } }, resolve);
          });
          if (cfgStatus) cfgStatus.textContent = '配置已保存。';
        } catch (err) {
          if (cfgStatus) cfgStatus.textContent = '配置保存失败。';
        }
      });
    }

    const testConnBtn = drawer.querySelector('[data-role="test-connect"]');
    if (testConnBtn) {
      testConnBtn.addEventListener('click', async () => {
        try {
          if (cfgStatus) cfgStatus.textContent = '正在连接...';
          state.panelConfig.apiBase = normalizeApiBase(apiBaseInput && apiBaseInput.value || '');
          state.panelConfig.token = String(tokenInput && tokenInput.value || '').trim();
          await new Promise((resolve) => {
            chrome.storage.local.set({ [CONFIG_STORE_KEY]: { apiBase: state.panelConfig.apiBase, token: state.panelConfig.token } }, resolve);
          });
          state.panelBootstrap = null;
          await loadInlineBootstrap();
          if (cfgStatus) cfgStatus.textContent = '连接成功，已拉取店铺配置。';
        } catch (err) {
          if (cfgStatus) cfgStatus.textContent = `连接失败：${err && err.message ? err.message : 'unknown'}`;
        }
      });
    }

    state.panelHost = Object.assign({}, state.panelHost || {}, {
      sideEntry,
      mask,
      drawer,
      tabButtons,
      tabPanels,
      kpiGrid: drawer.querySelector('[data-role="kpi-grid"]'),
      note: drawer.querySelector('[data-role="note"]'),
      recBox: drawer.querySelector('[data-role="rec"]'),
      badgeOverview: drawer.querySelector('[data-role="badge-overview"]'),
      badgeMaterials: drawer.querySelector('[data-role="badge-materials"]'),
      storeSelect: drawer.querySelector('[data-role="store"]'),
      targetRoiInput: drawer.querySelector('[data-role="target-roi"]'),
      budgetInput: drawer.querySelector('[data-role="budget"]'),
      syncButtons: [syncMainBtn, syncOverviewBtn].filter(Boolean),
      materialSearchInput,
      materialFilterSelect,
      materialSortSelect,
      materialBody,
      materialSelectAll,
      selectedCountEl,
      syncScopeSelect,
      apiBaseInput,
      tokenInput,
      configStatus: cfgStatus,
      profitCaptureBtn: drawer.querySelector('[data-role="profit-capture"]'),
      profitAddBtn: drawer.querySelector('[data-role="profit-add"]'),
      profitExpandDateBtn: drawer.querySelector('[data-role="profit-expand-date"]'),
      profitClearBtn: drawer.querySelector('[data-role="profit-clear"]'),
      profitSubmitBtn: drawer.querySelector('[data-role="profit-submit"]'),
      profitBatchDates: drawer.querySelector('[data-role="profit-batch-dates"]'),
      profitSummary: drawer.querySelector('[data-role="profit-summary"]'),
      profitList: drawer.querySelector('[data-role="profit-list"]'),
      profitNote: drawer.querySelector('[data-role="profit-note"]')
    });

    if (state.panelHost.profitCaptureBtn) {
      state.panelHost.profitCaptureBtn.addEventListener('click', () => captureProfitActivePage());
    }
    if (state.panelHost.profitAddBtn) {
      state.panelHost.profitAddBtn.addEventListener('click', () => {
        state.panelProfitRows.unshift(profitRowDefaults());
        renderProfitPanel();
      });
    }
    if (state.panelHost.profitClearBtn) {
      state.panelHost.profitClearBtn.addEventListener('click', () => {
        state.panelProfitRows = [];
        renderProfitPanel();
      });
    }
    if (state.panelHost.profitExpandDateBtn) {
      state.panelHost.profitExpandDateBtn.addEventListener('click', () => expandProfitRowsByDates());
    }
    if (state.panelHost.profitSubmitBtn) {
      state.panelHost.profitSubmitBtn.addEventListener('click', () => submitProfitRows());
    }
    if (state.panelHost.profitList) {
      state.panelHost.profitList.addEventListener('click', (evt) => {
        const target = evt.target && evt.target.closest ? evt.target.closest('[data-profit-action]') : null;
        if (!target) return;
        const action = String(target.getAttribute('data-profit-action') || '');
        const rowId = Number(target.getAttribute('data-profit-id') || 0);
        if (!rowId) return;
        const row = findProfitRowById(rowId);
        if (!row) return;
        if (action === 'remove') {
          state.panelProfitRows = state.panelProfitRows.filter((item) => Number(item.id) !== rowId);
          renderProfitPanel();
        }
      });
      state.panelHost.profitList.addEventListener('change', (evt) => {
        const target = evt.target;
        if (!target || !target.getAttribute) return;
        const rowId = Number(target.getAttribute('data-profit-id') || 0);
        const field = String(target.getAttribute('data-profit-field') || '').trim();
        if (!rowId || !field) return;
        const row = findProfitRowById(rowId);
        if (!row) return;
        updateProfitRowField(row, field, target.value);
      });
      state.panelHost.profitList.addEventListener('input', (evt) => {
        const target = evt.target;
        if (!target || !target.getAttribute) return;
        const rowId = Number(target.getAttribute('data-profit-id') || 0);
        const field = String(target.getAttribute('data-profit-field') || '').trim();
        if (!rowId || !field) return;
        const row = findProfitRowById(rowId);
        if (!row) return;
        updateProfitRowField(row, field, target.value, true);
      });
    }

    state.panelTab = getSavedPanelTab();
    setDrawerTab(state.panelTab);
    renderProfitPanel();
    return state.panelHost;
  }

  function normalizeApiBase(raw) {
    let base = String(raw || '').trim();
    if (!base) return '';
    if (!/^https?:\/\//i.test(base)) base = 'https://' + base;
    return base.replace(/\/+$/, '');
  }

  function storageGetPromise(key) {
    return new Promise((resolve) => {
      chrome.storage.local.get([key], (res) => resolve(res || {}));
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

  function buildApiUrl(path) {
    const base = normalizeApiBase(state.panelConfig.apiBase);
    if (!base) throw new Error('请先在插件中配置 API Base');
    if (/\/admin\.php$/i.test(base) && /^\/admin\.php\//.test(path)) {
      return base + path.slice('/admin.php'.length);
    }
    return base + path;
  }

  async function requestApi(path, method, body) {
    const token = String(state.panelConfig.token || '').trim();
    if (!token) throw new Error('请先在插件中配置 Token');
    const payload = {
      type: 'profit_plugin_fetch',
      method: String(method || 'GET').toUpperCase(),
      url: buildApiUrl(path),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      }
    };
    if (body != null) payload.body = JSON.stringify(body);
    const response = await runtimeSendMessage(payload);
    if (!response || !response.ok) {
      throw new Error(response && response.error ? response.error : 'network_failed');
    }
    if (!response.json || Number(response.json.code || 0) !== 0) {
      const msg = response && response.json && response.json.msg ? String(response.json.msg) : 'api_failed';
      const trace = response && response.json && response.json.trace_id ? String(response.json.trace_id) : '';
      throw new Error(trace ? `${msg} (trace_id=${trace})` : msg);
    }
    return response.json;
  }

  function parseDateFromText(text) {
    const raw = String(text || '').trim();
    if (!raw) return '';
    const m = raw.match(/(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})/);
    if (!m) return '';
    return `${m[1]}-${String(Number(m[2])).padStart(2, '0')}-${String(Number(m[3])).padStart(2, '0')}`;
  }

  function resolveMetricDate() {
    const ctx = state.context || {};
    const fromRange = parseDateFromText(ctx.date_range || '');
    if (fromRange) return fromRange;
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  function numberOrNull(v) {
    if (v == null || v === '') return null;
    const n = Number(String(v).replace(/[，,]/g, '').trim());
    return Number.isFinite(n) ? n : null;
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

  function parseDateText(dateText) {
    const normalized = normalizeDate(dateText);
    if (!normalized) return null;
    const dt = new Date(`${normalized}T00:00:00`);
    return Number.isNaN(dt.getTime()) ? null : dt;
  }

  function dateToText(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  function enumerateDateRange(startText, endText) {
    const start = parseDateText(startText);
    const end = parseDateText(endText);
    if (!start || !end) return [];
    if (start.getTime() > end.getTime()) return [];
    const list = [];
    const cursor = new Date(start.getTime());
    while (cursor.getTime() <= end.getTime()) {
      list.push(dateToText(cursor));
      cursor.setDate(cursor.getDate() + 1);
      if (list.length > 370) break;
    }
    return list;
  }

  function parseBatchDatesInput(rawText) {
    const source = String(rawText || '').trim();
    if (!source) return [];
    const segments = source
      .split(/[\n,，;；]+/)
      .map((item) => String(item || '').trim())
      .filter(Boolean);
    const result = [];
    const seen = new Set();
    segments.forEach((segment) => {
      const rangeMatch = segment.match(/^(.+?)\s*(?:~|至|to)\s*(.+)$/i);
      const picked = rangeMatch ? enumerateDateRange(rangeMatch[1], rangeMatch[2]) : [normalizeDate(segment)];
      picked.forEach((item) => {
        const dt = normalizeDate(item);
        if (!dt || seen.has(dt)) return;
        seen.add(dt);
        result.push(dt);
      });
    });
    return result;
  }

  function getAccounts() {
    return Array.isArray(state.panelBootstrap && state.panelBootstrap.accounts) ? state.panelBootstrap.accounts : [];
  }

  function getStoreMappings() {
    return Array.isArray(state.panelBootstrap && state.panelBootstrap.mappings && state.panelBootstrap.mappings.store)
      ? state.panelBootstrap.mappings.store
      : [];
  }

  function getAccountMappings() {
    return Array.isArray(state.panelBootstrap && state.panelBootstrap.mappings && state.panelBootstrap.mappings.account)
      ? state.panelBootstrap.mappings.account
      : [];
  }

  function getChannelOptions() {
    const raw = state.panelBootstrap && state.panelBootstrap.channel_options;
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
    const raw = state.panelBootstrap && state.panelBootstrap.currency_options;
    if (Array.isArray(raw) && raw.length > 0) {
      return raw.map((c) => String(c || '').toUpperCase()).filter(Boolean);
    }
    return defaultCurrencies;
  }

  function storeById(id) {
    const target = Number(id || 0);
    if (target <= 0) return null;
    const stores = Array.isArray(state.panelBootstrap && state.panelBootstrap.stores) ? state.panelBootstrap.stores : [];
    return stores.find((s) => Number(s.id || 0) === target) || null;
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

  function lowerTrim(text) {
    return String(text || '').trim().toLowerCase();
  }

  function guessStoreId(refText) {
    const ref = lowerTrim(refText);
    if (!ref) return 0;
    if (/^\d+$/.test(ref)) {
      const id = Number(ref);
      if (storeById(id)) return id;
    }
    const mapped = getStoreMappings().find((m) => lowerTrim(m.alias) === ref);
    if (mapped && Number(mapped.store_id || 0) > 0) return Number(mapped.store_id);
    const stores = Array.isArray(state.panelBootstrap && state.panelBootstrap.stores) ? state.panelBootstrap.stores : [];
    const direct = stores.find((s) => lowerTrim(s.store_name) === ref || lowerTrim(s.store_code) === ref);
    return direct ? Number(direct.id || 0) : 0;
  }

  function guessAccountId(refText, storeId) {
    const ref = lowerTrim(refText);
    if (!ref) return 0;
    if (/^\d+$/.test(ref)) {
      const id = Number(ref);
      const found = accountById(id);
      if (found && (!storeId || Number(found.store_id || 0) === Number(storeId || 0))) return id;
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

  function normalizeRawMetrics(raw) {
    if (raw == null || raw === '') return null;
    if (typeof raw === 'object' && !Array.isArray(raw)) return Object.assign({}, raw);
    if (typeof raw === 'string') {
      const text = raw.trim();
      if (!text) return null;
      try {
        const decoded = JSON.parse(text);
        if (decoded && typeof decoded === 'object' && !Array.isArray(decoded)) return decoded;
      } catch (_) {
        return { raw_text: text.slice(0, 1000) };
      }
    }
    return null;
  }

  function profitRowDefaults() {
    return {
      id: state.panelProfitSeq++,
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
      roi_value: '',
      page_type: '',
      raw_metrics_json: null,
      source_page: '',
      status: '',
      message: ''
    };
  }

  function fillCurrencyByAccount(row) {
    const account = accountById(row.account_id);
    if (account) {
      if (!row.ad_spend_currency) row.ad_spend_currency = String(account.account_currency || 'USD').toUpperCase();
      if (!row.gmv_currency) row.gmv_currency = String(account.default_gmv_currency || 'VND').toUpperCase();
    }
    if (String(row.page_type || '').toLowerCase() === 'ad' && row.ad_spend_currency) {
      row.gmv_currency = String(row.ad_spend_currency || '').toUpperCase();
    }
  }

  function normalizeCapturedRow(raw) {
    const row = Object.assign(profitRowDefaults(), {
      entry_date: normalizeDate(raw && raw.entry_date) || todayText(),
      store_ref: String(raw && raw.store_ref || '').trim(),
      account_ref: String(raw && raw.account_ref || '').trim(),
      channel_type: String(raw && raw.channel_type || 'video').trim() || 'video',
      ad_spend_amount: raw && raw.ad_spend_amount == null ? '' : String(raw && raw.ad_spend_amount || ''),
      ad_spend_currency: String(raw && raw.ad_spend_currency || '').toUpperCase() || 'USD',
      gmv_amount: raw && raw.gmv_amount == null ? '' : String(raw && raw.gmv_amount || ''),
      gmv_currency: String(raw && raw.gmv_currency || '').toUpperCase() || 'VND',
      order_count: raw && raw.order_count == null ? '' : String(raw && raw.order_count || ''),
      roi_value: raw && raw.roi_value == null ? '' : String(raw && raw.roi_value || ''),
      page_type: String(raw && raw.page_type || '').trim().toLowerCase(),
      raw_metrics_json: normalizeRawMetrics(raw && (raw.raw_metrics_json || raw.raw_metrics) || null),
      source_page: String(raw && raw.source_page || '').trim()
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

  function storeOptionsHtml(selectedId) {
    const current = Number(selectedId || 0);
    const options = ['<option value="">店铺</option>'];
    const stores = Array.isArray(state.panelBootstrap && state.panelBootstrap.stores) ? state.panelBootstrap.stores : [];
    stores.forEach((s) => {
      const id = Number(s.id || 0);
      const selected = id === current ? ' selected' : '';
      options.push(`<option value="${id}"${selected}>${escapeHtml(s.store_name || s.store_code || String(id))}</option>`);
    });
    return options.join('');
  }

  function accountOptionsHtml(storeId, selectedId) {
    const current = Number(selectedId || 0);
    const options = ['<option value="">账户</option>'];
    accountsByStore(storeId).forEach((a) => {
      const id = Number(a.id || 0);
      const selected = id === current ? ' selected' : '';
      options.push(`<option value="${id}"${selected}>${escapeHtml(a.account_name || a.account_code || String(id))}</option>`);
    });
    return options.join('');
  }

  function channelOptionsHtml(selected) {
    const current = String(selected || 'video');
    return getChannelOptions().map((c) => `<option value="${escapeHtml(c.value)}"${c.value === current ? ' selected' : ''}>${escapeHtml(c.label)}</option>`).join('');
  }

  function currencyOptionsHtml(selected) {
    const current = String(selected || '').toUpperCase();
    return getCurrencyOptions().map((c) => `<option value="${escapeHtml(c)}"${c === current ? ' selected' : ''}>${escapeHtml(c)}</option>`).join('');
  }

  function findProfitRowById(rowId) {
    return state.panelProfitRows.find((row) => Number(row.id) === Number(rowId)) || null;
  }

  function validateProfitRow(row) {
    if (!normalizeDate(row.entry_date)) return '日期必填';
    if (!String(row.store_id || row.store_ref || '').trim()) return '店铺必填';
    if (!String(row.account_id || row.account_ref || '').trim()) return '账号必填';
    const channel = String(row.channel_type || 'video').trim();
    const ad = Number(row.ad_spend_amount || 0);
    const gmv = Number(row.gmv_amount || 0);
    const order = Number(row.order_count || 0);
    if ((channel === 'video' || channel === 'live') && (ad <= 0 || gmv <= 0 || order <= 0)) return '视频/直播要求花费、GMV、订单>0';
    if (channel === 'influencer' && order <= 0) return '达人要求订单>0';
    return '';
  }

  function profitRowToPayload(row) {
    const adAmount = row.ad_spend_amount === '' ? null : Number(row.ad_spend_amount);
    const gmvAmount = row.gmv_amount === '' ? null : Number(row.gmv_amount);
    const orderCount = row.order_count === '' ? null : Number(row.order_count);
    const roiValue = row.roi_value === '' ? null : Number(row.roi_value);
    const payload = {
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
    if (String(row.page_type || '').toLowerCase() === 'ad' && payload.ad_spend_currency) payload.gmv_currency = payload.ad_spend_currency;
    const rawMetrics = normalizeRawMetrics(row.raw_metrics_json);
    if (rawMetrics) {
      if (Number.isFinite(roiValue)) rawMetrics.total_roi = roiValue;
      payload.raw_metrics_json = rawMetrics;
    } else if (Number.isFinite(roiValue)) {
      payload.raw_metrics_json = { total_roi: roiValue };
    }
    return payload;
  }

  function renderProfitPanel() {
    if (!state.panelHost || !state.panelHost.profitList || !state.panelHost.profitSummary) return;
    const total = state.panelProfitRows.length;
    const invalid = state.panelProfitRows.filter((row) => validateProfitRow(row) !== '').length;
    const success = state.panelProfitRows.filter((row) => row.status === 'ok').length;
    const failed = state.panelProfitRows.filter((row) => row.status === 'fail').length;
    state.panelHost.profitSummary.innerHTML = `
      <div class="pcp-modal-kpi"><span>预览行数</span><strong>${total}</strong></div>
      <div class="pcp-modal-kpi"><span>待修正</span><strong>${invalid}</strong></div>
      <div class="pcp-modal-kpi"><span>成功</span><strong>${success}</strong></div>
      <div class="pcp-modal-kpi"><span>失败</span><strong>${failed}</strong></div>
    `;
    if (!total) {
      state.panelHost.profitList.innerHTML = '<div class="pcp-material-empty">暂无利润预览数据</div>';
      return;
    }
    const html = state.panelProfitRows.map((row) => {
      const statusClass = row.status === 'ok' ? 'ok' : row.status === 'fail' ? 'fail' : '';
      const statusText = row.status === 'ok' ? '成功' : (row.status === 'fail' ? (row.message || '失败') : '-');
      return `
        <div class="pcp-profit-item">
          <div class="pcp-profit-row">
            <input type="date" data-profit-id="${row.id}" data-profit-field="entry_date" value="${escapeHtml(row.entry_date)}" />
            <select data-profit-id="${row.id}" data-profit-field="store_id">${storeOptionsHtml(row.store_id)}</select>
            <select data-profit-id="${row.id}" data-profit-field="account_id">${accountOptionsHtml(row.store_id, row.account_id)}</select>
            <select data-profit-id="${row.id}" data-profit-field="channel_type">${channelOptionsHtml(row.channel_type)}</select>
            <input type="number" step="0.01" min="0" data-profit-id="${row.id}" data-profit-field="ad_spend_amount" value="${escapeHtml(row.ad_spend_amount)}" placeholder="花费" />
            <input type="number" step="0.01" min="0" data-profit-id="${row.id}" data-profit-field="gmv_amount" value="${escapeHtml(row.gmv_amount)}" placeholder="GMV" />
            <input type="number" step="1" min="0" data-profit-id="${row.id}" data-profit-field="order_count" value="${escapeHtml(row.order_count)}" placeholder="订单" />
            <span class="pcp-profit-status ${statusClass}" title="${escapeHtml(statusText)}">${escapeHtml(statusText)}</span>
            <button class="pcp-profit-remove" type="button" data-profit-action="remove" data-profit-id="${row.id}">删除</button>
          </div>
        </div>
      `;
    }).join('');
    state.panelHost.profitList.innerHTML = html;
  }

  function updateProfitRowField(row, field, value, silent) {
    row[field] = value;
    if (field === 'store_id') {
      const storeId = Number(value || 0);
      const store = storeById(storeId);
      row.store_id = storeId > 0 ? storeId : '';
      if (store) row.store_ref = String(store.store_name || store.store_code || storeId);
      if (row.account_id) {
        const account = accountById(row.account_id);
        if (!account || Number(account.store_id || 0) !== Number(row.store_id || 0)) row.account_id = '';
      }
      renderProfitPanel();
      return;
    }
    if (field === 'account_id') {
      const accountId = Number(value || 0);
      const account = accountById(accountId);
      row.account_id = accountId > 0 ? accountId : '';
      if (account) {
        row.account_ref = String(account.account_name || account.account_code || accountId);
        if (!row.store_id) row.store_id = Number(account.store_id || 0) || '';
      }
      fillCurrencyByAccount(row);
      renderProfitPanel();
      return;
    }
    if (!silent) renderProfitPanel();
  }

  async function captureProfitActivePage() {
    if (!state.panelHost || !state.panelHost.profitCaptureBtn) return;
    try {
      state.panelHost.profitCaptureBtn.disabled = true;
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = '正在抓取当前页面利润数据...';
      await loadInlineBootstrap();
      const result = captureNow();
      if (!result || !result.ok || (!Array.isArray(result.rows) && !result.row)) {
        throw new Error(result && result.error ? result.error : 'capture_failed');
      }
      const rawRows = Array.isArray(result.rows) && result.rows.length > 0 ? result.rows : [result.row];
      const normalizedRows = rawRows.filter((item) => item && typeof item === 'object').map((item) => normalizeCapturedRow(item));
      if (!normalizedRows.length) throw new Error('empty_capture');
      for (let i = normalizedRows.length - 1; i >= 0; i -= 1) {
        const row = normalizedRows[i];
        if (!row.source_page) row.source_page = String(window.location.href || '');
        state.panelProfitRows.unshift(row);
      }
      renderProfitPanel();
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = `抓取成功，已加入 ${normalizedRows.length} 行`;
    } catch (err) {
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = `抓取失败：${err && err.message ? err.message : 'unknown'}`;
    } finally {
      state.panelHost.profitCaptureBtn.disabled = false;
    }
  }

  function expandProfitRowsByDates() {
    if (!state.panelHost || !state.panelHost.profitBatchDates) return;
    const dates = parseBatchDatesInput(state.panelHost.profitBatchDates.value);
    if (!dates.length) {
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = '请输入有效日期或区间';
      return;
    }
    if (!state.panelProfitRows.length) {
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = '请先抓取至少一行';
      return;
    }
    const added = [];
    const snapshots = state.panelProfitRows.slice();
    snapshots.forEach((row) => {
      dates.forEach((dateText) => {
        const cloned = Object.assign({}, row, { id: state.panelProfitSeq++, entry_date: dateText, status: '', message: '' });
        added.push(cloned);
      });
    });
    if (!added.length) return;
    state.panelProfitRows = added.concat(state.panelProfitRows);
    renderProfitPanel();
    if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = `已新增 ${added.length} 行日期扩展`;
  }

  async function submitProfitRows() {
    if (state.panelProfitSubmitting || !state.panelHost || !state.panelHost.profitSubmitBtn) return;
    if (!state.panelProfitRows.length) {
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = '请先抓取或新增利润行';
      return;
    }
    const invalids = [];
    state.panelProfitRows.forEach((row, idx) => {
      const reason = validateProfitRow(row);
      if (reason) invalids.push(`#${idx + 1}:${reason}`);
    });
    if (invalids.length) {
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = `存在无效行：${invalids.slice(0, 3).join(' | ')}`;
      return;
    }
    try {
      state.panelProfitSubmitting = true;
      state.panelHost.profitSubmitBtn.disabled = true;
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = '正在回传利润中心...';
      state.panelProfitRows.forEach((row) => { row.status = ''; row.message = ''; });
      renderProfitPanel();
      const json = await requestApi('/admin.php/profit_center/plugin/ingestBatch', 'POST', {
        rows: state.panelProfitRows.map((row) => profitRowToPayload(row))
      });
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
      state.panelProfitRows.forEach((row, idx) => {
        const key = idx + 1;
        if (savedMap[key]) {
          row.status = 'ok';
          row.message = 'saved';
        } else if (failedMap[key]) {
          row.status = 'fail';
          row.message = String(failedMap[key].message || 'save_failed');
        } else {
          row.status = 'fail';
          row.message = 'unknown_result';
        }
      });
      renderProfitPanel();
      if (state.panelHost.profitNote) {
        state.panelHost.profitNote.textContent = `回传完成：成功 ${Number(data.saved_count || 0)}，失败 ${Number(data.failed_count || 0)}`;
      }
    } catch (err) {
      if (state.panelHost.profitNote) state.panelHost.profitNote.textContent = `回传失败：${err && err.message ? err.message : 'unknown'}`;
    } finally {
      state.panelProfitSubmitting = false;
      state.panelHost.profitSubmitBtn.disabled = false;
    }
  }

  function rowsForSync(scope) {
    const mode = String(scope || state.panelSyncScope || 'all').trim();
    let sourceRows = state.rows;
    if (mode === 'filtered') sourceRows = getFilteredMaterialRows();
    if (mode === 'selected') sourceRows = getSelectedRows();

    return sourceRows
      .filter((row) => row && normalizeLabel(row.manual_label || row.auto_label) !== 'ignore')
      .map((row, idx) => {
        const rawId = String(row.video_id || '').trim();
        const finalId = rawId || fallbackVideoId(`${row.row_key || ''}|${row.title || ''}`, idx);
        const sourceType = row.source_video_id_type || (String(finalId).startsWith('pseudo_') ? 'pseudo' : 'actual');
        return {
        video_id: finalId,
        source_video_id_type: sourceType,
        row_key: row.row_key || '',
        title: row.title || '',
        tiktok_account: row.tiktok_account || '',
        status: row.status || '',
        metrics: row.metrics || {},
        auto_label: normalizeLabel(row.manual_label || row.auto_label),
        manual_label: row.manual_label || '',
        exclude_flag: !!row.exclude_flag,
        hook_score: row.hook_score || '',
        retention_score: row.retention_score || '',
        conversion_score: row.conversion_score || '',
        material_type: row.material_type || '',
        problem_position: row.problem_position || '',
        continue_delivery: row.continue_delivery || '',
        core_conclusion: row.core_conclusion || '',
        actions: Array.isArray(row.actions) ? row.actions.slice(0, 3) : [],
        diagnosis: row.diagnosis || null,
        source_page: state.context && state.context.host ? `https://${state.context.host}` : ''
      };
      });
  }

  function fillStoreOptions() {
    if (!state.panelHost || !state.panelHost.storeSelect) return;
    const select = state.panelHost.storeSelect;
    const stores = Array.isArray(state.panelBootstrap && state.panelBootstrap.stores) ? state.panelBootstrap.stores : [];
    const current = String(select.value || '').trim();
    select.innerHTML = '';
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = '请选择店铺';
    select.appendChild(empty);
    select.style.color = '#0f172a';
    stores.forEach((store) => {
      const opt = document.createElement('option');
      opt.value = String(store.id || '');
      opt.textContent = String(store.store_name || store.name || store.store_code || `Store#${store.id || ''}`);
      select.appendChild(opt);
    });
    if (stores.length === 0) {
      empty.textContent = '未获取到店铺（请先连接后台）';
      select.value = '';
      (state.panelHost.syncButtons || []).forEach((btn) => { if (btn) btn.disabled = true; });
      return;
    }
    (state.panelHost.syncButtons || []).forEach((btn) => { if (btn) btn.disabled = false; });
    if (current && stores.some((s) => String(s.id || '') === current)) {
      select.value = current;
    } else if (stores[0] && stores[0].id != null) {
      select.value = String(stores[0].id);
    }
  }

  async function loadInlineBootstrap() {
    const cfg = await storageGetPromise(CONFIG_STORE_KEY);
    const raw = cfg && cfg[CONFIG_STORE_KEY] ? cfg[CONFIG_STORE_KEY] : {};
    state.panelConfig.apiBase = normalizeApiBase(raw.apiBase || '');
    state.panelConfig.token = String(raw.token || '').trim();
    if (state.panelHost && state.panelHost.apiBaseInput && document.activeElement !== state.panelHost.apiBaseInput) {
      state.panelHost.apiBaseInput.value = state.panelConfig.apiBase;
    }
    if (state.panelHost && state.panelHost.tokenInput && document.activeElement !== state.panelHost.tokenInput) {
      state.panelHost.tokenInput.value = state.panelConfig.token;
    }
    if (state.panelBootstrap) {
      fillStoreOptions();
      renderProfitPanel();
      return state.panelBootstrap;
    }
    const json = await requestApi('/admin.php/profit_center/plugin/bootstrap', 'GET');
    state.panelBootstrap = json.data || {};
    fillStoreOptions();
    renderProfitPanel();
    return state.panelBootstrap;
  }

  function renderInlineRecommendation(rec) {
    if (!state.panelHost || !state.panelHost.note) return;
    if (!rec || typeof rec !== 'object') {
      state.panelHost.note.textContent = '同步完成，但后端暂未返回建议内容。';
      if (state.panelHost.recBox) state.panelHost.recBox.innerHTML = '';
      return;
    }
    const escapeHtml = (input) => String(input == null ? '' : input)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
    const fatigue = rec.fatigue_alert && typeof rec.fatigue_alert === 'object' ? rec.fatigue_alert : {};
    const guard = rec.scale_guard && typeof rec.scale_guard === 'object' ? rec.scale_guard : {};
    const split = rec.budget_split && rec.budget_split.split ? rec.budget_split.split : {};
    const doList = Array.isArray(rec.today_do) ? rec.today_do.slice(0, 3) : [];
    const avoidList = Array.isArray(rec.today_avoid) ? rec.today_avoid.slice(0, 2) : [];
    const noteHead = [
      `<strong>阶段：</strong>${escapeHtml(String(rec.stage || '-'))}`,
      `<strong>主问题：</strong>${escapeHtml(String(rec.main_problem || '-'))}`,
      `<strong>动作级别：</strong>${escapeHtml(String(rec.action_level || '-'))}`,
      `<strong>结论：</strong>${escapeHtml(String(rec.core_conclusion || '-'))}`
    ].filter(Boolean).join('<br/>');
    const doBlock = doList.length ? `<div style="margin-top:8px;"><strong>今日该做</strong><ol class="pcp-quick-list">${doList.map((item) => `<li>${escapeHtml(String(item || ''))}</li>`).join('')}</ol></div>` : '';
    const avoidBlock = avoidList.length ? `<div style="margin-top:6px;"><strong>今日不要做</strong><ol class="pcp-quick-list">${avoidList.map((item) => `<li>${escapeHtml(String(item || ''))}</li>`).join('')}</ol></div>` : '';
    state.panelHost.note.innerHTML = `${noteHead}${doBlock}${avoidBlock}`;
    if (state.panelHost.recBox) {
      const fatigueLevel = String(fatigue.level || 'normal').toLowerCase();
      const fatigueClass = fatigueLevel === 'high' ? 'bad' : (fatigueLevel === 'medium' ? 'warn' : 'ok');
      const guardClass = guard.can_scale ? 'ok' : 'bad';
      const splitItems = [
        ['放量', Number(split.scale || 0)],
        ['潜力', Number(split.potential || 0)],
        ['观察', Number(split.observe || 0)],
        ['测试', Number(split.test || 0)],
        ['止损', Number(split.waste_control || 0)]
      ];
      state.panelHost.recBox.innerHTML = `
        <div class="pcp-inline-rec-card">
          <h4>素材疲劳预警</h4>
          <span class="pcp-inline-tag ${fatigueClass}">${escapeHtml(fatigueLevel || 'normal')}</span>
          <p style="margin-top:8px;">${escapeHtml(String(fatigue.summary || '暂无明显疲劳信号。'))}</p>
        </div>
        <div class="pcp-inline-rec-card">
          <h4>放量护栏</h4>
          <span class="pcp-inline-tag ${guardClass}">${guard.can_scale ? '可放量' : '先优化'}</span>
          <p style="margin-top:8px;">${escapeHtml(String(guard.summary || '-'))}</p>
        </div>
        <div class="pcp-inline-rec-card">
          <h4>预算分配建议</h4>
          <ul class="pcp-inline-split">
            ${splitItems.map((it) => {
              const val = Math.max(0, Math.min(100, Number(it[1] || 0)));
              return `<li><span style="width:28px;">${it[0]}</span><div class="bar"><span style="width:${val}%;"></span></div><strong style="width:34px;text-align:right;">${val}%</strong></li>`;
            }).join('')}
          </ul>
        </div>
      `;
    }
  }

  async function refreshRowsPromise() {
    return new Promise((resolve) => {
      state.lastFingerprint = '';
      rebuild((err, data) => {
        if (err) {
          resolve({ ok: false, error: err });
          return;
        }
        render();
        resolve({ ok: true, data });
      });
    });
  }

  async function syncAssistantFromInline(syncBtn) {
    if (!state.panelHost) return;
    const resetText = (syncBtn && String(syncBtn.textContent || '').trim()) || '同步到后端并生成建议';
    try {
      if (syncBtn) syncBtn.disabled = true;
      if (state.panelHost.note) state.panelHost.note.textContent = '正在同步中...';
      if (state.panelHost.recBox) state.panelHost.recBox.innerHTML = '';
      await loadInlineBootstrap();
      await refreshRowsPromise();
      const storeId = String(state.panelHost.storeSelect && state.panelHost.storeSelect.value || '').trim();
      if (!storeId) throw new Error('请先选择店铺');
      const scope = state.panelSyncScope || 'all';
      const rows = rowsForSync(scope);
      if (!rows.length) {
        if (scope === 'selected') throw new Error('已选中素材为空，请先在素材列表勾选');
        if (scope === 'filtered') throw new Error('当前筛选结果为空，请调整筛选条件');
        throw new Error('没有可同步的素材，请确认当前是 TikTok GMV Max 素材列表页');
      }
      const payload = {
        store_id: storeId,
        campaign_id: String(state.context && state.context.campaign_id || '').trim(),
        campaign_name: '',
        date_range: String(state.context && state.context.date_range || '').trim(),
        metric_date: resolveMetricDate(),
        target_roi: numberOrNull(state.panelHost.targetRoiInput && state.panelHost.targetRoiInput.value),
        campaign_budget: numberOrNull(state.panelHost.budgetInput && state.panelHost.budgetInput.value),
        source_page: state.context && state.context.host ? `https://${state.context.host}` : '',
        rows
      };
      const json = await requestApi('/admin.php/gmv_max/creative/sync', 'POST', payload);
      const data = json.data || {};
      state.panelRecommendation = data.recommendation || null;
      renderInlineRecommendation(state.panelRecommendation);
      if (syncBtn) syncBtn.textContent = `同步成功 ${data.saved_count || 0} 条`;
      setTimeout(() => {
        if (syncBtn) syncBtn.textContent = resetText;
      }, 1800);
    } catch (err) {
      if (state.panelHost.note) {
        state.panelHost.note.textContent = `同步失败：${err && err.message ? err.message : 'unknown'}`;
      }
      if (syncBtn) {
        syncBtn.textContent = '同步失败';
        setTimeout(() => {
          syncBtn.textContent = resetText;
        }, 1800);
      }
    } finally {
      if (syncBtn) syncBtn.disabled = false;
    }
  }

  function saveCreativeStore(storeObj) {
    return new Promise((resolve) => {
      chrome.storage.local.set({ [CREATIVE_STORE_KEY]: storeObj || {} }, () => resolve(true));
    });
  }

  function persistRowDecision(row) {
    if (!row || !row.video_id) return Promise.resolve(false);
    return new Promise((resolve) => {
      loadCreativeStore(async (storeObj) => {
        try {
          const next = Object.assign({}, storeObj || {});
          const key = decisionKey(state.context || {}, row.video_id);
          next[key] = {
            auto_label: normalizeLabel(row.auto_label),
            manual_label: row.manual_label ? normalizeLabel(row.manual_label) : '',
            exclude_flag: row.exclude_flag ? 1 : 0,
            updated_at: Date.now()
          };
          await saveCreativeStore(next);
          resolve(true);
        } catch (_) {
          resolve(false);
        }
      });
    });
  }

  function findRowByKey(rowKey) {
    const key = String(rowKey || '').trim();
    if (!key) return null;
    for (let i = 0; i < state.rows.length; i += 1) {
      const row = state.rows[i];
      if (String(row.row_key || '') === key) return row;
    }
    return null;
  }

  async function applyManualLabel(rowKey, label) {
    const row = findRowByKey(rowKey);
    if (!row) return;
    row.manual_label = normalizeLabel(label);
    if (row.manual_label === 'garbage') row.exclude_flag = 1;
    await persistRowDecision(row);
    render();
  }

  async function toggleExclude(rowKey) {
    const row = findRowByKey(rowKey);
    if (!row) return;
    row.exclude_flag = row.exclude_flag ? 0 : 1;
    await persistRowDecision(row);
    render();
  }

  function metricValue(row, key) {
    const metrics = row && row.metrics ? row.metrics : {};
    const raw = metrics[key];
    const n = Number(raw);
    return Number.isFinite(n) ? n : null;
  }

  function formatPercentFromMetric(row, key) {
    const n = metricValue(row, key);
    if (n == null) return '-';
    return `${n.toFixed(2)}%`;
  }

  function formatSimpleNumber(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return '-';
    if (Math.abs(v) >= 1000) return v.toLocaleString('en-US');
    return String(v);
  }

  function round2(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return 0;
    return Math.round(v * 100) / 100;
  }

  function getSelectedRows() {
    const selectedMap = state.panelSelectedRowKeys || {};
    return state.rows.filter((row) => row && row.row_key && selectedMap[String(row.row_key)]);
  }

  function toggleSelectRow(rowKey, checked) {
    const key = String(rowKey || '').trim();
    if (!key) return;
    if (!state.panelSelectedRowKeys || typeof state.panelSelectedRowKeys !== 'object') state.panelSelectedRowKeys = {};
    if (checked) state.panelSelectedRowKeys[key] = 1;
    else delete state.panelSelectedRowKeys[key];
    if (state.panelOpen && state.panelTab === 'materials') {
      renderMaterialsPanel();
    }
  }

  function toggleExpandRow(rowKey) {
    const key = String(rowKey || '').trim();
    if (!key) return;
    if (!state.panelExpandedRowKeys || typeof state.panelExpandedRowKeys !== 'object') state.panelExpandedRowKeys = {};
    if (state.panelExpandedRowKeys[key]) delete state.panelExpandedRowKeys[key];
    else state.panelExpandedRowKeys[key] = 1;
    renderMaterialsPanel();
  }

  function clearSelectedRows() {
    state.panelSelectedRowKeys = {};
    renderMaterialsPanel();
  }

  function selectFilteredRows() {
    const list = getFilteredMaterialRows();
    const next = {};
    list.forEach((row) => {
      if (row && row.row_key) next[String(row.row_key)] = 1;
    });
    state.panelSelectedRowKeys = next;
    renderMaterialsPanel();
  }

  function exportFilteredRowsCsv(triggerBtn) {
    const rows = getFilteredMaterialRows();
    if (!rows.length) return;
    const toCsvCell = (v) => `"${String(v == null ? '' : v).replace(/"/g, '""')}"`;
    const lines = [
      ['video_id', 'title', 'tiktok_account', 'label', 'excluded', 'roi', 'ctr', 'cvr', 'sku_orders'],
      ...rows.map((row) => ([
        row.video_id || '',
        row.title || '',
        row.tiktok_account || '',
        normalizeLabel(row.manual_label || row.auto_label),
        row.exclude_flag ? '1' : '0',
        metricValue(row, 'roi') == null ? '' : metricValue(row, 'roi'),
        metricValue(row, 'product_ad_click_rate') == null ? '' : metricValue(row, 'product_ad_click_rate'),
        metricValue(row, 'ad_conversion_rate') == null ? '' : metricValue(row, 'ad_conversion_rate'),
        metricValue(row, 'sku_orders') == null ? '' : metricValue(row, 'sku_orders')
      ]))
    ];
    const csv = lines.map((cols) => cols.map(toCsvCell).join(',')).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const dateText = resolveMetricDate().replace(/-/g, '');
    a.href = url;
    a.download = `gmv_materials_${dateText}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1200);
    if (triggerBtn) {
      const origin = triggerBtn.textContent;
      triggerBtn.textContent = `已导出 ${rows.length} 条`;
      setTimeout(() => { triggerBtn.textContent = origin; }, 1400);
    }
  }

  function getFilteredMaterialRows() {
    const keyword = String(state.panelMaterialQuery || '').trim().toLowerCase();
    const filter = String(state.panelMaterialFilter || 'all').trim();
    const sortMode = String(state.panelMaterialSort || 'roi_desc').trim();
    const rows = state.rows.filter((row) => row && (row.video_id || row.title));

    let list = rows.filter((row) => {
      if (normalizeLabel(row.manual_label || row.auto_label) === 'ignore') return false;
      if (!keyword) return true;
      const payload = [
        row.video_id || '',
        row.title || '',
        row.tiktok_account || ''
      ].join(' ').toLowerCase();
      return payload.includes(keyword);
    });

    if (filter === 'excluded') {
      list = list.filter((row) => !!row.exclude_flag);
    } else if (filter === 'boost_only') {
      list = list.filter((row) => !!row.can_boost);
    } else if (filter === 'non_boost') {
      list = list.filter((row) => !row.can_boost);
    } else if (filter !== 'all') {
      list = list.filter((row) => normalizeLabel(row.manual_label || row.auto_label) === filter);
    }

    const readSortValue = (row) => {
      if (sortMode === 'orders_desc') return Number(metricValue(row, 'sku_orders') || 0);
      if (sortMode === 'ctr_desc') return Number(metricValue(row, 'product_ad_click_rate') || 0);
      if (sortMode === 'cvr_desc') return Number(metricValue(row, 'ad_conversion_rate') || 0);
      return Number(metricValue(row, 'roi') || 0);
    };

    list.sort((a, b) => readSortValue(b) - readSortValue(a));
    return list;
  }

  function persistRowsDecision(rows) {
    const batch = Array.isArray(rows) ? rows.filter((r) => r && r.video_id) : [];
    if (!batch.length) return Promise.resolve(false);
    return new Promise((resolve) => {
      loadCreativeStore(async (storeObj) => {
        try {
          const next = Object.assign({}, storeObj || {});
          batch.forEach((row) => {
            const key = decisionKey(state.context || {}, row.video_id);
            next[key] = {
              auto_label: normalizeLabel(row.auto_label),
              manual_label: row.manual_label ? normalizeLabel(row.manual_label) : '',
              exclude_flag: row.exclude_flag ? 1 : 0,
              updated_at: Date.now()
            };
          });
          await saveCreativeStore(next);
          resolve(true);
        } catch (_) {
          resolve(false);
        }
      });
    });
  }

  async function applyBulkLabel(label) {
    const targetLabel = normalizeLabel(label || 'observe');
    const selected = getSelectedRows();
    const list = selected.length ? selected : getFilteredMaterialRows();
    if (!list.length) return;
    list.forEach((row) => {
      row.manual_label = targetLabel;
      if (targetLabel === 'garbage') row.exclude_flag = 1;
    });
    await persistRowsDecision(list);
    render();
  }

  async function applyBulkExclude(flag) {
    const target = !!flag;
    const selected = getSelectedRows();
    const list = selected.length ? selected : getFilteredMaterialRows();
    if (!list.length) return;
    list.forEach((row) => { row.exclude_flag = target ? 1 : 0; });
    await persistRowsDecision(list);
    render();
  }

  function renderMaterialsPanel() {
    if (!state.panelHost || !state.panelHost.materialBody) return;
    const validKeyMap = {};
    state.rows.forEach((row) => {
      if (row && row.row_key) validKeyMap[String(row.row_key)] = 1;
    });
    Object.keys(state.panelSelectedRowKeys || {}).forEach((k) => {
      if (!validKeyMap[k]) delete state.panelSelectedRowKeys[k];
    });
    Object.keys(state.panelExpandedRowKeys || {}).forEach((k) => {
      if (!validKeyMap[k]) delete state.panelExpandedRowKeys[k];
    });

    const list = getFilteredMaterialRows();
    const selectedCount = Object.keys(state.panelSelectedRowKeys || {}).length;
    if (state.panelHost.selectedCountEl) {
      state.panelHost.selectedCountEl.textContent = `已选 ${selectedCount}`;
    }
    if (state.panelHost.materialSelectAll) {
      const filteredCount = list.length;
      const selectedInFiltered = list.filter((row) => row && state.panelSelectedRowKeys[String(row.row_key || '')]).length;
      state.panelHost.materialSelectAll.checked = filteredCount > 0 && selectedInFiltered === filteredCount;
      state.panelHost.materialSelectAll.indeterminate = selectedInFiltered > 0 && selectedInFiltered < filteredCount;
    }

    if (!list.length) {
      state.panelHost.materialBody.innerHTML = '<div class="pcp-material-empty">没有符合条件的素材</div>';
      return;
    }

    const html = list.slice(0, 120).map((row) => {
      const rowKey = escapeHtml(String(row.row_key || ''));
      const label = normalizeLabel(row.manual_label || row.auto_label);
      const roi = metricValue(row, 'roi');
      const orders = metricValue(row, 'sku_orders');
      const ctr = formatPercentFromMetric(row, 'product_ad_click_rate');
      const cvr = formatPercentFromMetric(row, 'ad_conversion_rate');
      const spend = metricValue(row, 'cost');
      const gmv = metricValue(row, 'gross_revenue');
      const rowKeyRaw = String(row.row_key || '');
      const checked = !!(state.panelSelectedRowKeys && state.panelSelectedRowKeys[rowKeyRaw]);
      const expanded = !!(state.panelExpandedRowKeys && state.panelExpandedRowKeys[rowKeyRaw]);
      const diagnosisActions = Array.isArray(row.actions) ? row.actions.filter(Boolean).slice(0, 4) : [];
      const problemText = String(row.problem_position || '').trim() || '-';
      const continueText = String(row.continue_delivery || '').trim() || '-';
      const hookScore = String(row.hook_score || '').trim() || '-';
      const retentionScore = String(row.retention_score || '').trim() || '-';
      const conversionScore = String(row.conversion_score || '').trim() || '-';
      const conclusion = String(row.core_conclusion || '').trim() || '-';
      return `
        <div class="pcp-material-row">
          <div class="pcp-material-main">
            <div class="pcp-material-top">
              <input class="pcp-material-check" type="checkbox" data-action="select" data-row-key="${rowKey}" ${checked ? 'checked' : ''} />
              <div style="min-width:0;flex:1;">
                <div class="pcp-material-id">${escapeHtml(String(row.video_id || '-'))}</div>
                <div class="pcp-material-title">
                  <strong title="${escapeHtml(String(row.title || ''))}">${escapeHtml(String(row.title || '-'))}</strong>
                  <span>${escapeHtml(String(row.tiktok_account || '-'))}</span>
                </div>
              </div>
            </div>
            <div class="pcp-material-meta">
              <span class="chip ${row.can_boost ? 'boost' : 'nonboost'}">${row.can_boost ? 'Boost' : '非Boost'}</span>
              <span class="chip">${escapeHtml(labelText(label))}</span>
              ${row.problem_position ? `<span class="chip problem">${escapeHtml(String(row.problem_position || ''))}</span>` : ''}
            </div>
          </div>
          <div class="pcp-material-side">
            <div class="pcp-metrics-grid">
              <div class="pcp-metric-card"><span>ROI</span><strong>${roi == null ? '-' : roi.toFixed(2)}</strong></div>
              <div class="pcp-metric-card"><span>订单</span><strong>${orders == null ? '-' : formatSimpleNumber(orders)}</strong></div>
              <div class="pcp-metric-card"><span>CTR</span><strong>${ctr}</strong></div>
              <div class="pcp-metric-card"><span>CVR</span><strong>${cvr}</strong></div>
              <div class="pcp-metric-card"><span>花费</span><strong>${spend == null ? '-' : formatSimpleNumber(round2(spend))}</strong></div>
              <div class="pcp-metric-card"><span>GMV</span><strong>${gmv == null ? '-' : formatSimpleNumber(round2(gmv))}</strong></div>
            </div>
            <div class="pcp-material-actions">
              <button class="pcp-act ${label === 'excellent' ? 'active' : ''}" data-action="label" data-row-key="${rowKey}" data-value="excellent">放量</button>
              <button class="pcp-act ${label === 'optimize' ? 'active' : ''}" data-action="label" data-row-key="${rowKey}" data-value="optimize">优化</button>
              <button class="pcp-act ${label === 'observe' ? 'active' : ''}" data-action="label" data-row-key="${rowKey}" data-value="observe">观察</button>
              <button class="pcp-act danger ${label === 'garbage' ? 'active' : ''}" data-action="label" data-row-key="${rowKey}" data-value="garbage">垃圾</button>
              <button class="pcp-act ${row.exclude_flag ? 'active danger' : ''}" data-action="exclude" data-row-key="${rowKey}">${row.exclude_flag ? '已排除' : '排除'}</button>
              <button class="pcp-act ${expanded ? 'active' : ''}" data-action="expand" data-row-key="${rowKey}">${expanded ? '收起诊断' : '展开诊断'}</button>
            </div>
          </div>
          ${expanded ? `
          <div class="pcp-material-detail">
            <div class="pcp-detail-grid">
              <div class="pcp-detail-item"><span>Hook（前3秒）</span><strong>${escapeHtml(hookScore)}</strong></div>
              <div class="pcp-detail-item"><span>Retention（中段）</span><strong>${escapeHtml(retentionScore)}</strong></div>
              <div class="pcp-detail-item"><span>Conversion（后段）</span><strong>${escapeHtml(conversionScore)}</strong></div>
              <div class="pcp-detail-item"><span>问题定位</span><strong>${escapeHtml(problemText)}</strong></div>
              <div class="pcp-detail-item"><span>是否继续投放</span><strong>${escapeHtml(continueText)}</strong></div>
              <div class="pcp-detail-item" style="grid-column:span 3;"><span>一句话结论</span><strong>${escapeHtml(conclusion)}</strong></div>
            </div>
            <ol class="pcp-detail-actions">
              ${(diagnosisActions.length ? diagnosisActions : ['暂无建议']).map((item) => `<li>${escapeHtml(String(item || ''))}</li>`).join('')}
            </ol>
          </div>` : ''}
        </div>
      `;
    }).join('');

    state.panelHost.materialBody.innerHTML = html;
  }

  function updateInlinePanel() {
    if (!state.panelHost) return;
    const total = state.rows.length;
    const excellent = state.rows.filter((r) => normalizeLabel(r.manual_label || r.auto_label) === 'excellent').length;
    const optimize = state.rows.filter((r) => normalizeLabel(r.manual_label || r.auto_label) === 'optimize').length;
    const garbage = state.rows.filter((r) => normalizeLabel(r.manual_label || r.auto_label) === 'garbage').length;
    const observe = state.rows.filter((r) => normalizeLabel(r.manual_label || r.auto_label) === 'observe').length;
    if (state.panelHost.sideEntry) {
      state.panelHost.sideEntry.title = `总${total} / 放量${excellent} / 优化${optimize} / 观察${observe} / 垃圾${garbage}`;
    }
    if (state.panelHost.kpiGrid) {
      state.panelHost.kpiGrid.innerHTML = `
        <div class="pcp-modal-kpi"><span>素材总数</span><strong>${total}</strong></div>
        <div class="pcp-modal-kpi"><span>放量素材</span><strong>${excellent}</strong></div>
        <div class="pcp-modal-kpi"><span>优化素材</span><strong>${optimize}</strong></div>
        <div class="pcp-modal-kpi"><span>垃圾素材</span><strong>${garbage}</strong></div>
      `;
    }
    if (state.panelHost.badgeOverview) state.panelHost.badgeOverview.textContent = String(total);
    if (state.panelHost.badgeMaterials) state.panelHost.badgeMaterials.textContent = String(total);
    if (state.panelHost.statsAll) state.panelHost.statsAll.textContent = `总${total}`;
    if (state.panelHost.statsExcellent) state.panelHost.statsExcellent.textContent = `放量${excellent}`;
    if (state.panelHost.statsOptimize) state.panelHost.statsOptimize.textContent = `优化${optimize}`;
    if (state.panelHost.statsGarbage) state.panelHost.statsGarbage.textContent = `垃圾${garbage}`;
    renderMaterialsPanel();
  }

  function ensureEmbeddedLauncher() {
    const tableRoot = getTableRoot();
    if (!tableRoot || !tableRoot.parentNode) return;

    const parent = tableRoot.parentNode;
    const current = state.panelHost && state.panelHost.inlineLauncher;
    if (current && current.isConnected) return;

    const wrap = document.createElement('div');
    wrap.className = 'pcp-embedded-launcher';
    wrap.innerHTML = `
      <div class="pcp-embedded-main">
        <div class="pcp-embedded-title">素材优化助手（页面入口）</div>
        <div class="pcp-embedded-sub">基于当前素材表实时识别，支持一键同步后端并获取投放建议。</div>
        <div class="pcp-embedded-stats">
          <span class="pcp-embedded-chip" data-role="stat-all">总0</span>
          <span class="pcp-embedded-chip" data-role="stat-excellent">放量0</span>
          <span class="pcp-embedded-chip" data-role="stat-optimize">优化0</span>
          <span class="pcp-embedded-chip" data-role="stat-garbage">垃圾0</span>
        </div>
      </div>
      <div class="pcp-embedded-actions">
        <button class="primary" type="button" data-role="open-drawer">打开助手</button>
        <button type="button" data-role="quick-sync">一键同步</button>
      </div>
    `;
    parent.insertBefore(wrap, tableRoot);

    const openBtn = wrap.querySelector('[data-role="open-drawer"]');
    if (openBtn) {
      openBtn.addEventListener('click', () => {
        createModal();
        setDrawerTab('overview');
        setDrawerOpen(true);
      });
    }
    const quickSyncBtn = wrap.querySelector('[data-role="quick-sync"]');
    if (quickSyncBtn) {
      quickSyncBtn.addEventListener('click', () => {
        createModal();
        setDrawerTab('sync');
        setDrawerOpen(true);
        syncAssistantFromInline(quickSyncBtn);
      });
    }

    state.panelHost = Object.assign({}, state.panelHost || {}, {
      inlineLauncher: wrap,
      statsAll: wrap.querySelector('[data-role="stat-all"]'),
      statsExcellent: wrap.querySelector('[data-role="stat-excellent"]'),
      statsOptimize: wrap.querySelector('[data-role="stat-optimize"]'),
      statsGarbage: wrap.querySelector('[data-role="stat-garbage"]')
    });
  }

  function ensureInlineLauncher() {
    createModal();
    try { loadInlineBootstrap().catch(() => {}); } catch (e) {}
    updateInlinePanel();
  }

  function getBoostButtons() {
    const selectors = [
      'button[data-uid^="creativeboostentrance:button"]',
      'button[data-tid="m4b_button"].boost-action-button-pqyM',
      'button[class*="boost-action-button"]'
    ];
    const result = [];
    const seen = new Set();
    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((node) => {
        if (!node || seen.has(node)) return;
        seen.add(node);
        result.push(node);
      });
    });
    return result;
  }

  function clearInjectedTags() {
    document.querySelectorAll('.pcp-boost-tag-wrap').forEach((node) => node.remove());
  }

  function detectCampaignIdFromPage() {
    const text = String(document.body && document.body.innerText || '');
    const m = text.match(/Campaign ID\s*[:：]?\s*(\d{6,})/i);
    if (m && m[1]) return m[1];
    const m2 = window.location.href.match(/[?&](?:campaign_id|campaignId|id)=(\d{6,})/i);
    return m2 && m2[1] ? m2[1] : '';
  }

  function detectVideoIdFromText(text) {
    const raw = String(text || '');
    let m = raw.match(/Video[^0-9]{0,10}([0-9][0-9\s]{7,30})/i);
    if (m && m[1]) {
      const compact = String(m[1]).replace(/\s+/g, '');
      if (/^[0-9]{8,25}$/.test(compact)) return compact;
    }
    m = raw.match(/Video\s*[:：]?\s*([0-9]{8,25})/i);
    if (m && m[1]) return String(m[1]);
    m = raw.match(/(?:^|\D)(\d{16,22})(?:\D|$)/);
    if (m && m[1]) return String(m[1]);
    m = raw.match(/([0-9]{8,15})/);
    return m && m[1] ? String(m[1]) : '';
  }

  function detectVideoIdFromNode(node, fallbackText) {
    const direct = detectVideoIdFromText(fallbackText || '');
    if (direct) return direct;
    if (!node || typeof node.querySelectorAll !== 'function') return '';

    const attrs = [
      'title',
      'aria-label',
      'data-title',
      'data-tooltip',
      'data-tooltip-content',
      'data-original-title',
      'data-tip',
      'data-content',
      'data-balloon',
      'data-popup',
      'content'
    ];
    const probes = [];
    const addProbe = (v) => {
      const t = String(v || '').trim();
      if (t) probes.push(t);
    };

    addProbe(node.textContent);
    attrs.forEach((key) => addProbe(node.getAttribute && node.getAttribute(key)));
    Array.from(node.querySelectorAll('*')).forEach((el) => {
      attrs.forEach((key) => {
        if (typeof el.getAttribute === 'function') addProbe(el.getAttribute(key));
      });
    });

    for (let i = 0; i < probes.length; i += 1) {
      const id = detectVideoIdFromText(probes[i]);
      if (id) return id;
    }
    return '';
  }

  function hashText(input) {
    const text = String(input || '');
    let hash = 2166136261;
    for (let i = 0; i < text.length; i += 1) {
      hash ^= text.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }
    return (hash >>> 0).toString(16);
  }

  function fallbackVideoId(seed, idx) {
    return `pseudo_${hashText(`${seed || 'creative'}|${idx + 1}|${window.location.pathname || ''}`)}`;
  }

  function parseNumberLike(text) {
    const raw = String(text || '').replace(/,/g, '').trim();
    if (!raw || /^(n\/a|--|-)$/i.test(raw)) return null;
    const m = raw.match(/([-+]?\d+(?:\.\d+)?)/);
    if (!m || !m[1]) return null;
    const n = Number(m[1]);
    return Number.isFinite(n) ? n : null;
  }

  function scoreHigher(value, low, high) {
    if (value == null) return null;
    if (value <= low) return 0;
    if (value >= high) return 1;
    return (value - low) / (high - low);
  }

  function weightedScore(parts) {
    let num = 0;
    let den = 0;
    parts.forEach((part) => {
      if (!part || part.score == null) return;
      const w = Number(part.weight || 0);
      if (!Number.isFinite(w) || w <= 0) return;
      num += part.score * w;
      den += w;
    });
    if (den <= 0) return null;
    return num / den;
  }

  function classifyFallback(metrics, title) {
    const safeTitle = String(title || '').toLowerCase();
    if (safeTitle.includes('product card')) return 'ignore';

    const cost = metrics.cost;
    const sku = metrics.sku_orders;
    const roi = metrics.roi;
    const cvr = metrics.ad_conversion_rate;
    const ctr = metrics.product_ad_click_rate;
    const view75 = metrics.view_rate_75;
    const cpo = metrics.cost_per_order != null
      ? metrics.cost_per_order
      : (cost != null && sku != null && sku > 0 ? cost / sku : null);
    const learningReached = (cost != null && cost >= 1.2)
      || (metrics.product_ad_impressions != null && metrics.product_ad_impressions >= 800)
      || (metrics.product_ad_clicks != null && metrics.product_ad_clicks >= 20);
    const evidenceCount = [roi, cvr, sku].filter((v) => v != null).length;
    const lowEvidence = evidenceCount < 2;

    if (learningReached) {
      if (sku != null && cost != null && sku <= 0 && cost >= 0.6) return 'garbage';
      if (roi != null && roi < 0.9 && cost != null && cost >= 0.6) return 'garbage';
      if (cvr != null && cvr < 0.9 && cost != null && cost >= 0.6) return 'garbage';
      if (cpo != null && cpo > 1.8) return 'garbage';
    }

    if ((roi != null && roi >= 2.3) && (sku != null && sku >= 3) && (cvr != null && cvr >= 2.0)) {
      return 'excellent';
    }

    const score = weightedScore([
      { score: scoreHigher(roi, 1.0, 2.2), weight: 0.35 },
      { score: scoreHigher(sku, 0, 3), weight: 0.30 },
      { score: scoreHigher(cvr, 1.0, 2.2), weight: 0.18 },
      { score: scoreHigher(ctr, 0.6, 1.5), weight: 0.10 },
      { score: scoreHigher(view75, 2.0, 5.0), weight: 0.07 }
    ]);

    if (score == null || lowEvidence || !learningReached) {
      return 'observe';
    }
    if (score >= 0.72) return 'excellent';
    if (score <= 0.28) return learningReached ? 'garbage' : 'optimize';
    if (score <= 0.48) return 'optimize';
    return 'observe';
  }

  function normalizeContext(raw) {
    return {
      host: String(raw && raw.host || window.location.hostname || '').trim().toLowerCase(),
      campaign_id: String(raw && raw.campaign_id || detectCampaignIdFromPage() || '').trim(),
      date_range: String(raw && raw.date_range || '').trim()
    };
  }

  function detectHeaderIndexMap(rowNode) {
    const tableRoot = rowNode && (rowNode.closest('.core-table') || rowNode.closest('table'));
    const map = {};
    if (!tableRoot) return map;
    const headers = Array.from(tableRoot.querySelectorAll('.core-table-th, th'));
    headers.forEach((th, idx) => {
      const text = String(th.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (!text) return;
      if (text.includes('creative')) map.creative = idx;
      if (text.includes('cost per order')) map.cost_per_order = idx;
      else if (text === 'cost' || text.includes(' ad cost') || text.includes('spend')) map.cost = idx;
      if (text.includes('sku order')) map.sku_orders = idx;
      if (text === 'roi' || text.includes(' roi')) map.roi = idx;
      if (text.includes('ad conversion rate')) map.ad_conversion_rate = idx;
      if (text.includes('product ad click rate')) map.product_ad_click_rate = idx;
      if (text.includes('2-second ad video view rate')) map.view_rate_2s = idx;
      if (text.includes('6-second ad video view rate')) map.view_rate_6s = idx;
      if (text.includes('25% ad video view rate')) map.view_rate_25 = idx;
      if (text.includes('50% ad video view rate')) map.view_rate_50 = idx;
      if (text.includes('75% ad video view rate')) map.view_rate_75 = idx;
      if (text.includes('100% ad video view rate')) map.view_rate_100 = idx;
      if (text.includes('product ad clicks')) map.product_ad_clicks = idx;
      if (text.includes('product ad impressions')) map.product_ad_impressions = idx;
    });
    return map;
  }

  function collectCandidateRowNodes() {
    const rows = [];
    const seen = new Set();
    const add = (node) => {
      if (!node || seen.has(node)) return;
      const cells = node.querySelectorAll('.core-table-td, td');
      if (!cells || cells.length === 0) return;
      const text = String(node.textContent || '').replace(/\s+/g, ' ').trim();
      if (!text || /^creative\s+/i.test(text)) return;
      seen.add(node);
      rows.push(node);
    };

    getBoostButtons().forEach((btn) => add(btn.closest('.core-table-tr') || btn.closest('tr')));
    document.querySelectorAll('.core-table-tr, table tr').forEach((node) => add(node));
    return rows;
  }

  function fallbackBuildRows() {
    const rowNodes = collectCandidateRowNodes();
    const rows = [];
    rowNodes.forEach((rowNode, idx) => {
      const cells = Array.from(rowNode.querySelectorAll('.core-table-td, td'));
      if (!cells.length) return;
      const header = detectHeaderIndexMap(rowNode);
      const creativeCell = cells[header.creative != null ? header.creative : 0];
      const creativeText = String(creativeCell && creativeCell.textContent || '').replace(/\s+/g, ' ').trim();
      const rowText = String(rowNode.textContent || '').replace(/\s+/g, ' ').trim();
      const rawVideoId = detectVideoIdFromNode(creativeCell, creativeText) || detectVideoIdFromText(rowText);
      const videoId = rawVideoId || fallbackVideoId(creativeText || rowText, idx);
      const metrics = {};
      Object.keys(header).forEach((key) => {
        if (key === 'creative') return;
        const cell = cells[header[key]];
        metrics[key] = parseNumberLike(cell && cell.textContent);
      });
      const hasBoost = !!rowNode.querySelector('button[data-uid^="creativeboostentrance:button"], button[data-tid="m4b_button"], button[class*="boost-action-button"]')
        || /\bBoost\b/i.test(rowText);
      const autoLabel = classifyFallback(metrics, creativeText);
      rows.push({
        row_index: idx + 1,
        row_key: `row_${idx + 1}_${hashText(videoId || creativeText || rowText).slice(0, 8)}`,
        video_id: videoId,
        source_video_id_type: rawVideoId ? 'actual' : 'pseudo',
        title: creativeText.slice(0, 120),
        can_boost: hasBoost,
        ignore: /product\s*card/i.test(creativeText),
        metrics,
        metrics_hash: `${videoId}|${Object.keys(metrics).map((k) => `${k}:${metrics[k]}`).join('|')}`,
        auto_label: autoLabel,
        manual_label: '',
        exclude_flag: autoLabel === 'garbage' ? 1 : 0
      });
    });
    return rows;
  }

  function parseCreativeRows() {
    const parser = window.ProfitPluginParser;
    if (parser && typeof parser.captureCreativeRows === 'function') {
      const captured = parser.captureCreativeRows(document, window.location.href) || {};
      const parsedRows = Array.isArray(captured.rows) ? captured.rows : [];
      if (parsedRows.length > 0) {
        return {
          context: normalizeContext(captured.context || {}),
          rows: parsedRows
        };
      }
      return {
        context: normalizeContext(captured.context || {}),
        rows: fallbackBuildRows()
      };
    }
    return {
      context: normalizeContext({}),
      rows: fallbackBuildRows()
    };
  }

  function fingerprint(rows, context) {
    const head = contextKey(context);
    const body = (rows || []).map((row) => {
      const label = normalizeLabel(row.auto_label);
      return [
        row.video_id || row.row_key || '',
        row.metrics_hash || '',
        label,
        row.material_type || '',
        row.problem_position || ''
      ].join('|');
    }).join('||');
    return `${head}||${body}`;
  }

  function normalizeRow(raw, idx) {
    const row = Object.assign({}, raw || {});
    row.row_index = Number(row.row_index || idx + 1);
    const seed = [
      row.video_id,
      row.title,
      row.tiktok_account,
      row.metrics_hash,
      row.row_index
    ].join('|');
    row.video_id = String(row.video_id || '').trim();
    if (!row.video_id) {
      row.video_id = fallbackVideoId(seed, idx);
      row.source_video_id_type = 'pseudo';
    } else if (!row.source_video_id_type) {
      row.source_video_id_type = 'actual';
    }
    row.row_key = String(row.row_key || `row_${row.row_index}_${hashText(seed || row.video_id).slice(0, 8)}`);
    row.auto_label = normalizeLabel(row.auto_label);
    row.manual_label = row.manual_label ? normalizeLabel(row.manual_label) : '';
    row.exclude_flag = row.exclude_flag ? 1 : 0;
    row.metrics_hash = String(row.metrics_hash || '');
    row.can_boost = row.can_boost !== false;
    return row;
  }

  function mergeLocalDecision(context, row, storeObj) {
    if (!row || !row.video_id) return row;
    const decision = storeObj[decisionKey(context, row.video_id)];
    if (!decision || typeof decision !== 'object') return row;
    const next = Object.assign({}, row);
    if (decision.auto_label) next.auto_label = normalizeLabel(decision.auto_label);
    if (decision.manual_label) next.manual_label = normalizeLabel(decision.manual_label);
    if (decision.exclude_flag != null) next.exclude_flag = decision.exclude_flag ? 1 : 0;
    return next;
  }

  function rebuild(done) {
    const parsed = parseCreativeRows();
    const context = parsed.context;
    const rows = (parsed.rows || []).map((row, idx) => normalizeRow(row, idx));
    const nextFingerprint = fingerprint(rows, context);

    if (nextFingerprint === state.lastFingerprint && state.rows.length > 0) {
      done(null, { context: state.context, rows: state.rows.slice() });
      return;
    }

    loadCreativeStore((storeObj) => {
      const merged = rows.map((row) => mergeLocalDecision(context, row, storeObj));
      const rowMapByVideo = {};
      merged.forEach((row) => {
        if (row.video_id && !rowMapByVideo[row.video_id]) {
          rowMapByVideo[row.video_id] = row;
        }
      });
      state.context = context;
      state.rows = merged;
      state.rowMapByVideo = rowMapByVideo;
      state.lastFingerprint = nextFingerprint;
      done(null, { context, rows: merged });
    });
  }

  function findRowForButton(button, boostRows, fallbackIndex) {
    const rowNode = button.closest('.core-table-tr') || button.closest('tr');
    if (rowNode) {
      const header = detectHeaderIndexMap(rowNode);
      const cells = Array.from(rowNode.querySelectorAll('.core-table-td, td'));
      const creativeCell = cells[header.creative != null ? header.creative : 0];
      const videoId = detectVideoIdFromNode(creativeCell, creativeCell ? creativeCell.textContent : '');
      if (videoId && state.rowMapByVideo[videoId]) {
        return state.rowMapByVideo[videoId];
      }
    }
    const indexed = boostRows[fallbackIndex] || null;
    if (indexed) return indexed;

    const fallbackNode = button.closest('.core-table-tr') || button.closest('tr');
    if (!fallbackNode) return null;
    const header = detectHeaderIndexMap(fallbackNode);
    const cells = Array.from(fallbackNode.querySelectorAll('.core-table-td, td'));
    const creativeCell = cells[header.creative != null ? header.creative : 0];
    const creativeText = String(creativeCell && creativeCell.textContent || '').replace(/\s+/g, ' ').trim();
    const videoId = detectVideoIdFromNode(creativeCell, creativeText);
    const metrics = {};
    Object.keys(header).forEach((key) => {
      if (key === 'creative') return;
      const cell = cells[header[key]];
      metrics[key] = parseNumberLike(cell && cell.textContent);
    });
    const autoLabel = classifyFallback(metrics, creativeText);
    return {
      row_key: `fallback_${fallbackIndex + 1}`,
      video_id: videoId,
      title: creativeText.slice(0, 120),
      auto_label: autoLabel,
      manual_label: '',
      exclude_flag: autoLabel === 'garbage' ? 1 : 0,
      can_boost: true,
      metrics
    };
  }

  function render() {
    const buttons = getBoostButtons();
    const hostName = String(window.location.hostname || '').toLowerCase();
    const supportedPage = hostName.includes('tiktok');
    if (!supportedPage) {
      state.panelOpen = false;
      if (state.panelHost && state.panelHost.sideEntry) state.panelHost.sideEntry.style.display = 'none';
      if (state.panelHost && state.panelHost.mask) {
        state.panelHost.mask.classList.remove('show');
        state.panelHost.mask.style.display = 'none';
      }
      if (state.panelHost && state.panelHost.drawer) {
        state.panelHost.drawer.classList.remove('open');
        state.panelHost.drawer.style.display = 'none';
      }
      if (state.panelHost && state.panelHost.inlineLauncher) state.panelHost.inlineLauncher.style.display = 'none';
      clearInjectedTags();
      return;
    }
    ensureInlineLauncher();
    if (state.panelHost && state.panelHost.sideEntry) state.panelHost.sideEntry.style.display = '';
    if (state.panelHost && state.panelHost.mask) state.panelHost.mask.style.display = '';
    if (state.panelHost && state.panelHost.drawer) state.panelHost.drawer.style.display = '';
    if (state.panelHost && state.panelHost.inlineLauncher) state.panelHost.inlineLauncher.style.display = '';

    if (!buttons.length) {
      clearInjectedTags();
      updateInlinePanel();
      return;
    }

    const boostRows = state.rows.filter((row) => row && row.can_boost !== false);
    let cursor = 0;
    const activeWraps = new Set();
    buttons.forEach((button) => {
      const row = findRowForButton(button, boostRows, cursor);
      cursor += 1;
      if (!row) return;

      const finalLabel = effectiveLabel(row);
      const parent = button.parentElement || button;
      if (!parent) return;

      let wrap = button.previousElementSibling;
      if (!wrap || !wrap.classList || !wrap.classList.contains('pcp-boost-tag-wrap')) {
        wrap = document.createElement('span');
        wrap.className = 'pcp-boost-tag-wrap';
        const tag = document.createElement('span');
        tag.className = 'pcp-el-tag';
        wrap.appendChild(tag);
        parent.insertBefore(wrap, button);
      }

      const tag = wrap.firstElementChild || wrap.appendChild(document.createElement('span'));
      tag.className = `pcp-el-tag ${finalLabel}${row.manual_label ? ' manual' : ''}`;
      tag.textContent = labelText(finalLabel);
      activeWraps.add(wrap);
    });

    document.querySelectorAll('.pcp-boost-tag-wrap').forEach((node) => {
      if (!activeWraps.has(node)) node.remove();
    });
    updateInlinePanel();
  }

  function runRefresh() {
    state.refreshQueued = false;
    state.lastRefreshAt = Date.now();
    rebuild(() => render());
  }

  function scheduleRefresh(delay) {
    if (document.hidden) return;
    const now = Date.now();
    const minGap = state.panelOpen ? 260 : 900;
    if (state.refreshQueued && (now - state.lastScheduleAt) < 260) return;
    state.lastScheduleAt = now;
    state.refreshQueued = true;

    if (state.refreshTimer) clearTimeout(state.refreshTimer);
    const baseDelay = Number(delay || (state.panelOpen ? 320 : 520));
    const waitByLastRun = Math.max(0, minGap - (now - state.lastRefreshAt));
    const finalDelay = Math.max(baseDelay, waitByLastRun);
    state.refreshTimer = window.setTimeout(() => {
      state.refreshTimer = null;
      const runner = () => runRefresh();
      if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(runner, { timeout: state.panelOpen ? 260 : 420 });
      } else {
        window.requestAnimationFrame(runner);
      }
    }, finalDelay);
  }

  function shouldRefreshByMutations(mutations) {
    if (!Array.isArray(mutations) || mutations.length === 0) return false;
    for (let i = 0; i < mutations.length; i += 1) {
      const m = mutations[i];
      if (!m) continue;
      const nodes = []
        .concat(Array.from(m.addedNodes || []))
        .concat(Array.from(m.removedNodes || []));
      if (nodes.length > 80) return true;
      for (let j = 0; j < nodes.length; j += 1) {
        const node = nodes[j];
        if (!node || node.nodeType !== 1) continue;
        const el = node;
        if (
          (el.matches && (el.matches('.core-table-tr, .core-table-td, .core-table, button[data-uid^="creativeboostentrance:button"]')))
          || (el.querySelector && el.querySelector('.core-table-tr, .core-table-td, button[data-uid^="creativeboostentrance:button"]'))
        ) {
          return true;
        }
      }
    }
    return false;
  }

  function onUrlMaybeChanged() {
    const current = String(window.location.href || '');
    if (current === state.lastUrl) return;
    state.lastUrl = current;
    state.lastFingerprint = '';
    scheduleRefresh(120);
  }

  function ensureObserver() {
    if (state.observer) return;
    const root = document.body;
    state.observer = new MutationObserver((mutations) => {
      if (shouldRefreshByMutations(mutations)) {
        scheduleRefresh(state.panelOpen ? 280 : 540);
      }
    });
    state.observer.observe(root, {
      childList: true,
      subtree: true
    });
    window.addEventListener('popstate', onUrlMaybeChanged);
    window.addEventListener('hashchange', onUrlMaybeChanged);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) scheduleRefresh(260);
    });
  }

  function exportExcludeIds(payload, done) {
    const inputRows = Array.isArray(payload && payload.rows) ? payload.rows : [];
    const sourceRows = inputRows.length > 0 ? inputRows : state.rows;
    const ids = sourceRows
      .filter((row) => row && row.video_id)
      .filter((row) => {
        const label = normalizeLabel(row.manual_label || row.auto_label);
        return row.exclude_flag || label === 'garbage';
      })
      .map((row) => String(row.video_id));
    done(null, Array.from(new Set(ids)));
  }

  function captureNow() {
    if (!window.ProfitPluginParser || typeof window.ProfitPluginParser.captureFromDocument !== 'function') {
      throw new Error('parser_unavailable');
    }
    const captured = window.ProfitPluginParser.captureFromDocument(document, window.location.href);
    const rows = Array.isArray(captured && captured.rows) ? captured.rows.filter((item) => item && typeof item === 'object') : [];
    const primaryRow = captured && captured.row ? captured.row : (rows.length > 0 ? rows[0] : null);
    if (!captured || !primaryRow) throw new Error('empty_capture');
    return { ok: true, row: primaryRow, rows: rows.length > 0 ? rows : [primaryRow], debug: captured.debug || {} };
  }

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (!message || !message.type) return false;

    if (message.type === 'profit_plugin_capture') {
      try {
        sendResponse(captureNow());
      } catch (err) {
        sendResponse({ ok: false, error: safeErrorMessage(err) });
      }
      return true;
    }

    if (message.type === 'profit_plugin_creative_scan') {
      rebuild((err, data) => {
        if (err) {
          sendResponse({ ok: false, error: safeErrorMessage(err) });
          return;
        }
        sendResponse({
          ok: true,
          context: data.context || {},
          rows: Array.isArray(data.rows) ? data.rows : []
        });
      });
      return true;
    }

    if (message.type === 'profit_plugin_creative_apply_labels') {
      try {
        const payloadRows = Array.isArray(message.payload && message.payload.rows) ? message.payload.rows : [];
        const nextRows = state.rows.slice();
        payloadRows.forEach((item, idx) => {
          const rowKey = String(item.row_key || '').trim();
          const videoId = String(item.video_id || '').trim();
          let targetIndex = -1;

          if (rowKey) {
            targetIndex = nextRows.findIndex((row) => String(row.row_key || '') === rowKey);
          }
          if (targetIndex < 0 && videoId) {
            targetIndex = nextRows.findIndex((row) => String(row.video_id || '') === videoId);
          }
          if (targetIndex < 0 && idx < nextRows.length) {
            targetIndex = idx;
          }

          const base = targetIndex >= 0 ? nextRows[targetIndex] : {};
          const merged = Object.assign({}, base, {
            row_key: rowKey || base.row_key || `row_${idx + 1}`,
            video_id: videoId || base.video_id || '',
            auto_label: normalizeLabel(item.auto_label || base.auto_label || 'observe'),
            manual_label: item.manual_label ? normalizeLabel(item.manual_label) : (base.manual_label || ''),
            exclude_flag: item.exclude_flag ? 1 : (base.exclude_flag || 0)
          });

          if (targetIndex >= 0) nextRows[targetIndex] = merged;
          else nextRows.push(merged);
        });

        state.rows = nextRows;
        const rowMapByVideo = {};
        state.rows.forEach((row) => {
          if (row.video_id && !rowMapByVideo[row.video_id]) rowMapByVideo[row.video_id] = row;
        });
        state.rowMapByVideo = rowMapByVideo;
        payloadRows.forEach((item) => {
          const rk = String(item && item.row_key || '').trim();
          const row = rk ? findRowByKey(rk) : null;
          if (row) persistRowDecision(row);
        });
        render();
        sendResponse({ ok: true });
      } catch (err) {
        sendResponse({ ok: false, error: safeErrorMessage(err) });
      }
      return true;
    }

    if (message.type === 'profit_plugin_creative_export_excludes') {
      exportExcludeIds(message.payload || {}, (err, ids) => {
        if (err) {
          sendResponse({ ok: false, error: safeErrorMessage(err) });
          return;
        }
        sendResponse({ ok: true, video_ids: ids || [] });
      });
      return true;
    }

    return false;
  });

  ensureStyle();
  ensureObserver();
  scheduleRefresh(700);
})();

