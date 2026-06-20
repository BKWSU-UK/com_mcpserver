<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

class MonologFactory
{
    public static function createComponentLogger(string $channel = 'mcpserver', string $serverName = ''): Logger
    {
        $logger = new Logger($channel);
        $handler = new StreamHandler(self::resolveLogPath(), Level::Info);
        $handler->setFormatter(new JsonFormatter());
        $logger->pushHandler($handler);

        if ($serverName !== '') {
            $logger->pushProcessor(function (LogRecord $record) use ($serverName): LogRecord {
                $record->extra['server'] = $serverName;
                return $record;
            });
        }

        return $logger;
    }

    /**
     * Resolve the file Monolog should write to, preferring Joomla's configured
     * log folder (the `log_path` global config), then the JPATH_LOGS constant,
     * and only falling back to the system temp dir when neither is usable.
     */
    private static function resolveLogPath(): string
    {
        $candidates = [];

        try {
            $configured = (string) Factory::getApplication()->get('log_path');
            if ($configured !== '') {
                $candidates[] = $configured;
            }
        } catch (\Throwable $e) {
            // Application not available (e.g. CLI bootstrap) — fall through.
        }

        if (defined('JPATH_LOGS')) {
            $candidates[] = JPATH_LOGS;
        }

        foreach ($candidates as $dir) {
            $dir = rtrim($dir, '/\\');
            if (is_dir($dir) && is_writable($dir)) {
                return $dir . '/com_mcpserver.log';
            }
        }

        return sys_get_temp_dir() . '/com_mcpserver.log';
    }
}


