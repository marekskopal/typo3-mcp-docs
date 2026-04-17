<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Tests\Unit\Service;

use MarekSkopal\MsMcpDocs\Dto\McpTool;
use MarekSkopal\MsMcpDocs\Service\McpIntrospectionService;
use MarekSkopal\MsMcpDocs\Service\McpResponseParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

#[CoversClass(McpIntrospectionService::class)]
final class McpIntrospectionServiceTest extends TestCase
{
    public function testGetToolsReturnsCachedResult(): void
    {
        $cachedTools = [
            new McpTool('tool_a', 'Tool A', []),
            new McpTool('tool_b', 'Tool B', []),
        ];

        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturn($cachedTools);
        $cache->expects(self::never())->method('set');

        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::never())->method('request');

        $service = new McpIntrospectionService($requestFactory, $cache, new McpResponseParser());
        $result = $service->getTools('https://example.com/mcp', 'token');

        self::assertCount(2, $result);
        self::assertSame('tool_a', $result[0]->name);
        self::assertSame('tool_b', $result[1]->name);
    }

    public function testGetToolsReturnsCachedResultWithFilter(): void
    {
        $cachedTools = [
            new McpTool('tool_a', 'Tool A', []),
            new McpTool('tool_b', 'Tool B', []),
            new McpTool('tool_c', 'Tool C', []),
        ];

        $cache = $this->createStub(FrontendInterface::class);
        $cache->method('get')->willReturn($cachedTools);

        $service = new McpIntrospectionService(
            $this->createStub(RequestFactory::class),
            $cache,
            new McpResponseParser(),
        );

        $result = $service->getTools('https://example.com/mcp', 'token', ['tool_a', 'tool_c']);

        self::assertCount(2, $result);
        self::assertSame('tool_a', $result[0]->name);
        self::assertSame('tool_c', $result[1]->name);
    }

    public function testGetToolsUsesCacheKeyBasedOnServerUrl(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with('mcp_tools_' . md5('https://example.com/mcp'))
            ->willReturn([new McpTool('tool', 'Tool', [])]);

        $service = new McpIntrospectionService(
            $this->createStub(RequestFactory::class),
            $cache,
            new McpResponseParser(),
        );

        $service->getTools('https://example.com/mcp', 'token');
    }

    public function testFilterToolsReturnsAllWhenFilterIsNull(): void
    {
        $tools = [
            new McpTool('a', '', []),
            new McpTool('b', '', []),
        ];

        self::assertSame($tools, McpIntrospectionService::filterTools($tools, null));
    }

    public function testFilterToolsReturnsAllWhenFilterIsEmpty(): void
    {
        $tools = [
            new McpTool('a', '', []),
            new McpTool('b', '', []),
        ];

        self::assertSame($tools, McpIntrospectionService::filterTools($tools, []));
    }

    public function testFilterToolsFiltersCorrectly(): void
    {
        $tools = [
            new McpTool('keep_this', '', []),
            new McpTool('remove_this', '', []),
            new McpTool('also_keep', '', []),
        ];

        $result = McpIntrospectionService::filterTools($tools, ['keep_this', 'also_keep']);

        self::assertCount(2, $result);
        self::assertSame('keep_this', $result[0]->name);
        self::assertSame('also_keep', $result[1]->name);
    }

    public function testFilterToolsReturnsEmptyWhenNoMatch(): void
    {
        $tools = [new McpTool('tool_a', '', [])];

        $result = McpIntrospectionService::filterTools($tools, ['nonexistent']);

        self::assertSame([], $result);
    }
}
