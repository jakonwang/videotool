# TikStar Profit Capture Browser Plugin (V1)

用于在 TikTok 广告后台 / 店铺后台抓取核心指标（广告费、GMV、订单数），并通过利润中心插件接口批量回传。

## V1.1 关键规则（2026-04-19）

- 自动识别广告渠道：
  - `Product GMV Max` -> `video`
  - `LIVE GMV Max` -> `live`
- 渠道优先级：
  - 优先读取当前页面“选中 Tab”作为渠道
  - 仅在无法识别选中 Tab 时，回退到页面文本识别
- 支持广告页面多广告系列自动汇总：
  - 同渠道多行系列自动聚合为 1 行回传
  - 聚合字段：`ad_spend_amount / gmv_amount / order_count / total_roi`
- 日期优先级：
  - 优先读取页面顶部日期范围控件，使用起始日期作为 `entry_date`
  - 无法识别时回退正文日期解析
- 广告页币种规则：
  - 广告页 `gmv_currency` 强制与 `ad_spend_currency` 保持一致
  - 不使用店铺默认 GMV 币种覆盖广告页抓取结果
- 回传附加指标：
  - `raw_metrics_json.capture_mode=campaign_aggregate`
  - `raw_metrics_json.campaign_count`
  - `raw_metrics_json.total_roi`

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

> 说明：若当前页面存在多条广告系列，插件会一次性生成多行（按渠道聚合后）到预览区。
> 说明：可在“批量日期”输入多个日期（支持 `,`/换行/`~` 区间），点击“按日期复制全部行”，一次性生成多日期数据后统一提交。
> 说明：若选中 `LIVE/Product GMV Max` tab 但页面显示无数据或未识别到该 tab 明细行，插件将按 0 回传，不再回退抓取页面总览值。

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

> 兼容说明：后端会自动兼容 `sku_orders / sku_order_count / orders_count`，并统一写入利润中心 `order_count`。

## 注意事项

- 同键冲突策略：`tenant + date + store + account + channel` 覆盖同字段并重算，不重复累计。
- 建议先在利润中心维护“别名映射”，可减少抓取识别误差导致的回传失败。
- 插件仅处理利润中心回传，不写入其他业务模块。
- 复制行与批量日期会自动做同键去重（日期+店铺+账户+渠道），避免重复生成。

## 解析测试

在项目根目录运行：

```bash
node scripts/profit_plugin_parser_test.js
```

