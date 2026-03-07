#!/usr/bin/env python3
import json, os, sys, urllib.parse, urllib.request, http.cookiejar

BASE_URL = os.environ.get("IMPRESSCMS_BASE_URL", "http://compose.test").rstrip("/")
USERNAME = os.environ.get("IMPRESSCMS_USERNAME", "admin")
PASSWORD = os.environ.get("IMPRESSCMS_PASSWORD", "")
ENDPOINT = f"{BASE_URL}/modules/mcpapi/mcp.php"

TOOLS = [
    {"name":"list_modules","description":"List accessible ImpressCMS modules and known handlers.","inputSchema":{"type":"object","properties":{"activeOnly":{"type":"boolean","description":"Only return active modules."}}}},
    {"name":"describe_handler","description":"Describe a module handler and its fields.","inputSchema":{"type":"object","properties":{"module":{"type":"string"},"handler":{"type":"string"}},"required":["module","handler"]}},
    {"name":"list_objects","description":"List objects from a module handler.","inputSchema":{"type":"object","properties":{"module":{"type":"string"},"handler":{"type":"string"},"fields":{"type":"array","items":{"type":"string"}},"filters":{"type":"object"},"start":{"type":"integer"},"limit":{"type":"integer"},"sort":{"type":"string"},"order":{"type":"string","enum":["ASC","DESC"]}},"required":["module","handler"]}},
    {"name":"get_object","description":"Fetch one object by id.","inputSchema":{"type":"object","properties":{"module":{"type":"string"},"handler":{"type":"string"},"id":{} ,"fields":{"type":"array","items":{"type":"string"}}},"required":["module","handler","id"]}},
    {"name":"create_object","description":"Create an object. This changes data.","inputSchema":{"type":"object","properties":{"module":{"type":"string"},"handler":{"type":"string"},"data":{"type":"object"},"fields":{"type":"array","items":{"type":"string"}}},"required":["module","handler","data"]}},
    {"name":"update_object","description":"Update an object. This changes data.","inputSchema":{"type":"object","properties":{"module":{"type":"string"},"handler":{"type":"string"},"id":{},"data":{"type":"object"},"fields":{"type":"array","items":{"type":"string"}}},"required":["module","handler","id","data"]}},
    {"name":"delete_object","description":"Delete an object. This changes data.","inputSchema":{"type":"object","properties":{"module":{"type":"string"},"handler":{"type":"string"},"id":{}},"required":["module","handler","id"]}},
]

TOOL_MAP = {
    "list_modules": "listModules",
    "describe_handler": "describeHandler",
    "list_objects": "listObjects",
    "get_object": "getObject",
    "create_object": "createObject",
    "update_object": "updateObject",
    "delete_object": "deleteObject",
}


class ImpressCMSClient:
    def __init__(self):
        jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
        self.logged_in = False

    def login(self):
        if self.logged_in:
            return
        if not PASSWORD:
            raise RuntimeError("Set IMPRESSCMS_PASSWORD before starting this MCP server.")
        self.opener.open(f"{BASE_URL}/user.php", timeout=30).read()
        form = urllib.parse.urlencode({"uname": USERNAME, "pass": PASSWORD, "op": "login", "xoops_redirect": "/"}).encode()
        self.opener.open(urllib.request.Request(f"{BASE_URL}/user.php", data=form), timeout=30).read()
        self.logged_in = True

    def call(self, method, params):
        self.login()
        body = json.dumps({"jsonrpc":"2.0","id":1,"method":method,"params":params}).encode()
        req = urllib.request.Request(ENDPOINT, data=body, headers={"Content-Type":"application/json","Accept":"application/json"})
        text = self.opener.open(req, timeout=60).read().decode("utf-8", "replace")
        if text.lstrip().startswith("<"):
            raise RuntimeError("Expected JSON from ImpressCMS but received HTML. Check login and module permissions.")
        payload = json.loads(text)
        if payload.get("error"):
            raise RuntimeError(payload["error"].get("message", "Unknown ImpressCMS error."))
        return payload.get("result")


def write_message(message):
    data = json.dumps(message, separators=(",", ":")).encode("utf-8")
    sys.stdout.buffer.write(data + b"\n")
    sys.stdout.buffer.flush()


def read_message():
    while True:
        line = sys.stdin.buffer.readline()
        if not line:
            return None
        text = line.decode("utf-8").strip()
        if not text:
            continue
        return json.loads(text)


def error_response(req_id, code, message):
    return {"jsonrpc":"2.0","id":req_id,"error":{"code":code,"message":message}}


def tool_result(data, is_error=False):
    text = data if isinstance(data, str) else json.dumps(data, indent=2, ensure_ascii=False)
    result = {"content":[{"type":"text","text":text}],"isError":is_error}
    if not isinstance(data, str):
        result["structuredContent"] = data
    return result


def handle_request(client, msg):
    method, req_id = msg.get("method"), msg.get("id")
    params = msg.get("params") or {}
    if method == "initialize":
        version = params.get("protocolVersion", "2025-06-18")
        return {"jsonrpc":"2.0","id":req_id,"result":{"protocolVersion":version,"capabilities":{"tools":{"listChanged":False}},"serverInfo":{"name":"impresscms-mcp","version":"0.1.0"}}}
    if method == "notifications/initialized":
        return None
    if method == "ping":
        return {"jsonrpc":"2.0","id":req_id,"result":{}}
    if method == "tools/list":
        return {"jsonrpc":"2.0","id":req_id,"result":{"tools":TOOLS}}
    if method == "tools/call":
        name, arguments = params.get("name"), (params.get("arguments") or {})
        if name not in TOOL_MAP:
            return {"jsonrpc":"2.0","id":req_id,"result":tool_result(f"Unknown tool: {name}", True)}
        try:
            data = client.call(TOOL_MAP[name], arguments)
            return {"jsonrpc":"2.0","id":req_id,"result":tool_result(data)}
        except Exception as exc:
            return {"jsonrpc":"2.0","id":req_id,"result":tool_result(str(exc), True)}
    return error_response(req_id, -32601, f"Method not found: {method}")


def main():
    client = ImpressCMSClient()
    while True:
        message = read_message()
        if message is None:
            return
        try:
            response = handle_request(client, message)
        except Exception as exc:
            response = error_response(message.get("id"), -32000, str(exc))
        if response is not None:
            write_message(response)


if __name__ == "__main__":
    main()
