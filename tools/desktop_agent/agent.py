#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
TikStar OPS Desktop Agent (Auto DM for PC)

Flow:
- pull task from /admin.php/desktop_agent/pull_auto
- open target chat page in browser (persistent profile)
- auto input message + auto send
- report status to /admin.php/desktop_agent/report_auto
"""
from __future__ import annotations

import json
import os
import re
import sys
import time
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional
from urllib.parse import quote_plus
from urllib.parse import urlsplit, urlunsplit, parse_qsl, urlencode
from urllib.request import Request, urlopen
from urllib.error import URLError, HTTPError

try:
    from playwright.sync_api import BrowserContext, Page, TimeoutError as PwTimeoutError, sync_playwright
except ModuleNotFoundError:
    BrowserContext = Any  # type: ignore[assignment]
    Page = Any  # type: ignore[assignment]
    PwTimeoutError = TimeoutError  # type: ignore[assignment]
    sync_playwright = None  # type: ignore[assignment]


VERSION = "desktop-agent/1.0.0"


def now_text() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def env_bool(name: str, default: bool = False) -> bool:
    raw = os.getenv(name, "").strip().lower()
    if raw == "":
        return default
    return raw in {"1", "true", "yes", "on"}


def norm_number(raw: str) -> str:
    return re.sub(r"\D+", "", (raw or "").strip())


@dataclass
class AgentConfig:
    admin_base: str
    token: str
    device_code: str
    pull_path: str
    report_path: str
    poll_interval_sec: float
    request_timeout_sec: float
    headless: bool
    browser_channel: str
    user_data_dir: Path
    screenshot_dir: Path
    task_types: List[str]
    send_enter: bool
    dry_run: bool

    @staticmethod
    def from_env() -> "AgentConfig":
        admin_base = os.getenv("DESKTOP_AGENT_ADMIN_BASE", "http://127.0.0.1/admin.php").rstrip("/")
        token = os.getenv("DESKTOP_AGENT_TOKEN", "").strip()
        device_code = os.getenv("DESKTOP_AGENT_DEVICE_CODE", "").strip()
        pull_path = os.getenv("DESKTOP_AGENT_PULL_PATH", "desktop_agent/pull_auto").strip() or "desktop_agent/pull_auto"
        report_path = os.getenv("DESKTOP_AGENT_REPORT_PATH", "desktop_agent/report_auto").strip() or "desktop_agent/report_auto"
        poll_interval_sec = max(0.8, float(os.getenv("DESKTOP_AGENT_POLL_INTERVAL_SEC", "2.0")))
        request_timeout_sec = max(5.0, float(os.getenv("DESKTOP_AGENT_REQUEST_TIMEOUT_SEC", "20")))
        headless = env_bool("DESKTOP_AGENT_HEADLESS", False)
        browser_channel = os.getenv("DESKTOP_AGENT_BROWSER_CHANNEL", "msedge").strip() or "msedge"
        user_data_dir = Path(os.getenv("DESKTOP_AGENT_USER_DATA_DIR", "runtime/desktop_agent/browser_profile")).resolve()
        screenshot_dir = Path(os.getenv("DESKTOP_AGENT_SCREENSHOT_DIR", "runtime/desktop_agent/screenshots")).resolve()
        task_types_raw = os.getenv("DESKTOP_AGENT_TASK_TYPES", "").strip()
        task_types = [x.strip() for x in task_types_raw.split(",") if x.strip()] if task_types_raw else []
        send_enter = env_bool("DESKTOP_AGENT_SEND_ENTER", True)
        dry_run = env_bool("DESKTOP_AGENT_DRY_RUN", False)
        user_data_dir.mkdir(parents=True, exist_ok=True)
        screenshot_dir.mkdir(parents=True, exist_ok=True)

        return AgentConfig(
            admin_base=admin_base,
            token=token,
            device_code=device_code,
            pull_path=pull_path,
            report_path=report_path,
            poll_interval_sec=poll_interval_sec,
            request_timeout_sec=request_timeout_sec,
            headless=headless,
            browser_channel=browser_channel,
            user_data_dir=user_data_dir,
            screenshot_dir=screenshot_dir,
            task_types=task_types,
            send_enter=send_enter,
            dry_run=dry_run,
        )


class DesktopAgent:
    def __init__(self, cfg: AgentConfig) -> None:
        self.cfg = cfg
        self._pw = None
        self._ctx: Optional[BrowserContext] = None
        self._working_url: Dict[str, str] = {}

    def run(self) -> None:
        self._validate()
        self._log("agent_started", version=VERSION, device_code=self.cfg.device_code, browser=self.cfg.browser_channel)
        while True:
            try:
                task = self.pull_task()
                if not task:
                    time.sleep(self.cfg.poll_interval_sec)
                    continue
                self.execute_task(task)
            except KeyboardInterrupt:
                self._log("agent_stopped", reason="keyboard_interrupt")
                break
            except Exception as exc:
                self._log("loop_error", error=str(exc))
                time.sleep(max(2.5, self.cfg.poll_interval_sec))
        self.close_browser()

    def _validate(self) -> None:
        if not self.cfg.token:
            raise RuntimeError("DESKTOP_AGENT_TOKEN is required")
        if not self.cfg.device_code:
            raise RuntimeError("DESKTOP_AGENT_DEVICE_CODE is required")

    def _url(self, path: str) -> str:
        return f"{self.cfg.admin_base}/{path.lstrip('/')}"

    def _candidate_urls(self, path: str) -> List[str]:
        clean_path = path.lstrip("/")
        base = self.cfg.admin_base.rstrip("/")
        out: List[str] = []
        seen = set()

        def add(u: str) -> None:
            if u and u not in seen:
                seen.add(u)
                out.append(u)

        if base.endswith(".php"):
            add(f"{base}/{clean_path}")
            parsed = urlsplit(base)
            q = dict(parse_qsl(parsed.query, keep_blank_values=True))
            q["s"] = clean_path
            query = urlencode(q, doseq=True)
            add(urlunsplit((parsed.scheme, parsed.netloc, parsed.path, query, parsed.fragment)))
        else:
            add(f"{base}/{clean_path}")
            add(f"{base}/admin.php/{clean_path}")
            add(f"{base}/public/admin.php/{clean_path}")
            add(f"{base}/admin.php?s={clean_path}")
            add(f"{base}/public/admin.php?s={clean_path}")
        return out

    def _headers(self) -> Dict[str, str]:
        return {
            "Accept": "application/json",
            "Content-Type": "application/json; charset=utf-8",
            "X-Mobile-Agent-Token": self.cfg.token,
            "X-Device-Code": self.cfg.device_code,
            "User-Agent": VERSION,
        }

    def _post_json(self, urls: List[str], payload: Dict[str, Any]) -> tuple[Dict[str, Any], str]:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        last_exc: Optional[Exception] = None
        for url in urls:
            req = Request(url=url, data=body, headers=self._headers(), method="POST")
            try:
                with urlopen(req, timeout=self.cfg.request_timeout_sec) as resp:
                    status = int(getattr(resp, "status", 200) or 200)
                    raw = resp.read()
                    text = raw.decode("utf-8", errors="replace")
                try:
                    data = json.loads(text)
                    if not isinstance(data, dict):
                        raise RuntimeError(f"non-json response({status}): {text[:300]}")
                    return data, url
                except Exception as exc:
                    last_exc = RuntimeError(f"non-json response({status}): {text[:300]}")
                    # If response indicates routing miss, continue trying fallback URLs.
                    if "No input file specified" in text or status == 404:
                        continue
                    raise last_exc from exc
            except HTTPError as exc:
                raw = exc.read() if hasattr(exc, "read") else b""
                text = raw.decode("utf-8", errors="replace")
                last_exc = RuntimeError(f"http_error:{exc.code}:{text[:300]}")
                if "No input file specified" in text or int(exc.code) == 404:
                    continue
            except URLError as exc:
                last_exc = RuntimeError(f"network_error:{exc}")
        if last_exc is not None:
            raise last_exc
        raise RuntimeError("request_failed:no_candidate_url")

    def pull_task(self) -> Optional[Dict[str, Any]]:
        payload: Dict[str, Any] = {
            "token": self.cfg.token,
            "device_code": self.cfg.device_code,
            "agent_version": VERSION,
        }
        if self.cfg.task_types:
            payload["task_types"] = self.cfg.task_types
        key = "pull"
        urls = [self._working_url[key]] if key in self._working_url else self._candidate_urls(self.cfg.pull_path)
        data, used_url = self._post_json(urls, payload)
        self._working_url[key] = used_url
        if int(data.get("code") or -1) != 0:
            raise RuntimeError(f"pull failed: {data}")
        task = (data.get("data") or {}).get("task")
        if not task:
            reason = (data.get("data") or {}).get("reason") or ""
            if reason:
                self._log("queue_idle", reason=reason)
            return None
        self._log("task_pulled", task_id=task.get("id"), task_type=task.get("task_type"), target_channel=task.get("target_channel"))
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
        extra: Optional[Dict[str, Any]] = None,
    ) -> None:
        payload: Dict[str, Any] = {
            "token": self.cfg.token,
            "device_code": self.cfg.device_code,
            "task_id": int(task_id),
            "event": event,
            "rendered_text": rendered_text,
            "error_code": error_code,
            "error_message": error_message,
            "duration_ms": max(0, int(duration_ms)),
            "screenshot_path": screenshot_path,
            "agent_version": VERSION,
        }
        if extra:
            payload.update(extra)
        key = "report"
        urls = [self._working_url[key]] if key in self._working_url else self._candidate_urls(self.cfg.report_path)
        data, used_url = self._post_json(urls, payload)
        self._working_url[key] = used_url
        if int(data.get("code") or -1) != 0:
            raise RuntimeError(f"report failed: {data}")

    def ensure_context(self) -> BrowserContext:
        if self._ctx is not None:
            return self._ctx
        if sync_playwright is None:
            raise RuntimeError("missing_playwright: 请先安装依赖并执行 python -m playwright install chromium")
        self._pw = sync_playwright().start()
        self._ctx = self._pw.chromium.launch_persistent_context(
            user_data_dir=str(self.cfg.user_data_dir),
            channel=self.cfg.browser_channel,
            headless=self.cfg.headless,
            viewport={"width": 1440, "height": 900},
            args=["--disable-features=TranslateUI", "--start-maximized"],
        )
        return self._ctx

    def close_browser(self) -> None:
        if self._ctx is not None:
            try:
                self._ctx.close()
            except Exception:
                pass
            self._ctx = None
        if self._pw is not None:
            try:
                self._pw.stop()
            except Exception:
                pass
            self._pw = None

    def pick_page(self, url: str) -> Page:
        ctx = self.ensure_context()
        page = ctx.pages[0] if ctx.pages else ctx.new_page()
        page.goto(url, wait_until="domcontentloaded", timeout=45000)
        return page

    def execute_task(self, task: Dict[str, Any]) -> None:
        start = time.time()
        task_id = int(task.get("id") or 0)
        rendered_text = str(task.get("rendered_text") or "").strip()
        payload = task.get("payload") if isinstance(task.get("payload"), dict) else {}
        target_channel = str(task.get("target_channel") or "").strip().lower()
        screenshot = ""
        try:
            if self.cfg.dry_run:
                screenshot = self.capture_screenshot(None, task_id, "dry_run")
                self.report_task(task_id, "sent", rendered_text=rendered_text, duration_ms=int((time.time() - start) * 1000), screenshot_path=screenshot)
                self._log("task_dry_run_sent", task_id=task_id)
                return

            page = self.execute_channel_task(target_channel, payload, rendered_text)
            screenshot = self.capture_screenshot(page, task_id, "sent")
            self.report_task(
                task_id=task_id,
                event="sent",
                rendered_text=rendered_text,
                duration_ms=int((time.time() - start) * 1000),
                screenshot_path=screenshot,
            )
            self._log("task_sent", task_id=task_id, target_channel=target_channel)
        except Exception as exc:
            err = str(exc)
            screenshot = screenshot or self.capture_screenshot(None, task_id, "failed")
            self._log("task_failed", task_id=task_id, error=err)
            try:
                self.report_task(
                    task_id=task_id,
                    event="failed",
                    rendered_text=rendered_text,
                    error_code="desktop_agent_exception",
                    error_message=err[:240],
                    duration_ms=int((time.time() - start) * 1000),
                    screenshot_path=screenshot,
                )
            except Exception as rep_exc:
                self._log("report_failed", task_id=task_id, error=str(rep_exc))

    def execute_channel_task(self, target_channel: str, payload: Dict[str, Any], rendered_text: str) -> Page:
        channels = payload.get("channels") if isinstance(payload.get("channels"), dict) else {}
        if target_channel in {"wa", "whatsapp"}:
            number = norm_number(str(channels.get("whatsapp") or ""))
            if not number:
                raise RuntimeError("missing_whatsapp_number")
            url = f"https://web.whatsapp.com/send?phone={number}"
            if rendered_text:
                url += f"&text={quote_plus(rendered_text)}"
            page = self.pick_page(url)
            self.send_message_on_whatsapp(page, rendered_text)
            return page

        if target_channel == "zalo":
            zalo_id = str(channels.get("zalo") or "").strip()
            if not zalo_id:
                raise RuntimeError("missing_zalo_id")
            # Prefer direct id route, fallback to profile page.
            url = f"https://chat.zalo.me/?id={quote_plus(zalo_id)}"
            page = self.pick_page(url)
            self.send_message_on_zalo(page, rendered_text, zalo_id=zalo_id)
            return page

        raise RuntimeError(f"unsupported_target_channel:{target_channel}")

    def send_message_on_whatsapp(self, page: Page, rendered_text: str) -> None:
        try:
            # Wait for WA web login/chat UI. If not logged in, this timeout gives clear error.
            box = page.locator('footer div[contenteditable="true"][aria-label]').first
            box.wait_for(state="visible", timeout=30000)
            if rendered_text:
                box.click()
                box.fill(rendered_text)
            if self.cfg.send_enter:
                box.press("Enter")
                page.wait_for_timeout(1200)
        except PwTimeoutError as exc:
            raise RuntimeError("whatsapp_input_not_found_or_not_logged_in") from exc

    def send_message_on_zalo(self, page: Page, rendered_text: str, zalo_id: str) -> None:
        if rendered_text == "":
            return
        selectors = [
            'div[contenteditable="true"]',
            'textarea',
            'input[type="text"]',
        ]
        for selector in selectors:
            loc = page.locator(selector).first
            try:
                loc.wait_for(state="visible", timeout=8000)
                loc.click()
                try:
                    loc.fill(rendered_text)
                except Exception:
                    # Some contenteditable nodes on Zalo may not support fill.
                    page.keyboard.type(rendered_text, delay=8)
                if self.cfg.send_enter:
                    page.keyboard.press("Enter")
                    page.wait_for_timeout(1000)
                return
            except Exception:
                continue
        raise RuntimeError(f"zalo_input_not_found_for_id:{zalo_id}")

    def capture_screenshot(self, page: Optional[Page], task_id: int, suffix: str) -> str:
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"task_{task_id}_{suffix}_{ts}.png"
        path = self.cfg.screenshot_dir / filename
        try:
            if page is not None:
                page.screenshot(path=str(path), full_page=True)
            else:
                # empty file marker to keep path trace for reports when page is unavailable
                path.write_text("", encoding="utf-8")
            return str(path).replace("\\", "/")
        except Exception:
            return ""

    def _log(self, msg: str, **kwargs: Any) -> None:
        payload = {"time": now_text(), "msg": msg}
        payload.update(kwargs)
        print(json.dumps(payload, ensure_ascii=False), flush=True)


def main() -> int:
    cfg = AgentConfig.from_env()
    agent = DesktopAgent(cfg)
    agent.run()
    return 0


if __name__ == "__main__":
    sys.exit(main())
