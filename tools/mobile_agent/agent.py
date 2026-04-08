#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
TikStar OPS Mobile Agent (Android + Appium + ADB)

Execution boundary:
- Auto fill / open conversation / prepare text
- Human clicks "Send" manually
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import time
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional
from urllib.parse import quote_plus

import requests

try:
    from appium import webdriver
    from appium.options.android import UiAutomator2Options
except Exception:  # pragma: no cover
    webdriver = None
    UiAutomator2Options = None


VERSION = "mobile-agent/1.0.0"


def env_bool(name: str, default: bool = False) -> bool:
    val = os.getenv(name, "")
    if val == "":
        return default
    return val.strip().lower() in {"1", "true", "yes", "on"}


@dataclass
class AgentConfig:
    admin_base: str
    token: str
    device_code: str
    adb_serial: str
    poll_interval_sec: float
    request_timeout_sec: float
    appium_url: str
    use_appium: bool
    confirm_sent_prompt: bool
    task_types: List[str]
    screenshot_dir: Path

    @staticmethod
    def from_env() -> "AgentConfig":
        admin_base = os.getenv("MOBILE_AGENT_ADMIN_BASE", "http://127.0.0.1/admin.php").rstrip("/")
        token = os.getenv("MOBILE_AGENT_TOKEN", "").strip()
        device_code = os.getenv("MOBILE_AGENT_DEVICE_CODE", "").strip()
        adb_serial = os.getenv("MOBILE_AGENT_ADB_SERIAL", "").strip()
        poll_interval_sec = float(os.getenv("MOBILE_AGENT_POLL_INTERVAL_SEC", "2.5"))
        request_timeout_sec = float(os.getenv("MOBILE_AGENT_REQUEST_TIMEOUT_SEC", "15"))
        appium_url = os.getenv("MOBILE_AGENT_APPIUM_URL", "http://127.0.0.1:4723")
        use_appium = env_bool("MOBILE_AGENT_USE_APPIUM", True)
        confirm_sent_prompt = env_bool("MOBILE_AGENT_CONFIRM_SENT_PROMPT", False)
        task_types_raw = os.getenv("MOBILE_AGENT_TASK_TYPES", "").strip()
        task_types = [x.strip() for x in task_types_raw.split(",") if x.strip()] if task_types_raw else []
        screenshot_dir = Path(os.getenv("MOBILE_AGENT_SCREENSHOT_DIR", "runtime/mobile_agent")).resolve()
        screenshot_dir.mkdir(parents=True, exist_ok=True)
        return AgentConfig(
            admin_base=admin_base,
            token=token,
            device_code=device_code,
            adb_serial=adb_serial,
            poll_interval_sec=max(0.5, poll_interval_sec),
            request_timeout_sec=max(5.0, request_timeout_sec),
            appium_url=appium_url,
            use_appium=use_appium,
            confirm_sent_prompt=confirm_sent_prompt,
            task_types=task_types,
            screenshot_dir=screenshot_dir,
        )


