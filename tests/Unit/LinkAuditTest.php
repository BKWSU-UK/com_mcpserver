<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Tests\Unit;

defined('_JEXEC') or die;

use Joomla\Component\Mcpserver\Administrator\Service\LinkAudit;
use PHPUnit\Framework\TestCase;

class LinkAuditTest extends TestCase
{
    public function testExtractLinksFindsHrefAndSrcWithQuoteStylesDecodesAndDedupes(): void
    {
        $html = <<<'HTML'
<a href="https://example.com/a">a</a>
<img src='/images/b.jpg'>
<a href="https://example.com/a">duplicate</a>
<a href="index.php?option=com_content&amp;view=article&amp;id=5">enc</a>
HTML;

        $this->assertSame([
            'https://example.com/a',
            '/images/b.jpg',
            'index.php?option=com_content&view=article&id=5',
        ], LinkAudit::extractLinks($html));
    }

    /**
     * @dataProvider classifyMatrix
     */
    public function testClassify(string $url, string $expected): void
    {
        $this->assertSame($expected, LinkAudit::classify($url, 'https://Example.com/joomla'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function classifyMatrix(): array
    {
        return [
            'same host https' => ['https://example.com/x', 'internal'],
            'same host http' => ['http://example.com/x', 'internal'],
            'same host case' => ['https://EXAMPLE.COM/x', 'internal'],
            'external' => ['https://other.com/x', 'external'],
            'scheme-relative same host' => ['//example.com/x', 'internal'],
            'scheme-relative other host' => ['//cdn.example.net/x', 'external'],
            'root-relative' => ['/about/team', 'internal'],
            'bare index.php' => ['index.php?option=com_content&view=article&id=1', 'internal'],
            'mailto' => ['mailto:a@b.com', 'skip'],
            'tel' => ['tel:+123', 'skip'],
            'javascript' => ['javascript:void(0)', 'skip'],
            'data' => ['data:text/plain,x', 'skip'],
            'fragment' => ['#section', 'skip'],
            'empty' => ['', 'skip'],
        ];
    }

    /**
     * @dataProvider resolveMatrix
     * @param  array{status: string, target_article_id: int|null}  $expected
     */
    public function testResolve(string $url, string $basePath, array $expected): void
    {
        $articles = [5 => 1, 8 => 0];
        $aliases = ['my-article' => 5, 'draft-post' => 8];
        $menuPaths = ['about/team'];

        $this->assertSame($expected, LinkAudit::resolve($url, $basePath, $articles, $aliases, $menuPaths));
    }

    /**
     * @return array<string, array{string, string, array{status: string, target_article_id: int|null}}>
     */
    public static function resolveMatrix(): array
    {
        return [
            'query published' => [
                'index.php?option=com_content&view=article&id=5',
                '/joomla',
                ['status' => 'ok', 'target_article_id' => 5],
            ],
            'query unpublished' => [
                'index.php?option=com_content&view=article&id=8',
                '/joomla',
                ['status' => 'unpublished', 'target_article_id' => 8],
            ],
            'query missing' => [
                'index.php?option=com_content&view=article&id=99',
                '/joomla',
                ['status' => 'missing', 'target_article_id' => 99],
            ],
            'query N:alias' => [
                'index.php?option=com_content&view=article&id=5:my-article',
                '/joomla',
                ['status' => 'ok', 'target_article_id' => 5],
            ],
            'menu path with index.php prefix html suffix and subdirectory' => [
                'https://example.com/joomla/index.php/about/team.html',
                '/joomla',
                ['status' => 'ok', 'target_article_id' => null],
            ],
            'N-alias segment' => [
                '/joomla/blog/42-my-article',
                '/joomla',
                ['status' => 'ok', 'target_article_id' => 5],
            ],
            'unknown not resolvable offline' => [
                '/joomla/some/random/path',
                '/joomla',
                ['status' => 'unknown', 'target_article_id' => null],
            ],
        ];
    }
}
