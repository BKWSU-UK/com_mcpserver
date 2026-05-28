# MCP Server for Joomla

A Joomla 4, 5 and 6 component that exposes a [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server over HTTP JSON-RPC. It lets MCP clients such as Claude Desktop and Cursor work with Joomla content through the site's own Joomla Web Services API.

**Version:** 0.6.0 · **Requires:** Joomla 4, 5 or 6 · PHP 8.1+ · **Licence:** GPL-2.0-or-later

## Features

- HTTP JSON-RPC 2.0 MCP endpoint in both site and administrator contexts
- Bearer token authentication with optional IP allow-listing and CORS origin control
- Configurable fixed-window rate limiting
- Response caching through Joomla's cache layer
- JSON Schema validation for MCP tool inputs
- Health endpoint for monitoring
- Node.js stdio-to-HTTP bridge for desktop MCP clients
- Joomla update server metadata for official releases

## MCP Tools

The component exposes tools for Joomla articles, article versions, custom modules, modules, menus, menu items, media, content languages, installed languages, template styles, installed templates and multilingual associations.

Write tools use Joomla's Web Services API where possible. A small number of Joomla behaviours that are not exposed cleanly through Web Services, such as custom module HTML writes and multilingual associations, are handled through Joomla's database APIs.

## Installation

Download the latest `com_mcpserver-<version>.zip` package from the GitHub releases page, then install it in Joomla Administrator via **System → Install → Extensions**.

For a local development build:

```bash
./build.sh
```

The build creates `com_mcpserver-<version>.zip` at the repository root. The version is read from `mcpserver.xml`.

## Configuration

Open **Administrator → Components → MCP Server → Options**.

Key settings:

- `Server Name`: identifier returned in MCP server information.
- `Base URL`: base URL of the Joomla site. Leave empty to use the current site.
- `API Token`: Joomla Web Services API token used for outbound REST calls.
- `Verify SSL`: verifies SSL certificates for outbound requests.
- `Default Language`: default language tag for content requests.
- `Cache TTL`: response cache lifetime in seconds.
- `Require Auth`: requires MCP clients to send a bearer token.
- `MCP Bearer Token`: token clients must send in `Authorization: Bearer`.
- `IP Allow List`: comma-separated client IP allow list.
- `Allowed Origins`: comma-separated CORS origin allow list.
- `Trusted Proxies`: comma-separated proxy IPs trusted for `X-Forwarded-For`.
- `Rate Limit Requests` and `Rate Limit Window`: fixed-window rate limit settings.

## Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/index.php?option=com_mcpserver&task=rpc.handle` | MCP JSON-RPC endpoint in the site application |
| `GET` | `/index.php?option=com_mcpserver&task=rpc.sse` | Server-Sent Events stream used by the stdio bridge |
| `GET` | `/index.php?option=com_mcpserver&task=health.ping` | Site health endpoint |
| `POST` | `/administrator/index.php?option=com_mcpserver&task=rpc.handle` | MCP JSON-RPC endpoint in the administrator application |
| `GET` | `/administrator/index.php?option=com_mcpserver&task=health.ping` | Administrator health endpoint |

## Desktop Client Bridge

For MCP clients that use stdio transport, run the included Node.js bridge. After installation it is located at `components/com_mcpserver/mcp-http-bridge.js` in your Joomla site root. When working from a repository checkout or extracted release zip, use `site/mcp-http-bridge.js` instead.

```bash
node components/com_mcpserver/mcp-http-bridge.js <endpoint-url> [bearer-token]
```

Example:

```bash
node components/com_mcpserver/mcp-http-bridge.js "https://example.com/index.php?option=com_mcpserver&task=rpc.handle" "$MCP_BEARER_TOKEN"
```

The bearer token can also be supplied through `HTTP_AUTH_BEARER`. Set `MCP_IGNORE_SSL=1` only for local development with self-signed certificates.

## Release Build

```bash
composer validate --working-dir=admin --no-check-publish
./build.sh
```

Before submitting a package to the Joomla Extensions Directory, install the generated zip on a clean Joomla site and run the official JED Checker against it.

## Licence

MCP Server for Joomla is free software released under `GPL-2.0-or-later`.