class MobileAgent:
    def __init__(self, cfg: AgentConfig) -> None:
        self.cfg = cfg
        self.session = requests.Session()
        self.driver = None

    def run(self) -> None:
        self._validate_config()
        self._log("agent started", version=VERSION, device_code=self.cfg.device_code)
        while True:
            try:
                task = self.pull_task()
                if not task:
                    time.sleep(self.cfg.poll_interval_sec)
                    continue
                self.execute_task(task)
            except KeyboardInterrupt:
                self._log("interrupted, exiting")
                break
            except Exception as exc:
                self._log("loop error", error=str(exc))
                time.sleep(max(self.cfg.poll_interval_sec, 3.0))

        self.quit_driver()

    def _validate_config(self) -> None:
        if not self.cfg.token:
            raise RuntimeError("MOBILE_AGENT_TOKEN is required")
        if not self.cfg.device_code:
            raise RuntimeError("MOBILE_AGENT_DEVICE_CODE is required")
        if self.cfg.use_appium and webdriver is None:
            self._log("Appium package not found, fallback to ADB only")
            self.cfg.use_appium = False

    def _url(self, path: str) -> str:
        return f"{self.cfg.admin_base}/{path.lstrip('/')}"

    def _headers(self) -> Dict[str, str]:
        return {
            "Accept": "application/json",
            "Content-Type": "application/json; charset=utf-8",
            "X-Mobile-Agent-Token": self.cfg.token,
            "X-Device-Code": self.cfg.device_code,
            "User-Agent": VERSION,
        }

    def pull_task(self) -> Optional[Dict[str, Any]]:
        payload: Dict[str, Any] = {
            "token": self.cfg.token,
            "device_code": self.cfg.device_code,
            "agent_version": VERSION,
        }
        if self.cfg.task_types:
            payload["task_types"] = self.cfg.task_types
        res = self.session.post(
            self._url("mobile_agent/pull"),
            headers=self._headers(),
            data=json.dumps(payload, ensure_ascii=False),
            timeout=self.cfg.request_timeout_sec,
        )
        data = self._safe_json(res)
        if data.get("code") != 0:
            raise RuntimeError(f"pull failed: {data}")
        task = ((data.get("data") or {}).get("task")) if isinstance(data, dict) else None
        if not task:
            reason = ((data.get("data") or {}).get("reason")) if isinstance(data, dict) else ""
            if reason:
                self._log("queue idle", reason=reason)
            return None
        self._log("task pulled", task_id=task.get("id"), task_type=task.get("task_type"))
        return task

    def report_task(
        self,
        task_id: int,
        event: str,
        rendered_text: str = "",
        error_code: str = "",
        error_message: str = "",
        duration_ms: int = 0,
        screenshot_path: str = "",
        extra_payload: Optional[Dict[str, Any]] = None,
    ) -> None:
        payload: Dict[str, Any] = {
            "token": self.cfg.token,
            "device_code": self.cfg.device_code,
            "task_id": task_id,
            "event": event,
            "rendered_text": rendered_text,
            "error_code": error_code,
            "error_message": error_message,
            "duration_ms": max(0, int(duration_ms)),
            "screenshot_path": screenshot_path,
            "agent_version": VERSION,
        }
        if extra_payload:
            payload["payload"] = extra_payload
        res = self.session.post(
            self._url("mobile_agent/report"),
            headers=self._headers(),
            data=json.dumps(payload, ensure_ascii=False),
            timeout=self.cfg.request_timeout_sec,
        )
        data = self._safe_json(res)
        if data.get("code") != 0:
            raise RuntimeError(f"report failed: {data}")

    def execute_task(self, task: Dict[str, Any]) -> None:
        task_id = int(task.get("id") or 0)
        start = time.time()
        screenshot = ""
        try:
            task_type = str(task.get("task_type") or "")
            target_channel = str(task.get("target_channel") or "auto")
            payload = task.get("payload") or {}
            rendered_text = str(task.get("rendered_text") or payload.get("comment_text") or "")
            if not isinstance(payload, dict):
                payload = {}

            self.prepare_clipboard(rendered_text)
            self.open_channel(task_type, target_channel, payload, rendered_text)
            screenshot = self.capture_screenshot(task_id, "prepared")

            prepared_event = self.prepared_event_for_task(task_type)
            self.report_task(
                task_id=task_id,
                event=prepared_event,
                rendered_text=rendered_text,
                duration_ms=int((time.time() - start) * 1000),
                screenshot_path=screenshot,
                extra_payload={"target_channel": target_channel},
            )
            self._log("prepared", task_id=task_id, event=prepared_event)

            if self.cfg.confirm_sent_prompt:
                ans = input(f"[task {task_id}] Sent manually? (y/N): ").strip().lower()
                if ans in {"y", "yes"}:
                    sent_event = "comment_sent" if task_type == "comment_warmup" else "done"
                    self.report_task(
                        task_id=task_id,
                        event=sent_event,
                        rendered_text=rendered_text,
                        duration_ms=int((time.time() - start) * 1000),
                        screenshot_path=self.capture_screenshot(task_id, "sent"),
                    )
                    self._log("sent_confirmed", task_id=task_id, event=sent_event)
        except Exception as exc:
            err = str(exc)
            screenshot = screenshot or self.capture_screenshot(task_id, "failed")
            self._log("task failed", task_id=task_id, error=err)
            try:
                self.report_task(
                    task_id=task_id,
                    event="failed",
                    error_code="agent_exception",
                    error_message=err[:240],
                    duration_ms=int((time.time() - start) * 1000),
                    screenshot_path=screenshot,
                )
            except Exception as rep_exc:
                self._log("report failed", task_id=task_id, error=str(rep_exc))

    def prepared_event_for_task(self, task_type: str) -> str:
        if task_type == "comment_warmup":
            return "comment_prepared"
        if task_type == "tiktok_dm":
            return "dm_prepared"
        return "im_prepared"

    def open_channel(self, task_type: str, target_channel: str, payload: Dict[str, Any], rendered_text: str) -> None:
        channels = payload.get("channels") or {}
        if not isinstance(channels, dict):
            channels = {}
        if target_channel == "wa":
            number = str(channels.get("whatsapp") or "").strip()
            if not number:
                raise RuntimeError("missing whatsapp number")
            url = f"https://wa.me/{number}"
            if rendered_text:
                url += f"?text={quote_plus(rendered_text)}"
            self.open_url_with_adb(url)
            return
        if target_channel == "zalo":
            zalo_id = str(channels.get("zalo") or "").strip()
            if not zalo_id:
                raise RuntimeError("missing zalo id")
            self.open_url_with_adb(f"https://zalo.me/{zalo_id}")
            return

        # TikTok comment / DM fallback
        handle = str(payload.get("tiktok_id") or "").strip()
        if not handle:
            raise RuntimeError("missing tiktok handle")
        if not handle.startswith("@"):
            handle = "@" + handle
        self.open_url_with_adb(f"https://www.tiktok.com/{handle}")
        if task_type == "comment_warmup":
            self._log("tip", msg="Please open latest TikTok video comment box, paste text, then tap Send manually.")
        else:
            self._log("tip", msg="Please open TikTok DM box, paste text, then tap Send manually.")

    def prepare_clipboard(self, text: str) -> None:
        value = str(text or "").strip()
        if not value:
            return
        if self.cfg.use_appium:
            drv = self.ensure_driver()
            if drv is not None:
                try:
                    drv.set_clipboard_text(value)
                    return
                except Exception:
                    pass
        self._log("clipboard fallback", msg="Appium clipboard failed, please copy message manually.")

    def ensure_driver(self):
        if not self.cfg.use_appium:
            return None
        if self.driver is not None:
            return self.driver
        if webdriver is None or UiAutomator2Options is None:
            return None
        caps = {
            "platformName": "Android",
            "appium:automationName": "UiAutomator2",
            "appium:newCommandTimeout": 120,
            "appium:noReset": True,
        }
        if self.cfg.adb_serial:
            caps["appium:udid"] = self.cfg.adb_serial
        options = UiAutomator2Options().load_capabilities(caps)
        self.driver = webdriver.Remote(self.cfg.appium_url, options=options)
        return self.driver

    def quit_driver(self) -> None:
        if self.driver is not None:
            try:
                self.driver.quit()
            except Exception:
                pass
            self.driver = None

    def open_url_with_adb(self, url: str) -> None:
        cmd = ["adb"]
        if self.cfg.adb_serial:
            cmd += ["-s", self.cfg.adb_serial]
        cmd += ["shell", "am", "start", "-a", "android.intent.action.VIEW", "-d", url]
        proc = subprocess.run(cmd, capture_output=True, text=True)
        if proc.returncode != 0:
            stderr = (proc.stderr or "").strip()
            raise RuntimeError(f"adb open failed: {stderr}")

    def capture_screenshot(self, task_id: int, suffix: str) -> str:
        if not self.cfg.use_appium:
            return ""
        drv = self.ensure_driver()
        if drv is None:
            return ""
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"task_{task_id}_{suffix}_{ts}.png"
        path = self.cfg.screenshot_dir / filename
        try:
            drv.get_screenshot_as_file(str(path))
            return str(path).replace("\\", "/")
        except Exception:
            return ""

    def _safe_json(self, response: requests.Response) -> Dict[str, Any]:
        text = response.text or ""
        try:
            return response.json()
        except Exception:
            raise RuntimeError(f"non-json response({response.status_code}): {text[:280]}")

    def _log(self, msg: str, **kwargs: Any) -> None:
        payload = {"time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"), "msg": msg}
        payload.update(kwargs)
        print(json.dumps(payload, ensure_ascii=False), flush=True)


def main() -> int:
    cfg = AgentConfig.from_env()
    agent = MobileAgent(cfg)
    agent.run()
    return 0


if __name__ == "__main__":
    sys.exit(main())
