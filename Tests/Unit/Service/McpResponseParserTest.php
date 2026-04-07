<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Tests\Unit\Service;

use MarekSkopal\MsMcpDocs\Dto\McpTool;
use MarekSkopal\MsMcpDocs\Dto\McpToolParameter;
use MarekSkopal\MsMcpDocs\Service\McpResponseParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpResponseParser::class)]
#[CoversClass(McpTool::class)]
#[CoversClass(McpToolParameter::class)]
final class McpResponseParserTest extends TestCase
{
    private McpResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new McpResponseParser();
    }

    public function testParseToolsFromDirectJsonResponse(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => [
                'tools' => [
                    [
                        'name' => 'get_user',
                        'description' => 'Get user by ID',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [
                                    'type' => 'integer',
                                    'description' => 'User ID',
                                ],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $tools = $this->parser->parseToolsFromResponse($body);

        self::assertCount(1, $tools);
        self::assertSame('get_user', $tools[0]->name);
        self::assertSame('Get user by ID', $tools[0]->description);
        self::assertCount(1, $tools[0]->parameters);
        self::assertSame('id', $tools[0]->parameters[0]->name);
        self::assertSame('integer', $tools[0]->parameters[0]->type);
        self::assertSame('User ID', $tools[0]->parameters[0]->description);
        self::assertTrue($tools[0]->parameters[0]->required);
    }

    public function testParseToolsFromSseResponse(): void
    {
        $body = "event: message\ndata: " . json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => [
                'tools' => [
                    ['name' => 'list_items', 'description' => 'List all items'],
                ],
            ],
        ], JSON_THROW_ON_ERROR) . "\n\n";

        $tools = $this->parser->parseToolsFromResponse($body);

        self::assertCount(1, $tools);
        self::assertSame('list_items', $tools[0]->name);
        self::assertSame('List all items', $tools[0]->description);
        self::assertSame([], $tools[0]->parameters);
    }

    public function testParseToolsFromSseWithMultipleDataLines(): void
    {
        $body = "event: message\n"
            . 'data: ' . json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], JSON_THROW_ON_ERROR) . "\n\n"
            . "event: message\n"
            . 'data: ' . json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'tools' => [
                        ['name' => 'do_something', 'description' => 'Does something'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR) . "\n\n";

        $tools = $this->parser->parseToolsFromResponse($body);

        self::assertCount(1, $tools);
        self::assertSame('do_something', $tools[0]->name);
    }

    public function testParseToolsReturnsEmptyForInvalidBody(): void
    {
        self::assertSame([], $this->parser->parseToolsFromResponse('not json'));
        self::assertSame([], $this->parser->parseToolsFromResponse(''));
    }

    public function testParseToolsReturnsEmptyWhenNoToolsKey(): void
    {
        $body = json_encode(['jsonrpc' => '2.0', 'id' => 2, 'result' => []], JSON_THROW_ON_ERROR);

        self::assertSame([], $this->parser->parseToolsFromResponse($body));
    }

    public function testParseMultipleTools(): void
    {
        $body = json_encode([
            'result' => [
                'tools' => [
                    ['name' => 'tool_a', 'description' => 'A'],
                    ['name' => 'tool_b', 'description' => 'B'],
                    ['name' => 'tool_c', 'description' => 'C'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $tools = $this->parser->parseToolsFromResponse($body);

        self::assertCount(3, $tools);
        self::assertSame('tool_a', $tools[0]->name);
        self::assertSame('tool_b', $tools[1]->name);
        self::assertSame('tool_c', $tools[2]->name);
    }

    public function testParseToolDataWithAllParameterFeatures(): void
    {
        $tool = $this->parser->parseToolData([
            'name' => 'create_report',
            'description' => 'Create a report',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'format' => [
                        'type' => 'string',
                        'description' => 'Output format',
                        'enum' => ['pdf', 'csv', 'html'],
                        'default' => 'pdf',
                    ],
                    'verbose' => [
                        'type' => 'boolean',
                        'description' => 'Verbose output',
                        'default' => true,
                    ],
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of items',
                        'default' => 10,
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'Report title',
                    ],
                ],
                'required' => ['title'],
            ],
        ]);

        self::assertSame('create_report', $tool->name);
        self::assertSame('Create a report', $tool->description);
        self::assertCount(4, $tool->parameters);

        $format = $tool->parameters[0];
        self::assertSame('format', $format->name);
        self::assertSame('string', $format->type);
        self::assertSame('pdf, csv, html', $format->enum);
        self::assertSame('pdf', $format->default);
        self::assertFalse($format->required);

        $verbose = $tool->parameters[1];
        self::assertSame('verbose', $verbose->name);
        self::assertSame('boolean', $verbose->type);
        self::assertSame('true', $verbose->default);

        $count = $tool->parameters[2];
        self::assertSame('count', $count->name);
        self::assertSame('10', $count->default);

        $title = $tool->parameters[3];
        self::assertSame('title', $title->name);
        self::assertTrue($title->required);
        self::assertNull($title->default);
        self::assertNull($title->enum);
    }

    public function testParseToolDataWithArrayType(): void
    {
        $tool = $this->parser->parseToolData([
            'name' => 'nullable_field',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'value' => [
                        'type' => ['string', 'null'],
                        'description' => 'Nullable string',
                    ],
                ],
            ],
        ]);

        self::assertSame('string|null', $tool->parameters[0]->type);
    }

    public function testParseToolDataWithMissingDescription(): void
    {
        $tool = $this->parser->parseToolData([
            'name' => 'no_desc_tool',
        ]);

        self::assertSame('no_desc_tool', $tool->name);
        self::assertSame('', $tool->description);
        self::assertSame([], $tool->parameters);
    }

    public function testParseToolDataWithBooleanDefaultFalse(): void
    {
        $tool = $this->parser->parseToolData([
            'name' => 'bool_test',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'flag' => [
                        'type' => 'boolean',
                        'default' => false,
                    ],
                ],
            ],
        ]);

        self::assertSame('false', $tool->parameters[0]->default);
    }

    public function testParseToolDataWithNoSchema(): void
    {
        $tool = $this->parser->parseToolData([
            'name' => 'simple_tool',
            'description' => 'No parameters',
        ]);

        self::assertSame([], $tool->parameters);
    }

    public function testExtractJsonFromSseWithDirectJson(): void
    {
        $json = json_encode(['result' => ['tools' => []]], JSON_THROW_ON_ERROR);

        $result = $this->parser->extractJsonFromSse($json);

        self::assertIsArray($result);
        self::assertArrayHasKey('result', $result);
    }

    public function testExtractJsonFromSseReturnsNullForInvalidInput(): void
    {
        self::assertNull($this->parser->extractJsonFromSse(''));
        self::assertNull($this->parser->extractJsonFromSse('not json at all'));
        self::assertNull($this->parser->extractJsonFromSse("data: not json\n"));
    }

    public function testExtractJsonFromSseSkipsDataLinesWithoutResult(): void
    {
        $body = "data: {\"method\":\"ping\"}\ndata: {\"result\":{\"tools\":[]}}\n";

        $result = $this->parser->extractJsonFromSse($body);

        self::assertIsArray($result);
        self::assertArrayHasKey('result', $result);
    }

    /** @return iterable<string, array{mixed, string|null}> */
    public static function nonScalarDefaultProvider(): iterable
    {
        yield 'array default' => [['a', 'b'], null];
        yield 'null default' => [null, null];
        yield 'object default' => [new \stdClass(), null];
    }

    #[DataProvider('nonScalarDefaultProvider')]
    public function testParseToolDataWithNonScalarDefault(mixed $defaultValue, ?string $expected): void
    {
        $tool = $this->parser->parseToolData([
            'name' => 'test',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'param' => [
                        'type' => 'string',
                        'default' => $defaultValue,
                    ],
                ],
            ],
        ]);

        self::assertSame($expected, $tool->parameters[0]->default);
    }
}
