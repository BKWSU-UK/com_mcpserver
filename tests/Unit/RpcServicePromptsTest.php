<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Tests\Unit;

defined('_JEXEC') or die;

use Joomla\Component\Mcpserver\Administrator\Service\CacheService;
use Joomla\Component\Mcpserver\Administrator\Service\JsonRpc;
use Joomla\Component\Mcpserver\Administrator\Service\PolicyService;
use Joomla\Component\Mcpserver\Administrator\Service\PromptRegistry;
use Joomla\Component\Mcpserver\Administrator\Service\RestClient;
use Joomla\Component\Mcpserver\Administrator\Service\RpcService;
use Joomla\Component\Mcpserver\Administrator\Service\SchemaValidator;
use Joomla\Component\Mcpserver\Administrator\Service\SimpleArrayCache;
use Joomla\Component\Mcpserver\Administrator\Service\ToolRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RpcServicePromptsTest extends TestCase
{
    public function testInitializeAdvertisesPromptsWhenEnabled(): void
    {
        $response = $this->makeService(promptsEnabled: true)->handle($this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
        ]));

        $this->assertSame(['listChanged' => false], $response['result']['capabilities']['prompts']);
        $this->assertArrayNotHasKey('resources', $response['result']['capabilities']);
    }

    public function testListPromptsReturnsRegisteredDefinitions(): void
    {
        $response = $this->makeService(promptsEnabled: true)->handle($this->rpc('prompts/list'));

        $names = array_column($response['result']['prompts'], 'name');
        $this->assertSame(['draft-article', 'seo-audit-article', 'translate-article'], $names);

        $draft = $response['result']['prompts'][0];
        $this->assertSame('topic', $draft['arguments'][0]['name']);
        $this->assertTrue($draft['arguments'][0]['required']);
        $this->assertSame('category', $draft['arguments'][1]['name']);
        $this->assertFalse($draft['arguments'][1]['required']);
    }

    public function testListPromptsReturnsEmptyWhenDisabled(): void
    {
        $rest = $this->createRestMock();
        $rest->expects($this->never())->method('get');

        $response = $this->makeService($rest, false)->handle($this->rpc('prompts/list'));

        $this->assertSame(['prompts' => []], $response['result']);
    }

    public function testGetPromptReturnsMethodNotFoundWhenDisabled(): void
    {
        $response = $this->makeService()->handle($this->rpc('prompts/get', [
            'name' => 'draft-article',
            'arguments' => ['topic' => 'gardening'],
        ]));

        $this->assertSame(JsonRpc::METHOD_NOT_FOUND, $response['error']['code']);
        $this->assertSame('Prompts are disabled by server policy', $response['error']['message']);
    }

    public function testDraftArticlePromptIncludesTopicRecentTitlesAndCategory(): void
    {
        $rest = $this->createRestMock();
        $rest->method('get')->willReturn([
            'data' => [
                ['id' => '2', 'attributes' => ['title' => 'Soil basics']],
                ['id' => '5', 'attributes' => ['title' => 'Composting guide']],
            ],
        ]);

        $response = $this->makeService($rest, true)->handle($this->rpc('prompts/get', [
            'name' => 'draft-article',
            'arguments' => [
                'topic' => 'raised beds',
                'category' => 'Gardening',
            ],
        ]));

        $text = $response['result']['messages'][0]['content']['text'];
        $this->assertStringContainsString('raised beds', $text);
        $this->assertStringContainsString('Gardening', $text);
        $this->assertStringContainsString('Composting guide', $text);
        $this->assertStringContainsString('Soil basics', $text);
        $this->assertStringContainsString('create_article', $text);
        $this->assertSame('user', $response['result']['messages'][0]['role']);
        $this->assertSame('text', $response['result']['messages'][0]['content']['type']);
    }

    public function testGetPromptRejectsMissingRequiredArgument(): void
    {
        $response = $this->makeService(promptsEnabled: true)->handle($this->rpc('prompts/get', [
            'name' => 'draft-article',
            'arguments' => [],
        ]));

        $this->assertSame(JsonRpc::INVALID_PARAMS, $response['error']['code']);
        $this->assertSame('Missing required argument: topic', $response['error']['message']);
    }

    public function testGetPromptRejectsUnknownName(): void
    {
        $response = $this->makeService(promptsEnabled: true)->handle($this->rpc('prompts/get', [
            'name' => 'no-such-prompt',
        ]));

        $this->assertSame(JsonRpc::INVALID_PARAMS, $response['error']['code']);
        $this->assertSame('Unknown prompt: no-such-prompt', $response['error']['message']);
    }

    public function testSeoAuditPromptEmbedsResourceAndMetadata(): void
    {
        $rest = $this->createRestMock();
        $rest->method('get')->willReturn([
            // No top-level data.id so injectRawArticleContent skips Factory::getDbo().
            'data' => [
                'attributes' => [
                    'title' => 'About us',
                    'alias' => 'about-us',
                    'metadesc' => 'Who we are',
                    'introtext' => '<p>Intro</p>',
                    'fulltext' => '<p>Body</p>',
                ],
            ],
        ]);

        $response = $this->makeService($rest, true)->handle($this->rpc('prompts/get', [
            'name' => 'seo-audit-article',
            'arguments' => ['article_id' => '12'],
        ]));

        $messages = $response['result']['messages'];
        $this->assertCount(2, $messages);
        $this->assertSame('resource', $messages[0]['content']['type']);
        $this->assertSame('joomla://article/12', $messages[0]['content']['resource']['uri']);
        $this->assertSame('text/html', $messages[0]['content']['resource']['mimeType']);
        $this->assertSame('<p>Intro</p><p>Body</p>', $messages[0]['content']['resource']['text']);

        $text = $messages[1]['content']['text'];
        $this->assertStringContainsString('About us', $text);
        $this->assertStringContainsString('about-us', $text);
        $this->assertStringContainsString('Who we are', $text);
        $this->assertStringContainsString('update_article', $text);
    }

    public function testTranslateArticlePromptListsPublishedLanguageTags(): void
    {
        $rest = $this->createRestMock();
        $rest->method('get')->willReturnCallback(
            static function (string $path, array $query = []): array {
                if (str_contains($path, 'languages')) {
                    return [
                        'data' => [
                            ['attributes' => ['lang_code' => 'en-GB']],
                            ['attributes' => ['lang_code' => 'fr-FR']],
                        ],
                    ];
                }

                return [
                    'data' => [
                        'attributes' => [
                            'introtext' => '<p>Hello</p>',
                            'fulltext' => '',
                        ],
                    ],
                ];
            }
        );

        $response = $this->makeService($rest, true)->handle($this->rpc('prompts/get', [
            'name' => 'translate-article',
            'arguments' => [
                'article_id' => '4',
                'target_language' => 'fr-FR',
            ],
        ]));

        $messages = $response['result']['messages'];
        $this->assertSame('resource', $messages[0]['content']['type']);
        $this->assertSame('joomla://article/4', $messages[0]['content']['resource']['uri']);
        $this->assertSame('<p>Hello</p>', $messages[0]['content']['resource']['text']);

        $text = $messages[1]['content']['text'];
        $this->assertStringContainsString('fr-FR', $text);
        $this->assertStringContainsString('en-GB', $text);
        $this->assertStringContainsString('create_article', $text);
        $this->assertStringContainsString('set_article_associations', $text);
    }

    public function testConstructionThrowsWhenAPromptHasNoBuilder(): void
    {
        $registry = new PromptRegistry();
        $registry->register([
            'name' => 'orphan_prompt',
            'title' => 'Orphan',
            'description' => 'Registered without a builder',
            'arguments' => [],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Prompt 'orphan_prompt' has a definition but no builder");

        $this->makeService(prompts: $registry);
    }

    /**
     * @return RestClient&MockObject
     */
    private function createRestMock(): RestClient
    {
        return $this->createMock(RestClient::class);
    }

    private function makeService(
        ?RestClient $rest = null,
        bool $promptsEnabled = false,
        ?PromptRegistry $prompts = null,
    ): RpcService {
        $policy = $this->createMock(PolicyService::class);
        $policy->method('isToolAllowed')->willReturn(true);
        $policy->method('isReadOnly')->willReturn(false);
        $policy->method('resourcesEnabled')->willReturn(false);
        $policy->method('promptsEnabled')->willReturn($promptsEnabled);

        return new RpcService(
            $rest ?? $this->createRestMock(),
            new CacheService(new SimpleArrayCache()),
            $policy,
            $this->createMock(LoggerInterface::class),
            new ToolRegistry(),
            new SchemaValidator(),
            $prompts ?? new PromptRegistry()
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params = []): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
        ];
        if ($params !== []) {
            $request['params'] = $params;
        }

        return $request;
    }
}
