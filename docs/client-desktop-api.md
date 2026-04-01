# 桌面端软件接入说明（授权校验 + 检查更新）

本文档供**第三方桌面客户端**对接本站点「发卡 / 版本」能力时使用。接口为**开放 API**，**不需要**登录态、Cookie 或 Token。

---

## 1. 配置项（交给对方开发填写的只有这些）

| 配置项 | 说明 | 示例 |
|--------|------|------|
| **API 根地址** | 站点对外可访问的根 URL，**建议 HTTPS** | `https://your-domain.com` |
| **验权接口路径** | 固定 | `/index.php/api/client/verifyLicense` |
| **检查更新路径** | 固定 | `/index.php/api/client/checkUpdate` |
| **（可选）公开下载页** | 浏览器打开，给员工/用户下载历史版本 | `/index.php/download` |

**完整 URL 拼法**（勿遗漏 `index.php`，除非贵站 Nginx/Apache 已把路由重写为省略 `index.php` 的等价地址）：

- 验权：`{API根地址}/index.php/api/client/verifyLicense`
- 更新：`{API根地址}/index.php/api/client/checkUpdate`
- 下载页：`{API根地址}/index.php/download`

若生产环境已配置伪静态，可能等价于 `{API根地址}/api/client/verifyLicense`，以贵站实际可访问的 URL 为准。

---

## 2. 通用约定

- **字符编码**：UTF-8。
- **响应体**：JSON，`Content-Type: application/json`。
- **统一结构**：

```json
{
  "code": 0,
  "msg": "ok",
  "data": { }
}
```

- **`code`**：`0` 表示业务成功（含「无更新」场景）；非 `0` 表示失败，以 `msg` 为准。
- **鉴权**：无；请勿在请求中依赖后台 Session。

---

## 3. 接口一：校验授权码并绑定机器

用于客户端首次激活或每次启动时校验（由产品策略决定调用频率）。

### 请求

- **方法**：`POST`
- **URL**：`{API根地址}/index.php/api/client/verifyLicense`
- **推荐**：`Content-Type: application/x-www-form-urlencoded`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `license_key` | string | 是 | 用户输入的卡密，需与后台生成的一致（区分大小写，建议原样传输） |
| `machine_id` | string | 是 | 本机唯一标识；**同一安装实例必须始终传相同值**（见下文建议） |

### 服务端逻辑摘要

1. 卡密不存在 → 失败。  
2. 卡密已禁用 → 失败。  
3. 若设置了到期时间且已过期 → 失败。  
4. 若该卡密**尚未绑定**机器：本次请求会将 `machine_id` **写入服务器**，视为首次绑定。  
5. 若**已绑定**：传入的 `machine_id` 必须与服务器一致，否则视为「已绑定其他设备」→ 失败。

### 成功响应示例

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "valid": true,
    "expire_time": "2027-12-31 23:59:59"
  }
}
```

- `expire_time`：为空字符串时表示**不限期**（永久）。

### 失败示例（节选）

| msg（示例） | 含义 |
|-------------|------|
| 缺少 license_key 或 machine_id | 参数不全 |
| 授权码无效 | 无此卡密 |
| 授权码已禁用 | 后台已停用 |
| 授权已过期 | 超过 expire_time |
| 授权码已绑定其他设备 | machine_id 与首次绑定不一致 |

---

## 4. 接口二：检查更新

用于对比当前安装版本与后台**已发布**的最新版本。

### 请求

- **方法**：`GET` 或 `POST` 均可  
- **URL**：`{API根地址}/index.php/api/client/checkUpdate`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `current_version` | string | 是 | 当前客户端版本号，如 `1.0.0` |

GET 示例：

`.../index.php/api/client/checkUpdate?current_version=1.0.0`

### 版本比较规则

- 服务端使用 PHP 的 **`version_compare`** 语义比较版本字符串（与常见「主.次.修订」形式兼容，如 `1.0.10` 大于 `1.0.9`）。
- 仅在后台 **状态为「发布」** 的版本记录中筛选；在**大于** `current_version` 的记录里取**最新**的一条作为返回结果。

### 无更新（仍算成功）

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "has_update": false,
    "version": "",
    "release_notes": "",
    "download_url": "",
    "is_mandatory": 0
  }
}
```

### 有更新

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "has_update": true,
    "version": "1.2.0",
    "release_notes": "修复若干问题……",
    "download_url": "https://.../setup.exe",
    "is_mandatory": 1
  }
}
```

| 字段 | 说明 |
|------|------|
| `is_mandatory` | `1` 表示后台标记为**强制更新**，客户端宜提示用户必须升级；`0` 为可选更新 |
| `download_url` | 安装包地址，可能是 **https 绝对地址**，或本站相对路径（如 `/uploads/...`），客户端需与 `API根地址` 拼接成可下载 URL |

---

## 5. `machine_id` 实现建议（给对方客户端）

- 必须**稳定**：同一台机器、同一安装目录下多次启动应相同。  
- 必须**可区分不同机器**：避免所有客户端撞成同一个 id。  
- 常见做法：基于硬件信息 + 安装路径哈希，或首次运行生成 UUID 写入本地配置文件。  
- 长度：服务端字段上限 **128 字符**，请控制在此范围内。

---

## 6. 调用示例（curl）

**验权（Windows / Linux 通用）：**

```bash
curl -s -X POST "https://your-domain.com/index.php/api/client/verifyLicense" ^
  -H "Content-Type: application/x-www-form-urlencoded" ^
  -d "license_key=XXXX-XXXX-XXXX-XXXX&machine_id=my-pc-001"
```

（Linux 下将 `^` 换为 `\` 并写成一行即可。）

**检查更新：**

```bash
curl -s "https://your-domain.com/index.php/api/client/checkUpdate?current_version=1.0.0"
```

---

## 7. 运维与发布（给对方产品/运维的提示）

- 卡密、到期时间、启停、解绑：在**本系统后台 → 系统 → 发卡**维护。  
- 新版本、下载链接、是否强制更新：在**系统 → 版本**维护；仅 **发布** 状态会参与 `checkUpdate`。  
- 公开下载列表页：`/index.php/download`，便于人工下载历史包（非必须对接进客户端）。

---

## 8. 联系与变更

若接口路径或字段有升级，以仓库内 **`requirements.md`** 中「桌面端」章节及本文档最新版本为准。
