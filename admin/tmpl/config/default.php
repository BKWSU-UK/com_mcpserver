<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

HTMLHelper::_('behavior.formvalidator');

?>
<div class="container">
	<h2><?php echo Text::_('COM_MCPSERVER_CONFIG_TITLE'); ?></h2>
	<p><?php echo Text::_('COM_MCPSERVER_CONFIG_DESC'); ?></p>
</div>

