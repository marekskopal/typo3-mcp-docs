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

**Flow:** FlexForm config (server URL, API key) → `McpDocsController::listAction()` → `McpIntrospectionService::getTools()` → HTTP requests to MCP server → cached response → Fluid templates render tool list.

**Key classes (all in `Classes/`):**
- `Controller/McpDocsController` — Extbase controller with single `listAction`, reads FlexForm settings, groups tools by name prefix
- `Service/McpIntrospectionService` — MCP protocol client: 3-step session (initialize → notification → tools/list), handles both JSON and SSE responses, caches results for 24h via TYPO3 cache `msmcpdocs`
- `Dto/McpTool`, `Dto/McpToolParameter` — Readonly DTOs for tool data

**Configuration:**
- `Configuration/Services.yaml` — DI config; cache injected via CacheManager factory
- `Configuration/FlexForms/Flexform.xml` — Plugin settings: mcpServerUrl, mcpApiKey, displayMode (full/overview), filterTools
- `ext_localconf.php` — Plugin registration and cache configuration
- `Configuration/Sets/MsMcpDocs/` — TYPO3 Site Set with TypoScript setup

## Code Standards

- PHP 8.3+ with `declare(strict_types=1)`
- PHPStan at level **max** with bleeding edge, strict checks, and `checkImplicitMixed: true`
- PHPCS with SlevomatCodingStandard (140 char line limit)
- All classes are `final readonly` where possible
- Supports TYPO3 v13.4 and v14.x
