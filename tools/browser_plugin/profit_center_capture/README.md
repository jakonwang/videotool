# TikStar Profit Capture Browser Plugin (V1)

用于在 TikTok 广告后台 / 店铺后台抓取核心指标（广告费、GMV、订单数），并通过利润中心插件接口批量回传。

## 目录结构

- `manifest.json`: Chrome/Edge Manifest V3 配置
- `background.js`: 跨域请求代理（通过 `chrome.runtime.sendMessage`）
- `content.js`: 页面抓取桥接，调用解析器并返回抓取结果
- `shared/parser.js`: 指标解析逻辑
- `popup.html/js/css`: 插件弹窗（配置、预览、回传）

## 安装（开发者模式）

1. 打开 Chrome/Edge 扩展页：
   - Chrome: `chrome://extensions`
   - Edge: `edge://extensions`
2. 开启“开发者模式”。
3. 点击“加载已解压的扩展程序”，选择目录：
   - `tools/browser_plugin/profit_center_capture`

## 使用流程

1. 在利润中心页面打开“插件接入设置”，生成插件 Token（只显示一次）。
2. 打开扩展弹窗，填写：
   - `API Base`（如：`https://your-domain.com`）
   - `插件 Token`
3. 点击“连接并拉取配置”，确认显示“已连接”。
4. 打开 TikTok 广告后台或店铺后台页面，点击“抓取当前页面”。
5. 在预览表中逐行检查 / 修正（店铺、广告户、渠道、金额、币种、日期）。
6. 点击“回传到利润中心”。
7. 根据行状态和 `trace_id` 排查失败记录。

## 回传字段（V1）

- `entry_date`
- `store_ref`
- `account_ref`
- `channel_type`
- `ad_spend_amount`
- `ad_spend_currency`
- `gmv_amount`
- `gmv_currency`
- `order_count`

## 注意事项

- 同键冲突策略：`tenant + date + store + account + channel` 覆盖同字段并重算，不重复累计。
- 建议先在利润中心维护“别名映射”，可减少抓取识别误差导致的回传失败。
- 插件仅处理利润中心回传，不写入其他业务模块。

## 解析测试

在项目根目录运行：

```bash
node scripts/profit_plugin_parser_test.js
```

