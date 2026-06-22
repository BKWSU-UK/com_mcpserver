<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

class PolicyService
{
    public function __construct(private readonly Registry $params)
    {
    }

    public function isToolAllowed(string $toolName, ?int $userId = null): bool
    {
        return !in_array($toolName, $this->getDisabledTools(), true);
    }

    /**
     * @return list<string>
     */
    public function getDisabledTools(): array
    {
        return $this->parseToolList((string) $this->params->get('disabled_tools', ''));
    }

    /**
     * @return list<string>
     */
    private function parseToolList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $tools = preg_split('/[\s,]+/', $value) ?: [];

        return array_values(array_filter(array_map('trim', $tools)));
    }
}
