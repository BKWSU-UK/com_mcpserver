<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterBase;

class McpserverRouter extends RouterBase
{
    public function build(&$query): array
    {
        $segments = [];
        
        if (isset($query['task'])) {
            $segments[] = $query['task'];
            unset($query['task']);
        }
        
        return $segments;
    }

    public function parse(&$segments): array
    {
        $vars = [];
        
        if (!empty($segments[0])) {
            $vars['task'] = $segments[0];
        }
        
        return $vars;
    }
}

