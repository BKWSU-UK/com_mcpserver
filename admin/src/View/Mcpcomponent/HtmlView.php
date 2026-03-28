<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\View\Mcpcomponent;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * MCP Component Landing View
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array
     */
    public $mcpConfig;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template
     * @return  void
     */
    public function display($tpl = null)
    {
        ToolbarHelper::title('MCP Server', 'mcp');
        ToolbarHelper::preferences('com_mcpserver');
        
        $this->mcpConfig = $this->generateMcpConfig();
        
        parent::display($tpl);
    }

    /**
     * Generate MCP configuration for clients
     *
     * @return array
     */
    private function generateMcpConfig(): array
    {
        $params = ComponentHelper::getParams('com_mcpserver');
        
        // Determine the frontend base URL
        $uri = Uri::getInstance();
        $scheme = $uri->getScheme() ?: 'http';
        $host = $uri->getHost() ?: 'localhost';
        $port = $uri->getPort();
        
        $baseUrl = $scheme . '://' . $host;
        if ($port && !in_array((int)$port, [80, 443])) {
            $baseUrl .= ':' . $port;
        }
        
        // Remove administrator suffix if present
        $basePath = Uri::base(true);
        $baseUrl .= rtrim(str_replace('/administrator', '', $basePath), '/');
        
        $rpcUrl = $baseUrl . '/index.php?option=com_mcpserver&task=rpc.handle';
        $token = (string) $params->get('mcp_bearer_token', '');
        
        $args = [
            '-s',
            '-X',
            'POST',
            $rpcUrl,
            '-H',
            'Content-Type: application/json'
        ];

        if ($params->get('require_auth', 0) && $token !== '') {
            $args[] = '-H';
            $args[] = 'Authorization: Bearer <YOUR_TOKEN>';
        }

        // Add the -d @- to read from stdin for MCP stdio transport
        $args[] = '-d';
        $args[] = '@-';

        $config = [
            'mcpServers' => [
                'joomla' => [
                    'command' => 'curl',
                    'args' => $args
                ]
            ]
        ];

        $maskedToken = '';
        if ($token !== '') {
            $maskedToken = strlen($token) > 4
                ? str_repeat('*', strlen($token) - 4) . substr($token, -4)
                : str_repeat('*', strlen($token));
        }

        return [
            'json' => json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'url' => $rpcUrl,
            'token' => $token,
            'maskedToken' => $maskedToken,
        ];
    }
}
