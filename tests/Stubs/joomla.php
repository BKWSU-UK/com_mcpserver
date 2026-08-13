<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\CMS {
    class Factory
    {
        public static ?object $application = null;

        public static function getApplication(): object
        {
            if (self::$application === null) {
                throw new \RuntimeException('Test application has not been installed');
            }

            return self::$application;
        }

        public static function reset(): void
        {
            self::$application = null;
        }
    }
}

namespace Joomla\CMS\Cache {
    class Cache
    {
        /** @var list<string> */
        public static array $availableGroups = [];

        /** @var list<string> */
        public static array $cleaned = [];

        public function __construct(array $options = [])
        {
        }

        public function getAll(): array
        {
            $items = [];
            foreach (self::$availableGroups as $group) {
                $items[] = (object) ['group' => $group];
            }

            return $items;
        }

        public function clean(string $group): bool
        {
            self::$cleaned[] = $group;

            return true;
        }

        public static function reset(): void
        {
            self::$availableGroups = [];
            self::$cleaned = [];
        }
    }
}

namespace Joomla\CMS\Event\Cache {
    class AfterPurgeEvent
    {
        public static ?string $lastName = null;

        /** @var array<string, mixed>|null */
        public static ?array $lastArguments = null;

        public function __construct(string $name, array $arguments = [])
        {
            if (array_key_exists('subject', $arguments)) {
                $value = $arguments['subject'];
                if (!\is_string($value) || $value === '') {
                    throw new \TypeError(
                        'AfterPurgeEvent::setSubject(): Argument #1 ($value) must be of type string, '
                        . ($value === '' ? 'empty string' : \get_debug_type($value)) . ' given'
                    );
                }
            }

            self::$lastName = $name;
            self::$lastArguments = $arguments;
        }

        public static function reset(): void
        {
            self::$lastName = null;
            self::$lastArguments = null;
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static string $root = 'https://example.test/';

        public static function root(bool $pathonly = false): string
        {
            return self::$root;
        }
    }
}

namespace Joomla\Registry {
    class Registry
    {
        /**
         * @param  array<string, mixed>  $data
         */
        public function __construct(private array $data = [])
        {
        }

        public function get(string $path, mixed $default = null): mixed
        {
            return $this->data[$path] ?? $default;
        }
    }
}
