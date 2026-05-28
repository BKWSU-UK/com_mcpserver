# Security Policy

## Supported Versions

Security fixes are provided for the latest published release of MCP Server for Joomla.

## Reporting a Vulnerability

Please report security issues privately through GitHub security advisories for this repository, or by contacting Onepoint Consulting Ltd through the repository owner profile.

Do not open public issues for vulnerabilities that could expose Joomla sites, API tokens, bearer tokens, content data or administrator access.

## Security Notes

Production sites should keep MCP bearer authentication enabled, use long random bearer tokens, restrict CORS origins where possible, configure trusted proxies before relying on `X-Forwarded-For`, and use HTTPS for all MCP client connections.
