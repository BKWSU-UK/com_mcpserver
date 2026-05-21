<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

class ToolRegistry
{
    private array $tools = [];
    private array $executors = [];

    public function __construct()
    {
        $this->registerDefaultTools();
    }

    private function registerDefaultTools(): void
    {
        $this->register([
            'name' => 'get_article_by_id',
            'description' => 'Retrieve a Joomla article by ID',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'description' => 'Article ID',
                    ],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'Get Article',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'search_articles',
            'description' => 'Search Joomla articles',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Search term'],
                    'language' => ['type' => 'string', 'description' => 'Language code'],
                    'catid' => ['type' => 'integer', 'description' => 'Category ID'],
                    'state' => ['type' => 'integer', 'description' => 'Publication state'],
                    'author' => ['type' => 'string', 'description' => 'Author name'],
                    'limit' => ['type' => 'integer', 'description' => 'Results limit'],
                    'offset' => ['type' => 'integer', 'description' => 'Results offset'],
                ],
            ],
            'annotations' => [
                'title' => 'Search Articles',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'create_article',
            'description' => 'Create a new Joomla article',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'article' => [
                        'type' => 'object',
                        'description' => 'Article data',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'alias' => ['type' => 'string'],
                            'introtext' => ['type' => 'string', 'description' => 'Article intro text (HTML). This is the field Joomla persists; do not use "articletext" or "text".'],
                            'fulltext' => ['type' => 'string', 'description' => 'Article full text (HTML), shown after the read-more break'],
                            'catid' => ['type' => 'integer'],
                            'language' => ['type' => 'string'],
                            'state' => ['type' => 'integer'],
                            'version_note' => ['type' => 'string', 'description' => 'Optional note stored with this version in content history'],
                        ],
                        'required' => ['title', 'catid', 'introtext'],
                    ],
                ],
                'required' => ['article'],
            ],
            'annotations' => [
                'title' => 'Create Article',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => false,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'update_article',
            'description' => 'Update an existing Joomla article',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Article ID'],
                    'article' => [
                        'type' => 'object',
                        'description' => 'Article fields to update. Use "introtext" (and optionally "fulltext") for content; "articletext" and "text" are not persisted by the Joomla API. Optional "version_note" is stored in content history when versioning is enabled.',
                    ],
                ],
                'required' => ['id', 'article'],
            ],
            'annotations' => [
                'title' => 'Update Article',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'delete_article',
            'description' => 'Delete a Joomla article',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Article ID'],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'Delete Article',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_article_versions',
            'description' => 'List saved versions (content history) for a Joomla article. Requires article versioning to be enabled in Joomla.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Article ID'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum number of versions to return'],
                    'offset' => ['type' => 'integer', 'description' => 'Number of versions to skip'],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'List Article Versions',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'get_article_version',
            'description' => 'Retrieve a single article version from content history, including the full version_data snapshot.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'version_id' => ['type' => 'integer', 'description' => 'Content history version ID'],
                ],
                'required' => ['version_id'],
            ],
            'annotations' => [
                'title' => 'Get Article Version',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'keep_article_version',
            'description' => 'Toggle the "keep forever" flag on an article version so Joomla will not prune it automatically.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'version_id' => ['type' => 'integer', 'description' => 'Content history version ID'],
                ],
                'required' => ['version_id'],
            ],
            'annotations' => [
                'title' => 'Keep Article Version',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'delete_article_version',
            'description' => 'Delete a single article version from content history. Versions marked "keep forever" cannot be deleted.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'version_id' => ['type' => 'integer', 'description' => 'Content history version ID'],
                ],
                'required' => ['version_id'],
            ],
            'annotations' => [
                'title' => 'Delete Article Version',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'restore_article_version',
            'description' => 'Restore a Joomla article to a previous saved version from content history. Creates a new version for the current state before applying the restored content.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Article ID to restore'],
                    'version_id' => ['type' => 'integer', 'description' => 'Content history version ID to restore from'],
                    'version_note' => ['type' => 'string', 'description' => 'Optional note for the version created by this restore'],
                ],
                'required' => ['id', 'version_id'],
            ],
            'annotations' => [
                'title' => 'Restore Article Version',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => false,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'create_custom_module',
            'description' => 'Create a new Joomla "Custom" (mod_custom) module',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Module title'],
                    'content' => ['type' => 'string', 'description' => 'HTML content for the module'],
                    'position' => ['type' => 'string', 'description' => 'Template position (e.g. "sidebar-right")'],
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                        'description' => 'Create as a site or administrator module',
                    ],
                    'published' => [
                        'type' => 'integer',
                        'enum' => [0, 1],
                        'default' => 1,
                        'description' => 'Published state (0 = unpublished, 1 = published)',
                    ],
                    'access' => [
                        'type' => 'integer',
                        'default' => 1,
                        'description' => 'Access level ID (1 = Public, 2 = Registered, etc.)',
                    ],
                    'language' => [
                        'type' => 'string',
                        'default' => '*',
                        'description' => 'Language code (e.g. "en-GB") or "*" for all',
                    ],
                    'note' => ['type' => 'string', 'description' => 'Optional admin note'],
                    'ordering' => ['type' => 'integer', 'description' => 'Module ordering within the position'],
                ],
                'required' => ['title', 'content', 'position'],
            ],
            'annotations' => [
                'title' => 'Create Custom Module',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => false,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_custom_modules',
            'description' => 'List all Joomla "Custom" (mod_custom) modules',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                        'description' => 'List site or administrator modules',
                    ],
                ],
            ],
            'annotations' => [
                'title' => 'List Custom Modules',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'get_custom_module_by_id',
            'description' => 'Retrieve a Joomla "Custom" module by ID',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Module ID'],
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                    ],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'Get Custom Module',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'update_custom_module',
            'description' => 'Update the content of a Joomla "Custom" module',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Module ID'],
                    'content' => ['type' => 'string', 'description' => 'The HTML content for the module'],
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                    ],
                ],
                'required' => ['id', 'content'],
            ],
            'annotations' => [
                'title' => 'Update Custom Module',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_menus',
            'description' => 'List all Joomla menus (menu types)',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                        'description' => 'List site or administrator menus',
                    ],
                ],
            ],
            'annotations' => [
                'title' => 'List Menus',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_menu_items',
            'description' => 'List menu items, optionally filtered by menu type',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'menutype' => ['type' => 'string', 'description' => 'Menu type alias to filter by (e.g. "mainmenu")'],
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                        'description' => 'List site or administrator menu items',
                    ],
                    'limit' => ['type' => 'integer', 'description' => 'Results limit'],
                    'offset' => ['type' => 'integer', 'description' => 'Results offset'],
                ],
            ],
            'annotations' => [
                'title' => 'List Menu Items',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_modules',
            'description' => 'List all Joomla modules',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                        'description' => 'List site or administrator modules',
                    ],
                ],
            ],
            'annotations' => [
                'title' => 'List Modules',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'get_module_by_id',
            'description' => 'Retrieve a Joomla module by ID',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Module ID'],
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                    ],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'Get Module',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'get_menu_item',
            'description' => 'Retrieve a Joomla menu item by ID',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Menu item ID'],
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                    ],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'Get Menu Item',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'create_menu_item',
            'description' => 'Create a new Joomla menu item',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Menu item title'],
                    'menutype' => ['type' => 'string', 'description' => 'Menu type alias (e.g. "mainmenu")'],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['component', 'url', 'alias', 'separator', 'heading'],
                        'default' => 'component',
                        'description' => 'Menu item type',
                    ],
                    'link' => ['type' => 'string', 'description' => 'URL or component link (e.g. "index.php?option=com_content&view=article&id=1"). For component menu items, include all required query parameters (such as the article id) in the link.'],
                    'component_id' => ['type' => 'integer', 'description' => 'Component ID (required for "component" type items)'],
                    'parent_id' => ['type' => 'integer', 'default' => 1, 'description' => 'Parent menu item ID (1 = root)'],
                    'published' => [
                        'type' => 'integer',
                        'enum' => [0, 1],
                        'default' => 1,
                        'description' => 'Published state',
                    ],
                    'access' => ['type' => 'integer', 'default' => 1, 'description' => 'Access level ID'],
                    'language' => ['type' => 'string', 'default' => '*', 'description' => 'Language code or "*" for all'],
                    'alias' => ['type' => 'string', 'description' => 'URL alias'],
                    'note' => ['type' => 'string', 'description' => 'Admin note'],
                    'browserNav' => [
                        'type' => 'integer',
                        'enum' => [0, 1, 2],
                        'default' => 0,
                        'description' => 'Target window (0 = parent, 1 = new window, 2 = new without navigation)',
                    ],
                    'home' => ['type' => 'integer', 'enum' => [0, 1], 'default' => 0, 'description' => 'Set as default page'],
                    'params' => ['type' => 'object', 'description' => 'Menu item parameters'],
                    'request' => [
                        'type' => 'object',
                        'description' => 'Request parameters required by the selected component view (e.g. {"id": 2} for a single article menu item linking to com_content article view)',
                    ],
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                    ],
                ],
                'required' => ['title', 'menutype', 'type'],
            ],
            'annotations' => [
                'title' => 'Create Menu Item',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => false,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'update_menu_item',
            'description' => 'Update an existing Joomla menu item',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Menu item ID'],
                    'menu_item' => [
                        'type' => 'object',
                        'description' => 'Menu item fields to update',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'alias' => ['type' => 'string'],
                            'link' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'published' => ['type' => 'integer'],
                            'access' => ['type' => 'integer'],
                            'language' => ['type' => 'string'],
                            'parent_id' => ['type' => 'integer'],
                            'menutype' => ['type' => 'string'],
                            'browserNav' => ['type' => 'integer'],
                            'home' => ['type' => 'integer'],
                            'params' => ['type' => 'object'],
                            'request' => ['type' => 'object'],
                            'note' => ['type' => 'string'],
                        ],
                    ],
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'default' => 'site',
                    ],
                ],
                'required' => ['id', 'menu_item'],
            ],
            'annotations' => [
                'title' => 'Update Menu Item',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_media',
            'description' => 'List Joomla media files and folders. Paths may include an adapter prefix (e.g. "local-images:/banners"); without one, "local-images" (the /images folder) is assumed.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Folder path to list (default: media root)'],
                    'search' => ['type' => 'string', 'description' => 'Search term filtering file/folder names'],
                    'include_url' => ['type' => 'boolean', 'default' => true, 'description' => 'Include a public URL attribute on file entries'],
                    'include_temp' => ['type' => 'boolean', 'default' => false, 'description' => 'Include a temporary URL attribute on file entries'],
                ],
            ],
            'annotations' => [
                'title' => 'List Media',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'get_media',
            'description' => 'Retrieve a single Joomla media file or folder by path',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'File or folder path (e.g. "banners/logo.png" or "local-images:/banners/logo.png")'],
                    'include_content' => ['type' => 'boolean', 'default' => false, 'description' => 'Include the file contents as base64 (omitted by default to avoid large responses)'],
                    'include_url' => ['type' => 'boolean', 'default' => true, 'description' => 'Include a public URL attribute'],
                ],
                'required' => ['path'],
            ],
            'annotations' => [
                'title' => 'Get Media',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'upload_media',
            'description' => 'Upload a new Joomla media file. Provide either base64 "content" or a "source_url" the server will fetch and encode. Cannot overwrite an existing file (use update_media for that).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Destination path including filename (e.g. "banners/logo.png")'],
                    'content' => ['type' => 'string', 'description' => 'Base64-encoded file contents'],
                    'source_url' => ['type' => 'string', 'description' => 'URL the server will download and base64-encode'],
                ],
                'required' => ['path'],
            ],
            'annotations' => [
                'title' => 'Upload Media',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => false,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'create_media_folder',
            'description' => 'Create a new folder in the Joomla media library',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Folder path including the new folder name (e.g. "banners/2026")'],
                ],
                'required' => ['path'],
            ],
            'annotations' => [
                'title' => 'Create Media Folder',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => false,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'update_media',
            'description' => 'Rename, move or replace the contents of an existing Joomla media file or folder. Provide at least one of "new_path", "content" or "source_url".',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Current file or folder path'],
                    'new_path' => ['type' => 'string', 'description' => 'New path to move or rename to'],
                    'content' => ['type' => 'string', 'description' => 'Replacement file contents as base64'],
                    'source_url' => ['type' => 'string', 'description' => 'URL the server will download and base64-encode as the replacement contents'],
                ],
                'required' => ['path'],
            ],
            'annotations' => [
                'title' => 'Update Media',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'delete_media',
            'description' => 'Delete a Joomla media file or folder by path',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'File or folder path'],
                ],
                'required' => ['path'],
            ],
            'annotations' => [
                'title' => 'Delete Media',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_content_languages',
            'description' => 'List Joomla content languages (the language tags assignable to articles, menu items, etc.)',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'published' => [
                        'type' => 'integer',
                        'enum' => [0, 1],
                        'description' => 'Filter by published state',
                    ],
                ],
            ],
            'annotations' => [
                'title' => 'List Content Languages',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'get_content_language',
            'description' => 'Retrieve a Joomla content language by ID',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Content language ID'],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'Get Content Language',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'create_content_language',
            'description' => 'Create a new Joomla content language',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'lang_code' => ['type' => 'string', 'description' => 'Language code (e.g. "en-GB")'],
                    'title' => ['type' => 'string', 'description' => 'Display title (e.g. "English (United Kingdom)")'],
                    'title_native' => ['type' => 'string', 'description' => 'Native name (e.g. "English")'],
                    'sef' => ['type' => 'string', 'description' => 'SEF prefix (e.g. "en")'],
                    'image' => ['type' => 'string', 'description' => 'Flag image identifier (e.g. "en")'],
                    'description' => ['type' => 'string'],
                    'metakey' => ['type' => 'string'],
                    'metadesc' => ['type' => 'string'],
                    'sitename' => ['type' => 'string', 'description' => 'Optional site name override for this language'],
                    'published' => ['type' => 'integer', 'enum' => [0, 1], 'default' => 1],
                    'access' => ['type' => 'integer', 'default' => 1],
                    'ordering' => ['type' => 'integer'],
                ],
                'required' => ['lang_code', 'title', 'title_native', 'sef'],
            ],
            'annotations' => [
                'title' => 'Create Content Language',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => false,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'update_content_language',
            'description' => 'Update an existing Joomla content language',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Content language ID'],
                    'language' => [
                        'type' => 'object',
                        'description' => 'Content language fields to update',
                        'properties' => [
                            'lang_code' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'title_native' => ['type' => 'string'],
                            'sef' => ['type' => 'string'],
                            'image' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'metakey' => ['type' => 'string'],
                            'metadesc' => ['type' => 'string'],
                            'sitename' => ['type' => 'string'],
                            'published' => ['type' => 'integer'],
                            'access' => ['type' => 'integer'],
                            'ordering' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'required' => ['id', 'language'],
            ],
            'annotations' => [
                'title' => 'Update Content Language',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'delete_content_language',
            'description' => 'Delete a Joomla content language by ID',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Content language ID'],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'Delete Content Language',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_installed_languages',
            'description' => 'List languages installed on the Joomla site (both site and administrator clients). Read-only view of `#__extensions` plus the application default language tag.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'client' => [
                        'type' => 'string',
                        'enum' => ['site', 'administrator'],
                        'description' => 'Filter to a single client; omit to return both',
                    ],
                ],
            ],
            'annotations' => [
                'title' => 'List Installed Languages',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_article_associations',
            'description' => 'List the cross-language associations for a Joomla article. Returns the sibling articles that share an association key in context "com_content.item".',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Primary article ID'],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'List Article Associations',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'set_article_associations',
            'description' => 'Set the cross-language associations for a Joomla article. Each item in `associated_ids` must use a different language from the primary article and from each other. Passing an empty `associated_ids` clears all associations for the primary.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Primary article ID'],
                    'associated_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => 'Article IDs to associate with the primary (one per other language)',
                    ],
                ],
                'required' => ['id', 'associated_ids'],
            ],
            'annotations' => [
                'title' => 'Set Article Associations',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'list_menu_item_associations',
            'description' => 'List the cross-language associations for a Joomla site menu item. Returns the sibling menu items that share an association key in context "com_menus.item".',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Primary menu item ID'],
                ],
                'required' => ['id'],
            ],
            'annotations' => [
                'title' => 'List Menu Item Associations',
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);

        $this->register([
            'name' => 'set_menu_item_associations',
            'description' => 'Set the cross-language associations for a Joomla site menu item. Each item in `associated_ids` must use a different language from the primary menu item and from each other. Passing an empty `associated_ids` clears all associations for the primary. Only applies to site (client_id=0) menu items.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Primary menu item ID'],
                    'associated_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => 'Menu item IDs to associate with the primary (one per other language)',
                    ],
                ],
                'required' => ['id', 'associated_ids'],
            ],
            'annotations' => [
                'title' => 'Set Menu Item Associations',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ]);
    }

    public function register(array $tool): void
    {
        $this->tools[$tool['name']] = $tool;
    }

    public function setExecutor(string $name, callable $executor): void
    {
        $this->executors[$name] = $executor;
    }

    public function execute(string $name, array $params): mixed
    {
        if (!isset($this->executors[$name])) {
            throw new \RuntimeException("No executor registered for tool '{$name}'");
        }

        return ($this->executors[$name])($params);
    }

    public function hasExecutor(string $name): bool
    {
        return isset($this->executors[$name]);
    }

    public function get(string $name): ?array
    {
        return $this->tools[$name] ?? null;
    }

    public function getAll(): array
    {
        return array_values($this->tools);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}

