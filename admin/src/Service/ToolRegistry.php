<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

class ToolRegistry
{
    private array $tools = [];

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
                            'articletext' => ['type' => 'string'],
                            'catid' => ['type' => 'integer'],
                            'language' => ['type' => 'string'],
                            'state' => ['type' => 'integer'],
                        ],
                        'required' => ['title', 'catid'],
                    ],
                ],
                'required' => ['article'],
            ],
        ]);

        $this->register([
            'name' => 'update_article',
            'description' => 'Update an existing Joomla article',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Article ID'],
                    'article' => ['type' => 'object', 'description' => 'Article data to update'],
                ],
                'required' => ['id', 'article'],
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
        ]);
    }

    public function register(array $tool): void
    {
        $this->tools[$tool['name']] = $tool;
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

