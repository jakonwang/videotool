# Mobile Agent (Android + Appium + ADB)

该目录提供 TikStar OPS 的移动执行层脚本，实现：
`后台编排 -> Mobile Agent 拉取任务 -> 手机自动打开会话并填充文案 -> 人工点击发送 -> 回传状态`。

## 1. 合规边界
- 自动化范围：打开 TikTok/Zalo/WhatsApp、准备文案、回传 `*_prepared` 事件。
- 人工操作范围：最终发送动作必须由人工点击完成。
- 默认不做无人值守自动发送。

## 2. 依赖安装

Windows:
```powershell
cd tools\mobile_agent
python -m venv .venv
.\.venv\Scripts\activate
pip install -r requirements.txt
```

Linux:
```bash
cd tools/mobile_agent
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

## 3. 环境变量

必填：
- `MOBILE_AGENT_ADMIN_BASE` 例如 `http://127.0.0.1/admin.php`
- `MOBILE_AGENT_TOKEN` 设备 token（来自 `mobile_devices.agent_token`）
- `MOBILE_AGENT_DEVICE_CODE` 设备编码（来自 `mobile_devices.device_code`）

推荐：
- `MOBILE_AGENT_ADB_SERIAL` 真机序列号（`adb devices`）
- `MOBILE_AGENT_USE_APPIUM=1`
- `MOBILE_AGENT_APPIUM_URL=http://127.0.0.1:4723`
- `MOBILE_AGENT_CONFIRM_SENT_PROMPT=1`（终端确认是否已人工发送）
- `MOBILE_AGENT_TASK_TYPES=comment_warmup,tiktok_dm,zalo_im,wa_im`

## 4. 启动

Windows:
```powershell
set MOBILE_AGENT_ADMIN_BASE=http://127.0.0.1/admin.php
set MOBILE_AGENT_TOKEN=your_token
set MOBILE_AGENT_DEVICE_CODE=android_01
set MOBILE_AGENT_ADB_SERIAL=emulator-5554
python tools\mobile_agent\agent.py
```

Linux:
```bash
export MOBILE_AGENT_ADMIN_BASE="http://127.0.0.1/admin.php"
export MOBILE_AGENT_TOKEN="your_token"
export MOBILE_AGENT_DEVICE_CODE="android_01"
export MOBILE_AGENT_ADB_SERIAL="emulator-5554"
python3 tools/mobile_agent/agent.py
```

## 5. 回传协议
- 拉取任务：`POST /admin.php/mobile_agent/pull`
- 回传结果：`POST /admin.php/mobile_agent/report`
- 关键动作：`comment_prepared/comment_sent/dm_prepared/im_prepared`

## 6. 常见问题
- 返回 HTML 非 JSON：通常是网关路径错误或 token 无效，请核对 `MOBILE_AGENT_ADMIN_BASE` 与 `MOBILE_AGENT_TOKEN`。
- ADB 打不开 App：先确认 `adb devices` 能看到设备，并确保手机已登录对应 App。
- Appium 不可用：可先设 `MOBILE_AGENT_USE_APPIUM=0`，仅用 ADB 跳转 + 人工粘贴发送。
