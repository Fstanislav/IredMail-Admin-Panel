#!/usr/bin/env python3
import os
import sys
import socket
import ssl
import time
import json
import threading
import http.client
from typing import Dict, Optional, Tuple

LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO").upper()

def log(level: str, msg: str):
    levels = ["DEBUG", "INFO", "WARN", "ERROR"]
    if levels.index(level) >= levels.index(LOG_LEVEL):
        ts = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime())
        print(f"[{ts}] {level}: {msg}", flush=True)

class TelegramClient:
    def __init__(self, token: str, chat_id: str):
        self.token = token
        self.chat_id = chat_id

    def send_message(self, text: str) -> bool:
        host = "api.telegram.org"
        path = f"/bot{self.token}/sendMessage"
        body = json.dumps({"chat_id": self.chat_id, "text": text})
        headers = {
            "Content-Type": "application/json",
            "Content-Length": str(len(body)),
        }
        conn = http.client.HTTPSConnection(host, timeout=10)
        try:
            conn.request("POST", path, body=body, headers=headers)
            resp = conn.getresponse()
            data = resp.read()
            if resp.status != 200:
                log("ERROR", f"Telegram send failed: {resp.status} {resp.reason} {data}")
                return False
            return True
        except Exception as e:
            log("ERROR", f"Telegram send exception: {e}")
            return False
        finally:
            try:
                conn.close()
            except Exception:
                pass

class AMIClient:
    def __init__(self, host: str, port: int, username: str, secret: str, use_tls: bool=False):
        self.host = host
        self.port = port
        self.username = username
        self.secret = secret
        self.use_tls = use_tls
        self.sock: Optional[socket.socket] = None
        self.file = None
        self.lock = threading.Lock()

    def connect(self):
        s = socket.create_connection((self.host, self.port), timeout=10)
        if self.use_tls:
            context = ssl.create_default_context()
            s = context.wrap_socket(s, server_hostname=self.host)
        self.sock = s
        self.file = s.makefile('rwb', buffering=0)
        banner = self._read_packet()
        log("DEBUG", f"AMI Banner: {banner}")
        self._login()

    def _send_packet(self, lines: Dict[str, str]):
        with self.lock:
            for k, v in lines.items():
                self.file.write(f"{k}: {v}\r\n".encode())
            self.file.write(b"\r\n")

    def _read_packet(self) -> Dict[str, str]:
        headers: Dict[str, str] = {}
        while True:
            line = self.file.readline()
            if not line:
                raise ConnectionError("AMI connection closed")
            line = line.decode(errors='ignore').rstrip('\r\n')
            if line == '':
                break
            if ':' in line:
                k, v = line.split(':', 1)
                headers[k.strip()] = v.strip()
        return headers

    def _login(self):
        self._send_packet({
            "Action": "Login",
            "Username": self.username,
            "Secret": self.secret,
            "Events": "on",
        })
        resp = self._read_packet()
        if resp.get("Response") != "Success":
            raise RuntimeError(f"AMI login failed: {resp}")
        log("INFO", "AMI login successful")

    def loop_packets(self):
        while True:
            pkt = self._read_packet()
            if not pkt:
                continue
            yield pkt

