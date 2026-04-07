#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = process.cwd();
const i18nMain = path.join(root, 'public', 'static', 'i18n', 'i18n.js');
const i18nExtra = path.join(root, 'public', 'static', 'i18n', 'i18n.ops2.js');

const args = new Set(process.argv.slice(2));
const scope = args.has('--scope=all') ? 'all' : 'ops2';

const scopedTargets = [
  'view/admin/influencer',
  'view/admin/outreach_workspace',
  'view/admin/sample',
  'view/admin/industry_trend',
  'view/admin/competitor_analysis',
  'view/admin/ad_insight',
  'view/admin/data_import',
  'view/admin/ops_center',
  'view/admin/common/sidebar.html',
  'view/admin/common/layout.html',
];

function walk(dir, files = []) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walk(full, files);
      continue;
    }
    if (entry.isFile() && full.toLowerCase().endsWith('.html')) {
      files.push(full);
    }
  }
  return files;
}

function collectFiles() {
  if (scope === 'all') {
    return walk(path.join(root, 'view', 'admin'));
  }

  const files = [];
  for (const rel of scopedTargets) {
    const full = path.join(root, rel);
    if (!fs.existsSync(full)) {
      continue;
    }
    const stat = fs.statSync(full);
    if (stat.isDirectory()) {
      walk(full, files);
    } else if (stat.isFile() && full.toLowerCase().endsWith('.html')) {
      files.push(full);
    }
  }
  return Array.from(new Set(files));
}

function loadI18nDict() {
  const sandbox = {
    window: {
      location: { search: '', pathname: '/admin.php', hash: '', href: 'http://localhost/admin.php' },
      localStorage: { getItem() { return null; }, setItem() {} }
    },
    navigator: { language: 'zh-CN' },
    document: { documentElement: { setAttribute() {} }, querySelectorAll() { return []; } },
    URLSearchParams,
    URL,
    console,
  };
  vm.createContext(sandbox);

  const mainCode = fs.readFileSync(i18nMain, 'utf8');
  vm.runInContext(mainCode, sandbox, { filename: 'i18n.js' });

  if (fs.existsSync(i18nExtra)) {
    const extraCode = fs.readFileSync(i18nExtra, 'utf8');
    vm.runInContext(extraCode, sandbox, { filename: 'i18n.ops2.js' });
  }

  if (!sandbox.window.AppI18n || !sandbox.window.AppI18n._dict) {
    throw new Error('Failed to load AppI18n dictionary');
  }

  return sandbox.window.AppI18n._dict;
}

function extractKeys(content) {
  const keys = new Set();
  const patterns = [
    /data-i18n="([^"]+)"/g,
    /data-i18n-ph="([^"]+)"/g,
    /data-i18n-title="([^"]+)"/g,
    /\btt\(\s*['"]([^'"]+)['"]/g,
    /AppI18n\.t\(\s*['"]([^'"]+)['"]/g,
  ];

  for (const re of patterns) {
    let m;
    while ((m = re.exec(content)) !== null) {
      if (m[1]) {
        keys.add(m[1].trim());
      }
    }
  }

  return keys;
}

function shouldIgnoreKey(key) {
  if (!key) {
    return true;
  }
  if (key.includes('{$') || key.includes('${') || key.includes('{:')) {
    return true;
  }
  if (/[{}]/.test(key)) {
    return true;
  }
  return false;
}

function main() {
  const dict = loadI18nDict();
  const files = collectFiles();
  const usedKeys = new Set();

  for (const file of files) {
    const content = fs.readFileSync(file, 'utf8');
    extractKeys(content).forEach((k) => {
      if (!shouldIgnoreKey(k)) {
        usedKeys.add(k);
      }
    });
  }

  const langs = ['zh', 'en', 'vi'];
  const missing = [];
  const unresolved = [];

  for (const key of usedKeys) {
    for (const lang of langs) {
      const table = dict[lang] || {};
      if (!Object.prototype.hasOwnProperty.call(table, key)) {
        missing.push({ key, lang });
      } else if (String(table[key] || '').trim() === key) {
        unresolved.push({ key, lang });
      }
    }
  }

  if (missing.length || unresolved.length) {
    console.error('i18n key check failed.');

    if (missing.length) {
      console.error('\nMissing keys:');
      for (const row of missing.slice(0, 200)) {
        console.error(`- [${row.lang}] ${row.key}`);
      }
      if (missing.length > 200) {
        console.error(`... and ${missing.length - 200} more missing entries`);
      }
    }

    if (unresolved.length) {
      console.error('\nUnresolved keys (value equals key):');
      for (const row of unresolved.slice(0, 200)) {
        console.error(`- [${row.lang}] ${row.key}`);
      }
      if (unresolved.length > 200) {
        console.error(`... and ${unresolved.length - 200} more unresolved entries`);
      }
    }

    process.exit(1);
  }

  console.log(`i18n key check passed. scope=${scope} scanned_files=${files.length} used_keys=${usedKeys.size}`);
}

main();
