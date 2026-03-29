# com_mcpserver

A Joomla 5 component that exposes a [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server via JSON-RPC over HTTP. It allows AI clients (e.g. Claude Desktop, Cursor) to interact with your Joomla site's content and APIs.

**Version:** 0.4.0 · **Requires:** Joomla 5.x · PHP 8.1+

---

## Features

- JSON-RPC 2.0 endpoint accessible on both the site and administrator contexts
- Bearer token authentication with optional IP allow-listing and CORS origin control
- Configurable rate limiting (requests per time window)
- Response caching via Joomla's cache layer
- JSON Schema validation for tool inputs
- Health / liveness endpoint for monitoring
- Node.js stdio→HTTP bridge (`mcp-http-bridge.js`) for desktop MCP clients

---

## Installation

### From a zip package

1. Run `./build.sh` to produce the installable zip under `build/`.
2. In Joomla Administrator → **System → Install → Extensions**, upload the zip.

### From source (development)

1. Clone this repository into your Joomla installation's `components/com_mcpserver` directory (or symlink it).
2. Install Composer dependencies:

```bash
cd admin
composer install --no-dev
```

3. Install the component via the Joomla installer using the manifest at the root, or copy the files manually and register the component in the database.

---

## Configuration

Navigate to **Administrator → Components → MCP Server → Options**.

### Basic

| Setting | Default | Description |
|---|---|---|
| Server Name | `joomla-mcp-server` | Identifier returned in MCP server info |
| Base URL | *(empty)* | Base URL of the Joomla REST API |
| API Token | *(empty)* | Bearer token for outbound REST API calls |
| Verify SSL | Yes | Verify SSL certificates on outbound requests |
| Default Language | `*` | Language tag for content requests |
| Cache TTL | `60` | Response cache lifetime in seconds |
| WebSocket Host | `0.0.0.0` | Host for the WebSocket listener |
| WebSocket Port | `9077` | Port for the WebSocket listener |

### Security

| Setting | Default | Description |
|---|---|---|
| Require Auth | Yes | Enforce bearer token on inbound MCP requests |
| MCP Bearer Token | *(empty)* | Token that clients must supply in `Authorization: Bearer` |
| IP Allow List | *(empty)* | Newline-separated list of allowed client IPs (empty = all) |
| Allowed Origins | *(empty)* | Newline-separated CORS origins (empty = all) |
| Trusted Proxies | *(empty)* | Newline-separated proxy IPs for `X-Forwarded-For` trust |
| Rate Limit Requests | `60` | Maximum requests allowed per window |
| Rate Limit Window | `60` | Window duration in seconds |

---

## Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/index.php?option=com_mcpserver&task=rpc.execute` | JSON-RPC 2.0 MCP endpoint (site) |
| `GET` | `/index.php?option=com_mcpserver&task=health.check` | Health check (site) |
| `POST` | `/administrator/index.php?option=com_mcpserver&task=rpc.execute` | JSON-RPC 2.0 MCP endpoint (admin) |
| `GET` | `/administrator/index.php?option=com_mcpserver&task=health.check` | Health check (admin) |

---

## Desktop Client Integration (stdio bridge)

For clients that communicate over stdio (e.g. Claude Desktop), use the included Node.js bridge:

```bash
node site/mcp-http-bridge.js
```

Configure your MCP client to spawn this process. The bridge forwards stdio JSON-RPC messages to the HTTP endpoint and streams responses back.

---

## Dependencies

Managed via Composer in `admin/`:

| Package | Purpose |
|---|---|
| `guzzlehttp/guzzle` | Outbound HTTP client |
| `monolog/monolog` | Logging |
| `justinrainbow/json-schema` | JSON Schema validation |

---

## Licence

GPL-2.0-or-later
