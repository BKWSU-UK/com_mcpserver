<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class RpcController extends BaseController
{
    use RpcHandlerTrait;
}
