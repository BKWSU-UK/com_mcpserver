<?php

declare(strict_types=1);

defined('_JEXEC') or die;

$autoload = __DIR__ . '/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}
