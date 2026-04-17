<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Tests\Unit\Service;

use MarekSkopal\MsMcpDocs\Dto\OAuthServerMetadata;
use MarekSkopal\MsMcpDocs\Repository\OAuthTokenRepositoryInterface;
use MarekSkopal\MsMcpDocs\Service\OAuthTokenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

#[CoversClass(OAuthTokenService::class)]
final class OAuthTokenServiceTest extends TestCase
{
    public function testGeneratePkceChallengeProducesValidPair(): void
    {
        $service = new OAuthTokenService(
            $this->createStub(RequestFactory::class),
            $this->createStub(FrontendInterface::class),
            $this->createStub(OAuthTokenRepositoryInterface::class),
        );

        $pair = $service->generatePkceChallenge();

        self::assertNotEmpty($pair->codeVerifier);
        self::assertNotEmpty($pair->codeChallenge);
        self::assertNotSame($pair->codeVerifier, $pair->codeChallenge);

        // Verify S256 relationship
        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $pair->codeVerifier, true)), '+/', '-_'), '=');
        self::assertSame($expectedChallenge, $pair->codeChallenge);
    }

    public function testBuildAuthorizationUrl(): void
    {
        $service = new OAuthTokenService(
            $this->createStub(RequestFactory::class),
            $this->createStub(FrontendInterface::class),
            $this->createStub(OAuthTokenRepositoryInterface::class),
        );

        $metadata = new OAuthServerMetadata(
            issuer: 'https://example.com/api/mcp',
            authorizationEndpoint: 'https://example.com/app/oauth/authorize',
            tokenEndpoint: 'https://example.com/api/mcp/oauth/token',
            registrationEndpoint: 'https://example.com/api/mcp/oauth/register',
        );

        $url = $service->buildAuthorizationUrl(
            metadata: $metadata,
            clientId: 'test-client',
            redirectUri: 'https://typo3.example.com/callback',
            codeChallenge: 'test-challenge',
            state: 'test-state',
        );

        self::assertStringStartsWith('https://example.com/app/oauth/authorize?', $url);
        self::assertStringContainsString('response_type=code', $url);
        self::assertStringContainsString('client_id=test-client', $url);
        self::assertStringContainsString('code_challenge=test-challenge', $url);
        self::assertStringContainsString('code_challenge_method=S256', $url);
        self::assertStringContainsString('state=test-state', $url);
        self::assertStringContainsString('redirect_uri=', $url);
    }

    public function testStorePkceStateAndLoad(): void
    {
        $cache = $this->createMock(FrontendInterface::class);

        $stateData = [
            'code_verifier' => 'verifier',
            'client_id' => 'client-123',
            'content_element_uid' => 42,
            'server_url' => 'https://example.com/api/mcp',
            'metadata' => [
                'issuer' => 'https://example.com/api/mcp',
                'authorizationEndpoint' => 'https://example.com/app/oauth/authorize',
                'tokenEndpoint' => 'https://example.com/api/mcp/oauth/token',
                'registrationEndpoint' => 'https://example.com/api/mcp/oauth/register',
            ],
        ];

        $cache->expects(self::once())->method('set')->with('oauth_state_test-state', $stateData, [], 600);
        $cache->method('get')->with('oauth_state_test-state')->willReturn($stateData);

        $service = new OAuthTokenService(
            $this->createStub(RequestFactory::class),
            $cache,
            $this->createStub(OAuthTokenRepositoryInterface::class),
        );

        $service->storePkceState('test-state', $stateData);

        $loaded = $service->loadPkceState('test-state');
        self::assertSame($stateData, $loaded);
    }

    public function testLoadPkceStateReturnsNullWhenMissing(): void
    {
        $cache = $this->createStub(FrontendInterface::class);
        $cache->method('get')->willReturn(false);

        $service = new OAuthTokenService(
            $this->createStub(RequestFactory::class),
            $cache,
            $this->createStub(OAuthTokenRepositoryInterface::class),
        );

        self::assertNull($service->loadPkceState('nonexistent'));
    }

    public function testGetValidAccessTokenReturnsTokenWhenValid(): void
    {
        $repository = $this->createStub(OAuthTokenRepositoryInterface::class);
        $repository->method('findByContentElementUid')->willReturn([
            'content_element_uid' => 1,
            'server_url' => 'https://example.com/api/mcp',
            'client_id' => 'client-123',
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => time() + 3600,
        ]);

        $service = new OAuthTokenService(
            $this->createStub(RequestFactory::class),
            $this->createStub(FrontendInterface::class),
            $repository,
        );

        $token = $service->getValidAccessToken(1, 'https://example.com/api/mcp');
        self::assertSame('valid-token', $token);
    }

    public function testGetValidAccessTokenReturnsNullWhenNoToken(): void
    {
        $repository = $this->createStub(OAuthTokenRepositoryInterface::class);
        $repository->method('findByContentElementUid')->willReturn(null);

        $service = new OAuthTokenService(
            $this->createStub(RequestFactory::class),
            $this->createStub(FrontendInterface::class),
            $repository,
        );

        self::assertNull($service->getValidAccessToken(1, 'https://example.com/api/mcp'));
    }
}
