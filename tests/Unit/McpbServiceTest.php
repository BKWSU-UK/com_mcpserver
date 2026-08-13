<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Tests\Unit;

defined('_JEXEC') or die;

use Joomla\Component\Mcpserver\Administrator\Service\McpbService;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

class McpbServiceTest extends TestCase
{
    private string $templateDir;

    protected function setUp(): void
    {
        $this->templateDir = JPATH_ADMINISTRATOR . '/components/com_mcpserver/mcpb';
        mkdir($this->templateDir . '/server', 0777, true);

        file_put_contents($this->templateDir . '/manifest.json', json_encode([
            'manifest_version' => '0.3',
            'name' => 'joomla-mcp-server',
            'display_name' => 'MCP Server for Joomla',
            'user_config' => [
                'endpoint_url' => ['type' => 'string'],
            ],
        ]));
        file_put_contents($this->templateDir . '/icon.png', 'icon');
        file_put_contents($this->templateDir . '/server/mcp-http-bridge.js', 'bridge');
    }

    protected function tearDown(): void
    {
        foreach ([
            $this->templateDir . '/server/mcp-http-bridge.js',
            $this->templateDir . '/icon.png',
            $this->templateDir . '/manifest.json',
        ] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->templateDir . '/server')) {
            rmdir($this->templateDir . '/server');
        }
        if (is_dir($this->templateDir)) {
            rmdir($this->templateDir);
        }
    }

    public function testBuildBundleThrowsWhenATemporaryFileCannotBeCreated(): void
    {
        $service = new class (new Registry(['base_url' => 'https://example.test'])) extends McpbService {
            protected function createTempFile(): string|false
            {
                return false;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not create a temporary file for the bundle archive.');

        $service->buildBundle('Example Site');
    }
}
