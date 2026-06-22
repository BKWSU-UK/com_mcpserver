"""HTTP JSON-RPC client for the Joomla MCP Server component."""

from __future__ import annotations

import json
import os
import ssl
import subprocess
import threading
import uuid
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


class JoomlaMcpError(RuntimeError):
    pass


class JoomlaHttpClient:
    """Call the Joomla MCP JSON-RPC endpoint over HTTP."""

    def __init__(self, endpoint: str, bearer_token: str = "", timeout: float = 30.0):
        self.endpoint = endpoint
        self.bearer_token = bearer_token
        self.timeout = timeout
        self._request_id = 0

    def _next_id(self) -> int:
        self._request_id += 1
        return self._request_id

    def call(self, method: str, params: dict[str, Any] | None = None) -> Any:
        payload = {
            "jsonrpc": "2.0",
            "id": self._next_id(),
            "method": method,
            "params": params or {},
        }
        headers = {"Content-Type": "application/json"}
        if self.bearer_token:
            headers["Authorization"] = f"Bearer {self.bearer_token}"

        request = Request(
            self.endpoint,
            data=json.dumps(payload).encode("utf-8"),
            headers=headers,
            method="POST",
        )

        try:
            context = None
            if os.environ.get("MCP_IGNORE_SSL") == "1":
                context = ssl._create_unverified_context()
            with urlopen(request, timeout=self.timeout, context=context) as response:
                body = response.read().decode("utf-8")
        except HTTPError as exc:
            detail = exc.read().decode("utf-8", errors="replace")
            raise JoomlaMcpError(f"HTTP {exc.code}: {detail[:500]}") from exc
        except URLError as exc:
            raise JoomlaMcpError(str(exc)) from exc

        if not body.strip():
            return None

        data = json.loads(body)
        if "error" in data:
            error = data["error"]
            message = error.get("message", "Unknown JSON-RPC error")
            raise JoomlaMcpError(message)

        return data.get("result")

    def initialize(self) -> dict[str, Any]:
        return self.call("initialize", {"protocolVersion": "2025-06-18", "capabilities": {}})

    def list_tools(self) -> list[dict[str, Any]]:
        result = self.call("tools/list", {})
        return result.get("tools", []) if isinstance(result, dict) else []

    def call_tool(self, name: str, arguments: dict[str, Any] | None = None) -> Any:
        result = self.call("tools/call", {"name": name, "arguments": arguments or {}})
        if not isinstance(result, dict):
            return result

        if result.get("isError"):
            content = result.get("content", [])
            if content and isinstance(content[0], dict):
                raise JoomlaMcpError(content[0].get("text", "Tool error"))
            raise JoomlaMcpError("Tool error")

        if "structuredContent" in result:
            return result["structuredContent"]

        content = result.get("content", [])
        if content and isinstance(content[0], dict) and content[0].get("type") == "text":
            text = content[0].get("text", "")
            try:
                return json.loads(text)
            except json.JSONDecodeError:
                return text

        return result


class JoomlaBridgeClient(JoomlaHttpClient):
    """Speak JSON-RPC over the Node stdio bridge process."""

    def __init__(self, bridge_script: str, endpoint: str, bearer_token: str = "", timeout: float = 30.0):
        super().__init__(endpoint, bearer_token, timeout)
        self.bridge_script = bridge_script
        self._process: subprocess.Popen[str] | None = None
        self._lock = threading.Lock()
        self._pending: dict[str, Any] = {}

    def start(self) -> None:
        args = ["node", self.bridge_script, self.endpoint]
        if self.bearer_token:
            args.append(self.bearer_token)
        self._process = subprocess.Popen(
            args,
            stdin=subprocess.PIPE,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            bufsize=1,
        )
        reader = threading.Thread(target=self._read_stdout, daemon=True)
        reader.start()

    def stop(self) -> None:
        if self._process and self._process.poll() is None:
            self._process.terminate()
            self._process.wait(timeout=5)
        self._process = None

    def _read_stdout(self) -> None:
        assert self._process and self._process.stdout
        for line in self._process.stdout:
            line = line.strip()
            if not line:
                continue
            try:
                message = json.loads(line)
            except json.JSONDecodeError:
                continue
            request_id = message.get("id")
            with self._lock:
                if request_id in self._pending:
                    self._pending[request_id] = message

    def call(self, method: str, params: dict[str, Any] | None = None) -> Any:
        if not self._process or not self._process.stdin:
            raise JoomlaMcpError("Bridge process is not running")

        request_id = str(uuid.uuid4())
        payload = {
            "jsonrpc": "2.0",
            "id": request_id,
            "method": method,
            "params": params or {},
        }

        with self._lock:
            self._pending[request_id] = None

        self._process.stdin.write(json.dumps(payload) + "\n")
        self._process.stdin.flush()

        import time

        deadline = time.time() + self.timeout
        while time.time() < deadline:
            with self._lock:
                message = self._pending.get(request_id)
            if message is not None:
                with self._lock:
                    self._pending.pop(request_id, None)
                if "error" in message:
                    raise JoomlaMcpError(message["error"].get("message", "Bridge error"))
                return message.get("result")
            time.sleep(0.05)

        raise JoomlaMcpError(f"Timed out waiting for bridge response to {method}")


def anthropic_tools(tools: list[dict[str, Any]]) -> list[dict[str, Any]]:
    return [
        {
            "name": tool["name"],
            "description": tool.get("description", ""),
            "input_schema": tool.get("inputSchema") or tool.get("input_schema") or {"type": "object", "properties": {}},
        }
        for tool in tools
    ]
