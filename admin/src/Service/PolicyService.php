<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class PolicyService
{
    public function isToolAllowed(string $toolName, ?int $userId = null): bool
    {
        // Placeholder: read from component params later
        return true;
    }
}


