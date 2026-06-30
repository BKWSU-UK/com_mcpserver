# Changelog

All notable release changes for MCP Server for Joomla are recorded here.

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
