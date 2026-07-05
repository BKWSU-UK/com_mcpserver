# Changelog

All notable release changes for MCP Server for Joomla are recorded here.

## 1.4.0 - 2026-07-05

- Added category tools: `list_categories`, `get_category`, `create_category`, `update_category`, `delete_category`.
- Added tag tools: `list_tags`, `get_tag`, `create_tag`, `update_tag`, `delete_tag`.
- Added extension management tools: `list_extensions`, `set_extension_state` (enable/disable, e.g. activating a plugin after install) and `uninstall_extension`.
- Added `create_menu`, `delete_menu_item`, `create_module` (any installed module type) and `delete_module` for full menu/module CRUD.
- Added a Read-Only Mode option that blocks every tool not annotated read-only.
- Fixed `search_articles` filters (`search`, `catid`, `state`, `author`, `language`) being silently ignored — they are now sent as the `filter[...]` query parameters the Joomla API reads; `author` is now a numeric user ID and a `featured` filter was added.
- Fixed `delete_article` failing on non-trashed articles; it now trashes first, then deletes (same behaviour for the new category, tag and menu item delete tools).
- Fixed pagination on `list_modules`, `list_menus`, `list_template_styles` and `list_content_languages`: they now accept `limit`/`offset` and forward them to the Joomla API, so items beyond the API's first page (20) are reachable.
- Fixed `list_custom_modules` hiding custom modules beyond the first 20 modules; it now aggregates every API page before filtering.
- Fixed rate limiting and the SSE response relay silently not working when Joomla's global caching is off (the Joomla default); the component now forces caching on for its own cache instances.
- Fixed `update_custom_module` writing without checking the module exists, matches the requested client and is a `mod_custom` module.
- Added JSON-RPC batch request support (required by MCP protocol revision 2025-03-26).
- CORS preflight now allows the `Mcp-Session-Id` and `MCP-Protocol-Version` headers sent by Streamable HTTP MCP clients.
- Security: `install_extension`, `uninstall_extension` and `update_template_file` (the code-execution tools) are now disabled by default; remove them from Disabled Tools in the options to opt in. `update_template_file` now carries an arbitrary-code-execution warning.
- Removed the unused Default Language option.

## 1.3.5 - 2026-06-30

- HTTP stdio bridge now aggregates paginated `tools/list` (and other list) responses so clients that ignore `nextCursor` still receive every tool.
- Added a configurable **Tools List Page Size** option (default 100) for `tools/list` pagination.
- Fixed Tools List Page Size using a number input instead of an empty integer dropdown.
- Fixed Cache TTL using a number input instead of an empty integer dropdown.
- Added server `instructions` on initialise describing list-tool pagination and `nextCursor` behaviour.
- Clarified `limit`/`offset` parameter descriptions on paginated list tools.

## 1.3.4 - 2026-06-30

- Fixed update feed infourl entries pointing to the wrong release tag.
- Added garbage collection and session cleanup to JoomlaCache to prevent stale SSE session data accumulating.
- Enhanced update.xml entries with maintainer, PHP minimum, and platform metadata.
- Updated documentation.

## 1.3.3 - 2026-06-29

- Fixed tools listing pagination.
- Updated `install_extension` tool description to prefer source path over base64 content.

## 1.3.2 - 2026-06-23

- Added SHA-256 checksum verification for release packages: the release workflow now records the package hash in `update.xml` so Joomla can validate downloaded updates.
- Fixed paging issues with broken offset value as well as a wrong total_count.
- Added evaluations for paging.

## 1.3.1 - 2026-06-22

- Improved tool execution failures and policy denials handling.
- Added pagination metadata (`total_count`, `count`, `offset`, `has_more`, `next_offset`) to all list tool responses.
- Added a **Disabled Tools** option to block specific MCP tools by name in component security settings.
- Fixed **Resolve Host To IP** so the override also applies to server local downloads.
- Added an MCP evaluation suite.
- Updated documentation.

## 1.3.0 - 2026-06-20

- Added a **Resolve Host To IP** option: pin the Base URL hostname to a specific IP (e.g. `127.0.0.1`) for the component's outbound REST calls only. Lets a server reach its own public hostname when NAT hairpinning is blocked, while keeping the correct Host header and TLS validation intact.

## 1.2.1 - 2026-06-20

- Updated project branding: added a new logo and refreshed dashboard screenshots, and removed the outdated overview screenshot.
- Added a GitHub star banner to the dashboard.
- Added more detail for the API token configuration in the README.

## 1.2.0 - 2026-06-20

- Added **extension installation** support: a new `install_extension` MCP tool installs extensions onto the site.
- Added **template editing** MCP tools: list installed templates, list and read template files, update template files, and create template overrides.
- Fixed the client configuration snippet generation in the component dashboard view.
- Fixed the Monolog log file path resolution.
- Reordered the administrator menu items.
- Updated the build script to also update the update.xml file.

## 1.1.0 - 2026-06-02

- Added request metrics: every MCP request is recorded to a new `#__mcpserver_request_log` table (method, tool, status, error code, HTTP status, latency, client IP, context).
- Added an admin **Dashboard** view with summary cards (total / last 24h / last 7d / error rate / average latency / rate-limit hits / auth failures), a requests-per-day chart, top tools and methods, and a recent-requests log.
- Added **Monitoring & Metrics** options: toggle recording on/off and configure the retention window (old rows are pruned automatically).

## 1.0.0 - 2026-05-31

- First stable release published to the Joomla Extensions Directory under `GPL-2.0-or-later`.
- Aligned the administrator menu and headings with the listing name "MCP Server for Joomla".
- Hardened the release build for the official JED Checker.

## 0.6.0 - 2026-05-28

- Added template style and installed template MCP tools.
- Added article and menu item multilingual association tools.
- Added article content history tools for listing, reading, keeping, deleting and restoring versions.
- Prepared the project for Joomla Extensions Directory release under `GPL-2.0-or-later`.
- Added Joomla update server metadata for GitHub release packages.
