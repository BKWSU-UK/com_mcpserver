# MCP Server for Joomla

A Joomla 4, 5 and 6 component that exposes a [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server over HTTP JSON-RPC. It lets MCP clients such as Claude Desktop and Cursor work with Joomla content through the site's own Joomla Web Services API.

**Version:** 1.4.0 · **Requires:** Joomla 4, 5 or 6 · PHP 8.1+ · **Licence:** GPL-2.0-or-later

## Features

- Administrator dashboard with request summary (totals, error rate and auth failures), a requests-per-day chart, top tools and methods, and a requests log
- Security with bearer token authentication, optional IP allow-listing and CORS origin control
- Configurable fixed-window rate limiting
- Response caching through Joomla's cache layer
- JSON Schema validation for MCP tool inputs
- Health endpoint for monitoring
- Joomla update server metadata for official releases

## MCP Tools

The component exposes 66 tools grouped by Joomla domain. List tools include a `pagination` object (`total_count`, `count`, `offset`, `has_more`, `next_offset`) so agents can page through large result sets. Write tools use Joomla's Web Services API where possible; a small number of behaviours not exposed cleanly through Web Services (custom module HTML writes, multilingual associations, template file editing) are handled through Joomla's database or filesystem APIs.

### Articles

| Tool | Description |
|---|---|
| `get_article_by_id` | Retrieve a Joomla article by ID |
| `search_articles` | Search Joomla articles |
| `create_article` | Create a new Joomla article |
| `update_article` | Update an existing Joomla article |
| `delete_article` | Delete a Joomla article (trashes it first when needed, then deletes permanently) |

### Categories

| Tool | Description |
|---|---|
| `list_categories` | List Joomla content categories (use to discover valid `catid` values) |
| `get_category` | Retrieve a Joomla content category by ID |
| `create_category` | Create a new Joomla content category |
| `update_category` | Update an existing Joomla content category |
| `delete_category` | Delete a Joomla content category (trashes first, then deletes; the category must be empty) |

### Tags

| Tool | Description |
|---|---|
| `list_tags` | List Joomla tags |
| `get_tag` | Retrieve a Joomla tag by ID |
| `create_tag` | Create a new Joomla tag |
| `update_tag` | Update an existing Joomla tag |
| `delete_tag` | Delete a Joomla tag (trashes first, then deletes) |

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
| `create_module` | Create a new module of any installed type (type-specific settings via `params`) |
| `update_module` | Update any Joomla module (all types); merges type-specific params |
| `delete_module` | Delete a Joomla module and its page assignments |

### Menus and menu items

| Tool | Description |
|---|---|
| `list_menus` | List all Joomla menus (menu types) |
| `create_menu` | Create a new Joomla menu (menu type) |
| `list_menu_items` | List menu items, optionally filtered by menu type |
| `get_menu_item` | Retrieve a Joomla menu item by ID |
| `create_menu_item` | Create a new Joomla menu item |
| `update_menu_item` | Update an existing Joomla menu item |
| `delete_menu_item` | Delete a Joomla menu item (trashes first, then deletes) |

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

### Template files

| Tool | Description |
|---|---|
| `list_template_files` | List editable source files of an installed template (Joomla's "Customise" view) |
| `get_template_file` | Read the source of a single template file |
| `update_template_file` | Overwrite the source of an existing template file |
| `create_template_override` | Create a template override by copying a core view, module, plugin or layout into the template |

### Extensions

| Tool | Description |
|---|---|
| `list_extensions` | List installed extensions (components, modules, plugins, templates, languages, …) |
| `set_extension_state` | Enable or disable an installed extension (e.g. activate a plugin after installing it) |
| `install_extension` | Install a Joomla extension from a base64 zip or a download URL (arbitrary code execution — restrict to trusted callers) |
| `uninstall_extension` | Uninstall an extension by `extension_id` (protected/locked core extensions are refused) |

`install_extension`, `uninstall_extension` and `update_template_file` are **disabled by default** because they allow code execution on the server. Remove them from the Disabled Tools list in the component options to opt in.

### Multilingual associations

| Tool | Description |
|---|---|
| `list_article_associations` | List cross-language associations for a Joomla article |
| `set_article_associations` | Set cross-language associations for a Joomla article |
| `list_menu_item_associations` | List cross-language associations for a Joomla site menu item |
| `set_menu_item_associations` | Set cross-language associations for a Joomla site menu item |

### Not covered (by design)

User management, Joomla global configuration, custom fields (com_fields), contacts, banners and redirects are deliberately not exposed as tools. User accounts and global configuration in particular would widen the blast radius of a leaked bearer token well beyond content management. If your workflow needs one of these domains, open an issue — they are candidates for opt-in tools in a future release.

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
- `Resolve Host To IP`: optional. Pins the Base URL hostname to a specific IP (e.g. `127.0.0.1`) for the component's outbound REST calls only. Use when the server cannot reach its own public hostname (NAT hairpinning); the Host header and TLS validation still use the real hostname, so `Verify SSL` can stay on.
- `Cache TTL`: response cache lifetime in seconds.
- `Require Auth`: requires MCP clients to send a bearer token.
- `MCP Bearer Token`: token clients must send in `Authorization: Bearer`.
- `IP Allow List`: comma-separated client IP allow list.
- `Allowed Origins`: comma-separated CORS origin allow list.
- `Trusted Proxies`: comma-separated proxy IPs trusted for `X-Forwarded-For`.
- `Read-Only Mode`: when enabled, only read-only tools may run; every tool that writes, deletes or installs anything is blocked.
- `Disabled Tools`: comma- or newline-separated MCP tool names to block (e.g. `delete_article`). Defaults to the code-execution tools (`install_extension`, `uninstall_extension`, `update_template_file`); remove them to opt in, or enter `none` to allow all tools (an emptied field reverts to the defaults when saved).
- `Rate Limit Requests` and `Rate Limit Window`: fixed-window rate limit settings.

### Configuring the API Token

The `API Token` setting holds a Joomla Web Services API token. The component uses it to make outbound REST calls to your site's own Joomla Web Services API, which is how most MCP tools read and write content. Without a valid token, those tools will fail.

1. **Enable the Web Services API.** In the Joomla administrator, go to **System → Global Configuration → Server** and ensure the Web Services components are available. The relevant plugins live under **System → Plugins**; enable **Web Services - Content** (and any other `Web Services -` plugins for the data you want to access). The API plugin **System - Joomla API Authentication** must also be enabled — it is by default.

2. **Create a token for a user.** Tokens are tied to a Joomla user account, and API calls run with that user's permissions, so use an account that has the access the MCP tools need (for full functionality, a Super User or an account with the equivalent component permissions).
   - Go to **Users → Manage**, edit the chosen user, and open the **Joomla API Token** tab.
   - Set **Token Enabled** to *Yes*, click **Save**, then copy the generated token. (If the tab is missing, enable the **User - Joomla API Token** plugin under **System → Plugins**.)

3. **Paste the token into the component options.** Back in **Components → MCP Server → Options**, paste the value into **API Token** and click **Save**.

To verify the token works, call the health endpoint or issue any MCP tool that reads content; an authentication error in the response usually means the token is missing, disabled, or belongs to a user without sufficient permissions.

> **Security note:** treat the API token like a password. It grants the token user's level of access to your site. Store it only in trusted configuration, and regenerate it (by toggling **Token Enabled** off and on) if it may have been exposed.

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
