# Desktop Agent（电脑自动私信执行器）

用于在 **Windows/Linux 电脑端** 直接执行 Auto DM 任务（Zalo / WhatsApp）：

- 拉取任务：`/admin.php/desktop_agent/pull_auto`
- 自动打开会话、输入文案、回车发送
- 回报结果：`/admin.php/desktop_agent/report_auto`

注意：
- 首次运行会打开浏览器，请先在对应网页完成登录（WhatsApp Web / Zalo Web）。
- Zalo 网页结构可能变动；如无法定位输入框，程序会回报失败并写日志，便于排查。

---

## 1. 最简方式（Windows 图形启动器）

第一次：

```powershell
cd tools\desktop_agent
python -m venv .venv
.\.venv\Scripts\activate
pip install -r requirements.txt
python -m playwright install chromium
```

之后每次双击：

- `tools\desktop_agent\run_gui.bat`

会打开一个小窗口，你只需要填：
- `Admin base URL`
- `Agent token`
- `Device code`

点 `Start agent` 即可运行。

---

## 2. 安装（Windows 命令行）

```powershell
cd tools\desktop_agent
python -m venv .venv
.\.venv\Scripts\activate
pip install -r requirements.txt
python -m playwright install chromium
```

## 3. 安装（Linux）

```bash
cd tools/desktop_agent
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -m playwright install chromium
```

---

## 4. 环境变量

必填：
- `DESKTOP_AGENT_ADMIN_BASE` 例如 `http://127.0.0.1/admin.php`
- `DESKTOP_AGENT_TOKEN` 设备 token（来自“移动设备/Desktop设备”）
- `DESKTOP_AGENT_DEVICE_CODE` 设备编码（desktop_xxx）

常用可选：
- `DESKTOP_AGENT_BROWSER_CHANNEL=msedge`（Windows 推荐）
- `DESKTOP_AGENT_HEADLESS=0`
- `DESKTOP_AGENT_SEND_ENTER=1`（自动按 Enter 发送）
- `DESKTOP_AGENT_DRY_RUN=0`（1=只拉取并直接回报 sent，不实际发送）
- `DESKTOP_AGENT_USER_DATA_DIR=runtime/desktop_agent/browser_profile`
- `DESKTOP_AGENT_SCREENSHOT_DIR=runtime/desktop_agent/screenshots`
- `DESKTOP_AGENT_TASK_TYPES=zalo_auto_dm,wa_auto_dm`

---

## 5. 启动（Windows）

```powershell
set DESKTOP_AGENT_ADMIN_BASE=http://127.0.0.1/admin.php
set DESKTOP_AGENT_TOKEN=your_token
set DESKTOP_AGENT_DEVICE_CODE=desktop_20260421_xxxxxx
set DESKTOP_AGENT_BROWSER_CHANNEL=msedge
set DESKTOP_AGENT_HEADLESS=0
set DESKTOP_AGENT_SEND_ENTER=1
python tools\desktop_agent\agent.py
```

## 6. 启动（Linux）

```bash
export DESKTOP_AGENT_ADMIN_BASE="http://127.0.0.1/admin.php"
export DESKTOP_AGENT_TOKEN="your_token"
export DESKTOP_AGENT_DEVICE_CODE="desktop_20260421_xxxxxx"
export DESKTOP_AGENT_HEADLESS=0
export DESKTOP_AGENT_SEND_ENTER=1
python3 tools/desktop_agent/agent.py
```

---

## 7. 打包为 EXE（Windows）

```powershell
cd tools\desktop_agent
powershell -ExecutionPolicy Bypass -File .\build_windows_exe.ps1
```

输出：
- `tools\desktop_agent\dist\TikStarDesktopAgentLauncher.exe`

---

## 8. 故障排查

1. 返回 `non-json response`  
   检查 `DESKTOP_AGENT_ADMIN_BASE` 是否正确（必须指向 `/admin.php` 入口）。

2. `whatsapp_input_not_found_or_not_logged_in`  
   先在打开的浏览器里登录 WhatsApp Web，再重试。

3. `zalo_input_not_found_for_id:*`  
   说明当前 Zalo 页面输入框未识别；先确认已登录 Zalo Web，且能打开对应会话。

4. 任务一直 `empty_queue`  
   检查活动是否在运行状态，且设备/活动的执行端策略允许 desktop。
