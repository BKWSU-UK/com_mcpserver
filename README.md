# com_mcpserver

A Joomla 4, 5 and 6 component that exposes a [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server via JSON-RPC over HTTP. It allows AI clients (e.g. Claude Desktop, Cursor) to interact with your Joomla site's content and APIs.

**Version:** 0.6.0 · **Requires:** Joomla 4, 5 or 6 · PHP 8.1+

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

## Exposed Tools

| Tool | Description |
|---|---|
| `get_article_by_id` | Retrieve a Joomla article by ID |
| `search_articles` | Search Joomla articles |
| `create_article` | Create a new Joomla article |
| `update_article` | Update an existing Joomla article |
| `delete_article` | Delete a Joomla article |
| `list_article_versions` | List saved versions (content history) for an article |
| `get_article_version` | Retrieve a single article version, including the `version_data` snapshot |
| `keep_article_version` | Toggle the "keep forever" flag on an article version |
| `delete_article_version` | Delete a single article version from content history |
| `restore_article_version` | Restore an article to a previous saved version |
| `create_custom_module` | Create a new Joomla Custom (`mod_custom`) module |
| `list_custom_modules` | List Joomla Custom (`mod_custom`) modules |
| `get_custom_module_by_id` | Retrieve a Joomla Custom (`mod_custom`) module by ID |
| `update_custom_module` | Update the content of a Joomla Custom (`mod_custom`) module |
| `list_modules` | List Joomla modules |
| `get_module_by_id` | Retrieve a Joomla module by ID |
| `list_menus` | List Joomla menus |
| `list_menu_items` | List Joomla menu items, optionally filtered by menu type |
| `get_menu_item` | Retrieve a Joomla menu item by ID |
| `create_menu_item` | Create a new Joomla menu item |
| `update_menu_item` | Update an existing Joomla menu item |
| `list_media` | List Joomla media files and folders |
| `get_media` | Retrieve a single Joomla media file or folder |
| `upload_media` | Upload a new Joomla media file |
| `create_media_folder` | Create a new Joomla media folder |
| `update_media` | Rename, move or replace the contents of an existing media file or folder |
| `delete_media` | Delete a Joomla media file or folder |
| `list_content_languages` | List Joomla content languages |
| `get_content_language` | Retrieve a Joomla content language by ID |
| `create_content_language` | Create a new Joomla content language |
| `update_content_language` | Update an existing Joomla content language |
| `delete_content_language` | Delete a Joomla content language |
| `list_installed_languages` | List languages installed on the site (site + administrator) |
| `list_template_styles` | List Joomla template styles for a given client |
| `get_template_style` | Retrieve a Joomla template style by ID |
| `create_template_style` | Create a new Joomla template style |
| `update_template_style` | Update an existing Joomla template style |
| `delete_template_style` | Delete a Joomla template style |
| `list_installed_templates` | List templates installed on the site (site + administrator) |
| `list_article_associations` | List the cross-language associations for an article |
| `set_article_associations` | Set the cross-language associations for an article |
| `list_menu_item_associations` | List the cross-language associations for a site menu item |
| `set_menu_item_associations` | Set the cross-language associations for a site menu item |

---

## Installation

### From a zip package

1. Run `./build.sh` to produce `com_mcpserver-<version>.zip` at the repo root (version is read from `mcpserver.xml`).
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
| `POST` | `/index.php?option=com_mcpserver&task=rpc.handle` | JSON-RPC 2.0 MCP endpoint (site) |
| `GET` | `/index.php?option=com_mcpserver&task=rpc.sse` | Server-Sent Events stream for queued responses (site, used by the stdio bridge) |
| `GET` | `/index.php?option=com_mcpserver&task=health.ping` | Health / liveness probe (site) |
| `POST` | `/administrator/index.php?option=com_mcpserver&task=rpc.handle` | JSON-RPC 2.0 MCP endpoint (admin) |
| `GET` | `/administrator/index.php?option=com_mcpserver&task=health.ping` | Health / liveness probe (admin) |

---

## Desktop Client Integration (stdio bridge)

For clients that communicate over stdio (e.g. Claude Desktop), use the included Node.js bridge:

```bash
node site/mcp-http-bridge.js <endpoint-url> [bearer-token]
```

Example:

```bash
node site/mcp-http-bridge.js https://example.com/index.php?option=com_mcpserver&task=rpc.handle "$MCP_BEARER_TOKEN"
```

The bearer token can also be supplied via the `HTTP_AUTH_BEARER` environment variable. Set `MCP_IGNORE_SSL=1` to disable certificate verification (development only). Configure your MCP client to spawn this process; it forwards stdio JSON-RPC messages to the HTTP endpoint and streams responses back.

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
