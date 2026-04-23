<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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

        if ($method === 'notifications/initialized'
            || $method === 'notifications/cancelled'
            || $method === 'notifications/progress'
            || $method === 'notifications/roots/list_changed'
        ) {
            return $isNotification ? null : JsonRpc::successResponse($id, null);
        }

        if ($method === 'initialize' || $method === 'capabilities') {
            $response = $this->handleCapabilities($id);
            return $isNotification ? null : $response;
        }

        if ($method === 'ping') {
            return $isNotification ? null : JsonRpc::successResponse($id, new \stdClass());
        }

        if ($method === 'tools/list') {
            $response = $this->handleListTools($id);
            return $isNotification ? null : $response;
        }

        if ($method === 'tools/call') {
            $response = $this->handleCallTool($id, $params);
            return $isNotification ? null : $response;
        }

        if ($method === 'resources/list') {
            return $isNotification ? null : JsonRpc::successResponse($id, ['resources' => []]);
        }

        if ($method === 'resources/templates/list') {
            return $isNotification ? null : JsonRpc::successResponse($id, ['resourceTemplates' => []]);
        }

        if ($method === 'prompts/list') {
            return $isNotification ? null : JsonRpc::successResponse($id, ['prompts' => []]);
        }

        if ($method === 'logging/setLevel') {
            return $isNotification ? null : JsonRpc::successResponse($id, new \stdClass());
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
                'create_custom_module' => $this->createCustomModule($toolParams),
                'list_custom_modules' => $this->listCustomModules($toolParams),
                'get_custom_module_by_id' => $this->getCustomModuleById($toolParams),
                'update_custom_module' => $this->updateCustomModule($toolParams),
                'list_modules' => $this->listModules($toolParams),
                'get_module_by_id' => $this->getModuleById($toolParams),
                'list_menus' => $this->listMenus($toolParams),
                'list_menu_items' => $this->listMenuItems($toolParams),
                'get_menu_item' => $this->getMenuItem($toolParams),
                'create_menu_item' => $this->createMenuItem($toolParams),
                'update_menu_item' => $this->updateMenuItem($toolParams),
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
            $response = $this->rest->get('api/index.php/v1/content/articles/' . $articleId);
            return $this->injectRawArticleContent($response);
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
            $response = $this->rest->get('api/index.php/v1/content/articles', $query);
            return $this->injectRawArticleContent($response);
        });
    }

    /**
     * The Joomla web services API runs the content plugins against the response, which
     * strips/expands tags such as {loadmoduleid …}, {loadposition …} and {loadmodule …}.
     * Replace introtext/fulltext with the raw values from #__content so a read-modify-write
     * round-trip preserves these tags.
     */
    private function injectRawArticleContent(array $response): array
    {
        $ids = [];
        if (isset($response['data']['id'])) {
            $ids[] = (int) $response['data']['id'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            foreach ($response['data'] as $row) {
                if (isset($row['id'])) {
                    $ids[] = (int) $row['id'];
                }
            }
        }

        $ids = array_filter($ids, static fn ($id) => $id > 0);
        if (empty($ids)) {
            return $response;
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'introtext', 'fulltext']))
            ->from($db->quoteName('#__content'))
            ->whereIn($db->quoteName('id'), $ids);
        $rows = $db->setQuery($query)->loadAssocList('id');

        if (empty($rows)) {
            return $response;
        }

        $apply = static function (array &$item) use ($rows): void {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0 || !isset($rows[$id])) {
                return;
            }
            $item['attributes']['introtext'] = $rows[$id]['introtext'] ?? '';
            $item['attributes']['fulltext']  = $rows[$id]['fulltext'] ?? '';
        };

        if (isset($response['data']['id'])) {
            $apply($response['data']);
        } elseif (isset($response['data']) && is_array($response['data'])) {
            foreach ($response['data'] as &$row) {
                $apply($row);
            }
            unset($row);
        }

        return $response;
    }

    private function createArticle(array $params): array
    {
        $payload = $this->normaliseArticlePayload((array) ($params['article'] ?? []));
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
        $payload = $this->normaliseArticlePayload((array) ($params['article'] ?? []));
        if ($articleId <= 0 || empty($payload)) {
            throw new \InvalidArgumentException('id and article are required');
        }

        $result = $this->rest->patch('api/index.php/v1/content/articles/' . $articleId, $payload);
        $this->cache->delete('article:' . $articleId);
        $this->cache->deleteByPrefix('articles_search:');
        return $result;
    }

    /**
     * Joomla's web services API only persists article content when supplied via "introtext"
     * (and optionally "fulltext"). Map common aliases so callers can't silently bump the
     * version without changing the body.
     */
    private function normaliseArticlePayload(array $payload): array
    {
        foreach (['articletext', 'text', 'content'] as $alias) {
            if (isset($payload[$alias]) && !isset($payload['introtext'])) {
                $payload['introtext'] = $payload[$alias];
            }
            unset($payload[$alias]);
        }

        return $payload;
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

    private function createCustomModule(array $params): array
    {
        $title = $params['title'] ?? '';
        $content = $params['content'] ?? '';
        $position = $params['position'] ?? '';

        if ($title === '' || $content === '' || $position === '') {
            throw new \InvalidArgumentException('title, content and position are required');
        }

        $client = $params['client'] ?? 'site';
        $clientId = $client === 'administrator' ? 1 : 0;

        $db = Factory::getDbo();

        $module = new \stdClass();
        $module->title     = $title;
        $module->module    = 'mod_custom';
        $module->position  = $position;
        $module->published = (int) ($params['published'] ?? 1);
        $module->access    = (int) ($params['access'] ?? 1);
        $module->language  = $params['language'] ?? '*';
        $module->client_id = $clientId;
        $module->content   = $content;
        $module->params    = '{}';
        $module->showtitle = 1;
        $module->ordering  = (int) ($params['ordering'] ?? 0);
        $module->note      = $params['note'] ?? '';

        $db->insertObject('#__modules', $module, 'id');
        $moduleId = (int) $module->id;

        if ($moduleId <= 0) {
            throw new \RuntimeException('Failed to create module');
        }

        $mapping = new \stdClass();
        $mapping->moduleid = $moduleId;
        $mapping->menuid   = 0;
        $db->insertObject('#__modules_menu', $mapping);

        $this->cache->deleteByPrefix('modules_list:');
        $this->cache->deleteByPrefix('all_modules_list:');

        $path = $client === 'administrator'
            ? 'api/index.php/v1/modules/administrator/'
            : 'api/index.php/v1/modules/site/';

        return $this->rest->get($path . $moduleId);
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

        $db = Factory::getDbo();

        $module = new \stdClass();
        $module->id      = $id;
        $module->content = $content;

        $db->updateObject('#__modules', $module, 'id');

        $this->cache->delete('module:' . $client . ':' . $id);
        $this->cache->delete('modules_list:' . $client);
        $this->cache->delete('all_modules_list:' . $client);

        $path = $client === 'administrator'
            ? 'api/index.php/v1/modules/administrator/'
            : 'api/index.php/v1/modules/site/';

        return $this->rest->get($path . $id);
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

    private function createMenuItem(array $params): array
    {
        $title = $params['title'] ?? '';
        $menutype = $params['menutype'] ?? '';
        $type = $params['type'] ?? 'component';

        if ($title === '' || $menutype === '') {
            throw new \InvalidArgumentException('title and menutype are required');
        }

        $client = $params['client'] ?? 'site';
        $path = $client === 'administrator'
            ? 'api/index.php/v1/menus/administrator/items'
            : 'api/index.php/v1/menus/site/items';

        $payload = [
            'title' => $title,
            'menutype' => $menutype,
            'type' => $type,
            'parent_id' => (int) ($params['parent_id'] ?? 1),
            'published' => (int) ($params['published'] ?? 1),
            'access' => (int) ($params['access'] ?? 1),
            'language' => $params['language'] ?? '*',
            'browserNav' => (int) ($params['browserNav'] ?? 0),
            'home' => (int) ($params['home'] ?? 0),
        ];

        foreach (['link', 'alias', 'note'] as $key) {
            if (isset($params[$key])) {
                $payload[$key] = $params[$key];
            }
        }

        if (isset($params['component_id'])) {
            $payload['component_id'] = (int) $params['component_id'];
        }

        if (isset($params['params'])) {
            $payload['params'] = (object) $params['params'];
        }

        $request = isset($params['request']) ? (array) $params['request'] : $this->extractRequestFromLink($payload['link'] ?? '');
        if (!empty($request)) {
            $payload['request'] = (object) $request;
        }

        $result = $this->rest->post($path, $payload);
        $this->cache->deleteByPrefix('menu_items:');
        return $result;
    }

    private function extractRequestFromLink(string $link): array
    {
        if ($link === '' || !str_contains($link, '?')) {
            return [];
        }

        $query = parse_url($link, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return [];
        }

        $parsed = [];
        parse_str($query, $parsed);

        unset($parsed['option'], $parsed['view'], $parsed['layout'], $parsed['Itemid']);

        return $parsed;
    }

    private function updateMenuItem(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $payload = (array) ($params['menu_item'] ?? []);
        $client = $params['client'] ?? 'site';

        if ($id <= 0 || empty($payload)) {
            throw new \InvalidArgumentException('id and menu_item are required');
        }

        $path = $client === 'administrator'
            ? 'api/index.php/v1/menus/administrator/items/'
            : 'api/index.php/v1/menus/site/items/';

        // Joomla's menu item PATCH endpoint reads fields such as menutype and
        // menuordering directly from the request body without merging with the
        // stored record. Sending a partial payload (e.g. only parent_id) causes
        // the nested-set rebuild to fail with a 500. Pre-load the existing item
        // and merge the caller's changes on top to send a complete payload.
        $existing = $this->rest->get($path . $id);
        $existingAttributes = $existing['data']['attributes'] ?? [];

        $writable = [
            'title', 'alias', 'menutype', 'type', 'link', 'parent_id', 'published',
            'access', 'language', 'browserNav', 'home', 'note', 'component_id',
            'params', 'request', 'template_style_id', 'publish_up', 'publish_down',
            'menuordering',
        ];

        $merged = [];
        foreach ($writable as $field) {
            if (array_key_exists($field, $existingAttributes)) {
                $merged[$field] = $existingAttributes[$field];
            }
        }

        foreach ($payload as $key => $value) {
            $merged[$key] = $value;
        }

        if (isset($merged['params'])) {
            $merged['params'] = (object) $merged['params'];
        }

        if (isset($merged['request'])) {
            $merged['request'] = (object) $merged['request'];
        }

        $result = $this->rest->patch($path . $id, $merged);
        $this->cache->delete('menu_item:' . $client . ':' . $id);
        $this->cache->deleteByPrefix('menu_items:');
        return $result;
    }
}

