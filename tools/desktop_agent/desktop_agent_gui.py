#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desktop Agent Launcher (Windows-friendly)

目标：
1) 桌面一键运行（自动准备 .venv 与依赖）
2) 启动失败可见（不再“点了没反应”）
3) 中文界面 + 实时日志 + 一键诊断
"""
from __future__ import annotations

import json
import os
import shutil
import signal
import subprocess
import sys
import threading
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple
from urllib.request import Request, urlopen
from urllib.error import URLError, HTTPError

import tkinter as tk
from tkinter import ttk, messagebox


def is_frozen() -> bool:
    return bool(getattr(sys, "frozen", False))


if is_frozen():
    APP_DIR = Path(sys.executable).resolve().parent
    BUNDLE_DIR = Path(getattr(sys, "_MEIPASS", APP_DIR))
else:
    APP_DIR = Path(__file__).resolve().parent
    BUNDLE_DIR = APP_DIR

AGENT_FILE = (BUNDLE_DIR / "agent.py").resolve()
RUNTIME_DIR = APP_DIR / "runtime_gui"
RUNTIME_DIR.mkdir(parents=True, exist_ok=True)
CONFIG_FILE = RUNTIME_DIR / "launcher_config.json"
LOG_FILE = RUNTIME_DIR / "desktop_agent.log"
VENV_DIR = APP_DIR / ".venv"
VENV_PY = VENV_DIR / ("Scripts/python.exe" if os.name == "nt" else "bin/python3")


DEFAULTS: Dict[str, str] = {
    "admin_base": "http://127.0.0.1/admin.php",
    "token": "",
    "device_code": "",
    "browser_channel": "msedge",
    "headless": "0",
    "send_enter": "1",
    "dry_run": "0",
    "task_types": "zalo_auto_dm,wa_auto_dm",
    "poll_interval_sec": "2.0",
}


class LauncherApp:
    def __init__(self) -> None:
        self.root = tk.Tk()
        self.root.title("TikStar 桌面代理")
        self.root.geometry("860x700")
        self.root.minsize(820, 640)

        self.proc: Optional[subprocess.Popen] = None
        self.log_fp = None
        self.agent_python: Optional[str] = None
        self.installing = False
        self._log_pos = 0

        self.vars: Dict[str, tk.StringVar] = {
            key: tk.StringVar(value=val) for key, val in DEFAULTS.items()
        }
        self.status_var = tk.StringVar(value="状态：未运行")
        self.health_var = tk.StringVar(value="健康度：未检测")

        self._build_ui()
        self._load_config()
        self._refresh_status()
        self._start_timers()
        self.root.protocol("WM_DELETE_WINDOW", self.on_close)

    def _build_ui(self) -> None:
        pad = {"padx": 12, "pady": 6}
        frm = ttk.Frame(self.root)
        frm.pack(fill="both", expand=True, padx=10, pady=10)

        head = ttk.Frame(frm)
        head.pack(fill="x", **pad)
        ttk.Label(head, text="桌面自动私信代理（Zalo / WhatsApp）", font=("Microsoft YaHei UI", 12, "bold")).pack(side="left")

        def row(label: str, key: str) -> None:
            line = ttk.Frame(frm)
            line.pack(fill="x", **pad)
            ttk.Label(line, text=label, width=22).pack(side="left")
            ent = ttk.Entry(line, textvariable=self.vars[key])
            ent.pack(side="left", fill="x", expand=True)

        row("后台地址", "admin_base")
        row("代理令牌", "token")
        row("设备编码", "device_code")
        row("浏览器通道", "browser_channel")
        row("任务类型（逗号分隔）", "task_types")
        row("轮询间隔（秒）", "poll_interval_sec")
        row("无头模式（0/1）", "headless")
        row("自动回车（0/1）", "send_enter")
        row("演练模式（0/1）", "dry_run")

        btns = ttk.Frame(frm)
        btns.pack(fill="x", padx=12, pady=10)
        ttk.Button(btns, text="保存配置", command=self.save_config).pack(side="left", padx=4)
        ttk.Button(btns, text="一键诊断", command=self.run_diagnosis).pack(side="left", padx=4)
        ttk.Button(btns, text="安装/修复依赖", command=self.install_dependencies_async).pack(side="left", padx=4)
        ttk.Button(btns, text="启动代理", command=self.start_agent).pack(side="left", padx=4)
        ttk.Button(btns, text="停止代理", command=self.stop_agent).pack(side="left", padx=4)
        ttk.Button(btns, text="打开日志", command=self.open_log_file).pack(side="left", padx=4)

        ttk.Separator(frm, orient="horizontal").pack(fill="x", padx=12, pady=8)
        ttk.Label(frm, textvariable=self.status_var).pack(anchor="w", padx=14)
        ttk.Label(frm, textvariable=self.health_var).pack(anchor="w", padx=14, pady=(2, 2))
        ttk.Label(frm, text=f"日志文件：{LOG_FILE}").pack(anchor="w", padx=14, pady=(2, 8))

        log_frame = ttk.Frame(frm)
        log_frame.pack(fill="both", expand=True, padx=12, pady=(0, 8))
        self.log_text = tk.Text(log_frame, height=16, wrap="word")
        self.log_text.pack(side="left", fill="both", expand=True)
        scroll = ttk.Scrollbar(log_frame, orient="vertical", command=self.log_text.yview)
        scroll.pack(side="right", fill="y")
        self.log_text.configure(yscrollcommand=scroll.set, state="disabled")

        tips = tk.Text(frm, height=8, wrap="word")
        tips.pack(fill="x", padx=12, pady=(0, 12))
        tips.insert(
            "1.0",
            "快速开始：\n"
            "1）后台【移动设备】新增平台=Desktop，复制 token 与 device_code。\n"
            "2）填入本窗口并点“启动代理”。\n"
            "3）首次会自动安装依赖并打开浏览器，请先登录 Zalo/WhatsApp Web。\n"
            "4）保持本程序后台运行即可自动拉任务发送。\n\n"
            "提示：如果点击启动无反应，先看“健康度”和日志。",
        )
        tips.configure(state="disabled")

    def _start_timers(self) -> None:
        self.root.after(1200, self._tick_status)
        self.root.after(1000, self._tick_log)

    def _tick_status(self) -> None:
        self._refresh_status()
        self.root.after(1200, self._tick_status)

    def _tick_log(self) -> None:
        self._append_new_logs()
        self.root.after(1000, self._tick_log)

    def _append_new_logs(self) -> None:
        if not LOG_FILE.exists():
            return
        try:
            with LOG_FILE.open("r", encoding="utf-8", errors="replace") as f:
                f.seek(self._log_pos)
                chunk = f.read()
                self._log_pos = f.tell()
            if not chunk:
                return
            self.log_text.configure(state="normal")
            self.log_text.insert("end", chunk)
            self.log_text.see("end")
            self.log_text.configure(state="disabled")
        except Exception:
            pass

    def _append_runtime_log(self, text: str) -> None:
        ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        line = f"[{ts}] {text}\n"
        with LOG_FILE.open("a", encoding="utf-8") as fp:
            fp.write(line)
            fp.flush()
        self._append_new_logs()

    def _load_config(self) -> None:
        if not CONFIG_FILE.exists():
            return
        try:
            data = json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
            if not isinstance(data, dict):
                return
            for key, var in self.vars.items():
                if key in data:
                    var.set(str(data[key]))
        except Exception:
            pass

    def save_config(self) -> None:
        data = {k: v.get().strip() for k, v in self.vars.items()}
        CONFIG_FILE.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
        messagebox.showinfo("已保存", f"配置已保存：\n{CONFIG_FILE}")

    def _find_bootstrap_python(self) -> Tuple[List[str], str]:
        if not is_frozen():
            return [sys.executable], sys.executable

        py = shutil.which("py")
        if py:
            return [py, "-3"], f"{py} -3"
        python = shutil.which("python")
        if python:
            return [python], python
        raise RuntimeError("未找到可用 Python，请先安装 Python 3.10+ 并加入 PATH。")

    def _run_cmd(self, cmd: List[str], timeout: Optional[int] = None) -> None:
        proc = subprocess.run(
            cmd,
            cwd=str(APP_DIR),
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            timeout=timeout,
        )
        if proc.stdout:
            self._append_runtime_log(proc.stdout.strip())
        if proc.stderr:
            self._append_runtime_log(proc.stderr.strip())
        if proc.returncode != 0:
            raise RuntimeError(f"命令失败：{' '.join(cmd)}")

    def _ensure_agent_python(self) -> str:
        if self.agent_python and Path(self.agent_python).exists():
            return self.agent_python

        bootstrap_cmd, bootstrap_hint = self._find_bootstrap_python()
        self._append_runtime_log(f"Bootstrap Python: {bootstrap_hint}")

        if not VENV_PY.exists():
            self._append_runtime_log("创建虚拟环境 .venv ...")
            self._run_cmd(bootstrap_cmd + ["-m", "venv", str(VENV_DIR)], timeout=180)

        py_path = str(VENV_PY)
        self._append_runtime_log(f"使用解释器：{py_path}")
        self.agent_python = py_path
        return py_path

    def _ensure_dependencies(self) -> None:
        py = self._ensure_agent_python()
        check = subprocess.run(
            [py, "-c", "import playwright"],
            cwd=str(APP_DIR),
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
        )
        if check.returncode == 0:
            return
        self._append_runtime_log("缺少依赖，开始自动安装（首次可能较慢）...")
        self._run_cmd([py, "-m", "pip", "install", "--upgrade", "pip"], timeout=240)
        self._run_cmd([py, "-m", "pip", "install", "-r", str(APP_DIR / "requirements.txt")], timeout=600)
        self._run_cmd([py, "-m", "playwright", "install", "chromium"], timeout=900)
        self._append_runtime_log("依赖安装完成。")

    def install_dependencies_async(self) -> None:
        if self.installing:
            messagebox.showinfo("处理中", "依赖安装正在进行中，请稍候。")
            return

        def worker() -> None:
            self.installing = True
            try:
                self._ensure_dependencies()
                self.root.after(0, lambda: messagebox.showinfo("完成", "依赖安装/修复完成。"))
            except Exception as exc:
                self._append_runtime_log(f"依赖安装失败：{exc}")
                self.root.after(0, lambda: messagebox.showerror("失败", f"依赖安装失败：\n{exc}"))
            finally:
                self.installing = False

        threading.Thread(target=worker, daemon=True).start()

    def _build_env(self) -> Dict[str, str]:
        env = os.environ.copy()
        env.update(
            {
                "DESKTOP_AGENT_ADMIN_BASE": self.vars["admin_base"].get().strip(),
                "DESKTOP_AGENT_TOKEN": self.vars["token"].get().strip(),
                "DESKTOP_AGENT_DEVICE_CODE": self.vars["device_code"].get().strip(),
                "DESKTOP_AGENT_BROWSER_CHANNEL": self.vars["browser_channel"].get().strip() or "msedge",
                "DESKTOP_AGENT_HEADLESS": self.vars["headless"].get().strip() or "0",
                "DESKTOP_AGENT_SEND_ENTER": self.vars["send_enter"].get().strip() or "1",
                "DESKTOP_AGENT_DRY_RUN": self.vars["dry_run"].get().strip() or "0",
                "DESKTOP_AGENT_TASK_TYPES": self.vars["task_types"].get().strip(),
                "DESKTOP_AGENT_POLL_INTERVAL_SEC": self.vars["poll_interval_sec"].get().strip() or "2.0",
                "PYTHONUNBUFFERED": "1",
            }
        )
        return env

    def _validate(self) -> bool:
        if not self.vars["admin_base"].get().strip():
            messagebox.showerror("错误", "后台地址不能为空")
            return False
        if not self.vars["token"].get().strip():
            messagebox.showerror("错误", "代理令牌不能为空")
            return False
        if not self.vars["device_code"].get().strip():
            messagebox.showerror("错误", "设备编码不能为空")
            return False
        if not AGENT_FILE.exists():
            messagebox.showerror("错误", f"未找到 agent.py：\n{AGENT_FILE}")
            return False
        return True

    def run_diagnosis(self) -> None:
        try:
            admin_base = self.vars["admin_base"].get().strip()
            token = self.vars["token"].get().strip()
            device_code = self.vars["device_code"].get().strip()
            if not admin_base or not token or not device_code:
                self.health_var.set("健康度：配置不完整（请先填写后台地址/token/设备编码）")
                return
            py = self._ensure_agent_python()
            py_ok = Path(py).exists()
            req = Request(url=admin_base, method="GET")
            try:
                with urlopen(req, timeout=8) as resp:
                    _ = resp.read(128)
                    http_ok = int(getattr(resp, "status", 200) or 200) < 500
            except HTTPError as exc:
                http_ok = exc.code < 500
            except URLError:
                http_ok = False

            if py_ok and http_ok:
                self.health_var.set("健康度：良好（Python可用，后台可访问）")
            elif py_ok:
                self.health_var.set("健康度：后台不可达（请检查后台地址）")
            else:
                self.health_var.set("健康度：Python不可用")
        except Exception as exc:
            self.health_var.set(f"健康度：诊断失败（{exc}）")

    def start_agent(self) -> None:
        if self.proc and self.proc.poll() is None:
            messagebox.showinfo("运行中", "代理已在运行中")
            return
        if not self._validate():
            return
        self.save_config()

        try:
            self._ensure_dependencies()
            py = self._ensure_agent_python()
        except Exception as exc:
            self._append_runtime_log(f"启动前检查失败：{exc}")
            messagebox.showerror("启动失败", f"环境准备失败：\n{exc}")
            return

        self.log_fp = LOG_FILE.open("a", encoding="utf-8")
        self.log_fp.write(f"\n[{datetime.now().isoformat()}] launcher start\n")
        self.log_fp.flush()
        creationflags = 0
        if os.name == "nt":
            creationflags = subprocess.CREATE_NEW_PROCESS_GROUP  # type: ignore[attr-defined]
        self.proc = subprocess.Popen(
            [py, str(AGENT_FILE)],
            cwd=str(APP_DIR),
            env=self._build_env(),
            stdout=self.log_fp,
            stderr=self.log_fp,
            creationflags=creationflags,
        )
        self._append_runtime_log(f"代理进程已启动，PID={self.proc.pid}")
        self._refresh_status()
        threading.Thread(target=self._watch_process, daemon=True).start()

        self.root.after(2500, self._detect_quick_exit)

    def _detect_quick_exit(self) -> None:
        if not self.proc:
            return
        rc = self.proc.poll()
        if rc is None:
            return
        msg = f"代理启动后立即退出（exit={rc}），请查看日志。"
        self._append_runtime_log(msg)
        messagebox.showerror("启动失败", msg)

    def _watch_process(self) -> None:
        if not self.proc:
            return
        self.proc.wait()
        self._append_runtime_log(f"代理已退出，exit={self.proc.returncode}")
        self._refresh_status()

    def stop_agent(self) -> None:
        if not self.proc or self.proc.poll() is not None:
            messagebox.showinfo("已停止", "代理当前未运行")
            return
        try:
            if os.name == "nt":
                self.proc.send_signal(signal.CTRL_BREAK_EVENT)  # type: ignore[arg-type]
            else:
                self.proc.terminate()
        except Exception:
            try:
                self.proc.kill()
            except Exception:
                pass
        self._append_runtime_log("已发送停止信号。")
        self._refresh_status()

    def _refresh_status(self) -> None:
        if self.proc and self.proc.poll() is None:
            self.status_var.set(f"状态：运行中（PID={self.proc.pid}）")
        else:
            self.status_var.set("状态：未运行")

    def open_log_file(self) -> None:
        LOG_FILE.touch(exist_ok=True)
        if os.name == "nt":
            os.startfile(str(LOG_FILE))  # type: ignore[attr-defined]
            return
        if sys.platform == "darwin":
            subprocess.Popen(["open", str(LOG_FILE)])
            return
        subprocess.Popen(["xdg-open", str(LOG_FILE)])

    def on_close(self) -> None:
        if self.proc and self.proc.poll() is None:
            if not messagebox.askyesno("退出", "代理仍在运行，是否先停止并退出？"):
                return
            self.stop_agent()
        if self.log_fp:
            try:
                self.log_fp.close()
            except Exception:
                pass
        self.root.destroy()

    def run(self) -> None:
        self.root.mainloop()


def main() -> int:
    app = LauncherApp()
    app.run()
    return 0


if __name__ == "__main__":
    sys.exit(main())
