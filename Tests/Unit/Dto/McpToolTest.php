<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Tests\Unit\Dto;

use MarekSkopal\MsMcpDocs\Dto\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpTool::class)]
final class McpToolTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function groupProvider(): iterable
    {
        yield 'prefix with underscore' => ['list_portfolios', 'list'];
        yield 'multiple underscores' => ['get_portfolio_summary', 'get'];
        yield 'no underscore' => ['simple', 'simple'];
        yield 'trailing underscore' => ['prefix_', 'prefix'];
    }

    #[DataProvider('groupProvider')]
    public function testGetGroup(string $name, string $expectedGroup): void
    {
        $tool = new McpTool($name, '', []);

        self::assertSame($expectedGroup, $tool->getGroup());
    }
}
