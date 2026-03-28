<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Mcpserver\Administrator\Controller\RpcHandlerTrait;

class RpcController extends BaseController
{
    use RpcHandlerTrait;
}
