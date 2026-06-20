# MCP Server for Joomla

A Joomla 4, 5 and 6 component that exposes a [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server over HTTP JSON-RPC. It lets MCP clients such as Claude Desktop and Cursor work with Joomla content through the site's own Joomla Web Services API.

**Version:** 1.1.0 · **Requires:** Joomla 4, 5 or 6 · PHP 8.1+ · **Licence:** GPL-2.0-or-later

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

This is the list of tools the MCP server exposes grouped by the Joomla domain.

### Articles

| Tool | Description |
|---|---|
| `get_article_by_id` | Retrieve a Joomla article by ID |
| `search_articles` | Search Joomla articles |
| `create_article` | Create a new Joomla article |
| `update_article` | Update an existing Joomla article |
| `delete_article` | Delete a Joomla article |

### Article versions

| Tool | Description |
|---|---|
| `list_article_versions` | List saved versions (content history) for a Joomla article |
| `get_article_version` | Retrieve a single article version from content history |
| `keep_article_version` | Toggle the "keep forever" flag on an article version |
| `delete_article_version` | Delete a single article version from content history |
| `restore_article_version` | Restore a Joomla article to a previous saved version |

Article versioning tools require Joomla article versioning to be enabled.

### Custom modules

| Tool | Description |
|---|---|
| `create_custom_module` | Create a new Joomla "Custom" (`mod_custom`) module |
| `list_custom_modules` | List all Joomla "Custom" (`mod_custom`) modules |
| `get_custom_module_by_id` | Retrieve a Joomla "Custom" module by ID |
| `update_custom_module` | Update the content of a Joomla "Custom" module |

### Modules

| Tool | Description |
|---|---|
| `list_modules` | List all Joomla modules |
| `get_module_by_id` | Retrieve a Joomla module by ID |
| `update_module` | Update any Joomla module (all types); merges type-specific params |

### Menus and menu items

| Tool | Description |
|---|---|
| `list_menus` | List all Joomla menus (menu types) |
| `list_menu_items` | List menu items, optionally filtered by menu type |
| `get_menu_item` | Retrieve a Joomla menu item by ID |
| `create_menu_item` | Create a new Joomla menu item |
| `update_menu_item` | Update an existing Joomla menu item |

### Media

| Tool | Description |
|---|---|
| `list_media` | List Joomla media files and folders |
| `get_media` | Retrieve a single Joomla media file or folder by path |
| `upload_media` | Upload a new Joomla media file |
| `create_media_folder` | Create a new folder in the Joomla media library |
| `update_media` | Rename, move or replace an existing media file or folder |
| `delete_media` | Delete a Joomla media file or folder by path |

### Content languages

| Tool | Description |
|---|---|
| `list_content_languages` | List Joomla content languages (tags assignable to articles, menu items, etc.) |
| `get_content_language` | Retrieve a Joomla content language by ID |
| `create_content_language` | Create a new Joomla content language |
| `update_content_language` | Update an existing Joomla content language |
| `delete_content_language` | Delete a Joomla content language by ID |

### Installed languages

| Tool | Description |
|---|---|
| `list_installed_languages` | List languages installed on the Joomla site (site and administrator clients) |

### Template styles

| Tool | Description |
|---|---|
| `list_template_styles` | List Joomla template styles for the chosen client |
| `get_template_style` | Retrieve a Joomla template style by ID |
| `create_template_style` | Create a new template style for an already-installed template |
| `update_template_style` | Update an existing Joomla template style |
| `delete_template_style` | Delete a Joomla template style |

### Installed templates

| Tool | Description |
|---|---|
| `list_installed_templates` | List templates installed on the Joomla site (site and administrator clients) |
| `install_extension` | Install a Joomla extension from a base64 zip or a download URL (arbitrary code execution — restrict to trusted callers) |

### Multilingual associations

| Tool | Description |
|---|---|
| `list_article_associations` | List cross-language associations for a Joomla article |
| `set_article_associations` | Set cross-language associations for a Joomla article |
| `list_menu_item_associations` | List cross-language associations for a Joomla site menu item |
| `set_menu_item_associations` | Set cross-language associations for a Joomla site menu item |

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

### MCP client configuration

For your agent e.g. Codex, Cursor, Claude, Hermes, OpenClaw, etc., add a remote MCP proxy to your MCP client configuration file:
```json
{
  "mcpServers": {
    "joomla": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://example.com/index.php?option=com_mcpserver&task=rpc.handle",
        "--header",
        "Authorization:${AUTH_HEADER}"
      ],
      "env": {
        "AUTH_HEADER": "Bearer your-mcp-bearer-token"
      }
    }
  }
}
```

The bearer token can also be supplied through `HTTP_AUTH_BEARER`. Set `MCP_IGNORE_SSL=1` only for local development with self-signed certificates.

## Release Build

```bash
composer validate --working-dir=admin --no-check-publish
./build.sh
```

## Licence

MCP Server for Joomla is free software released under `GPL-2.0-or-later`.
