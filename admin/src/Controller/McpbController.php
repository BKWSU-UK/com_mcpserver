<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Component\Mcpserver\Administrator\Extension\McpserverComponent;
use Joomla\Component\Mcpserver\Administrator\Service\McpbService;

class McpbController extends BaseController
{
    /**
     * Stream a personalised Claude Desktop extension bundle to the browser.
     */
    public function download(): void
    {
        if (!Session::checkToken('get')) {
            $this->setRedirect('index.php?option=com_mcpserver', Text::_('JINVALID_TOKEN'), 'error');
            return;
        }

        try {
            $bundlePath = $this->getMcpbService()->buildBundle((string) $this->app->get('sitename', ''));
        } catch (\Throwable $e) {
            $this->setRedirect(
                'index.php?option=com_mcpserver',
                Text::sprintf('COM_MCPSERVER_MCPB_ERROR', $e->getMessage()),
                'error'
            );
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="com_mcpserver.mcpb"');
        header('Content-Length: ' . (string) filesize($bundlePath));
        header('Cache-Control: no-store');
        readfile($bundlePath);
        unlink($bundlePath);
        $this->app->close();
    }

    /**
     * Resolve McpbService from the DI container, with a direct fallback
     * mirroring the pattern used by the dashboard view.
     */
    private function getMcpbService(): McpbService
    {
        $container = McpserverComponent::getServiceContainer();
        if ($container !== null && $container->has(McpbService::class)) {
            return $container->get(McpbService::class);
        }

        return new McpbService(ComponentHelper::getParams('com_mcpserver'));
    }
}
