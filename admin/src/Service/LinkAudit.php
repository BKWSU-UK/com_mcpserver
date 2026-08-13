<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

class LinkAudit
{
    /**
     * @return list<string>
     */
    public static function extractLinks(string $html): array
    {
        if ($html === '') {
            return [];
        }

        if (!preg_match_all('/\b(?:href|src)\s*=\s*(["\'])(.*?)\1/i', $html, $matches)) {
            return [];
        }

        $seen = [];
        $links = [];
        foreach ($matches[2] as $raw) {
            $url = html_entity_decode(trim((string) $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $links[] = $url;
        }

        return $links;
    }

    public static function classify(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#')) {
            return 'skip';
        }

        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));
        if (in_array($scheme, ['mailto', 'tel', 'javascript', 'data'], true)) {
            return 'skip';
        }

        $host = parse_url($url, \PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return 'internal';
        }

        $baseHost = parse_url($baseUrl, \PHP_URL_HOST);
        if (is_string($baseHost) && $baseHost !== '' && strcasecmp($host, $baseHost) === 0) {
            return 'internal';
        }

        return 'external';
    }

    /**
     * @param  array<int, int>  $articles  Article id → publication state
     * @param  array<string, int>  $aliases  Lowercased alias → article id
     * @param  list<string>  $menuPaths  Normalised menu paths (no leading/trailing slash)
     * @return array{status: string, target_article_id: int|null}
     */
    public static function resolve(
        string $url,
        string $basePath,
        array $articles,
        array $aliases,
        array $menuPaths
    ): array {
        $parts = parse_url($url);
        $query = [];
        if (is_array($parts) && isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if (
            ($query['option'] ?? '') === 'com_content'
            && ($query['view'] ?? '') === 'article'
            && isset($query['id'])
        ) {
            $id = (int) explode(':', (string) $query['id'], 2)[0];

            return self::statusForArticleId($id, $articles);
        }

        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        $path = self::normalisePath($path, $basePath);

        if ($path !== '') {
            $menuSet = array_fill_keys($menuPaths, true);
            if (isset($menuSet[strtolower($path)])) {
                return ['status' => 'ok', 'target_article_id' => null];
            }

            $lastSegment = strtolower((string) basename($path));
            if (isset($aliases[$lastSegment])) {
                return self::statusForArticleId($aliases[$lastSegment], $articles);
            }

            $stripped = preg_replace('/^\d+-/', '', $lastSegment) ?? $lastSegment;
            if ($stripped !== $lastSegment && isset($aliases[$stripped])) {
                return self::statusForArticleId($aliases[$stripped], $articles);
            }
        }

        return ['status' => 'unknown', 'target_article_id' => null];
    }

    /**
     * @param  array<int, int>  $articles
     * @return array{status: string, target_article_id: int|null}
     */
    private static function statusForArticleId(int $id, array $articles): array
    {
        if ($id <= 0) {
            return ['status' => 'unknown', 'target_article_id' => null];
        }

        if (!array_key_exists($id, $articles)) {
            return ['status' => 'missing', 'target_article_id' => $id];
        }

        $state = (int) $articles[$id];
        if ($state === 1) {
            return ['status' => 'ok', 'target_article_id' => $id];
        }

        return ['status' => 'unpublished', 'target_article_id' => $id];
    }

    private static function normalisePath(string $path, string $basePath): string
    {
        $basePath = '/' . trim($basePath, '/');
        if ($basePath !== '/' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath)) ?: '';
        }

        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#^/index\.php(?:/|$)#i', '/', $path) ?? $path;
        $path = preg_replace('/\.html$/i', '', $path) ?? $path;

        return trim($path, '/');
    }
}