class CallTracker:
    def __init__(self, include_internal: bool, notify_missed: bool):
        self.include_internal = include_internal
        self.notify_missed = notify_missed
        self.calls: Dict[str, Dict[str, str]] = {}
        self.lock = threading.Lock()

    @staticmethod
    def _is_internal(num: str) -> bool:
        return num.isdigit() and 2 <= len(num) <= 6

    def update_from_event(self, ev: Dict[str, str]) -> Optional[Tuple[str, str]]:
        et = ev.get("Event")
        unique_id = ev.get("Uniqueid") or ev.get("UniqueID")
        if not unique_id:
            return None

        with self.lock:
            call = self.calls.setdefault(unique_id, {})

            if et == "Newchannel":
                caller = ev.get("CallerIDNum") or ev.get("CallerID") or ""
                exten = ev.get("Exten") or ""
                call.update({"caller": caller, "exten": exten, "state": "new"})
                return None

            if et == "Newstate":
                state = ev.get("ChannelStateDesc")
                if state:
                    call["state"] = state
                return None

            if et == "Dial":
                sub = ev.get("SubEvent")
                if sub == "Begin":
                    src = ev.get("CallerIDNum") or ev.get("CallerID") or ev.get("SrcCallerIDNum") or ""
                    dst = ev.get("DialString") or ev.get("DestCallerIDNum") or ev.get("DestCallerID") or ev.get("Dest") or ""
                    call.update({"caller": src, "callee": dst, "state": "dialing"})
                    return ("ring", f"📞 Входящий звонок: {src} → {dst}")
                if sub == "End":
                    dialstatus = ev.get("DialStatus")
                    call["dialstatus"] = dialstatus or ""
                    if dialstatus in ("BUSY", "NOANSWER", "CANCEL", "CONGESTION", "CHANUNAVAIL"):
                        if self.notify_missed:
                            src = call.get("caller", "?")
                            dst = call.get("callee", "?")
                            return ("missed", f"❌ Пропущенный: {src} → {dst} ({dialstatus})")
                    return None

            if et == "BridgeEnter":
                caller = ev.get("CallerIDNum") or call.get("caller", "")
                callee = ev.get("ConnectedLineNum") or call.get("callee", "")
                call.update({"caller": caller, "callee": callee, "state": "bridged"})
                return ("answer", f"✅ Ответил: {caller} ↔ {callee}")

            if et in ("Hangup",):
                cause = ev.get("Cause-txt") or ev.get("Cause-txt") or ev.get("Cause") or ""
                dialstatus = call.get("dialstatus", "")
                if self.notify_missed and dialstatus in ("", "NOANSWER") and call.get("state") != "bridged":
                    src = call.get("caller", "?")
                    dst = call.get("callee", call.get("exten", "?"))
                    note = f"❌ Пропущенный: {src} → {dst}"
                    if cause:
                        note += f" ({cause})"
                    return ("missed", note)
                self.calls.pop(unique_id, None)
                return None

            # cleanup on VarSet for unique linked id
            if et == "VarSet" and ev.get("Variable") == "BRIDGEPEER":
                # just update info
                call["bridgepeer"] = ev.get("Value", "")
                return None

            return None


def main():
    token = os.getenv("TELEGRAM_BOT_TOKEN")
    chat_id = os.getenv("TELEGRAM_CHAT_ID")
    if not token or not chat_id:
        log("ERROR", "TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID must be set")
        sys.exit(1)

    ami_host = os.getenv("AMI_HOST", "127.0.0.1")
    ami_port = int(os.getenv("AMI_PORT", "5038"))
    ami_username = os.getenv("AMI_USERNAME")
    ami_secret = os.getenv("AMI_SECRET")
    use_tls = os.getenv("AMI_TLS", "false").lower() == "true"

    if not ami_username or not ami_secret:
        log("ERROR", "AMI_USERNAME and AMI_SECRET must be set")
        sys.exit(1)

    include_internal = os.getenv("CALLFLOW_INCLUDE_INTERNAL", "false").lower() == "true"
    notify_missed = os.getenv("NOTIFY_MISSED", "true").lower() != "false"

    tg = TelegramClient(token, chat_id)
    tracker = CallTracker(include_internal=include_internal, notify_missed=notify_missed)

    while True:
        try:
            ami = AMIClient(ami_host, ami_port, ami_username, ami_secret, use_tls)
            ami.connect()
            for pkt in ami.loop_packets():
                if pkt.get("Event"):
                    ev_type = pkt.get("Event")
                    # Filter purely internal calls if requested
                    if not include_internal:
                        src = pkt.get("CallerIDNum") or pkt.get("CallerID") or ""
                        dst = pkt.get("DialString") or pkt.get("ConnectedLineNum") or pkt.get("Exten") or ""
                        if src and dst and CallTracker._is_internal(src) and CallTracker._is_internal(dst):
                            continue
                    res = tracker.update_from_event(pkt)
                    if res:
                        kind, text = res
                        log("INFO", f"Notify {kind}: {text}")
                        tg.send_message(text)
        except Exception as e:
            log("ERROR", f"Loop error: {e}")
            time.sleep(5)

if __name__ == "__main__":
    main()
