<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

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
        $handler = new StreamHandler(defined('JPATH_LOGS') ? JPATH_LOGS . '/com_mcpserver.log' : sys_get_temp_dir() . '/com_mcpserver.log', Level::Info);
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
}


