# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TYPO3 CMS extension (`ms_mcp_docs`) that renders MCP (Model Context Protocol) server tool documentation as a content element. It connects to an MCP server, fetches available tools via the MCP protocol (v2025-03-26), and displays them with their parameters.

## Commands

```bash
# Static analysis (level max, strict)
vendor/bin/phpstan analyse

# Code style check
vendor/bin/phpcs

# Code style fix
vendor/bin/phpcbf

# Run tests
vendor/bin/phpunit

# Install dependencies (runs asset:publish post-install)
composer install
```

## Architecture

**Flow:** FlexForm config (server URL, auth type, API key/OAuth) → `McpDocsController::listAction()` → `McpIntrospectionService::getTools()` → HTTP requests to MCP server → cached response → Fluid templates render tool list.

**Key classes (all in `Classes/`):**
- `Controller/McpDocsController` — Extbase controller with single `listAction`, reads FlexForm settings, groups tools by name prefix
- `Controller/OAuthCallbackController` — Backend route controller handling OAuth initiate (redirect to auth server) and callback (token exchange) actions
- `Service/McpIntrospectionService` — MCP protocol client: 3-step session (initialize → notification → tools/list), handles both JSON and SSE responses, caches results for 24h via TYPO3 cache `msmcpdocs`
- `Service/OAuthTokenService` — OAuth 2.0 client: metadata discovery, dynamic client registration, PKCE challenge generation, token exchange, token refresh, PKCE state storage via cache
- `Repository/OAuthTokenRepository` — Persists OAuth tokens per content element in `tx_msmcpdocs_oauth_token`
- `Form/Element/OAuthStatusElement` — Custom TCA render type (`oauthStatus`) showing authorization status and authorize button in FlexForm
- `Dto/McpTool`, `Dto/McpToolParameter` — Readonly DTOs for tool data
- `Dto/OAuthServerMetadata`, `Dto/OAuthTokenResponse`, `Dto/PkceChallengePair` — Readonly DTOs for OAuth data

**Configuration:**
- `Configuration/Services.yaml` — DI config; cache injected via CacheManager factory; `OAuthCallbackController` registered as public service
- `Configuration/Backend/Routes.php` — Backend routes for OAuth initiate and callback endpoints
- `Configuration/FlexForms/Flexform.xml` — Plugin settings: mcpServerUrl, authType (bearer/oauth), mcpApiKey, oauthStatus, displayMode (full/overview), filterTools
- `ext_localconf.php` — Plugin registration, cache configuration, and `oauthStatus` node registry
- `ext_tables.sql` — DB schema for `tx_msmcpdocs_oauth_token` table
- `Configuration/Sets/MsMcpDocs/` — TYPO3 Site Set with TypoScript setup

## Code Standards

- PHP 8.3+ with `declare(strict_types=1)`
- PHPStan at level **max** with bleeding edge, strict checks, and `checkImplicitMixed: true`
- PHPCS with SlevomatCodingStandard (140 char line limit)
- All classes are `final readonly` where possible
- Supports TYPO3 v13.4 and v14.x
