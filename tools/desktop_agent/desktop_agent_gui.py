#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desktop Agent Launcher (Windows-friendly)

Lightweight Tk launcher for tools/desktop_agent/agent.py
"""
from __future__ import annotations

import json
import os
import signal
import subprocess
import sys
import threading
from datetime import datetime
from pathlib import Path
from typing import Dict

import tkinter as tk
from tkinter import ttk, messagebox


BASE_DIR = Path(__file__).resolve().parent
AGENT_FILE = BASE_DIR / "agent.py"
RUNTIME_DIR = BASE_DIR / "runtime_gui"
RUNTIME_DIR.mkdir(parents=True, exist_ok=True)
CONFIG_FILE = RUNTIME_DIR / "launcher_config.json"
LOG_FILE = RUNTIME_DIR / "desktop_agent.log"


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
        self.root.title("TikStar Desktop Agent Launcher")
        self.root.geometry("700x560")
        self.root.minsize(660, 520)

        self.proc: subprocess.Popen | None = None
        self.log_fp = None

        self.vars: Dict[str, tk.StringVar] = {
            key: tk.StringVar(value=val) for key, val in DEFAULTS.items()
        }
        self.status_var = tk.StringVar(value="Status: stopped")

        self._build_ui()
        self._load_config()
        self._refresh_status()

        self.root.protocol("WM_DELETE_WINDOW", self.on_close)

    def _build_ui(self) -> None:
        pad = {"padx": 12, "pady": 6}

        frm = ttk.Frame(self.root)
        frm.pack(fill="both", expand=True, padx=10, pady=10)

        def row(label: str, key: str, show: str | None = None) -> None:
            line = ttk.Frame(frm)
            line.pack(fill="x", **pad)
            ttk.Label(line, text=label, width=22).pack(side="left")
            ent = ttk.Entry(line, textvariable=self.vars[key], show=show if show else "")
            ent.pack(side="left", fill="x", expand=True)

        row("Admin base URL", "admin_base")
        row("Agent token", "token")
        row("Device code", "device_code")
        row("Browser channel", "browser_channel")
        row("Task types (comma)", "task_types")
        row("Poll interval sec", "poll_interval_sec")
        row("Headless (0/1)", "headless")
        row("Send enter (0/1)", "send_enter")
        row("Dry run (0/1)", "dry_run")

        btns = ttk.Frame(frm)
        btns.pack(fill="x", padx=12, pady=10)
        ttk.Button(btns, text="Save config", command=self.save_config).pack(side="left", padx=4)
        ttk.Button(btns, text="Start agent", command=self.start_agent).pack(side="left", padx=4)
        ttk.Button(btns, text="Stop agent", command=self.stop_agent).pack(side="left", padx=4)
        ttk.Button(btns, text="Open log file", command=self.open_log_file).pack(side="left", padx=4)

        ttk.Separator(frm, orient="horizontal").pack(fill="x", padx=12, pady=8)
        ttk.Label(frm, textvariable=self.status_var).pack(anchor="w", padx=14)
        ttk.Label(frm, text=f"Log: {LOG_FILE}").pack(anchor="w", padx=14, pady=(2, 8))

        tips = tk.Text(frm, height=12, wrap="word")
        tips.pack(fill="both", expand=True, padx=12, pady=(0, 12))
        tips.insert(
            "1.0",
            "Quick start:\n"
            "1) Fill token and device_code from admin mobile-device page (Desktop platform).\n"
            "2) Click 'Save config'.\n"
            "3) Click 'Start agent'. Browser opens and keeps login profile.\n"
            "4) Keep this launcher running in background.\n\n"
            "If send fails, check log file and browser login status.",
        )
        tips.configure(state="disabled")

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
        messagebox.showinfo("Saved", f"Config saved:\n{CONFIG_FILE}")

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
            messagebox.showerror("Error", "Admin base URL is required")
            return False
        if not self.vars["token"].get().strip():
            messagebox.showerror("Error", "Agent token is required")
            return False
        if not self.vars["device_code"].get().strip():
            messagebox.showerror("Error", "Device code is required")
            return False
        if not AGENT_FILE.exists():
            messagebox.showerror("Error", f"agent.py not found:\n{AGENT_FILE}")
            return False
        return True

    def start_agent(self) -> None:
        if self.proc and self.proc.poll() is None:
            messagebox.showinfo("Running", "Agent is already running.")
            return
        if not self._validate():
            return
        self.save_config()
        self.log_fp = LOG_FILE.open("a", encoding="utf-8")
        self.log_fp.write(f"\n[{datetime.now().isoformat()}] launcher start\n")
        self.log_fp.flush()
        creationflags = 0
        if os.name == "nt":
            creationflags = subprocess.CREATE_NEW_PROCESS_GROUP  # type: ignore[attr-defined]
        self.proc = subprocess.Popen(
            [sys.executable, str(AGENT_FILE)],
            cwd=str(BASE_DIR),
            env=self._build_env(),
            stdout=self.log_fp,
            stderr=self.log_fp,
            creationflags=creationflags,
        )
        self._refresh_status()
        threading.Thread(target=self._watch_process, daemon=True).start()

    def _watch_process(self) -> None:
        if not self.proc:
            return
        self.proc.wait()
        self._refresh_status()

    def stop_agent(self) -> None:
        if not self.proc or self.proc.poll() is not None:
            messagebox.showinfo("Stopped", "Agent is not running.")
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
        self._refresh_status()

    def _refresh_status(self) -> None:
        if self.proc and self.proc.poll() is None:
            self.status_var.set(f"Status: running (pid={self.proc.pid})")
        else:
            self.status_var.set("Status: stopped")

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
            if not messagebox.askyesno("Exit", "Agent is still running. Stop and exit?"):
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

