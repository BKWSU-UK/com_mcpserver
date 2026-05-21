<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Version as JoomlaVersion;
use Psr\Log\LoggerInterface;

class RpcService
{
    private const SUPPORTED_PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    private static ?string $cachedVersion = null;

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

        $this->registerToolExecutors();
    }

    private function registerToolExecutors(): void
    {
        $executors = [
            'get_article_by_id'       => fn(array $p) => $this->getArticleById($p),
            'search_articles'         => fn(array $p) => $this->searchArticles($p),
            'create_article'          => fn(array $p) => $this->createArticle($p),
            'update_article'          => fn(array $p) => $this->updateArticle($p),
            'delete_article'          => fn(array $p) => $this->deleteArticle($p),
            'create_custom_module'    => fn(array $p) => $this->createCustomModule($p),
            'list_custom_modules'     => fn(array $p) => $this->listCustomModules($p),
            'get_custom_module_by_id' => fn(array $p) => $this->getCustomModuleById($p),
            'update_custom_module'    => fn(array $p) => $this->updateCustomModule($p),
            'list_modules'            => fn(array $p) => $this->listModules($p),
            'get_module_by_id'        => fn(array $p) => $this->getModuleById($p),
            'list_menus'              => fn(array $p) => $this->listMenus($p),
            'list_menu_items'         => fn(array $p) => $this->listMenuItems($p),
            'get_menu_item'           => fn(array $p) => $this->getMenuItem($p),
            'create_menu_item'        => fn(array $p) => $this->createMenuItem($p),
            'update_menu_item'        => fn(array $p) => $this->updateMenuItem($p),
            'list_media'              => fn(array $p) => $this->listMedia($p),
            'get_media'               => fn(array $p) => $this->getMedia($p),
            'upload_media'            => fn(array $p) => $this->uploadMedia($p),
            'create_media_folder'     => fn(array $p) => $this->createMediaFolder($p),
            'update_media'            => fn(array $p) => $this->updateMedia($p),
            'delete_media'            => fn(array $p) => $this->deleteMedia($p),
            'list_content_languages'        => fn(array $p) => $this->listContentLanguages($p),
            'get_content_language'          => fn(array $p) => $this->getContentLanguage($p),
            'create_content_language'       => fn(array $p) => $this->createContentLanguage($p),
            'update_content_language'       => fn(array $p) => $this->updateContentLanguage($p),
            'delete_content_language'       => fn(array $p) => $this->deleteContentLanguage($p),
            'list_installed_languages'      => fn(array $p) => $this->listInstalledLanguages($p),
            'list_article_associations'     => fn(array $p) => $this->listArticleAssociations($p),
            'set_article_associations'      => fn(array $p) => $this->setArticleAssociations($p),
            'list_menu_item_associations'   => fn(array $p) => $this->listMenuItemAssociations($p),
            'set_menu_item_associations'    => fn(array $p) => $this->setMenuItemAssociations($p),
        ];

        foreach ($executors as $name => $executor) {
            $this->toolRegistry->setExecutor($name, $executor);
        }

        foreach ($this->toolRegistry->getAll() as $tool) {
            if (!$this->toolRegistry->hasExecutor($tool['name'])) {
                throw new \LogicException("Tool '{$tool['name']}' has a schema but no executor");
            }
        }
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
            $response = $this->handleCapabilities($id, $params);
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

    private function handleCapabilities(mixed $id, array $params = []): array
    {
        $clientVersion = $params['protocolVersion'] ?? null;
        $negotiatedVersion = is_string($clientVersion) && in_array($clientVersion, self::SUPPORTED_PROTOCOL_VERSIONS, true)
            ? $clientVersion
            : self::SUPPORTED_PROTOCOL_VERSIONS[0];

        return JsonRpc::successResponse($id, [
            'protocolVersion' => $negotiatedVersion,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => $this->serverName,
                'version' => $this->getComponentVersion(),
            ],
        ]);
    }

    private function getComponentVersion(): string
    {
        if (self::$cachedVersion !== null) {
            return self::$cachedVersion;
        }

        $manifestPath = JPATH_ADMINISTRATOR . '/components/com_mcpserver/mcpserver.xml';

        if (is_file($manifestPath)) {
            $xml = @simplexml_load_file($manifestPath);
            if ($xml !== false && isset($xml->version)) {
                $version = trim((string) $xml->version);
                if ($version !== '') {
                    self::$cachedVersion = $version;
                    return self::$cachedVersion;
                }
            }
        }

        self::$cachedVersion = 'unknown';
        return self::$cachedVersion;
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
            $result = $this->toolRegistry->execute($toolName, $toolParams);

            return JsonRpc::successResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_PRETTY_PRINT),
                    ],
                ],
                'structuredContent' => $result,
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

    private function listMedia(array $params): array
    {
        $query = [];
        if (isset($params['path']) && $params['path'] !== '') {
            $query['path'] = (string) $params['path'];
        }
        if (isset($params['search']) && $params['search'] !== '') {
            $query['search'] = (string) $params['search'];
        }
        if (($params['include_url'] ?? true)) {
            $query['url'] = 1;
        }
        if (!empty($params['include_temp'])) {
            $query['temp'] = 1;
        }

        $cacheKey = 'media_list:' . md5(json_encode($query));
        return $this->cache->remember($cacheKey, function () use ($query) {
            return $this->rest->get('api/index.php/v1/media/files', $query);
        });
    }

    private function getMedia(array $params): array
    {
        $path = (string) ($params['path'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('path is required');
        }

        $query = [];
        if (($params['include_url'] ?? true)) {
            $query['url'] = 1;
        }
        if (!empty($params['include_content'])) {
            $query['content'] = 1;
        }

        $cacheKey = 'media_item:' . md5($path . '|' . json_encode($query));
        return $this->cache->remember($cacheKey, function () use ($path, $query) {
            return $this->rest->get('api/index.php/v1/media/files/' . $this->mediaItemPath($path), $query);
        });
    }

    private function uploadMedia(array $params): array
    {
        $path = (string) ($params['path'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('path is required');
        }

        $content = $this->resolveMediaContent($params);
        if ($content === null) {
            throw new \InvalidArgumentException('Either content or source_url is required');
        }

        $result = $this->rest->post('api/index.php/v1/media/files', [
            'path' => $path,
            'content' => $content,
        ]);
        $this->cache->deleteByPrefix('media_');
        return $result;
    }

    private function createMediaFolder(array $params): array
    {
        $path = (string) ($params['path'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('path is required');
        }

        $result = $this->rest->post('api/index.php/v1/media/files', [
            'path' => $path,
        ]);
        $this->cache->deleteByPrefix('media_');
        return $result;
    }

    private function updateMedia(array $params): array
    {
        $path = (string) ($params['path'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('path is required');
        }

        $payload = [];
        if (isset($params['new_path']) && $params['new_path'] !== '') {
            $payload['path'] = (string) $params['new_path'];
        }

        $content = $this->resolveMediaContent($params);
        if ($content !== null) {
            $payload['content'] = $content;
        }

        if (empty($payload)) {
            throw new \InvalidArgumentException('Provide at least one of new_path, content or source_url');
        }

        $result = $this->rest->patch('api/index.php/v1/media/files/' . $this->mediaItemPath($path), $payload);
        $this->cache->deleteByPrefix('media_');
        return $result;
    }

    private function deleteMedia(array $params): array
    {
        $path = (string) ($params['path'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('path is required');
        }

        $result = $this->rest->delete('api/index.php/v1/media/files/' . $this->mediaItemPath($path));
        $this->cache->deleteByPrefix('media_');
        return $result;
    }

    private function resolveMediaContent(array $params): ?string
    {
        $hasContent = isset($params['content']) && $params['content'] !== '';
        $hasUrl = isset($params['source_url']) && $params['source_url'] !== '';

        if ($hasContent && $hasUrl) {
            throw new \InvalidArgumentException('Provide either content or source_url, not both');
        }

        if ($hasContent) {
            return (string) $params['content'];
        }

        if ($hasUrl) {
            return base64_encode($this->rest->fetchUrlContent((string) $params['source_url']));
        }

        return null;
    }

    private function mediaItemPath(string $path): string
    {
        $adapter = '';
        if (preg_match('/^([A-Za-z0-9_\-]+:)(.*)$/', $path, $matches)) {
            $adapter = $matches[1];
            $path = $matches[2];
        }

        $segments = array_map(
            static fn ($segment) => rawurlencode($segment),
            explode('/', $path)
        );

        return $adapter . implode('/', $segments);
    }

    private const CONTENT_LANGUAGES_PATH = 'api/index.php/v1/languages';
    private const ASSOC_CONTEXT_ARTICLE = 'com_content.item';
    private const ASSOC_CONTEXT_MENU_ITEM = 'com_menus.item';

    private function listContentLanguages(array $params): array
    {
        $query = [];
        if (isset($params['published'])) {
            $query['filter[published]'] = (int) $params['published'];
        }

        $cacheKey = 'content_languages:' . md5(json_encode($query));
        return $this->cache->remember($cacheKey, fn () => $this->rest->get(self::CONTENT_LANGUAGES_PATH, $query));
    }

    private function getContentLanguage(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $cacheKey = 'content_language:' . $id;
        return $this->cache->remember($cacheKey, fn () => $this->rest->get(self::CONTENT_LANGUAGES_PATH . '/' . $id));
    }

    private function createContentLanguage(array $params): array
    {
        $payload = $this->buildContentLanguagePayload($params, true);
        $result = $this->rest->post(self::CONTENT_LANGUAGES_PATH, $payload);
        $this->cache->deleteByPrefix('content_languages:');
        return $result;
    }

    private function updateContentLanguage(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $payload = $this->buildContentLanguagePayload((array) ($params['language'] ?? []), false);
        if ($id <= 0 || empty($payload)) {
            throw new \InvalidArgumentException('id and language are required');
        }

        $result = $this->rest->patch(self::CONTENT_LANGUAGES_PATH . '/' . $id, $payload);
        $this->cache->delete('content_language:' . $id);
        $this->cache->deleteByPrefix('content_languages:');
        return $result;
    }

    private function deleteContentLanguage(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $result = $this->rest->delete(self::CONTENT_LANGUAGES_PATH . '/' . $id);
        $this->cache->delete('content_language:' . $id);
        $this->cache->deleteByPrefix('content_languages:');
        return $result;
    }

    private function buildContentLanguagePayload(array $input, bool $requireCore): array
    {
        $allowed = [
            'lang_code', 'title', 'title_native', 'sef', 'image', 'description',
            'metakey', 'metadesc', 'sitename', 'published', 'access', 'ordering',
        ];

        $payload = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = $input[$field];
            }
        }

        if ($requireCore) {
            foreach (['lang_code', 'title', 'title_native', 'sef'] as $required) {
                if (!isset($payload[$required]) || $payload[$required] === '') {
                    throw new \InvalidArgumentException($required . ' is required');
                }
            }
            $payload['published'] = (int) ($payload['published'] ?? 1);
            $payload['access']    = (int) ($payload['access'] ?? 1);
        }

        return $payload;
    }

    private function listInstalledLanguages(array $params): array
    {
        $clientFilter = $params['client'] ?? null;
        $cacheKey = 'installed_languages:' . ($clientFilter ?? 'all');

        return $this->cache->remember($cacheKey, function () use ($clientFilter) {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'name', 'element', 'client_id', 'enabled']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('language'))
                ->order($db->quoteName('client_id') . ' ASC, ' . $db->quoteName('element') . ' ASC');

            if ($clientFilter === 'site') {
                $query->where($db->quoteName('client_id') . ' = 0');
            } elseif ($clientFilter === 'administrator') {
                $query->where($db->quoteName('client_id') . ' = 1');
            }

            $rows = $db->setQuery($query)->loadAssocList() ?: [];

            $data = [];
            foreach ($rows as $row) {
                $clientId = (int) $row['client_id'];
                $data[] = [
                    'extension_id' => (int) $row['extension_id'],
                    'name'         => (string) $row['name'],
                    'tag'          => (string) $row['element'],
                    'client_id'    => $clientId,
                    'client'       => $clientId === 0 ? 'site' : 'administrator',
                    'enabled'      => (int) $row['enabled'] === 1,
                ];
            }

            return [
                'data' => $data,
                'meta' => [
                    'application_default' => (string) Factory::getConfig()->get('language', 'en-GB'),
                ],
            ];
        });
    }

    private function listArticleAssociations(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }
        return $this->loadAssociations(self::ASSOC_CONTEXT_ARTICLE, $id);
    }

    private function setArticleAssociations(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }
        if (!array_key_exists('associated_ids', $params) || !is_array($params['associated_ids'])) {
            throw new \InvalidArgumentException('associated_ids is required');
        }
        $associatedIds = array_map('intval', $params['associated_ids']);

        return $this->saveAssociations(self::ASSOC_CONTEXT_ARTICLE, $id, $associatedIds);
    }

    private function listMenuItemAssociations(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }
        return $this->loadAssociations(self::ASSOC_CONTEXT_MENU_ITEM, $id);
    }

    private function setMenuItemAssociations(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }
        if (!array_key_exists('associated_ids', $params) || !is_array($params['associated_ids'])) {
            throw new \InvalidArgumentException('associated_ids is required');
        }
        $associatedIds = array_map('intval', $params['associated_ids']);

        return $this->saveAssociations(self::ASSOC_CONTEXT_MENU_ITEM, $id, $associatedIds);
    }

    private function loadAssociations(string $context, int $primaryId): array
    {
        $cacheKey = $this->associationCacheKey($context, $primaryId);
        return $this->cache->remember($cacheKey, function () use ($context, $primaryId) {
            $db = Factory::getDbo();

            $query = $db->getQuery(true)
                ->select($db->quoteName('key'))
                ->from($db->quoteName('#__associations'))
                ->where($db->quoteName('context') . ' = ' . $db->quote($context))
                ->where($db->quoteName('id') . ' = ' . (int) $primaryId);
            $key = $db->setQuery($query)->loadResult();

            if (!$key) {
                return [
                    'data' => [],
                    'meta' => ['key' => null, 'context' => $context, 'id' => $primaryId],
                ];
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__associations'))
                ->where($db->quoteName('context') . ' = ' . $db->quote($context))
                ->where($db->quoteName('key') . ' = ' . $db->quote($key));
            $allIds = array_map('intval', $db->setQuery($query)->loadColumn() ?: []);

            $items = $this->loadItemsForAssociation($context, $allIds);

            return [
                'data' => $items,
                'meta' => ['key' => $key, 'context' => $context, 'id' => $primaryId],
            ];
        });
    }

    private function saveAssociations(string $context, int $primaryId, array $associatedIds): array
    {
        $allIds = array_values(array_unique(array_map('intval', array_merge([$primaryId], $associatedIds))));

        if (count($allIds) === 1) {
            // Caller wants to clear associations; just remove rows for the primary.
            $this->deleteAssociationsForIds($context, $allIds);
            $this->cache->deleteByPrefix($this->associationCachePrefix($context));
            return $this->loadAssociations($context, $primaryId);
        }

        $items = $this->loadItemsForAssociation($context, $allIds);
        $byId = [];
        foreach ($items as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $assocMap = [];
        foreach ($allIds as $itemId) {
            if (!isset($byId[$itemId])) {
                throw new \InvalidArgumentException("Item with id {$itemId} not found for context '{$context}'");
            }
            $lang = (string) ($byId[$itemId]['language'] ?? '');
            if ($lang === '' || $lang === '*') {
                throw new \InvalidArgumentException(
                    "Item id {$itemId} has language '" . ($lang === '' ? '' : $lang)
                    . "'; associations require a specific language tag"
                );
            }
            if (isset($assocMap[$lang])) {
                throw new \InvalidArgumentException(
                    "Language conflict: items {$assocMap[$lang]} and {$itemId} both have language '{$lang}'."
                    . ' Associations require one item per language.'
                );
            }
            $assocMap[$lang] = $itemId;
        }

        $db = Factory::getDbo();
        $this->deleteAssociationsForIds($context, $allIds);

        $key = md5(json_encode($assocMap));
        foreach ($assocMap as $itemId) {
            $row = new \stdClass();
            $row->id      = (int) $itemId;
            $row->context = $context;
            $row->key     = $key;
            $db->insertObject('#__associations', $row);
        }

        $this->cache->deleteByPrefix($this->associationCachePrefix($context));
        return $this->loadAssociations($context, $primaryId);
    }

    private function loadItemsForAssociation(string $context, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $db = Factory::getDbo();

        if ($context === self::ASSOC_CONTEXT_ARTICLE) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias', 'language', 'catid', 'state']))
                ->from($db->quoteName('#__content'))
                ->whereIn($db->quoteName('id'), $ids);
        } elseif ($context === self::ASSOC_CONTEXT_MENU_ITEM) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias', 'language', 'menutype', 'client_id', 'published']))
                ->from($db->quoteName('#__menu'))
                ->whereIn($db->quoteName('id'), $ids);
        } else {
            throw new \InvalidArgumentException("Unsupported association context '{$context}'");
        }

        $rows = $db->setQuery($query)->loadAssocList() ?: [];

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            if (isset($row['client_id'])) {
                $row['client_id'] = (int) $row['client_id'];
            }
            return $row;
        }, $rows);
    }

    private function deleteAssociationsForIds(string $context, array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__associations'))
            ->where($db->quoteName('context') . ' = ' . $db->quote($context))
            ->whereIn($db->quoteName('id'), $ids);
        $db->setQuery($query)->execute();
    }

    private function associationCacheKey(string $context, int $id): string
    {
        return $this->associationCachePrefix($context) . $id;
    }

    private function associationCachePrefix(string $context): string
    {
        if ($context === self::ASSOC_CONTEXT_ARTICLE) {
            return 'article_associations:';
        }
        if ($context === self::ASSOC_CONTEXT_MENU_ITEM) {
            return 'menu_item_associations:';
        }
        return 'associations:' . $context . ':';
    }
}

