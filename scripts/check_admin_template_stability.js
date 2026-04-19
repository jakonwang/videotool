#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const root = process.cwd();
const targets = [
  path.join(root, 'view', 'admin', 'tenant', 'index.html'),
  path.join(root, 'view', 'admin', 'profit_center', 'index.html'),
  path.join(root, 'view', 'admin', 'product', 'index.html'),
];

function exists(filePath) {
  try {
    return fs.statSync(filePath).isFile();
  } catch (e) {
    return false;
  }
}

function lineOf(content, index) {
  const prefix = content.slice(0, Math.max(0, index));
  return prefix.split(/\r\n|\r|\n/).length;
}

function stripScriptBlocks(content) {
  return content.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
}

function checkSelfClosingElTags(filePath, content) {
  const issues = [];
  const htmlOnly = stripScriptBlocks(content);
  const re = /<\s*(el-[a-z0-9_-]+)\b[^>]*\/>/gi;
  let m;
  while ((m = re.exec(htmlOnly)) !== null) {
    issues.push({
      type: 'self_closing_el_tag',
      tag: m[1],
      line: lineOf(content, m.index),
    });
  }
  return issues;
}

function checkDataSection(filePath, content) {
  if (!/data-section\s*=/.test(content)) {
    return [
      {
        type: 'missing_data_section',
        line: 1,
      },
    ];
  }
  return [];
}

function checkDataModule(filePath, content) {
  if (!/data-module\s*=/.test(content)) {
    return [
      {
        type: 'missing_data_module',
        line: 1,
      },
    ];
  }
  return [];
}

function checkBootstrapInit(filePath, content) {
  if (!/AdminPageBootstrap\.init\s*\(/.test(content)) {
    const normalized = filePath.replace(/\\/g, '/');
    if (normalized.endsWith('/view/admin/tenant/index.html') && /tenant_center\.js/i.test(content)) {
      return [];
    }
    return [
      {
        type: 'missing_bootstrap_init',
        line: 1,
      },
    ];
  }
  return [];
}

function main() {
  const missingFiles = targets.filter((filePath) => !exists(filePath));
  if (missingFiles.length > 0) {
    console.error('Admin template stability check failed: missing target files');
    missingFiles.forEach((filePath) => console.error(`- missing: ${path.relative(root, filePath)}`));
    process.exit(1);
  }

  const allIssues = [];
  targets.forEach((filePath) => {
    const content = fs.readFileSync(filePath, 'utf8');
    const fileIssues = [
      ...checkSelfClosingElTags(filePath, content),
      ...checkDataSection(filePath, content),
      ...checkDataModule(filePath, content),
      ...checkBootstrapInit(filePath, content),
    ];
    fileIssues.forEach((issue) => {
      allIssues.push({
        file: path.relative(root, filePath),
        ...issue,
      });
    });
  });

  if (allIssues.length > 0) {
    console.error('Admin template stability check failed.');
    allIssues.forEach((issue) => {
      if (issue.type === 'self_closing_el_tag') {
        console.error(`- [${issue.file}:${issue.line}] self-closing tag not allowed: <${issue.tag} />`);
        return;
      }
      if (issue.type === 'missing_data_section') {
        console.error(`- [${issue.file}:${issue.line}] missing required data-section marker`);
        return;
      }
      if (issue.type === 'missing_data_module') {
        console.error(`- [${issue.file}:${issue.line}] missing required data-module marker`);
        return;
      }
      if (issue.type === 'missing_bootstrap_init') {
        console.error(`- [${issue.file}:${issue.line}] missing AdminPageBootstrap.init call`);
      }
    });
    process.exit(1);
  }

  console.log(`Admin template stability check passed. files=${targets.length}`);
}

main();
