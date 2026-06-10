<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Mcpserver\Administrator\Extension\McpserverComponent;
use Joomla\Component\Mcpserver\Administrator\Service\MetricsService;

/**
 * Metrics dashboard view.
 */
class HtmlView extends BaseHtmlView
{
    /** @var array */
    public $summary;

    /** @var array */
    public $topTools;

    /** @var array */
    public $topMethods;

    /** @var array */
    public $perDay;

    /** @var array */
    public $recent;

    /** @var bool */
    public $metricsEnabled;

    /**
     * Display the view.
     *
     * @param   string  $tpl  Template
     * @return  void
     */
    public function display($tpl = null)
    {
        $metrics = $this->getMetricsService();

        // Belt-and-braces cleanup so the log stays within the retention window
        // even on sites that receive little traffic.
        $metrics->prune();

        $this->metricsEnabled = $metrics->isEnabled();
        $this->summary        = $metrics->getSummary();
        $this->topTools       = $metrics->getTopTools(10);
        $this->topMethods     = $metrics->getTopMethods(10);
        $this->perDay         = $metrics->getRequestsPerDay(14);
        $this->recent         = $metrics->getRecentRequests(25);

        ToolbarHelper::title(Text::_('COM_MCPSERVER_DASHBOARD_TITLE'), 'chart');
        ToolbarHelper::preferences('com_mcpserver');

        parent::display($tpl);
    }

    /**
     * Resolve MetricsService from the DI container, with a direct fallback
     * mirroring the pattern used by the RPC controllers.
     */
    private function getMetricsService(): MetricsService
    {
        $container = McpserverComponent::getServiceContainer();
        if ($container !== null && $container->has(MetricsService::class)) {
            return $container->get(MetricsService::class);
        }

        return new MetricsService(ComponentHelper::getParams('com_mcpserver'));
    }
}
