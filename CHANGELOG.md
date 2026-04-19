# Changelog

All notable changes to the `ms_mcp_docs` extension will be documented in this file.

## [1.1.1] - 2026-04-19

### Added
- Plugin title for better identification in TYPO3 backend
- `.gitattributes` for cleaner distribution packaging

### Changed
- Updated version to 1.1.1

## [1.1.0] - 2026-04-18

### Added
- OAuth 2.0 authentication support with PKCE
  - `OAuthCallbackController` for handling OAuth initiate and callback flows
  - `OAuthTokenService` for metadata discovery, dynamic client registration, and token exchange
  - `OAuthTokenRepository` for persisting tokens per content element
  - Custom `OAuthStatusElement` TCA render type showing authorization status in FlexForm
  - DTOs: `OAuthServerMetadata`, `OAuthTokenResponse`, `PkceChallengePair`
  - Backend routes for OAuth endpoints
  - FlexForm fields for auth type selection (bearer/oauth) and OAuth status
  - Database table `tx_msmcpdocs_oauth_token`
- Unit tests for `OAuthTokenService`

### Fixed
- Dependency injection configuration
- Column layout in templates

## [1.0.0] - 2026-04-07

### Added
- Initial release
- MCP protocol client (v2025-03-26) with 3-step session (initialize → notification → tools/list)
- `McpDocsController` with `listAction` for rendering tool documentation
- `McpIntrospectionService` handling JSON and SSE responses with 24h caching
- FlexForm configuration for server URL, API key, display mode, and tool filtering
- Tool grouping by name prefix
- DTOs: `McpTool`, `McpToolParameter`
- Fluid templates for full and overview display modes
- Localization support (English, Czech, German)
- Unit tests for `McpIntrospectionService`
- TYPO3 v13.4 and v14.x support
