<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\Registry\Registry;
use Psr\Container\ContainerInterface;

class McpserverComponent extends MVCComponent implements BootableExtensionInterface
{
    private static ?ContainerInterface $serviceContainer = null;

    public function boot(ContainerInterface $container): void
    {
        self::$serviceContainer = $container;
    }

    public static function getServiceContainer(): ?ContainerInterface
    {
        return self::$serviceContainer;
    }

    public function getParams(): Registry
    {
        return ComponentHelper::getParams('com_mcpserver');
    }
}
