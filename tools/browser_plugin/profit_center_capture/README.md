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

## V1.3 素材三段式分析（本地版，2026-04-20）

### 功能

- 新增“素材优化”面板：识别 TikTok GMV Max 素材表并自动诊断。
- 页面仅在 `Boost` 列旁渲染标签：`优秀款 / 观察中 / 垃圾素材 / 忽略`（el-tag 风格）。
- 弹窗输出三段式诊断：`hook_score / retention_score / conversion_score`、问题位置、是否继续投放、可执行建议。
- 支持手动覆盖标签、勾选“加入排除清单”、复制待排除 `video_id`。
- 结果仅存浏览器本地：`chrome.storage.local[profit_plugin_creative_opt_v1]`。

### 打标规则（默认：平衡模式 + 越南 GMV Max 通用基准）

- 三段评分：
  - `hook_score`：`CTR + 2秒播放率`
  - `retention_score`：`6秒率 + 25%/50%/75%播放率`
  - `conversion_score`：`Ad conversion rate + ROI + Cost per order + SKU orders`
- 素材类型映射：
  - `差素材（bad）`：转化段低且已达到学习门槛（成本/曝光/点击任一达阈值）
  - `潜力素材（potential）`：至少一段强但整体不闭环
  - `放量素材（scale）`：转化段高，且钩子/留存不低于中
- 问题定位：
  - `front_3s / middle / conversion_tail / multi_stage`
- 建议引擎：
  - 按问题位置输出可执行改法（改什么 + 怎么改 + 预期提升指标）。

### 使用步骤

1. 打开 TikTok 素材列表页（建议勾选核心列：`ROI / SKU orders / Ad conversion rate / Product ad click rate / 2s/6s/25/50/75% view rate`）。
2. 在插件弹窗点击“识别并打标”。
3. 页面在每行 Boost 按钮左侧显示状态标签；弹窗可查看三段评分与建议。
4. 需要时在弹窗手动改判（优秀款/观察中/垃圾素材/忽略）。
5. 点击“复制待排除视频ID”，将结果粘贴到 TikTok 批量操作。
5. 点击“清空本页覆盖”可重置当前页面上下文（host+campaign+date_range）的手动设置。

## V1.4 GMV Max 动态投放助手（后端历史基准版，2026-04-24）

### 功能

- 新增“GMV Max 动态投放助手”面板。
- 支持把当前 TikTok GMV Max 素材页的可见素材指标同步到后端。
- 后端按租户、店铺、广告系列、日期、视频 ID 沉淀历史数据。
- 后端自动计算店铺 7/14/30 天历史基准，并根据店铺历史基准返回投放建议。
- 返回内容包括：账户阶段、主问题、今日该做、今日不要做、预算建议、ROI 建议、素材新增方向、放量视频 ID、待排除视频 ID。

### 使用步骤

1. 先在利润中心插件接入里生成 Token，并在插件中完成“连接并拉取配置”。
2. 打开 TikTok GMV Max 素材列表页，建议勾选核心列：`Cost / SKU orders / Cost per order / Gross revenue / ROI / Product ad impressions / Product ad clicks / Product ad click rate / Ad conversion rate / 2s/6s/25/50/75/100% view rate`。
3. 在插件“GMV Max 动态投放助手”里选择店铺。
4. 可选填写目标 ROI、当前预算。
5. 点击“同步到后端并生成建议”。
6. 根据返回建议执行：放量、观察、优化前3秒、优化转化段或排除垃圾素材。

### 后端接口

- `POST /admin.php/gmv_max/creative/sync`：插件同步当前素材并生成建议。
- `GET /admin.php/gmv_max/creative/baseline`：查询店铺历史基准。
- `GET /admin.php/gmv_max/creative/recommendation`：查询最新建议。
- `GET /admin.php/gmv_max/creative/history`：查询素材历史。
- `GET /admin.php/gmv_max/creative/ranking`：查询素材排行。

### 迁移

```bash
php database/run_migration_gmv_max_creative_insights.php
```

### 说明

- 同一天同店铺同广告系列同视频 ID 重复同步会覆盖更新，不重复累计。
- 店铺历史样本不足 30 条时，建议会降级为通用基准，并在结果中显示 `baseline_mode=regional_default`。
- 插件不会自动点击 TikTok 后台按钮，只负责同步数据、生成建议和复制排除 ID。
- 若 TikTok 页面未渲染真实 `Video ID`，插件会为当前行生成 `pseudo_xxx` 稳定伪 ID 用于历史沉淀，避免出现“没有可同步的素材”；后续页面显示真实 ID 时会按真实 ID 继续沉淀；早期伪 ID 样本会保留用于当日诊断。
- 投放助手默认固定展示“GMV Max 从0到放量 SOP”，即使暂未同步后端数据，也能查看冷启动、首测、放量、止损、账户养护和每日执行节奏。

