<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

class PromptRegistry
{
    private array $prompts = [];
    private array $builders = [];

    public function __construct()
    {
        $this->registerDefaultPrompts();
    }

    private function registerDefaultPrompts(): void
    {
        $this->register([
            'name' => 'draft-article',
            'title' => 'Draft article',
            'description' => 'Draft a new Joomla article on a topic, matching the tone of recent published articles.',
            'arguments' => [
                [
                    'name' => 'topic',
                    'description' => 'Subject of the article to draft',
                    'required' => true,
                ],
                [
                    'name' => 'category',
                    'description' => 'Optional category name or ID to place the article in',
                    'required' => false,
                ],
            ],
        ]);

        $this->register([
            'name' => 'seo-audit-article',
            'title' => 'SEO audit article',
            'description' => 'Audit a published article for SEO and suggest update_article changes.',
            'arguments' => [
                [
                    'name' => 'article_id',
                    'description' => 'ID of the article to audit',
                    'required' => true,
                ],
            ],
        ]);

        $this->register([
            'name' => 'translate-article',
            'title' => 'Translate article',
            'description' => 'Translate a published article into a target language and associate the translation.',
            'arguments' => [
                [
                    'name' => 'article_id',
                    'description' => 'ID of the source article to translate',
                    'required' => true,
                ],
                [
                    'name' => 'target_language',
                    'description' => 'Target content-language tag (e.g. fr-FR)',
                    'required' => true,
                ],
            ],
        ]);
    }

    public function register(array $prompt): void
    {
        $this->prompts[$prompt['name']] = $prompt;
    }

    public function setBuilder(string $name, callable $builder): void
    {
        $this->builders[$name] = $builder;
    }

    public function build(string $name, array $arguments): array
    {
        if (!isset($this->builders[$name])) {
            throw new \RuntimeException("No builder registered for prompt '{$name}'");
        }

        return ($this->builders[$name])($arguments);
    }

    public function hasBuilder(string $name): bool
    {
        return isset($this->builders[$name]);
    }

    public function get(string $name): ?array
    {
        return $this->prompts[$name] ?? null;
    }

    public function getAll(): array
    {
        return array_values($this->prompts);
    }

    public function has(string $name): bool
    {
        return isset($this->prompts[$name]);
    }
}
