# MCP Docs for TYPO3 CMS

MCP server tool documentation as a content element in TYPO3. Connects to an MCP (Model Context Protocol) server, fetches available tools with their parameters, and renders them as a browsable reference. Results are cached for 24 hours.

## Features

- Automatic tool introspection via MCP protocol (v2025-03-26)
- Two display modes: **full** (with parameter tables) and **overview** (name + description only)
- Tool filtering — show only selected tools via comma-separated list
- Tools grouped by name prefix
- 24-hour response caching (TYPO3 database-backed cache)
- Bearer token authentication
- OAuth 2.0 authentication with PKCE (RFC 7636) and dynamic client registration (RFC 7591)
- Handles both JSON and SSE (Server-Sent Events) responses
- Customizable templates and styling

## Requirements

- PHP 8.3+
- TYPO3 13.4 or 14.x

## Installation

```bash
composer require marekskopal/typo3-mcp-docs
```

## Setup

Include the TypoScript Set **MCP Docs** in your site package or via the site configuration sets.

## Content Element Options (FlexForm)

| Option | Description |
|--------|-------------|
| **MCP Server URL** | URL of the MCP server endpoint (e.g. `https://example.com/api/mcp`) |
| **Auth type** | `bearer` — static API key; `oauth` — OAuth 2.0 with PKCE |
| **MCP API Key** | Bearer token for authentication (shown when auth type is `bearer`) |
| **OAuth Status** | Shows authorization status and provides authorize/re-authorize button (shown when auth type is `oauth`) |
| **Display mode** | `full` — tool name, description, and parameter table; `overview` — name and description only |
| **Filter tools** | Comma-separated list of tool names to display (empty = all tools) |

### OAuth Authentication

When using OAuth, the extension performs the full OAuth 2.0 authorization code flow with PKCE:

1. Discovers the server's OAuth metadata via `/.well-known/oauth-authorization-server`
2. Dynamically registers a client with the authorization server (RFC 7591)
3. Redirects to the authorization endpoint with a PKCE code challenge
4. Exchanges the authorization code for access/refresh tokens
5. Stores tokens per content element and automatically refreshes them when expired

To set up OAuth: select **OAuth** as auth type, save the content element, then click the **Authorize** button.

## Customization

### Templates

Override templates by setting custom paths in TypoScript:

```typoscript
plugin.tx_msmcpdocs_mcpdocs.view.templateRootPaths.10 = EXT:your_extension/Resources/Private/Templates/MsMcpDocs/
plugin.tx_msmcpdocs_mcpdocs.view.partialRootPaths.10  = EXT:your_extension/Resources/Private/Partials/MsMcpDocs/
plugin.tx_msmcpdocs_mcpdocs.view.layoutRootPaths.10   = EXT:your_extension/Resources/Private/Layouts/MsMcpDocs/
```

### Styling

The extension includes minimal CSS. Key classes:

| Class | Element |
|-------|---------|
| `.msmcpdocs-wrapper` | Outer wrapper |
| `.msmcpdocs__tool` | Single tool card |
| `.msmcpdocs__tool-name` | Tool name heading |
| `.msmcpdocs__tool-description` | Tool description |
| `.msmcpdocs__params` | Parameters table |
| `.msmcpdocs__error` | Error message |

## License

GPL-2.0-or-later
