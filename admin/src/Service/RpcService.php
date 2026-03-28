<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Version as JoomlaVersion;
use Psr\Log\LoggerInterface;

class RpcService
{
    private RestClient $rest;
    private CacheService $cache;
    private PolicyService $policy;
    private LoggerInterface $logger;
    private ToolRegistry $toolRegistry;
    private SchemaValidator $validator;
    private string $serverName;

    public function __construct(
        RestClient $rest,
        CacheService $cache,
        PolicyService $policy,
        LoggerInterface $logger,
        ToolRegistry $toolRegistry,
        SchemaValidator $validator,
        string $serverName = 'joomla-mcp-server'
    ) {
        $this->rest = $rest;
        $this->cache = $cache;
        $this->policy = $policy;
        $this->logger = $logger;
        $this->toolRegistry = $toolRegistry;
        $this->validator = $validator;
        $this->serverName = $serverName;
    }

    public function handle(array $request): ?array
    {
        $id = $request['id'] ?? null;
        $isNotification = !array_key_exists('id', $request);
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];

        $this->logger->info('Handling RPC request', [
            'method' => $method,
            'has_id' => !$isNotification,
            'server' => $this->serverName
        ]);

        if ($method === 'notifications/initialized') {
            return $isNotification ? null : JsonRpc::successResponse($id, null);
        }

        if ($method === 'initialize' || $method === 'capabilities') {
            $response = $this->handleCapabilities($id);
            return $isNotification ? null : $response;
        }

        if ($method === 'tools/list') {
            $response = $this->handleListTools($id);
            return $isNotification ? null : $response;
        }

        if ($method === 'tools/call') {
            $response = $this->handleCallTool($id, $params);
            return $isNotification ? null : $response;
        }

        if ($method === 'site_health') {
            $version = new JoomlaVersion();
            $response = JsonRpc::successResponse($id, [
                'status' => 'ok',
                'joomla_version' => $version->getShortVersion(),
                'timestamp' => (new \DateTimeImmutable('now'))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM),
            ]);
            return $isNotification ? null : $response;
        }

        $response = JsonRpc::errorResponse($id, JsonRpc::METHOD_NOT_FOUND, 'Requested method not implemented');
        return $isNotification ? null : $response;
    }

    private function handleCapabilities(mixed $id): array
    {
        return JsonRpc::successResponse($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => $this->serverName,
                'version' => '0.4.0',
            ],
        ]);
    }

    private function handleListTools(mixed $id): array
    {
        $tools = $this->toolRegistry->getAll();
        $this->logger->info('listTools: Found ' . count($tools) . ' tools', ['server' => $this->serverName]);
        return JsonRpc::successResponse($id, ['tools' => $tools]);
    }

    private function handleCallTool(mixed $id, array $params): array
    {
        $toolName = $params['name'] ?? '';
        $toolParams = $params['arguments'] ?? [];

        if (empty($toolName)) {
            return JsonRpc::errorResponse($id, JsonRpc::INVALID_PARAMS, 'Tool name is required');
        }

        if (!$this->policy->isToolAllowed($toolName)) {
            return JsonRpc::errorResponse($id, JsonRpc::FORBIDDEN, 'Tool not allowed');
        }

        $tool = $this->toolRegistry->get($toolName);
        if ($tool === null) {
            return JsonRpc::errorResponse($id, JsonRpc::METHOD_NOT_FOUND, 'Tool not found');
        }

        if (isset($tool['inputSchema'])) {
            $validationError = $this->validator->validate($toolParams, $tool['inputSchema']);
            if ($validationError !== null) {
                return JsonRpc::errorResponse($id, JsonRpc::INVALID_PARAMS, 'Invalid parameters: ' . $validationError);
            }
        }

        try {
            $result = match ($toolName) {
                'get_article_by_id' => $this->getArticleById($toolParams),
                'search_articles' => $this->searchArticles($toolParams),
                'create_article' => $this->createArticle($toolParams),
                'update_article' => $this->updateArticle($toolParams),
                'delete_article' => $this->deleteArticle($toolParams),
                'list_custom_modules' => $this->listCustomModules($toolParams),
                'get_custom_module_by_id' => $this->getCustomModuleById($toolParams),
                'update_custom_module' => $this->updateCustomModule($toolParams),
                'list_modules' => $this->listModules($toolParams),
                'get_module_by_id' => $this->getModuleById($toolParams),
                'list_menus' => $this->listMenus($toolParams),
                'list_menu_items' => $this->listMenuItems($toolParams),
                'get_menu_item' => $this->getMenuItem($toolParams),
                default => throw new \RuntimeException('Tool not found'),
            };

            return JsonRpc::successResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_PRETTY_PRINT),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Tool execution failed', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return JsonRpc::errorResponse($id, JsonRpc::INTERNAL_ERROR, $e->getMessage());
        }
    }

    private function getArticleById(array $params): array
    {
        $articleId = (int) ($params['id'] ?? 0);
        if ($articleId <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $cacheKey = 'article:' . $articleId;
        return $this->cache->remember($cacheKey, function () use ($articleId) {
            return $this->rest->get('api/index.php/v1/content/articles/' . $articleId);
        });
    }

    private function searchArticles(array $params): array
    {
        $query = [];
        foreach (['search', 'language', 'catid', 'state', 'author', 'limit', 'offset'] as $key) {
            if (isset($params[$key])) {
                $query[$key] = $params[$key];
            }
        }

        $cacheKey = 'articles_search:' . md5(json_encode($query));
        return $this->cache->remember($cacheKey, function () use ($query) {
            return $this->rest->get('api/index.php/v1/content/articles', $query);
        });
    }

    private function createArticle(array $params): array
    {
        $payload = (array) ($params['article'] ?? []);
        if (empty($payload)) {
            throw new \InvalidArgumentException('article object is required');
        }

        if (!isset($payload['language'])) {
            $payload['language'] = '*';
        }

        $result = $this->rest->post('api/index.php/v1/content/articles', $payload);
        $this->cache->deleteByPrefix('articles_search:');
        return $result;
    }

    private function updateArticle(array $params): array
    {
        $articleId = (int) ($params['id'] ?? 0);
        $payload = (array) ($params['article'] ?? []);
        if ($articleId <= 0 || empty($payload)) {
            throw new \InvalidArgumentException('id and article are required');
        }

        $result = $this->rest->patch('api/index.php/v1/content/articles/' . $articleId, $payload);
        $this->cache->delete('article:' . $articleId);
        $this->cache->deleteByPrefix('articles_search:');
        return $result;
    }

    private function deleteArticle(array $params): array
    {
        $articleId = (int) ($params['id'] ?? 0);
        if ($articleId <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $result = $this->rest->delete('api/index.php/v1/content/articles/' . $articleId);
        $this->cache->delete('article:' . $articleId);
        $this->cache->deleteByPrefix('articles_search:');
        return $result;
    }

    private function listCustomModules(array $params): array
    {
        $client = $params['client'] ?? 'site';
        $path = $client === 'administrator' ? 'api/index.php/v1/modules/administrator' : 'api/index.php/v1/modules/site';
        
        $cacheKey = 'modules_list:' . $client;
        $modules = $this->cache->remember($cacheKey, function () use ($path) {
            return $this->rest->get($path);
        });

        // Filter for mod_custom
        if (isset($modules['data'])) {
            $modules['data'] = array_values(array_filter($modules['data'], function ($item) {
                return ($item['attributes']['module'] ?? '') === 'mod_custom';
            }));
        }

        return $modules;
    }

    private function getCustomModuleById(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $client = $params['client'] ?? 'site';
        
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $path = $client === 'administrator' ? 'api/index.php/v1/modules/administrator/' : 'api/index.php/v1/modules/site/';
        $cacheKey = 'module:' . $client . ':' . $id;

        return $this->cache->remember($cacheKey, function () use ($path, $id) {
            return $this->rest->get($path . $id);
        });
    }

    private function updateCustomModule(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $content = $params['content'] ?? null;
        $client = $params['client'] ?? 'site';

        if ($id <= 0 || $content === null) {
            throw new \InvalidArgumentException('id and content are required');
        }

        $path = $client === 'administrator' ? 'api/index.php/v1/modules/administrator/' : 'api/index.php/v1/modules/site/';
        
        // In Joomla REST API, we need to update params['custom_content'] for mod_custom
        $payload = [
            'params' => [
                'custom_content' => $content
            ]
        ];

        $result = $this->rest->patch($path . $id, $payload);

        $this->cache->delete('module:' . $client . ':' . $id);
        $this->cache->delete('modules_list:' . $client);

        return $result;
    }

    private function listModules(array $params): array
    {
        $client = $params['client'] ?? 'site';
        $path = $client === 'administrator'
            ? 'api/index.php/v1/modules/administrator'
            : 'api/index.php/v1/modules/site';

        $cacheKey = 'all_modules_list:' . $client;
        return $this->cache->remember($cacheKey, function () use ($path) {
            return $this->rest->get($path);
        });
    }

    private function getModuleById(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $client = $params['client'] ?? 'site';

        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $path = $client === 'administrator'
            ? 'api/index.php/v1/modules/administrator/'
            : 'api/index.php/v1/modules/site/';

        $cacheKey = 'module:' . $client . ':' . $id;
        return $this->cache->remember($cacheKey, function () use ($path, $id) {
            return $this->rest->get($path . $id);
        });
    }

    private function listMenus(array $params): array
    {
        $client = $params['client'] ?? 'site';
        $path = $client === 'administrator'
            ? 'api/index.php/v1/menus/administrator'
            : 'api/index.php/v1/menus/site';

        $cacheKey = 'menus_list:' . $client;
        return $this->cache->remember($cacheKey, function () use ($path) {
            return $this->rest->get($path);
        });
    }

    private function listMenuItems(array $params): array
    {
        $client = $params['client'] ?? 'site';
        $path = $client === 'administrator'
            ? 'api/index.php/v1/menus/administrator/items'
            : 'api/index.php/v1/menus/site/items';

        $query = [];
        foreach (['menutype', 'limit', 'offset'] as $key) {
            if (isset($params[$key])) {
                $query[$key] = $params[$key];
            }
        }

        $cacheKey = 'menu_items:' . $client . ':' . md5(json_encode($query));
        return $this->cache->remember($cacheKey, function () use ($path, $query) {
            return $this->rest->get($path, $query);
        });
    }

    private function getMenuItem(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $client = $params['client'] ?? 'site';

        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $path = $client === 'administrator'
            ? 'api/index.php/v1/menus/administrator/items/'
            : 'api/index.php/v1/menus/site/items/';

        $cacheKey = 'menu_item:' . $client . ':' . $id;
        return $this->cache->remember($cacheKey, function () use ($path, $id) {
            return $this->rest->get($path . $id);
        });
    }
}

